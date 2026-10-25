# Compatible-batch scheduling

## Before dispatch

Build a transient coordinator table from the task/spec, current source, existing worktrees and open PR
diffs. Reconstruct it on resume. Do not write scheduling dependencies into tracker blocking relations.

For each candidate record:

- Issue, executable task ID, dependency state, base SHA and predecessor PRs/merge SHAs.
- Allowed edit surfaces: exact repository-relative paths or narrow globs, including tests and any
  authorized new-file directories. Unknown surfaces mean `needs-scope`, not permission to edit anywhere.
- Shared contracts it changes or consumes, and expected writes to shared artifacts with their owner.
- Lifecycle: queued, running, pending-ci, ci-failed, ci-passed, stopped, or integrated; PR and wait reason.
- Implementation owner: runtime/host, worker ID and retained owner-record/handoff paths, recovered on resume
  under [implementation ownership](handoff.md#implementation-ownership).

Inspect the shared hotspots listed in the [project profile](../../../.agent-workflow/profile.md), including source contracts,
generated outputs and feature documents. Reading a file is not a write reservation. Reserve document
writes when expected, rather than assuming every task edits every spec; an unexpected write uses the
scope-expansion rule below. Different files can still conflict through a changing interface.

Use current diffs for existing work, not just ticket descriptions. If another session's scope or a PR's
state cannot be verified, hold potentially overlapping candidates and report the missing evidence.
Do not claim that this in-session table is a cross-session lock.

## Admission rules

| Evidence                                                                     | Action                                                    |
| ---------------------------------------------------------------------------- | --------------------------------------------------------- |
| A real prerequisite is unfinished                                            | `blocked-dependency`; do not dispatch.                    |
| Prerequisite code is not verified in the base, even if the tracker says done | `waiting-integration`.                                    |
| Files are disjoint and no changing shared contract couples the tasks         | Run concurrently, within the session limit.               |
| Expected writes overlap, or a shared contract is being changed               | Queue the later task until the predecessor is integrated. |
| Edit surface or ownership is unknown                                         | `needs-scope`; inspect before dispatch.                   |

Keep each task's code, tests and required spec updates together. A shared artifact has one active owner;
serialize its writers through integration instead of splitting code and documentation into independent
deliveries. Do not silently transfer ownership, discard another task's edits, or use last-writer-wins.
Stacked branches are an explicitly agreed alternative, not an automatic fallback.

## Handoff and scope changes

Give the implementer and reviewer the same base SHA, predecessor evidence, shared-artifact ownership,
and `## Allowed edit surfaces` section (one path/glob per line; no prose within that section).
Reconcile the actual diff against the allowed surfaces before accepting a task's commit.

If implementation, generation or a spec deviation needs another path, the worker pauses before that
write and reports the proposed expansion. The coordinator inspects it, recomputes compatibility and
returns the revised surface only when safe. If it collides with active or PR-open work, preserve the
worker's current work and queue it; allow disjoint tasks to continue. A product decision still goes to
the user. Never drop required tests or spec updates to stay inside the original surface.

## Integration and release

An open PR retains its reservation and its worktree. Before releasing it, fetch the current base,
verify that the PR is merged and that its merge/squash/rebased result is contained in that base; use the
host's actual merged commit identity, not the pre-merge branch SHA (squash changes it). Missing or
ambiguous integration evidence keeps the wait. Then recompute the next candidate's scope on that base.

Closed-unmerged or stopped work is not integrated. Remove its reservation only after the coordinator
has verified that it is explicitly abandoned or superseded and no active worker will keep writing it;
do not delete worktrees or branches as a scheduling shortcut. Abandoning a dependency does not satisfy it.

Use [the task pipeline](task.md) as the authority for candidate preparation, focused rechecks, independent
review, publication and candidate-bound CI evidence. Update the handoff base after any integration change;
never reuse stale results. Regenerate affected derived files rather than hand-merging or blindly
concatenating routes/imports. Report later PR conflicts as integration follow-ups, not as done.
