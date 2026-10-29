# <Project> execution profile

Copy to `.agent-workflow/profile.md` at the repository root and fill every `<...>`. Read this profile
once in the coordinator and pass resolved values to workers. Its standing authorization is the single
source of permission for workflow operations.

## Standing authorization

Invoking a workflow skill grants these operations for <tracker workspace/team/project> and <remote>
through <connected session>. Everything else is asked first.

Pre-authorized:

- <reads, dispatch, status changes, worktrees/branches/databases, push and PR, document edits>

Ask first:

- <merging, product decisions, ticket publication or reorganization, destructive or shared-state operations>

## Identities and paths

| Setting                                   | Value                                                                                                                               |
| ----------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| Tracker                                   | <tool; workspace, team, project>                                                                                                    |
| Eligible statuses / dependency completion | <statuses> / <status>                                                                                                               |
| Active / stopped status                   | <status> / <status>                                                                                                                 |
| Issue key                                 | <e.g. `ABC-NN`>                                                                                                                     |
| Executable task                           | <task ID pattern and where task rows live; title format>                                                                            |
| Ready label                               | <label>, executable mapped leaves only                                                                                              |
| Relation fields                           | <native parent, blocking and related fields>                                                                                        |
| Codex entrypoint prefix                   | <prefix>                                                                                                                            |
| Remote / base                             | <remote> / <branch>                                                                                                                 |
| Task branch                               | <pattern>                                                                                                                           |
| Worktree root                             | <path>                                                                                                                              |
| Task worktree                             | <path>                                                                                                                              |
| Implementation owner record               | <ignored task-local file retaining runtime/host, implementer ID and handoff until integration>                                      |
| Test database                             | <name pattern, or none>                                                                                                             |
| Limits                                    | <pipelines; substantive reviews; mechanical follow-up reviews; consecutive product failures per stage; recovery attempts per stage> |

<How to resolve paths and the runtime environment for workers.>

## Model routing

Every dispatch sets its role's model explicitly; a worker never inherits the coordinator's model.

| Tier     | Roles                                                | Claude Code | Codex      |
| -------- | ---------------------------------------------------- | ----------- | ---------- |
| Light    | Discovery, Local check runner, Publisher/CI observer | <model>     | <model ID> |
| Standard | Implementer, Preparation                             | <model>     | <model ID> |
| Strong   | Reviewer                                             | <model>     | <model ID> |

## Tracker constraints

<Tracker rules the ticket gates rely on: parent cardinality, inherited fields, automations.>

## Commands

<Worker command prefix and pre-execution database/configuration guard. Invoke executables with argv,
not shell strings; establish effective isolated settings before install hooks, tests or app commands.>

| Operation                 | Command in the task worktree                                                                   |
| ------------------------- | ---------------------------------------------------------------------------------------------- |
| Setup                     | <command>                                                                                      |
| Focused test              | <command>                                                                                      |
| Focused checks            | <diff-derived formatting/test helper; changed-contract consumer mappings; explicit exclusions> |
| Full validation (CI only) | <workflow and commands>                                                                        |

<Default UI review uses measurement/DOM/interaction reports. Previews are reserved for new design,
unexplained failures or user requests. Reuse visual evidence only with reviewed, unchanged rendering
inputs and current verification; full application CI remains candidate-bound.>

<Retain separate budgets, failure mechanisms, task disposition/reservations and stage timestamps in
the implementation-owner record; initialize the shared state helper with profile limits.>

<Cleanup after verified integration: worktree removal and any project cleanup script under
`.agent-workflow/scripts/`.>

## Project conventions

- <Documents to read before a task and how to reconcile them at close.>
- <Test-first and code style rules.>
- <Shared hotspots.>
- <Commit message and PR body format.>
