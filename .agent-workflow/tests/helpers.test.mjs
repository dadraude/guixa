import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    mkdtempSync,
    mkdirSync,
    writeFileSync,
    rmSync,
    existsSync,
    symlinkSync,
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
        git,
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

void test('deleted contracts retain consumer checks and fingerprint their removal without executing missing files', () => {
    const f = fixture();
    try {
        f.write(
            'tests/consumer.test.mjs',
            'import { test } from "node:test"; test("consumer", () => {});',
        );
        f.write('deleted.md', 'Removed documentation');
        f.git('add', '.');
        f.git('-c', 'commit.gpgsign=false', 'commit', '-qm', 'Deletion inputs');
        const base = f.git('rev-parse', 'HEAD');
        for (const path of [
            'src/Contract.php',
            'tests/ExistingTest.php',
            'deleted.md',
        ])
            rmSync(join(f.cwd, path));
        const declaration = {
            tests: [],
            changedContracts: [
                {
                    name: 'Contract',
                    path: 'src/Contract.php',
                    evidence: 'Removal consumers inspected',
                    consumers: ['tests/consumer.test.mjs'],
                },
            ],
        };
        const deleted = checkPlan(f.cwd, base, declaration);
        assert.deepEqual(deleted.changed, [
            'deleted.md',
            'src/Contract.php',
            'tests/ExistingTest.php',
        ]);
        assert.deepEqual(deleted.removed, deleted.changed);
        assert.deepEqual(deleted.tests, ['tests/consumer.test.mjs']);
        assert.deepEqual(deleted.formatting.selected, []);
        assert.deepEqual(deleted.commands, [
            ['node', '--test', 'tests/consumer.test.mjs'],
        ]);
        const withoutContracts = checkPlan(f.cwd, base, {
            tests: [],
            changedContracts: [],
            noContractChangesReason: 'Fixture checks deletion inventory',
        });
        assert.deepEqual(withoutContracts.changed, deleted.changed);
        f.write('src/Contract.php', '');
        const restored = checkPlan(f.cwd, base, declaration);
        assert.notEqual(restored.inputHash, deleted.inputHash);
        assert.ok(restored.formatting.selected.includes('src/Contract.php'));
        assert.deepEqual(restored.removed, [
            'deleted.md',
            'tests/ExistingTest.php',
        ]);
    } finally {
        f.clean();
    }
});

void test('focused verification detects a deleted contract restored by a consumer check', () => {
    const f = fixture();
    try {
        f.write(
            'tests/consumer.test.mjs',
            'import { writeFileSync } from "node:fs"; writeFileSync("src/Contract.php", "<?php // mutated during checks");',
        );
        f.git('add', '.');
        f.git(
            '-c',
            'commit.gpgsign=false',
            'commit',
            '-qm',
            'Mutating consumer fixture',
        );
        const base = f.git('rev-parse', 'HEAD');
        rmSync(join(f.cwd, 'src/Contract.php'));
        f.write(
            '.git/check-plan.json',
            JSON.stringify({
                tests: [],
                changedContracts: [
                    {
                        name: 'Contract',
                        path: 'src/Contract.php',
                        evidence: 'Removal consumers inspected',
                        consumers: ['tests/consumer.test.mjs'],
                    },
                ],
            }),
        );
        const env = { ...process.env };
        for (const key of [
            'DB_DATABASE',
            'DB_CONNECTION',
            'DB_URL',
            'DATABASE_URL',
            'APP_CONFIG_CACHE',
        ])
            delete env[key];
        delete env.NODE_TEST_CONTEXT;
        const result = spawnSync(
            process.execPath,
            [
                fileURLToPath(
                    new URL('../scripts/check-task.mjs', import.meta.url),
                ),
                '208',
                base,
                '.git/check-plan.json',
            ],
            { cwd: f.cwd, encoding: 'utf8', env },
        );
        assert.notEqual(result.status, 0, result.stdout + result.stderr);
        assert.match(
            result.stderr,
            /Candidate changed during focused verification/,
        );
        assert.equal(existsSync(join(f.cwd, 'src/Contract.php')), true);
    } finally {
        f.clean();
    }
});

