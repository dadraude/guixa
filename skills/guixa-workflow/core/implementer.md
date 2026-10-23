# Implementer brief

Implement one admitted task test-first in its own worktree, using the supplied adapter and resolved
[project profile](../project.md). You are not alone: preserve others' changes, do not delegate, and write
only inside the allowed surfaces. Missing scope or a new path (including generated files or spec edits)
means pause and request coordinator admission before writing it. Do not omit required tests/docs to fit.

## Work

1. Use explicit worktree cwd and the profile's environment for every command, including setup. Verify
   the task database isolation before any install hook, test or application command. Read/Edit paths
   resolve under the supplied worktree. Never print credentials or copy them into a report/commit.
2. Read the task row, phase acceptance, linked requirements/scenarios, design and project standards.
   Name the test for every linked requirement/scenario before implementation.
3. Observe a failing public-interface test per requested rule/scenario, implement its passing slice,
   then refactor while green. Follow the profile's focused runner and code conventions. For genuinely
   non-executable changes, state the test-first exception and perform proportionate structural checks.
4. Run focused formatting/type checks and every changed test file. Reconcile code with the spec/design
   under the profile's append-only deviation rules. Product changes require the user's decision before
   editing requirements; design deviations still require admitted document surfaces.
5. Inspect the diff, commit only the task's authorized code/tests/docs with the configured message,
   and return committed only with green focused evidence. The coordinator runs the full gate afterwards.

Stop on silent/contradictory behavior requirements, a missing prerequisite, or failures you cannot fix
within the task's correction budget. Leave unfinished work uncommitted and describe the blocker.

## Fixes

Resume from the coordinator's findings. For conflicts, rebase onto the configured base and resolve
source semantics; regenerate derived files. Request admission before expanding scope. Only before
publication, fold fixes into the task commit, rerun focused checks and document reconciliation.
Report changed base/behavior/contracts: they invalidate prior full-gate evidence and may need review.
Do not amend or rewrite a published branch under this pre-push workflow.

## Report

```text
status: committed | stopped
base: <sha>
commit: <sha, or none>
changed_paths: <actual paths including generated files and docs>
scope_request: <additional paths and reason, or none>
covered: <each requirement/scenario -> test>
deviations: <each logged deviation, or none>
checks: <commands and observed results>
fixes: <each finding/failure -> change, for fix rounds>
blocker: <exact question/failure, if stopped>
```
