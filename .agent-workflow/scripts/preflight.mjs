// Read-only machine checks a session needs before admitting tasks. Each check reports ok or FAIL
// with the fix a person applies between sessions; a session never repairs anything itself.
import { execFileSync } from 'node:child_process';
import { accessSync, constants, writeFileSync } from 'node:fs';
import { basename, dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const MIN_FREE_GIB = 5;
const MIN_NODE_MAJOR = 22;
const BASE_REF = 'refs/remotes/origin/main';

const failure = (reason) => Object.assign(new Error(reason), { reason });

function check(id, fix, observe) {
    try {
        return { id, ok: true, evidence: observe() };
    } catch (error) {
        return {
            id,
            ok: false,
            evidence: error.reason ?? 'Command failed or is unavailable',
            fix,
        };
    }
}

export function requiredPhp(composerJson) {
    return (
        JSON.parse(composerJson).require?.php?.match(/(\d+\.\d+)/)?.[1] ?? null
    );
}

export function runChecks(root, { execute, writable }) {
    const worktreeRoot = join(dirname(root), `${basename(root)}-worktrees`);
    const php = join(worktreeRoot, '.bin/php');
    const pdo = (dsn) => `new PDO("${dsn}", "postgres", ""); echo "ok";`;
    return [
        check(
            'gh-auth',
            'Run `gh auth login` for the account that pushes to origin.',
            () => {
                execute('gh', ['auth', 'status'], root);
                return 'gh auth status exit 0';
            },
        ),
        check(
            'disk',
            `Free at least ${MIN_FREE_GIB} GiB on the volume holding the worktrees.`,
            () => {
                const freeKib = Number(
                    execute('df', ['-Pk', root], root)
                        .trim()
                        .split('\n')
                        .pop()
                        .split(/\s+/)[3],
                );
                const freeGib = Math.floor(freeKib / 1024 / 1024);
                if (!(freeGib >= MIN_FREE_GIB))
                    throw failure(`Only ${freeGib} GiB free`);
                return `${freeGib} GiB free`;
            },
        ),
        check(
            'worktree-root',
            `Create ${worktreeRoot} and allow the host to write there.`,
            () => {
                if (!writable(worktreeRoot))
                    throw failure(`${worktreeRoot} is missing or not writable`);
                return `${worktreeRoot} is writable`;
            },
        ),
        check(
            'php',
            `Link ${php} to the PHP binary composer.json requires (Herd keeps one per version).`,
            () => {
                const required = requiredPhp(
                    execute('git', ['show', `${BASE_REF}:composer.json`], root),
                );
                const actual = execute(php, ['-v'], root).match(
                    /PHP (\d+\.\d+)/,
                )?.[1];
                if (!required || actual !== required)
                    throw failure(
                        `composer.json requires PHP ${required ?? 'unknown'}; ${php} reports ${actual ?? 'unknown'}`,
                    );
                return `${php} reports PHP ${actual}`;
            },
        ),
        check(
            'postgres',
            'Start the local Postgres on 127.0.0.1:5432 with role postgres and no password.',
            () => {
                execute(
                    php,
                    [
                        '-r',
                        pdo('pgsql:host=127.0.0.1;port=5432;dbname=postgres'),
                    ],
                    root,
                );
                return 'Connected to 127.0.0.1:5432 as postgres';
            },
        ),
        check('redis', 'Start the local Redis on 127.0.0.1:6379.', () => {
            execute(
                php,
                [
                    '-r',
                    '$s = fsockopen("127.0.0.1", 6379, $e, $m, 2); if (!$s) exit(1); echo "ok";',
                ],
                root,
            );
            return 'Connected to 127.0.0.1:6379';
        }),
        check(
            'node',
            `Install Node ${MIN_NODE_MAJOR} or later, as CI uses.`,
            () => {
                const version = execute('node', ['--version'], root).trim();
                if (
                    Number(version.replace(/^v/, '').split('.')[0]) <
                    MIN_NODE_MAJOR
                )
                    throw failure(
                        `node ${version} is older than ${MIN_NODE_MAJOR}`,
                    );
                return `node ${version}`;
            },
        ),
        check(
            'pnpm',
            'Install pnpm (the lockfile is pnpm-lock.yaml).',
            () => `pnpm ${execute('pnpm', ['--version'], root).trim()}`,
        ),
        check(
            'ci',
            'Restore the aggregate `ci` job in .github/workflows/tests.yml.',
            () => {
                const workflow = execute(
                    'git',
                    ['show', `${BASE_REF}:.github/workflows/tests.yml`],
                    root,
                );
                if (!/^ {2}ci:\s*$/m.test(workflow))
                    throw failure('tests.yml has no `ci` job at the base');
                return `tests.yml at ${BASE_REF} has the aggregate ci job`;
            },
        ),
    ];
}

export function preflightReport(root, dependencies, now = new Date()) {
    const checks = runChecks(root, dependencies);
    return {
        version: 1,
        verified_at: now.toISOString(),
        base_sha: dependencies
            .execute('git', ['rev-parse', '--verify', BASE_REF], root)
            .trim(),
        ready: checks.every((entry) => entry.ok),
        checks,
    };
}

if (
    process.argv[1] &&
    resolve(process.argv[1]) === fileURLToPath(import.meta.url)
) {
    const [flag, out] = process.argv.slice(2);
    const root = dirname(
        execFileSync(
            'git',
            ['rev-parse', '--path-format=absolute', '--git-common-dir'],
            { encoding: 'utf8' },
        ).trim(),
    );
    const report = preflightReport(root, {
        execute: (file, args, cwd) =>
            execFileSync(file, args, {
                cwd,
                encoding: 'utf8',
                timeout: 60000,
                stdio: ['ignore', 'pipe', 'pipe'],
            }),
        writable: (path) => {
            try {
                accessSync(path, constants.W_OK);
                return true;
            } catch {
                return false;
            }
        },
    });
    for (const entry of report.checks)
        console.log(
            `${entry.ok ? 'ok  ' : 'FAIL'} ${entry.id}: ${entry.evidence}${entry.ok ? '' : `\n     fix: ${entry.fix}`}`,
        );
    if (flag === '--out' && out)
        writeFileSync(resolve(out), JSON.stringify(report, null, 2) + '\n', {
            mode: 0o600,
        });
    console.log(
        report.ready
            ? 'Environment ready.'
            : 'Environment NOT ready: affected tasks are needs-environment.',
    );
    if (!report.ready) process.exitCode = 1;
}
