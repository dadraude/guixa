// Recommends the next compatible batch for one feature. Read-only: it never fetches, claims or
// writes the tracker. The coordinator writes the tracker snapshot from the connected Linear tools:
// { "version": 1, "observed_at": ISO, "complete": true, "feature": "NNN-slug",
//   "issues": [{ "id": "DRA-NN", "task": "Tnn" | null, "status", "priority", "labels", "blockers" }] }
import { execFileSync } from 'node:child_process';
import { createHash, randomUUID } from 'node:crypto';
import { existsSync, readFileSync, renameSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { selectTasks } from '../../skills/agent-workflow/scripts/select-tasks.mjs';
import { loadCatalog } from './task-catalog.mjs';

const BASE_REF = 'refs/remotes/origin/main';
const MERGED_LIMIT = 500;
const OPEN_LIMIT = 100;
const PR_FILES_LIMIT = 100;
const POLICY = { capacity: 3, largeLimit: 2 };

const run = (command, args, cwd) =>
    execFileSync(command, args, {
        cwd,
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'pipe'],
    });

export const issueOfBranch = (branch) =>
    branch?.match(/(?:^|\/)dra-(\d+)(?:-|$)/)?.[1];

export function catalogDigest(tasks) {
    return createHash('sha256').update(JSON.stringify(tasks)).digest('hex');
}

function isAncestor(root, commit, base) {
    try {
        run('git', ['merge-base', '--is-ancestor', commit, base], root);
        return true;
    } catch {
        return false;
    }
}

function worktrees(root) {
    const entries = [];
    let current = null;
    for (const line of run(
        'git',
        ['worktree', 'list', '--porcelain'],
        root,
    ).split('\n')) {
        if (line.startsWith('worktree ')) {
            current = { path: line.slice(9) };
            entries.push(current);
        } else if (line.startsWith('branch ') && current)
            current.branch = line.slice(7).replace('refs/heads/', '');
    }
    return entries.filter((entry) => resolve(entry.path) !== resolve(root));
}

function changedPaths(worktree, base) {
    const status = run(
        'git',
        ['status', '--porcelain', '--untracked-files=all'],
        worktree,
    )
        .split('\n')
        .filter(Boolean)
        .map((line) => line.slice(3).split(' -> ').at(-1));
    const committed = run(
        'git',
        ['diff', '--name-only', `${base}...HEAD`],
        worktree,
    )
        .split('\n')
        .filter(Boolean);
    return [...new Set([...status, ...committed])];
}

function ownerRecord(worktree) {
    const path = join(worktree, '.agent-workflow/implementation-owner.json');
    if (!existsSync(path)) return null;
    return JSON.parse(readFileSync(path, 'utf8'));
}

export function collectGitEvidence(root, tracker) {
    const baseSha = run(
        'git',
        ['rev-parse', '--verify', BASE_REF],
        root,
    ).trim();
    const remoteSha = run(
        'git',
        ['ls-remote', 'origin', 'refs/heads/main'],
        root,
    ).split(/\s/)[0];
    const issueByNumber = new Map(
        tracker.issues.map((issue) => [issue.id.replace('DRA-', ''), issue]),
    );
    const integrated = {};
    const merged = JSON.parse(
        run(
            'gh',
            [
                'pr',
                'list',
                '--state',
                'merged',
                '--base',
                'main',
                '--limit',
                String(MERGED_LIMIT),
                '--json',
                'number,headRefName,mergeCommit',
            ],
            root,
        ),
    );
    const integratedIssues = new Set();
    for (const pr of merged) {
        const issue = issueByNumber.get(issueOfBranch(pr.headRefName));
        if (!issue?.task || !pr.mergeCommit?.oid) continue;
        if (!isAncestor(root, pr.mergeCommit.oid, baseSha)) continue;
        integrated[issue.task] = {
            pr: pr.number,
            merge_sha: pr.mergeCommit.oid,
        };
        integratedIssues.add(issue.id);
    }

    const reservations = [];
    const open = JSON.parse(
        run(
            'gh',
            [
                'pr',
                'list',
                '--state',
                'open',
                '--limit',
                String(OPEN_LIMIT),
                '--json',
                'number,headRefName,files',
            ],
            root,
        ),
    );
    for (const pr of open) {
        const number = issueOfBranch(pr.headRefName);
        const owner = number ? `DRA-${number}` : `pr#${pr.number}`;
        const complete = pr.files.length < PR_FILES_LIMIT;
        reservations.push({
            owner,
            paths: complete ? pr.files.map((file) => file.path) : null,
            source: `open PR #${pr.number}`,
        });
    }

    const active = [];
    for (const worktree of worktrees(root)) {
        const number = issueOfBranch(worktree.branch);
        const owner = number
            ? `DRA-${number}`
            : `worktree:${worktree.branch ?? worktree.path}`;
        if (integratedIssues.has(owner)) continue;
        const record = ownerRecord(worktree.path);
        const planned = record?.pipeline?.reservations ?? [];
        const paths = [
            ...new Set([...changedPaths(worktree.path, BASE_REF), ...planned]),
        ];
        if (paths.length)
            reservations.push({
                owner,
                paths,
                source: `worktree ${worktree.path}`,
            });
        if (record?.pipeline?.disposition === 'active')
            active.push({
                owner,
                task: issueByNumber.get(number)?.task,
                tier: record.pipeline.tier ?? 'L',
            });
    }

    return {
        version: 1,
        complete: tracker.complete === true,
        observed_at: tracker.observed_at,
        feature: tracker.feature,
        base: { sha: baseSha, remote_sha: remoteSha },
        issues: tracker.issues,
        integrated,
        merged_complete: merged.length < MERGED_LIMIT,
        reservations,
        active,
    };
}

