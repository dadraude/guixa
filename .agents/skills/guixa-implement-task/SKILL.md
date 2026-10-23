---
name: guixa-implement-task
description: 'Implement one admitted Guixa task with independent review.'
---

# Guixa implement-task

Explicit invocation only. Load the [Codex adapter](../../../skills/guixa-workflow/adapters/codex.md),
[project profile](../../../skills/guixa-workflow/project.md), then the
[shared process](../../../skills/guixa-workflow/core/task.md), in that order.
Follow the shared process; this entrypoint adds no publication, ticket-migration or permission authority.
The coordinator loads these sources once; dispatch workers with the shared process's role-scoped
handoff, not this full loading chain. Missing capabilities stop the relevant action; keep review independent.
