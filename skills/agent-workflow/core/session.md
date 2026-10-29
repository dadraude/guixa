# Coordinate compatible tasks

Use the selected runtime adapter and [project profile](../../../.agent-workflow/profile.md). Read [scheduling](scheduling.md)
before selecting work. These rules are shared by every runtime; one coordinator owns a feature across
all sessions and runtimes. An in-session table is not a cross-process lock.

## Discover and admit

Read the configured tracker project through the adapter, verifying workspace/team/project identities.
Paginate the eligible issues and their dependency and parent/child relations. Only executable leaves
with a matching task row are candidates: exclude grouping parents even if their title resembles a task;
include eligible children regardless of parent status. Report unmapped leaves rather than guessing.

Dependency-ready means every blocker has the profile's completion status, or no blockers exist. Inspect current source, worktrees
and authorized open PR evidence to establish edit surfaces and actual prerequisite integration. Classify
each candidate as eligible, waiting-integration, needs-scope or blocked-dependency. Done alone is not
proof that prerequisite code exists in the base. Missing tooling/evidence keeps affected tasks waiting.

## Clean up merged tasks

Before admitting new work, check each live task worktree's PR. When its integration is verified as in
[scheduling](scheduling.md#integration-and-release), run the profile's cleanup serially: remove the
clean worktree and drop its task databases. Keep and report a dirty worktree, an unverifiable PR, or a
closed-unmerged PR until the user confirms it is abandoned.

## Report the batch

Show the compatible batch and queued tasks with reasons, then dispatch the batch without waiting. Ask
one question only when a needed operation falls outside the profile's standing authorization.

Only admitted executable tasks change status. Keep grouping parents untouched.

## Run and report

Run [the task pipeline](task.md) for admitted tasks, using task order as a tie-breaker. Use the lesser
of the project's task limit and available runtime capacity. No available independent reviewer means
stop the affected pipeline; do not replace independent review with writer self-review.

Use the [role-scoped handoff](handoff.md); keep the adapter and orchestration context in the coordinator.
Delegate potentially verbose discovery/evidence work, focused execution, preparation and CI observation
to the existing roles with bounded briefs and artifact destinations. Keep routing and decisions in the
coordinator; reuse workers for related internal work and matching current evidence across handoffs.
Workers do not delegate. The single implementation owner remains the sole implementation writer for
each admitted task. The coordinator waits for actual completion and reconciles concise results before
dependent steps, reading detailed artifacts only for concrete gaps. Partial/empty worker output is not
success. Verify actual paths against admitted scope.

An open PR frees an execution slot, not its reservation. Recompute compatibility before admitting the
next task. A stopped task holds overlapping work while its changes remain pending, but not disjoint work.
While CI runs, wait through the adapter's completion mechanism and continue each pipeline as results
arrive. When every remaining task waits on the user's merge, report exact PRs and candidate identities
and stop. Retain every open PR's environment for fixes; resume with current evidence.
On resume reconstruct reservations from live worktrees, retained disposition/ownership evidence and authorized PR evidence, not chat alone.
Recover each task's recorded implementer identity using [implementation ownership](handoff.md#implementation-ownership);
resume that worker for corrections instead of dispatching a new implementer.

Return one table: task, outcome (pending-ci/ci-failed/ci-passed/stopped/waiting-integration), PR, review rounds, deviations, retained
worktree, reserved surfaces and predecessor PRs. Include pending checks, unresolved decisions and observed stage durations from the retained task record.
Report missing historical timings explicitly; preserve holds and deferred ownership across sessions.
A PR is not an integrated task. Do not mark missing proof as passed.
