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
3. Move the issue to **In Progress**.

## 2. Implement

Launch the `ticket-implementer` agent with the full ticket (id, title, description, acceptance
criteria) and the branch. Wait for its report. If it could not commit, stop and tell the user.

## 3. Independent review

Launch the `ticket-reviewer` agent with the ticket and the base ref (`main`, or the blocker's branch).
If the verdict is `CHANGES REQUESTED`, relay the blockers to a new `ticket-implementer` pass (one
correction pass only). Follow-ups are listed in the PR body, not fixed.

## 4. Deliver

Show the user a short summary: what was built, test/lint results (as reported, not re-verified unless
asked), review verdict, open doubts. Then ask once whether to push and open the PR.
On a yes: push with `-u`, `gh pr create --base <base>` with a conventional title
`type(scope): description (DRA-NN)` and a short body ending with
`🤖 Generated with [Claude Code](https://claude.com/claude-code)`, then move the issue to **In Review**.

## 5. Status file

Agents never edit `STATUS.md`. After the PRs merge, write one session entry on `main` yourself.

## Guardrails

- Never push to `main` or force-push. Never merge PRs unless the user asks.
- If a permission is denied, stop and tell the user; do not look for another route.
