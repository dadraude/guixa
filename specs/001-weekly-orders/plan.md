# 001 — Weekly orders · Plan

Status: Draft · Spec: [spec.md](spec.md) + areas (R1–R36, S1–S31) · Rules: [constitution](../constitution.md)

## Bootstrap

- Laravel 12 **Vue starter kit** (Inertia + Vue 3 + TS + Vite, auth scaffolding). Public registration disabled.
- Postgres via `DB_CONNECTION=pgsql`; a separate `guixa_test` database for integration tests.
- `composer.json` autoloads `Guixa\` → `src/`.
- Dev tooling: `spatie/laravel-typescript-transformer`, Pest arch tests (A1–A8), ESLint rules (A9), Vitest.

## Bounded contexts

| Context | Owns | Spec |
| --- | --- | --- |
| `Shared` | `Money`, `Quantity`, `Clock`, `Actor`, `TransactionRunner` | — |
| `Membership` | Households, accounts (members, producers, managers), invitations | R33–R36 |
| `Catalog` | Producers, products, default cutoff rule | R1–R3, R5 (rule) |
| `Ordering` | Cycles, cutoffs, orders, reception (received totals + allocations), pickup | R4–R24 |
| `Stock` | Stock products and stock movements | R25–R28 |
| `Wallet` | Wallet movements and balance | R29–R32 |

```
Ordering ──port ProductCatalog──────▶ Catalog\Application  (orderable product + price snapshot)
Ordering ──port ProducerCutoffRules─▶ Catalog\Application  (default rules when opening a cycle)
Ordering ──port StockCatalog────────▶ Stock\Application    (stock product, price, available quantity)
Ordering ──port StockSeller─────────▶ Stock\Application    (record a sale at pickup confirmation)
Ordering ──port WalletLedger────────▶ Wallet\Application   (charge and top-up at pickup confirmation)
```

Ports live in `Ordering\Domain`; their adapters live in `Ordering\Infrastructure\<OtherContext>` and only call
the other context's use cases (rule A4). Every write that spans contexts runs inside one `TransactionRunner`
call, so it is all or nothing (R22).

## Shared

- **`Money`** `final readonly`: `int $cents`. `add`, `subtract`, `negate`, `multiply(Quantity): Money` rounding
  **half up** to the cent (R22). Formatting is a UI concern.
- **`Quantity`** `final readonly`: integer thousandths (`1.120 kg` → `1120`). Rejects negatives; `isZero()`.
  Stored as `numeric(10,3)`; mapped through strings, never floats.
- **`Clock`** port: `now(): DateTimeImmutable` and `timezone(): DateTimeZone` (the cooperative's timezone).
  `SystemClock` (Infrastructure, reads `config('guixa.timezone')` ← `COOP_TIMEZONE`) and `FrozenClock` (tests,
  any timezone).
- **`Actor`** `final readonly`: `accountId`, `role` (`Manager|Member|Producer`), `?householdId`, `?producerId`.
  Built by an HTTP middleware from the authenticated user and passed to every use case.
  Authorization is checked inside use cases (`assertManager()`, `assertMemberOf($householdId)`…),
  so scenarios test it without HTTP.
- **`TransactionRunner`** port (`Shared\Application`): `run(callable)`. DB adapter wraps `DB::transaction`.

## Catalog

Domain
- `Producer` (id, name, active, `CutoffRule`).
- `CutoffRule` `final readonly`: `daysBefore` (0–6) + `time` (`HH:MM`).
  `closesAt(DateTimeImmutable $pickupDate, DateTimeZone $timezone): DateTimeImmutable`, computed in the
  cooperative's timezone and returned as an absolute instant (R5, S6).
- `Product` (id, producerId, name, unit, `Money` price, variable, active). Price > 0.
- Ports: `ProducerRepository`, `ProductRepository`.

Use cases
| Use case | Actor | Covers |
| --- | --- | --- |
| `CreateProducer` | manager | R36 (with Membership) |
| `CreateProduct`, `UpdateProduct`, `DeactivateProduct` | producer (own) | R1, R2 |
| `SetDefaultCutoff` | producer (own) | R5 |
| `ListProducerProducts` | producer (own) | R2 |
| `ListOrderableCatalog` | member | R3 |
| `GetOrderableProduct` | — (internal query for Ordering) | R3, R10 |
| `ListCutoffRules` | — (internal query for Ordering) | R5 |

## Ordering

### Domain

- **`Cycle`** (id, pickupDate, `cutoffs: producerId → DateTimeImmutable`).
  - `open(id, pickupDate, rules)` computes every cutoff (R5).
  - `isOpenFor(producerId, now)`: `now < cutoff`. A producer with no cutoff in this cycle
    (created after the cycle opened) is closed for it (D2).
  - `overrideCutoff(producerId, at)`: rejects `at >= pickupDate 00:00` (R6, D3).
  - `?distributedAt`, `hasConfirmedPickups` (R18).
    `markDistributed(now)`: throws `CutoffsPending` unless every cutoff has passed.
    `reopenDistribution()`: throws `DistributionLocked` once a pickup is confirmed.
    `assertDistributed()` / `assertNotDistributed()` guard pickups and receptions.

- **`Order`** aggregate — everything a household has in one cycle. Created on the first ordered line,
  or when a manager opens a pickup for a household without one (stock-only pickup, R19).
  - `orderLines`, keyed by productId. **`OrderLine`**: productId, producerId, name, unit, unitPrice,
    variable, `ordered`, `allocated`, `allocatedByHand`, `firstOrderedAt`.
  - `stockLines`, keyed by stockProductId. **`StockLine`**: stockProductId, name, unit, unitPrice, quantity.
  - `?pickedUpAt`, `?charged Money`.
  - Ordering: `setLine(OrderableProduct, Quantity, Cycle, now)` checks cutoff (R8), zero removes (R9),
    whole units for non-variable products (R9), snapshots name/unit/price (R10), and sets
    `allocated = ordered`. No balance check (R11).
  - Reception: `allocate(productId, Quantity)` sets one line's allocation and marks it `allocatedByHand` (R16);
    `reallocate(productId, Quantity)` sets it from way A and clears the mark (R15).
  - Pickup: `adjustDelivered(productId, Quantity)` = `allocate` at pickup time, so it counts as manual (R19);
    `addStock(StockOffer, Quantity)` checks `quantity ≤ available` (R20); `removeStock(stockProductId)`.
  - `total(): Money` = Σ round(allocated × unitPrice) + Σ round(quantity × unitPrice), per line, half up (R22).
  - `confirmPickup(?Money topUp, now)`: throws `NothingToConfirm` if total is zero and there is no top-up (R24),
    `PickupAlreadyConfirmed` if already confirmed (R23); otherwise freezes. Every mutator throws
    `PickupAlreadyConfirmed` once frozen.

- **`ReceivedTotal`** (cycleId, productId, `Quantity`): what a producer delivered. Absent means
  "equal to the ordered total" (R14).

- **`ProportionalAllocator`** domain service (R15). Pure function, integer arithmetic only:
  ```
  allocate(target: Quantity, step: 1 unit | 0.001, claims: list<householdId, ordered, firstOrderedAt>)
    → map<householdId, Quantity>   // Σ result == target, always
  ```
  `share_i = target × ordered_i / Σ ordered`, floored to `step`; leftover steps go one by one to the
  largest remainders, ties by `firstOrderedAt`. Computed on thousandths as integers, so it is exact.

- **`Reception`** read model (not an aggregate): per product of a producer in a cycle, `ordered total`,
  `received total`, every household's `allocated` and `allocatedByHand` flag, and
  `difference = received − Σ allocated` (R14, R17), plus whether the cycle is distributed (R18).
  Built from orders + received totals + cycle.

- Errors (`Ordering\Domain\Error`): `ProducerClosed`, `ProductNotOrderable`, `FractionalQuantityNotAllowed`,
  `CycleAlreadyExists`, `CutoffNotBeforePickup`, `ReceptionNotOpenYet`, `CutoffsPending`,
  `CycleAlreadyDistributed`, `CycleNotDistributed`, `DistributionLocked`, `ManualAllocationsWouldBeLost`,
  `NotEnoughStock`, `PickupAlreadyConfirmed`, `NothingToConfirm`.

- Ports: `CycleRepository`, `OrderRepository`, `ReceivedTotalRepository`, `ProductCatalog`,
  `ProducerCutoffRules`, `StockCatalog`, `StockSeller`, `WalletLedger`.

### Use cases

| Use case | Actor | Covers |
| --- | --- | --- |
| `OpenCycle` | manager | R4, R5, S6, S9 |
| `OverrideCutoff` | manager | R6, S6 |
| `SetOrderLine` (quantity 0 removes) | member of household | R7–R11, S1–S5 |
| `GetHouseholdOrder` | member of household | R7, S4 |
| `ListOpenCycles` | member | R4 |
| `GetProducerCycleSummary` | producer (own) | R12, S7, S8 |
| `GetCycleSummary` (totals + per household) | manager | R13 |
| `GetReception` | manager | R14, R17 |
| `SetReceivedTotal(overwriteManual?)` → runs `ProportionalAllocator` | manager | R15, S10–S12, S31 |
| `SetAllocation` | manager | R16, R17, S13 |
| `MarkCycleDistributed` | manager | R18, S27, S28 |
| `ReopenDistribution` | manager | R18, S29, S30 |
| `GetPickup` (lines, stock lines, balance, total, resulting balance) | manager | R19, R21, S27 |
| `AdjustDeliveredQuantity` | manager | R19, S14 |
| `AddStockToPickup`, `RemoveStockFromPickup` | manager | R20, S16–S18 |
| `ConfirmPickup(topUp?)` | manager | R21–R24, S9, S14–S22, S26 |

`SetReceivedTotal(cycle, product, received)`:
1. `cycle.assertNotDistributed()` (R18).
2. If any line of the product is `allocatedByHand` and `overwriteManual` is not true, throw
   `ManualAllocationsWouldBeLost` listing those households; nothing is stored. The page shows the
   warning and resubmits with `overwriteManual=true` on confirm, or does nothing on cancel (R15, S31).
3. Claims = every household that ordered the product, with its `ordered` quantity, and `target = received`.
   No pickup can exist yet, so nobody is excluded (R18, R19).
4. `reallocate` each line with the result and store the `ReceivedTotal`.

`SetAllocation` also starts with `cycle.assertNotDistributed()`. Every pickup use case starts with
`cycle.assertDistributed()` (R19).

`ConfirmPickup(cycle, household, topUp?)`, in one transaction:
1. Load the cycle `FOR SHARE` and assert it is distributed; load the order `FOR UPDATE`;
   `order.confirmPickup(topUp, now)`.
2. For each stock line, `StockSeller::sell(stockProductId, quantity, orderId)` — locks the stock product
   and fails with `NotEnoughStock` if it went below the quantity since it was added (R20, R27).
3. `WalletLedger::topUp(...)` if any, then `WalletLedger::charge(household, cycle, total)` (R21, R22).
Any failure rolls everything back.

## Stock

Domain
- **`StockProduct`** (id, name, unit, `Money` price, variable, active). Price > 0 (R25).
- **`StockMovement`** `final readonly`: id, stockProductId, type (`Entry|Adjustment|Sale`), signed `Quantity`,
  occurredAt, recordedBy, `?note`, `?orderId`. Invariants: entry > 0; adjustment ≠ 0 with a non-empty note;
  sale < 0 with an orderId (R26–R28).
- `available = Σ movements`; a movement that would make it negative is rejected (R27).
- Ports: `StockProductRepository`, `StockMovementRepository` (append-only).

Use cases
| Use case | Actor | Covers |
| --- | --- | --- |
| `CreateStockProduct`, `UpdateStockProduct`, `DeactivateStockProduct` | manager | R25 |
| `RecordStockEntry` | manager | R26, S23 |
| `RecordStockAdjustment` | manager | R27, S23 |
| `ListStock` (with available quantity) | manager | R28 |
| `GetStockOffer` | — (internal query for Ordering) | R20 |
| `SellStock` | — (called by Ordering) | R22, R28 |

## Wallet

Domain
- **`Movement`** `final readonly`: id, householdId, type (`TopUp|Charge|Correction`), signed `Money` amount,
  occurredAt, recordedBy, `?note`, `?cycleId`. Invariants: top-up > 0; charge ≤ 0 with a cycleId;
  correction ≠ 0 with a non-empty note (R29, R32).
- Port: `MovementRepository` — append-only: `add`, `balanceOf`, `statementOf`. No update, no delete (R32).

Use cases
| Use case | Actor | Covers |
| --- | --- | --- |
| `RecordTopUp` | manager (also called by Ordering at pickup) | R29, S19, S24 |
| `RecordCorrection` | manager | R32, S24 |
| `ChargeForPickup` | — (called by Ordering) | R22, R30 |
| `GetBalance` | — (internal query for Ordering) / manager | R21 |
| `GetWalletStatement` | member of household / manager | R30, R31, S21 |

## Membership

Domain
- `Household` (id, name). `Account` (id, name, email, role, `?householdId`, `?producerId`, `?activatedAt`,
  `?deactivatedAt`). A member account has exactly one household (R35); a producer account has exactly one producer.
- Ports: `HouseholdRepository`, `AccountRepository`, `InvitationSender`.

Use cases
| Use case | Actor | Covers |
| --- | --- | --- |
| `CreateHousehold` | manager | R33 |
| `AddMember` → sends invitation | manager | R33, R34, S25 |
| `RemoveMember` (deactivates the account; history kept) | manager | R35, D4 |
| `InviteProducerAccount` | manager | R36 |
| `AcceptInvitation` (sets password, activates) | invitee | R34, S25 |

Infrastructure: accounts are the `users` table through `App\Models\User` (A6 exception).
`InvitationSender` uses Laravel's password broker with a custom "set your password" notification.
Login rejects accounts without `activated_at` or deactivated (S25).

## Persistence (Postgres)

All ids are UUID v7 generated by repositories (`nextId()`); timestamps are `timestamptz` in UTC.

| Table | Columns | Constraints |
| --- | --- | --- |
| `households` | id, name | |
| `producers` | id, name, active, cutoff_days_before, cutoff_time | `CHECK (cutoff_days_before BETWEEN 0 AND 6)` |
| `users` | id, name, email, password?, role, household_id?, producer_id?, activated_at?, deactivated_at? | email unique; `CHECK` role ↔ household/producer consistency |
| `products` | id, producer_id, name, unit, price_cents, variable, active | `CHECK (price_cents > 0)` |
| `cycles` | id, pickup_date, distributed_at? | `UNIQUE (pickup_date)` |
| `cycle_cutoffs` | cycle_id, producer_id, closes_at, overridden | `PK (cycle_id, producer_id)` |
| `orders` | id, cycle_id, household_id, picked_up_at?, charged_cents? | `UNIQUE (cycle_id, household_id)` |
| `order_lines` | id, order_id, product_id, producer_id, product_name, unit, unit_price_cents, variable, ordered_quantity, allocated_quantity, allocated_by_hand, first_ordered_at | `UNIQUE (order_id, product_id)`; `CHECK (ordered_quantity > 0)`; `CHECK (allocated_quantity >= 0)` |
| `received_totals` | cycle_id, product_id, received_quantity | `PK (cycle_id, product_id)`; `CHECK (received_quantity >= 0)` |
| `order_stock_lines` | id, order_id, stock_product_id, name, unit, unit_price_cents, quantity | `UNIQUE (order_id, stock_product_id)`; `CHECK (quantity > 0)` |
| `stock_products` | id, name, unit, price_cents, variable, active | `CHECK (price_cents > 0)` |
| `stock_movements` | id, stock_product_id, type, quantity, occurred_at, recorded_by, note?, order_id? | `CHECK` type ↔ sign/note/order_id |
| `wallet_movements` | id, household_id, type, amount_cents, occurred_at, recorded_by, note?, cycle_id? | partial `UNIQUE (household_id, cycle_id) WHERE type = 'charge'` — second line of defence for R23 |

Concurrency:
- `SetOrderLine`, `SetAllocation`, `AdjustDeliveredQuantity` and `ConfirmPickup` load the order `FOR UPDATE`.
- `SetReceivedTotal` locks every order of the cycle that has the product, in id order (no deadlocks).
- `ReopenDistribution` locks the cycle `FOR UPDATE` and `ConfirmPickup` `FOR SHARE`, so a reopen cannot
  slip in while a pickup is being confirmed (R18).
- `SellStock` and stock adjustments lock the `stock_products` row, so availability is checked under the lock.

## HTTP & pages

Middleware `role:<role>` gates route groups; use cases still check the `Actor`.

| Route | Page / action | Use case |
| --- | --- | --- |
| `GET /` | `Orders/Index` | `ListOpenCycles` |
| `GET /cycles/{cycle}/order` | `Orders/Edit` | `GetHouseholdOrder`, `ListOrderableCatalog` |
| `PUT /cycles/{cycle}/order/lines/{product}` | partial reload of `order` prop | `SetOrderLine` |
| `GET /wallet` | `Wallet/Statement` | `GetWalletStatement` |
| `GET /producer/products` … | `Producer/Products/*` | Catalog CRUD |
| `PUT /producer/cutoff` | `Producer/Cutoff` | `SetDefaultCutoff` |
| `GET /producer/cycles/{cycle}` | `Producer/CycleSummary` | `GetProducerCycleSummary` |
| `GET/POST /manage/cycles` | `Manage/Cycles/Index` | `OpenCycle` |
| `GET /manage/cycles/{cycle}` | `Manage/Cycles/Show` | `GetCycleSummary` |
| `PUT /manage/cycles/{cycle}/cutoffs/{producer}` | — | `OverrideCutoff` |
| `POST/DELETE /manage/cycles/{cycle}/distribution` | — | `MarkCycleDistributed`, `ReopenDistribution` |
| `GET /manage/cycles/{cycle}/receptions/{producer}` | `Manage/Reception` | `GetReception` |
| `PUT …/receptions/{producer}/products/{product}/received` | partial reload | `SetReceivedTotal` |
| `PUT …/receptions/{producer}/products/{product}/allocations/{household}` | partial reload | `SetAllocation` |
| `GET /manage/cycles/{cycle}/pickups` | `Manage/Pickups/Index` (pending / confirmed) | `GetCycleSummary` |
| `GET /manage/cycles/{cycle}/pickups/{household}` | `Manage/Pickups/Show` | `GetPickup` |
| `PUT …/pickups/{household}/lines/{product}` | partial reload | `AdjustDeliveredQuantity` |
| `POST/DELETE …/pickups/{household}/stock-lines[/{stockProduct}]` | partial reload | `AddStockToPickup`, `RemoveStockFromPickup` |
| `POST …/pickups/{household}/confirm` | redirect to pickups index | `ConfirmPickup` |
| `/manage/stock…` | `Manage/Stock/*` | Stock CRUD, entries, adjustments |
| `/manage/households…`, `/manage/producers…` | `Manage/Households/*`, `Manage/Producers/*` | Membership + Catalog |
| `POST /manage/households/{household}/wallet/{top-ups,corrections}` | — | `RecordTopUp`, `RecordCorrection` |

The pickup page follows the four steps the manager works through: check the household's lines
(from the reception), adjust what is needed, add stock, enter a top-up if they pay, confirm.
Each step before confirmation is saved on the server, so a pickup can be left and resumed.

Controllers map domain errors in one place: an exception handler turns `Guixa\*\Domain\Error\*`
into a 422 with a translated message, so Vue shows it as a form error.

Client-side logic (Vitest): order running total, pickup resulting balance, both display-only.

## Testing

| Level | What | Where |
| --- | --- | --- |
| Domain | Value objects, `CutoffRule`, `ProportionalAllocator` (property: Σ result == target), `Order` | `tests/Unit/<Context>` |
| Application | **Every scenario S1–S31**, with in-memory repositories and `FrozenClock` | `tests/Application/<Context>` |
| Infrastructure | Each Eloquent repository against `guixa_test` (constraints, locks, numeric mapping) | `tests/Integration` |
| HTTP | Role gates, wiring, `assertInertia` props, error → 422 mapping | `tests/Feature` |
| Architecture | A1–A8 | `tests/Architecture` |
| Frontend | Display totals, A9 via ESLint | `resources/js/**/*.spec.ts` |

In-memory repositories live in `tests/Support/InMemory` and must pass the same contract tests
as the Eloquent ones, so the Application tests stay trustworthy.

## Decisions taken in this plan

- **D2** A producer created after a cycle was opened is closed for that cycle; the manager can add
  them with `OverrideCutoff`.
- **D3** An overridden cutoff must be strictly before the pickup date at 00:00 (R6).
- **D4** Removing a member deactivates the account instead of deleting it (R35, R32 history).
- **D7** Stock is not reserved when added to a pickup; it is checked when added and again, under lock,
  at confirmation. Stock lines are part of the `Order` aggregate until then.

Dropped: D1 (pickup is now a sequence of saved steps, R19–R22). D5, D6 and D8 became spec rules
(R18, R15 warning, R15 tie rule).
