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
   3. **Spec review**: the `spec-reviewer` agent (`model: "opus"`, read-only) lists the edge cases the spec leaves
      undecided (concurrency, lifecycle states, signed out, permissions, sibling screens, failure states).
      Obvious answers go into the spec; the rest are asked one by one with `/grill-with-docs` and recorded.
   4. **The user approves the spec** (`Status: approved`). No tickets and no code before that.
   5. `/to-tickets`: splits the spec into vertical-slice tickets in Linear (project Guixa). Each ticket
      carries the edge cases decided for its stories as acceptance criteria.
   6. `/ship-ticket DRA-NN`: one or a few tickets per session; the `ticket-implementer` agent builds
      test-first (`/tdd`) and two reviewer agents check it. Do not use the plugin's `/implement`: it
      runs inline on the main session's model instead of the implementer's.

Domain vocabulary lives in `CONTEXT.md`; decisions in `docs/adr/NNNN-title.md`.

## Models

| Who | Model | Where it is set |
| --- | --- | --- |
| Main session: `/grill-with-docs`, `/to-spec`, `/to-tickets`, triage | Opus | the user's `/model`; never pinned in `.claude/settings.json` |
| Main session: `/ship-ticket`, `/run-session` work phase (git, Linear, launching agents) | Sonnet recommended | the user's `/model` |
| `ticket-implementer` | Sonnet | agent frontmatter |
| `ticket-reviewer`, `ticket-correctness-reviewer`, `spec-reviewer` | Opus | agent frontmatter |

Every agent launch passes the `model` from this table explicitly: an Agent call's `model` overrides the
agent's frontmatter, so a generic default (such as `sonnet` for every delegation) would silently downgrade
the reviewers. Implementation and review must stay on different models.

The main session never writes application code or tests, inside or outside `/ship-ticket`: every code
change (features, fixes, review findings, merge conflicts, help on a `ready-for-human` issue) goes to a
`ticket-implementer` launch with `model: "sonnet"`. The main session only writes docs, specs, ADRs and
agent/skill files.

### Keeping cost down

- Context: one ticket per session when possible; otherwise `/compact` between tickets. Long contexts
  cost more even when cached.
- Linear: filter every list call (state, label, project) and set a `limit`; never re-read an issue that
  is already in context.

## Session rules

`/run-session` runs a whole session in this order (open, work, close); defined in
`.claude/skills/run-session/SKILL.md`.

- Each session has **one small goal** (e.g. "write spec 001", "tickets DRA-3 and DRA-4").
- Linear is the only record of project status (ADR 0009): ticket states, blocking relations and
  priority hold the queue; open topics are issues; each session leaves a project status update.
  There is no status file in the repo.
- At the start: read the latest Guixa project status update, then triage the Guixa issues in the
  Triage state with the user: each one becomes `ready-for-agent`, `ready-for-human` (Backlog) or `wontfix` (Canceled).
  Then choose the session's issues with the user and move them to **Todo**: Todo is the session scope.
- At the end: leave nothing half-done, commit and push to `origin` (github.com:dadraude/guixa), and
  post a Guixa project status update with what was done and the concrete next step. Todo must be
  empty: every session issue is In Review or Done, or goes back to Backlog and the status update says
  why. In Review may carry over to the next session (it waits on a merge).
- If the goal doesn't fit, split it and close the finished part.

## Ticket flow (`/ship-ticket DRA-NN`)

Standard way to implement a Linear ticket; defined in `.claude/skills/ship-ticket/SKILL.md`.

- Branch `feature|bugfix|…/dra-NN-short-title`, from updated `main`, or from the blocking ticket's
  branch while its PR is still open (stacked; the PR targets that branch until it merges).
- Always work in a worktree (`../guixa-worktrees/dra-NN`); the main checkout stays on `main` for testing.
  Remove the worktree once CI is green (or, if CI never gets green, once the work is escalated to the user).
- `ticket-implementer` agent (Sonnet): test-first, linters, full suite, one single-line commit.
- `ticket-reviewer` (Spec and Standards) and `ticket-correctness-reviewer` (bugs and security) agents
  (Opus, read-only), in parallel: independent review on a different model from the implementer. Up to two correction passes: the second only if the first leaves confirmed findings, and its re-review covers just those findings and the new diff; what is still open after it is escalated, not deferred.
