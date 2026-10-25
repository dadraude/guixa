---
name: implement-task
description: "Implement one admitted task with independent review."
disable-model-invocation: true
---

# implement-task

Load the [Claude Code adapter](../../../skills/agent-workflow/adapters/claude-code.md),
[project profile](../../../.agent-workflow/profile.md), then the
[shared process](../../../skills/agent-workflow/core/task.md), in that order.
Follow the shared process within the profile's standing authorization. The coordinator loads these
sources once and dispatches workers with the shared process's role-scoped handoff.
