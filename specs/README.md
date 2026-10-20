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

## Features

| ID  | Feature                         | Status |
| --- | ------------------------------- | ------ |
| 001 | [Weekly orders](001-weekly-orders/spec.md) | Spec revised (OQ1–OQ19 resolved) · plan + tasks updated, drafts pending review |
