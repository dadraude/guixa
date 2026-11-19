# Task tiers, context packs and reviewers

Every admitted task gets a tier S, M or L, recorded with a `tier` event in the implementation-owner
record ([state helper](../scripts/state.mjs)). The tier sets context depth and checkpoint reporting; the
model of each role still comes from the profile's Model routing.

## Classify

Read signals by section, never whole documents: the task detail's outcome, linked requirements and
scenarios, reserved files/surfaces, dependencies, stated size and the count of its done-when rows.

- **L** if any: stated large; a new table or migration with data backfill; concurrent writers, locking or
  an atomic multi-record transaction; authentication, authorization or tenancy rules; a shared contract
  change with two or more consumers; or a profile L signal.
- **S** if all: stated small; no shared contract change; at most 5 done-when rows; no profile L signal.
- **M** otherwise.

More than 10 done-when rows raises S to M or M to L. Missing or conflicting signals pick the higher tier.
Record the tier and a one-line reason.

## Reviewer B condition

Record `reviewer_b: required | not_required` with the reason at admission. Require B for changes to
authentication, authorization or tenancy; credentials or cryptography; untrusted input or external
exposure; destructive data operations; shared public contracts with several consumers; or architecture
boundaries and shared conventions; never for size alone. Reevaluate before review: Reviewer A or new scope
evidence may add B later. Required B is never omitted for capacity or model availability.

Reviewer A owns Spec, Red-proof, Test economy, Correctness and Integration; B owns Standards,
Responsibility and Security. Without B, A covers every area. Both inspect the same fixed candidate
independently, neither reads the other's conclusions before returning its own, and their findings merge
into one substantive round. B runs on the profile's alternate review model, distinct from A's.

## Context packs

The coordinator writes the task handoff with the sections in [role-scoped context](handoff.md#task-handoff-file).
Each worker reads its `## Read` list and nothing else unless a concrete gap needs it.

- **S:** the task row and detail, the contracts it consumes (paths and public signatures) and only the
  conventions it touches; no design or architecture documents unless the task links them.
- **M:** S plus the linked document sections (heading anchors, never whole files) and the consumer
  inventory of changed contracts.
- **L:** M plus a context map built by a Light discovery worker before the implementer starts (each linked
  section as `file:line-range`, each reserved file with its relevant symbols, existing tests to extend,
  hotspot owners) and a `progress.md` journal next to the handoff, kept by the implementer at every
  checkpoint so it can resume after context compaction.

Reviewer packs: **S** fixed diff, done-when rows, conventions; **M** S plus quoted contracts and the
implementer's report; **L** M plus the context map and the riskiest invariant's evidence. The reviewer
count follows the B condition, not the tier; integration coverage always stays with A.

## Coordinator context

Keep coordinator context to decisions, identities, state files and digests. Run deterministic steps
inline when their output is compact (state writes, bounded tracker and ref reads); use workers for source
interpretation or volume (consumer inventories, context maps, implementation, review, long check runs,
semantic rebases, CI failure analysis). Inline work never replaces an independent reviewer.

While workers run, do useful independent work. When a dependency blocks progress, wait on completion
events and require the terminal result before reading artifacts or running dependent steps. Re-read a
loaded brief or completed artifact only for a named gap. Report a new user decision or environment
blocker immediately.

## Re-tier

At the first checkpoint, if evidence shows the task is bigger or riskier than its tier (a hidden
invariant, a contract wider than listed, a non-mechanical checkpoint failure), raise the tier and revise
the context pack and checkpoints before implementation widens; reevaluate the B condition. More context
is no reason to replace the implementer. Two consecutive substantive finding rounds on an S task require
an M reviewer pack. A tier never drops mid-task; the state helper keeps the history.
