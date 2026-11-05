---
status: accepted
---

# SQLite is the only database

Guixa serves one cooperative from one installation with a handful of concurrent users, so a database
server is operational weight with no payoff. Every environment (development, tests, CI, production)
runs SQLite. Nothing is in production yet, so there is no data to migrate and the schema is rebuilt
from scratch.

This supersedes the "PostgreSQL in every environment" line of the constitution.

## Rules

1. **One writer, serialised.** Write transactions start with `BEGIN IMMEDIATE`, so writers queue
   instead of failing mid-transaction. The connection uses WAL mode, `busy_timeout` and foreign keys on.
2. **Locking behind one port.** Repositories keep calling `lockForUpdate()`: it is a no-op on SQLite
   and becomes real on Postgres. The household advisory lock is replaced by a `Locks` port in
   Infrastructure, so only that class changes if the engine does.
3. **Portable SQL only.** Constraints and partial unique indexes are declared in `create table` /
   `create index` (SQLite cannot `ALTER ... ADD CONSTRAINT`), using syntax Postgres also accepts.
   No `::` casts, `btrim`, `ilike` or other engine-specific functions.
4. **Schema from scratch.** The existing migrations are replaced by one clean set per context.
5. **Timestamps in UTC.** Stored as ISO-8601 text in UTC and converted to the cooperative's timezone
   at the edge, which also removes the local Madrid vs CI UTC mismatch.
6. **Concurrency tests prove the single-writer model.** They assert that serialised writers keep every
   invariant (wallet balance, one basket per source order, manager role count) instead of racing
   Postgres connections.

## Exit path

Moving to Postgres when the app runs on several servers is a planned change, not a rewrite: rule 2
and rule 3 keep it to the `Locks` port, the connection config and a one-off data copy. Doing so
needs a new ADR.

## Consequences

- No database server locally, in CI or in production; the database file lives on persistent disk.
- Tests run on in-memory SQLite and need no per-ticket database or `DB_USERNAME`.
- A second app instance cannot share the database; that is the trigger for the exit path.
