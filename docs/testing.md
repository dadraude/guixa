# Testing and CI reproduction

This guide describes the current commands. Local work uses focused checks; the pull request
runs the selected complete CI jobs. See the [workflow profile](../.agent-workflow/profile.md)
for task ownership and the [CI check map](ci-checks.md) for path-based job selection.

## Isolate an application test

Work in a task worktree branched from `origin/main`; the main checkout is read-only. The
workflow coordinator creates the worktree, assigns the task's numeric issue number `NN`, and
provisions `guixa_test_draNN` (with `_test_N` worker databases if parallel tests are needed).
Use the project's PHP 8.5 runtime; the configured local option is the sibling worktree root's
`.bin` directory. Verify `php -v` before running PHP checks. CI uses PHP 8.5, Node 22,
PostgreSQL 17, and Redis 7. Local managed services need PostgreSQL and Redis available; a
matching container setup is not configured in this repository.

Task worktrees have no `.env`: never read, copy or create any `.env*` file. The coordinator
creates `guixa_test_draNN` on the local PostgreSQL before dispatch and generates one throwaway
key with `php -r 'echo "base64:".base64_encode(random_bytes(32));'`. Pass it as `APP_KEY`, with
`DB_USERNAME=postgres`, in the environment of every guarded invocation below; the wrapper sets
`DB_DATABASE` to the task database and `phpunit.xml` supplies the rest. The task wrapper validates
the database settings, the PHPUnit configuration, and cached configuration before launching a
child. Stop if it rejects isolation; do not substitute `guixa` or shared `guixa_test`.

Run setup and application commands from the task worktree through the wrapper below. Replace
`NN` with the assigned **digits**, and `WORKTREE_ROOT` with the sibling worktree root;
run each command separately. The wrapper sets `APP_ENV=testing`, `DB_CONNECTION=pgsql`,
the task database, and `PGTZ=UTC`. UTC matches CI and avoids timezone-dependent database
results on a local Europe/Madrid session. The PHP test configuration uses Redis for cache,
queue, and session. Set `REDIS_PREFIX=guixa-test-draNN:` on **every** guarded invocation,
including setup and frontend commands; replace `NN` with the same task number and keep the
trailing colon. The wrapper does not set or validate this prefix. The test base class deletes
keys under the effective Redis prefix, so a shared prefix can delete another task's keys.
Do not clear a shared Redis database.

```sh
cd "$WORKTREE"
APP_KEY="$APP_KEY" DB_USERNAME=postgres REDIS_PREFIX="guixa-test-draNN:" PATH="$WORKTREE_ROOT/.bin:$PATH" node .agent-workflow/scripts/task-command.mjs NN -- composer install
APP_KEY="$APP_KEY" DB_USERNAME=postgres REDIS_PREFIX="guixa-test-draNN:" PATH="$WORKTREE_ROOT/.bin:$PATH" node .agent-workflow/scripts/task-command.mjs NN -- pnpm install --frozen-lockfile
APP_KEY="$APP_KEY" DB_USERNAME=postgres REDIS_PREFIX="guixa-test-draNN:" PATH="$WORKTREE_ROOT/.bin:$PATH" node .agent-workflow/scripts/task-command.mjs NN -- php artisan test --filter=TestName
```

A frontend build is needed only when the focused test requires its Vite manifest or when
reproducing a build failure. Run `pnpm run build` through the same wrapper in that case.
Use repository tests or factories for scenario data. If a failure specifically depends on
seeded demo data, record the seeder and seed state in the reproduction notes; do not seed a
shared database.

## Choose focused checks

Start with the affected behavior, every changed executable test, and existing tests that
consume a changed contract. PHP test suites are `Unit`, `Application`, `Integration`,
`Architecture`, and `Feature` in `phpunit.xml`; choose a test path or an exact
`--filter` rather than running all suites locally. For a frontend spec, use the installed
runner's path selector:

```sh
APP_KEY="$APP_KEY" DB_USERNAME=postgres REDIS_PREFIX="guixa-test-draNN:" PATH="$WORKTREE_ROOT/.bin:$PATH" node .agent-workflow/scripts/task-command.mjs NN -- php artisan test tests/Application/Membership/AccountManagementTest.php
APP_KEY="$APP_KEY" DB_USERNAME=postgres REDIS_PREFIX="guixa-test-draNN:" PATH="$WORKTREE_ROOT/.bin:$PATH" node .agent-workflow/scripts/task-command.mjs NN -- pnpm exec vp test run resources/js/pages/Orders/Edit.spec.ts
```

These are existing example paths; select the files relevant to your change. The frontend
suite is configured for
`resources/js/**/*.spec.ts`. Selectors must be checked against the installed runner
before execution; do not let a mistaken selector expand into a full local suite.
Format every changed supported file. Run `pnpm run types:check` through the wrapper
when the changed contract needs a type check. For documentation-only changes, formatting
and links are the relevant local checks; no application test is implied.

For mapped workflow tasks, `.agent-workflow/scripts/check-task.mjs` selects changed
executable tests and declared consumers, and reports supported formatting checks.
Follow the [profile's check-plan contract](../.agent-workflow/profile.md#commands)
to create the task-local ignored JSON and inspect with `--plan` before executing.
Consumer discovery is a review responsibility: a generated plan cannot prove that every
affected test was found. Keep the plan and results with task artifacts, not tracked docs.

## Reproduce a CI failure

1. Read the failing job and step in `.github/workflows/tests.yml`, then the
   [check map](ci-checks.md). Record the commit, job, failing test or command,
   PHP/Node/service versions, timezone, and any fixture or seed input. Redact secrets.
2. Recreate the relevant task environment above and run the failing test path or selector
   through the wrapper. Add adjacent tests for the same changed contract. For frontend
   failures, run the build, type check, or selected spec that corresponds to the failing
   step. For tooling failures, use the exact offline command listed in the CI job.
3. Fix the cause and rerun the focused checks. The PR then runs the selected complete jobs;
   a feature-branch push alone does not trigger this workflow. Do not report unrun checks
   as passing.

Current top-level commands are `pnpm run check` (lint and format),
`pnpm run types:check`, `pnpm run test` (all frontend specs), and
`composer test` (clear configuration, Pint, PHPStan, and parallel PHP tests).
`composer ci:check` combines the frontend checks with `composer test`; it is a
full local check, not the default focused loop. CI always runs `docs`, selects
`frontend`, `backend`, and `tooling` by changed paths, and requires the
aggregate `ci` check to pass. The backend CI job provisions PostgreSQL and Redis,
runs `composer setup`, then `composer test`; reproduce only the failing part
locally unless a broader run is needed to diagnose it.

## Coverage

`composer test:coverage` runs the PHP suite with coverage of `app/` and `src/` and fails below its
`--min` (see `composer.json`). CI runs it in the backend job. The local PHP 8.5 build has no coverage
driver, so read the total from the CI log. Feature work raises the floor: add tests for the
under-covered existing code a task touches, then raise `--min` to the new total rounded down, as the
[workflow profile](../.agent-workflow/profile.md) describes.
