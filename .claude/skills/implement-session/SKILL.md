---
name: implement-session
description: Implement every ready Linear task of the Coop project in parallel, each one to its own PR.
disable-model-invocation: true
---

You orchestrate a session; agents do the work. A task is **ready** when its Linear issue is in Backlog or
Todo and every issue in its `blockedBy` is Done (no blockers counts as ready). Ready tasks never block
each other, so they all run in parallel. A file two tasks share is resolved when the later task rebases
before its push; a conflict that appears after its PR is open is the user's to resolve at merge.

## 1. Find the ready tasks

Launch one agent with `model: "haiku"`: check with `get_workspace` that the Linear workspace is
`Draude`, list the issues of project Coop (team DRA) in Backlog or Todo, and read each one's relations.
It returns one table: `DRA-NN`, the T id that starts the title, the title, status, and either "ready"
or each blocker that is not Done with its status.

Done when every Backlog and Todo issue of Coop is classified.

## 2. Confirm

Show the user the ready tasks (`DRA-NN`, T id, title) and the blocked ones with their blockers, and ask
them to confirm the ready list or name the tasks to leave out. Wait for the answer. Their confirmation
authorizes, for each confirmed task, the push and the PR.

Then launch one agent with `model: "haiku"` that moves every confirmed issue to **Todo**.

Done when the confirmed issues are in Todo.

## 3. Run the tasks

Run the pipeline in `.claude/skills/implement-task/SKILL.md` for each confirmed task, in T order, with
at most **3 tasks in flight**, passing the title from step 1. Launch agents in the background and move
each pipeline forward as its agent reports; start the next queued task when one ends, with a PR or
stopped. A stopped task never holds the others back.

Done when every confirmed task has a PR or a stop reason.

## 4. Report

One table: task, outcome (PR link, or stopped and why), review rounds, deviations logged, worktree
kept (stopped tasks only). Below it, list every question a stopped task left for the user.
