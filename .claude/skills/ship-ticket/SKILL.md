---
name: ship-ticket
description: Standard Guixa flow to take one Linear ticket (DRA-NN) from Backlog to a PR in review. Use when the user types /ship-ticket DRA-NN or asks to implement a ticket.
disable-model-invocation: true
---

Orchestrate the ticket in the argument. Only the main session can launch agents, so you run this flow;
the agents do the heavy work and you keep a short thread. Talk to the user in Catalan.

## 1. Prepare

1. Check with `get_workspace` that the Linear workspace is `Draude` (stop otherwise). Read the ticket
   with Linear (`get_issue`, including relations); keep its project ID and optional milestone for
   follow-ups, escalations and the session update. If it has no project, ask before starting. Inside
   `/run-session`, it must belong to the selected project; otherwise stop for a scope decision.
   If a blocker is not Done, tell the user
   which one and which PR holds it.
2. Branch name: `feature|bugfix|improvement|refactor|chore/dra-NN-short-kebab-title`.
   - Blockers merged, or none: create it from an updated `main`.
   - A blocker still has an open PR: create it from that blocker's branch (stacked). The PR will target
     that branch; mention it in the PR body and retarget to `main` once the blocker merges.
   - Never branch from a sibling that is not a blocker.
   - Always work in a worktree, never switch branches in the main checkout: it stays on `main` so the
     user can test there. `git worktree add ../guixa-worktrees/dra-NN -b <branch> <base>`, then in it
     copy `.env` from the main checkout, `composer install` and `npm ci`. Every later step (agents,
     merges, checks, push) runs inside that worktree.
   - In the worktree, `php` must be 8.5: Herd's isolation only covers the main checkout, and the global
     `php` is 8.4. Put a `php` symlink to Herd's `php85` first on `PATH` before `composer install`
     (the Vite Wayfinder plugin also calls `php`), and tell the agents to keep it there.
   - With tickets running in parallel, give each worktree its own test database: tell the agents that
     EVERY test command carries `DB_DATABASE=testing_draNN`. Filtered runs while doing TDD stay serial
     (the first serial run also creates `testing_draNN`, which `--parallel` needs to exist); the full
     suite adds `--parallel`: `DB_DATABASE=testing_draNN php artisan test --parallel`. In local runs,
     never add `--processes` or another explicit worker-count limit: use the runner's default
     parallelism. Parallel workers
     use their own databases, `testing_draNN_test_N`, so they collide neither with the main checkout
     nor with other worktrees. Any other database the ticket needs (e.g. a reviewer running tests) is
     named `testing_draNN_<suffix>`, so the cleanup in step 4 finds it. Tests never touch `guixa`
     (the user's database) nor `testing` (the main checkout's): never pass either as `DB_DATABASE`.
3. **Cross-cutting tickets ship alone.** If the ticket touches many files outside its own feature
   (formatter or linter sweeps, strict types, mass renames, moving folders), check `gh pr list` across
   the whole repository, including PRs from other Linear projects: with
   other PRs open, tell the user and ask whether to wait until they merge. Once its PR is open, do not
   open new PRs until it merges.
4. Move the issue to **In Progress**.

## 2. Implement

Every `ticket-implementer` launch in this flow passes `model: "sonnet"`; never implement inline in the
main session or with the plugin's `/implement`.

Launch the `ticket-implementer` agent with the full ticket (id, title, description, acceptance
criteria), the branch and the worktree path. Tell it to read the two reviewer agents,
`.claude/agents/ticket-reviewer.md` and `.claude/agents/ticket-correctness-reviewer.md`, before it starts
and to self-check its work against their checklists (spec coverage and standards; bugs and security), so
the reviewers do not find what it could have caught. Wait for its report.

If it stopped on an undecided edge case or any product question, ask the user (one question at a time),
record each answer in the ticket as an acceptance criterion, and launch it again with the updated ticket;
open a Triage issue only if the user leaves it open or rules it out of scope.

