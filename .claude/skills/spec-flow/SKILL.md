---
name: spec-flow
description: Route a Guixa change through its spec documents and record each history entry in its own versioned file. Use when a bug or change request arrives, when editing a feature's `spec.md`, area files, `plan.md` or `tasks.md`, or before marking a task done.
---

The rules live in `specs/README.md`: the three routes under "Spec flow or direct fix?" and the four
log entry kinds (**Request**, **Q&A**, **Change**, **Deviation**). Read it first; this skill is the
order of work.

## 1. Route

For a bug or change request, pick one route (direct fix, spec flow, plan only) and tell the user which
and why in one line, before editing anything. A spec flow then moves one document at a time, each
waiting for the user's OK.

Done when the user has seen the route.

Before grilling or writing a new spec, read the Roadmap and Features tables in `specs/README.md` and any
decision record they link. If the request overlaps or contradicts an entry, put that decision to the user
first. Check every new role, term or name against the glossary in `spec.md`; a collision is settled before
the first question that uses it.

## 2. Log

Every edit to a feature's documents and every answered question creates a separate entry in
`specs/NNN-feature/history/`, following [the history contract](../../../specs/README.md#history).
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

First `git fetch origin` and check that the spec's PR is still open and that `origin/main` has no newer
version of it with open questions already answered; work on a branch from the current `origin/main`, never
on an old local copy of a PR branch.

Before committing a new or amended spec, walk the gap families in `.claude/skills/spec-gaps/SKILL.md`
and fix or record each gap as an open question. Open the PR only after that, and say in it that the check ran.

Done when no gap family is unchecked.

## 3. Plan and break down accepted scope

After the spec is accepted, use the readiness checks in
[planning evidence and handoffs](../../../skills/agent-workflow/references/planning.md) before writing
the technical plan or task breakdown. Use the current accepted spec, constitution and relevant design
sources; check implementation paths using the repository's discovery rules. Missing product decisions
return to the spec/OQs; missing technical contracts belong in the proposed plan. Do not mark dependent
tasks ready until the necessary decisions are accepted.

Present `plan.md` for the user's OK before editing `tasks.md`. Once the plan is accepted, use the
reference's slicing, handoff and coverage checks within the canonical task registry and linked task
details. Preserve feature-global IDs and existing approvals; include the source-backed architecture
overview, bounded outcomes, partial requirement allocations and genuine dependency reasons. Log each
document change as above. Report the frontier separately from authorization to implement.

Task approval does not authorize implementation or ticket publication. For requested ticket drafting
or publication, follow the [shared tickets process](../../../skills/agent-workflow/core/tickets.md)
with the runtime adapter and project profile; do not publish as a side effect of editing these docs.

## 4. Close a task

Before opening a task's PR (its Linear issue tracks the status), compare the code against `plan.md`
and the task's allocated acceptance scope for every linked `R#` and scenario. If a requirement is
shared, verify this task's part and the named owner/dependency for the remainder; do not claim the
whole requirement complete or record its planned remainder as a deviation. Carry that allocation in
implementer and reviewer handoffs. Write actual `plan.md`/`tasks.md` mismatches back in the task PR
and log a **Deviation**. A mismatch with `spec.md` or an area file goes to the stacked spec-sync PR
([Sync requirements documents](../../../skills/agent-workflow/core/task.md#sync-requirements-documents)),
logged as a **Change**; merging that PR is the user's decision.

Done when the allocated acceptance scope matches the code, its tests pass and any remainder retains
an explicit owner.
