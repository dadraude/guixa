import assert from 'node:assert/strict';
import { test } from 'node:test';
import { evaluatePredecessor } from '../scripts/predecessor-readiness.mjs';

const ready = () => ({
    task: 'T17',
    pr: 470,
    pr_status: 'open',
    draft: false,
    remote_head: 'h1',
    local_head: 'h1',
    base_sha: 'b1',
    review: { head_sha: 'h1', base_sha: 'b1', passed: true },
    ci: {
        head_sha: 'h1',
        base_sha: 'b1',
        expected_checks: ['ci'],
        checks: [{ name: 'ci', status: 'completed', conclusion: 'success' }],
    },
    stopped: false,
    user_hold: false,
    active_correction: false,
    committed_paths: ['resources/js/components/ui/select/SelectContent.vue'],
    uncommitted_paths: [],
    untracked_paths: [],
    candidate_paths: [
        'components/ui/select/SelectContent.vue',
        'app/Models/Reception.php',
    ],
});

void test('a reviewed, CI-green, clean predecessor is ready and reports the overlap it carries', () => {
    const result = evaluatePredecessor(ready());
    assert.deepEqual(result.reasons, []);
    assert.equal(result.ready_for_stack, true);
    assert.deepEqual(
        result.overlap.map((entry) => entry.candidate_path),
        ['components/ui/select/SelectContent.vue'],
    );
});

void test('stale review, pending CI, unpublished edits or a hold rule out the stack', () => {
    const cases = {
        'review.head_sha: differs from remote_head': (e) =>
            (e.review.head_sha = 'h0'),
        'ci.checks: ci did not complete with success': (e) =>
            (e.ci.checks[0].status = 'in_progress'),
        'uncommitted_paths: unpublished edits present': (e) =>
            (e.uncommitted_paths = ['app/X.php']),
        'user_hold: active': (e) => (e.user_hold = true),
        'active_correction: missing boolean': (e) => delete e.active_correction,
    };
    for (const [reason, mutate] of Object.entries(cases)) {
        const evidence = ready();
        mutate(evidence);
        const result = evaluatePredecessor(evidence);
        assert.equal(result.ready_for_stack, false, reason);
        assert.ok(result.reasons.includes(reason), ` not in `);
    }
});
