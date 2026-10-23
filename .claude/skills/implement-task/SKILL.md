---
name: implement-task
description: "Implement one admitted Guixa task with independent review."
disable-model-invocation: true
---

# Guixa implement-task

Explicit invocation only. Load the [Claude Code adapter](../../../skills/guixa-workflow/adapters/claude-code.md),
[project profile](../../../skills/guixa-workflow/project.md), then the
[shared process](../../../skills/guixa-workflow/core/task.md), in that order.
Follow the shared process; this entrypoint adds no publication, ticket-migration or permission authority.
Pass these paths and the task's input to delegated workers. Missing required capabilities stop the
relevant action; never silently replace independent review with self-review.
