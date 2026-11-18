# Claude Code adapter

Entrypoints: `/implement-session`, `/implement-task <issue>`, `/to-tickets` in `.claude/skills/`.
Their `disable-model-invocation: true` preserves explicit-only invocation. The wrappers load this
adapter, the [project profile](../../../.agent-workflow/profile.md) and the shared process, not a separate Claude workflow.

## Capabilities and dispatch

Inspect available tools and permissions before mutation. Use the exposed `Agent` tool (or its documented
`Task` alias on older hosts) for delegation; preserve returned agent/task IDs. Wait through the host's
completion mechanism and verify a terminal result before a dependent step; partial output is not success.
For fixes, use the host's supported resume mechanism; `SendMessage` is usable only when actually exposed
and applicable to that worker. Do not assume `ToolSearch`, team messaging or background tools exist.

Dispatch each role as the profile's project agent for its tier (`subagent_type`), whose definition fixes
model and effort; the Strong agent's `disallowedTools` enforces read-only review for Edit/Write, while its
brief covers shell writes. Without a tier agent, pass the role's model from the profile's Model routing
on every `Agent` call; an omitted `model` inherits the coordinator's and spends a higher tier. Reviewer B
is the Strong agent with the profile's alternate review model passed as `model`. Host/user policy wins; if a requested model is
unavailable, report it and use only an explicitly permitted fallback. Do not silently weaken independent
review or invent a model. No nested delegation, even when the host would allow it. Any skill that delegates, including the
spec and ticket skills outside the task pipeline, follows this model rule.
Cap parallelism by both the project limit and live host capacity.

Use [role-scoped handoffs](../core/handoff.md) with explicit relevant paths/sections and resolved settings,
not the whole profile, adapter or transcript. Keep briefs self-contained and use only documented
context controls exposed by the host; do not assume a Codex-style fork option exists. Reuse returned
agent/task IDs with the supported resume mechanism for related internal work, preserving the assigned
implementation owner. Internal subtasks use these workers, never new user-owned chats. A resumed writer
is not an independent reviewer. Missing required delegation/reviewer capability stops the affected
implementation before mutation; ticket drafting alone does not require delegation.

Use `Bash`, `Read` and `Edit` only if exposed, with explicit worktree cwd/paths and the configured
environment. Respect permission denials, native approvals and the profile's standing authorization; never
change permission modes, reuse ambient credentials or invoke another tool to bypass a refusal.
A prompt nobody answers is a denial: an unattended run needs the profile's permission rules merged into
the main checkout's ignored local settings, with the worktree root under `additionalDirectories`, by
the user in an attended run. The deny rules stop an accident, not an adversary.

CI observation: use one background `gh pr checks <number> --watch` per PR, started only once
`gh pr checks <number>` lists a check (it exits early while the workflow registers). Do not busy-poll.

## Tracker and questions

Use the connected tools for the profile's tracker after verifying the profile's identities and current schemas.
Map semantic parent/blocking/related operations to the supported native fields; preserve existing
relations and read back results. Missing native relation support stops that operation, never substitutes
plain text links or a different workspace/session. Tool discovery is conditional on host capabilities.

Ask one focused question with the available question tool or chat and wait for the answer. Preserve
native review's candidate-specific consent and exact continuations where applicable; task-level review
does not replace it.
