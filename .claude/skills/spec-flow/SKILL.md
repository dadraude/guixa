---
name: spec-flow
description: Route a Guixa change through its spec documents and keep the feature's `log.md` current. Use when a bug or change request arrives, when editing a feature's `spec.md`, area files, `plan.md` or `tasks.md`, or before marking a task done.
---

The rules live in `specs/README.md`: the three routes under "Spec flow or direct fix?" and the four
log entry kinds (**Request**, **Q&A**, **Change**, **Deviation**). Read it first; this skill is the
order of work.

## 1. Route

For a bug or change request, pick one route (direct fix, spec flow, plan only) and tell the user which
and why in one line, before editing anything. A spec flow then moves one document at a time, each
waiting for the user's OK.

Done when the user has seen the route.

## 2. Log

Every edit to a feature's documents and every answered question appends one entry to
`specs/NNN-feature/log.md`. A direct fix logs nothing.

If `log.md` is missing, create it with a **Request** entry first. Ask the user for the original
request verbatim; a reconstructed request has no value as a record.

Append at the end of the file, leaving existing entries byte-identical:

```markdown
## YYYY-MM-DD · Change

R12 (`ordering.md`): <what changed>.
Why: <reason, or the OQ / conversation that decided it>.
```

Done when every document edit in the diff has its entry.

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
implementer and reviewer handoffs. Write actual mismatches back to the affected document and log a
**Deviation**; a behavior change still needs the user's decision.

Done when the allocated acceptance scope matches the code, its tests pass and any remainder retains
an explicit owner.
