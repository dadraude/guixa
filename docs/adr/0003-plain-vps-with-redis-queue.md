# 0003 — Plain VPS with Redis for queues, cache and sessions

- Status: accepted
- Date: 2026-09-30

Guixa runs on a single VPS with PostgreSQL, Redis, PHP-FPM and nginx installed directly,
without Docker. Background work (closing producer deadlines, emailing producer orders,
retries, offline syncs) uses Laravel's Redis queue, monitored with Horizon, plus the Laravel
scheduler; cache and sessions also live in Redis, and PostgreSQL holds only application data.
Everything stays in one place, with no external services, within the ~10 €/month budget.

## Considered options

- **Database queue on Postgres:** one fewer service and jobs are transactional with the data,
  but no Horizon and cache/sessions compete with application data.
- **Serverless (Vercel + Supabase + Inngest/QStash):** free tiers, but three or four
  providers and scheduled work depending on an external service.
- **Managed PaaS (Railway, Render, Fly):** no server to administer, but variable cost that can
  exceed the budget.
- **VPS with Docker:** reproducible, but an extra layer the maintainer chose not to run.

## Consequences

- The queue is not transactional with Postgres: jobs are dispatched after the database
  transaction commits (`afterCommit`).
- Redis must persist to disk (AOF) so queued jobs survive a restart.
- The maintainer is responsible for OS updates, PostgreSQL/Redis upgrades and backups.
- Deploys use Deployer with atomic releases; Horizon (systemd) is restarted on each release.
