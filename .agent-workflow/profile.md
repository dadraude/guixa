# Guixa execution profile

Read this profile once in the coordinator and pass resolved values to workers.
Delegate bounded internal discovery, execution and observation through the existing roles; keep the
main session's context to decisions, identities, scope, concise evidence and blockers. Follow the
[role-scoped handoff](../skills/agent-workflow/core/handoff.md) for isolated briefs and artifact returns. Its standing
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
- Edit admitted `plan.md`/`tasks.md` and create one Deviation file per entry in `history/` (see `specs/README.md#history`).

Ask first:

- Merging any PR (the user merges).
- Product decisions: `spec.md` changes, OQ answers, behaviour changes.
- Publishing new tickets; reorganizing existing tickets, relations, labels or automations.
- Force-push, pushing to `main`, rewriting published history, other remotes, workspaces or credentials,
  deleting branches or tickets, the `guixa`/`guixa_test` databases, machine configuration.

## Identities and paths

| Setting                                   | Value                                                                                                                                       |
| ----------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| Tracker                                   | Linear; workspace URL slug `draude`, team `DRA`, project `Coop`                                                                             |
| Eligible statuses / dependency completion | `Backlog`, `Todo` / `Done`                                                                                                                  |
| Active / stopped status                   | `In Progress` / `Todo`                                                                                                                      |
| Issue key                                 | `DRA-NN`                                                                                                                                    |
| Executable task                           | `Tnn` title prefix matching a row in `specs/*/tasks.md`; title format `Tnn — Title`                                                         |
| Ready label                               | `ready-for-agent`, executable mapped leaves only                                                                                            |
| Relation fields                           | `parentId`, `blockedBy`/`blocks`, `relatedTo` on `save_issue`, where exposed                                                                |
| Codex entrypoint prefix                   | `agent-workflow` (`$agent-workflow-implement-session`, `$agent-workflow-implement-task DRA-NN`, `$agent-workflow-to-tickets`)               |
| Remote / base                             | `origin` / `main`                                                                                                                           |
| Task branch                               | `feature/dra-NN-<3-6 title words>`; `chore/` for tooling, test support or CI; `docs/` for documents only                                    |
| Worktree root                             | Sibling of the selected repository: `<repo-parent>/<repo-name>-worktrees`                                                                   |
| Task worktree                             | `<worktree-root>/dra-NN`                                                                                                                    |
| Implementation owner record               | `<worktree>/.agent-workflow/implementation-owner.json` (ignored runtime state; retain until integration)                                    |
| Test database                             | `guixa_test_draNN`; parallel workers may add `_test_N`                                                                                      |
| Limits                                    | 3 pipelines; 3 substantive reviews; 2 mechanical follow-up reviews; 2 consecutive product failures per stage; 2 recovery attempts per stage |

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

## Model routing

Every dispatch sets its role's model explicitly; a worker never inherits the coordinator's model.
The coordinator runs on the session model the user chose.
Codex pins each tier in `.codex/agents/agent-workflow-<tier>.toml` (efforts: low, medium, high).

