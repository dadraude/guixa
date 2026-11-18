import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    pathsOverlap,
    selectTasks,
    validateCatalog,
} from '../scripts/select-tasks.mjs';

const NOW = Date.parse('2026-11-18T12:00:00Z');
const task = (id, order, overrides = {}) => ({
    id,
    order,
    tier: 'M',
    paths: [`app/${id}/`],
    dependencies: [],
    ...overrides,
});
const issue = (id, taskId, overrides = {}) => ({
    id,
    task: taskId,
    status: 'Todo',
    priority: 3,
    labels: [],
    blockers: [],
    ...overrides,
});
const evidence = (issues, overrides = {}) => ({
    version: 1,
    complete: true,
    observed_at: '2026-11-18T11:55:00Z',
    base: { sha: 'abc', remote_sha: 'abc' },
    issues,
    integrated: {},
    reservations: [],
    active: [],
    ...overrides,
});
const select = (tasks, snapshot, options = {}) =>
    selectTasks(tasks, snapshot, { now: NOW, ...options });
const decisions = (report) =>
    Object.fromEntries(report.rows.map((row) => [row.task, row.decision]));

void test('ranks by priority, then tasks unblocked, then plan order, within capacity', () => {
    const tasks = [
        task('T1', 1),
        task('T2', 2),
        task('T3', 3),
        task('T4', 4, { dependencies: [{ task: 'T3', gate: 'start' }] }),
        task('T5', 5),
    ];
    const report = select(
        tasks,
        evidence([
            issue('DRA-1', 'T1'),
            issue('DRA-2', 'T2', { priority: 1 }),
            issue('DRA-3', 'T3'),
            issue('DRA-4', 'T4'),
            issue('DRA-5', 'T5'),
        ]),
    );
    assert.deepEqual(report.selected, ['T2', 'T3', 'T1']);
    assert.equal(decisions(report).T5, 'queued');
    assert.equal(decisions(report).T4, 'blocked-dependency');
});

void test('a start dependency needs base containment; an integration dependency does not block', () => {
    const tasks = [
        task('T1', 1),
        task('T2', 2, { dependencies: [{ task: 'T1', gate: 'start' }] }),
        task('T3', 3, { dependencies: [{ task: 'T1', gate: 'integration' }] }),
    ];
    const report = select(
        tasks,
        evidence([
            issue('DRA-1', 'T1', { status: 'Done' }),
            issue('DRA-2', 'T2'),
            issue('DRA-3', 'T3'),
        ]),
    );
    assert.equal(decisions(report).T2, 'waiting-integration');
    assert.equal(decisions(report).T3, 'eligible');
    assert.deepEqual(
        report.rows.find((row) => row.task === 'T3').integration_pending,
        ['T1'],
    );
    assert.match(report.warnings.join(), /DRA-1 is Done but no merged PR/);

    const merged = select(
        tasks,
        evidence(
            [
                issue('DRA-1', 'T1', { status: 'Done' }),
                issue('DRA-2', 'T2'),
                issue('DRA-3', 'T3'),
            ],
            { integrated: { T1: { pr: 1, merge_sha: 'def' } } },
        ),
    );
    assert.equal(decisions(merged).T1, 'integrated');
    assert.equal(decisions(merged).T2, 'eligible');
});

void test('overlapping, unknown and peer reservations queue a task; its own reservation does not', () => {
    const tasks = [
        task('T1', 1, {
            paths: ['resources/js/components/ui/select/SelectContent.vue'],
        }),
        task('T2', 2),
        task('T3', 3, { paths: ['components/ui/select/SelectContent.vue'] }),
    ];
    const issues = [
        issue('DRA-1', 'T1'),
        issue('DRA-2', 'T2'),
        issue('DRA-3', 'T3'),
    ];
    const report = select(
        tasks,
        evidence(issues, {
            reservations: [
                {
                    owner: 'pr#9',
                    paths: ['app/T2/Model.php'],
                    source: 'open PR',
                },
                {
                    owner: 'DRA-1',
                    paths: ['resources/js/components/ui/select/'],
                    source: 'worktree',
                },
            ],
        }),
    );
    assert.equal(decisions(report).T1, 'eligible');
    assert.equal(decisions(report).T2, 'waiting-integration');
    assert.equal(decisions(report).T3, 'queued');

    const unknown = select(
        tasks,
        evidence(issues, { reservations: [{ owner: 'pr#7', paths: null }] }),
    );
    assert.deepEqual(unknown.selected, []);
});

