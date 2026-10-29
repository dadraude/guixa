import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    mkdtempSync,
    mkdirSync,
    writeFileSync,
    rmSync,
    existsSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { execFileSync, spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { checkPlan } from '../scripts/check-task.mjs';
import { taskEnvironment } from '../scripts/task-command.mjs';

function fixture() {
    const cwd = mkdtempSync(join(tmpdir(), 'workflow-check-'));
    const write = (path, text) => {
        mkdirSync(join(cwd, path, '..'), { recursive: true });
        writeFileSync(join(cwd, path), text);
    };
    const git = (...args) =>
        execFileSync('git', ['-c', 'core.hooksPath=/dev/null', ...args], {
            cwd,
            encoding: 'utf8',
        }).trim();
    git('init', '-q');
    git('config', 'user.name', 'Workflow Test');
    git('config', 'user.email', 'workflow@example.invalid');
    write('src/Contract.php', '<?php // contract');
    write('tests/ExistingTest.php', '<?php // consumer');
    write(
        'vite.config.ts',
        "export default {\n    fmt: {\n        ignorePatterns: ['specs/**', 'resources/js/generated/**'],\n    },\n};",
    );
    git('add', '.');
    git('-c', 'commit.gpgsign=false', 'commit', '-qm', 'Fixture');
    return {
        cwd,
        write,
        base: git('rev-parse', 'HEAD'),
        clean: () => rmSync(cwd, { recursive: true, force: true }),
    };
}
void test('plans all changed supported formats, changed tests and unchanged contract consumers', () => {
    const f = fixture();
    try {
        f.write('src/Contract.php', '<?php // new constructor');
        f.write('view with spaces.html', '<main />');
        f.write('notes.md', 'Notes');
        f.write('specs/log.md', 'Append-only');
        f.write('resources/js/New.spec.ts', 'test');
        f.write('tools/new.test.mjs', 'test');
        f.write('tools/test_new.py', 'test');
        const p = checkPlan(f.cwd, f.base, {
            tests: [],
            changedContracts: [
                {
                    name: 'Contract',
                    path: 'src/Contract.php',
                    evidence: 'Constructor callers inspected',
                    consumers: ['tests/ExistingTest.php'],
                },
            ],
        });
        assert.ok(p.formatting.selected.includes('view with spaces.html'));
        assert.ok(p.formatting.selected.includes('notes.md'));
        assert.ok(p.formatting.selected.includes('src/Contract.php'));
        assert.deepEqual(p.formatting.excluded, ['specs/log.md']);
        assert.ok(p.tests.includes('tests/ExistingTest.php'));
        assert.ok(p.tests.includes('resources/js/New.spec.ts'));
        assert.ok(
            p.commands.some((args) => args.includes('view with spaces.html')),
        );
        assert.ok(
            p.commands.some((args) => args.includes('tools/new.test.mjs')),
        );
        assert.ok(p.commands.some((args) => args.includes('test_new.py')));
        assert.ok(!p.commands.some((args) => args.includes('--parallel')));
        assert.throws(
            () =>
                checkPlan(f.cwd, f.base, {
                    tests: [],
                    changedContracts: [
                        {
                            name: 'Contract',
                            path: 'src/Contract.php',
                            evidence: 'Known',
                        },
                    ],
                }),
            /consumer/,
        );
        assert.throws(
            () => checkPlan(f.cwd, f.base, { tests: [], changedContracts: [] }),
            /Explain/,
        );
    } finally {
        f.clean();
    }
});
void test('task environment rejects unsafe overrides, cached config and forced XML overrides', () => {
    const f = fixture();
    try {
        const safe = taskEnvironment('208', f.cwd, {});
        assert.equal(safe.DB_DATABASE, 'guixa_test_dra208');
        assert.equal(safe.PGTZ, 'UTC');
        assert.equal(
            taskEnvironment('208', f.cwd, {
                DB_DATABASE: 'guixa_test_dra208_test_2',
            }).DB_DATABASE,
            'guixa_test_dra208_test_2',
        );
        for (const env of [
            { DB_DATABASE: 'guixa' },
            { DB_DATABASE: 'guixa_test' },
            { DB_DATABASE: 'guixa_test_dra209' },
            { DB_URL: 'postgres://secret' },
            { DATABASE_URL: 'secret' },
            { APP_CONFIG_CACHE: 'other.php' },
        ])
            assert.throws(() => taskEnvironment('208', f.cwd, env));
        assert.throws(() => taskEnvironment('bad', f.cwd, {}), /digits/);
        assert.throws(
            () => taskEnvironment('208', f.cwd, {}, ['--env=production']),
            /overrides/,
        );
        f.write('.env.testing', 'DB_DATABASE=guixa\n');
        assert.throws(() => taskEnvironment('208', f.cwd, safe), /Unsafe/);
        rmSync(join(f.cwd, '.env.testing'));
        f.write(
            'phpunit.xml',
            '<phpunit><php><env name="DB_DATABASE" value="guixa_test"/></php></phpunit>',
        );
        assert.equal(
            taskEnvironment('208', f.cwd, {}).DB_DATABASE,
            'guixa_test_dra208',
        );
        f.write(
            'phpunit.xml',
            '<phpunit><php><env value="guixa" force="true" name="DB_DATABASE"/></php></phpunit>',
        );
        assert.throws(() => taskEnvironment('208', f.cwd, {}), /Unsafe/);
        rmSync(join(f.cwd, 'phpunit.xml'));
        f.write('bootstrap/cache/config.php', '<?php return [];');
        assert.throws(() => taskEnvironment('208', f.cwd, {}), /Cached/);
    } finally {
        f.clean();
    }
});
void test('unsafe wrapper never launches its child or reveals rejected credentials', () => {
    const f = fixture();
    try {
        const marker = join(f.cwd, 'launched');
        const wrapper = fileURLToPath(
            new URL('../scripts/task-command.mjs', import.meta.url),
        );
        const result = spawnSync(
            process.execPath,
            [
                wrapper,
                '208',
                '--',
                process.execPath,
                '-e',
                "require('node:fs').writeFileSync(process.argv[1], 'bad')",
                marker,
            ],
            {
                cwd: f.cwd,
                encoding: 'utf8',
                env: {
                    ...process.env,
                    DB_URL: 'postgres://private-password@localhost/guixa',
                },
            },
        );
        assert.notEqual(result.status, 0);
        assert.equal(existsSync(marker), false);
        assert.ok(!result.stderr.includes('private-password'));
    } finally {
        f.clean();
    }
});

void test('safe wrapper preserves argument boundaries and sets the effective task environment', () => {
    const f = fixture();
    try {
        const wrapper = fileURLToPath(
            new URL('../scripts/task-command.mjs', import.meta.url),
        );
        const env = { ...process.env };
        for (const key of [
            'DB_URL',
            'DATABASE_URL',
            'DB_DATABASE',
            'DB_CONNECTION',
            'APP_CONFIG_CACHE',
        ])
            delete env[key];
        const result = spawnSync(
            process.execPath,
            [
                wrapper,
                '208',
                '--',
                process.execPath,
                '-e',
                'console.log(JSON.stringify([process.env.DB_DATABASE,process.env.APP_ENV,process.argv[1]]))',
                'argument with spaces; $(false)',
            ],
            { cwd: f.cwd, encoding: 'utf8', env },
        );
        assert.equal(result.status, 0, result.stderr);
        assert.deepEqual(JSON.parse(result.stdout), [
            'guixa_test_dra208',
            'testing',
            'argument with spaces; $(false)',
        ]);
    } finally {
        f.clean();
    }
});
