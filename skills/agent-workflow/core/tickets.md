# Create ticket batches

## Activation Contract

Use the selected adapter and [project profile](../../../.agent-workflow/profile.md) for future ticket batches. Auditing or drafting does not authorize publishing. Reorganizing
existing tickets is a separate request; do not migrate them as a side effect of creating new work.
If a reader is needed, use [role-scoped context](handoff.md); ticket drafting does not load worker
execution/setup instructions or authorize implementation.

## Hard Rules

- Target the workspace/team/project in the profile through the authorized connector. Verify returned
  identities/URLs before any write. If unavailable or mismatched, stop; do not
  switch to another workspace, credential or session.
- Read the source spec/plan or issue and its relevant context. Keep the spec authoritative; link its
  task row instead of duplicating a long mutable specification. Preserve existing task IDs.
- Make executable slices independently verifiable, with their code, tests and docs together. Do not
  split one behavior into separate layer-only tickets. For a cross-cutting mechanical refactor, use
  expand, migrate, contract with real prerequisite edges rather than pretending each layer stands alone.
- Shape a batch as tracer bullets whenever it can be: the first executable slice is the thinnest one
  that runs end to end through every layer it needs and gives the user something to review; later
  slices reuse and widen that path and are blocked by it. If no end-to-end first slice is possible,
  say why in the proposed breakdown.
- Use the tracker's native parent, blocking and related fields (named in the profile), not only
  mentions in descriptions.
- Never use blocking links to encode temporary shared-file contention. The implementation scheduler
  handles that separately. A leaf with no blockers is dependency-ready, not automatically parallel-safe.
- Do not change team status automations, close parents or reparent existing work without authorization.

## Decision Gates

| Relationship      | When to propose it                                                                                                                                                                                                                      |
| ----------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Parent → children | One deliverable (feature, bug or task) decomposes into executable slices that roll up into it and share its context. Reuse an explicitly selected source issue as parent, or propose a new grouping parent for spec/conversation input. |
| Blocking          | Separate items with a sequential dependency: B cannot start or finish until A is done, often across features, workflows or teams, each with its own owner and cycle. Keep these edges between executable tasks, with no cycles.         |
| Related           | A decision, follow-up or adjacent task provides useful context without being a prerequisite. Do not relate everything just because it shares a parent.                                                                                  |
| No parent         | A standalone slice, or an approved project/milestone already supplies sufficient grouping. Do not create redundant containers.                                                                                                          |

Apply the profile's tracker constraints (parent cardinality, inherited fields, automations) to these gates.

Grouping parents describe the outcome and completion criteria, have no executable task ID and receive
no ready label. Each executable child maps to a task row and uses the profile's executable title format.
If that mapping is missing, report it before publishing agent-ready work; do not invent task numbers.
Parent status does not gate its children. Do not make a child blocked by its own grouping parent.

## Execution Steps

1. Inspect the current project, matching issues and relationships to avoid duplicates. Draft vertical
   slices from the authorized requirements, using the project's domain vocabulary, with the tracer
   slice first.
2. Present one proposed breakdown: title, parent (or none), deliverable, acceptance criteria, source
   task link, blockers and contextual relations with a short reason. Include any proposed new parent.
   Ask one approval question and wait. Do not silently turn existing issues into children.
3. On approval to publish, create approved new parents first, then leaves in dependency order; use
   returned IDs. Create contextual links after both endpoints exist. Apply the profile's ready label only to
   executable mapped leaves and only if that label exists or its creation was explicitly approved.
4. Preserve existing relationships on touched issues. Read current values before updates; do not
   assume array updates append. Read back parent IDs and all requested relations after publishing.
   Check no dependency/parent cycles, self-links, wrong-project issues or duplicate tickets were added.
5. If a create/link call partially succeeds or has an unknown outcome, stop further writes and report
   confirmed IDs plus pending operations. Do not blindly recreate or retry; reconcile exact issue
   identities before any separately authorized recovery. Do not delete successful tickets to roll back.

## Output Contract

Return created issue links, grouping parents, each executable task's parent/blockers/related links, and
any pending or failed operation. In draft-only mode return the proposed graph, not a success claim.
Existing ticket reorganization remains untouched unless separately requested.

## References

- [Implementation discovery](session.md) excludes grouping parents.
- [Scheduling](scheduling.md) handles edit surfaces independently of tracker relations.
