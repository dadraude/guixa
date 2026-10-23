# Create ticket batches

## Activation Contract

Use the selected adapter and [project profile](../project.md) for future ticket batches. Auditing or drafting does not authorize publishing. Reorganizing
existing tickets is a separate request; do not migrate them as a side effect of creating new work.

## Hard Rules

- Target the workspace/team/project in the profile through the authorized connector. Verify returned
  identities/URLs before any write. If unavailable or mismatched, stop; do not
  switch to another workspace, credential or session.
- Read the source spec/plan or issue and its relevant context. Keep the spec authoritative; link its
  task row instead of duplicating a long mutable specification. Preserve existing `Tnn` identifiers.
- Make executable slices independently verifiable, with their code, tests and docs together. Do not
  split one behavior into separate layer-only tickets. For a cross-cutting mechanical refactor, use
  expand, migrate, contract with real prerequisite edges rather than pretending each layer stands alone.
- Use native `parentId`, `blockedBy`/`blocks` and `relatedTo`, not only mentions in descriptions.
- Never use blocking links to encode temporary shared-file contention. The implementation scheduler
  handles that separately. A leaf with no blockers is dependency-ready, not automatically parallel-safe.
- Do not change team status automations, close parents or reparent existing work without authorization.

## Decision Gates

| Relationship      | When to propose it                                                                                                                                                          |
| ----------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Parent → children | A coherent deliverable needs multiple executable slices. Reuse an explicitly selected source issue as parent, or propose a new grouping parent for spec/conversation input. |
| Blocking          | One slice genuinely requires another's result before it can proceed. Keep these edges between executable tasks, with no cycles.                                             |
| Related           | A decision, follow-up or adjacent task provides useful context without being a prerequisite. Do not relate everything just because it shares a parent.                      |
| No parent         | A standalone slice, or an approved project/milestone already supplies sufficient grouping. Do not create redundant containers.                                              |

Grouping parents describe the outcome and completion criteria, have no executable `Tnn` prefix and
receive no `ready-for-agent` label. Each executable child maps to a task row and uses `Tnn — Title`.
If that mapping is missing, report it before publishing agent-ready work; do not invent task numbers.
Parent status does not gate its children. Do not make a child blocked by its own grouping parent.

## Execution Steps

1. Inspect the current project, matching issues and relationships to avoid duplicates. Draft vertical
   slices from the authorized requirements, using the project's domain vocabulary.
2. Present one proposed breakdown: title, parent (or none), deliverable, acceptance criteria, source
   task link, blockers and contextual relations with a short reason. Include any proposed new parent.
   Ask one approval question and wait. Do not silently turn existing issues into children.
3. On approval to publish, create approved new parents first, then leaves in dependency order; use
   returned IDs. Create contextual links after both endpoints exist. Apply `ready-for-agent` only to
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
- [Scheduling](scheduling.md) handles edit surfaces independently of Linear relations.
