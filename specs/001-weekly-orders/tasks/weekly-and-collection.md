# 001 — Weekly integration and collection task details

Part of the [canonical registry](../tasks.md); dependencies live there. Status: **accepted (2026-10-28)**.
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
  nothing-to-collect bases while open. Validate only a settled, current source, under the shared source
  cycle lock; stale source edits are rejected. Preserve cutoff/send and empty-order scheduler behavior.
- Explicit unvalidated-only/all redistribution uses validation as financial freeze even if uncollected.
  Exclusion subtracts effective validated quantities and rejects negative/no-claim remainders; inclusion
  appends signed effective quantity/Wallet adjustments, with no stock/return resale. Unchanged targets
  append nothing; successive changes use latest effective amounts. Never edit validated originals.
- Close after settlement, distributed receptions and all bases validated/nothing to collect, irrespective
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

Verify S78/S89 and S9/S14–S16/S19/S20/S27–S30/S39/S40/S42/S46/S47/S53/S54/S61/S63/S64 plus S88's
weekly no-contents/revival case. Preserve allocator/tie/overwrite/cutoff regressions instead of rewriting
them. Integration proves source-sync versus validation serialization, idempotent settlement/redistribution,
source uniqueness, weekly rollback, no reopening after closure and one charge despite old endpoint replay.
T61 finishes the persistent indicators and member collection action; basic pending status remains visible.

Ownership/reuse: Ordering settlement/reopen/read ports, Sales weekly commands/adapters, Membership
pending-source adapter, old pickup financial-route cutover, reception/member/staff pages and shared
`DeliveredForm`/`Adjustments` patterns. Exercise the complete reception→draft→validate→closed/pending
flow through HTTP/UI with distinct validation and collection controls.

## T60

**Late supplements and container attribution.** Staff can open another basket from an existing
collection, including a basket collected the next day after its weekly cycle closed.

Source: [Basket sources](../plan.md#basket-aggregate-and-history),
[collection](../plan.md#collection-and-corrections), R18a/R19/R24b.

Acceptance:

- Supplement carries only newly chosen stock/return contents, not copied weekly lines. Optional
  origin cycle is server-verified attribution, not an open/settled gate or an exclusive cycle mutation
  lock. Multiple baskets per household/cycle are valid; cycleless purchases remain valid too. Cancelling a referenced pre-send cycle leaves an independent
  stock basket and its posted movements intact (R4b); attribution never imports weekly cancellation.
- For goods taken earlier and reported later, create/validate/collect with current recorded actor/time,
  preserving origin attribution without backdating/reopening the cycle. Insufficient recorded stock
  requires the existing justified Manager inventory process; Finance cannot bypass it.
- Per-basket/household and cycle container counts use effective weekly/stock snapshots, ceil per line;
  returns excluded. Before the base exists use allocations. Later attributed supplements update the
  closed cycle's counts; cycleless baskets count only for themselves.

Verify S80/S90 and updated S60 with late physical pickup then stock, cycleless purchases, multiple
supplements and corrected effective quantities. PostgreSQL interleaving must prove an attributed stock
sale and weekly validation cannot invert cycle/product locks via foreign-key key-share locks; use the
plan's compatible stable-key cycle lock and ordered product locks. Test origin access/identity server-side.

Ownership: Sales create-from-collection flow, attribution and count queries/adapters, Ordering cycle
counts and Finance basket links. Reuse staff list/detail and cycle summary components with no new weekly
order/session requirement. This supplements the T55 purchase tracer rather than adding a second sale path.

## T61

**Self-collection and persistent pending indicators.** A current member marks their household's
validated basket collected; staff and members continue to see pending baskets after the planned date
and weekly closure.

Source: [HTTP and pending indication](../plan.md#http--pages), R19a/R23a/R23b/R24/R24b/R35.

Acceptance:

- Application and HTTP check current household membership on every own-basket access/action. Only
  validated baskets may be collected; Finance/Manager may operate all. Record first actor/time,
  repeat/replay no-op without money/stock writes. Member cannot validate, edit or record receipts.
- Pending list includes closed-cycle and cycleless baskets, ordered by validation time/UUID; own
  household for members, all households for staff. Date passing never abandons/refunds/returns goods.
- Dashboard and basket list show pending indications; cycle list/detail retain a count badge/link after
  closure, including attributed supplements. Draft means preparation. Collection removes its pending
  item without reopening/recharging; corrected collection history remains accessible.
- Replace current Dashboard pending-cycle data with narrow Sales basket summaries and preserve
  unrelated cards. Feature-002 DRA-202/DRA-203 are context, not prerequisites or a scope expansion.

Verify S82/S83 and S26/S42/S54; include cross-household IDs, changed/unlinked member, closed cycle,
cycleless purchase, no balance/payment gating, duplicate simultaneous collection and retained first event.
Measure structural/status/action changes before visual runner review of populated/empty/late/closed
states, long household/product names and narrow-role navigation.

Ownership: Sales own/staff queries and collection access, member Dashboard/basket list/detail,
Finance pending list, cycle badges and shared navigation. Original validation/ledger commands are reused.

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
It can proceed alongside T60/T61 after T59: shared pages are scheduling contention, not blockers.

Ownership: Membership warning/deactivation/to-settle commands, Sales open-obligation query, household/
account administration and Finance resolve-existing-basket entry points. No general household rights
are granted to Finance.

## T63

**Remove superseded pickup ownership and schema.** Contract the expand/migrate/contract sequence
once every consumer uses Catalog/Sales. This is a justified mechanical cleanup, not another behavior
slice or a data-migration project.

Source: [Context map](../plan.md#bounded-contexts), [Persistence](../plan.md#persistence-postgres),
[HTTP](../plan.md#http--pages), D19–D25.

Acceptance:

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
slice's checks. Depends on T54, T61 and T62 because catalogue readers, collection indicators and household
obligation consumers must all be cut over first.

## T33

**Current traceability audit and feature closure.** Preserve existing T33 identity
([DRA-183](https://linear.app/draude/issue/DRA-183/t33-traceability-check-and-close-feature-001));
its original baseline remains historical. The accepted scope was published with explicit user authorization on
2026-10-28; T33 remains Todo. See the [publication record](../tasks.md#linear-publication-and-current-gates).

Acceptance:

- Audit all 65 requirements and S1–S90 against meaningful assertions through the appropriate public
  seams, using [coverage allocation](coverage.md) as responsibility, not proof. Read assertions and
  failure boundaries, fill confirmed gaps without changing accepted behavior; identifier presence alone
  never establishes coverage. Include source/stock/Wallet concurrency, permissions and retained history.
- Confirm every required leaf, T36 translations and outstanding visual work/approval is integrated;
  all 11 original native blockers were retained, and T02/T44/T46/T63 plus T48/T50/T51 were
  added (18 total). T47–T51 map to DRA-219–DRA-223; every canonical closure prerequisite now
  has a native blocker. No local task row or tracker publication implies Done.
- Record focused checks, independent review, required visual reports and exact-candidate CI evidence.
  Only then mark feature 001 Done in `specs/README.md`, with deviations reconciled in spec/plan/log.

Ownership: tests and missing assertions, traceability evidence and final feature documentation. No
unapproved product choices, publishing or new implementation work is authorized by this document acceptance.
