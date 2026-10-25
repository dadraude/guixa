---
name: implement-task
description: "Implement one admitted Guixa task with independent review."
disable-model-invocation: true
---

# Guixa implement-task

Load the [Claude Code adapter](../../../skills/guixa-workflow/adapters/claude-code.md),
[project profile](../../../skills/guixa-workflow/project.md), then the
[shared process](../../../skills/guixa-workflow/core/task.md), in that order.
Follow the shared process within the profile's standing authorization. The coordinator loads these
sources once and dispatches workers with the shared process's role-scoped handoff.
