---
name: implement-session
description: 'Coordinate compatible Guixa implementation tasks.'
disable-model-invocation: true
---

# Guixa implement-session

Explicit invocation only. Load the [Claude Code adapter](../../../skills/guixa-workflow/adapters/claude-code.md),
[project profile](../../../skills/guixa-workflow/project.md), then the
[shared process](../../../skills/guixa-workflow/core/session.md), in that order.
Follow the shared process; this entrypoint adds no publication, ticket-migration or permission authority.
The coordinator loads these sources once; dispatch workers with the shared process's role-scoped
handoff, not this full loading chain. Missing capabilities stop the relevant action; keep review independent.
