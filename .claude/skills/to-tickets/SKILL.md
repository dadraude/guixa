---
name: to-tickets
description: 'Draft Guixa ticket hierarchies, dependencies and contextual relations.'
disable-model-invocation: true
---

# Guixa to-tickets

Load the [Claude Code adapter](../../../skills/guixa-workflow/adapters/claude-code.md),
[project profile](../../../skills/guixa-workflow/project.md), then the
[shared process](../../../skills/guixa-workflow/core/tickets.md), in that order.
Follow the shared process within the profile's standing authorization. The coordinator loads these
sources once and dispatches workers with the shared process's role-scoped handoff.
