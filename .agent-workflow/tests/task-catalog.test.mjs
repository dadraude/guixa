import assert from 'node:assert/strict';
import { test } from 'node:test';
import { issueOfBranch, validateReplay } from '../scripts/next-task.mjs';
import {
    classifyTier,
    ownRequirements,
    parseDependencies,
    parseTasks,
} from '../scripts/task-catalog.mjs';
import { requirementIndex, taskFindings } from '../scripts/validate-plan.mjs';

const TASKS_MD = `# 009 — Example: Tasks

## Canonical task registry

| ID | Task | Acceptance owner | After |
| --- | --- | --- | --- |
| T01 | First | R1 | — |
| T02 | Second | R2 | T01; T03 (integration) |
| T03 | Third | R3 | T01 approved |

## Task details

### T01 First

- **Requirements/scenarios.** R1; S1; 001 R23a/R24.
- **Files/surfaces reserved.** Edit: \`app/Models/Basket.php\`, \`resources/js/pages/Baskets/Show.vue\`. New: \`tests/Feature/BasketTest.php\`.
- **Dependencies.** None.
- **Done when.**
  - \`BasketTest\` fails first, then passes.
  - The page shows the total.
- **Size.** ~100 lines (small).

### T02 Second

- **Requirements/scenarios.** R2 "as R9 says".
- **Files/surfaces reserved.** \`app/Http/Controllers/BasketController.php\`
- **Dependencies.** T01's model; T03 is checked after merge.
- **Done when.** One paragraph of acceptance.

### T03 Third

- **Requirements/scenarios.** R7.
- **Files/surfaces reserved.** The four files.
- **Dependencies.** C1 approved.
- **Done when.**
  - Row.
`;

void test('parses registry order, dependency gates, reserved paths, rows and own requirements', () => {
    const [first, second, third] = parseTasks(TASKS_MD);
    assert.deepEqual(
        [
            first.id,
            first.order,
            first.paths,
            first.doneWhenRows,
            first.tier,
            first.requirements,
        ],
        [
            'T01',
            1,
            [
                'app/Models/Basket.php',
                'resources/js/pages/Baskets/Show.vue',
                'tests/Feature/BasketTest.php',
            ],
            2,
            'S',
            ['R1', 'S1'],
        ],
    );
    assert.deepEqual(second.dependencies, [
        { task: 'T01', gate: 'start' },
        { task: 'T03', gate: 'integration' },
    ]);
    assert.deepEqual([second.doneWhenRows, second.requirements], [1, ['R2']]);
    assert.deepEqual(
        [third.dependencies, third.paths],
        [[{ task: 'T01', gate: 'start' }], []],
    );
});

void test('After ranges expand and a dash means no dependency', () => {
    assert.deepEqual(
        parseDependencies('T09–T11').map((d) => d.task),
        ['T09', 'T10', 'T11'],
    );
    assert.deepEqual(parseDependencies('—'), []);
    assert.deepEqual(ownRequirements('R21–R23 for `R99/x`; 004 R5, R6; S2'), [
        'R21',
        'R22',
        'R23',
        'S2',
    ]);
});

void test('the tier follows the stated size, raised by done-when rows', () => {
    assert.equal(classifyTier('~50 lines (small)', 3).tier, 'S');
    assert.equal(classifyTier('~50 lines (small)', 6).tier, 'M');
    assert.equal(classifyTier('medium', 11).tier, 'L');
    assert.equal(classifyTier('medium-large', 2).tier, 'L');
    assert.equal(classifyTier('', 2).tier, 'M');
});

void test('plan validation reports unexplained dependencies, missing paths and undefined requirements', () => {
    const tasks = parseTasks(TASKS_MD);
    const defined = requirementIndex([
        '- **R1 — One.**',
        '**S1 — Scene.**',
        '- **R2** Two.',
    ]);
    assert.deepEqual(taskFindings(tasks[0], defined), []);
    assert.deepEqual(taskFindings(tasks[1], defined), []);
    assert.deepEqual(taskFindings(tasks[2], defined), [
        'T03: reserves no paths ("Files/surfaces reserved" or "Scope and path")',
        'T03: "After" names T01 but "Dependencies" never explains it',
        'T03: R7 is not defined in the spec',
    ]);
});

void test('branch names map to issues and replay refuses a changed catalog or base', () => {
    assert.equal(issueOfBranch('feature/dra-42-show-basket'), '42');
    assert.equal(issueOfBranch('docs/005-t17-spec-sync'), undefined);
    const evidence = {
        catalog_revision: 'r1',
        catalog_digest: 'd1',
        base: { sha: 'b1' },
    };
    assert.equal(
        validateReplay(evidence, {
            revision: 'r1',
            digest: 'd1',
            baseSha: 'b1',
        }),
        evidence,
    );
    assert.throws(
        () =>
            validateReplay(evidence, {
                revision: 'r2',
                digest: 'd1',
                baseSha: 'b1',
            }),
        /Catalog changed/,
    );
    assert.throws(
        () =>
            validateReplay(evidence, {
                revision: 'r1',
                digest: 'd1',
                baseSha: 'b2',
            }),
        /base moved/,
    );
});
