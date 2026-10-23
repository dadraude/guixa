# Run one task

The coordinator owns every transition using the selected adapter and [project profile](../project.md).
Workers never launch other workers. This also applies to standalone task requests.

## 1. Admit

Read the issue before updating it. Verify an executable leaf, matching task row, completed dependencies,
integration evidence and compatible allowed edit surfaces using [scheduling](scheduling.md). Validate
required runtime capabilities and authorization for the target remote/session and proposed operations.
On a wait or missing scope/capability, report it before any mutation. Then set the admitted issue active.

## 2. Prepare and implement

The coordinator fetches the approved remote and creates the configured task worktree from the verified
base, serializing shared Git operations. Pass issue/task IDs, branch, root/worktree/database, fixed base
SHA, predecessor evidence, shared owners and allowed edit surfaces to the [implementer](implementer.md).
Use the adapter's implementer role. Keep its identity for fixes; if it cannot resume, pass its full
handoff and current diff to a replacement, never pretend it retained context.

Accept `committed` only after comparing actual paths with admitted scope. A scope request pauses writes
until the coordinator readmits it; a product question goes to the user. `stopped` goes to Stop.

## 3. Gate

Run the profile's full gate in the task worktree with its isolated environment. Report checked HEAD,
base SHA and green/red; on red, include failed checks and the last 40 output lines with secrets removed.
Send failures to the implementer and gate again. Two consecutive red gates stop the task. A green
result covers only that HEAD/base; missing/partial output or unavailable tooling is not green.

## 4. Independent review

Launch a fresh read-only reviewer using [the reviewer brief](reviewer.md), fixed base and scheduling
handoff. At most three rounds, including post-integration reviews; never reset the counter on rebase.
Clean proceeds to preparation for publication. Product questions stop. Fixes in rounds 1/2 return to
the implementer, then gate, then a fresh reviewer. Any unresolved finding after round 3 stops.
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
- Changed HEAD/base or resolved conflict: run the full gate on the final candidate, including after
  a clean rebase. Only identical HEAD/base can reuse earlier green evidence.
- Behavioral/contract changes in resolution, regeneration or gate fixes: require another independent
  review if a round remains; otherwise stop without pushing. A clean review returns here without
  resetting counters. Update the review base rather than reusing the old one.
- Missing evidence, stopped result or exhausted gate/review budget: Stop.

## 6. Publish and clean up

Only after all required checks/reviews cover the final candidate and publication is authorized, send
the exact checked HEAD/base to a publisher. It verifies they still match before push/PR; mismatch
returns to preparation without pushing. Publish using the profile's branch and PR conventions.
On partial/uncertain publication failure, stop and report confirmed remote state; do not blindly retry
or send an already-pushed branch into the pre-push amend/rebase loop. Never force-push automatically.

Record the confirmed PR and keep its reservation. The coordinator removes only the clean owned worktree
and invokes the guarded task-database cleanup from the profile, serially. A cleanup failure preserves
remaining state and is reported. PR-open is the end of delivery, not integration: only the scheduling
policy releases overlapping queued tasks after verified integration.

## Stop

Preserve the worktree and changes. If tracker updates were authorized, post the exact blocker/question
and return the executable issue to Todo; otherwise report locally without a remote mutation. A failed
status update is pending, not a reason to retry publication. Keep the task reservation and report status,
HEAD/base, checks/review rounds, remote outcomes and the exact decision or missing capability needed.
