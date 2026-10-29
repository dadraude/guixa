# Specs

Spec-driven development, by hand. Every feature goes through four documents, in order;
no code is written before its spec is accepted.

1. `constitution.md` — project-wide principles, stack and architecture. Changes rarely.
2. `NNN-feature/spec.md` — what and why: glossary, requirements (`R#`), acceptance scenarios
   (Given/When/Then), open questions. No technology.
   A long spec is split into one file per area (`ordering.md`, `pickup.md`…) plus `open-questions.md`;
   `spec.md` keeps the glossary, roles and an index of the areas. `R#` and `S#` stay global.
3. `NNN-feature/plan.md` — how: domain model, use cases, ports/adapters, persistence, UI routes.
4. `NNN-feature/tasks.md` — the canonical registry of ordered, small tasks, each linked to the `R#`
   and scenarios it covers. Large task sets may put detail in `tasks/<area>.md` and historical evidence
   in `tasks/baseline.md`; keep one global Tnn sequence and all task rows/dependencies in `tasks.md`.
   Tasks are tracer bullets wherever possible: the first task is the thinnest slice that runs end to
   end through every layer it needs (page, use case, domain, persistence, tests) and stops for review;
   each later task is another vertical slice that reuses and widens that path. A layer-only task
   (only the schema, only the tokens) is used only when no vertical slice can be delivered, and its
   row says why. Apply the shared [task-quality and slicing rules](../skills/agent-workflow/references/planning.md#task-quality-invest)
   to every new task breakdown.

Alongside them, `NNN-feature/log.md` keeps the why. It is append-only: entries are never edited or
removed, so the reasoning survives after the spec is rewritten. Each entry is dated and is one of:

- **Request** — the original ask, verbatim. The first entry of every feature.
- **Q&A** — a question asked while writing a document and the answer given, verbatim.
- **Change** — what changed in which document (`R#`, `S#`, plan section, task) and why.
- **Deviation** — something found while coding that the spec or plan did not anticipate, and how it was resolved.

Rules:

- Each acceptance scenario becomes at least one test. A task is done when its scenarios pass.
- A requirement change edits the spec first, then the plan and tasks it affects, and logs a **Change**.
- A task is not done until any deviation found while coding is written back to the spec or plan it
  affects and logged as a **Deviation**. Code never silently disagrees with its documents.
- Open questions block only the requirements that depend on them. When resolved, the answer is logged as a **Q&A**.
- Before a spec is accepted, walk the gap families in `.claude/skills/spec-gaps/SKILL.md`.

## Spec flow or direct fix?

- **Direct fix** (test + code, no document changes): the code breaks an existing `R#` or scenario, or
  the change is one no requirement or scenario describes (refactor, tooling, a copy typo). Write a
  failing test that reproduces it first.
- **Spec flow** (spec → plan → tasks): new behaviour, or a change to what an existing `R#` or
  scenario says. A "bug" where the spec is wrong or silent is a spec change.
- **Plan only** (plan → tasks, logged as a **Change**): the behaviour stays the same but the design
  changes — a new port, a different persistence model, a moved use case.

When in doubt, ask: "would a reader of `spec.md` notice?" If yes, use the spec flow.

Agents follow these rules through `.claude/skills/spec-flow/SKILL.md`.

## Research

[UX discovery (2026-10-24)](../docs/ux-discovery/2026-10-24-guixa-discovery.md): personas, jobs, journeys, design
principles and success metrics for the whole product. Before starting a spec, check its requirements against the
design principles and the gap analysis there.

## Features

| ID  | Feature                         | Status |
| --- | ------------------------------- | ------ |
| 001 | [Weekly orders](001-weekly-orders/spec.md) | Spec + plan amended and accepted (2026-10-28): products, Sales baskets and Finance · [split task breakdown accepted](001-weekly-orders/tasks.md) · implementation status in Linear (Coop) |
| 002 | [Actionable dashboard](002-actionable-dashboard/spec.md) | **Retired (2026-10-29)** · T01–T04 and DRA-201–DRA-204 canceled; historical request and discovery retained · no dashboard-content work authorized. A new spec needs completion of 001 and a new user decision. The independently shipped login destination (002 T05/R11) and 001 T61 basic dashboard summary remain in force. |

## Roadmap

Features are built one slice at a time: only the next one gets a full spec, and it starts once the
previous slice is merged. The rest live here as one line each, so a slice does not close off an option a
later one needs. Before starting a spec, review this table and check its *Builds on* terms against the
current glossary.

An entry gets an `NNN` ID and moves to *Features* when its spec starts. The order below is not a priority.

| Feature | For | Builds on |
| --- | --- | --- |
| Cooperative fee | A percentage added to the price of every product a household takes, charged at pickup. | Product, Order line, Pickup, Wallet |
| Work shifts | A manager assigns households to tasks such as reception and pickup, per cycle; members see theirs. | Household, Cycle, Reception, Pickup |
| Notifications | Members and producers are told about events other than a sent order (R12a). | Account, Cycle, Producer order |
| Producer confirmation | A producer accepts or adjusts a sent order before delivering. | Producer order, Sent, Reception |
| Minimum order per producer | A producer order below its minimum is not sent. | Producer order, Cutoff, Participating producer |
| Producer invoicing and payments | The cooperative records what it owes each producer and pays it. | Producer, Reception |
| Container returns to producers | Containers taken back at pickup are tracked until the cooperative hands them back to their producer. | Container, Return item, Producer |
| Admin rename | Manager becomes Admin (Administrador/a) in specs, code and screens, with no change in behaviour. Comes before Commissions. | Manager, Account |
| Commissions | An Admin groups accounts into commissions and gives each permissions by area, such as producers and their products. [Decisions](../docs/commissions/2026-10-25-commission-decisions.md). | Account, Producer, Product, Admin |
