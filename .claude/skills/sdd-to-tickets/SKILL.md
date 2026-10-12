---
name: sdd-to-tickets
description: "Use /sdd-to-tickets to split an approved spec or plan into small vertical slices with testable criteria, decided exclusions and native blocking relations."
disable-model-invocation: true
---

# To Tickets (sized, with decided scope)

Break a plan, spec, or conversation into a set of **tickets**: tracer-bullet vertical slices, each declaring the tickets that **block** it.

The issue tracker and triage label vocabulary come from the project's `docs/agents/issue-tracker.md` and `docs/agents/triage-labels.md`. Read them first: they say where tickets are created, how blocking edges, milestones and labels are set, and what the ticket body must start with. If they do not exist, tell the user to set the project up first.

## Process

### 1. Gather context

Work from whatever is already in the conversation context. If the user passes a reference (a spec path, an issue number or URL) as an argument, fetch it and read its full body and comments, including the spec's decisions and its list of deliberate exclusions.

### 2. Explore the codebase (optional)

If you have not already explored the codebase, do so to understand the current state of the code. Ticket titles and descriptions should use the project's domain glossary vocabulary, and respect ADRs in the area you're touching.

Look for opportunities to prefactor the code to make the implementation easier. "Make the change easy, then make the easy change."

### 3. Draft vertical slices

Break the work into **tracer bullet** tickets.

<vertical-slice-rules>

- Each slice cuts a narrow but COMPLETE path through every layer (schema, API, UI, tests): vertical, NOT a horizontal slice of one layer
- A completed slice is demoable or verifiable on its own
- Each slice is sized to fit in a single fresh context window
- Any prefactoring should be done first

</vertical-slice-rules>

<size-cap>

- **Size cap:** roughly 300 diff lines (code, tests and docs together) is the hard cap.
- **Split rule:** the number of areas touched (for example schema, an action, a screen, a mail, a policy) is only a signal: more than 3 areas usually means more than one behaviour, so look for a split. Split along behaviour, keeping each piece a vertical slice, not along layers.
- If a slice cannot get under the cap, or cannot be split by behaviour, without becoming a horizontal slice, say so when quizzing the user instead of silently creating an oversized ticket.

</size-cap>

Give each ticket its **blocking edges**: the other tickets that must complete before it can start. A ticket with no blockers can start immediately.

**Wide refactors are the exception to vertical slicing.** A **wide refactor** is one mechanical change (rename a column, retype a shared symbol) whose **blast radius** fans across the whole codebase, so a single edit breaks thousands of call sites at once and no vertical slice can land green. Don't force it into a tracer bullet; sequence it as **expand–contract**. First expand: add the new form beside the old so nothing breaks. Then migrate the call sites over in batches sized by blast radius (per package, per directory), each batch its own ticket blocked by the expand, keeping CI green batch to batch because the old form still exists. Finally contract: delete the old form once no caller remains, in a ticket blocked by every migrate batch. When even the batches can't stay green alone, keep the sequence but let them share an integration branch that all block a final integrate-and-verify ticket; green is promised only there. The size cap applies to each batch, not to the whole refactor.

### 4. Quiz the user

Present the proposed breakdown as a numbered list. For each ticket, show:

- **Title**: short descriptive name
- **Blocked by**: which other tickets (if any) must complete first
- **What it delivers**: the end-to-end behaviour this ticket makes work
- **Size**: estimated diff lines and the areas it touches
- **Out of scope (decided)**: what it deliberately leaves out

Ask the user:

- Does the granularity feel right? (too coarse / too fine)
- Are the blocking edges correct: does each ticket only depend on tickets that genuinely gate it?
- Should any tickets be merged or split further?

Iterate until the user approves the breakdown.

### 5. Publish the tickets to the configured tracker

Publish the approved tickets. **How** depends on the tracker described in `docs/agents/issue-tracker.md`; the tickets are the same either way, only the shape of the blocking edges changes:

- **Local files** → write one file per ticket under `.scratch/<feature-slug>/issues/<NN>-<slug>.md`, numbered from `01` in dependency order (blockers first). Each file's "Blocked by" lists the numbers/titles it depends on. Use the per-ticket file template below: one ticket per file, never a single combined file.
- **A real issue tracker (GitHub, Linear, …)** → publish one issue per ticket in dependency order (blockers first) so each ticket's blocking edges can reference real identifiers. Use the platform's native blocking relationship where it has one; otherwise set each ticket's "Blocked by" to the blocking issues. Follow the tracker document for the body prefix, milestone and parent. If the source was an existing issue, make each ticket its sub-issue. Apply the `ready-for-agent` triage label unless instructed otherwise; the tickets are agent-grabbable by construction.

Work the **frontier**: any ticket whose blockers are all done. For a purely linear chain that means top to bottom.

Do NOT close or modify any parent issue.

### Rules for every ticket

- **Acceptance criteria include the edge cases decided for the ticket's stories** (roles, states, empty and boundary values, repeated requests, sibling screens), taken from the spec and its decisions.
- **At least one acceptance criterion must be checkable by an automated test.** Write it so a test can fail without the change (observable behaviour, not "code is clean"). A ticket with no such criterion is not ready: rework it or, if it is docs-only, name the check that covers it (e.g. `bash .claude/hooks/tests/run.sh`), or say there is none.
- **"Out of scope (decided)" lists only things the spec's decisions deliberately exclude.** Do not invent exclusions and do not list undecided questions: those go back to the user. Anything listed there is a decision, not a follow-up: implementers do not implement it and reviewers do not report its absence (they still report code that implements or breaks it, or an item the spec does not back). It only holds exclusions decided in the spec before the tickets are created; what a user rules out of scope during `/ship-ticket` stays a follow-up. Write "None" when nothing was excluded.

<local-ticket-template>

# <NN>: <Ticket title>

**What to build:** the end-to-end behaviour this ticket makes work, from the user's perspective, not a layer-by-layer implementation list.

**Blocked by:** the numbers/titles of the tickets that gate this one, or "None (can start immediately)".

**Status:** ready-for-agent

- [ ] Acceptance criterion 1 (at least one is verifiable by an automated test)
- [ ] Acceptance criterion 2

## Out of scope (decided)

- Something the spec deliberately excludes, or "None".

</local-ticket-template>

<issue-template>

## Parent

A reference to the parent issue on the tracker (if the source was an existing issue, otherwise omit this section).

## What to build

The end-to-end behaviour this ticket makes work, from the user's perspective, not layer-by-layer implementation.

## Acceptance criteria

- [ ] Criterion 1 (at least one is verifiable by an automated test)
- [ ] Criterion 2

## Out of scope (decided)

- Something the spec deliberately excludes, or "None".

## Blocked by

- A reference to each blocking ticket, or "None (can start immediately)". Omit this section when blockers were set as native edges.

</issue-template>

In either form, avoid specific file paths or code snippets: they go stale fast. Exception: if a prototype produced a snippet that encodes a decision more precisely than prose can (state machine, reducer, schema, type shape), inline it and note briefly that it came from a prototype. Trim to the decision-rich parts, not a working demo, just the important bits.
