# Claude Code adapter

Entrypoints remain `/implement-session`, `/implement-task DRA-NN`, `/to-tickets`.
Their `disable-model-invocation: true` preserves explicit-only invocation. The wrappers load this
adapter, the [project profile](../project.md) and the shared process, not a separate Claude workflow.

## Capabilities and dispatch

Inspect available tools and permissions before mutation. Use the exposed `Agent` tool (or its documented
`Task` alias on older hosts) for delegation; preserve returned agent/task IDs. Wait through the host's
completion mechanism and verify a terminal result before a dependent step; partial output is not success.
For fixes, use the host's supported resume mechanism; `SendMessage` is usable only when actually exposed
and applicable to that worker. Do not assume `ToolSearch`, team messaging or background tools exist.

Preserve the existing role defaults when available: haiku for discovery/status/checks, sonnet for
implementation/preparation/publication, opus for a fresh read-only review. Host/user policy wins; if a
requested model is unavailable, report it and use only an explicitly permitted fallback. Do not silently
weaken independent review or invent a model. No nested delegation, even when the host would allow it.
Cap parallelism by both the project limit and live host capacity.

Use [role-scoped handoffs](../core/handoff.md) with explicit relevant paths/sections and resolved settings,
not the whole profile, adapter or transcript. A resumed writer
is not an independent reviewer. Missing required delegation/reviewer capability stops the affected
implementation before mutation; ticket drafting alone does not require delegation.

Use `Bash`, `Read` and `Edit` only if exposed, with explicit worktree cwd/paths and the configured
environment. Respect permission denials, native approvals and remote authorization separately; never
change permission modes, reuse ambient credentials or invoke another tool to bypass a refusal.

## Tracker and questions

Use the authorized connected Linear tools after verifying the profile's identities and current schemas.
Map semantic parent/blocking/related operations to the supported native fields; preserve existing
relations and read back results. Missing native relation support stops that operation, never substitutes
plain text links or a different workspace/session. Tool discovery is conditional on host capabilities.

Ask one focused question with the available question tool or chat and wait for the answer. Preserve
native review's candidate-specific consent and exact continuations where applicable; task-level review
does not replace it. Do not turn publishing approval into merge or force-push approval.
