# Remaining Sales tasks: accepted reslicing

Status: **accepted and published to Linear, 2026-10-31**. See verified publication below.
This proposal changes task boundaries and verification sequencing, not accepted product behavior.
Canonical titles/scopes and the prerequisite graph live in [tasks.md](../tasks.md); this document
records the decision evidence and publication delta, not a second execution registry.

## Evidence and readiness

- Baseline: fetched `origin/main` at `877fe20f83686c5e1f86b0d1312b5fe919542d54`.
  Accepted plan/requirements and legacy history consulted through the history reader. T52–T54
  are Done in Linear; T55/DRA-210 is In Review with unmerged PR #269 at `a9c36d8`.
  T56–T63 are Backlog under DRA-206; T33 remains Todo with its 18 existing blockers.
  The full Coop issue inventory was read in one page (`hasNextPage=false`) and each affected issue's
  description and native relationships read separately. No existing T64–T68 was found in that inventory.
- The codebase-memory generation/coverage APIs are unavailable. Codegraph reported this worktree
  unindexed. Evidence uses bounded direct source reads; no graph completeness claim is made.
- Main already has `src/Wallet/Application/RecordTopUp.php`, `RecordCorrection.php`, immutable
  `Movement.php`, Manager wallet HTTP/forms and repository tests. Current corrections have a note
  and optional cycle but no original-movement source contract. T64 extends that real path; it is not
  a new receipt subsystem. T56 changes role propagation; T64 completes Finance receipt capabilities.
- T55 candidate `tests/Application/Sales/StockBasketTest.php` and
  `tests/Integration/Sales/BasketPersistenceTest.php` exercise combined availability, fractional steps,
  inactive snapshots/producers, remove/re-add/cancelled history, rounding and replay. These remain
  regression obligations; T57 no longer budgets a new implementation for them. Candidate evidence
  becomes a dependency only after actual integration; do not mark T55 Done from this document.
- `src/Ordering/Application/CycleSettlement.php` closes using Order pickup state; `SetReceivedTotal.php`
  and `SetAllocation.php` redistribute through that frozen state and the old Wallet seam. The plan
  changes all of those to Sales validation/effective quantities. A half-cutover would have different
  financial truths, so T59 retains one merge with three early feedback checkpoints.
- Requirements/design: ready to draft from the accepted Sales/Wallet/Membership plan. Planned paths
  are labelled in each task. No new external service, product decision, flag or data migration is needed.
  Execution is blocked on approval/integration and actual predecessors; this draft is not admission.
  All implementation line estimates are rough, source-informed ranges, not measured effort forecasts.

## What moves, and what stays together

| Existing task | Proposed treatment | Where the removed acceptance goes |
| --- | --- | --- |
| T56 / DRA-211 | Finance role and existing basket access only | T64 owns confirmed receipts and source-linked receipt correction; S84 completes across both |
| T57 / DRA-212 | Return-only/mixed basket validation | T65 owns member reads; stock rules already in T55 remain regressions, not duplicate work |
| T58 / DRA-213 | Amount-only basket correction | T68 owns physical stock/effective quantity correction and S86; T66 owns collection-mark correction |
| T59 / DRA-214 | Retain atomic weekly cutover | All source-sync, validation, redistribution, closure, readers and old-writer disabling remain together; three checkpoints bound feedback |
| T60 / DRA-215 | Retain supplements plus container counts | S90 requires an attributed supplement to update the count; deferring counts would give a wrong intermediate summary |
| T61 / DRA-216 | Member collection action | T67 owns persistent pending query/list/dashboard/cycle indicators |
| T62 / DRA-217 | Retain lifecycle warning plus resolution/access behavior | Separate warnings and resolution would disagree about whether obligations remain actionable |
| T63 / DRA-218 | Retain final mechanical contract cleanup | Retired source, schema, permissions and readers are removed coherently after all consumers migrate |
| T33 / DRA-183 | Retain final audit and closure | Each delivery supplies assertion-level evidence; T33 reconciles and investigates gaps rather than first discovering all acceptance |

Five additional executable tasks (T64–T68) turn eight remaining feature deliveries into thirteen.
This is fewer bundled outcomes, not thirteen approvals: each task still has one implementation owner,
focused verification, meaningful independent review and exact-candidate CI. Early checkpoints are
same-owner coordinator feedback and do not consume extra review rounds.

## Proposed native relationship delta

All existing issues retain their identities, project, team, status, parent, assignment and labels.
New leaves use the existing DRA-206 parent, Backlog, no assignee/delegate, and the existing
`ready-for-agent` convention only after the approved canonical scopes are published. That label
does not override unresolved blockers or task admission. No new grouping parent or reparenting.

The rows below show the **complete proposed blocker sets**, not array appends. New task identifiers
are resolved to actual returned Linear IDs only during authorized publication. Keep unrelated relations.

