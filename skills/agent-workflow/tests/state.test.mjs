import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test } from 'node:test';
import { applyEvent, compatibleTranslations } from '../scripts/state.mjs';
const initialize = (owner = {}, at) =>
    applyEvent(owner, {
        type: 'initialize',
        evidence: 'New admission, no earlier attempts',
        counters: { substantive: 0, mechanical: 0, product: {}, recovery: {} },
        at,
    });
const failure = (classification, mechanism = 'fixture-ready') => ({
    type: 'failure',
    classification,
    mechanism,
    stage: 'visual',
});
const recovery = (mechanism = 'fixture-ready') => ({
    type: 'recovery',
    stage: 'visual',
    classification: 'harness',
    mechanism,
    diagnosis: 'Selector is stale',
    reproduction: 'Focused selector probe failed',
    evidence: 'Selector corrected and focused probe rerun',
});
const result = (mechanism, outcome) => ({
    type: 'recovery-result',
    stage: 'visual',
    classification: 'harness',
    mechanism,
    outcome,
    evidence: `Focused probe ${outcome}`,
});
void test('successful unrelated recoveries do not spend a mechanism failure allowance', () => {
    let owner = initialize({ implementer_id: 'original' });
    for (const mechanism of ['selector-a', 'selector-b', 'selector-c']) {
        owner = applyEvent(owner, failure('harness', mechanism));
        owner = applyEvent(owner, recovery(mechanism));
        owner = applyEvent(owner, result(mechanism, 'success'));
    }
    assert.equal(owner.pipeline.counters.recovery.visual, 3);
    owner = applyEvent(owner, failure('harness', 'fixture-ready'));
    owner = applyEvent(owner, recovery());
    owner = applyEvent(owner, result('fixture-ready', 'failure'));
    owner = JSON.parse(JSON.stringify(owner));
    owner = applyEvent(owner, failure('product', 'overflow'));
    assert.equal(owner.pipeline.blocker, undefined);
    assert.throws(
        () => applyEvent(owner, failure('infrastructure')),
        /Reclassification/,
    );
    owner = applyEvent(owner, recovery());
    owner = applyEvent(owner, result('fixture-ready', 'failure'));
    assert.match(owner.pipeline.blocker, /recovery/);
    assert.equal(owner.implementer_id, 'original');
    assert.throws(() => applyEvent(owner, recovery()), /exhausted/);
});
void test('CI failure counts survive local success; mechanical review does not consume substantive budget', () => {
    let owner = applyEvent(initialize(), {
        ...failure('product', 'assertion'),
        stage: 'ci',
    });
    owner = applyEvent(owner, {
        type: 'success',
        classification: 'product',
        stage: 'local',
        mechanism: 'focused',
    });
    assert.equal(owner.pipeline.counters.product.ci, 1);
    owner = applyEvent(owner, {
        type: 'review',
        classification: 'substantive',
        verdict: 'clean',
    });
    owner = applyEvent(owner, {
        type: 'review',
        classification: 'mechanical',
        verdict: 'clean',
    });
    owner = applyEvent(owner, {
        type: 'review',
        classification: 'mechanical',
        verdict: 'clean',
    });
    assert.equal(owner.pipeline.counters.substantive, 1);
    assert.throws(
        () =>
            applyEvent(owner, {
                type: 'review',
                classification: 'mechanical',
                verdict: 'clean',
            }),
        /exhausted/,
    );
    owner = applyEvent(owner, {
        ...failure('product', 'assertion'),
        stage: 'ci',
    });
    assert.match(owner.pipeline.blocker, /product/);
});
void test('disposition and ownership evidence persist; stage timing excludes uncompleted periods', () => {
    let owner = applyEvent(initialize({}, '2026-10-29T09:00:00Z'), {
        type: 'disposition',
        value: 'deferred',
        evidence: 'User hold',
        at: '2026-10-29T10:00:00Z',
    });
    owner = applyEvent(owner, {
        type: 'reservations',
        paths: ['shared.json'],
        evidence: 'Retained worker still owns draft',
        at: '2026-10-29T10:00:00Z',
    });
    owner = applyEvent(owner, {
        type: 'start',
        stage: 'decision-wait',
        at: '2026-10-29T10:00:00Z',
    });
    assert.equal(owner.pipeline.durationsMs['decision-wait'], 0);
    owner = applyEvent(owner, {
        type: 'end',
        stage: 'decision-wait',
        at: '2026-10-29T10:02:00Z',
    });
    assert.equal(owner.pipeline.durationsMs['decision-wait'], 120000);
    assert.equal(owner.pipeline.disposition, 'deferred');
    assert.deepEqual(owner.pipeline.reservations, ['shared.json']);
    assert.throws(
        () => applyEvent(owner, { type: 'disposition', value: 'abandoned' }),
        /evidence/,
    );
});
void test('translation overlap permits only disjoint additions preserving all base keys', () => {
    const base = { Existing: 'Existing' };
    assert.equal(
        compatibleTranslations(base, { ...base, A: 'A' }, { ...base, B: 'B' }),
        true,
    );
    assert.equal(
        compatibleTranslations(base, { ...base, A: 'A' }, { ...base, A: 'A' }),
        false,
    );
    assert.equal(
        compatibleTranslations(
            base,
            { Existing: 'Changed' },
            { ...base, B: 'B' },
        ),
        false,
    );
    assert.equal(compatibleTranslations(base, {}, base), false);
    assert.equal(
        compatibleTranslations(base, { ...base, A: { nested: 'A' } }, base),
        false,
    );
});

