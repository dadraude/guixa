# Weekly workflow batch

Applies the retrospective proposals that reached the threshold in one PR. The rules for choosing and
recording proposals live in
[retrospective.md](../../skills/agent-workflow/core/retrospective.md#propose); this reference only
orders the work. Run it in its own session, never inside an `implement-*` session, and stop after the PR.

## 1. Collect

Read every `retrospective.md` under `<worktree-root>/*/.agent-workflow/` (stopped or CI-failed tasks
only; a cleaned worktree takes its retrospective with it, so run the batch before that cleanup). Skip a
proposal already `applied` and check that the workflow files do not already hold the change.

## 2. Tally

Group proposals by problem. A proposal qualifies when the same problem appears in at least two
retrospectives, or when its single occurrence stopped a task, failed CI or cost more than one session of
rework. Every other proposal stays `deferred` with the count of retrospectives that show it.

## 3. Decide with the user

Permission changes, weaker validation or review, altered correction limits and product decisions are
never applied without the user's explicit decision. Show the qualifying list and ask once before editing.

## 4. Apply

Follow [AGENTS.md](../../AGENTS.md#worktrees): its own worktree from `origin/main`, branch
`chore/workflow-batch-<yyyy-mm-dd>`. Amend or replace the authoritative instruction for each applied
proposal; never append a new rule. Validate a new check against the observed mistake and against valid
code. Run the checks the change touches (`python3 .github/scripts/test_commands.py contracts` and
`helpers`).

## 5. Publish

One commit, one PR. The body lists, per applied proposal, the retrospectives it comes from and the signal
the next task will show, and lists what stays `deferred`. Update a proposal's disposition in its
retrospective only for changes that landed. Merging is the user's.
