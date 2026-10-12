---
name: ship-batch
description: Deliver one confirmed group of up to four related tickets in a fresh provider-configured orchestrator, one branch and PR, with isolated tests and bounded independent review.
argument-hint: "<manifest path>"
disable-model-invocation: true
---

Use `docs/agents/batch-execution.md` and ADR 0012. This is the agent workflow for a single batch;
`scripts/run-batches.sh` supplies the local execution/scheduling boundary. A manifest must explicitly
select a resolved project and an owner-reviewed provider adapter/verifier and model roles. Stop
visibly when they or their permissions are missing. Never select a provider implicitly or introduce
permission bypasses. Existing provider-specific denies/hooks remain intact.

## Prepare

1. The interactive session confirms grouping before launch. Group related/chained tickets on one
   branch and PR, at most four tickets, one implementation commit per ticket. Independent batches
   run in parallel (default three). A cross-cutting sweep is a batch alone and waits until no PR is
   open anywhere in the shared repository; no new batch starts while its PR remains open.
2. Use the worktree, branch, base, parent refs, project and database from the request. Never switch
   the primary checkout or reuse another batch's branch or database. Provision dependencies and a
   safe `.env`; every test command uses `testing_dra<FIRST>` or its reviewer/parallel suffix, never
   `guixa` or `testing`. Each worktree needs its own CodeGraph index if code discovery needs it.
3. Resolve full tickets with relations/spec/acceptance criteria, validate their project, and move
   them to In Progress. Delivered parent batches need verified open PRs with passing CI before
   stacking. The PR targets the request's base branch; `start_ref` and additional `parents` are verified head
   SHAs, not mutable branch refs.

## Implement and review

1. For each ticket in dependency order launch a **fresh** implementer using the configured role.
   Give it the complete ticket and both reviewer checklists. Implement test-first at agreed seams,
   run meaningful project checks and commit once for that ticket. Never implement in the orchestrator.
2. After all deliverable tickets, verify any base update before synchronizing; never replace the
   pinned parent heads with unchecked mutable refs. Launch two independent fresh reviewers
   once, in parallel, on the **combined batch diff** and all deliverable tickets: spec/standards and
   correctness/security. Use configured model roles and read-only review permissions.
3. Apply confirmed findings in at most two correction passes. Re-review only previous findings and
   the new diff. Remaining findings become deduplicated Triage escalations and stop delivery; do not
   weaken a check or defer defects in changed code. Follow-ups become issues before the PR opens.
4. In headless execution an unanswered product question immediately becomes one deduplicated Triage
   issue in the parent's project, related to the ticket, with `cause: product-question` and inherited
   milestone only when present. Record its identifier and move the ticket to Backlog with a reason.
   Stop its dependents; independent tickets in the batch may proceed. Never invent a product answer.
   Other escalation conditions follow `ship-ticket`; retain work and record required reconciliation.

## Deliver

Push the validated batch branch and open one PR covering its delivered tickets and follow-up issue
links. Wait for current-head CI, with the same bounded CI-fix/escalation rules as `ship-ticket`.
Emit the strict result file only after verifying local checks, both reviews, ticket commits, project,
branch, open PR, expected base and CI. Partial delivery excludes blocked tickets from the PR scope.
The separate verifier establishes external readiness; a result alone never proves Git/permission/CI
safety. Missing result or nonzero process exit is a visible failure, never success by inference.

The runner retains every worktree. The coordinator checks receipts, Linear, Git status and current
PR/CI, records unresolved questions and cleans only authorized state after preserving local work.
Only the interactive session posts its selected project's status update. Human PR merge is required;
never push to main, force-push or merge automatically.
