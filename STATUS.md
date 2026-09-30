# Project status

## Done

- **Session 1 (2026-09-30):** initial SDD structure (`CLAUDE.md`, `specs/`).
- **Session 2 (2026-09-30):** SDD workflow based on Matt Pocock's skills
  (ADR 0001). Tickets tracker set up in Linear (team Draude, project Guixa, triage labels);
  specs stay in `specs/`. `docs/agents/` configured. Everything translated to English.
- **Session 3 (2026-09-30):** vision written in `docs/vision.md` with `/grill-with-docs`;
  `CONTEXT.md` started (households, members, commissions, order cycle, producer orders, boxes,
  balances). Open question: data migration from the current software.

## Next step

**Session 4 — Principles:** run `/grill-with-docs` to write `docs/principles.md`
(significant decisions become ADRs).

Starting questions:
1. Tech stack (language, framework, database) and why.
2. Hosting and budget; who maintains it after launch.
3. Authentication for members (email magic link, password, …).
4. Testing strategy and quality rules (TDD, CI).
5. Data protection (personal data of members, GDPR) and backups.
