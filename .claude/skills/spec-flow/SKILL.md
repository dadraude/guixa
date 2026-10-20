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

## 3. Close a task

Before opening a task's PR (its Linear issue tracks the status), compare the code against `plan.md`
and every `R#` and scenario the task links. Write each mismatch back to the document it contradicts and log it as a
**Deviation**.

Done when every linked `R#` and scenario matches the code and its tests pass.
