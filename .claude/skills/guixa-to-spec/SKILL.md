---
name: guixa-to-spec
description: Turn the current conversation into a Guixa spec at specs/NNN-name/spec.md (Status draft), then run spec-reviewer. Use when the user types /guixa-to-spec or asks to write a spec after /grill-with-docs.
disable-model-invocation: true
---

Based on the global `to-spec` skill, adapted to the Guixa SDD workflow (`CLAUDE.md`). Take the current
conversation and codebase understanding and produce a spec. Do NOT interview the user; synthesize what
you already know. Talk to the user in Catalan; the spec itself is in English.

## Differences from the global `to-spec`

1. The spec is written to `specs/NNN-name/spec.md` in the repo with `Status: draft`. It is not
   published to the issue tracker and gets no `ready-for-agent` label.
2. Every user story has at least one acceptance criterion that can become an automated test (a Pest
   feature test at the HTTP level by default; Vitest or Playwright where `docs/principles.md` says so). A story without one is not finished.
3. The skill ends by launching `spec-reviewer`. The spec is not offered for approval until the
   reviewer's `Undecided` count is 0.

## Process

1. Explore the repo if you have not already. Use the vocabulary of `CONTEXT.md` and respect the ADRs in
   the area you touch. Pick the next free `NNN` in `specs/`.
2. Sketch the seams at which the feature will be tested. Prefer existing seams and the highest one
   possible; the ideal number is one. Check with the user that they match.
3. Write `specs/NNN-name/spec.md` with the template below, starting with `Status: draft`.
4. Launch the `spec-reviewer` agent with `model: "opus"` and the spec path.
5. Resolve the reviewer's output: write the obvious answers into the spec as acceptance criteria, and
   ask the rest one by one, invoking the `grilling` and `domain-modeling` skills directly (one question at a
   time, as `/grill-with-docs` does; the model cannot invoke that command). Record each decision in the spec.
   An edge case is decided when there is a written decision in the spec, its `decisions.md` or an ADR; one
   explicitly decided as out of scope (listed in the spec's Out of Scope) counts as decided.
6. Re-run `spec-reviewer` until it ends with `Undecided: 0`. From the 2nd pass on, tell it to check the
   earlier items and the new or changed text only; new cases about unchanged text are brought to the user in
   a single list, who decides each one or moves it to Out of Scope. Only then tell the user the spec is ready
   for approval. Never set `Status: approved` yourself.

<spec-template>

Status: draft

## Problem Statement

The problem that the user is facing, from the user's perspective.

## Solution

The solution to the problem, from the user's perspective.

## User Stories

A long, numbered list of user stories covering all aspects of the feature, each in the format:

1. As an <actor>, I want a <feature>, so that <benefit>
   - Acceptance: at least one criterion that can become an automated test.

## Implementation Decisions

- The modules that will be built/modified
- The interfaces of those modules that will be modified
- Technical clarifications from the developer
- Architectural decisions
- Schema changes
- API contracts
- Specific interactions

Do NOT include specific file paths or code snippets; they get outdated. Exception: a prototype snippet
that encodes a decision more precisely than prose (state machine, schema, type shape), trimmed to the
decision-rich parts and noted as coming from a prototype.

## Testing Decisions

- What makes a good test (external behavior only, not implementation details)
- Which modules will be tested
- Prior art for the tests (similar tests in the codebase)

## Out of Scope

Things that are out of scope for this spec.

## Further Notes

Any further notes about the feature.

</spec-template>
