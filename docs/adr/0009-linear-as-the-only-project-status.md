# 0009 — Linear is the only record of project status

- Status: accepted
- Date: 2026-10-07

Project status lives only in Linear (team Draude, project Guixa). Ticket states, blocking
relations and priority hold the work queue. Open topics that are not tickets yet (questions,
decisions still to take, manual checks) are issues too, usually `ready-for-human`. Each session
ends with one Guixa project status update: what was done and the concrete next step. The repo
no longer has a `STATUS.md`. Milestones group tickets by spec, plus one for going live, so
progress per spec is visible without a separate summary.

## Amendment — explicit project sessions (2026-10-12, DRA-145)

The original single-project wording above describes the initial setup. Workflow autonomy is now a
separate Linear project (`P-DRA-2`) in the same Draude workspace and repository. `/run-session` takes
one explicit project name or ID; if it is missing, unknown or ambiguous, ask and wait before reading
its queue or changing issues. No default to Guixa and no implicit combined session.

Each project owns its queue, session numbering, status updates and autonomy metrics. A new project
starts at session 1; its first reporting window begins at project creation. Follow-ups inherit the
parent ticket's project and optional milestone. Shared repository safeguards still apply across
projects. `docs/agents/issue-tracker.md` defines PR attribution and incomplete-evidence reporting.

This amendment supersedes the Guixa-only session and status rules below; Linear remains the only
record of project status.

## Considered options

- **`STATUS.md` in the repo next to Linear (sessions 1–22):** it repeated ticket states and the
  queue order that Linear already holds, so it went stale between sessions (on 2026-10-07 it
  still listed DRA-59 to DRA-62 as untriaged after they had been triaged). Every session also
  needed its own `chore(status)` branch and PR, and agents had to be kept away from the file so
  parallel branches would not conflict.
- **A slimmer `STATUS.md` with only the session log and the next step:** removes the
  duplication but keeps the extra PR per session and a second place to look.

## Consequences

- Sessions start by reading the latest Guixa status update and triaging issues in the Triage state,
  and end by posting a status update after pushing.
- Decisions still go to ADRs and the specs' `decisions.md`; lessons about tooling go to
  `CLAUDE.md` or the skill that needs them, not to the status update.
- The history of sessions 1–22 stays in git (`STATUS.md` before its removal).
- Knowing where the project stands requires access to Linear, which every session already needs.
