# Run one task

The coordinator owns every transition using the selected adapter and [project profile](../../../.agent-workflow/profile.md).
Workers never launch other workers. This also applies to standalone task requests.
Dispatch with [role-scoped context](handoff.md); process and adapter documents stay with the coordinator.

## 1. Admit

Read the issue before updating it. Verify an executable leaf, matching task row, completed dependencies,
integration evidence and compatible allowed edit surfaces using [scheduling](scheduling.md). Validate
required runtime capabilities, and that the proposed operations fall within the profile's standing authorization.
On a wait or missing scope/capability, report it before any mutation. Then set the admitted issue active.

## 2. Prepare and implement

The coordinator fetches the approved remote and creates the configured task worktree from the verified
base, serializing shared Git operations. Pass issue/task IDs, branch, root/worktree/database, fixed base
SHA, predecessor evidence, shared owners and allowed edit surfaces to the [implementer](implementer.md).
Use the adapter's implementer role once, then preserve and resume that owner through the whole task
under [implementation ownership](handoff.md#implementation-ownership). Recover its recorded identity
before any continuation; routine fixes or a new session never require a new implementer.
For visual tasks, check the implementer's route/state inventory, component plan, selected case count
and rationale against acceptance before broad UI implementation. Return missing coverage or reuse
decisions to that implementer without consuming an independent review round.

Accept `committed` only after comparing actual paths with admitted scope. A scope request pauses writes
until the coordinator readmits it; a product question goes to the user. `stopped` goes to Stop.

## 3. Focused local verification

Validate the implementer's focused evidence against the actual HEAD/base, covered requirements and
changed test files, existing tests of changed contracts, and formatting for all changed supported
source/document files. Reuse complete matching evidence; do not rerun it in a check worker by default.
Run missing focused checks in the isolated task environment. Full build, lint, type analysis and test
suites belong to CI, not an additional local gate. Run a local build only when a focused test needs it
or the change specifically needs a build reproduction; record the reason.

Report checked HEAD/base and command/result, not passing logs. On failure, include the failed check
and at most 40 sanitized output lines; send it to the implementer. Two consecutive failed attempts in
either stage (local verification or CI) stop the task; local success never resets the CI failure counter.
Pending/unavailable evidence is not a passing attempt. Focused
green covers only that HEAD/base and never means full validation has passed.

For rendered UI changes, apply the [visual pre-review gate](../references/visual.md#pre-review-gate):
check the [component reuse plan](../references/visual.md#reuse-components-before-building-screens)
against the diff, compare the manifest to acceptance and the affected page/role/state inventory, require a successful
shared verifier on the current HEAD/base, and require inspection of distinct layouts/states, changed
patterns and failures plus focused acceptance checks.
Missing coverage returns to implementation before launching a reviewer; it is not a review round.

## 4. Independent review

Launch a fresh read-only reviewer using [the reviewer brief](reviewer.md), fixed base and scheduling
handoff. Aim for one complete review of the prepared candidate. Additional rounds address concrete
findings or behavioral changes, up to three rounds total including post-integration reviews; never
reset the counter on rebase.
Clean proceeds to preparation for publication. Product questions stop. Fixes in rounds 1/2 return to
the implementer for the [whole-pattern correction](../references/visual.md#correct-a-defect-pattern)
when visual findings apply, then focused verification, then a fresh reviewer. Require the instance
sweep and remaining-gap report before relaunching review. Any unresolved finding after round 3 stops.
This task-level review does not replace any applicable repository-native review/consent requirement.

## 5. Prepare publication (no push)

The coordinator fetches the approved remote; a preparation worker records previous checked HEAD/base,
rebases onto the current configured base and regenerates affected derived files. It returns prepared
with final identities/changed paths and behavioral or contract changes needing review. On conflict,
abort the rebase and return conflict/paths. Missing evidence returns stopped. Never blindly keep both
sides or hand-merge generated files. The worker cannot publish or launch gates/reviewers.

The coordinator routes the result:

- Conflict: the implementer resolves semantically within admitted scope. A still-unresolved conflict
  stops rather than retrying delivery. Update the handoff base and inspect the resolution diff.
- Changed HEAD/base or resolved conflict: rerun focused checks on the final candidate, including after
  a clean rebase; full validation is pending CI. Only identical HEAD/base can reuse focused evidence.
- Behavioral/contract changes in resolution, regeneration or check fixes: require another independent
  review if a round remains; otherwise stop without pushing. A clean review returns here without
  resetting counters. Update the review base rather than reusing the old one.
- Missing evidence, stopped result or exhausted gate/review budget: Stop.

## 6. Publish for CI

Once focused checks and required independent/native reviews cover the final candidate, send the exact
checked HEAD/base to a publisher. It verifies they still match before push/PR; mismatch
returns to preparation without pushing. Publish using the profile's branch and PR conventions.
On partial/uncertain publication failure, stop and report confirmed remote state; do not blindly retry
or send an already-pushed branch into the pre-push amend/rebase loop. Never force-push automatically.

Record the confirmed PR as `pending-ci`, retaining its worktree, database and reservation. Publication
is not full validation. A feature-branch push alone does not trigger the configured PR workflow.

## 7. Observe CI and close

Use authorized read access to resolve expected checks from the current workflow/scripts and any
required branch checks. Record PR, head SHA, base SHA, tested revision/merge parents when applicable,
run URL and each expected check's conclusion. For PR merge-ref runs, verify their parents match the
current head/base. Re-read PR identities before accepting success; changed head/base invalidates prior
CI evidence. A green badge or an unrelated/stale run is insufficient.

- All expected checks succeeded for the current candidate: report `ci-passed`; the PR waits for the user's merge.
- Queued/running, absent, skipped, cancelled, partial, stale or unreadable checks: remain `pending-ci`,
  report missing proof and retain state. Use a bounded native wait/completion event if available; do not
  busy-poll or run the full suite locally as a substitute. Resume observation when results are available.
- Failed CI: report `ci-failed` with failed check names and bounded sanitized excerpts. Within the
  existing correction/review budgets, return to the implementer for a focused reproduction and fix.
  Recheck focused evidence and independently review the changed candidate before another push. Use new
  commits on the published branch, never the pre-publication amend/rebase loop. New commits restart CI
  evidence collection.

Do not mark the task validated, clean up its environment or release its reservation while CI is pending
or failed. Missing CI capability remains missing proof, not permission to skip full validation. If the
PR conflicts with a moved base, merge the configured base into the task branch as a new commit, rerun focused
checks, get an independent review for behavioral/contract changes, then push. Do not reset correction/review counters after publishing or restarting a session.

After `ci-passed`, keep the worktree, database and reservation while the PR is open: review feedback,
conflicts or a new CI run reuse them. Delivery ends at a CI-validated PR; the user merges. Cleanup runs
only after verified integration, at the start of a later session ([Clean up merged tasks](session.md#clean-up-merged-tasks)).

## Stop

Preserve the worktree and changes. Post the exact blocker/question on the issue and return it to the
profile's stopped status. A failed
status update is pending, not a reason to retry publication. Keep the task reservation and report status,
HEAD/base, checks/review rounds, remote outcomes and the exact decision or missing capability needed.
