# 001 — SQLite migration task details (2026-11-06)

Part of the [canonical registry](../tasks.md); dependencies live there. Technical contract:
[ADR 0002](../../../docs/adr/0002-sqlite-as-the-only-database.md). No behaviour changes: every existing
scenario keeps passing on SQLite. Nothing is in production, so there is no data migration.

These are layer tasks: the schema cannot run on SQLite until the connection exists, and the suite is not
green until the schema is rewritten. T77 is the thinnest slice that runs end to end (one write
use case through the new connection and lock port), and T78 widens it to the whole suite.

## T77

**SQLite connection, serialised writes and the `Locks` port.** Configure the `sqlite` connection with WAL,
`busy_timeout`, foreign keys on and `BEGIN IMMEDIATE` for write transactions. Replace
`pg_advisory_xact_lock` in `DatabaseHouseholdRepository` with a `Locks` port implemented in
Infrastructure. Keep `lockForUpdate()` in the other repositories. Switch `phpunit.xml` to in-memory
SQLite. Test the port and one write use case end to end. Layer task: nothing runs on SQLite without it.

## T78

**Rewrite the schema as portable SQLite migrations.** Replace the existing migrations with one clean set
per context. Declare CHECK constraints and partial unique indexes inline in `create table` /
`create index`; no `ALTER ... CONSTRAINT`. Port the raw SQL: `::bigint` in
`EloquentStockMovementRepository`, `btrim` in the basket adjustment CHECK, `lower(...)` ordering and
email lookups. Done when the whole Pest suite is green on SQLite except the concurrency tests (T79).

## T79

**Concurrency tests for the single-writer model.** Rework the seven `*ConcurrencyTest` /
`WeeklyReceptionRaceTest` files so they prove serialised writers keep each invariant (wallet balance,
one basket per source order, manager and finance role counts, order lines, product use) instead of racing
Postgres connections.

## T80

**Store timestamps in UTC.** Store every timestamp as UTC ISO-8601 text, convert to the cooperative's
timezone at the edge, and replace `timestampTz`. Add a test that runs under a non-UTC `TZ` and a
cutoff-boundary scenario so the local-versus-CI mismatch cannot recur.

## T81

**CI, tooling and documentation on SQLite.** Drop the Postgres and Redis-for-DB provisioning from the
CI backend job, the `guixa_test_draNN` database handling and `DB_USERNAME=postgres` from
`docs/testing.md` and the agent-workflow scripts, and update `README.md`,
`docs/architecture/modules.md` and ADR 0001's "contract tests against PostgreSQL" line.