- Once the work is validated (local checks green, review questions answered, correction passes applied), the
  orchestrator pushes and opens the PR without asking; otherwise it asks once.
- Green CI is a convention gate (branch protection is unavailable on a private repo on the free plan): after
  the PR is opened the orchestrator waits for CI (`gh pr checks <PR> --watch`, after checks exist for the
  pushed commit). The PR is **ready to merge** (validated and CI green) only when every check passes ("validated" is evaluated once, before the push; after a CI-fix pass, green CI replaces the local full-suite requirement); it is
  never merged while any check is red or pending, and merging still needs the user to ask. Red CI goes to a
  `ticket-implementer` launch; repeated failures are an escalation condition (below). A PR that touches only paths the workflow ignores (the `paths-ignore` entries in
  `.github/workflows/ci.yml`) reports no checks: for such PRs only, and after the wait for checks to
  appear, "no checks reported" counts as passing. Any other PR with no checks is not ready to merge. Local equivalents of each CI step:
  `docs/principles.md`.
- Findings outside the ticket text. Protected areas: already merged migrations, `specs/`, ADRs, `CLAUDE.md`,
  linter or CI config, and changes to the behaviour of other features' shared code (compatible extensions
  with tests are not protected); a file the ticket already changes is never protected. Needed for the
  ticket and not protected: **fix it**; needed but protected: **escalate**; not needed: **fix it** if in a
  file the ticket already changes, else **follow-up**, however small. Escalation conditions: (1) CI keeps
  failing after a CI-fix pass (the orchestrator counts: same job and error again, cause not found, or 2
  CI-fix passes; the initial red CI does not count); (2) a product question the spec does not resolve;
  (3) a needed change in a protected area; (4) a fix that would need weakening a check. For 1, 3 and 4 the
  orchestrator creates one Triage issue per finding (deduplicated); for 2 it asks the user first and
  creates an issue only if the user leaves it open or rules it out of scope. Full rule:
  `.claude/agents/ticket-implementer.md`.
- Review findings are fixed in the correction pass by default; open product questions are asked in the
  session. Only a pre-existing problem outside the files the ticket changes, a question ruled out of scope, or an
  implementer-reported follow-up (findings rule above) becomes a follow-up: a Linear issue (state Triage, related to the parent ticket) linked from the PR body.
- No PR or ticket keeps follow-ups as plain text, also for work done outside `/ship-ticket`: each one is a
  `DRA-NN` issue, created before the PR is opened (this includes follow-ups under the findings rule above).
- Linear: In Progress when starting, In Review when the PR is opened.
- Before review and again before pushing, the branch merges `origin/<base>` so conflicts show up in the
  session, not in the PR.
- Cross-cutting tickets (sweeps over many files) ship alone: no other PRs open while theirs is open.
- Migrations keep the real timestamp from `make:migration`; never hand-number them.
- Agents never post project status updates; the orchestrator posts the session's one at the end.
- Never push to `main`, never force-push, never merge PRs without being asked.
- Hooks in `.claude/hooks/` back these rules (push to `main` and force push, edits to migrations already on
  `main`, lint + tests when `ticket-implementer` stops); the `deny` rules in `.claude/settings.json` remain a
  second layer and are what stops `gh pr merge`. Test the hooks with `bash .claude/hooks/tests/run.sh`.

## Language

- Conversation with the user: **Catalan**.
- Everything generated (docs, specs, ADRs, tickets, code, comments, commit messages, file names): **English**.

## Agent skills

### Issue tracker

Specs in the repo (`specs/NNN-name/spec.md`); tickets in Linear (team Draude, project Guixa). See `docs/agents/issue-tracker.md`.
Before the first Linear call of a session, check with `get_workspace` that the connected workspace is
`Draude`; if not, stop and tell the user.

### Triage labels

Labels needs-info, ready-for-agent, ready-for-human, wontfix, created in Linear. "Needs triage" is the Linear Triage state, not a label. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: `CONTEXT.md` + `docs/adr/` at the repo root. See `docs/agents/domain.md`.