void test('PHPUnit server values always validate while overwritten unforced env defaults remain valid', () => {
    const f = fixture();
    try {
        const unsafe = [
            ['DB_DATABASE', 'guixa', /Unsafe/],
            ['DB_URL', 'postgres://private-password@localhost/guixa', /URL/],
            [
                'DATABASE_URL',
                'postgres://private-password@localhost/guixa',
                /URL/,
            ],
            ['DB_CONNECTION', 'sqlite', /connection/],
            ['APP_CONFIG_CACHE', 'other.php', /cached configuration/],
        ];
        for (const force of [
            '',
            ' force="false"',
            ' force="true"',
            ' force="1"',
        ]) {
            for (const [name, value, message] of unsafe) {
                f.write(
                    'phpunit.xml',
                    `<phpunit><php><server name="${name}" value="${value}"${force}/></php></phpunit>`,
                );
                assert.throws(() => taskEnvironment('208', f.cwd, {}), message);
            }
            f.write(
                'phpunit.xml',
                `<phpunit><php><server name="DB_DATABASE" value="guixa_test_dra208"${force}/><server name="DB_CONNECTION" value="pgsql"${force}/></php></phpunit>`,
            );
            assert.equal(
                taskEnvironment('208', f.cwd, {}).DB_DATABASE,
                'guixa_test_dra208',
            );
        }
        for (const [name, value] of unsafe.filter(
            ([name]) => name !== 'APP_CONFIG_CACHE',
        )) {
            f.write(
                'phpunit.xml',
                `<phpunit><php><env name="${name}" value="${value}"/></php></phpunit>`,
            );
            const env = taskEnvironment('208', f.cwd, {});
            assert.equal(env.DB_DATABASE, 'guixa_test_dra208');
            assert.equal(env.DB_CONNECTION, 'pgsql');
            assert.equal(env.DB_URL, '');
            assert.equal(env.DATABASE_URL, '');
        }
        f.write(
            'phpunit.xml',
            '<phpunit><php><env name="APP_CONFIG_CACHE" value="other.php"/></php></phpunit>',
        );
        assert.throws(
            () => taskEnvironment('208', f.cwd, {}),
            /cached configuration/,
        );
    } finally {
        f.clean();
    }
});

void test('dotenv exported and quoted isolation overrides are validated in both environment files', () => {
    const f = fixture();
    try {
        const unsafe = [
            ['APP_CONFIG_CACHE', 'other.php', /cached configuration/],
            ['DB_URL', 'postgres://private-password@localhost/guixa', /URL/],
            [
                'DATABASE_URL',
                'postgres://private-password@localhost/guixa',
                /URL/,
            ],
            ['DB_DATABASE', 'guixa', /Unsafe/],
            ['DB_CONNECTION', 'sqlite', /connection/],
        ];
        for (const path of ['.env', '.env.testing']) {
            for (const [name, value, message] of unsafe) {
                for (const key of [name, `'${name}'`, `"${name}"`]) {
                    for (const prefix of ['', 'export ', '  export\t']) {
                        f.write(path, `${prefix}${key} = "${value}"\n`);
                        assert.throws(
                            () => taskEnvironment('208', f.cwd, {}),
                            message,
                        );
                    }
                }
            }
            f.write(
                path,
                'export APP_NAME="Isolated fixture"\nexport DB_DATABASE="guixa_test_dra208"\n',
            );
            assert.equal(
                taskEnvironment('208', f.cwd, {}).DB_DATABASE,
                'guixa_test_dra208',
            );
            rmSync(join(f.cwd, path));
        }
    } finally {
        f.clean();
    }
});

void test('all PHPUnit and artisan default configuration files validate conservatively', () => {
    const f = fixture();
    const names = ['phpunit.xml', 'phpunit.dist.xml', 'phpunit.xml.dist'];
    try {
        for (const name of names)
            f.write(
                name,
                '<phpunit><php><env name="DB_DATABASE" value="guixa"/></php></phpunit>',
            );
        for (const path of names) {
            for (const [name, value, message] of [
                ['DB_DATABASE', 'guixa', /Unsafe/],
                ['DB_CONNECTION', 'sqlite', /connection/],
                [
                    'DB_URL',
                    'postgres://private-password@localhost/guixa',
                    /URL/,
                ],
                ['APP_CONFIG_CACHE', 'other.php', /cached configuration/],
                ['APP_ENV', 'production', /environment/],
                ['APP_BASE_PATH', '/other/application', /base path/],
            ]) {
                f.write(
                    path,
                    `<phpunit><php><env name="${name}" value="${value}" force="TRUE"/></php></phpunit>`,
                );
                assert.throws(() => taskEnvironment('208', f.cwd, {}), message);
            }
            f.write(
                path,
                '<phpunit><php><env name="DB_DATABASE" value="guixa"/></php></phpunit>',
            );
            assert.equal(
                taskEnvironment('208', f.cwd, {}).DB_DATABASE,
                'guixa_test_dra208',
            );
        }
        for (const name of names) rmSync(join(f.cwd, name), { force: true });
        mkdirSync(join(f.cwd, 'phpunit.xml'));
        f.write(
            'phpunit.dist.xml',
            '<phpunit><php><env name="DB_DATABASE" value="guixa" force="true"/></php></phpunit>',
        );
        assert.throws(() => taskEnvironment('208', f.cwd, {}), /Unsafe/);
    } finally {
        f.clean();
    }
});

