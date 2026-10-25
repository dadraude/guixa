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
   Name the test for every linked requirement/scenario before implementation. For rendered UI changes,
   follow [visual verification](../references/visual.md): inspect and map existing components before
   building screens, declare the page/role/state matrix, and use the shared harness.
3. Observe a failing public-interface test per requested rule/scenario, implement its passing slice,
   then refactor while green. Follow the profile's focused runner and code conventions. For genuinely
   non-executable changes, state the test-first exception and perform proportionate structural checks.
4. Run tests for affected behavior and every changed test file, including unchanged tests of changed
   contracts (such as route membership, shared theme scope or portals); use supported file/test selectors.
   Check formatting on every changed supported source/document file and types where needed; full suites, lint, type analysis and
   build belong to CI. If a focused test requires built assets or another setup step, run that prerequisite
   and record why. Reconcile code with the spec/design
   under the profile's append-only deviation rules. Product changes require the user's decision before
   editing requirements; design deviations still require admitted document surfaces.
5. Inspect the diff, commit only the task's authorized code/tests/docs with the configured message,
   and return committed only with green focused evidence bound to HEAD/base. Full validation awaits CI
   after independent review and publication.
6. For UI changes, generate final evidence on that commit, inspect every capture and satisfy the visual
   coverage gate before returning it. Include acceptance-specific checks beyond the shared harness.

Stop on silent/contradictory behavior requirements, a missing prerequisite, or failures you cannot fix
within the task's correction budget. Leave unfinished work uncommitted and describe the blocker.

## Fixes

Resume from the coordinator's findings. Apply the [whole-pattern correction rule](../references/visual.md#correct-a-defect-pattern)
for visual findings: reproduce, inventory comparable instances within scope, fix/check them together,
and report remaining gaps before the next review. For conflicts, rebase onto the configured base and resolve
source semantics; regenerate derived files. Request admission before expanding scope. Only before
publication, fold fixes into the task commit, rerun focused checks and document reconciliation.
Report changed base/behavior/contracts: they invalidate earlier validation evidence and may need review.
For a post-publication fix or conflict, create new commits (merge the configured base for a conflict), rerun
focused checks and return them for independent review and publication. Do not amend or rewrite a published branch.

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
component_plan: <patterns -> reused/extended component paths; alternatives and gap for new components, or not applicable>
visual: <manifest, inventory, report/gallery, verifier result and capture inspection, or not applicable>
fixes: <each finding/failure -> change, for fix rounds>
pattern_sweep: <pattern, instances_checked, fix_or_evidence_per_instance, remaining_gaps, or not applicable>
blocker: <exact question/failure, if stopped>
```
