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
Pass these paths and the task's input to delegated workers. Missing required capabilities stop the
relevant action; never silently replace independent review with self-review.
