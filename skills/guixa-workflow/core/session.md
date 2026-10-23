# Coordinate compatible tasks

Use the selected runtime adapter and [project profile](../project.md). Read [scheduling](scheduling.md)
before selecting work. These rules are shared by every runtime; one coordinator owns a feature across
all sessions and runtimes. An in-session table is not a cross-process lock.

## Discover and admit

Read the configured tracker project through the adapter, verifying workspace/team/project identities.
Paginate the eligible issues and their dependency and parent/child relations. Only executable leaves
with a matching task row are candidates: exclude grouping parents even if their title resembles a task;
include eligible children regardless of parent status. Report unmapped leaves rather than guessing.

Dependency-ready means every blocker is Done, or no blockers exist. Inspect current source, worktrees
and authorized open PR evidence to establish edit surfaces and actual prerequisite integration. Classify
each candidate as eligible, waiting-integration, needs-scope or blocked-dependency. Done alone is not
proof that prerequisite code exists in the base. Missing tooling/evidence keeps affected tasks waiting.

## Confirm scope and permissions

Show the compatible batch and queued tasks with reasons. Ask one confirmation question and wait.
Explicitly identify remote destinations, the connected session to use, and the proposed remote reads,
status updates, push and PR creation. Never inspect ambient credentials to fill missing authorization.
Confirmation does not authorize merging, force-pushing, changing ticket relationships or executing an
inadmissible task. Discovery reads need their own existing authorization; ask before an unauthorized read.

Only confirmed executable tasks may be moved to Todo. Keep grouping parents untouched.

## Run and report

Run [the task pipeline](task.md) for admitted tasks, using task order as a tie-breaker. Use the lesser
of the project's task limit and available runtime capacity. No available independent reviewer means
stop the affected pipeline; do not replace independent review with writer self-review.

Pass resolved project configuration, the adapter, relevant briefs and scheduling handoff to every worker.
Workers do not delegate. The coordinator waits for actual completion and reconciles results before
dependent steps. Partial/empty worker output is not success. Verify actual paths against admitted scope.

An open PR frees an execution slot, not its reservation. Recompute compatibility before admitting the
next task. A stopped task holds overlapping work while its changes remain pending, but not disjoint work.
When only integration waits remain, report the exact prerequisite PRs and stop, without polling or merging.
On resume reconstruct reservations from live worktrees and authorized PR evidence, not chat alone.

Return one table: task, outcome (PR/stopped/waiting-integration), review rounds, deviations, retained
worktree, reserved surfaces and predecessor PRs. Include pending checks and unresolved decisions.
A PR is not an integrated task. Do not mark missing proof as passed.
