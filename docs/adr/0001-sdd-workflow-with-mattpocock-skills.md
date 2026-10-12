# 0001 — SDD workflow with Matt Pocock's skills

- Status: accepted
- Date: 2026-09-30

## Context

Guixa is built with spec-driven development in short sessions. The initial structure
planned a custom flow (`spec.md` + `pla.md` + `tasques.md` per feature, written in Catalan).
The `mattpocock-skills` plugin already provides a complete flow: interview
(`/grill-with-docs`), spec synthesis (`/to-spec`), vertical-slice tickets (`/to-tickets`)
and test-first implementation with review (`/implement`).

## Decision

- Use the plugin's flow as the foundation, with the project-level replacements below.
- Specs live in the repo (`specs/NNN-name/spec.md`); the spec includes implementation
  decisions, so there is no separate plan file. The user approves each spec before tickets.
- Tickets live in the configured Linear project, with the default triage labels (`needs-triage`
  is the Linear Triage state). Project selection is defined in `docs/agents/issue-tracker.md` and ADR 0009.
- Domain docs: single `CONTEXT.md` + `docs/adr/` at the root (replaces `docs/decisions/`).
- All generated artifacts are in English; conversation with the user is in Catalan.
- Session rules (one goal, `STATUS.md`, commit and push) stay in `CLAUDE.md`.
  (`STATUS.md` was replaced by Linear project status updates in ADR 0009.)

## Amendment — project-level SDD skills (2026-10-12, DRA-143)

`/sdd-to-spec` replaces the plugin's `/to-spec`: it writes a draft spec locally and makes
`spec-reviewer` blocking. The spec is offered for human approval only when every listed edge case has
a written decision (`Undecided: 0`); later review passes cover previous findings and changed text.

`/sdd-to-tickets` replaces the plugin's `/to-tickets`: tickets have a roughly 300-line size cap,
testable acceptance criteria (an explicit covering check for documentation-only work), and an
"Out of scope (decided)" section backed by the spec's decisions. These rules reduce avoidable
follow-ups without changing the human spec-approval gate.

The names are project-independent so the skills can be reused. Tracker destinations, spec paths and
domain sources come from `docs/agents/`; project testing rules come from `docs/principles.md`.
The plugin still supplies the design interview. `/ship-ticket` replaces direct `/implement` calls
with the project's implementation and independent-review workflow in `CLAUDE.md`.

## Consequences

- The `/grill-with-docs`, `/sdd-to-spec`, `/sdd-to-tickets` and `/ship-ticket` steps are
  user-invoked to move between phases; `/run-session` can orchestrate them.
- Specs are versioned with the code; ticket progress is tracked in Linear.
- Tracker details are in `docs/agents/issue-tracker.md`.
