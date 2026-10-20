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
| 001 | [Weekly orders](001-weekly-orders/spec.md) | Spec revised (OQ1–OQ18 resolved), reopened for OQ19 · plan + tasks pending update |
