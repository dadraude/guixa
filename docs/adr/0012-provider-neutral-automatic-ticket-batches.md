# 0012 — Provider-neutral automatic ticket batches

- Status: accepted
- Date: 2026-10-12

A work session selects one explicit project, confirms related ticket groups and sends each group to
one fresh external AI orchestrator process. Related/chained tickets share a branch and PR, at most
four tickets per batch and one implementation commit per ticket. Independent batches run in parallel,
three by default. Dependencies wait for verified open parent PRs and stack on their branches. A
cross-cutting sweep runs alone across the whole repository and blocks new work until its PR merges.

The local Python stdlib runner owns manifest validation, DAG readiness, worktree/database isolation,
bounded subprocesses, advisory locks and recoverable state. Configured executable adapters own the
agent workflow: fresh per-ticket implementers, two independent combined-diff reviewers once, no more
than two correction passes, project-scoped issue handling, checks and PR delivery. Model roles and
literal argv are configuration, with no provider default or permission bypass. Any AI service may
implement this file protocol; no service is invoked merely by installing the scripts.

An unanswered headless product question becomes a deduplicated Triage issue in its parent's project
with `cause: product-question`; the ticket returns to Backlog, its dependents stop and independent
remainder may proceed. Only the interactive session publishes the project status update. PR merge
remains a human decision.

## Trust and consequences

Results are claims, not proof. A separately configured, trusted verifier checks repository-wide PR
exclusion and exact head/base/CI delivery before dependent execution. Cooperative locks coordinate
runner invocations only; humans and outside tools can still change remote state. The wrapper's actual
permissions and Git/check enforcement require owner review (DRA-135); prompt text cannot enforce
those constraints. Missing wrappers, permission grants or receipts fail visibly and preserve work.

Tests use fake local services and temporary Git repositories. They establish deterministic protocol,
isolation, readiness and cancellation behavior, without claiming supervised live-provider validation.
A real provider adapter/verifier, its permission enforcement, tracker writes/deduplication, current CI
and an end-to-end supervised service run remain integration acceptance gates. Application tests are
not the runner's seam; focused stdlib tests and existing hook tests are run in dedicated CI.
