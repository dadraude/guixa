# Guixa — app for a food cooperative

Built with **Spec-Driven Development (SDD)**, step by step, in short sessions,
using Matt Pocock's skills (`mattpocock-skills` plugin). See `docs/adr/0001-sdd-workflow-with-mattpocock-skills.md`.

## SDD workflow

1. **Vision** (`docs/vision.md`): what the cooperative is, who uses the app, what we will NOT build.
   Worked out with `/grill-with-docs`.
2. **Principles** (`docs/principles.md`): technical decisions and rules for the whole project.
   Worked out with `/grill-with-docs`; significant decisions become ADRs.
3. For each feature:
   1. `/grill-with-docs`: interview until the feature is clear; updates `CONTEXT.md` and `docs/adr/`.
   2. `/to-spec`: writes `specs/NNN-name/spec.md` (`Status: draft`) — problem, user stories,
      implementation and testing decisions.
   3. **Spec review**: the `spec-reviewer` agent (Opus, read-only) lists the edge cases the spec leaves
      undecided (concurrency, lifecycle states, signed out, permissions, sibling screens, failure states).
      Obvious answers go into the spec; the rest are asked one by one with `/grill-with-docs` and recorded.
   4. **The user approves the spec** (`Status: approved`). No tickets and no code before that.
   5. `/to-tickets`: splits the spec into vertical-slice tickets in Linear (project Guixa). Each ticket
      carries the edge cases decided for its stories as acceptance criteria.
   6. `/implement`: one or a few tickets per session, test-first (`/tdd`), then `/code-review`.

Domain vocabulary lives in `CONTEXT.md`; decisions in `docs/adr/NNNN-title.md`.

## Session rules

- Each session has **one small goal** (e.g. "write spec 001", "tickets DRA-3 and DRA-4").
- Linear is the only record of project status (ADR 0009): ticket states, blocking relations and
  priority hold the queue; open topics are issues; each session leaves a project status update.
  There is no status file in the repo.
- At the start: read the latest Guixa project status update, then triage the Guixa issues labelled
  `needs-triage` with the user: each one becomes `ready-for-agent`, `ready-for-human` or `wontfix`.
- At the end: leave nothing half-done, commit and push to `origin` (github.com:dadraude/guixa), and
  post a Guixa project status update with what was done and the concrete next step.
- If the goal doesn't fit, split it and close the finished part.

## Ticket flow (`/ship-ticket DRA-NN`)

Standard way to implement a Linear ticket; defined in `.claude/skills/ship-ticket/SKILL.md`.

- Branch `feature|bugfix|…/dra-NN-short-title`, from updated `main`, or from the blocking ticket's
  branch while its PR is still open (stacked; the PR targets that branch until it merges).
- Always work in a worktree (`../guixa-worktrees/dra-NN`); the main checkout stays on `main` for testing.
  Remove the worktree once the PR is open.
- `ticket-implementer` agent (Sonnet): test-first, linters, full suite, one single-line commit.
- `ticket-reviewer` (Spec and Standards) and `ticket-correctness-reviewer` (bugs and security) agents
  (Opus, read-only), in parallel: independent review on a different model from the implementer. One correction pass.
- Review follow-ups: trivial ones are fixed or dropped; every other one becomes a Linear issue
  (`needs-triage`, related to the parent ticket) linked from the PR body.
- No PR or ticket keeps follow-ups as plain text, also for work done outside `/ship-ticket`: each one is a
  `DRA-NN` issue, created before the PR is opened.
- Linear: In Progress when starting, In Review when the PR is opened.
- Before review and again before pushing, the branch merges `origin/<base>` so conflicts show up in the
  session, not in the PR.
- Cross-cutting tickets (sweeps over many files) ship alone: no other PRs open while theirs is open.
- Migrations keep the real timestamp from `make:migration`; never hand-number them.
- Agents never post project status updates; the orchestrator posts the session's one at the end.
- Never push to `main`, never force-push, never merge PRs without being asked.

## Language

- Conversation with the user: **Catalan**.
- Everything generated (docs, specs, ADRs, tickets, code, comments, commit messages, file names): **English**.

## Agent skills

### Issue tracker

Specs in the repo (`specs/NNN-name/spec.md`); tickets in Linear (team Draude, project Guixa). See `docs/agents/issue-tracker.md`.
Before the first Linear call of a session, check with `get_workspace` that the connected workspace is
`Draude`; if not, stop and tell the user.

### Triage labels

Default five labels (needs-triage, needs-info, ready-for-agent, ready-for-human, wontfix), created in Linear. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: `CONTEXT.md` + `docs/adr/` at the repo root. See `docs/agents/domain.md`.
