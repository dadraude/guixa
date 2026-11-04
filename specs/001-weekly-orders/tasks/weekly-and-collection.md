# 001 — Weekly integration and collection task details

Part of the [canonical registry](../tasks.md); dependencies live there. Status: the revised delivery boundaries below are **proposed (2026-10-31)**; product acceptance is unchanged.
Authoritative behavior: [spec](../spec.md) and its areas; technical contracts: [plan](../plan.md).
Every slice includes its affected adapters, tests, translations, UI evidence and document reconciliation.
Paths in the ownership notes are edit surfaces, not new cross-context domain dependencies.

## T59

**Weekly basket cutover and redistribution.** Switch settlement, actual quantities, validation and
weekly closure to Sales in one coherent writer cutover. This is the migrate stage of the context
split: each part changes the same financial-freeze/closure invariant, so a layer-only or half-cutover
would leave duplicate charges or incorrect redistribution. Reuse the already tested allocation algorithm.

Source: [Ordering](../plan.md#ordering), [Sales](../plan.md#sales),
[persistence and locks](../plan.md#persistence-postgres), R4/R12a/R18/R19/R19a/R22–R24b.

Acceptance:

- First settlement creates exactly one base draft per eligible source order, ordered snapshots retained,
  actual quantities initially allocations, no charge. Replayed settlement creates no duplicate basket.
  Staff changes actual weekly quantity (including zero) before validation; member sees ordered versus
  actual, reception flags differences but still balances on allocations. Only Sales owns actuals.
- Reopen/synchronize resets affected unvalidated actual overrides, preserves stock/returns and revives
  nothing-to-collect bases while open. A late arrival (received 0 → >0) adds to the open weekly draft
  or creates one late weekly basket draft when all the household's weekly baskets are validated (S30/S91). Validate only a settled, current source, under the shared source
  cycle lock; stale source edits are rejected. Preserve cutoff/send and empty-order scheduler behavior.
- Validation is the financial freeze even if uncollected: a redistribution of a product any validated
  basket contains is rejected before anything changes, pointing to per-basket corrections (T58/T68).
  There is no drafts-only/all choice and reception never appends adjustments to validated baskets.
  Products no validated basket contains synchronize into drafts (S63/S64/S89). Never edit validated originals.
- Close after settlement, distributed receptions and all base/late weekly baskets validated/nothing to collect, irrespective
  of physical collection. Empty/no-content means no money or collection; real-content zero totals can
  validate. Closed weekly sources cannot reopen. Supplements never gate closure.
- Switch all weekly financial writers and current source readers together, including pending/obligation
  adapters consumed by Membership and current cycle/member screens. Remove/disable charge-on-collection
  and abandonment endpoints for weekly purchases at cutover. Standalone stock operations do not invoke
  Ordering readiness. Remaining dead ownership is removed in T63; there is no active dual writer.
- Update `architecture/modules.php` alongside the writer cutover: declare the delivered Sales/Ordering
  interfaces, permitted callers, dependencies and table ownership; reflect Sales ownership of actuals,
  validation and collection. Remove superseded integration permissions when their active callers are
  cut over, including direct Ordering Stock/Wallet permissions no longer used. Stock keeps inventory
  ownership and Wallet keeps ledger ownership. Retired schema cleanup remains in T63. Update the
  module-contract documentation and relevant rejection tests, then run `tests/Architecture`.

Verify S78/S89 and S9/S14–S16/S19/S20/S27–S30/S39/S40/S42/S46/S47/S53/S54/S61/S63/S64/S91 plus S88's
weekly no-contents/revival case. Preserve allocator/tie/overwrite/cutoff regressions instead of rewriting
them. Integration proves source-sync versus validation serialization, idempotent settlement/redistribution,
source uniqueness, weekly rollback, no reopening after closure and one charge despite old endpoint replay.
T61 supplies member collection; this cutover verifies its weekly/closed-cycle integration. T67 finishes persistent aggregate indicators; basic pending status remains visible.

Ownership/reuse: Ordering settlement/reopen/read ports, Sales weekly commands/adapters, Membership
pending-source adapter, old pickup financial-route cutover, reception/member/staff pages and shared
`DeliveredForm`/`Adjustments` patterns. Exercise the complete reception→draft→validate→closed/pending
flow through HTTP/UI with distinct validation and collection controls.


### T59 delivery checkpoints and size exception

Retain one atomic cutover PR. This is deliberately not called a thin tracer. No new flag, shadow
financial writer, preview-only product or temporary disabled redistribution is proposed. The current
`CycleSettlement`, `SetReceivedTotal` and `SetAllocation` use Order pickup/freeze state; replacing only
settlement or only charging would leave closure/redistribution on a different financial truth.

Before writing, inventory old entrypoints and their replacement consumers: scheduler settlement,
manual settlement/reopen, both allocation commands, validation, collection, Membership obligations,
member/staff current reads and architecture permissions. Use exact paths from the candidate base;
the plan's `SalesBaskets`/`WeeklyBasketSource` are planned interfaces, not existing implementations.

Internal checkpoints with the same owner, before broad final verification:

1. Through public settlement and HTTP, create/reload one uncharged weekly base with original ordered
   and initial actual quantities. Assert source uniqueness and no ledger write; then validate once,
   close only when eligible, and collect without a second charge. Include an old-endpoint replay probe.
2. Reopen/synchronize with one validated pending basket and one draft. Assert rejection of a
   redistribution of a product the validated basket contains (nothing changes), draft sync for other
   products, and late arrivals into the open draft or one late weekly basket with its own validation and
   charge. Assert immutable originals and no Wallet write from reception. Preserve allocator regressions.
3. Race source sync against validation under the cycle lock; prove rollback, empty/revived sources,
   scheduler and closed-cycle guards, Membership obligations, current readers and role boundaries.
   Reconcile the entrypoint inventory before independent review and candidate-bound CI.

These are feedback points inside an unmerged task, not partial releases or extra mandatory independent
review rounds. A checkpoint must show the behavioral red/green and persisted/rendered values; a test
count alone does not pass it. If source inspection identifies a genuinely behavior-preserving, useful
prefactor, propose its exact boundary before splitting; do not invent a preparatory ticket on speculation.

Dependency reasons: T68 supplies effective signed quantity/Wallet corrections and all independent line
kinds; T66 supplies effective collection history; T23/T30 supply the existing settlement/redistribution
behavior being replaced. T68 transitively includes T56/T64/T65. T61 is a regression integration when
available, not a prerequisite for Finance/Manager weekly collection; T61 must also test weekly sources
if it lands after T59. Pending indicator completion remains T67 after both paths exist.

Size estimate: 1,500–3,000 authored changed lines including tests, low confidence; generated/deleted
files reported separately. The one-writer invariant justifies this exception, not a generic waiver.
Checkpoints bound feedback latency; source-specific scope must be reassessed before implementation.

## T60

**Household container balance.** Each household has an accumulated balance of containers it still has
to return, across all its baskets and days. Validating a basket adds; return lines reduce. A late
addition after validation is simply a new independent basket with no cycle.

Source: [Basket sources](../plan.md#basket-aggregate-and-history), R18a/R19/R24b.

Acceptance:

- Additions go to a basket while it is not validated; once validated, anything added creates a new
  independent basket with no cycle reference. For goods taken earlier and reported later, create,
  validate and collect with the current recorded actor/time, without backdating or reopening a cycle.
  Insufficient recorded stock requires the existing justified Manager inventory process; Finance
  cannot bypass it.
- The balance is derived: effective validated weekly/stock lines add quantity × snapshotted containers
  per unit, ceil per line; validated return lines reduce it. No money movement is added (the container
  price is already in the product price).
- The balance may be negative; a return is never rejected for exceeding it (R18a, S92).

Verify S60 (per household), S80 and S92 with late physical pickup then a new independent basket, multiple
baskets and corrected effective quantities. Test household scoping server-side.

Ownership: Sales balance read query/adapter, Ordering's container summary consumer and Finance basket
links. Reuse staff list/detail components. This supplements the T55 purchase tracer rather than adding
a second sale path. Focused checks: Sales public queries, persistence, role HTTP and real button interaction.
Dependency reason: T59 supplies validated weekly baskets; T31 supplies container snapshots.

Size/rollout: roughly 150-350 authored lines, low confidence. No flag. Pending lists/dashboard/cycle
badges stay in T67.

## T61

**Collect an own-household validated basket.**

A current member marks their household's validated basket collected from its detail page, reusing
the staff collection operation without posting another charge or stock sale.

Source: [Collection](../plan.md#collection-and-corrections), R19a/R23a/R23b, S82.

Done when:

- Given a validated own-household basket, when the current member collects it, then the first actor/time
  are retained. Duplicate simultaneous requests/replay have no additional event or ledger effect.
  Negative balance and lack of a receipt do not block collection; drafts/cancelled baskets cannot collect.
- Current membership is checked at Application and HTTP boundaries. Forged household IDs, moved/unlinked
  members and deactivated accounts lose access; members cannot validate, edit, receive money or correct.
- The detail control and state update are translated and keyboard accessible; T66's effective collection
  state/history remains coherent after a staff correction. Staff permissions stay intact.

First checkpoint: an own-household HTTP collection succeeds and an identical cross-household request
fails; reload the first actor/time and unchanged ledger counts. Then check concurrent replay.
Paths: T65 member detail/read path, T55 MarkBasketCollected, T66 effective collection/history.
Focused checks: Sales public commands, persistence/concurrency, role HTTP and real button interaction.
Dependency reason: T66 supplies the corrected collection model and transitively T65's authorized member access.

Size/rollout: roughly 200–450 authored lines, low confidence. Deliver on independent baskets first;
T59 applies the same operation to weekly baskets and verifies late/closed-cycle integration. No flag.
Pending lists/dashboard/cycle badges move to T67; this task does not require supplements to exist.

## T67

**Keep pending collection visible across pages and cycle closure.**

Staff and members continue to see outstanding collections after the planned date and weekly closure,
including cycleless purchases, without reopening or charging them again.

Source: [Sales interface](../plan.md#sales), R24/R24b, S42/S54/S83.

Done when:

- Given late, closed-cycle, cycleless and supplementary baskets, when pending lists are read, then
  validated effective outstanding goods remain visible, ordered by validation time/UUID. Members see
  only their current household; Finance/Manager sees all. Draft means preparation, not pending collection.
- Dashboard and basket list use narrow Sales summaries; cycle list/detail show persistent count/link
  of weekly baskets. Collection removes the pending item without reopening/recharging.
  Full content reversal and corrected collection consume T68/T66 effective state; history remains accessible.
- Preserve unrelated dashboard cards; do not revive feature-002 dashboard scope. Verify populated,
  empty, late/closed, long-name and role states with exact counts/status/actions before geometry.

First checkpoint: one closed-cycle pending basket appears with the correct count/link, then real
collection removes it while the cycle stays closed and ledger is unchanged. Widen the same query to
dashboard/list and role cases; include source-scoped HTTP privacy and PostgreSQL reads.
Paths: Sales pending queries/adapters (planned per plan), existing Dashboard, cycle list/detail and
basket views, T61 collection action. Reuse layouts/navigation.
Focused checks: pending query/HTTP and membership regressions, dashboard/cycle/basket components,
translations, exact effective-count fixtures. Related: DRA-202/DRA-203 as context only, never blockers.
Dependency reasons: T60 supplies independent baskets; T61 supplies member collection
and transitively T66 corrected collection. T60/T59/T68 supply effective goods quantities.

Size/rollout: roughly 400–800 authored lines, low confidence. One shared pending query must feed its
consumers consistently; checkpoint it on one page before widening. Existing basic basket status remains
visible before this task. No flag, new financial writer or broader dashboard redesign.

## T62

**Household obligations across lifecycle changes.** Deactivating or emptying a household keeps its
existing drafts, collections and Wallet obligations actionable without admitting new purchases.

Source: [Membership](../plan.md#membership), [account rules](../stock-wallet.md#households-and-accounts),
R35/R35a/R36b.

Acceptance:

- Deactivation warning and Manager to-settle list include balance, unresolved drafts and validated
  pending collection from Sales. Keep the household listed until all three are resolved, even if its
  balance is zero. Retain orders, basket/ledger originals and actor history.
- Existing drafts can still be validated/corrected/collected by Finance/Manager and receipts recorded;
  new stock baskets/orders require an active household. Deactivation and last-member leave unlinks
  members; reactivation/linking keeps the prior account/household semantics and moves no history.
- A moved/unlinked/deactivated member cannot operate the former household's basket by an old link.
  Reactivation does not restore former membership links; independent Finance role rules remain.

Verify S58, S43 and S59 regressions through Membership's public commands/queries and HTTP, with
zero-balance draft-only and pending-only obligations, final correction/collection resolution and relinking.
The data-source adapter switched in T59; this task completes lifecycle behavior and its warning/UI checks.
It can proceed alongside T60/T67 once its prerequisites are integrated: shared pages are scheduling contention, not blockers.

Ownership: Membership warning/deactivation/to-settle commands, Sales open-obligation query, household/
account administration and Finance resolve-existing-basket entry points. No general household rights
are granted to Finance.


First checkpoint: deactivate a zero-balance household with a draft-only obligation through the public
Membership command; the warning/list retains it, staff can resolve it, and an old member URL is denied.
Widen to pending-only, receipt/correction and reactivation cases before final verification. Use existing
Membership household commands and administration pages; Sales obligation adapters arrive in T59.
Dependencies: T59 supplies the current Sales obligation source, T32 the existing lifecycle UI/rules,
T64 confirmed receipt/correction resolution and T66 corrected collection semantics are inherited through T59. Keep one task:
warning, retained obligation and permission changes must agree. Rough size 300–650 authored lines,
low confidence. No new general Finance household rights or flag.

## T63

**Remove superseded pickup ownership and schema.** Contract the expand/migrate/contract sequence
once every consumer uses Catalog/Sales. This is a justified mechanical cleanup, not another behavior
slice or a data-migration project.

Source: [Context map](../plan.md#bounded-contexts), [Persistence](../plan.md#persistence-postgres),
[HTTP](../plan.md#http--pages), D19–D25.

Acceptance:

- Drop column `baskets.reference_cycle_id` (created by an earlier migration) in the contract stage, with
  its foreign key and any index; baskets carry no cycle reference of their own.
- Remove Stock's duplicate product entity/table/metadata, Ordering stock/return/actual/pickup/charge
  ownership and obsolete adapters/helpers/tests of replaced behavior. Active commands/routes cannot
  invoke `CompletePickup`, charge-on-collection or automatic not-collected charging.
- Final fresh-schema migrations use Catalog products and Sales baskets before referencing stock/wallet
  sources, retained history and partial uniqueness; remove obsolete order stock/return/adjustment
  tables and household/cycle charge uniqueness. No imports, UUID mapping, backfills or aliases.
- Current readers, factories, DTO/types, architecture tests, navigation and documentation use the
  accepted interfaces. Preserve useful regressions with new assertions rather than simply deleting
  tests that reveal missing behavior. No production reset is run.
- Remove retired exports, caller permissions, dependencies, table ownership entries and scoped
  exceptions from `architecture/modules.php` in the same change as their source/schema removal.
  Reconcile the final Catalog/Sales/Ordering interfaces and complete migration-created table ownership
  with the accepted plan. Update the module-contract documentation and retain rejection tests proving
  retired access remains forbidden; run the complete `tests/Architecture` suite.

Verify architecture/public route contracts, a fresh isolated schema and affected repository contracts;
run focused catalogue/weekly/stock/wallet/membership/basket regressions and candidate-bound full CI.
Search the bounded retired symbol/table/route set for remaining executable callers and manually explain
any deliberately retained historical documentation mention. T33 then performs the semantic global audit.

Ownership: remaining retired source/adapters/persistence/routes/tests and stale current documentation
across the affected contexts. No new UI redesign; existing visible paths must still pass their owning
slice's checks. Depends on T54, T67 and T62 because catalogue readers, all collection indicators and household
obligation consumers must be cut over first; T67 includes T60/T61 and T62 includes T64. All new slices
therefore reach T33 through this cleanup, without adding redundant closure blockers.


First checkpoint: remove one bounded retired ownership path together with its callers/permissions,
then prove the public route cannot invoke it and the fresh schema/shared contracts still work. Repeat
by retired family; reconcile the complete symbol/table/route inventory before review. Do not turn
code, schema and architecture declarations into independent incompatible deliveries.
Rough size 800–1,600 changed lines including deletions, low confidence; this mechanical contract-stage
exception follows real consumer migration, not an arbitrary large cleanup. No application feature or
production database mutation. T33 retains the final semantic audit, not first-time acceptance ownership.

## T33

**Current traceability audit and feature closure.** Preserve existing T33 identity
([DRA-183](https://linear.app/draude/issue/DRA-183/t33-traceability-check-and-close-feature-001));
its original baseline remains historical. The accepted scope was published with explicit user authorization on
2026-10-28; T33 remains Todo. See the [publication record](../tasks.md#linear-publication-and-current-gates).

Acceptance:

- Audit all 65 requirements and S1–S92 (S90 retired) against meaningful assertions through the appropriate public
  seams, using [coverage allocation](coverage.md) as responsibility, not proof. Read assertions and
  failure boundaries, fill confirmed gaps without changing accepted behavior; identifier presence alone
  never establishes coverage. Include source/stock/Wallet concurrency, permissions and retained history.
- Confirm every required leaf, T36 translations and outstanding visual work/approval is integrated;
  all 11 original native blockers were retained, and T02/T44/T46/T63 plus T48/T50/T51 were
  added (18 total). T47–T51 map to DRA-219–DRA-223; every canonical closure prerequisite now
  has a native blocker. No local task row or tracker publication implies Done.
- Collect each delivery's assertion-level acceptance evidence and current integration identities; investigate confirmed gaps rather than rerunning every prior suite. Record focused checks, independent review, required visual reports and exact-candidate CI evidence.
  Only then mark feature 001 Done in `specs/README.md`, with deviations reconciled in spec/plan and immutable history entries.

Ownership: tests and missing assertions, traceability evidence and final feature documentation. No
unapproved product choices, publishing or new implementation work is authorized by this document acceptance.
