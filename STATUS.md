# Project status

## Done

- **Session 1 (2026-09-30):** initial SDD structure (`CLAUDE.md`, `specs/`).
- **Session 2 (2026-09-30):** SDD workflow based on Matt Pocock's skills
  (ADR 0001). Tickets tracker set up in Linear (team Draude, project Guixa, triage labels);
  specs stay in `specs/`. `docs/agents/` configured. Everything translated to English.
- **Session 3 (2026-09-30):** vision written in `docs/vision.md` with `/grill-with-docs`;
  `CONTEXT.md` started (households, members, commissions, order cycle, producer orders, boxes,
  balances). Open question: data migration from the current software.

- **Session 4 (2026-09-30):** principles written in `docs/principles.md` with
  `/grill-with-docs`: Laravel + Inertia + Vue 3 (TS) monolith with offline islands for packing
  boxes and recording payments (ADR 0002); plain VPS with PostgreSQL and Redis, Horizon,
  Deployer from GitHub Actions (ADR 0003); magic link + optional password, invitation-only;
  Pest + Vitest + Playwright, blocking CI. `CONTEXT.md`: invitation, inactive household.
  Open questions: backup strategy and data migration.

- **Session 5 (2026-10-01):** `/grill-with-docs` on the first feature (households, members,
  commissions). Decisions in `specs/001-households-members-commissions/decisions.md`;
  ADR 0004 (fixed roles assigned to dynamic commissions, administrator outside households);
  `CONTEXT.md`: administrator, inactive member, household number, role, commission updated;
  `principles.md`: password now required when accepting the invitation.
  Glossary reviewed: every term in `CONTEXT.md` has its agreed Catalan UI word (household =
  "unitat familiar", order = "comanda" with no separate cart, box = "caixa"…); definitions
  now say permissions come from roles, not commissions. `README.md` added.

## Next step

**Session 6:** run `/to-spec` from `specs/001-households-members-commissions/decisions.md` to
write `specs/001-households-members-commissions/spec.md` (`Status: draft`), then the user
reviews and approves it.

Still open (not blocking): backup strategy, data migration from the current software.
