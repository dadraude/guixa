# Guixa spec flow

The rules live in `specs/README.md`: the three routes under "Spec flow or direct fix?" and the four
log entry kinds (**Request**, **Q&A**, **Change**, **Deviation**). Read it first; this reference gives the
order of work.

## Delegation

Keep this session's context to routes, decisions and the user's answers. Send the reading and
drafting below to workers using the selected runtime adapter and profile model routing,
following [role-scoped context](../../skills/agent-workflow/core/handoff.md) and the
spec-side table in the profile. A worker returns a short digest, writes no `spec.md` or area file and never delegates.

| Step                                | Worker (role)                                                                                      | Returns                                                                                                   |
| ----------------------------------- | -------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------- |
| 1 Route: overlap and glossary check | Discovery                                                                                          | Roadmap/feature rows, decision records and glossary terms that collide with the request, with `path:line` |
| 1a Discover scope                   | Discovery for existing obligations, users, outcomes and dependencies; Preparation for a draft      | Source-backed discovery evidence and proposed delivery slices, with unresolved decisions                  |
| 2 Log: read history                 | Discovery                                                                                          | The entries relevant to the request (R#, Request, Q&A, Change, Deviation), or "no original Request found" |
| 2a Gap check                        | Preparation, or a fresh Reviewer for a finished spec                                               | The coverage table of [spec-gaps](spec-gaps.md#delegation)                                                |
| 3 Plan and break down               | Discovery for readiness and implementation paths; Preparation for the `plan.md` / `tasks.md` draft | Evidence list; a draft in the working tree for this session to present                                    |
| 4 Close a task                      | Discovery for code-vs-plan evidence per `R#` and scenario                                          | Matches, mismatches and remainders with owners                                                            |

Do inline only what costs less than a brief: a single-file lookup, the route line, a history `add`.
This session still decides the route, asks the user, edits the documents and creates the history entries.

## 1. Route

For a bug or change request, pick one route (direct fix, spec flow, plan only) and tell the user which
and why in one line, before editing anything. A spec flow moves one document at a time. At each phase gate, check for prior explicit
authorization of the same concrete scope; seek a decision only for new or changed scope.

Done when the user has seen the route.

Before grilling or writing a new spec, read the Roadmap and Features tables in `specs/README.md`,
the current spec and history, relevant implementation evidence, and any linked decision record. Summarize
what each source already settles before asking questions. For each unresolved decision, ask the user
for a concrete example, counterexample and priority when those are not already established. Then
present the real options and trade-offs, test a recommendation against those examples, and ask only
for the missing decision. Do not repeat a question answered by history, accepted documents or the user
in this session. After an answer, record it and advance to the next unresolved case. If the request
overlaps or contradicts an entry, put that decision to the user first. Check every new role, term or name against the glossary in `spec.md`; a collision is settled before
the first question that uses it.

### 1a. Discover scope before the selected slice's spec

Use discovery when the request has several independently useful outcomes, distinct users or decision
owners, or uncertain boundaries/dependencies. Do not use an arbitrary task count as the trigger.
For a single bounded outcome, discovery may be brief and select one slice. Direct fixes stay on their
existing route without a feature manifest. A plan-only change starts from its accepted behavior and
records the design change without reopening settled product decisions. Read existing features,
roadmap, accepted obligations, history and implementation before deciding what remains to deliver.
Retired or superseded scope, including 002 dashboard content, remains retired unless the user makes
a new explicit product decision.

Allocate the `NNN-feature` ID at discovery. Create `discovery.md`, a discovery-only row in the
Features table, and the first immutable Request entry containing the user's original ask verbatim.
Log each answered question as Q&A. In `discovery.md`, record source links, intended outcomes and
users, non-goals, shared constraints/invariants, existing obligations and owners, and the choices that
must be settled before the first slice versus those safe to decide later. Record each candidate
delivery slice with a short name, useful observable outcome, boundary, actual dependency reason,
and observable completion. Recommend the first by value and uncertainty reduced, showing the
alternatives and trade-offs. Selection and product decisions remain with the user; a discovery-only
row or artifact is not authorization to implement.

A delivery slice may contain multiple implementation tasks but must leave a useful result a user or
tester can observe. Keep later slices as this lightweight roadmap; add the selected slice's spec,
then accepted plan, then tasks. Do not prewrite detailed requirements, technical plans, tickets or
implementation for later slices. Keep an indivisible business invariant in one slice when a smaller
cut would be unsafe, and record that reason with bounded checkpoints. Reconcile every existing
accepted `R#`/`S#` with delivered evidence or an explicit owner; defer only newly proposed scope
whose decision and dependency allow it. Retain earlier accepted requirements, scenarios, task rows
and allocations in the cumulative canonical documents. Preserve global `R#`, `S#` and `Tnn` IDs when
later slices enter them.

After a slice integrates, review its allocated acceptance and regression of shared invariants on the
integrated base. Select the next slice using current evidence, then repeat the spec → gap review →
acceptance → plan → acceptance → tasks → acceptance sequence for its scope. Keep the previous slice's
scope decision, acceptance matrix, evidence, and exact reviewed source revisions/commit in an
immutable Change history entry. The canonical documents retain accepted earlier scope while the
manifest points to the currently selected slice's evidence. The first slice's acceptance does not
imply acceptance of the whole initiative. Run the feature-wide review only after the final in-scope
slice integrates.

### Evidence gate

Before advancing the selected slice, run the next-stage command in
[feature evidence validation](feature-validation.md). Repair missing or stale evidence before entering
the stage. Keep the manifest tied to the selected slice and preserve prior-slice evidence, acceptance
matrix and exact reviewed source revisions in immutable history. For an existing accepted feature or
plan-only change, cite actual accepted documents and history for completed stages; preserve approvals
of unchanged scope. The evidence gate checks recorded sources, not product decisions, so use actual
decisions and review results.

The manifest follows the selected delivery slice, not an individual `Tnn`. Run the
`implementation` prerequisite gate before dispatching its first task. Keep delivering and reviewing
independent vertical tasks through their existing task PR gates. Populate subsequent slice-stage
evidence cumulatively at the declared slice review and integration milestone, reusing task acceptance
records and current integrated proof; the validator does not require every task to finish before the
first task review or merge.

## 2. Log

Every edit to a feature's documents and every answered question creates a separate entry in
`specs/NNN-feature/history/`, following [the history contract](../../specs/README.md#history).
A direct fix logs nothing.

Before editing, consult the feature's combined legacy and new history:

```sh
node .agent-workflow/scripts/history.mjs read NNN-feature
```

Omit the feature argument to consult the entire repository history. Search both formats for relevant
requirements/tasks, follow corrections and source links, and read the full combined history when
context is unclear. Existing `log.md` files stay frozen and still count as history. Do not invent a
missing Request: if neither the archive nor new entries contain it, ask for the original verbatim.
For a new feature, record that Request as its first entry file.

Prepare the body in a temporary file, then create an exclusive, uniquely named entry:

```sh
node .agent-workflow/scripts/history.mjs add NNN-feature Change DRA-123 < /tmp/history-body.md
```

Omit the issue argument when there is none. The helper supplies the UTC timestamp, kind and optional
issue heading, preserving the body verbatim. A Change body can be:

```markdown
R12 (`ordering.md`): <what changed>.
Why: <reason, or the OQ / conversation that decided it>.
```

Commit each printed path with its change. Never edit an existing entry or shared index; record a
correction in a new file linking the original. Pass exact entry paths to implementers and reviewers.
Done when every document edit in the diff has its entry.

## 2a. Gap check before the PR

First `git fetch origin` and compare the candidate with current `origin/main`, including open
questions and integrated history. If an existing spec PR is being amended, verify that PR is still
open and reconcile its branch; for a new or already integrated spec, no open PR is required. Work
on a branch from the current `origin/main`, not an old local copy of a PR branch.

Before committing a new or amended spec, walk the gap families in [spec-gaps](spec-gaps.md)
and fix or record each gap as an open question. Open the PR only after that, and say in it that the check ran.

Done when no gap family is unchecked.

## 3. Plan and break down accepted scope

For accepted scope, use the readiness checks in
[planning evidence and handoffs](../../skills/agent-workflow/references/planning.md) before writing
the technical plan or task breakdown. Use the current accepted spec, constitution and relevant design
sources; check implementation paths using the repository's discovery rules. Missing product decisions
return to the spec/OQs; missing technical contracts belong in the proposed plan. Do not mark dependent
tasks ready until the necessary decisions are accepted.

Present `plan.md` before editing `tasks.md`; if the user has already approved that concrete plan,
continue without asking again. Once the plan is accepted, use the
reference's slicing, handoff and coverage checks within the canonical task registry and linked task
details. Preserve feature-global IDs and existing approvals; include the source-backed architecture
overview, bounded outcomes, partial requirement allocations and genuine dependency reasons. Log each
document change as above. Report the frontier separately from authorization to implement.

Task approval does not authorize implementation or ticket publication. For requested ticket drafting
or publication, follow the [shared tickets process](../../skills/agent-workflow/core/tickets.md)
with the runtime adapter and project profile; do not publish as a side effect of editing these docs.

## 4. Close a task

Before opening a task's PR (its Linear issue tracks the status), compare the code against `plan.md`
and the task's allocated acceptance scope for every linked `R#` and scenario. If a requirement is
shared, verify this task's part and the named owner/dependency for the remainder; do not claim the
whole requirement complete or record its planned remainder as a deviation. Carry that allocation in
implementer and reviewer handoffs. Write actual `plan.md`/`tasks.md` mismatches back in the task PR
and log a **Deviation**. A mismatch with `spec.md` or an area file goes to the stacked spec-sync PR
([Sync requirements documents](../../skills/agent-workflow/core/task.md#sync-requirements-documents)),
logged as a **Change**; merging that PR is the user's decision.

Done when the allocated acceptance scope matches the code, its tests pass and any remainder retains
an explicit owner.

## 5. Feature acceptance after the slices

After the last in-scope slice is integrated, run the [feature acceptance review](feature-acceptance.md)
against the current integrated base. A grouping parent reaching `Done` is tracker housekeeping, not
product acceptance. Report the feature verdict and evidence separately. Do not infer user acceptance
or merge a PR from a passing review; respect acceptance already explicitly given for the same
reviewed scope and evidence.
