# Codex adapter

Entrypoints: `.agents/skills/<prefix>-implement-session`, `<prefix>-implement-task`, `<prefix>-to-tickets`,
with the profile's Codex prefix. Their `agents/openai.yaml` preserves explicit-only invocation. The wrappers load this adapter,
the [project profile](../../../.agent-workflow/profile.md) and the requested shared process; do not copy process rules here.

## Capabilities and dispatch

Inspect the active tool schemas, runtime limits and permission policy before mutation. Use the exposed
collaboration surface: `spawn_agent` for workers, `wait_agent` plus terminal-status confirmation for
completion, `send_message` for a running worker and `followup_task` for an idle worker's next fix.
Do not infer completion from a notification or timeout. Where names differ, use the documented exposed
equivalent; if no equivalent exists, report that capability unavailable, not a successful handoff.

Map shared roles to read-only reader, bounded implementer, check runner, fresh read-only reviewer,
preparation worker and publisher. Spawn each worker as the project agent
`.codex/agents/agent-workflow-<tier>.toml` for its tier in the profile's Model routing; the agent file's
`model` and `model_reasoning_effort` take precedence, while a generic spawn inherits the session's. If the
spawn surface cannot select that agent, report the role unavailable rather than spend a higher tier.
Never translate another provider's model name into a guessed model ID. Use only the host's allowed
model/reasoning options and thread budget. No nested delegation.

Use [role-scoped handoffs](../core/handoff.md), supplying relevant source paths/sections and resolved
configuration rather than the full profile, adapter or inherited chat/MCP access. Prefer
`fork_turns="none"` with a self-contained brief to keep unrelated conversation out of worker context.
Retain worker IDs and resume/message the same suitable worker for related internal work; preserve the
assigned implementation owner. Use collaboration workers for subtasks, never user-owned chats.
A reviewer must be a fresh independent worker, not the writer with a different label. If required
delegation is unavailable, stop the affected implementation before mutation. A read-only ticket draft does not require a subagent merely to compensate for that absence.

Use shell/file tools with explicit cwd/absolute paths. Honor sandbox refusals and the host approval
mechanism; never weaken permissions or reroute through another tool to bypass a denial. Runtime approval
covers the host; the profile's standing authorization covers destinations and operations.

## Tracker and questions

Discover the connected tools for the profile's tracker and read their current schemas. Verify the
profile's tracker identities before writes. Map shared relation semantics to the native parent,
blocking and related fields the profile names.
Read existing relations before updates; preserve them and verify results. No tool/field support means
stop that operation, not substitute a text-only relation or a different workspace.

Ask one focused question through the available input surface or chat, then stop and wait. Preserve
approval boundaries, including any native review's exact candidate consent and continuation. The
shared process cannot grant itself consent or replace required native review with its task reviewer.
