# Codex adapter

Entrypoints: `$guixa-implement-session`, `$guixa-implement-task DRA-NN`, `$guixa-to-tickets`.
Their `agents/openai.yaml` preserves explicit-only invocation. The wrappers load this adapter,
the [project profile](../project.md) and the requested shared process; do not copy process rules here.

## Capabilities and dispatch

Inspect the active tool schemas, runtime limits and permission policy before mutation. Use the exposed
collaboration surface: `spawn_agent` for workers, `wait_agent` plus terminal-status confirmation for
completion, `send_message` for a running worker and `followup_task` for an idle worker's next fix.
Do not infer completion from a notification or timeout. Where names differ, use the documented exposed
equivalent; if no equivalent exists, report that capability unavailable, not a successful handoff.

Map shared roles to read-only reader, bounded implementer, check runner, fresh read-only reviewer,
preparation worker and publisher. Inherit the session's model unless an applicable user/project policy
selects an available model. Never translate another provider's model name into a guessed model ID.
Use only the host's allowed model/reasoning options and thread budget. No nested delegation.

Use [role-scoped handoffs](../core/handoff.md), supplying relevant source paths/sections and resolved
configuration rather than the full profile, adapter or inherited chat/MCP access. A reviewer must be a fresh independent worker, not the writer
with a different label. If required delegation is unavailable, stop the affected implementation before
mutation. A read-only ticket draft does not require a subagent merely to compensate for that absence.

Use shell/file tools with explicit cwd/absolute paths. Honor sandbox refusals and the host approval
mechanism; never weaken permissions or reroute through another tool to bypass a denial. Runtime approval
does not replace the user's authorization for a destination, operation and connected session.

## Tracker and questions

Discover connected Linear tools and read their current schemas. Verify the profile's workspace URL,
team and project before writes. Map shared relation semantics to native parent, blocking and related
fields (currently `parentId`, `blockedBy`/`blocks`, `relatedTo` on `save_issue` where exposed).
Read existing relations before updates; preserve them and verify results. No tool/field support means
stop that operation, not substitute a text-only relation or a different workspace.

Ask one focused question through the available input surface or chat, then stop and wait. Preserve
approval boundaries, including any native review's exact candidate consent and continuation. The
shared process cannot grant itself consent or replace required native review with its task reviewer.
