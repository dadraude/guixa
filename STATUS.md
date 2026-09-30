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

## Next step

**Session 5 — First feature:** run `/grill-with-docs` on the first feature to spec. Suggested
start: households, members and commissions (invitations, magic link, permissions), since every
other feature depends on it. Then `/to-spec` for `specs/001-...`.

Still open (not blocking): backup strategy, data migration from the current software.
