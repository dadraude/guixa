# Guixa feature acceptance

Use this after the final planned slice is integrated, and after any later scope change that invalidates
the prior verdict. Read the current feature spec and area files, accepted plan, canonical tasks,
combined history, integrated code and tests, and current tracker/PR state. Establish the reviewed
`origin/main` commit, document revisions, reviewer and review date first. Refresh prior slice
evidence against this integrated commit; retain a previous result only when its inputs and
contract assumptions still match. Record pending CI, spec-sync PRs and document ownership
as remainders rather than treating them as integrated proof. Exclude retired or superseded scope, especially
002 dashboard content; retain only independently accepted obligations identified in the current
specs. An open PR or green task CI is not integrated behavior.

Dispatch a fresh independent read-only Reviewer through the selected adapter and profile model
routing with a bounded handoff: reviewed source identities, spec/plan/task scope, slice records,
integrated code/test paths and unresolved decisions. The reviewer checks the current integrated
feature against the matrix below, returns evidence and gaps, and makes no tracker, PR or document
mutations. The coordinator reconciles the verdict and records it in history.

Build one acceptance matrix with every in-scope `R#` and `S#`: the responsible slice(s), code path,
scenario-level test and observed result, relevant review/CI evidence, and any remainder with an owner.
Recheck cross-slice journeys and contracts through the integrated application, including permissions,
state transitions and shared data; add focused checks where prior slice evidence cannot establish the
combined result. Record failures and unresolved OQs without inventing acceptance.

Return a feature verdict: `accepted by user` only when the user has explicitly accepted this same
concrete scope and evidence. Use `ready for user review` only when every in-scope `R#`/`S#` row has
evidence at the integrated commit, required current checks and reviews are satisfied, and no blocking
OQ or remainder remains. Missing scenario evidence is `not ready`; an external decision or action
that prevents verification is `blocked`, with its owner. Include source identity, matrix and exact
remaining decisions/actions. A new review can preserve a prior
explicit acceptance only when the reviewed scope and evidence still match. Tracker parent closure,
task PR publication, CI and this agent review do not themselves record user product acceptance.
Keep the verdict and matrix in a durable, versioned `Change` history entry using
`node .agent-workflow/scripts/history.mjs add NNN-feature Change`, with exact paths reported.
Do not merge PRs or move product requirements as a side effect of this review.
