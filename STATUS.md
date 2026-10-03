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

- **Session 6 (2026-10-02):** `/to-spec` wrote `specs/001-households-members-commissions/spec.md`
  (`Status: draft`). Test seam agreed: Pest feature tests at the HTTP level plus one Playwright
  path (invitation → password → magic-link code). Dev and tests temporarily on MySQL
  (production stays PostgreSQL), noted in `principles.md`.

- **Session 6b (2026-10-02):** spec 001 reviewed with the user and **approved**. Changes:
  **owner** (the Artisan-created administrator; only one who manages administrators, cannot be
  deactivated from the app), household number is an auto-increment ID (name editable),
  every member sees all commissions and their members. `CONTEXT.md` and `decisions.md`
  updated.

- **Session 6c (2026-10-02):** ADR 0005: UUIDv7 primary keys whenever possible; household
  number is a separate auto-increment column. `principles.md`, spec 001 and `CONTEXT.md`
  updated.

- **Session 6d (2026-10-02):** `/grill-with-docs` on project-wide technical decisions:
  Laravel 13 Vue starter kit with Fortify plus our own magic link (ADR 0006); `User` is the
  sign-in identity (`CONTEXT.md`); Laravel Sail locally; PostgreSQL everywhere again (the
  temporary MySQL exception is dropped); PHP 8.5 and Node 22 LTS. `principles.md` and spec 001
  updated.

- **Session 7 (2026-10-02):** `/to-tickets` split spec 001 into 14 vertical-slice tickets in
  Linear (project Guixa, `ready-for-agent`, native blocking relations): DRA-10 skeleton and CI
  → DRA-11 owner + magic link → DRA-12 sign-in screen, DRA-13 administrators, DRA-14
  households → DRA-15 invitations → DRA-16 pending invitations, DRA-17 profile switch,
  DRA-18 commissions, DRA-19 own profile → DRA-20 roles (opens membership work to the
  membership role) → DRA-21 edit/move members, DRA-22 deactivate/reactivate, DRA-23
  directory + warning + Playwright path. Until DRA-20, membership work is administrator-only
  through one shared "can manage membership" rule.

- **Session 8 (2026-10-03):** DRA-10 implemented (PR open, Linear "In Progress"): Laravel 13
  Vue starter kit trimmed to password sign-in (no registration, password reset, two-factor,
  passkeys, email verification, account deletion or welcome page); Pest on PostgreSQL;
  `User` with UUIDv7 key and stubs that make UUIDs the default; Europe/Madrid, `ca` locale,
  four-week sessions, Redis for sessions, cache and queue; Sail with PostgreSQL, Redis and
  Mailpit; all UI text in `lang/ca.json` and `lang/ca/*.php` (Vue uses `t()` from
  `@/lib/i18n`, and a test fails on a missing translation); CI on GitHub Actions (Pest, Pint,
  Larastan level 7, Oxlint/Oxfmt, vue-tsc). The kit's Vite+ replaces ESLint and Prettier
  (ADR 0007). Reviewed with `/code-review` and findings fixed.

## Next step

**Before session 9:** merge the DRA-10 PR once CI is green, move DRA-10 to Done, and make the
`ci` check required in the GitHub branch protection for `main` (CI only blocks merging once
that is set).

**Session 9:** `/implement` DRA-11 (create the owner and sign in with a magic link),
test-first, then `/code-review`.

Still open (not blocking): backup strategy, data migration from the current software.
