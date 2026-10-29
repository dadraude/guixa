# 001 — Sales task details

Part of the [canonical registry](../tasks.md); dependencies live there. Status: **accepted (2026-10-28)**.
Authoritative behavior: [spec](../spec.md) and its areas; technical contracts: [plan](../plan.md).
Every slice includes its affected adapters, tests, translations, UI evidence and document reconciliation.
Paths in the ownership notes are edit surfaces, not new cross-context domain dependencies.

## T55

**Independent stock purchase tracer.** A Manager selects an active household, creates a cycleless
basket, adds a stock line, validates it and records physical collection as a separate action. This
thin path runs through Sales, Catalog, Stock, Wallet, storage, HTTP and reviewable staff UI.

Source: [Sales](../plan.md#sales), [Wallet](../plan.md#wallet),
[transaction seam](../plan.md#validatebasket-the-transactional-seam), R19–R23b/R28/R30/R31.

Acceptance:

- Basket exists without an Order/Cycle. Draft line addition checks mode/quantity/combined availability
  under the Catalog product lock, snapshots the product and reserves/charges nothing. Remove/cancel
  retains history; Catalog's usage query includes removed lines/cancelled Sales drafts immediately.
- Validation atomically records immutable contents/total/actor/time, stock sale and one Wallet
  charge/credit by basket source. Recheck stock under ordered locks; negative global balance is allowed.
  Use line-level half-up rounding. Zero totals have no movement; repeated/concurrent validation has
  one financial/stock effect. A confirmed receipt remains an independent operation.
- Manager collection records actor/time on a validated basket, repeat is a no-op preserving the first
  record, with no ledger writes. Original fields cannot be edited after validation. Existing inactive
  lines retain their snapshot and may validate; new/increased inactive lines are forbidden.
- Wallet statements identify the basket and optional cycle; source indexes admit multiple purchases
  for the same household. Add Sales architecture/repository contracts. Keep the old weekly writer
  isolated until T59; never expose old and new writers for the same weekly purchase.
- Register or extend Sales in `architecture/modules.php` with its delivered basket table ownership,
  exact integration exports/DTOs, permitted callers and dependencies, including the Stock/Wallet
  permissions needed by this tracer. Preserve Ordering permissions still required by the isolated
  weekly flow until T59; Stock keeps inventory ownership and Wallet keeps ledger ownership. Update
  the module-contract documentation and relevant rejection tests, then run `tests/Architecture`.
  This adds no dependency on T54.

Verify S18/S21/S22/S45/S79/S81, Sales history in S87 and S72's unit/variable guard after removed/cancelled basket use. Repository tests cover active-line uniqueness,
archived references, immutable snapshot reload and sale/charge source uniqueness. PostgreSQL integration
proves rollback after an injected stock/wallet failure, two baskets competing for final units, replay,
and multiple-product additions in opposite order. HTTP denies members/producers Finance actions.

Ownership/reuse: Sales context and ports/adapters, basket/line/collection persistence, Stock sales,
Wallet basket sources, Finance basket create/list/show routes initially Manager-gated, statement links.
Refactor existing pickup stock/content/total patterns into shared basket presentation; T56 adds Finance
access, not another financial writer. Show draft→validated→collected and insufficient-stock states.

## T56

**Finance role and global manual receipts.** A Manager grants/removes Finance on an existing
account; that person can operate the T55 basket flow and record confirmed cash/bank receipts globally.

Source: [Membership](../plan.md#membership), [Wallet](../plan.md#wallet),
[HTTP and areas](../plan.md#http--pages), R19a/R21/R29–R32/R36a/R36c.

Acceptance:

- Explicit Finance is an effective login role without household/producer. Member remains derived;
  Manager retains every Finance capability. Grant/revoke is Manager-only and takes effect on requests.
- Central Actor checks, middleware, Inertia role areas and navigation agree. Finance can access basket
  operations and required household statements, not catalogue/cycle/reception/account or general
  inventory administration. No configurable commissions/commission CRUD is introduced.
- Staff confirms money received before recording a positive receipt. Credit the global Wallet at
  recorded time without basket/cycle allocation. A receipt can cover several charges or leave credit;
  validation failure never undoes it. Receipt correction is append-only with non-zero amount, original
  source, actor/time/reason. No payment gateway or paid/part-paid basket field.

Verify S84/S85, S24 and S19's independent-receipt part with Finance-only and combined roles, direct
unauthorized requests, role removal, own-household privacy and unchanged last-Manager safeguards.
Test receipt before/after validation, failure, several baskets, negative balance, correction replay and
original movement immutability. Reuse `Manage/Accounts/Index`, `Wallet/Statement` and receipt forms;
check narrow Finance navigation and translated confirmation/error states.

Ownership: Membership role commands/storage, Shared Actor/capabilities, HTTP area/navigation and
Wallet receipt/correction surfaces, plus Finance access to the existing Sales tracer.

## T57

**Basket contents, returns and member view.** Widen the independent purchase flow with all draft
stock/return actions and own-household read-only basket contents/status.

Source: [Basket model](../plan.md#basket-aggregate-and-history), R19a/R19b/R20/R20a/R23a/R24a.

Acceptance:

- Repeated stock additions use the first active snapshot and check combined stock; removal archives,
  re-add takes a new line/snapshot. Inactive existing lines survive and can be removed/validated but
  cannot be added/increased. An inactive producer does not block active stock sales.
- Staff adds/removes positive whole return quantities with return-item snapshots. Catalogue editing
  remains Manager-only. Returns reduce the total; return-only credit and real-content net-zero/free
  baskets validate correctly, with no zero Wallet movement. An empty stock draft can be cancelled
  without posting money/stock; retain its references.
- Member sees only their household's baskets, actual contents, preparation/validation/collection
  state, immutable originals/adjustments, total, balance and statement link. They cannot edit contents
  or validate; self-collection arrives in T61. Do not label a draft ready for collection.

Verify S16's stock addition, S17/S41/S55/S75, S76's sale boundary and S88's returns/real-content cases.
S20/S88's weekly nothing-to-collect revival is completed by T59. Test snapshots after catalogue edits,
removed/archived lines, zero/negative rounding, whole/fraction steps and forged cross-household IDs.
Reuse `StockLines`, `ReturnLines` and shared detail patterns; check empty, deactivated line, credit,
net-zero, long-name and member permission states.

Ownership: Sales draft/return commands, DTO/repository contracts, stock/return ports, Finance draft
controls and `Baskets/Index`/`Show`. No independent stock reservation flow.

## T58

**Append-only basket corrections.** Staff resolves an error in a validated independent purchase
without overwriting the original sale, receipt or collection record.

Source: [Corrections](../plan.md#collection-and-corrections), R23/R23b/R24a/R27/R32.

Acceptance:

- Reasoned actor/time/source-linked signed adjustments preserve original lines, validation time,
  validated total and posted movements. Distinguish amount-only corrections from goods physically
  returned; do not silently restore stock. Corrective stock/money effects are one transaction,
  remain non-negative and replay once by correction ID.
- A mistaken collection mark appends staff correction history, never a charge/refund. A full justified
  contents reversal leaves no effective goods pending collection. Members can see adjustment history
  but cannot correct; Finance does not gain unrelated inventory-adjustment powers.
- Expose the narrow signed weekly-adjustment interface T59 will call, with public Application tests
  of effective quantities/amounts. This slice verifies corrections through actual stock purchases;
  it does not yet move Ordering's weekly source. Preserve T56 receipt-correction behavior.

Verify S86 and receipt-correction S85 regressions, partial/repeated/full corrections, retained originals,
rollback on a stock constraint failure, unauthorized requests and a corrected collection replay.
UI presents original versus effective content and requires a reason, reusing existing `Adjustments`
and Wallet patterns. No original-row update is used as an audit substitute.

Ownership: Sales corrective/collection events and effective read models, Stock/Wallet corrective ports,
unique correction references, Finance correction UI and member read-only history.
