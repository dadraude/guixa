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
   3. **The user approves the spec** (`Status: approved`). No tickets and no code before that.
   4. `/to-tickets`: splits the spec into vertical-slice tickets in Linear (project Guixa).
   5. `/implement`: one or a few tickets per session, test-first (`/tdd`), then `/code-review`.

Domain vocabulary lives in `CONTEXT.md`; decisions in `docs/adr/NNNN-title.md`.

## Session rules

- Each session has **one small goal** (e.g. "write spec 001", "tickets DRA-3 and DRA-4").
- At the start: read `STATUS.md`, then triage the Guixa issues labelled `needs-triage` with the user:
  each one becomes `ready-for-agent`, `ready-for-human` or `wontfix`.
- At the end: leave nothing half-done, update `STATUS.md` with what was done and the concrete
  next step, commit and push to `origin` (github.com:dadraude/guixa).
- If the goal doesn't fit, split it and close the finished part.

## Ticket flow (`/ship-ticket DRA-NN`)

Standard way to implement a Linear ticket; defined in `.claude/skills/ship-ticket/SKILL.md`.

- Branch `feature|bugfix|…/dra-NN-short-title`, from updated `main`, or from the blocking ticket's
  branch while its PR is still open (stacked; the PR targets that branch until it merges).
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
- Agents never edit `STATUS.md` (parallel branches would conflict); the orchestrator writes the session
  entry on `main` after the PRs merge.
- Never push to `main`, never force-push, never merge PRs without being asked.

## Language

- Conversation with the user: **Catalan**.
- Everything generated (docs, specs, ADRs, tickets, code, comments, commit messages, file names): **English**.

## Agent skills

### Issue tracker

Specs in the repo (`specs/NNN-name/spec.md`); tickets in Linear (team Draude, project Guixa). See `docs/agents/issue-tracker.md`.

### Triage labels

Default five labels (needs-triage, needs-info, ready-for-agent, ready-for-human, wontfix), created in Linear. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: `CONTEXT.md` + `docs/adr/` at the repo root. See `docs/agents/domain.md`.
