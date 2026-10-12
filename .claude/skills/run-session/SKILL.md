---
name: run-session
description: "Run a full work session for an explicit Linear project: open, work its Todo issues, close with its status update. Ask for the project when omitted."
argument-hint: "<project name or ID>"
disable-model-invocation: true
---

You orchestrate the whole session; agents do the heavy work and you keep a short thread. Talk to the
user in Catalan, generate everything in English, and ask one question at a time. The session rules
live in `CLAUDE.md` and `docs/agents/issue-tracker.md`; this skill is their running order.

## Project selection

The argument selects one Linear project, for example `/run-session Guixa` or
`/run-session P-DRA-2` (Workflow autonomy). If missing, ask which project and wait. Never
infer it from the repository, an earlier session or the latest update. After the workspace check,
resolve it with `get_project`; if unknown or ambiguous, ask for an exact name or ID and wait.
Keep the resolved project ID for every project-scoped call. Do not read a project queue, change
issues or publish updates before this selection is resolved.

## 1. Open

1. `get_workspace`: the workspace must be `Draude`, otherwise stop and tell the user.
2. Resolve the project as above, then read its latest status update (`get_status_updates`,
   `project: <selected project ID>`). Its session number plus one is this session number. With no
   updates, start at 1 and propose a goal from that project's queue. If an existing update has no
   usable number, ask rather than copying another project's sequence. Its next step is the default goal.
3. **Leftovers**: list the selected project's issues in Todo, In Progress and In Review (one
   `list_issues` call per state, each with `project: <selected project ID>` and a `limit`).
   - In Review: check each PR with
     `gh pr view <PR> --json state,reviewDecision,reviews,comments,statusCheckRollup`. Merged and still
     In Review → move it to Done. Still open and clean (checks green or none expected, no changes
     requested, no unanswered comments) → it carries over; mention it. Still open with red checks,
     changes requested, or review comments not yet answered → offer it to the user as a **PR fix**; if
     accepted, move the issue to **Todo** (session scope), otherwise it carries over and you say why it
     needs work. Do not offer a PR whose checks are only pending.
   - Todo or In Progress means the last session did not close cleanly: ask the user, issue by issue,
     whether to take it into this session or send it back to Backlog.
4. **Triage**: every issue in the selected project's Triage state, one by one with the user. Each ends as
   `ready-for-agent`, `ready-for-human` (state Backlog, with that label) or `wontfix` (state Canceled
   with a closing comment).
5. **Scope**: propose one small goal, starting from the status update's next step: usually one to
   three unblocked Backlog issues in the selected project, by blocking order and priority. Ask the user to confirm or change it,
   then move the chosen issues to **Todo**.

Done when: the selected project's Todo holds exactly the confirmed scope and its Triage is empty.
Other projects' queues are untouched; a blocker in another project may be read to establish readiness,
but is not automatically taken into this session.

## 2. Work

Take only the selected project's Todo issues in blocking order:

- **Ticket** (`ready-for-agent`): read `.claude/skills/ship-ticket/SKILL.md` and run its flow for the
  issue, skipping its step 5 (the status update belongs to this skill's close).
- **PR fix** (an In Review issue taken in Open): run the "PR fix" flow in
  `.claude/skills/ship-ticket/SKILL.md`; when the PR is pushed and CI is green again, move the issue back
  to In Review.
- **`ready-for-human`**: the user does it; help as asked and move the issue when they say it is done.
  Any code change that help needs goes to a `ticket-implementer` launch with `model: "sonnet"`.

Between two tickets, suggest `/compact` to the user (or ending the session) when the context is long.
- **Spec work** (vision, principles, a spec, its review, its tickets): run the matching step of the
  SDD workflow in `CLAUDE.md`, in a worktree on a `chore/` or `feature/` branch, and open a PR the same way.

Record every open topic that comes up (a question, a decision to take, a manual check) in the
selected project's Triage as soon as it appears. Follow-ups and escalations inherit their parent
ticket's project and optional milestone (see `docs/agents/issue-tracker.md`); unrelated topics about
another project are not silently added to this session's scope.

If the goal turns out not to fit, tell the user, split it, finish and close the part that fits, and
send the rest back to Backlog.

Done when: every session issue is In Review, Done, or agreed with the user to go back to Backlog.

## 3. Close

1. **Linear**: no issue in the selected project left in Todo or In Progress. Each one sent back to Backlog has a comment
   saying why. Do not move another project's issues to satisfy this check.
2. **Repo**: the main checkout is clean, on `main` and up to date with `origin`; `git worktree list`
   is checked for this session's leftover worktrees. Preserve other sessions' worktrees and local
   changes; report them instead of cleaning or committing them. Everything made this session is
   committed and pushed.
3. **Status update**: draft it as `issue-tracker.md` describes (`**Session NN (YYYY-MM-DD)**`, issues
   and PRs by identifier, decisions with their ADR, the concrete next step, why anything went back to
   Backlog) plus the autonomy metrics block from `issue-tracker.md` (Project status). Count it from
   Linear (selected project and `cause` label) and GitHub (PRs attributed to that project as the
   tracker document defines; `gh pr list --state merged`,
   `gh pr view --json reviews,commits`), never from memory. Show it to the user and post it with
   `save_status_update`, `project: <selected project ID>`, on their OK. Never post it to Guixa
   merely because both projects share this repository.

Done when: the status update is posted. End with a short summary in Catalan: what shipped, what is in
review, the next step.

## Guardrails

- Project selection narrows Linear work, not repository safety: cross-cutting tickets still ship
  alone across all projects in this repository (`ship-ticket` section 1.3), and shared-base and
  worktree rules still apply.
- Never push to `main`, force-push, or merge PRs unless the user asks.
- If a permission is denied, stop and tell the user; do not look for another route.