void test('In Progress work consumes capacity and reserves its planned scope', () => {
    const tasks = [
        task('T1', 1),
        task('T2', 2, { paths: ['app/T1/Policy.php'] }),
        task('T3', 3),
    ];
    const report = select(
        tasks,
        evidence([
            issue('DRA-1', 'T1', { status: 'In Progress' }),
            issue('DRA-2', 'T2'),
            issue('DRA-3', 'T3'),
        ]),
        { limit: 3 },
    );
    assert.deepEqual(report.active, ['DRA-1']);
    assert.equal(decisions(report).T1, 'owned');
    assert.equal(decisions(report).T2, 'waiting-integration');
    assert.deepEqual(report.selected, ['T3']);
});

void test('at most two L tasks run at once', () => {
    const tasks = [
        task('T1', 1, { tier: 'L' }),
        task('T2', 2, { tier: 'L' }),
        task('T3', 3, { tier: 'L' }),
    ];
    const report = select(
        tasks,
        evidence([
            issue('DRA-1', 'T1'),
            issue('DRA-2', 'T2'),
            issue('DRA-3', 'T3'),
        ]),
    );
    assert.deepEqual(report.selected, ['T1', 'T2']);
    assert.equal(decisions(report).T3, 'queued');
});

void test('holds, undeclared blockers, missing paths and a moved base are not eligible', () => {
    const tasks = [task('T1', 1), task('T2', 2), task('T3', 3, { paths: [] })];
    const report = select(
        tasks,
        evidence([
            issue('DRA-1', 'T1', { labels: ['On-Hold'] }),
            issue('DRA-2', 'T2', { blockers: ['DRA-1'] }),
            issue('DRA-3', 'T3'),
        ]),
    );
    assert.deepEqual(decisions(report), {
        T1: 'held',
        T2: 'needs-scope',
        T3: 'needs-scope',
    });

    const moved = select(
        [task('T1', 1)],
        evidence([issue('DRA-1', 'T1')], {
            base: { sha: 'a', remote_sha: 'b' },
        }),
    );
    assert.equal(decisions(moved).T1, 'needs-environment');
});

void test('stale or incomplete evidence produces no recommendation', () => {
    const tasks = [task('T1', 1)];
    assert.throws(
        () =>
            select(
                tasks,
                evidence([issue('DRA-1', 'T1')], {
                    observed_at: '2026-11-18T11:40:00Z',
                }),
            ),
        /older than 15 minutes/,
    );
    assert.throws(
        () =>
            select(
                tasks,
                evidence([issue('DRA-1', 'T1')], { complete: false }),
            ),
        /Incomplete/,
    );
});

void test('the catalog rejects start cycles and unknown dependencies', () => {
    assert.throws(
        () =>
            validateCatalog([
                task('T1', 1, {
                    dependencies: [{ task: 'T2', gate: 'start' }],
                }),
                task('T2', 2, {
                    dependencies: [{ task: 'T1', gate: 'start' }],
                }),
            ]),
        /cycle/,
    );
    assert.throws(
        () =>
            validateCatalog([
                task('T1', 1, {
                    dependencies: [{ task: 'T9', gate: 'start' }],
                }),
            ]),
        /Unknown dependency T9/,
    );
});

void test('paths overlap by prefix, segment suffix or glob, never by a shared name stem', () => {
    assert.equal(pathsOverlap('app/Models/', 'app/Models/User.php'), true);
    assert.equal(pathsOverlap('resources/js/lib/a.ts', 'lib/a.ts'), true);
    assert.equal(
        pathsOverlap('tests/Feature/*.php', 'tests/Feature/X.php'),
        true,
    );
    assert.equal(
        pathsOverlap('app/Models/User.php', 'app/Models/UserRole.php'),
        false,
    );
});

void test('preflight evidence, when supplied, must be ready, fresh and on the same base', () => {
    const tasks = [task('T1', 1)];
    const environment = {
        verified_at: '2026-11-18T11:58:00Z',
        base_sha: 'abc',
        ready: true,
        checks: [],
    };
    const run = (overrides) =>
        decisions(
            select(
                tasks,
                evidence([issue('DRA-1', 'T1')], {
                    environment: { ...environment, ...overrides },
                }),
            ),
        ).T1;
    assert.equal(run({}), 'eligible');
    assert.equal(
        run({ ready: false, checks: [{ id: 'postgres', ok: false }] }),
        'needs-environment',
    );
    assert.equal(
        run({ verified_at: '2026-11-18T11:00:00Z' }),
        'needs-environment',
    );
    assert.equal(run({ base_sha: 'old' }), 'needs-environment');
});
