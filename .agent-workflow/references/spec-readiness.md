# Guixa spec readiness

Diagnoses whether one scope (a roadmap row or an `NNN-feature`) can be planned, and stops. It never plans,
creates tasks or tickets, estimates, resolves a gap, answers an open question or approves a decision for the
user. The next step, a plan, is a separate request through the [spec flow](spec-flow.md).

## Boundaries

- **Read:** `specs/`, `docs/`, `app/`, `src/`, `tests/`, the code graph and non-destructive checks.
- **Write:** only `.scratch/<scope-slug>/readiness.md` (ignored), in a worktree per
  [AGENTS.md](../../AGENTS.md#worktrees). No other file, branch, commit, push, PR or tracker change.
- **Never:** edit a spec, plan, ADR, code or test; record a decision as approved; assign IDs, estimates or
  order of execution; run anything with an external effect.
- Silence is not approval. A boundary still being defined is **provisional** unless an ADR, an accepted
  `R#` or a Q&A entry confirms it, and a provisional boundary is not a gap by itself.

Delegate the reading and the gap walk to workers ([spec-flow delegation](spec-flow.md#delegation)); this
session keeps the verdicts and the report.

## Procedure

1. **Scope.** Resolve the argument to roadmap rows and features. Without one, ask which; with an inferred
   scope, say so in the report. Name what is out of scope: retired or superseded scope stays out unless a
   new Request reopened it.
2. **Resume.** If the report exists, keep its confirmed decisions, re-verify only conclusions whose sources
   changed (compare the Git reference it records) and list what changed and on what evidence. Read the
   feature's combined history (`node .agent-workflow/scripts/history.mjs read NNN-feature`) and the local
   changes that touch the scope.
3. **Domain model.** Test the scope's use cases against `specs/constitution.md` (hexagonal boundaries,
   single tenant), `docs/architecture/modules.md`, `docs/adr/` and the glossary in `spec.md`. Look for
   ambiguous or duplicated vocabulary, responsibilities with two owners or none, who may change which data,
   invariants and where they are enforced, states and transitions, and contracts between modules. For each
   doubtful boundary give the evidence, the use case that tests it, what needs clarifying before planning
   and what may stay provisional. Describe alternatives with consequences; never choose between them.
4. **Spec.** Walk the [gap families](spec-gaps.md) over the scope instead of restating them, and report the
   coverage table. Add contradictions between `spec.md`, area files, scenarios and examples, and separate a
   necessary omission from an optional extension of scope.
5. **Plan against the repository.** Where `plan.md` or `tasks.md` exist, run
   `node .agent-workflow/scripts/validate-feature.mjs NNN-feature plan` and
   `node .agent-workflow/scripts/validate-plan.mjs NNN-feature`, then check that referenced files,
   components and contracts exist or are marked new, that persistence, errors and permissions are covered,
   and that existing tests give the guarantees the documents assume. Report divergence between current and
   desired behavior without preferring either. A technical probe is stated as an open question; do not run
   or schedule it.
6. **Design.** For rendered scope, check the declared pages, roles and states against `docs/ux-discovery/`,
   `docs/visual-design/` and `docs/visual-checks/`, and whether a design approval the spec requires exists.
   Report which parts are affected and which are independent as diagnosis only, never as a breakdown.
7. **Traceability.** Where the documents allow it, link `R#` → `S#` → plan section → task `Tnn` → test →
   repository evidence and mark each missing link. Missing code or tests for future behavior is not a gap;
   unclear behavior or unclear verification is.
8. **Classify and write** the report below, citing fully qualified references (`R12`, `S58`, `OQ3`,
   `ADR-0002`) and relative links.

Never ask what the documents or the repository can answer. If an ambiguity blocks a dependent part, ask one
concrete question and keep working the independent parts.

## Report

Distinguish what was run, what was inspected in code and what is only proposed as a check. Do not call the
review complete if a relevant part could not be examined.

Classify each finding as **BLOCKS PLANNING** (an essential decision is missing or a contradiction prevents a
coherent plan; scope it to the affected part), **PROVISIONAL** (may stay open on an explicit hypothesis, with
consequences and a review condition), **OPTIONAL OBSERVATION** or **NOT VERIFIABLE**. Each finding gives the
part affected, evidence and location, the concrete consequence, the missing decision and what would settle
it. Present findings as a diagnosis, never as a task list or execution order.

Verdict per roadmap row or feature and, for a partial block, per functional part: `READY TO PLAN`, `READY TO
PLAN WITH EXPLICIT HYPOTHESES`, `NOT READY TO PLAN` or `NOT ASSESSABLE WITH THE AVAILABLE EVIDENCE`. Never
write "ready to implement".

Sections: 1 scope, date, sources and Git reference; 2 verdicts; 3 spec and domain coherence (with the gap
coverage table); 4 plan against the repository; 5 design availability; 6 traceability; 7 gaps,
contradictions and pending decisions; 8 provisional hypotheses and review conditions; 9 limits.

## Stop

Done when the report is saved, even if everything is ready. Reply with the report link, the verdict per
scope, the most important gaps and pending decisions, and the review's limits. Do not start planning, fixing
gaps, creating tasks or implementing; each needs its own request.
