# Guixa execution profile

Read this profile once in the coordinator and pass resolved values to workers. Its standing
authorization is the single source of permission for workflow operations.

## Standing authorization

Invoking a workflow skill grants these operations for Linear `draude`/`DRA`/`Coop` and GitHub `origin`
through the current `gh` session. Everything else is asked first.

Pre-authorized:

- Read the project's issues, relations, PRs, checks and CI runs.
- Dispatch every admitted task (dependency-ready, compatible, within Limits).
- Set an admitted issue `In Progress`; on Stop, comment the blocker and return it to `Todo`.
- Create and clean up task worktrees, branches and `guixa_test_draNN*` databases.
- Push the task branch, open its PR against `main`, and push new commits for CI fixes and
  merges of `main` when the PR conflicts.
- Edit admitted `plan.md`/`tasks.md` and append Deviation entries to `log.md`.

Ask first:

- Merging any PR (the user merges).
- Product decisions: `spec.md` changes, OQ answers, behaviour changes.
- Publishing new tickets; reorganizing existing tickets, relations, labels or automations.
- Force-push, pushing to `main`, rewriting published history, other remotes, workspaces or credentials,
  deleting branches or tickets, the `guixa`/`guixa_test` databases, machine configuration.

## Identities and paths

| Setting                                   | Value                                                                                                                         |
| ----------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| Tracker                                   | Linear; workspace URL slug `draude`, team `DRA`, project `Coop`                                                               |
| Eligible statuses / dependency completion | `Backlog`, `Todo` / `Done`                                                                                                    |
| Active / stopped status                   | `In Progress` / `Todo`                                                                                                        |
| Issue key                                 | `DRA-NN`                                                                                                                      |
| Executable task                           | `Tnn` title prefix matching a row in `specs/*/tasks.md`; title format `Tnn — Title`                                           |
| Ready label                               | `ready-for-agent`, executable mapped leaves only                                                                              |
| Relation fields                           | `parentId`, `blockedBy`/`blocks`, `relatedTo` on `save_issue`, where exposed                                                  |
| Codex entrypoint prefix                   | `agent-workflow` (`$agent-workflow-implement-session`, `$agent-workflow-implement-task DRA-NN`, `$agent-workflow-to-tickets`) |
| Remote / base                             | `origin` / `main`                                                                                                             |
| Task branch                               | `feature/dra-NN-<3-6 title words>`; `chore/` for tooling, test support or CI; `docs/` for documents only                      |
| Worktree root                             | Sibling of the selected repository: `<repo-parent>/<repo-name>-worktrees`                                                     |
| Task worktree                             | `<worktree-root>/dra-NN`                                                                                                      |
| Test database                             | `guixa_test_draNN`; parallel workers may add `_test_N`                                                                        |
| Limits                                    | 3 task pipelines in flight; 3 review rounds; stop after 2 consecutive failed attempts per validation stage                    |

Resolve `REPO_ROOT` as the main checkout, the parent of
`git rev-parse --path-format=absolute --git-common-dir`, so a coordinator started inside a worktree
still finds it; retain it across all workers instead of recomputing it from their working directory. Derive
`WORKTREE_ROOT` from that root's parent/name, and resolve `WORKTREE`, `NN`, `TEST_DB`, `BRANCH` and
`BASE_REF=origin/main` before dispatch. Validate `NN` as digits. Never reuse or overwrite an existing
task worktree without reconciling its owner/state. Paths containing spaces must remain quoted.

Use the project's configured PHP 8.5 environment. The existing local option is
`$WORKTREE_ROOT/.bin` prepended to `PATH`; verify it exists and `php -v` reports the required version.
If unavailable, ask for an approved runtime path; do not install or change machine configuration.
Do not assume the coordinator's Herd isolation applies to sibling worktrees.

## Tracker constraints

Linear rules the ticket gates rely on:

- A sub-issue has exactly one parent. If a slice seems to belong to two deliverables, it is a separate
  issue linked by blocking or related, not a shared child.
- Sub-issues inherit project, cycle, priority and team from the parent. Pick a parent only when those
  should match; work needing its own owner, cycle or team is a separate top-level issue with blocking.
- A team may auto-close the parent when all sub-issues are done, so a parent must hold only the slices
  that complete its deliverable.

## Commands

Each worker command sets its own cwd and environment; never rely on shell state from a previous call:

```sh
cd "$WORKTREE" && PATH="$WORKTREE_ROOT/.bin:$PATH" PGTZ=UTC DB_DATABASE="$TEST_DB" <command>
```

Before setup, privately copy `$REPO_ROOT/.env` to the new task worktree, replace its `DB_DATABASE`
with `TEST_DB`, and verify the replacement without logging credentials. Never change the source `.env`.
All setup, tests and artisan commands use the worktree prefix above; the user's `guixa` database and
shared `guixa_test` are not task databases. If isolated database configuration cannot be verified, stop.

| Operation                 | Command in the task worktree                                                                                                                                                             |
| ------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Setup                     | `composer install && pnpm install --frozen-lockfile`; build only for a focused-test prerequisite or build reproduction                                                                   |
| Focused test              | `php artisan test --filter=<test>` (run serially initially)                                                                                                                              |
| Focused checks            | Tests for affected behavior and every changed test file; select frontend files/tests using the installed runner's supported selectors; affected-file format/type checks only when needed |
| Full validation (CI only) | `.github/workflows/tests.yml`: `composer setup`, then `composer ci:check`; resolve their current definitions from `composer.json` and `package.json`                                     |

Verify selector syntax from the installed runner before execution; do not guess a command or silently
expand it to a full local suite. For changes without executable behavior, record the test-first exception
and run relevant structural checks. Never report an omitted check as passed.

The current CI triggers on PRs and pushes to `main`, not a feature-branch push alone. Resolve expected
checks from the current workflow (currently `ci` in `tests`) plus branch requirements before accepting
results. This application pipeline does not automatically cover workflow-skill structural tests; run
the offline checks in `skills/agent-workflow/tests` and `.agent-workflow/tests` locally when these
instruction documents change.

The prefix's `PGTZ=UTC` matches CI; a local Europe/Madrid database session can hide timestamp failures.
Discover affected generators from the repository scripts before regenerating; do not invent a command.
Only the coordinator runs shared `git fetch origin`, `git worktree add/remove`, one at a time.
Create worktrees with `git worktree add "$WORKTREE" -b "$BRANCH" "$BASE_REF"`.

After the task's PR is merged and its merged commit is contained in `origin/main`, remove only the
clean, task-owned worktree (never `--force`) and run the existing guarded cleanup helper from the
coordinator with the verified PHP runtime:
`php .agent-workflow/scripts/drop-test-databases.php NN`.
Its allowed namespace is `guixa_test_draNN` and its suffixed worker databases. A cleanup failure is
reported, not retried destructively.

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
  Deviations (or None). Do not add provider attribution.
- Read Linear status back after integration rather than assuming the merge moved it.
