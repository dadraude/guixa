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

- Use the plugin's flow instead of writing custom skills.
- Specs live in the repo (`specs/NNN-name/spec.md`); the spec includes implementation
  decisions, so there is no separate plan file. The user approves each spec before tickets.
- Tickets live in Linear (team Draude, project Guixa), with the default triage labels.
- Domain docs: single `CONTEXT.md` + `docs/adr/` at the root (replaces `docs/decisions/`).
- All generated artifacts are in English; conversation with the user is in Catalan.
- Session rules (one goal, `STATUS.md`, commit and push) stay in `CLAUDE.md`.
  (`STATUS.md` was replaced by Linear project status updates in ADR 0009.)

## Consequences

- The `/grill-with-docs`, `/to-spec`, `/to-tickets` and `/implement` skills are user-invoked:
  the user types them to move between phases.
- Specs are versioned with the code; ticket progress is tracked in Linear.
- Tracker details are in `docs/agents/issue-tracker.md`.
