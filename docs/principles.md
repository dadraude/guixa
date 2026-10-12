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
- **SOLID and DDD.** The domain (`Models`, `Auth`) and the use cases (`Actions`) do not depend on
  delivery code (`Http`, `Console`, `Providers`, Inertia), and `Models` and `Mail` do not depend on
  `Actions`. Classes stay small enough for one responsibility. Dependencies point inwards only.
  Rules that can be checked statically are Pest architecture tests; see
  [ADR 0010](adr/0010-solid-and-ddd-enforced-by-arch-tests.md).
- **Base:** Laravel 13 from the official **Vue starter kit** (Inertia, Tailwind, shadcn-vue,
  Wayfinder). Sign-in uses **Fortify** for passwords and rate limits; the magic link and
  member creation are our own code. See [ADR 0006](adr/0006-vue-starter-kit-with-fortify.md).
- **Versions:** PHP 8.5 and Node 22 LTS, the same locally, in CI and on the VPS.
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
- **Primary keys** are UUIDv7 whenever possible; numbers people read (e.g. household number)
  are separate columns. See [ADR 0005](adr/0005-uuidv7-primary-keys.md).
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

## Local development

- **Laravel Herd** serves PHP; **DBngin** runs PostgreSQL and Redis locally (no Docker). CI uses a
  PostgreSQL service container. Production does not use Docker.

## Access

- No open sign-up and no invitations: the owner, administrators and the membership
  commission create **households** and **members** by hand (ADR 0008).
- Members sign in with a **magic link** by email or with a **password**. Setting the password
  is required on first access, so both ways always work.
  Sessions are long-lived (weeks), so offline screens keep working on a phone.
- Permissions come from **commission** membership and are enforced with Laravel policies on
  the server.

## Quality

- **Test-first** (`/tdd`) for every ticket.
  - **Pest** feature tests against a real PostgreSQL are the main safety net (a PostgreSQL
    service container in CI).
  - **Vitest** for client-side logic, especially offline storage and sync.
  - **Playwright** for the offline flows and a few critical end-to-end paths.
- **CI (GitHub Actions) must be green to merge, by convention.** GitHub cannot enforce it: branch
  protection and rulesets are unavailable on a private repository on the free plan. So `/ship-ticket`
  waits for the checks (`gh pr checks <PR> --watch`) and a PR is only reported as ready to merge
  (validated and CI green), and only merged, when they all pass. The workflow ignores the paths listed under `paths-ignore` in
  `.github/workflows/ci.yml`, so a PR touching only those paths reports no checks and, after the wait
  for checks to appear, counts as passing. Any other PR with no checks is not ready to merge. CI runs tests, Pint, Larastan (level 8), Oxlint and
  Oxfmt (through Vite+, the starter kit's own linter and formatter, used instead of ESLint and
  Prettier, see ADR 0007), `vue-tsc` and Playwright.
- **CI steps and local equivalents.** Run the same checks before pushing (the Pest and Playwright
  runs use a test database; in a worktree set `DB_DATABASE=testing_draNN`):

  | CI step | Local equivalent |
  | --- | --- |
  | Build assets (also generates Wayfinder routes) | `npm run build` |
  | Pint | `vendor/bin/pint --test` (or `composer lint:check`; `composer lint` fixes) |
  | Larastan | `vendor/bin/phpstan analyse --memory-limit=1G` (or `composer types:check -- --memory-limit=1G`) |
  | Oxlint, Oxfmt | `npm run check` (`npm run check:fix` fixes) |
  | `vue-tsc` | `npm run types:check` |
  | Pest | `php artisan test --parallel` (or `vendor/bin/pest --parallel`) |
  | Playwright (`e2e` job) | `npm run e2e` (see the note below) |

  `composer ci:check` chains `npm run check`, `npm run types:check` and `composer test` (Pint, Larastan,
  Pest); Larastan needs `--memory-limit=1G`, which the script does not pass, so run it separately.
  `npm run e2e` shares resources: the fixed port 8000 (`reuseExistingServer: false`), the shared Mailpit
  mailbox (SMTP 1025, UI 8025) that `e2e/global-setup.ts` clears at start, and `migrate:fresh` on
  `DB_DATABASE` (default `testing`, the main checkout's database). Run it in one worktree at a time, with
  Mailpit running, `npx playwright install chromium` done once, the built assets and its own
  `DB_DATABASE=testing_draNN`.
- **Rules are enforced by tools, not by review.** Pint adds `declare(strict_types=1)` and strict
  comparisons; Pest architecture tests (`tests/Unit/ArchitectureTest.php`) check project rules such
  as UUIDv7 models and actions with `handle()`; a test fails when a translated string is missing
  from `lang/ca.json`. When a review finding comes back more than once, turn it into one of these.

## Data

- Deactivating a household keeps all its data (no deletion or anonymisation). It is allowed
  with a non-zero **balance**, after a warning and confirmation.
- Store only the personal data the cooperative needs to operate.

## Open questions

- **Backups:** strategy not decided yet. Requirement: automated backups stored outside the
  VPS, with a tested restore, before going to production.
- **Data migration** from the current software: not decided yet (see `docs/vision.md`).
