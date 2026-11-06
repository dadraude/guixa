# 001 — SQLite migration task details (2026-11-06)

Part of the [canonical registry](../tasks.md); dependencies live there. Technical contract:
[ADR 0002](../../../docs/adr/0002-sqlite-as-the-only-database.md). No behaviour changes: every existing
scenario keeps passing on SQLite. Nothing is in production, so there is no data migration.

Every task keeps the required suite green. T77, T78 and T80 make the code portable while CI still runs
on PostgreSQL. T77 and T78 are independent; T80 follows T78 because both edit the migrations. T79 is the single switch: it moves tests, CI and the
task tooling to SQLite at once. T81 removes what PostgreSQL left behind.

From T78 onwards CI also runs an optional `sqlite` job: the backend suite on in-memory SQLite, reported
but not required. Each portable task lowers its failures; T79 makes it the required backend job.

## T77

**The `Locks` port, with a PostgreSQL adapter.** Replace `pg_advisory_xact_lock` in
`DatabaseHouseholdRepository` with a `Locks` port in Application and a PostgreSQL adapter in
Infrastructure that keeps the advisory lock. Keep `lockForUpdate()` in the other repositories. Test the
port's contract and one write use case through it. Runs on PostgreSQL; the SQLite adapter comes in T79.

## T78

**Rewrite the schema as portable migrations.** Replace the existing migrations with one clean set per
context, built with the schema builder. Drop every CHECK constraint (ADR 0002 rule 3): before
removing one, make sure a domain or Application test proves its invariant (if none does, add the
domain guard and its test), and remove database tests that only exercise the CHECK. Keep partial unique indexes as portable `create index ... where`; no
`ALTER ... CONSTRAINT`. Port the raw SQL: `::bigint` in `EloquentStockMovementRepository`,
`lower(...)` ordering and email lookups. Use only syntax both engines accept. Add the optional `sqlite` CI job. Done when the
suite is green on PostgreSQL and `migrate:fresh` succeeds in the `sqlite` job.

## T79

**Switch to SQLite.** Configure the `sqlite` connection with WAL, `busy_timeout`, foreign keys on and
`BEGIN IMMEDIATE` for write transactions, and add the SQLite `Locks` adapter. Switch `phpunit.xml`, the
CI backend job and `task-command.mjs` to in-memory SQLite, and make the `sqlite` job required. Rework the
seven `*ConcurrencyTest` / `WeeklyReceptionRaceTest` files so they prove serialised writers keep each
invariant (wallet balance, one basket per source order, manager and finance role counts, order lines,
product use) instead of racing PostgreSQL connections. Done when the whole required suite is green on
SQLite.

## T80

**Store timestamps in UTC.** Store every timestamp as UTC ISO-8601 text, convert to the cooperative's
timezone at the edge, and replace `timestampTz`. Add a test that runs under a non-UTC `TZ` and a
cutoff-boundary scenario so the local-versus-CI mismatch cannot recur. Runs on PostgreSQL.

## T81

**Remove PostgreSQL leftovers.** Drop the Postgres and Redis-for-DB provisioning from CI, the
`guixa_test_draNN` database handling and `DB_USERNAME=postgres` from `docs/testing.md`, the profile and
the agent-workflow scripts, and update `README.md`, `docs/architecture/modules.md` and ADR 0001's
"contract tests against PostgreSQL" line. Documents and tooling only.