void test('effective XML overrides reject missing or empty database and connection values while empty URLs stay safe', () => {
    const f = fixture();
    try {
        for (const [tag, force] of [
            ['server', ''],
            ['server', ' force="false"'],
            ['env', ' force="true"'],
            ['env', ' force="TRUE"'],
            ['env', ' force="TrUe"'],
            ['env', ' force="1"'],
        ]) {
            for (const [name, message] of [
                ['DB_DATABASE', /Unsafe/],
                ['DB_CONNECTION', /connection/],
            ]) {
                for (const value of [' value=""', '']) {
                    f.write(
                        'phpunit.xml',
                        `<phpunit><php><${tag} name="${name}"${value}${force}/></php></phpunit>`,
                    );
                    assert.throws(
                        () => taskEnvironment('208', f.cwd, {}),
                        message,
                    );
                }
            }
            f.write(
                'phpunit.xml',
                `<phpunit><php><${tag} name="DB_URL" value=""${force}/><${tag} name="DATABASE_URL" value=""${force}/></php></phpunit>`,
            );
            assert.equal(
                taskEnvironment('208', f.cwd, {}).DB_DATABASE,
                'guixa_test_dra208',
            );
        }
        f.write(
            'phpunit.xml',
            '<phpunit><php><env name="DB_DATABASE" value=""/><env name="DB_CONNECTION" value=""/></php></phpunit>',
        );
        const env = taskEnvironment('208', f.cwd, {});
        assert.equal(env.DB_DATABASE, 'guixa_test_dra208');
        assert.equal(env.DB_CONNECTION, 'pgsql');
        assert.equal(
            taskEnvironment('208', f.cwd, {
                DB_DATABASE: '',
                DB_CONNECTION: '',
            }).DB_DATABASE,
            'guixa_test_dra208',
        );
    } finally {
        f.clean();
    }
});

void test('effective XML APP_ENV cannot select an unchecked dotenv file', () => {
    const f = fixture();
    try {
        f.write('.env.production', 'export APP_CONFIG_CACHE=other.php\n');
        for (const [tag, force] of [
            ['server', ''],
            ['server', ' force="false"'],
            ['env', ' force="true"'],
            ['env', ' force="TRUE"'],
            ['env', ' force="TrUe"'],
            ['env', ' force="1"'],
        ]) {
            for (const value of [' value="production"', ' value=""', '']) {
                f.write(
                    'phpunit.xml',
                    `<phpunit><php><${tag} name="APP_ENV"${value}${force}/></php></phpunit>`,
                );
                assert.throws(
                    () => taskEnvironment('208', f.cwd, {}),
                    /environment/,
                );
            }
            f.write(
                'phpunit.xml',
                `<phpunit><php><${tag} name="APP_ENV" value="testing"${force}/></php></phpunit>`,
            );
            assert.equal(taskEnvironment('208', f.cwd, {}).APP_ENV, 'testing');
        }
        f.write(
            'phpunit.xml',
            '<phpunit><php><env name="APP_ENV" value="production"/></php></phpunit>',
        );
        assert.equal(taskEnvironment('208', f.cwd, {}).APP_ENV, 'testing');
    } finally {
        f.clean();
    }
});

void test('renamed contracts retain source mappings and fingerprint both endpoints', () => {
    const f = fixture();
    try {
        f.git('mv', 'src/Contract.php', 'src/Renamed.php');
        const declaration = {
            tests: [],
            changedContracts: [
                {
                    name: 'Contract',
                    path: 'src/Contract.php',
                    evidence: 'Renamed contract consumers inspected',
                    consumers: ['tests/ExistingTest.php'],
                },
            ],
        };
        const renamed = checkPlan(f.cwd, f.base, declaration);
        assert.deepEqual(renamed.changed, [
            'src/Contract.php',
            'src/Renamed.php',
        ]);
        assert.deepEqual(renamed.removed, ['src/Contract.php']);
        assert.deepEqual(renamed.formatting.selected, ['src/Renamed.php']);
        assert.deepEqual(renamed.tests, ['tests/ExistingTest.php']);
        assert.ok(
            renamed.commands.every(
                (args) => !args.includes('src/Contract.php'),
            ),
        );
        assert.ok(
            renamed.commands.some((args) => args.includes('src/Renamed.php')),
        );
        f.write('src/Renamed.php', '<?php // changed renamed contract');
        assert.notEqual(
            checkPlan(f.cwd, f.base, declaration).inputHash,
            renamed.inputHash,
        );
        f.write('src/Contract.php', '');
        assert.notEqual(
            checkPlan(f.cwd, f.base, declaration).inputHash,
            renamed.inputHash,
        );
    } finally {
        f.clean();
    }
});