void test('missing historical counters cannot be silently initialized or reset', () => {
    assert.throws(
        () => applyEvent({ implementer_id: 'retained' }, failure('product')),
        /unknown history/,
    );
    assert.throws(
        () =>
            applyEvent(initialize(), {
                type: 'initialize',
                evidence: 'Restart',
                counters: {},
            }),
        /initialized again/,
    );
});

void test('only product success breaks that stage product failure streak', () => {
    let owner = initialize();
    owner = applyEvent(owner, {
        ...failure('product', 'assertion'),
        stage: 'ci',
    });
    owner = applyEvent(owner, {
        ...failure('product', 'assertion'),
        stage: 'local',
    });
    owner = applyEvent(owner, {
        ...failure('infrastructure', 'service'),
        stage: 'local',
    });
    owner = applyEvent(owner, {
        ...recovery('service'),
        classification: 'infrastructure',
        stage: 'local',
    });
    owner = applyEvent(owner, {
        ...result('service', 'success'),
        classification: 'infrastructure',
        stage: 'local',
    });
    owner = applyEvent(owner, {
        type: 'success',
        classification: 'infrastructure',
        mechanism: 'service',
        stage: 'local',
    });
    assert.equal(owner.pipeline.counters.product.local, 1);
    assert.equal(owner.pipeline.counters.product.ci, 1);
    assert.equal(owner.pipeline.counters.recovery.local, 1);
    owner = applyEvent(owner, {
        ...failure('product', 'assertion'),
        stage: 'local',
    });
    assert.match(owner.pipeline.blocker, /product/);
});

void test('pending recovery and missing diagnosis cannot hide failed attempts', () => {
    let owner = applyEvent(initialize(), failure('harness'));
    assert.throws(
        () => applyEvent(owner, { ...recovery(), diagnosis: '' }),
        /diagnosis/,
    );
    owner = applyEvent(owner, recovery());
    assert.throws(() => applyEvent(owner, recovery('renamed')), /pending/);
    assert.throws(() => applyEvent(owner, failure('harness')), /pending/);
    assert.throws(
        () =>
            applyEvent(owner, {
                type: 'success',
                stage: 'visual',
                classification: 'harness',
                mechanism: 'fixture-ready',
            }),
        /pending/,
    );
    assert.throws(
        () => applyEvent(owner, result('renamed', 'failure')),
        /pending/,
    );
    owner = applyEvent(owner, {
        type: 'disposition',
        value: 'deferred',
        evidence: 'User pause while correction is unresolved',
    });
    owner = applyEvent(owner, { type: 'start', stage: 'decision-wait' });
    assert.equal(
        owner.pipeline.recoveryLedger.pending.mechanism,
        'fixture-ready',
    );
    owner = applyEvent(
        JSON.parse(JSON.stringify(owner)),
        result('fixture-ready', 'failure'),
    );
    assert.equal(
        owner.pipeline.recoveryLedger.failures['visual:fixture-ready'],
        1,
    );
});

