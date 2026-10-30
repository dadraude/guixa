import assert from 'node:assert/strict';
import { execFileSync, spawn } from 'node:child_process';
import {
    copyFileSync,
    mkdirSync,
    mkdtempSync,
    readFileSync,
    readdirSync,
    rmSync,
    writeFileSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test } from 'node:test';

function fixture(t) {
    const root = mkdtempSync(join(tmpdir(), 'guixa-history-'));
    t.after(() => rmSync(root, { recursive: true, force: true }));
    mkdirSync(join(root, '.agent-workflow/scripts'), { recursive: true });
    const script = join(root, '.agent-workflow/scripts/history.mjs');
    copyFileSync(new URL('../scripts/history.mjs', import.meta.url), script);
    for (const feature of ['001-first', '002-second'])
        mkdirSync(join(root, 'specs', feature), { recursive: true });
    const run = (args, input = '') =>
        execFileSync(process.execPath, [script, ...args], {
            cwd: tmpdir(),
            input,
            encoding: 'utf8',
            stdio: ['pipe', 'pipe', 'pipe'],
        });
    return { root, script, run };
}

test('reads every feature with legacy bytes and ordered entries, without a maintained index', (t) => {
    const { root, run } = fixture(t);
    const legacy = '## 2020-01-01 · Request\n\nOriginal ask.  \n';
    writeFileSync(join(root, 'specs/001-first/log.md'), legacy);
    mkdirSync(join(root, 'specs/001-first/history'));
    writeFileSync(
        join(root, 'specs/001-first/history/2026-02.md'),
        'Later decision',
    );
    writeFileSync(
        join(root, 'specs/001-first/history/2026-01.md'),
        'Earlier decision',
    );
    run(['add', '002-second', 'Request'], 'Second original ask');
    const output = run(['read']);
    assert.ok(output.includes(legacy));
    assert.ok(
        output.indexOf('Earlier decision') < output.indexOf('Later decision'),
    );
    assert.ok(output.includes('Second original ask'));
    const scoped = run(['read', '001-first']);
    assert.ok(!scoped.includes('Second original ask'));
    assert.equal(
        readFileSync(join(root, 'specs/001-first/log.md'), 'utf8'),
        legacy,
    );
});

test('simultaneous writers create independent entries and preserve verbatim bodies and existing files', async (t) => {
    const { root, script, run } = fixture(t);
    const original = run(['add', '001-first', 'Request'], 'Original request\n');
    const originalBytes = readFileSync(join(root, original.trim()));
    const bodies = Array.from(
        { length: 8 },
        (_, i) => `Decision ${i}\n  literal spaces  \n`,
    );
    await Promise.all(
        bodies.map(
            (body) =>
                new Promise((resolve, reject) => {
                    const child = spawn(process.execPath, [
                        script,
                        'add',
                        '001-first',
                        'Q&A',
                        'DRA-123',
                    ]);
                    let error = '';
                    child.stderr.on('data', (chunk) => {
                        error += chunk;
                    });
                    child.on('error', reject);
                    child.on('close', (code) =>
                        code === 0 ? resolve() : reject(new Error(error)),
                    );
                    child.stdin.end(body);
                }),
        ),
    );
    assert.equal(readdirSync(join(root, 'specs/001-first/history')).length, 9);
    const output = run(['read']);
    for (const body of bodies) assert.ok(output.includes(body));
    assert.ok(output.includes('Task: DRA-123'));
    assert.deepEqual(readFileSync(join(root, original.trim())), originalBytes);
});

test('invalid feature, kind, task and blank entries fail without creating history files', (t) => {
    const { root, run } = fixture(t);
    for (const [args, body] of [
        [['add', '../outside', 'Change'], 'text'],
        [['add', '003-missing', 'Change'], 'text'],
        [['add', '001-first', 'Unknown'], 'text'],
        [['add', '001-first', 'Change', '../bad'], 'text'],
        [['add', '001-first', 'Change'], '  \n'],
        [['read', '001-first', 'extra'], ''],
        [['read', '../outside'], ''],
        [['read', '003-missing'], ''],
    ])
        assert.throws(() => run(args, body));
    assert.deepEqual(readdirSync(join(root, 'specs/001-first')), []);
});
