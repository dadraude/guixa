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

| Setting                                   | Value                                                    |
| ----------------------------------------- | -------------------------------------------------------- |
| Tracker                                   | <tool; workspace, team, project>                         |
| Eligible statuses / dependency completion | <statuses> / <status>                                    |
| Active / stopped status                   | <status> / <status>                                      |
| Issue key                                 | <e.g. `ABC-NN`>                                          |
| Executable task                           | <task ID pattern and where task rows live; title format> |
| Ready label                               | <label>, executable mapped leaves only                   |
| Relation fields                           | <native parent, blocking and related fields>             |
| Codex entrypoint prefix                   | <prefix>                                                 |
| Remote / base                             | <remote> / <branch>                                      |
| Task branch                               | <pattern>                                                |
| Worktree root                             | <path>                                                   |
| Task worktree                             | <path>                                                   |
| Test database                             | <name pattern, or none>                                  |
| Limits                                    | <pipelines in flight; review rounds; failed attempts>    |

<How to resolve paths and the runtime environment for workers.>

## Tracker constraints

<Tracker rules the ticket gates rely on: parent cardinality, inherited fields, automations.>

## Commands

<Worker command prefix and isolation rules.>

| Operation                 | Command in the task worktree |
| ------------------------- | ---------------------------- |
| Setup                     | <command>                    |
| Focused test              | <command>                    |
| Focused checks            | <rule>                       |
| Full validation (CI only) | <workflow and commands>      |

<Cleanup after verified integration: worktree removal and any project cleanup script under
`.agent-workflow/scripts/`.>

## Project conventions

- <Documents to read before a task and how to reconcile them at close.>
- <Test-first and code style rules.>
- <Shared hotspots.>
- <Commit message and PR body format.>
