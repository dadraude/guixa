---
name: agent-workflow-implement-task
description: 'Implement one admitted task with independent review.'
---

# implement-task

Load the [Codex adapter](../../../skills/agent-workflow/adapters/codex.md),
[project profile](../../../.agent-workflow/profile.md), then the
[shared process](../../../skills/agent-workflow/core/task.md), in that order.
Follow the shared process within the profile's standing authorization. The coordinator loads these
sources once and dispatches workers with the shared process's role-scoped handoff.