void test('ordinary prerequisite preparation is separate from correction attempts', () => {
    let owner = initialize();
    owner = applyEvent(owner, {
        type: 'preparation',
        stage: 'visual',
        evidence: 'Browser readiness checked before first validation',
    });
    assert.equal(owner.pipeline.counters.recovery.visual, undefined);
    owner = applyEvent(owner, failure('harness'));
    assert.throws(
        () =>
            applyEvent(owner, {
                type: 'success',
                stage: 'visual',
                classification: 'harness',
                mechanism: 'fixture-ready',
            }),
        /recovery/,
    );
});

void test('total stage attempts stop mechanism renaming at eight', () => {
    let owner = initialize();
    for (let i = 0; i < 8; i++) {
        const mechanism = `selector-${i}`;
        owner = applyEvent(owner, failure('harness', mechanism));
        owner = applyEvent(owner, recovery(mechanism));
        owner = applyEvent(owner, result(mechanism, 'success'));
    }
    owner = applyEvent(owner, failure('harness', 'selector-8'));
    assert.throws(() => applyEvent(owner, recovery('selector-8')), /exhausted/);
});

void test('an exception cannot be spent by an unrelated mechanism', () => {
    let owner = initialize();
    for (let i = 0; i < 8; i++) {
        const mechanism = `selector-${i}`;
        owner = applyEvent(owner, failure('harness', mechanism));
        owner = applyEvent(owner, recovery(mechanism));
        owner = applyEvent(owner, result(mechanism, 'success'));
    }
    owner = applyEvent(owner, failure('harness', 'authorized'));
    owner = applyEvent(owner, {
        type: 'recovery-authorization',
        stage: 'visual',
        mechanism: 'authorized',
        additionalAttempts: 1,
        authorization: 'User approved',
        evidence: 'Focused evidence reviewed',
    });
    owner = applyEvent(owner, failure('harness', 'unrelated'));
    assert.throws(() => applyEvent(owner, recovery('unrelated')), /exhausted/);
    owner = applyEvent(owner, recovery('authorized'));
    owner = applyEvent(owner, result('authorized', 'success'));
    owner = applyEvent(owner, failure('harness', 'authorized'));
    assert.throws(() => applyEvent(owner, recovery('authorized')), /exhausted/);
});

void test('authorization for another mechanism cannot clear an exhausted blocker', () => {
    let owner = applyEvent(initialize(), failure('harness', 'blocked'));
    owner = applyEvent(owner, failure('harness', 'other'));
    for (let i = 0; i < 2; i++) {
        owner = applyEvent(owner, recovery('blocked'));
        owner = applyEvent(owner, result('blocked', 'failure'));
    }
    assert.match(owner.pipeline.blocker, /recovery/);
    owner = applyEvent(owner, {
        type: 'recovery-authorization',
        stage: 'visual',
        mechanism: 'other',
        additionalAttempts: 1,
        authorization: 'User approved other',
        evidence: 'Other diagnosis',
    });
    assert.match(owner.pipeline.blocker, /recovery/);
    assert.throws(() => applyEvent(owner, recovery('other')), /exhausted/);
});

