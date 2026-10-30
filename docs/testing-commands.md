# Testing commands

CI keeps the path selection described in [the CI checks guide](../.github/README.md).
The selected jobs still run their complete existing checks. This page describes
the reusable tooling commands and the focused application checks used during an
isolated task.

## Workflow tooling

From the repository root, run only the group relevant to the changed tooling:

```sh
python3 .github/scripts/test_commands.py selection
python3 .github/scripts/test_commands.py contracts
python3 .github/scripts/test_commands.py helpers
```

`selection` checks CI path selection and the final gate. `contracts` runs the
Python workflow contracts in `skills/agent-workflow/tests` and
`.agent-workflow/tests`. `helpers` runs their Node helper and visual harness
tests. CI invokes these same commands. The selectors are fixed: the runner
rejects extra arguments and fails if an expected helper test group is empty.
The helper group needs Chrome for its browser harness tests; CI sets
`CHROME_BIN` to `/usr/bin/google-chrome`.

## Focused application checks

For a task with a DRA issue and its isolated worktree, follow
[the execution profile](../.agent-workflow/profile.md). Its guarded
`task-command.mjs` wrapper verifies the task database before launching PHP or
application commands. Set an inherited task-specific Redis prefix such as
`REDIS_PREFIX="guixa-test-dra${NN}:"` (including the trailing colon) for every
guarded application invocation; the wrapper does not set that prefix. Run the
task's diff-derived `check-task.mjs` plan with
the fixed base SHA and the mapped contract consumers. The plan selects changed
tests, formatting, and relevant types; review its selection before execution.
Use a single PHP test filter or exact test path when diagnosing one case, and
the installed frontend runner's exact test path for a frontend case. Invoke
application commands through the guarded wrapper in that worktree.

`composer test` and `pnpm test` remain the complete backend and frontend suite
commands used in CI. `composer ci:check` combines the application checks;
full validation remains in CI. The tooling commands above are standalone and
do not start an application database. There is no application browser E2E
command in this repository yet.
