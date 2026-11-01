# 001 — Catalogue task details

Part of the [canonical registry](../tasks.md); dependencies live there. Status: **accepted (2026-10-28)**.
Authoritative behavior: [spec](../spec.md) and its areas; technical contracts: [plan](../plan.md).
Every slice includes its affected adapters, tests, translations, UI evidence and document reconciliation.
Paths in the ownership notes are edit surfaces, not new cross-context domain dependencies.

## T52

**Unified producer catalogue tracer.** Deliver a Manager-created stock product from a producer's
record, visible in stock administration and usable by the existing stock ledger, backed by the single
Catalog Product. This is the first end-to-end review surface, not a table-only ticket.

Source: [Catalog](../plan.md#catalog), [Persistence](../plan.md#persistence-postgres),
[ordering rules](../ordering.md#catalog), [stock rules](../stock-wallet.md#stock).

Acceptance:

- Product has immutable producer identity and stock mode; weekly and stock versions are distinct IDs.
  Manager creates/administers stock; own-producer administration remains weekly-only. Move stock
  metadata ownership out of Stock and update its offer/ledger references in the same slice.
- Weekly selectors/mutations, per-cycle availability, reception and allocation reject stock products
  server-side; inventory entries/adjustments reject weekly products. Preserve the existing return-item
  catalogue and stock non-negativity. General inventory remains Manager-only.
- Fresh schema/factories/repositories and forms use Catalog IDs. First-reference writers take the
  Catalog lock so later safe-edit guards can share it. Do not expose classification/deletion controls
  before T53. No duplicate product metadata or production conversion job.

Verify S65, S66's separate records/weekly exclusion, S71, S76's inventory boundary and S77 through
public use cases and HTTP permissions; contract-test IDs/producer/snapshots in both repositories.
Regress weekly availability, producer deactivation and reception of inactive weekly products
(S5/S32/S48/S50/S57). Stock with an inactive producer remains eligible for its later Sales path.

Ownership/reuse: Catalog product interfaces/adapters, Stock metadata adapters, Ordering catalogue
ports, fresh referencing migrations, producer-context Manager routes and existing product forms
(`StockProductFields`, `Producer/Products`, `Manage/Stock`). Test the actual creation→stock-view
path with Catalan copy and the required narrow/desktop visual cases.

Implementation (DRA-207): Manager stock metadata actions use
`/manage/producers/{producer}/products/...`, with producer identity checked on every action;
the stock index and movement ledger remain under `/manage/stock/...`. Stock reads and locks
Catalog Product through its own port; Catalog alone persists product metadata. The
[visual check](../../../docs/visual-checks/dra-207.md) covers the five affected Manager views.

## T53

**Retained history and protected catalogue edits.** Deliver unused-only reclassification/deletion
and permanent stock unit protection without adding a product-used marker or usage ledger.

Source: [Catalog domain](../plan.md#domain-and-administration),
[weekly history](../plan.md#weekly-model), R1/R1b/R1c/R4b/R9/R19b/R25a.

Acceptance:

- Order line removal/zeroing and pre-send cycle cancellation retain references; current totals,
  selectors, proportional claims and cancelled summaries exclude archived records. Re-add creates
  a new line ID/time, preserving the allocation tie-break. Retain existing pickup stock-line history
  during the temporary pre-cutover flow; T55 extends the same query to Sales baskets.
- Manager alone changes an unused classification; producer identity never changes. Only the current
  mode's administrator deletes an unused product. Used records can be deactivated, never deleted.
  Administration transfers when an unused mode changes.
- Derive use from retained Ordering/reception/Stock references; include Sales once T55 exists.
  Stock unit/variable edits lock forever after stock use, including zero remaining quantity or removed
  references. Weekly unit/variable edits keep the unsettled-active-line rule. Other snapshots stay intact.
- First-use writes and protected edits/deletion serialize on the same product lock. History foreign
  keys cannot cascade away use. Failed attempts create no committed reference.

Verify S67–S70, S72's movement/current-line cases and S87's removed-order/cancelled-cycle cases;
T55 completes removed/cancelled Sales cases. Include archived filtering and re-add repository contracts,
S37 cancellation/S62 no-totals regressions, tie-break S31 and concurrent first reference versus
reclassification, unit change and deletion. Show permitted/forbidden actions through current forms.

Ownership/reuse: Catalog usage/read ports and protected commands, Ordering removal/cancellation
and repositories, existing stock-line retention, product form actions/confirmations and HTTP tests.
No separate audit database or ledger duplicates this history.

## T54

**Read-only stock discovery.** Deliver a member stock catalogue and own-producer stock visibility,
independent of weekly cycle dates, participation and producer activation.

Source: [Catalogue interface](../plan.md#application-interface),
[Sales stock offers](../plan.md#sales), R2/R25b/R25c/R28.

Acceptance:

- Member sees active stock products with producer/name/unit/current price/available quantity;
  zero quantity is visible and labelled sold out, inactive products hidden. No reservation, order
  mutation or basket edit action is exposed or accepted.
- Producer sees only their own stock records and quantities read-only alongside editable own weekly
  products. Multiple-role accounts retain each area’s permissions; producer mode does not grant stock
  mutation. Batch quantity reads avoid recursive Catalog↔Stock composition or one query per product.
- Use Sales' query-only `ListStockOffers` surface where planned; do not add an empty basket facade
  or depend on unbuilt purchase commands. T55 later uses the same offers contract.
- When introducing the Sales query-only surface, register or extend Sales in
  `architecture/modules.php` with only the delivered read exports, DTOs, permitted callers and
  dependencies. Keep any required new table ownership explicit; do not predeclare
  basket tables or purchase commands. Update the module-contract documentation and relevant rejection
  tests, then run `tests/Architecture`. This adds no dependency on T55.

Verify S73/S74 and S66 visibility with zero stock, inactive producer/product, no cycle, ownership and
attempted forged mutations. Confirm reading does not write/reserve stock. Exercise the member
`Stock/Index` and producer list empty/populated/sold-out states with existing layouts and components.

Ownership: read-only Sales/Catalog composition adapters, Stock quantity query, stock/producer pages,
role-aware navigation and permissions. After T52 only: shared navigation/forms with T53 are scheduling
contention, not a behavioral blocker.
