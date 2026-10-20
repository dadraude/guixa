# Specs

Spec-driven development, by hand. Every feature goes through four documents, in order;
no code is written before its spec is accepted.

1. `constitution.md` — project-wide principles, stack and architecture. Changes rarely.
2. `NNN-feature/spec.md` — what and why: glossary, requirements (`R#`), acceptance scenarios
   (Given/When/Then), open questions. No technology.
   A long spec is split into one file per area (`ordering.md`, `pickup.md`…) plus `open-questions.md`;
   `spec.md` keeps the glossary, roles and an index of the areas. `R#` and `S#` stay global.
3. `NNN-feature/plan.md` — how: domain model, use cases, ports/adapters, persistence, UI routes.
4. `NNN-feature/tasks.md` — ordered, small tasks, each linked to the `R#` and scenarios it covers.

Rules:

- Each acceptance scenario becomes at least one test. A task is done when its scenarios pass.
- A requirement change edits the spec first, then the plan and tasks it affects.
- Open questions block only the requirements that depend on them.
- Before a spec is accepted, walk the gap families in `.claude/skills/spec-gaps/SKILL.md`.

## Features

| ID  | Feature                         | Status |
| --- | ------------------------------- | ------ |
| 001 | [Weekly orders](001-weekly-orders/spec.md) | Spec revised (OQ1–OQ19 resolved) · plan + tasks updated, drafts pending review |

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
| Stock sales outside a pickup | Households buy stock products on days other than a pickup. | Stock product, Wallet |
| Notifications | Members and producers are told about events other than a sent order (R12a). | Account, Cycle, Producer order |
| Producer confirmation | A producer accepts or adjusts a sent order before delivering. | Producer order, Sent, Reception |
| Minimum order per producer | A producer order below its minimum is not sent. | Producer order, Cutoff, Participating producer |
| Producer invoicing and payments | The cooperative records what it owes each producer and pays it. | Producer, Reception |
| Container returns to producers | Containers taken back at pickup are tracked until the cooperative hands them back to their producer. | Container, Return item, Producer |
