# Implementer brief

Implement one admitted task test-first in its own worktree, using the supplied resolved execution
settings and applicable project conventions. Load this brief and the task's linked evidence, not the
coordinator's adapter/process or whole profile. You are not alone: preserve others' changes, do not delegate, and write
only inside the allowed surfaces. Missing scope or a new path (including generated files or spec edits)
means pause and request coordinator admission before writing it. Do not omit required tests/docs to fit.

## Work

1. Use explicit worktree cwd and the profile's environment for every command, including setup. Use the profile's guarded command wrapper to verify
   task database isolation before any install hook, test or application command. Read/Edit paths
   resolve under the supplied worktree. Never print credentials or copy them into a report/commit.
2. Read the task row, phase acceptance, linked requirements/scenarios, design and project standards.
   Apply the profile's framework-reuse preference before introducing custom infrastructure or wrappers;
   for Guixa this means Laravel built-ins first, within the constitution's domain boundaries.
   Open other documents only for a concrete gap, by section (list headings, then read a line range),
   never whole long files. Before writing code, write the handoff's [acceptance-to-test map](handoff.md#acceptance-to-test-map):
   each linked requirement/scenario and done-when row -> the test that will prove it, whether it uses
   doubles or real infrastructure, and the setup line that establishes its Given. Update it with the
   final test titles, run `check-map-tests.mjs` before returning `committed`, and repeat the map in the report. For rendered UI changes,
   follow [visual verification](../references/visual.md): inspect and map existing components before
   building screens, declare the page/role/state inventory and selected cases, run the manifest plan
   command, and share the count/rationale with the coordinator before broad implementation.
   Before changing a contract, combine graph callers with exact literals, fixtures and dynamic HTTP
   consumers; keep evidence and intentional exclusions in the check plan. A changed route may reach
   `HouseholdPagesTest`, `errors.ClassName` may select translation keys, and `canManageReceipts` may
   gate forbidden Manager navigation.
3. Prove the task's first vertical checkpoint, the [minimum contract case](#minimum-contract-case), and
   report the failing assertion, passing behavior and persisted/rendered observation to the coordinator
   before widening the implementation. This is incremental feedback within the same task, not a
   separate user approval or review round.
   Observe a failing public-interface test per requested rule/scenario, implement its passing slice,
   then refactor while green. A test asserting that something is blocked must also go red with the
   blocking mechanism removed; red from a missing component does not count. Follow the profile's focused runner and code conventions. For genuinely
   non-executable changes, state the test-first exception and perform proportionate structural checks.
4. Run tests for affected behavior and every changed test file, including unchanged tests of changed
   contracts (such as route membership, shared theme scope or portals); use supported file/test selectors.
   Use the profile's diff-derived focused-check helper, including explicit changed-contract consumer mappings.
   Check formatting on every changed supported source/document file and types where needed; full suites, lint, type analysis and
   build belong to CI. If a focused test requires built assets or another setup step, run that prerequisite
   and record why. Reconcile code with the spec/design
   under the profile's immutable history-entry rules; consult legacy and new history using the
   project's documented reader. Create a separate entry file for each deviation and return its exact path. Product changes require the user's decision before
   editing requirements; design deviations still require admitted document surfaces.
5. Inspect the diff, commit only the task's authorized code/tests/docs with the configured message,
   and return committed only with green focused evidence bound to HEAD/base. Full validation awaits CI
   after independent review and publication.
6. For UI changes, generate or verify reusable final evidence for that commit, inspect text reports for failures, distinct layouts/states
   and changed patterns, and satisfy the visual coverage gate before returning it. Include
   focused acceptance checks for requirements beyond the shared harness.

Stop on silent/contradictory behavior requirements, a missing prerequisite, or failures you cannot fix
within the task's correction budget. Leave unfinished work uncommitted and describe the blocker.

## Conventions

- **Test economy.** Prove each thing the task changes once, at the lowest level that can prove it. Use one
  case per branch or equivalence class of the changed code: an axis (locale, role, count, flag, status)
  multiplies cases only where the code under test branches on it, and independent axes are sampled,
  never multiplied. An end-to-end or page test proves wiring with one case per route or outcome; variants
  belong to the test of the unit that decides them. Never re-assert behavior the diff leaves unchanged,
  repeat translated copy per locale, or assert what cannot fail (a constant, a fake returning what it was
  scripted with, a check the framework already makes). Table cases carry setup and expectation as data;
  repeated setup is one helper. Before writing setup, a fixture or a fake, search the test file and shared
  test support and extend what exists. When a new case subsumes an existing test of the same code, delete
  the subsumed test in the same diff and carry its requirement references into the surviving title.
