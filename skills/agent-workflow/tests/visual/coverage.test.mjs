import assert from 'node:assert/strict';
import { test } from 'node:test';
import { spawnSync } from 'node:child_process';
import { mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { expandMatrix, checkCoverage } from '../../scripts/visual/coverage.mjs';

const manifest = {
    baseUrl: 'http://127.0.0.1:5180',
    matrix: {
        widths: [375, 1024, 1280],
        modes: ['light', 'dark'],
        text: [100, 200],
    },
    views: [
        {
            id: 'wallet-manager',
            path: '/',
            root: 'main',
            panels: '.panel',
            fonts: ['16px Arial'],
            focus: ['button'],
            states: [{ id: 'populated', query: {} }],
        },
    ],
};
const candidate = {
    head: 'abc',
    base: 'def',
    manifestHash: '123',
    harnessHash: '456',
};
const passing = () => ({
    ...candidate,
    cases: expandMatrix(manifest).map((c) => ({
        id: c.id,
        status: 'passed',
        screenshot: `${c.id}.png`,
        sha256: 'a'.repeat(64),
        measurements: { viewport: c.width, text: c.text, mode: c.mode },
        focused: ['button'],
    })),
});

// The Node test runner owns completion and failure reporting; register the whole suite synchronously.
void test('enumerates every view/state and matrix combination', () => {
    assert.equal(expandMatrix(manifest).length, 12);
    const copy = structuredClone(manifest);
    copy.views[0].states.push({ id: 'long-name', query: { long: 1 } });
    assert.equal(expandMatrix(copy).length, 24);
    assert.deepEqual(checkCoverage(manifest, passing(), candidate), []);
});
void test('explicit cases select only declared combinations', () => {
    const selected = structuredClone(manifest);
    selected.views[0].states[0].cases = [
        { width: 375, mode: 'light', text: 100 },
        { width: 1280, mode: 'dark', text: 200 },
    ];
    assert.deepEqual(
        expandMatrix(selected).map((item) => item.id),
        [
            'wallet-manager--populated--375--light--100',
            'wallet-manager--populated--1280--dark--200',
        ],
    );
    const report = {
        ...candidate,
        cases: passing().cases.filter((record) =>
            expandMatrix(selected).some((item) => item.id === record.id),
        ),
    };
    assert.deepEqual(checkCoverage(selected, report, candidate), []);
    assert.match(
        checkCoverage(selected, passing(), candidate).join('\n'),
        /Unexpected case/,
    );
    report.cases.pop();
    assert.match(
        checkCoverage(selected, report, candidate).join('\n'),
        /Missing or failed case/,
    );
});
void test('plan reports the case count without a browser or committed fixture', () => {
    const directory = mkdtempSync(join(tmpdir(), 'visual-plan-test-'));
    try {
        const path = join(directory, 'visual.json');
        const selected = structuredClone(manifest);
        selected.views[0].states[0].cases = [
            { width: 375, mode: 'light', text: 100 },
        ];
        writeFileSync(path, JSON.stringify(selected));
        const result = spawnSync(
            process.execPath,
            [
                fileURLToPath(new URL('../../scripts/visual/run.mjs', import.meta.url)),
                'plan',
                path,
            ],
            { encoding: 'utf8' },
        );
        assert.equal(result.status, 0, result.stderr);
        assert.match(result.stdout, /wallet-manager: 1 cases/);
        assert.match(result.stdout, /Total: 1 cases/);
    } finally {
        rmSync(directory, { recursive: true, force: true });
    }
});
void test('rejects invalid or duplicate explicit cases', () => {
    for (const cases of [
        [],
        null,
        [
            { width: 375, mode: 'light', text: 100 },
            { width: 375, mode: 'light', text: 100 },
        ],
        [{ width: 400, mode: 'light', text: 100 }],
        [{ width: 375, mode: 'system-dark', text: 100 }],
        [{ width: 375, mode: 'light', text: 150 }],
        [{ width: 375, mode: 'light' }],
        [{ width: 375, mode: 'light', text: 100, typo: true }],
    ]) {
        const selected = structuredClone(manifest);
        selected.views[0].states[0].cases = cases;
        assert.throws(() => expandMatrix(selected));
    }
});
void test('rejects empty or duplicate coverage definitions', () => {
    for (const edit of [
        (m) => (m.views = []),
        (m) => (m.matrix.widths = []),
        (m) => m.matrix.widths.push(375),
        (m) => m.views.push(m.views[0]),
        (m) => (m.views[0].states = []),
        (m) => (m.views[0].focus = []),
        (m) => (m.views[0].fonts = []),
    ]) {
        const m = structuredClone(manifest);
        edit(m);
        assert.throws(() => expandMatrix(m));
    }
});
void test('missing, duplicate, failed and unknown cases block review', () => {
    for (const edit of [
        (r) => r.cases.pop(),
        (r) => r.cases.push(r.cases[0]),
        (r) => (r.cases[0].status = 'failed'),
        (r) => (r.cases[0].id = 'unknown'),
    ]) {
        const report = passing();
        edit(report);
        assert.ok(checkCoverage(manifest, report, candidate).length);
    }
});
void test('stale candidate, fixture configuration or harness blocks review', () => {
    for (const key of Object.keys(candidate)) {
        assert.ok(
            checkCoverage(manifest, passing(), {
                ...candidate,
                [key]: 'changed',
            }).length,
        );
    }
});
void test('missing captures, measured coverage or keyboard evidence blocks review', () => {
    for (const edit of [
        (r) => delete r.cases[0].sha256,
        (r) => (r.cases[0].screenshot = '../escape.png'),
        (r) => (r.cases[0].measurements.viewport = 800),
        (r) => (r.cases[0].measurements.text = 1000),
        (r) => (r.cases[0].measurements.mode = 'dark'),
        (r) => (r.cases[0].focused = []),
    ]) {
        const report = passing();
        edit(report);
        assert.ok(checkCoverage(manifest, report, candidate).length);
    }
});