| Issue/task | Proposed blocked by | Change from tracker |
| --- | --- | --- |
| DRA-211 / T56 | T55, T07, T38 | Unchanged |
| DRA-212 / T57 | T56 | Unchanged |
| DRA-213 / T58 | T64, T65 | Replace T57: amount-only correction does not consume returns; needs authorized staff, linked Wallet corrections and member read history |
| DRA-214 / T59 | T68, T66, T23, T30 | Replace T58 with its goods/effective-quantity completion T68; include collection-history contract T66 |
| DRA-215 / T60 | T59, T31 | Unchanged |
| DRA-216 / T61 | T66 | Replace T60: member collection works on independent baskets before weekly supplements; needs own access and effective collection state |
| DRA-217 / T62 | T59, T32 | Unchanged; receipt and corrected-collection contracts are inherited through T59 |
| DRA-218 / T63 | T54, T67, T62 | Replace T61 with completion of all pending-reader consumers T67 |
| New T64 | T56 | Finance plus existing Wallet receipt/statement path |
| New T65 | T55, T38 | Existing baskets plus translated shared member controls |
| New T66 | T56, T65 | Finance correction and household-scoped history views |
| New T67 | T60, T61 | Supplement/weekly attribution plus member collection; inherits effective quantity/history contracts |
| New T68 | T58, T57 | Append-only amount history plus all independent basket line kinds |

T61 keeps its existing related links to DRA-202/DRA-203; add the same context-only links to T67,
where the pending dashboard scope moves. Neither becomes a blocker or revives feature 002.
DRA-206's completion body expands to T52–T68. T33 keeps all 18 native blockers, including T63;
the new leaves are transitively required by T63. Do not close or otherwise change either parent/status.

At the original draft read, the execution frontier was **none**: T55 was not integrated and the
breakdown was unapproved. The publication readback below supersedes this snapshot. Once T55 and this document change are integrated/accepted, T56 and T65
have satisfied implementation prerequisites (T07/T26/T38 are already Done); scheduling still checks
shared routes/components. Existing numbering is identity, not a serial execution order.

## Publication packet and approval boundary

The task title in the canonical registry, its full detail section, the shared execution contract and
its complete `After` set form each proposed issue body. A task-local `.atl/reslice/linear-draft.json`
contains the exact assembled titles/bodies and old/new relation sets for review, including DRA-206
and T33 updates. It is a generated review snapshot; any source change requires regeneration before
publication. No new issue IDs, successful publication or completed tests are implied.

Publish only after the user reviews this concrete breakdown, as explicitly requested. Re-read tracker
state and canonical source at that point; preserve concurrent changes and other native relations.
Create new leaves, record returned IDs, then reconcile affected existing blockers and read back every
changed body/relation. Source links must target the actually published documentation revision rather
than inventing links to an unpushed branch. The user approved the concrete breakdown and explicitly requested its publication to Linear.

## Workflow changes and remaining audit work

The task-creation reference now reconciles delivered/pending work, conserves acceptance ownership,
checks safe intermediate behavior, uses line count as a warning rather than a quota, and requires a
first semantic vertical checkpoint. Flags are conditional, not a default answer to oversized tasks.
The ticket process handles explicitly requested reslicing; implementation briefs consume those
checkpoints without adding user approval or independent-review rounds.

After reviewing this breakdown, return to the other retrospective recommendations: routine-harness
recovery policy, executable preflight/examples, semantic focused-check selection, authoritative owner
state, and non-overlapping timing/communication. Those policy/helper changes are not bundled here.

## Verified Linear publication — 2026-10-31

The user approved the breakdown and explicitly authorized publication. Five leaves were created
and ten existing issues updated. Readback verified titles, complete descriptions (allowing Linear
Markdown normalization), team/project, parent, status, labels and all native prerequisite/context
relations. Existing assignments, milestones, priorities and dates were preserved. Linear-generated
mention relations were removed where not part of the approved graph. No duplicate tasks or cycles
were found. All 17 T52–T68 children remain under DRA-206; T33 retains all 18 blockers and Todo.
New leaves are Backlog, unassigned, with ready-for-agent. Nothing was marked complete by this update.

| Issue | Task | Blocked by | Related context |
| --- | --- | --- | --- |
| DRA-211 | T56 | DRA-159, DRA-194, DRA-210 | None |
| DRA-212 | T57 | DRA-211 | None |
| DRA-213 | T58 | DRA-224, DRA-225 | None |
| DRA-214 | T59 | DRA-228, DRA-226, DRA-180, DRA-176 | None |
| DRA-215 | T60 | DRA-214, DRA-181 | None |
| DRA-216 | T61 | DRA-226 | DRA-203, DRA-202 |
| DRA-217 | T62 | DRA-214, DRA-182 | None |
| DRA-218 | T63 | DRA-227, DRA-209, DRA-217 | None |
| DRA-224 | T64 | DRA-211 | None |
| DRA-225 | T65 | DRA-210, DRA-194 | None |
| DRA-226 | T66 | DRA-225, DRA-211 | None |
| DRA-227 | T67 | DRA-216, DRA-215 | DRA-202, DRA-203 |
| DRA-228 | T68 | DRA-212, DRA-213 | None |

Sources in Linear target published commit `a3462e05e555c532788d957241c8342c198b815b`.
The documentation/workflow branch `docs/reslice-sales-tasks` is published, not merged.
Publication readback also found T55 Done and PR #269 merged at
`55c1842d407c636093514e561dafb4c41e51e560`; the final T55 candidate is an ancestor
of origin/main. T56 and T65 now have satisfied technical prerequisites. Scheduling and normal
implementation gates still apply; use the accepted documentation revision until it is integrated.

The remaining audit findings are handed to a new session for prioritization, not automatic implementation.
