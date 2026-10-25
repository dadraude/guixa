---
name: agent-workflow-to-tickets
description: 'Draft ticket hierarchies, dependencies and contextual relations.'
---

# to-tickets

Load the [Codex adapter](../../../skills/agent-workflow/adapters/codex.md),
[project profile](../../../.agent-workflow/profile.md), then the
[shared process](../../../skills/agent-workflow/core/tickets.md), in that order.
Follow the shared process within the profile's standing authorization. The coordinator loads these
sources once and dispatches workers with the shared process's role-scoped handoff.
