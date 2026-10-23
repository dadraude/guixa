---
name: to-tickets
description: 'Draft Guixa ticket hierarchies, dependencies and contextual relations.'
disable-model-invocation: true
---

# Guixa to-tickets

Explicit invocation only. Load the [Claude Code adapter](../../../skills/guixa-workflow/adapters/claude-code.md),
[project profile](../../../skills/guixa-workflow/project.md), then the
[shared process](../../../skills/guixa-workflow/core/tickets.md), in that order.
Follow the shared process; this entrypoint adds no publication, ticket-migration or permission authority.
The coordinator loads these sources once; dispatch workers with the shared process's role-scoped
handoff, not this full loading chain. Missing capabilities stop the relevant action; keep review independent.
