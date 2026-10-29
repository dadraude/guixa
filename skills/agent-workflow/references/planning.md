# Planning evidence and task handoffs

Use this reference when turning accepted requirements and a design into executable tasks, or when
preparing those tasks for ticket publication. It complements the project's document flow; it does
not authorize implementation, product decisions, document approval or tracker writes.

## Readiness before slicing

Check only the requested scope. Record Ready, Not ready or Not applicable for each concern below,
with source paths/sections and a reason. Unknown is not Ready. Reuse current evidence; an old blocker
list is a starting point to verify, not proof of today's state.

| Concern                | Evidence to establish                                                                                                                                                                                                                                     |
| ---------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Behavior               | Accepted requirements, scenarios, glossary, inherited constraints and permission rules specify the outcome. Check feature status: retired or superseded scope is not current authorization.                                                               |
| Decisions              | Identify open questions and contradictions affecting this scope, following resolved references to the decision that landed. Unrelated open questions do not block it.                                                                                     |
| Design                 | The plan specifies the necessary contracts, ownership, data/state flow and failure behavior. For UI work, approved sources settle the required interaction and states; reuse existing components and tokens. Non-UI work needs no invented screen.        |
| External prerequisites | Required external contracts, services, access and environment are available. A filed request or tracking issue alone does not prove delivery. Record prerequisites without secret values.                                                                 |
| Paths                  | Check existing implementation paths using repository discovery rules. Mark proposed paths as planned and cite the design establishing their boundary; new files need not already exist.                                                                   |
| Verification           | Identify an observable result and checks through the real production path needed for this behavior. Test doubles can support focused tests, but do not prove an integration works. Do not add persistence or network calls the behavior does not require. |

Distinguish missing decisions or external delivery from source-specified implementation work that can
be scheduled as a prerequisite in this batch. A missing contract or required design is not made ready
by putting its tracking issue under Blocked by.

For Not ready scope, return the affected requirement/scenario refs, exact evidence and the decision
or artifact needed. Ask the concrete unresolved question through the adapter; do not silently invent
an answer, amend the spec or issue a publishable draft for that scope. An independent ready subset may
be drafted if it still delivers a useful result; disclose the deferred scope and why. Recheck affected
evidence after a source, scope or dependency change.

## Task quality (INVEST)

Every new executable task and ticket must be:

- **Independent:** mergeable without waiting on unfinished tasks. Name unavoidable prerequisites and
  the merged result required before the successor is ready.
- **Valuable:** delivers a result a user or tester can observe.
- **Small:** one PR, ideally under about 400 lines of diff, with code, tests and docs together.
  Estimate scope; split oversized work into useful vertical slices or explain a justified exception.
- **Testable:** includes spec-derived acceptance criteria and requirement/scenario refs, expressed as
  concrete Given/When/Then observations in the handoff below.

For AI agents, scope each vertical task so one implementer can understand, deliver and verify it.

## Slice and explain the batch

Start the proposed breakdown with a short architecture overview: the source-backed pathway, what
already works and the first missing behavior to establish. The first executable task is the smallest
reviewable end-to-end result; later tasks reuse it only where they actually depend on it. If the
path already exists, do not schedule a duplicate bootstrap or renumber existing tasks to add task zero.

For a big new project, start with a **walking skeleton**, including CI/CD, deployment and a check
of the deployed result, to expose architecture problems early. Reuse existing delivery paths;
planning does not authorize deployment. Group feature slices under one native epic tracking the
outcome; reuse existing epics and respect parent/inheritance constraints.

Use feature flags to merge unfinished slices safely to main. Record the flag, safe default, off/on
acceptance checks and rollout/removal owner or task. Testers must be able to demonstrate the slice.

Keep each task within one fresh implementer's context, including its required source excerpts and
verification. A task that needs several unrelated paths or cannot state a bounded observable result
should be split. Put a necessary prefactor inside the behavior it enables when practical. Preserve
the project's justified exceptions for tooling, non-UI work and mechanical expand/migrate/contract
refactors; state the observable check and why a vertical behavior is impractical. Each refactor stage
must preserve working behavior, name bounded consumers and order removal after all migrations.

Give every dependency a reason: the result or contract the successor needs. Do not serialize tasks
because of their numbering, priority or temporary shared-file contention. After prerequisites, prefer
the available slice that resolves the greatest technical or integration uncertainty.

## Executable task handoff

Use the project's canonical task format, carrying these fields in its existing sections rather than
creating a second registry. Grouping parents need outcome/completion criteria, not executable detail.
Scale detail to the task; use Not applicable with a reason where a concern is absent.

- **Identity and outcome:** existing task ID, action-oriented title, source task link, parent or none,
  and the observable behavior this task delivers.
- **Scope and path:** entry action or trigger, relevant state/domain rules, adapters or persistence
  actually needed, and the return/result path. List verified existing paths and justified planned paths.
- **Requirements and contracts:** qualified requirement/scenario references, the relevant acceptance
  text and inherited constraints; necessary contracts, data shapes, error behavior and domain terms.
  Include relevant configuration defaults/scopes and UI states/component paths when applicable.
- **Dependencies:** task IDs or existing issue identifiers, with a reason per edge; related context
  stays distinct. None means no implementation prerequisite, not automatic admission to execution.
- **Done when:** concrete Given/When/Then observations and focused tests/checks with expected results.
  For persistence-dependent behavior, verify write/read through the real adapter; for UI behavior,
  identify the visible result and required visual evidence. Planned checks are not passing results.
- **Size and rollout:** estimated diff size, justified exceptions and any flag details listed above.
- **Limits and allocation:** explicit out-of-scope limits. If this task covers only part of a source
  requirement, identify that part, the remainder's owner and the task/dependency that completes it.
  Do not exclude required permissions, validation or failure behavior to make a slice look smaller.

Keep specs and plans canonical. Copy only the exact acceptance text and contract excerpts needed to
understand the task; preserve their refs and meaning, linking the source for provenance and current
context. Do not duplicate entire mutable specs or require the implementer to reconstruct a behavior
from links alone. A source change requires reconciliation of affected drafts before publication.

## Check before presenting

- Readiness evidence still matches the proposed scope and source versions.
- Task quality and batch slicing meet the rules above; rework failures before presenting.
- Each executable task maps to the canonical registry and has a bounded outcome and verifiable Done
  when. Planned paths and partial requirement coverage are explicit.
- Every scoped requirement/scenario has an owner, or is explicitly deferred with the reason. A shared
  criterion is not claimed complete by each partial slice. Out-of-scope text does not contradict it.
- Blockers resolve to batch tasks or existing issues. There are no missing endpoints, self-links or
  cycles; the order respects dependencies without inventing sequential edges.
- Required behavior, contracts and UI details can be understood from the handoff plus its identified
  source excerpts and implementation, without guessing product decisions.

Present exact proposed titles, bodies and relationships for review. Invite corrections to granularity,
missing context and dependency reasons; recheck changed tasks and dependents. Existing approval of the
same concrete breakdown need not be requested again. Draft approval alone does not request publishing.

Report the current **frontier**: tasks whose implementation blockers are all completed, including
unblocked tasks. A published prerequisite is not a completed prerequisite. The frontier is separate
from tracker labels, authorization, admission and safe parallel scheduling.
