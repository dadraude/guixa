import assert from 'node:assert/strict';
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
void test('failure classes have separate persistent budgets and cannot reset by reclassification', () => {
    let owner = initialize({ implementer_id: 'original' });
    owner = applyEvent(owner, failure('harness'));
    owner = applyEvent(owner, {
        ...failure('harness'),
        type: 'recovery',
        evidence: 'Readiness condition corrected; focused probe passed',
    });
    owner = JSON.parse(JSON.stringify(owner));
    owner = applyEvent(owner, failure('product', 'overflow'));
    assert.equal(owner.pipeline.blocker, undefined);
    assert.throws(
        () => applyEvent(owner, failure('infrastructure')),
        /Reclassification/,
    );
    owner = applyEvent(owner, {
        ...failure('harness'),
        type: 'recovery',
        evidence: 'Second diagnosed correction; focused probe passed',
    });
    owner = applyEvent(owner, failure('harness'));
    assert.match(owner.pipeline.blocker, /recovery/);
    assert.equal(owner.implementer_id, 'original');
    assert.throws(
        () =>
            applyEvent(owner, {
                ...failure('harness'),
                type: 'recovery',
                evidence: 'Third recovery',
            }),
        /exhausted/,
    );
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

void test('a successful recovered validation breaks only that stage product failure streak', () => {
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
        ...failure('infrastructure', 'service'),
        type: 'recovery',
        stage: 'local',
        evidence: 'Service restarted; focused prerequisite passed',
    });
    owner = applyEvent(owner, {
        type: 'success',
        classification: 'infrastructure',
        mechanism: 'service',
        stage: 'local',
    });
    assert.equal(owner.pipeline.counters.product.local, 0);
    assert.equal(owner.pipeline.counters.product.ci, 1);
    assert.equal(owner.pipeline.counters.recovery.local, 1);
    owner = applyEvent(owner, {
        ...failure('product', 'assertion'),
        stage: 'local',
    });
    assert.equal(owner.pipeline.blocker, undefined);
});
