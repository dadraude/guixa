---
name: implement-task
description: Take one ready Coop task (DRA-NN) from Todo to an open PR, with sonnet implementing and opus reviewing.
disable-model-invocation: true
---

The pipeline for one task, run by the main session: subagents cannot launch agents, so you launch each
one and keep a short thread. `/implement-session` runs several of these at once; `/implement-task
DRA-NN` runs one. Every agent gets the task's names:

- `NN`: the issue number; `Tnn`: the T id that starts its title.
- Branch: `feature/dra-NN-<3-6 words of the title, kebab-case>`; `chore/` instead for a task that only
  touches tooling, test support or CI (Phase 0 bootstrap-style tasks).
- Worktree: `/Users/eduard/code/practice/guixa-worktrees/dra-NN`.
- Test database: `guixa_test_draNN` (plus `guixa_test_draNN_test_N` for parallel workers).

Every Bash call starts in the main checkout with a fresh environment, so each command the pipeline
runs in the worktree starts with `cd <worktree> && PATH=/Users/eduard/code/practice/guixa-worktrees/.bin:$PATH`
(the `.bin` shim gives PHP 8.5), and Read and Edit take absolute paths under the worktree. Git
operations on the shared repository (`fetch`, `worktree add`, `worktree remove`) run only in the main
session, one at a time, so parallel tasks never race on its ref locks.

Fixes go back to the implementer through `SendMessage`; load it with ToolSearch before its first use.

## 1. Start

Launch an agent with `model: "haiku"` that moves the issue to **In Progress**. Run standalone (no title
from a session), it first reads the issue, returns its title and `Tnn`, and checks that every
`blockedBy` issue is Done; if one is not, report it to the user and end here.

## 2. Create the worktree

From the main checkout:

```sh
git fetch origin
git worktree add ../guixa-worktrees/dra-NN -b <branch> origin/main
```

## 3. Implement

Launch the implementer with `model: "sonnet"`, telling it to follow
`.claude/skills/implement-task/implementer.md` with the names above. Keep its agent ID: every later fix
goes back to the same agent, so it keeps its context.

Done when it reports `committed` (go on) or `stopped` (go to **Stop**).

## 4. Gate

Launch an agent with `model: "haiku"` that runs the task's single full check:

```sh
cd /Users/eduard/code/practice/guixa-worktrees/dra-NN && PATH=/Users/eduard/code/practice/guixa-worktrees/.bin:$PATH DB_DATABASE=guixa_test_draNN composer ci:check
```

It reports `green`, or `red` with the failing checks and the last 40 lines of output.

Red: send the failures to the implementer, then gate again. A second red in a row goes to **Stop**.

## 5. Review

Launch a reviewer with `model: "opus"`, telling it to follow `.claude/skills/implement-task/reviewer.md`
with the names above and the round number. A round is one review; there are at most 3.

- `clean` (no `fix` and no `question` findings): go to **Deliver**.
- Any `question`: go to **Stop**; a product decision is the user's.
- `fix` findings after review 1 or 2: send all of them to the implementer, gate again (step 4), then
  review again with a fresh reviewer.
- Review 3 not clean: go to **Stop** with its findings, without another fix.

## 6. Deliver

Run `git fetch origin` from the main checkout, then launch an agent with `model: "sonnet"` that, with the
worktree command prefix above:

1. Runs `git rebase origin/main` (the branch is not pushed yet). On a conflict where both sides only add
   (imports, routes, list entries), it keeps both, continues the rebase and runs the step 4 command once.
   On any other conflict it runs `git rebase --abort`. Either way, when the rebase is not clean and green
   it reports `blocked` with the conflicting files or the failing checks, and goes no further.
2. Pushes with `git push -u origin <branch>` and runs `gh pr create --base main` with the title of the
   task commit and a body with: what the task builds, the `R#` and scenarios it covers, the review
   rounds, a **Deviations** section listing every Deviation logged in `log.md` (or "None"), and the line
   `🤖 Generated with [Claude Code](https://claude.com/claude-code)`. The `DRA-NN` in the branch moves the
   issue to In Review on its own.

It reports the PR link, or `blocked`. On `blocked`, send the conflict or the failures to the implementer
as a fix round (it rebases and resolves them), gate (step 4; a second red in a row goes to **Stop**), and
deliver again.

Once the PR is open, from the main checkout run `git worktree remove ../guixa-worktrees/dra-NN` and
`php .claude/skills/implement-task/drop-test-databases.php NN`. The branch stays.

Done when the PR is open and the worktree is gone.

## Stop

Launch an agent with `model: "haiku"` that comments on the issue (the reason, and the question or
findings verbatim) and moves it back to **Todo**. Keep the worktree, so the user can inspect it.
Report the stop to the session.
