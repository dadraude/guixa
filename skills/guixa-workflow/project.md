# Guixa execution profile

Read this profile once in the coordinator and pass resolved values to workers. It is project
configuration, not permission to run the commands or access a remote service.

## Identities and paths

| Setting | Value |
| --- | --- |
| Tracker | Linear; workspace URL slug `draude`, team `DRA`, project `Coop` |
| Eligible statuses / dependency completion | `Backlog`, `Todo` / `Done` |
| Active / stopped status | `In Progress` / `Todo` |
| Executable task | `Tnn` title prefix matching a row in `specs/*/tasks.md` |
| Ready label | `ready-for-agent`, executable mapped leaves only |
| Remote / base | `origin` / `main` |
| Task branch | `feature/dra-NN-<3-6 title words>`; `chore/` for tooling, test support or CI |
| Worktree root | Sibling of the selected repository: `<repo-parent>/<repo-name>-worktrees` |
| Task worktree | `<worktree-root>/dra-NN` |
| Test database | `guixa_test_draNN`; parallel workers may add `_test_N` |
| Limits | 3 task pipelines in flight; 3 review rounds; stop after 2 consecutive red gates |

Resolve `REPO_ROOT` with `git rev-parse --show-toplevel` in the coordinator's selected checkout;
retain it across all workers instead of recomputing it from their working directory. Derive
`WORKTREE_ROOT` from that root's parent/name, and resolve `WORKTREE`, `NN`, `TEST_DB`, `BRANCH` and
`BASE_REF=origin/main` before dispatch. Validate `NN` as digits. Never reuse or overwrite an existing
task worktree without reconciling its owner/state. Paths containing spaces must remain quoted.

Use the project's configured PHP 8.5 environment. The existing local option is
`$WORKTREE_ROOT/.bin` prepended to `PATH`; verify it exists and `php -v` reports the required version.
If unavailable, ask for an approved runtime path; do not install or change machine configuration.
Do not assume the coordinator's Herd isolation applies to sibling worktrees.

## Commands (only after the corresponding workflow authorization)

Each worker command sets its own cwd and environment; never rely on shell state from a previous call:

```sh
cd "$WORKTREE" && PATH="$WORKTREE_ROOT/.bin:$PATH" PGTZ=UTC DB_DATABASE="$TEST_DB" <command>
```

Before setup, privately copy `$REPO_ROOT/.env` to the new task worktree, replace its `DB_DATABASE`
with `TEST_DB`, and verify the replacement without logging credentials. Never change the source `.env`.
All setup, tests and artisan commands use the worktree prefix above; the user's `guixa` database and
shared `guixa_test` are not task databases. If isolated database configuration cannot be verified, stop.

| Operation | Command in the task worktree |
| --- | --- |
| Setup | `composer install && pnpm install --frozen-lockfile && pnpm run build` |
| Focused test | `php artisan test --filter=<test>` (run serially initially) |
| Focused checks | `composer lint:check`, `composer types:check`, focused tests for every changed test file |
| Full gate | `composer ci:check` |

The prefix's `PGTZ=UTC` matches CI; a local Europe/Madrid database session can hide timestamp failures.
Discover affected generators from the repository scripts before regenerating; do not invent a command.
Only the coordinator runs shared `git fetch origin`, `git worktree add/remove`, one at a time.
Create worktrees with `git worktree add "$WORKTREE" -b "$BRANCH" "$BASE_REF"`.

After a confirmed PR, remove only the clean, task-owned worktree (never `--force`) and run the existing
guarded cleanup helper from the coordinator with the verified PHP runtime:
`php skills/guixa-workflow/scripts/drop-test-databases.php NN`.
Its allowed namespace is `guixa_test_draNN` and its suffixed worker databases. A cleanup failure is
reported, not retried destructively or treated as releasing the PR's reservation.

## Project conventions

- Read `specs/README.md`, `specs/constitution.md`, the task row, its phase's Done when, and every linked
  `R#` and scenario. The feature's `plan.md` records design; `log.md` is append-only.
- At task close, reconcile code/tests with those documents. Record authorized design mismatches in
  the affected document and a Deviation log entry; preserve existing log entries byte-for-byte. Missing
  original Request or a behavior change needs the user's decision, not an invented requirement/log.
- Use one failing Application test per rule/scenario through the public use case, then implement the
  passing slice. Follow repository TDD instructions when available; do not require a global skill install.
- Keep early returns, explicit names, short functions and comments for the why. Verify architecture,
  types and format with the configured checks rather than inventing additional conventions.
- Shared hotspots: route/provider registration, repository interfaces/implementations, test contracts
  and fixtures, dependency manifests/locks, `resources/js/generated/`, feature plan/tasks/log.
- Task commits: one line, no body or attribution, `<type>(<scope>): <description> (DRA-NN)`; preserve
  the existing branch/type convention. PR body: delivered behavior, covered R#/scenarios, review rounds,
  Deviations (or None). Do not add provider attribution. Publication and merge are separate permissions.
- Do not assume branch integration moved Linear status; read it back. No status/relationship update
  is authorized merely by installing or validating these skills.
