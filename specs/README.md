# Specs

Spec-driven development, by hand. Discovery precedes the feature's spec, plan and tasks; the
constitution remains project-wide. No feature code is written before its selected delivery slice's
spec is accepted.

1. `constitution.md` — project-wide principles, stack and architecture. Changes rarely.
2. `NNN-feature/discovery.md` — the original outcome, users, non-goals, shared constraints, candidate
   delivery slices and their dependency reasons. A new `NNN` ID, directory, discovery-only Features
   row and immutable Request history entry are created when discovery starts. Record answered questions
   as Q&A entries. Discovery is a scope proposal, not approval to build or change existing obligations.
3. `NNN-feature/spec.md` — what and why for the **selected delivery slice**, retaining earlier
   accepted scope: glossary, requirements (`R#`), acceptance scenarios (Given/When/Then), open
   questions. No technology.
   A long spec is split into one file per area (`ordering.md`, `pickup.md`…) plus `open-questions.md`;
   `spec.md` keeps the glossary, roles and an index of the areas. `R#` and `S#` stay global.
4. `NNN-feature/plan.md` — how for accepted scope, retaining earlier accepted design: domain model,
   use cases, ports/adapters, persistence, UI routes.
5. `NNN-feature/tasks.md` — the cumulative canonical registry of ordered, small tasks, each linked
   to the `R#` and scenarios it covers. Large task sets may put detail in `tasks/<area>.md` and historical evidence
   in `tasks/baseline.md`; keep one global Tnn sequence and all task rows/dependencies in `tasks.md`.
   Tasks are tracer bullets wherever possible: the first task is the thinnest increment that runs end to
   end through every layer it needs (page, use case, domain, persistence, tests) and stops for review;
   each later task is another vertical increment that reuses and widens that path. A layer-only task
   (only the schema, only the tokens) is used only when no vertical slice can be delivered, and its
   row says why. Apply the shared [task-quality and slicing rules](../skills/agent-workflow/references/planning.md#task-quality-invest)
   to every new task breakdown.

A **delivery slice** produces an independently useful, observable outcome. It may need several
implementation tasks; a task is a bounded change within the selected slice, not another roadmap
feature. Several outcomes with separate users or decisions, or uncertain boundaries between them,
trigger discovery before a full spec. Task count alone does not. Select the first slice for value and
the uncertainty it can resolve. Keep later slices as a short roadmap in `discovery.md` until their
turn; add their detailed spec, plan and tasks only after the preceding slice's integrated acceptance
is reviewed. Retain earlier accepted requirements and task rows in the canonical documents, with
global `R#`, `S#` and `Tnn` identifiers across slices. An indivisible business invariant can justify
a larger slice: record why a smaller independently useful boundary is unsafe, and use bounded
implementation checkpoints. Existing accepted obligations remain owned until
explicitly changed; discovery cannot silently defer them.

## History

Alongside the documents, `NNN-feature/history/` keeps the why: **one new Markdown file per entry**,
committed with the change. Entries are immutable once committed; corrections and superseding decisions
get a new entry linking the original. Never append to or rewrite another entry, maintain a shared
index, or reserve a sequential filename. Each entry is dated and is one of:

- **Request** — the original ask, verbatim. The first entry of every feature, created at discovery.
- **Q&A** — a question asked while writing a document and the answer given, verbatim.
- **Change** — what changed in which document (`R#`, `S#`, plan section, task) and why.
- **Deviation** — something found while coding that the spec or plan did not anticipate, and how it was resolved.

Existing `NNN-feature/log.md` files remain frozen, byte-for-byte, as legacy archives. Do not split,
move or delete them: existing links keep working. A feature's complete history is its legacy archive
(if present) **plus every file in `history/`**. New features start with a Request file in `history/`;
do not create a new `log.md`. If neither source contains the original Request, ask for it verbatim.

From the task worktree, create an entry from a prepared body file (Node only; no app/database setup):

```sh
node .agent-workflow/scripts/history.mjs add 001-weekly-orders Change DRA-123 < /tmp/history-body.md
```

Use `Request`, `Q&A`, `Change` or `Deviation` as the kind (quote `"Q&A"` in shell). Omit the issue
argument for work without an issue. The helper prints the new path and creates it exclusively with a
UTC timestamp, issue key (or `no-task`) and UUID; parallel agents never share a target filename.
The heading records the timestamp and kind, followed by the issue when supplied and the body verbatim.
Include affected `R#`, `S#`, task/document sections, what changed and why, and decision/source links.
Requests and Q&A retain the user's wording verbatim. Commit the printed file, not the temporary body.
Creating an entry does not grant permission to change product requirements.

Consult history before changing a feature or interpreting an earlier decision:

```sh
# Complete history of one feature: frozen archive first, then new files sorted by filename.
node .agent-workflow/scripts/history.mjs read 001-weekly-orders
# Complete repository history, grouped by feature in directory-name order.
node .agent-workflow/scripts/history.mjs read
# Locate relevant decisions in both formats, then read the matching entries and their references.
rg -n --glob 'log.md' --glob '**/history/*.md' 'R20|T25|DRA-123' specs/001-weekly-orders
```

Reader output includes source paths and never writes a combined file. Within `history/`, UTC filenames sort by creation time; UUIDs break ties, not
causal order. The legacy archive retains its original order, without attempting to interleave dates.
A correction must link the entry it supersedes. Search results alone do not establish the current
rule: follow those links and check the accepted spec/plan. Read the full combined history when asked
for all history or when context is unclear. No history is stored only in Linear.

The reader sees the current worktree, including uncommitted entries; it cannot see unintegrated peer
branches or future commits. Refresh from `origin/main` through the normal integration workflow before
claiming the history is current, and disclose any pending task branches that matter.

Rules:

- Each acceptance scenario becomes at least one test. A task is done when its allocated scenarios
  pass and its slice acceptance record maps requirements, task, test evidence, verdict and remainder.
- A requirement change edits the spec first, then the plan and tasks it affects, and logs a **Change**.
- A task is not done until any deviation found while coding is written back to the spec or plan it
  affects and logged as a **Deviation**. Code never silently disagrees with its documents.
- When a task PR makes `spec.md` or an area file stale, the agent opens a stacked spec-sync PR on the
  task branch with the edits and a **Change** entry; merging it accepts the change.
- Open questions block only the requirements that depend on them. When resolved, the answer is logged as a **Q&A**.
- Before a spec is accepted, walk the gap families in `.agent-workflow/references/spec-gaps.md`; record
the G1–G14 review with source identity in a new immutable Change history entry.

## Spec flow or direct fix?

- **Direct fix** (test + code, no document changes): the code breaks an existing `R#` or scenario, or
  the change is one no requirement or scenario describes (refactor, tooling, a copy typo). Write a
  failing test that reproduces it first.
- **Spec flow** (discovery/scope when needed → spec → plan → tasks): new behaviour, or a change to
  what an existing `R#` or scenario says. A "bug" where the spec is wrong or silent is a spec change.
- **Plan only** (plan → tasks, logged as a **Change**): the behaviour stays the same but the design
  changes — a new port, a different persistence model, a moved use case.

When in doubt, ask: "would a reader of `spec.md` notice?" If yes, use the spec flow.

Agents follow these rules through the Guixa spec flow: `.claude/skills/spec-flow/SKILL.md`
or `.agents/skills/agent-workflow-spec-flow/SKILL.md`. The shared guidance lives in
`.agent-workflow/references/spec-flow.md` and gap families in
`.agent-workflow/references/spec-gaps.md`.

After each selected delivery slice integrates, review its acceptance and shared-invariant regression
before selecting the next slice. After the final in-scope slice integrates, run a fresh
[feature acceptance review](../.agent-workflow/references/feature-acceptance.md)
against the integrated base. Record its evidence and verdict separately from task completion or
grouping-parent tracker status. Only the user accepts the feature; this review does not merge PRs.

## Research

[UX discovery (2026-10-24)](../docs/ux-discovery/2026-10-24-guixa-discovery.md): personas, jobs, journeys, design
principles and success metrics for the whole product. Before starting a spec, check its requirements against the
design principles and the gap analysis there.

## Features

| ID  | Feature                         | Status |
| --- | ------------------------------- | ------ |
| 001 | [Weekly orders](001-weekly-orders/spec.md) | Spec + plan amended and accepted (2026-10-28): products, Sales baskets and Finance · [split task breakdown accepted](001-weekly-orders/tasks.md) · implementation status in Linear (Coop) |
| 002 | [Actionable dashboard](002-actionable-dashboard/spec.md) | **Retired (2026-10-29)** · T01–T04 and DRA-201–DRA-204 canceled; historical request and discovery retained · no dashboard-content work authorized. A new spec needs completion of 001 and a new user decision. The independently shipped login destination (002 T05/R11) and 001 T61 basic dashboard summary remain in force. |
| 003 | [Role management](003-role-management/spec.md) | Spec accepted (2026-11-08), OQ1–OQ6 resolved: Catalogue and Receiver roles, Finance runs receptions, role management screen · [plan](003-role-management/plan.md) accepted (2026-11-08) · [tasks](003-role-management/tasks.md) accepted (2026-11-08) · supersedes Commissions |
| 004 | [Dashboard](004-dashboard/spec.md) | First-slice spec accepted (Q22, 2026-11-11): unified attention and opportunities. [Discovery](004-dashboard/discovery.md) keeps later insight slices; [technical plan](004-dashboard/plan.md) accepted (Q23, 2026-11-11); [task breakdown](004-dashboard/tasks.md) accepted (Q24, 2026-11-11); [Linear tickets](004-dashboard/linear-publication.md) published: DRA-256 parent, DRA-257–DRA-263 children. |
| 005 | [Landing identity rollout](005-landing-identity-rollout/spec.md) | Slice 1 spec accepted (2026-11-13, PR #416): Catalogue role complete in the landing identity. [Discovery](005-landing-identity-rollout/discovery.md) keeps slices 2–7 as a roadmap; technical plan, tasks and tickets not yet. |

## Roadmap

Features are built one delivery slice at a time. A roadmap entry gets an `NNN` ID and a discovery-only
Features row when discovery starts. Only its selected slice gets a full spec, plan and task breakdown;
later slices remain lightweight in `discovery.md`. Before discovery or a subsequent slice, review this
table and check its *Builds on* terms against the current glossary. The order below is not a priority.

| Feature | For | Builds on |
| --- | --- | --- |
| Cooperative fee | A percentage added to the price of every product a household takes, charged at pickup. | Product, Order line, Pickup, Wallet |
| Work shifts | A manager assigns households to tasks such as reception and pickup, per cycle; members see theirs. | Household, Cycle, Reception, Pickup |
| Notifications | Members and producers are told about events other than a sent order (R12a). | Account, Cycle, Producer order |
| Producer confirmation | A producer accepts or adjusts a sent order before delivering. | Producer order, Sent, Reception |
| Minimum order per producer | A producer order below its minimum is not sent. | Producer order, Cutoff, Participating producer |
| Producer invoicing and payments | The cooperative records what it owes each producer and pays it. | Producer, Reception |
| Monthly amount payable per producer | At month end, a report of what the cooperative must pay each producer for the products supplied. Basis undecided: validated reception or distributed goods. | Producer, Reception, Basket |
| Container returns to producers | Containers taken back at pickup are tracked until the cooperative hands them back to their producer. | Container, Return item, Producer |
| Admin rename | Manager becomes Admin (Administrador/a) in specs, code and screens, with no change in behaviour. Comes before Commissions. | Manager, Account |
| ~~Commissions~~ | **Superseded by [003](003-role-management/spec.md) (2026-11-08):** fixed staff roles instead of configurable commissions. [Decisions](../docs/commissions/2026-10-25-commission-decisions.md) kept for history. | Account, Producer, Product, Admin |
