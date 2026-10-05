---
name: ship-ticket
description: Standard Guixa flow to take one Linear ticket (DRA-NN) from Backlog to a PR in review. Use when the user types /ship-ticket DRA-NN or asks to implement a ticket.
disable-model-invocation: true
---

Orchestrate the ticket in the argument. Only the main session can launch agents, so you run this flow;
the agents do the heavy work and you keep a short thread. Talk to the user in Catalan.

## 1. Prepare

1. Read the ticket with Linear (`get_issue`, including relations). If a blocker is not Done, tell the user
   which one and which PR holds it.
2. Branch name: `feature|bugfix|improvement|refactor|chore/dra-NN-short-kebab-title`.
   - Blockers merged, or none: create it from an updated `main`.
   - A blocker still has an open PR: create it from that blocker's branch (stacked). The PR will target
     that branch; mention it in the PR body and retarget to `main` once the blocker merges.
   - Never branch from a sibling that is not a blocker.
3. **Cross-cutting tickets ship alone.** If the ticket touches many files outside its own feature
   (formatter or linter sweeps, strict types, mass renames, moving folders), check `gh pr list`: with
   other PRs open, tell the user and ask whether to wait until they merge. Once its PR is open, do not
   open new PRs until it merges.
4. Move the issue to **In Progress**.

## 2. Implement

Launch the `ticket-implementer` agent with the full ticket (id, title, description, acceptance
criteria) and the branch. Wait for its report. If it could not commit, stop and tell the user.

Then sync with the base: `git fetch origin` and `git merge origin/<base>` (merge, never rebase: the
branch may be pushed and force-push is forbidden). If it conflicts, resolve it with the
`resolving-merge-conflicts` skill. If the base brought new commits, run a `ticket-implementer` pass that
only runs the full checks (step 4 of its flow) and fixes what breaks.

## 3. Independent review

Launch both reviewers in parallel (one message, two agents), each with the ticket and the base ref
(`main`, or the blocker's branch):
- `ticket-reviewer`: spec coverage and standards.
- `ticket-correctness-reviewer`: bugs and security.

If either verdict is `CHANGES REQUESTED`, relay the blockers of both to a new `ticket-implementer` pass
(one correction pass only).

Then sort every follow-up into one of two buckets, so none lives only in a PR body:
- **Trivial** (wording, formatting, a comment): fix it in the correction pass, or drop it and say so.
- **Worth doing**: one Linear issue each in project Guixa, label `needs-triage`, related to the
  parent ticket, titled with the change to make; body: file:line, the reviewer's finding and the
  suggested fix. Create them after the user approves the PR (step 4), before opening it.

## 4. Deliver

Show the user a short summary: what was built, test/lint results (as reported, not re-verified unless
asked), both review verdicts, the follow-ups and their bucket, open doubts. Then ask once whether to push and
open the PR.
On a yes: create the follow-up issues, fetch again and, if the base moved since the sync, repeat the
step 2 sync before pushing. Then push with `-u`, `gh pr create --base <base>` with a conventional
title `type(scope): description (DRA-NN)` and a short body that links each follow-up issue and ends with
`🤖 Generated with [Claude Code](https://claude.com/claude-code)`, then move the issue to **In Review**.

## 5. Status file

Agents never edit `STATUS.md`. After the PRs merge, write one session entry on `main` yourself.

## Guardrails

- Never push to `main` or force-push. Never merge PRs unless the user asks.
- If a permission is denied, stop and tell the user; do not look for another route.
