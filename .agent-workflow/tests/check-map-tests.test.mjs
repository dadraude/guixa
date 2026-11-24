import assert from 'node:assert/strict';
import { test } from 'node:test';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import {
    checkMap,
    missingTests,
    namedTests,
    testTitles,
} from '../scripts/check-map-tests.mjs';

const titles = testTitles(`<?php
it('warns with the balance (R35a, S58)', function () {});
void test("deactivates the household", function () {});
it('wraps a long ' . 'title', function () {});
it("pays {$amount} to $name", function () {});
describe('statement', function () {});
it('shows the statement', () => {});
it(\`renders \${count} rows\`, () => {});`);

void test('titles are read from Pest and Vitest declarations', () => {
    assert.equal(titles.length, 7);
    assert.ok(titles.includes('warns with the balance (R35a, S58)'));
});

void test('table cells name tests; identifiers and values without spaces are ignored', () => {
    const markdown =
        '## Acceptance-to-test map\n| T1 | `deactivates the household` | `HouseholdDeactivationTest.php` | R35a |\nplain `not in a table`';
    assert.deepEqual(namedTests(markdown), ['deactivates the household']);
});

void test('exact names, unique prefixes, missing and ambiguous prefixes are told apart', () => {
    assert.deepEqual(
        missingTests(['deactivates the household', 'warns with the…'], titles),
        [],
    );
    assert.deepEqual(missingTests(['no such test'], titles), [
        'missing: no such test',
    ]);
    assert.match(
        missingTests(['shows the…'], [...titles, 'shows the totals'])[0],
        /ambiguous prefix/,
    );
});

void test('wrapped and interpolated titles match their generated names', () => {
    assert.deepEqual(
        missingTests(
            ['wraps a long title', 'pays 5 to Ana', 'renders 3 rows'],
            titles,
        ),
        [],
    );
});

void test('only the map section limits the names that are checked', () => {
    const markdown =
        '## Other\n| `one two` |\n## Acceptance-to-test map\n| `three four` |\n';
    assert.deepEqual(namedTests(markdown), ['three four']);
    assert.throws(() => namedTests('## Other\n'), /section not found/);
});

void test('checkMap scans the project test directories', () => {
    const root = mkdtempSync(join(tmpdir(), 'map-tests-'));
    try {
        mkdirSync(join(root, 'tests/Feature'), { recursive: true });
        mkdirSync(join(root, 'node_modules/x'), { recursive: true });
        writeFileSync(
            join(root, 'tests/Feature/AccountTest.php'),
            "<?php\nit('links the account', function () {});",
        );
        writeFileSync(
            join(root, 'node_modules/x/FakeTest.php'),
            "<?php\nit('ignored test', function () {});",
        );
        const handoff = join(root, 'handoff.md');
        writeFileSync(
            handoff,
            '## Acceptance-to-test map\n| T1 | `links the account` | x |\n| T2 | `ignored test` | x |\n',
        );
        const result = checkMap(handoff, root);
        assert.deepEqual(result.problems, ['missing: ignored test']);
        assert.equal(result.titles, 1);
    } finally {
        rmSync(root, { recursive: true, force: true });
    }
});