void test('scoped authorization adds bounded recovery capacity without changing review or product limits', () => {
    let owner = applyEvent(initialize(), failure('harness'));
    for (let i = 0; i < 2; i++) {
        owner = applyEvent(owner, recovery());
        owner = applyEvent(owner, result('fixture-ready', 'failure'));
    }
    assert.throws(() => applyEvent(owner, recovery()), /exhausted/);
    owner = applyEvent(owner, {
        type: 'recovery-authorization',
        stage: 'visual',
        mechanism: 'fixture-ready',
        additionalAttempts: 1,
        authorization: 'User approved one focused retry',
        evidence: 'Two failures reviewed; same selector diagnosis',
    });
    owner = applyEvent(owner, recovery());
    owner = applyEvent(owner, result('fixture-ready', 'success'));
    assert.equal(owner.pipeline.limits.product, 2);
    assert.equal(owner.pipeline.limits.substantive, 3);
    assert.throws(
        () =>
            applyEvent(owner, {
                type: 'recovery-authorization',
                stage: 'visual',
                mechanism: 'fixture-ready',
                additionalAttempts: 1,
                authorization: 'Again',
                evidence: 'Again',
            }),
        /already authorized/,
    );
    assert.throws(
        () =>
            applyEvent(owner, {
                type: 'recovery-authorization',
                stage: 'visual',
                mechanism: 'another',
                additionalAttempts: 2,
                authorization: 'User approved',
                evidence: 'Reviewed',
            }),
        /known|exhausted/,
    );
});

void test('legacy recovery state fails closed while product events remain usable', () => {
    let owner = initialize();
    delete owner.pipeline.recoveryLedger;
    owner = applyEvent(owner, failure('harness'));
    assert.throws(() => applyEvent(owner, recovery()), /legacy|unknown/i);
    owner = applyEvent(owner, failure('product', 'assertion'));
    assert.equal(owner.pipeline.counters.product.visual, 1);
});

void test('CLI rejects invalid recovery without changing owner bytes', () => {
    const dir = mkdtempSync(join(tmpdir(), 'workflow-recovery-'));
    try {
        const ownerPath = join(dir, 'owner.json');
        const eventPath = join(dir, 'event.json');
        writeFileSync(ownerPath, JSON.stringify(initialize()));
        writeFileSync(eventPath, JSON.stringify(recovery()));
        const before = readFileSync(ownerPath);
        const run = spawnSync(
            process.execPath,
            ['skills/agent-workflow/scripts/state.mjs', ownerPath, eventPath],
            {
                cwd: new URL('../../..', import.meta.url),
                encoding: 'utf8',
            },
        );
        assert.equal(run.status, 1);
        assert.match(run.stderr, /prior diagnosed failure/);
        assert.deepEqual(readFileSync(ownerPath), before);
    } finally {
        rmSync(dir, { recursive: true, force: true });
    }
});

void test('CLI persists the paired attempt and result across invocations', () => {
    const dir = mkdtempSync(join(tmpdir(), 'workflow-recovery-'));
    try {
        const ownerPath = join(dir, 'owner.json');
        const eventPath = join(dir, 'event.json');
        writeFileSync(
            ownerPath,
            JSON.stringify(applyEvent(initialize(), failure('harness'))),
        );
        const runEvent = (event) => {
            writeFileSync(eventPath, JSON.stringify(event));
            const run = spawnSync(
                process.execPath,
                [
                    'skills/agent-workflow/scripts/state.mjs',
                    ownerPath,
                    eventPath,
                ],
                {
                    cwd: new URL('../../..', import.meta.url),
                    encoding: 'utf8',
                },
            );
            assert.equal(run.status, 0, run.stderr);
            return JSON.parse(readFileSync(ownerPath));
        };
        let owner = runEvent(recovery());
        assert.equal(
            owner.pipeline.recoveryLedger.pending.mechanism,
            'fixture-ready',
        );
        owner = runEvent(result('fixture-ready', 'failure'));
        assert.equal(owner.pipeline.recoveryLedger.pending, null);
        assert.equal(
            owner.pipeline.recoveryLedger.failures['visual:fixture-ready'],
            1,
        );
    } finally {
        rmSync(dir, { recursive: true, force: true });
    }
});