void test('tracked file type changes remain in candidate checks', () => {
    const f = fixture();
    try {
        f.write('tests/LinkTest.php', '<?php // original test file');
        f.git('add', '.');
        f.git(
            '-c',
            'commit.gpgsign=false',
            'commit',
            '-qm',
            'File type fixture',
        );
        const base = f.git('rev-parse', 'HEAD');
        rmSync(join(f.cwd, 'tests/LinkTest.php'));
        symlinkSync('ExistingTest.php', join(f.cwd, 'tests/LinkTest.php'));
        const plan = checkPlan(f.cwd, base, {
            tests: [],
            changedContracts: [],
            noContractChangesReason: 'Fixture replaces a test with a symlink',
        });
        assert.deepEqual(plan.changed, ['tests/LinkTest.php']);
        assert.deepEqual(plan.tests, ['tests/LinkTest.php']);
        assert.deepEqual(plan.formatting.selected, ['tests/LinkTest.php']);
    } finally {
        f.clean();
    }
});

void test('application base redirects are rejected from inherited, dotenv and all XML override types', () => {
    const f = fixture();
    try {
        for (const value of ['/other/application', '']) {
            assert.throws(
                () => taskEnvironment('208', f.cwd, { APP_BASE_PATH: value }),
                /base path/,
            );
            for (const path of ['.env', '.env.testing']) {
                f.write(path, `export APP_BASE_PATH="${value}"\n`);
                assert.throws(
                    () => taskEnvironment('208', f.cwd, {}),
                    /base path/,
                );
                rmSync(join(f.cwd, path));
            }
            for (const [tag, force] of [
                ['server', ''],
                ['server', ' force="false"'],
                ['env', ''],
                ['env', ' force="false"'],
                ['env', ' force="TRUE"'],
            ]) {
                f.write(
                    'phpunit.xml',
                    `<phpunit><php><${tag} name="APP_BASE_PATH" value="${value}"${force}/></php></phpunit>`,
                );
                assert.throws(
                    () => taskEnvironment('208', f.cwd, {}),
                    /base path/,
                );
            }
            rmSync(join(f.cwd, 'phpunit.xml'));
        }
    } finally {
        f.clean();
    }
});

void test('encoded XML control names, encoded force and declaration indirection fail closed', () => {
    const f = fixture();
    try {
        for (const [name, value] of [
            ['DB_DATA&#66;ASE', 'guixa'],
            ['DB_DATA&#x42;ASE', 'guixa'],
            ['APP_CONFIG_CAC&#72;E', 'other.php'],
            ['APP_BASE_PA&#84;H', '/other/application'],
            ['APP_E&#78;V', 'production'],
        ]) {
            for (const [tag, force] of [
                ['server', ''],
                ['env', ''],
                ['env', ' force="true"'],
            ]) {
                f.write(
                    'phpunit.xml',
                    `<phpunit><php><${tag} name="${name}" value="${value}"${force}/></php></phpunit>`,
                );
                assert.throws(
                    () => taskEnvironment('208', f.cwd, {}),
                    /Encoded XML/,
                );
            }
        }
        for (const force of ['tr&#117;e', '&#84;RUE']) {
            f.write(
                'phpunit.xml',
                `<phpunit><php><env name="DB_DATABASE" value="guixa" force="${force}"/></php></phpunit>`,
            );
            assert.throws(
                () => taskEnvironment('208', f.cwd, {}),
                /Encoded XML/,
            );
        }
        f.write(
            'phpunit.xml',
            '<!DOCTYPE phpunit [<!ENTITY setting "APP_CONFIG_CACHE">]><phpunit><php><env name="&setting;" value="other.php"/></php></phpunit>',
        );
        assert.throws(() => taskEnvironment('208', f.cwd, {}), /indirection/);
        f.write(
            'phpunit.xml',
            '<phpunit><php><env name="DB_DATABASE" value="guixa" force="FALSE"/><env name="APP_ENV" value="production"/></php></phpunit>',
        );
        assert.equal(
            taskEnvironment('208', f.cwd, {}).DB_DATABASE,
            'guixa_test_dra208',
        );
    } finally {
        f.clean();
    }
});
