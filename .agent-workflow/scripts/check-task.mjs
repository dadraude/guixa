import { execFileSync, spawnSync } from 'node:child_process';
import { readFileSync, existsSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createHash } from 'node:crypto';
import { taskEnvironment } from './task-command.mjs';

const gitPaths = (cwd, ...args) =>
    execFileSync('git', args, { cwd, encoding: 'utf8' })
        .split('\0')
        .filter(Boolean);
const executableTest = (path) =>
    /(?:Test\.php|\.spec\.ts|\.test\.mjs|(?:^|\/)test_[^/]+\.py)$/.test(path);
const glob = (path, pattern) =>
    new RegExp(
        '^' +
            pattern
                .split('**')
                .map((part) =>
                    part
                        .split('*')
                        .map((piece) =>
                            piece
                                .replace(/[.+?^{}()|[\]\\]/g, '\\$&')
                                .replaceAll('$', '\\$'),
                        )
                        .join('[^/]*'),
                )
                .join('.*') +
            '$',
    ).test(path);

export function checkPlan(cwd, base, plan) {
    base = execFileSync('git', ['rev-parse', '--verify', base + '^{commit}'], {
        cwd,
        encoding: 'utf8',
    }).trim();
    const changed = [
        ...new Set([
            ...gitPaths(
                cwd,
                'diff',
                '--name-only',
                '-z',
                '--diff-filter=ACMR',
                base,
            ),
            ...gitPaths(
                cwd,
                'ls-files',
                '--others',
                '--exclude-standard',
                '-z',
            ),
        ]),
    ].sort((a, b) => (a < b ? -1 : a > b ? 1 : 0));
    if (!Array.isArray(plan.changedContracts) || !Array.isArray(plan.tests))
        throw new Error('Declare changedContracts and focused tests');
    if (!plan.changedContracts.length && !plan.noContractChangesReason?.trim())
        throw new Error('Explain why no contracts changed');
    const tests = new Set(plan.tests);
    for (const contract of plan.changedContracts) {
        if (
            !contract.name ||
            !contract.path ||
            !contract.evidence ||
            !contract.consumers?.length
        )
            throw new Error(
                'Changed contract needs evidence and consumer tests',
            );
        if (!changed.includes(contract.path))
            throw new Error('Contract path must occur in the diff');
        contract.consumers.forEach((path) => tests.add(path));
    }
    changed.filter(executableTest).forEach((path) => tests.add(path));
    for (const path of tests) {
        if (
            typeof path !== 'string' ||
            path.startsWith('/') ||
            path.split('/').includes('..') ||
            !executableTest(path) ||
            !existsSync(resolve(cwd, path))
        )
            throw new Error('Invalid focused test path');
    }
    const config = existsSync(resolve(cwd, 'vite.config.ts'))
        ? readFileSync(resolve(cwd, 'vite.config.ts'), 'utf8')
        : '';
    const fmt = config.match(/\bfmt:\s*\{([\s\S]*?)\n    \},?/);
    const literal =
        fmt?.[1].match(/ignorePatterns:\s*\[([\s\S]*?)\]/)?.[1] ?? '';
    const exclusions = [...literal.matchAll(/['"]([^'"]+)['"]/g)].map(
        (match) => match[1],
    );
    const supported =
        /\.(?:[cm]?jsx?|tsx?|vue|jsonc?|mdx?|html|ya?ml|css|scss)$/;
    const excluded = changed.filter(
        (path) =>
            supported.test(path) &&
            exclusions.some((pattern) => glob(path, pattern)),
    );
    const formatted = changed.filter(
        (path) => supported.test(path) && !excluded.includes(path),
    );
    const php = changed.filter((path) => path.endsWith('.php'));
    const commands = [];
    if (formatted.length)
        commands.push([
            'pnpm',
            'exec',
            'vp',
            'check',
            '--no-lint',
            ...formatted,
        ]);
    if (php.length) commands.push(['php', 'vendor/bin/pint', '--test', ...php]);
    const groups = { php: [], frontend: [], node: [], python: [] };
    for (const path of [...tests].sort((a, b) =>
        a < b ? -1 : a > b ? 1 : 0,
    )) {
        if (path.endsWith('.php')) groups.php.push(path);
        else if (path.endsWith('.ts')) groups.frontend.push(path);
        else if (path.endsWith('.mjs')) groups.node.push(path);
        else groups.python.push(path);
    }
    if (groups.php.length)
        commands.push(['php', 'artisan', 'test', ...groups.php]);
    if (groups.frontend.length)
        commands.push([
            'pnpm',
            'exec',
            'vp',
            'test',
            'run',
            ...groups.frontend,
        ]);
    if (groups.node.length) commands.push(['node', '--test', ...groups.node]);
    for (const path of groups.python)
        commands.push([
            'python3',
            '-m',
            'unittest',
            'discover',
            '-s',
            dirname(path),
            '-p',
            path.split('/').at(-1),
        ]);
    const fingerprint = createHash('sha256');
    for (const path of changed) {
        fingerprint.update(path + '\0');
        fingerprint.update(readFileSync(resolve(cwd, path)));
    }
    return {
        inputHash: fingerprint.digest('hex'),
        dirty: !!execFileSync(
            'git',
            ['status', '--porcelain', '--untracked-files=no'],
            { cwd, encoding: 'utf8' },
        ).trim(),
        head: execFileSync('git', ['rev-parse', 'HEAD'], {
            cwd,
            encoding: 'utf8',
        }).trim(),
        base,
        changed,
        tests: [...tests].sort((a, b) => (a < b ? -1 : a > b ? 1 : 0)),
        formatting: {
            selected: [...formatted, ...php],
            excluded,
            unsupported: changed.filter(
                (path) => !supported.test(path) && !path.endsWith('.php'),
            ),
        },
        commands,
    };
}

if (
    process.argv[1] &&
    resolve(process.argv[1]) === fileURLToPath(import.meta.url)
) {
    try {
        const [number, base, path, option] = process.argv.slice(2);
        if (!number || !base || !path || (option && option !== '--plan'))
            throw new Error(
                'Usage: node check-task.mjs <task-number> <base-sha> <check-plan.json> [--plan]',
            );
        const plan = checkPlan(
            process.cwd(),
            base,
            JSON.parse(readFileSync(path, 'utf8')),
        );
        if (option === '--plan') console.log(JSON.stringify(plan, null, 2));
        else {
            const env = taskEnvironment(number, process.cwd());
            const results = [];
            for (const [executable, ...args] of plan.commands) {
                const start = Date.now();
                const result = spawnSync(executable, args, {
                    env,
                    stdio: 'inherit',
                    shell: false,
                });
                results.push({
                    command: [executable, ...args],
                    durationMs: Date.now() - start,
                    passed: result.status === 0 && !result.error,
                });
                if (!results.at(-1).passed) {
                    process.exitCode = 1;
                    break;
                }
            }
            const after = checkPlan(
                process.cwd(),
                base,
                JSON.parse(readFileSync(path, 'utf8')),
            );
            if (
                after.head !== plan.head ||
                after.base !== plan.base ||
                after.inputHash !== plan.inputHash
            )
                throw new Error(
                    'Candidate changed during focused verification',
                );
            console.log(JSON.stringify({ ...plan, results }, null, 2));
        }
    } catch (error) {
        console.error(error.message);
        process.exitCode = 1;
    }
}
