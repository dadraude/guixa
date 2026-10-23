# Implementer brief

Implement one admitted task test-first in its own worktree, using the supplied resolved execution
settings and applicable project conventions. Load this brief and the task's linked evidence, not the
coordinator's adapter/process or whole profile. You are not alone: preserve others' changes, do not delegate, and write
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
4. Run tests for affected behavior and every changed test file; use supported file/test selectors.
   Run only formatting/type checks needed for the changed surface; full suites, lint, type analysis and
   build belong to CI. If a focused test requires built assets or another setup step, run that prerequisite
   and record why. Reconcile code with the spec/design
   under the profile's append-only deviation rules. Product changes require the user's decision before
   editing requirements; design deviations still require admitted document surfaces.
5. Inspect the diff, commit only the task's authorized code/tests/docs with the configured message,
   and return committed only with green focused evidence bound to HEAD/base. Full validation awaits CI
   after independently reviewed, explicitly authorized publication.

Stop on silent/contradictory behavior requirements, a missing prerequisite, or failures you cannot fix
within the task's correction budget. Leave unfinished work uncommitted and describe the blocker.

## Fixes

Resume from the coordinator's findings. For conflicts, rebase onto the configured base and resolve
source semantics; regenerate derived files. Request admission before expanding scope. Only before
publication, fold fixes into the task commit, rerun focused checks and document reconciliation.
Report changed base/behavior/contracts: they invalidate earlier validation evidence and may need review.
For an authorized post-publication fix, create a new commit, rerun focused checks and return it for
independent review and authorized publication. Do not amend or rewrite a published branch.

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
