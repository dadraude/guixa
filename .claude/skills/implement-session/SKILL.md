---
name: implement-session
description: 'Coordinate compatible Guixa implementation tasks.'
disable-model-invocation: true
---

# Guixa implement-session

Load the [Claude Code adapter](../../../skills/guixa-workflow/adapters/claude-code.md),
[project profile](../../../skills/guixa-workflow/project.md), then the
[shared process](../../../skills/guixa-workflow/core/session.md), in that order.
Follow the shared process within the profile's standing authorization. The coordinator loads these
sources once and dispatches workers with the shared process's role-scoped handoff.