| Tier     | Roles                                                | Claude Code | Codex         |
| -------- | ---------------------------------------------------- | ----------- | ------------- |
| Light    | Discovery, Local check runner, Publisher/CI observer | `haiku`     | `gpt-6-luna`  |
| Standard | Implementer, Preparation                             | `sonnet`    | `gpt-6-sol`   |
| Strong   | Reviewer                                             | `opus`      | `gpt-6-astra` |

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
cd "$WORKTREE" && PATH="$WORKTREE_ROOT/.bin:$PATH" node .agent-workflow/scripts/task-command.mjs "$NN" -- <executable> <arguments>
```

Before setup, privately copy `$REPO_ROOT/.env` to the new task worktree, replace its `DB_DATABASE`
with `TEST_DB`, and verify the replacement without logging credentials. Never change the source `.env`.
All setup, tests and artisan commands use the guarded worktree prefix above; the user's `guixa` database and
shared `guixa_test` are not task databases. If isolated database configuration cannot be verified, stop.

| Operation                 | Command in the task worktree                                                                                                                                                                                             |
| ------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Setup                     | `composer install && pnpm install --frozen-lockfile`; build only for a focused-test prerequisite or build reproduction                                                                                                   |
| Focused test              | `php artisan test --filter=<test>` (run serially initially)                                                                                                                                                              |
| Focused checks            | Tests for affected behavior and every changed test file; select frontend files/tests using the installed runner's supported selectors; format all changed supported code/docs; type checks when needed                   |
| Full validation (CI only) | `.github/workflows/tests.yml`: selected `frontend`, `backend` and `tooling` jobs plus the always-on `docs` lint/format check; `ci` aggregates their results; resolve commands from the workflow and dependency manifests |

For visual tasks, use the [shared visual workflow](../skills/agent-workflow/references/visual.md).
Start the task-owned fixture server separately in the isolated worktree after the guarded task
environment is prepared. The installed package script is `dev: vp dev`; the installed `vp dev --help`
supports `--host`, `--port` and `--strictPort`. Use:

```sh
PATH="$WORKTREE_ROOT/.bin:$PATH" node .agent-workflow/scripts/task-command.mjs "$NN" -- pnpm dev --host 127.0.0.1 --port "$PORT" --strictPort
```

Choose an unused task port, keep this command running, and set the tracked visual manifest's `baseUrl`
to that address. Then run the shared visual `preflight` with explicit `CHROME_BIN`, the tracked manifest,
an ignored `.atl/<task>/visual` output and the fixed base commit SHA. Preflight confirms browser and
one representative fixture route are ready; it does not prove source identity, role authorization,
persistence or acceptance. Confirm those separately before relying on a full visual run.
The available visual dimensions are 375, 1024 and 1280 px, light/dark, and 100%/200% root text.
Select cases by risk instead of multiplying every state by every dimension. The minimum is each
affected page/role in its populated state at 375 and 1280 px, light and dark, 100% text. Check each
distinct layout/form/table pattern at 1024 px and 200% text where reflow can change; include both
themes when color or contrast is affected. Check each unique empty, error, disabled, long-content,
domain or overlay state on its owning page at its riskiest width/text/theme, expanding after a defect.
Include controls below the first viewport. Add system appearance, reduced motion and fallback-font
cases where applicable. Preserve the plan/constitution contrast, 44 px target and no-clipping
requirements. Record the selected combinations and rationale before implementation. Store the task
manifest with its committed fixture; keep generated text reports and optional previews in an untracked
task artifact directory. Routine review uses measurements without captures. Generate a small preview
only for a new design direction, unexplained failure or explicit request. Use the shared verifier's
reviewed rendering-input proof to reuse unchanged evidence across commits; otherwise rerun. Focused
application checks and current CI retain their candidate requirements.
Select existing tests of changed contracts even when their test files were not edited.

Use `node .agent-workflow/scripts/check-task.mjs NN FIXED_BASE_SHA CHECK_PLAN_JSON --plan` to inspect
the diff-derived check selection; omit `--plan` to execute it in the guarded environment. The ignored
task-local check plan has `tests` (exact executable test paths), `changedContracts` (each with
`name`, changed `path`, source/discovery `evidence` and existing `consumers` test paths), or an empty
contract list plus `noContractChangesReason`. The coordinator checks discovery completeness; the
helper cannot infer every dynamic consumer. Missing consumer mappings block readiness. Changed
executable tests are always included. Formatter exclusions from the checked-in Vite format config
are reported, preserving append-only specs and generated files; unsupported types need their own
structural check. Retain the helper's JSON selection/results bound to HEAD/base in task artifacts.
Do not put the check plan or outputs into tracked deliverables.

The task-command wrapper rejects unsafe environment/file/XML database overrides, DB URLs and cached
configuration before launching a child. Use its required argv form, not shell command strings.
Prepare the isolated task env first; never pass shared application credentials or unsafe DB overrides
to work around a refusal. Split setup commands into separate guarded invocations.

Verify selector syntax from the installed runner before execution; do not guess a command or silently
expand it to a full local suite. For changes without executable behavior, record the test-first exception
and run relevant structural checks. Never report an omitted check as passed.

The current CI triggers on PRs and pushes to `main`, not a feature-branch push alone. Resolve expected
checks from the current workflow (currently `ci` in `tests`) plus branch requirements before accepting
results. The `tooling` job covers workflow-skill structural and helper tests for agent-tooling changes. Run
the focused offline checks in `skills/agent-workflow/tests` and `.agent-workflow/tests` locally when
these instruction documents change; CI also runs them when `.github/` changes.

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
  `R#` and scenario. The feature's `plan.md` records design; history uses immutable files in `history/`.
  Consult legacy `log.md` plus all relevant new entries with the history helper before changing a feature.
- At task close, reconcile code/tests with those documents. Record authorized design mismatches in
  the affected document and a new Deviation file; preserve existing entries and legacy logs byte-for-byte. Missing
  original Request or a behavior change needs the user's decision, not an invented requirement/log.
- Use one failing Application test per rule/scenario through the public use case, then implement the
  passing slice. Follow repository TDD instructions when available; do not require a global skill install.
- Keep early returns, explicit names, short functions and comments for the why. Verify architecture,
  types and format with the configured checks rather than inventing additional conventions.
- UI reuse: compose pages from `resources/js/components/`, the existing shadcn-vue primitives in
  `resources/js/components/ui/`, and shared layouts/tokens. Record exact component paths before
  implementation; extend existing variants before adding components. Repeated patterns need a shared
  component, and a new component needs the concrete gap and inspected alternatives in its task handoff.
- Shared hotspots: route/provider registration, repository interfaces/implementations, test contracts
  and fixtures, dependency manifests/locks, `resources/js/generated/`, feature plan/tasks and legacy logs. Distinct new `history/` entry files do not share a write surface;
  reserve their exact new paths, while retaining reservations on any shared spec/plan/task edits.
- Task commits: one line, no body or attribution, `<type>(<scope>): <description> (DRA-NN)`; preserve
  the existing branch/type convention. PR body: delivered behavior, covered R#/scenarios, review rounds,
  Deviations (or None). Do not add provider attribution.
- Read Linear status back after integration rather than assuming the merge moved it.
