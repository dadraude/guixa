# Principles

Technical decisions and rules for the whole project. Domain terms are defined in
[`CONTEXT.md`](../CONTEXT.md); significant decisions have an ADR in [`docs/adr/`](adr/).

## Constraints

- **Maintainer:** one developer working with AI agents. Prefer well-known, conservative
  technology, little code and few moving parts over cleverness.
- **Budget:** up to about 10 €/month for all hosting.

## Architecture

- **Monolith: Laravel + Inertia + Vue 3 (TypeScript)** on **PostgreSQL**. One codebase,
  server-side routing, policies and validation; no separate API. See
  [ADR 0002](adr/0002-laravel-inertia-monolith-with-offline-islands.md).
- **No real-time.** Orders have no shared stock limits, so plain request/response is enough.
- **Offline only where needed:** packing **boxes** and recording **payments** must work
  without a reliable connection (the cooperative's premises have poor coverage).
  - These screens are Vue "islands" that download the data they need beforehand, store it and
    every change in IndexedDB, and send an outbox of changes to a sync endpoint when back
    online. A service worker caches only these routes.
  - Several devices may pack at the same time, each on different boxes. Conflicts are rare:
    the last change wins and the user is warned.
  - Everything else (ordering, catalogue, administration) requires a connection.
- **Background work** uses Laravel's Redis queue (with Horizon) and scheduler: closing
  **producer deadlines** and emailing **producer orders**, retrying failed emails,
  processing offline syncs. Jobs are dispatched after the transaction commits. Cache and
  sessions also live in Redis. No external queue service. See
  [ADR 0003](adr/0003-plain-vps-with-redis-queue.md).
- **Money** is stored as integer cents. **Time zone** is `Europe/Madrid`.
- **UI language:** Catalan only, but every user-facing string lives in Laravel/Vue
  translation files so more languages can be added later. Code and docs are in English.

## Infrastructure

- **One VPS** with PostgreSQL, Redis (with AOF persistence), PHP-FPM and nginx installed
  directly (no Docker). Horizon runs as a systemd service; the scheduler runs from cron.
- **Email** is sent through the SMTP account the cooperative already has.
- **Deploys:** Deployer with atomic releases and one-command rollback, triggered by GitHub
  Actions when CI passes on `main`. Migrations must be safe to run with the previous release
  still serving requests.
- **Monitoring:** Laravel emails the maintainer on errors and failed jobs (Horizon shows
  queue details); a free external uptime check watches the site and a scheduler heartbeat.

## Access

- No open sign-up: the membership commission creates **households** and invites **members**
  by email.
- Members sign in with a **magic link** by email or with a **password**. Setting the password
  is required when accepting the **invitation**, so both ways always work.
  Sessions are long-lived (weeks), so offline screens keep working on a phone.
- Permissions come from **commission** membership and are enforced with Laravel policies on
  the server.

## Quality

- **Test-first** (`/tdd`) for every ticket.
  - **Pest** feature tests against a real PostgreSQL are the main safety net.
  - **Vitest** for client-side logic, especially offline storage and sync.
  - **Playwright** for the offline flows and a few critical end-to-end paths.
- **CI (GitHub Actions) blocks merging** unless all pass: tests, Pint, Larastan (high level),
  ESLint, Prettier and `vue-tsc`.

## Data

- Deactivating a household keeps all its data (no deletion or anonymisation). It is allowed
  with a non-zero **balance**, after a warning and confirmation.
- Store only the personal data the cooperative needs to operate.

## Open questions

- **Backups:** strategy not decided yet. Requirement: automated backups stored outside the
  VPS, with a tested restore, before going to production.
- **Data migration** from the current software: not decided yet (see `docs/vision.md`).
