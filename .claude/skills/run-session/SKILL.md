---
name: run-session
description: Run one full Guixa work session — open (status, triage, scope), work the Todo issues, close (Todo empty, status update).
disable-model-invocation: true
---

You orchestrate the whole session; agents do the heavy work and you keep a short thread. Talk to the
user in Catalan, generate everything in English, and ask one question at a time. The session rules
live in `CLAUDE.md` and `docs/agents/issue-tracker.md`; this skill is their running order.

## 1. Open

1. `get_workspace`: the workspace must be `Draude`, otherwise stop and tell the user.
2. Read the latest Guixa project status update (`get_status_updates`). The session number is its
   number plus one; its next step is the default goal.
3. **Leftovers**: list Guixa issues in Todo, In Progress and In Review.
   - In Review: check each PR with `gh pr view`. Merged and still In Review → move it to Done.
     Still open → it carries over; mention it.
   - Todo or In Progress means the last session did not close cleanly: ask the user, issue by issue,
     whether to take it into this session or send it back to Backlog.
4. **Triage**: every Guixa issue labelled `needs-triage`, one by one with the user. Each ends as
   `ready-for-agent`, `ready-for-human` or `wontfix` (state Canceled with a closing comment), and
   loses `needs-triage`.
5. **Scope**: propose one small goal, starting from the status update's next step: usually one to
   three unblocked Backlog issues, by blocking order and priority. Ask the user to confirm or change it,
   then move the chosen issues to **Todo**.

Done when: Todo holds exactly the confirmed scope and nothing is left labelled `needs-triage`.

## 2. Work

Take the Todo issues in blocking order:

- **Ticket** (`ready-for-agent`): read `.claude/skills/ship-ticket/SKILL.md` and run its flow for the
  issue, skipping its step 5 (the status update belongs to this skill's close).
- **`ready-for-human`**: the user does it; help as asked and move the issue when they say it is done.
- **Spec work** (vision, principles, a spec, its review, its tickets): run the matching step of the
  SDD workflow in `CLAUDE.md`, in a worktree on a `chore/` or `feature/` branch, and open a PR the same way.

Record every open topic that comes up (a question, a decision to take, a manual check) as a Guixa issue
labelled `needs-triage` as soon as it appears.

If the goal turns out not to fit, tell the user, split it, finish and close the part that fits, and
send the rest back to Backlog.

Done when: every session issue is In Review, Done, or agreed with the user to go back to Backlog.

## 3. Close

1. **Linear**: no Guixa issue left in Todo or In Progress. Each one sent back to Backlog has a comment
   saying why.
2. **Repo**: the main checkout is clean, on `main` and up to date with `origin`; `git worktree list`
   shows only worktrees of PRs that still need work. Everything made this session is committed and pushed.
3. **Status update**: draft it as `issue-tracker.md` describes (`**Session NN (YYYY-MM-DD)**`, issues
   and PRs by identifier, decisions with their ADR, the concrete next step, why anything went back to
   Backlog). Show it to the user and post it with `save_status_update` on their OK.

Done when: the status update is posted. End with a short summary in Catalan: what shipped, what is in
review, the next step.

## Guardrails

- Never push to `main`, force-push, or merge PRs unless the user asks.
- If a permission is denied, stop and tell the user; do not look for another route.
