---
name: guixa-to-tickets
description: 'Draft Guixa ticket hierarchies, dependencies and contextual relations.'
---

# Guixa to-tickets

Load the [Codex adapter](../../../skills/guixa-workflow/adapters/codex.md),
[project profile](../../../skills/guixa-workflow/project.md), then the
[shared process](../../../skills/guixa-workflow/core/tickets.md), in that order.
Follow the shared process within the profile's standing authorization. The coordinator loads these
sources once and dispatches workers with the shared process's role-scoped handoff.