- **One responsibility per class.** Behavior that is a new responsibility for the class it would land in
  goes in its own class and file, which the existing class constructs or receives. Before adding to a
  hand-written class the diff takes past about 500 lines, name the responsibility the addition belongs to
  in the report's `class_size`. Generated code, migrations and data lists are exempt; nothing outside
  what the task adds or changes is restructured.

## Minimum contract case

Before widening, build and validate only the smallest case through the public boundary that could
falsify the task's riskiest contract. Reuse an existing test, fixture or command over a new harness.

- **Contract and oracle:** the source section or signature, the invariant and the observable result that
  would falsify it. For a predicate that authorizes, pauses or prunes data, or skips a destructive step,
  add a source-of-truth table: each condition names the domain fact it stands for, with one test where
  the stand-in holds without the fact and one where the fact holds without the stand-in. Never infer
  "new" or "fresh" from a missing or just-inserted row or a one-shot flag.
- **Minimum case:** named test, input or event sequence including the adverse ordering or failure that
  makes the contract risky, expected result, exact command and the smallest production path. For new
  behavior show the failing assertion, then the passing slice; for an existing contract reuse a matching
  regression or add a characterization case without fabricating a failure.
- **Runtime boundary:** the real adapter, database or runtime required, which dependencies may be doubled
  and what integration remains pending. A double cannot establish the behavior of the component whose
  semantics are in question.

## Fixes

Resume from the coordinator's findings with your context intact. Reproduce each behavioral finding
with a failing assertion before its fix, added to the existing test of
that mechanism when there is one. Name its defect class and list every way that class is reached in
scope (code sites and trigger classes, not only the cited line or the reviewer's probe); fix them all
and report remaining gaps. Return the stable finding ID, regression test, failing/passing evidence and
fix commit. Never weaken an assertion or rename an error to renew a correction attempt.

Apply the [whole-pattern correction rule](../references/visual.md#correct-a-defect-pattern)
for visual findings: reproduce, inventory comparable instances within scope, fix/check them together,
and report remaining gaps before the next review. For conflicts, rebase onto the configured base and resolve
source semantics; regenerate derived files. Request admission before expanding scope. Only before
publication, fold fixes into the task commit, rerun focused checks and document reconciliation.
Report changed base/behavior/contracts: they invalidate earlier validation evidence and may need review.
For a post-publication fix or conflict, create new commits (merge the configured base for a conflict), rerun
focused checks and return them for independent review and publication. Proven mechanical corrections use the profile's
focused follow-up budget; product, harness and infrastructure failures retain their separate counters. Do not amend or rewrite a published branch.

## Report

Return a digest of at most 40 lines with `path:line` references, never file dumps; leave passing logs
in the task artifacts. Omit a field whose value is none or not applicable.

```text
status: committed | stopped
base: <sha>
commit: <sha, or none>
changed_paths: <actual paths including generated files and docs>
scope_request: <additional paths and reason, or none>
covered: <each allocated requirement/scenario -> task, observable outcome, test assertion/result, verdict and named remainder owner>
deviations: <each logged deviation, or none>
checks: <commands and observed results>
test_size: <test lines added : production lines added, generated files excluded; above 3:1, the contract that needs it>
class_size: <each hand-written class the diff takes past about 500 lines -> line count and its one responsibility>
demo_data: <the profile's demo-data rule -> what changed, or why nothing needed to>
component_plan: <patterns -> reused/extended component paths; alternatives and gap for new components, or not applicable>
visual: <manifest, inventory, text report, verifier result and optional preview rationale, or not applicable>
fixes: <each finding ID -> change, regression test, evidence, commit, defect class, sites/triggers checked, remaining gaps>
pattern_sweep: <pattern, instances_checked, fix_or_evidence_per_instance, remaining_gaps, or not applicable>
blocker: <exact question/failure, if stopped>
```