If it stopped on any other escalation condition (see Guardrails), create ONE Triage issue per finding
(in the ticket's project, related to the ticket, with its milestone only when present), after checking the issues already related to
this ticket to avoid duplicates, and tell the user. Then either relaunch it with the decision, or stop
the ticket cleanly: issue back to Backlog, worktree removed, test databases dropped, nothing dangling.

If it could not commit for any other reason, stop and tell the user.

Then sync with the base: `git fetch origin` and `git merge origin/<base>` (merge, never rebase: the
branch may be pushed and force-push is forbidden). If it conflicts, do not resolve it yourself: launch a
`ticket-implementer` pass that resolves it with the `resolving-merge-conflicts` skill, runs the full
checks and commits the merge. Otherwise, if the base brought new commits, run a `ticket-implementer` pass
that only runs the full checks (step 5 of its flow) and fixes what breaks.

## 3. Independent review

Launch both reviewers in parallel (one message, two agents), each with `model: "opus"`, the full ticket (as given to the implementer) and
the base ref (`main`, or the blocker's branch). If they run tests, each prefixes them with its own
`DB_DATABASE=testing_draNN_review` or `DB_DATABASE=testing_draNN_correctness`:
- `ticket-reviewer`: spec coverage and standards.
- `ticket-correctness-reviewer`: bugs and security.

Each reviewer marks every finding `fix now`, `question` or `follow-up` (see the agents). The default is
to fix it now: a correction pass is cheaper than an issue, its triage and another session.

1. **Questions** (a product decision the ticket and spec leave open): ask the user, one at a time, and
   record each answer in the ticket as an acceptance criterion. If the user rules it out of the
   ticket's scope, it becomes a follow-up.
2. **Correction pass**: if there is any `fix now` finding or answered question, relay all of them from
   both reviewers to one new `ticket-implementer` pass, whatever the verdicts. A finding a tool could
   check is fixed by adding the check (arch test, Larastan or Pint rule), not by a one-off edit. Drop
   anything not worth a line of code and say so.
   **Second pass**: there are at most two correction passes. After the first, if any `fix now` finding was
   dropped or fixed wrongly, or the diff may have introduced new problems, relaunch both reviewers
   (`model: "opus"`) with only the first pass's findings and the diff of the correction commit
   (`git diff <commit before the pass>..HEAD`), not the whole ticket again. If they confirm real
   `fix now` findings, relay them to a second `ticket-implementer` pass, and do not review again after it
   beyond checking that each of those findings is fixed (you may ask a reviewer for that). Whatever is
   still confirmed after the second pass is not deferred: create one Triage issue per finding as in
   section 2 (deduplicated, related to the ticket), tell the user, and the work is not validated (section 4).
3. **Follow-ups**, only for (a) a problem that already existed before this ticket and sits outside the
   files it changes (a reviewer `follow-up` inside a file the ticket changes is treated as `fix now`), (b) a question the user ruled out of scope (the ticket's "Out of scope (decided)" section only holds exclusions decided in the spec before the tickets were created; what a user rules out during `/ship-ticket` stays a follow-up), or (c) a follow-up the implementer
   reported under the decision rule (Guardrails). One Linear issue each in the parent ticket's
   project, state Triage, related to the parent ticket and with its milestone only when present, titled with the
   change to make; body: file:line, the finding and the suggested fix. Add exactly one `cause` label
   (see below) to every follow-up or escalation issue you create. Skip any finding already filed as
   an escalation or follow-up issue for this ticket (check the issues related to it first). Create them in
   step 4, before opening the PR. Never fix anything in the main session.

**Cause labels** (Linear group `cause`, counted in the session status update): (a) →
`pre-existing`; (b) and escalation condition 2 → `product-question`; (c) → `spec-gap` if the spec left
the case undecided, `ticket-too-big` if the work did not fit the ticket, `other` otherwise; findings
still confirmed after the second correction pass → `review-finding`; escalation conditions 1, 3 and 4 →
`other`.

## 4. Deliver

The work is **validated** when all of these hold:
- the last `ticket-implementer` pass committed and reports the full suite and linters green;
- every reviewer `question` was answered by the user (or ruled out of scope into a follow-up);
- the correction passes (at most two), if any, applied every `fix now` finding (dropped ones are only
  those not worth a line of code);
- no open doubt is left from the implementer or the reviewers, and no escalation is unresolved.

"Validated" is evaluated once, before the push; CI is checked after it. Validated: deliver without asking. Not validated: show the user what is missing and ask once whether to
push and open the PR anyway.

To deliver: create the follow-up issues, fetch again and, if the base moved since the sync, repeat the
step 2 sync before pushing. Then push with `-u`, `gh pr create --base <base>` with a conventional
title `type(scope): description (DRA-NN)` and a short body that links each follow-up issue and ends with
`🤖 Generated with [Claude Code](https://claude.com/claude-code)`, then move the issue to **In Review**.

**CI gate.** Green CI is a convention, not a GitHub rule (no branch protection on a private repo on the
free plan, see `docs/principles.md`). The PR is **ready to merge** only when it is validated AND CI is
green; never report it as ready, nor merge it, while any check is red or pending. After a CI-fix pass the
local full-suite requirement no longer applies (that pass only runs the failing step's local equivalents):
green CI replaces it, so the PR is ready to merge once CI is green again.
1. Wait until checks exist for the pushed SHA: poll
   `gh run list --branch <branch> --commit <sha> --json databaseId --jq length` every ~10 s, up to ~2 min.
   `gh pr checks <PR> --watch` does not wait for checks that are not registered yet: it exits at once with
   "no checks reported" and exit code 1, the same code as a red check.
2. Then `gh pr checks <PR> --watch`. Exit 1 means either "no checks" or "red": read the output to tell which.
3. A PR that touches only paths the workflow ignores (the `paths-ignore` entries in
   `.github/workflows/ci.yml`) reports no checks: for such PRs only, and only after the wait in step 1,
   "no checks reported" counts as passing. Any other PR with no checks reported is not ready: tell the user.

Red CI: get the failing run and job with
`gh pr checks <PR> --json name,bucket,link --jq '.[] | select(.bucket=="fail")'` (the link carries the run
and job id), then read the log with `gh run view <run-id> --log-failed` (or `--job <job-id>`; without an id
the command is interactive). Launch a `ticket-implementer` pass in the same worktree with the failing job
and its log; it fixes, runs the local equivalents of the failing step (see "CI steps and local
equivalents" in `docs/principles.md`) and makes one more commit `fix(scope): <description> (DRA-NN)`,
without amending or rewriting anything already pushed. Push, repeat step 1 (the query may still show the
previous commit's results, so match the new SHA) and step 2.
- The CI-fix pass must never disable or weaken a check: no skipped or deleted tests, no PHPStan baseline
  entries, no files excluded from Pint, no `test.skip` in Playwright, no looser linter config. If the fix
  would need that, it is escalation condition (4) in Guardrails.
- Escalation (condition 1 in Guardrails): you count, not the implementer. The initial red CI is not a
  failure after a fix. Pass the previous failure's log to each next CI-fix pass. Do not launch another
  pass, escalate (below) and tell the user instead of looping, when the CI run after a CI-fix
  pass fails again in the same job with the same error, or the implementer reports it could not find the
  cause, or 2 CI-fix passes have already run even if every failure was diagnosable. Escalating with the
  PR open: create the Triage issue as in section 2, leave the PR open (convert it to draft if needed)
  with a comment linking that issue, keep the ticket In Review and blocked by that issue (blocking
  relation), and still remove the worktree and drop the test databases as below.

Once CI is green, remove the worktree
(`git worktree remove ../guixa-worktrees/dra-NN`) and drop its test databases from the main checkout:
`php .claude/skills/ship-ticket/drop-test-databases.php NN` (it only matches `testing_draNN` and
`testing_draNN_*`). The branch stays. If CI never gets green (escalated, red, or no checks when expected),
do the same anyway and say so in the summary. If the PR needs more work later, add a new worktree for it;
its first serial test run recreates the database.

Then show the user a short summary: the PR link, what was built, CI result (ready to merge or not), the CI-fix commit(s) with their diff (nobody re-reviews them, and a file list would not reveal a weakened check), test/lint results (as reported, not
re-verified unless asked), both review verdicts, what the correction pass(es) fixed, what was dropped and the
follow-ups.

## PR fix (feedback on an open PR)

For an In Review issue whose open PR has red checks, changes requested or unanswered review comments
(found in the `run-session` open phase, or when the user asks). It is a correction on the existing
branch, not a new ticket; the PR stays the same.

1. Move the issue to **In Progress**. Read the feedback: `gh pr view <PR> --json reviews,comments` for
   review text and `gh pr checks <PR>` for red checks (logs as in "Red CI" in section 4).
2. New worktree on the existing branch (the previous one was removed): `git fetch origin`,
   `git worktree add ../guixa-worktrees/dra-NN <branch>`, then the setup in section 1 step 2 (`.env`,
   `php` 8.5 on `PATH`, `composer install`, `npm ci`, its own `testing_draNN` database). Merge
   `origin/<base>` as in section 2.
3. Launch a `ticket-implementer` (`model: "sonnet"`; the main session never writes code) with the
   ticket, the feedback to address and the branch. It follows the same decision rule and escalation
   conditions (Guardrails) and makes one more commit `fix(scope): <description> (DRA-NN)`, never
   amending or rewriting what is pushed. A review comment raising a product question the spec does not
   resolve is escalation condition 2: ask the user before changing behaviour.
4. Re-review with the two Opus reviewers (section 3, only the diff of the fix commit) only when the fix
   is non-trivial: it changes behaviour or logic, or touches more than the lines the feedback names. A
   CI-only or wording fix does not need it; say so in the summary.
5. Push (never force), answer each review comment on the PR with what changed, then follow the CI gate
   of section 4 (including the CI-fix limits and the worktree and database cleanup). Move the issue back
   to **In Review**.

Never skip or disable a test to get green. Never merge; never push to `main`.

## 5. Session status

Agents never post project status updates. At the end of a standalone ticket session, post one status
update in the ticket's project yourself. Under `/run-session`, its Close phase posts the single update
in the selected project (see `docs/agents/issue-tracker.md`).

## Guardrails

- Findings outside the ticket text. Protected areas: already merged migrations, `specs/`, ADRs, `CLAUDE.md`,
  linter or CI config, and changes to the behaviour of other features' shared code (extending shared code
  compatibly, with tests, is not protected); a file the ticket already changes is never protected. In order:
  needed for the ticket (it touches or breaks it, or it needs it to work, even in another file) and not in
  a protected area: **fix it**; needed but in a protected area: **escalate**; not needed: **fix it** if it
  is in a file the ticket already changes, otherwise **follow-up**, however small (it never meets the
  protected-area escalation). Escalation conditions: (1) CI keeps failing after a CI-fix pass (the
  orchestrator counts: same job with the same error again, the implementer could not find the cause, or 2
  CI-fix passes in total; the initial red CI does not count); (2) a product question the spec does not
  resolve; (3) a needed change in a protected area; (4) a fix that would need disabling or weakening a
  check. The implementer stops and reports (no Linear access). For 1, 3 and 4 the orchestrator creates one
  Triage issue per finding (deduplicated), related to the ticket, and either relaunches it with the
  decision or stops the ticket; for 2 it asks the user first and creates an issue only if the user leaves
  it open or rules it out of scope. Full rule: `.claude/agents/ticket-implementer.md`.
  Reviewer follow-ups (section 3) are the same kind of issue and obey the guardrail below.
- Never open a PR whose body or ticket lists follow-ups as plain text: each one must be a linked `DRA-NN`.
- Never push to `main` or force-push. Never merge PRs unless the user asks.
- If a permission is denied, stop and tell the user; do not look for another route.