export function validateReplay(evidence, { revision, digest, baseSha }) {
    if (
        evidence.catalog_revision !== revision ||
        evidence.catalog_digest !== digest
    )
        throw new Error(
            'Catalog changed since the evidence was collected; collect again',
        );
    if (evidence.base?.sha !== baseSha)
        throw new Error(
            'Local base moved since the evidence was collected; collect again',
        );
    return evidence;
}

function saveEvidence(file, evidence) {
    const temporary = `${file}.${randomUUID()}.tmp`;
    writeFileSync(temporary, JSON.stringify(evidence, null, 2) + '\n', {
        flag: 'wx',
        mode: 0o600,
    });
    renameSync(temporary, file);
}

function parseArguments(args) {
    const flags = new Set(['--json', '--working-tree']);
    const values = new Set([
        '--feature',
        '--tracker',
        '--evidence',
        '--save-evidence',
        '--limit',
        '--environment',
    ]);
    const options = {};
    for (let n = 0; n < args.length; n++) {
        const key = args[n];
        if (flags.has(key)) options[key] = true;
        else if (
            values.has(key) &&
            args[n + 1] &&
            !args[n + 1].startsWith('--')
        )
            options[key] = args[++n];
        else throw new Error(`Unknown or incomplete option ${key}`);
    }
    if (!options['--feature'])
        throw new Error('--feature NNN-slug is required');
    if (Boolean(options['--tracker']) === Boolean(options['--evidence']))
        throw new Error(
            'Choose --tracker FILE (live) or --evidence FILE (replay)',
        );
    if (options['--save-evidence'] && !options['--tracker'])
        throw new Error('--save-evidence requires --tracker');
    return options;
}

export function main(args) {
    const options = parseArguments(args);
    const root = dirname(
        run(
            'git',
            ['rev-parse', '--path-format=absolute', '--git-common-dir'],
            process.cwd(),
        ).trim(),
    );
    const feature = options['--feature'];
    const tasks = loadCatalog(root, feature, {
        workingTree: options['--working-tree'],
    });
    const revision = run('git', ['rev-parse', 'HEAD'], root).trim();
    const digest = catalogDigest(tasks);
    let evidence;
    if (options['--tracker']) {
        const tracker = JSON.parse(
            readFileSync(resolve(options['--tracker']), 'utf8'),
        );
        if (tracker.feature !== feature)
            throw new Error('Tracker snapshot is for another feature');
        evidence = {
            ...collectGitEvidence(root, tracker),
            catalog_revision: revision,
            catalog_digest: digest,
        };
        if (options['--save-evidence'])
            saveEvidence(resolve(options['--save-evidence']), evidence);
    } else {
        const baseSha = run(
            'git',
            ['rev-parse', '--verify', BASE_REF],
            root,
        ).trim();
        evidence = validateReplay(
            JSON.parse(readFileSync(resolve(options['--evidence']), 'utf8')),
            { revision, digest, baseSha },
        );
    }
    // Preflight output is applied at selection time and never saved into tracker evidence.
    if (options['--environment'])
        evidence = {
            ...evidence,
            environment: JSON.parse(
                readFileSync(resolve(options['--environment']), 'utf8'),
            ),
        };
    const limit = options['--limit'] ? Number(options['--limit']) : undefined;
    const report = selectTasks(tasks, evidence, { ...POLICY, limit });
    if (!options['--environment'])
        report.warnings.push(
            'No --environment preflight evidence: machine readiness is unverified',
        );
    report.feature = feature;
    report.mode = options['--tracker'] ? 'live' : 'replay';
    if (report.mode === 'replay')
        report.note =
            'EVIDENCE REPLAY: not claim authorization. ' + report.note;
    if (options['--working-tree'])
        report.note =
            'UNCOMMITTED CATALOG PREVIEW: do not dispatch. ' + report.note;
    if (options['--json']) {
        console.log(JSON.stringify(report, null, 2));
        return report;
    }
    console.log(
        `Next: ${report.next ?? 'none ready'}; selected: ${report.selected.join(', ') || 'none'}`,
    );
    for (const row of report.rows) {
        if (row.decision === 'integrated') continue;
        const pending = row.integration_pending.length
            ? `; integration pending: ${row.integration_pending.join(', ')}`
            : '';
        console.log(
            `${row.task}\t${row.issue ?? '-'}\t${row.tier}\t${row.decision}\t${row.reasons.join('; ')}${pending}`,
        );
    }
    for (const warning of report.warnings) console.log(`Warning: ${warning}`);
    console.log(report.note);
    return report;
}

if (
    process.argv[1] &&
    resolve(process.argv[1]) === fileURLToPath(import.meta.url)
) {
    try {
        main(process.argv.slice(2));
    } catch (error) {
        console.error(
            error.stderr
                ? `Read command failed: ${String(error.stderr).trim()}`
                : error.message,
        );
        process.exitCode = 1;
    }
}
