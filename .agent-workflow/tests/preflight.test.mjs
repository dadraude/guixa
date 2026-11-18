import assert from 'node:assert/strict';
import { test } from 'node:test';
import { preflightReport } from '../scripts/preflight.mjs';

const healthy = {
    'gh auth': '',
    'df -Pk':
        'Filesystem 1024-blocks Used Available Capacity Mounted\n/dev/disk1 1 1 20971520 1% /',
    'git show refs/remotes/origin/main:composer.json':
        '{"require":{"php":"^8.5"}}',
    'php -v': 'PHP 8.5.1 (cli)',
    'php -r': 'ok',
    'node --version': 'v22.4.0',
    'pnpm --version': '10.1.0',
    'git show refs/remotes/origin/main:.github/workflows/tests.yml':
        'jobs:\n  ci:\n    needs: []\n',
    'git rev-parse': 'abc\n',
};

const fakeExecute = (outputs) => (file, args) => {
    const key = Object.keys(outputs).find((prefix) =>
        `${file.split('/').pop()} ${args.join(' ')}`.startsWith(prefix),
    );
    if (key === undefined || outputs[key] instanceof Error)
        throw new Error('command failed');
    return outputs[key];
};

void test('a healthy machine is ready and binds the evidence to the base', () => {
    const report = preflightReport(
        '/r/guixa',
        { execute: fakeExecute(healthy), writable: () => true },
        new Date('2026-11-18T12:00:00Z'),
    );
    assert.deepEqual(
        report.checks.filter((entry) => !entry.ok),
        [],
    );
    assert.deepEqual(
        [report.ready, report.base_sha, report.verified_at],
        [true, 'abc', '2026-11-18T12:00:00.000Z'],
    );
});

void test('each failure names its check and the fix, and the machine is not ready', () => {
    const report = preflightReport('/r/guixa', {
        execute: fakeExecute({
            ...healthy,
            'php -v': 'PHP 8.4.3 (cli)',
            'php -r': new Error(),
        }),
        writable: () => false,
    });
    const failed = report.checks.filter((entry) => !entry.ok);
    assert.equal(report.ready, false);
    assert.deepEqual(
        failed.map((entry) => entry.id),
        ['worktree-root', 'php', 'postgres', 'redis'],
    );
    assert.match(failed[1].evidence, /requires PHP 8\.5; .* reports 8\.4/);
    assert.ok(failed.every((entry) => entry.fix));
});
