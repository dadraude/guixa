# 001 — Weekly orders · Plan

Status: Draft · Spec: [spec.md](spec.md) · Rules: [constitution](../constitution.md)

## Bootstrap

- Laravel 12 **Vue starter kit** (Inertia + Vue 3 + TS + Vite, auth scaffolding). Public registration disabled.
- Postgres via `DB_CONNECTION=pgsql`; a separate `guixa_test` database for integration tests.
- `composer.json` autoloads `Guixa\` → `src/`.
- Dev tooling: `spatie/laravel-typescript-transformer`, Pest arch tests (A1–A8), ESLint rules (A9), Vitest.

## Bounded contexts

| Context | Owns | Spec |
| --- | --- | --- |
| `Shared` | `Money`, `Quantity`, `Clock`, `Actor`, `TransactionRunner` | — |
| `Membership` | Households, accounts (members, producers, managers), invitations | R22–R25 |
| `Catalog` | Producers, products, default cutoff rule | R1–R3, R5 (rule) |
| `Ordering` | Cycles, per-cycle cutoffs, orders, lines, pickup | R4–R17 |
| `Wallet` | Movements and balance | R18–R21 |

```
Membership ──(account → household/producer ids)──▶ everyone, via Actor
Ordering ──port ProductCatalog────▶ Catalog\Application   (orderable product + price snapshot)
Ordering ──port ProducerCutoffRules▶ Catalog\Application  (default rules when opening a cycle)
Ordering ──port WalletCharger─────▶ Wallet\Application    (charge at pickup)
```

Ports live in `Ordering\Domain`; their adapters live in `Ordering\Infrastructure\<OtherContext>` and only call
the other context's use cases (rule A4).

## Shared

- **`Money`** `final readonly`: `int $cents`. `add`, `subtract`, `negate`, `multiply(Quantity): Money` rounding
  **half up** to the cent (R15). Formatting is a UI concern.
- **`Quantity`** `final readonly`: integer thousandths (`1.120 kg` → `1120`). Rejects negatives; `isZero()`.
  Stored as `numeric(10,3)`; mapped through strings, never floats.
- **`Clock`** port: `now(): DateTimeImmutable`. `SystemClock` (Infrastructure) and `FrozenClock` (tests).
- **`Actor`** `final readonly`: `accountId`, `role` (`Manager|Member|Producer`), `?householdId`, `?producerId`.
  Built by an HTTP middleware from the authenticated user and passed to every use case.
  Authorization is checked inside use cases (`Actor::assertManager()`, `assertMemberOf($householdId)`…),
  so scenarios test it without HTTP.
- **`TransactionRunner`** port (`Shared\Application`): `run(callable)`. DB adapter wraps `DB::transaction`.

## Catalog

Domain
- `Producer` (id, name, active, `CutoffRule`).
- `CutoffRule` `final readonly`: `daysBefore` (0–6) + `time` (`HH:MM`). `closesAt(DateTimeImmutable $pickupDate): DateTimeImmutable`
  in `Europe/Madrid` (R5, S10).
- `Product` (id, producerId, name, unit, `Money` price, variable, active). Price > 0.
- Ports: `ProducerRepository`, `ProductRepository`.

Use cases
| Use case | Actor | Covers |
| --- | --- | --- |
| `CreateProducer` | manager | R25 (with Membership) |
| `CreateProduct`, `UpdateProduct`, `DeactivateProduct` | producer (own) | R1, R2 |
| `SetDefaultCutoff` | producer (own) | R5 |
| `ListProducerProducts` | producer (own) | R2 |
| `ListOrderableCatalog` | member | R3 |
| `GetOrderableProduct` | — (internal query for Ordering) | R3, R10 |
| `ListCutoffRules` | — (internal query for Ordering) | R5 |

## Ordering

Domain
- **`Cycle`** (id, pickupDate, `cutoffs: producerId → DateTimeImmutable`).
  - `open(id, pickupDate, rules)` computes every cutoff (R5).
  - `isOpenFor(producerId, now)`: `now < cutoff`. A producer with no cutoff in this cycle
    (created after the cycle opened) is closed for it.
  - `overrideCutoff(producerId, at)`: rejects `at >= pickupDate 00:00` (R6).
- **`Order`** aggregate (id, cycleId, householdId, lines keyed by productId, `?pickedUpAt`, `?charged Money`).
  - `setLine(OrderableProduct, Quantity, Cycle, now)`: checks cutoff (R8), zero removes (R9),
    snapshots name/unit/price (R10). No balance check (R11).
  - `confirmPickup(deliveredByProduct, now): Money`: delivered defaults to ordered (R14), sums
    `line.unitPrice × delivered` rounded per line (R15), freezes the order. Second call throws (R16).
  - Every mutator throws `OrderAlreadyPickedUp` once frozen (R16).
- **`OrderLine`** `final readonly`: productId, producerId, name, unit, unitPrice, quantity.
- Errors (`Ordering\Domain\Error`): `ProducerClosed`, `OrderAlreadyPickedUp`, `ProductNotOrderable`,
  `CycleAlreadyExists`, `CutoffNotBeforePickup`, `NothingToPickUp`.
- Ports: `CycleRepository` (unique by pickup date), `OrderRepository`, `ProductCatalog`,
  `ProducerCutoffRules`, `WalletCharger`.

Use cases
| Use case | Actor | Covers |
| --- | --- | --- |
| `OpenCycle` | manager | R4, R5, S10, S13 |
| `OverrideCutoff` | manager | R6, S10 |
| `SetOrderLine` (quantity 0 removes) | member of household | R7–R11, S1–S5 |
| `GetHouseholdOrder` | member of household | R7, S4 |
| `ListOpenCycles` | member | R4 |
| `GetProducerCycleSummary` | producer (own) | R12, S11, S14 |
| `GetCycleSummary` (totals + per household) | manager | R13 |
| `GetPickupSheet` | manager | R14 |
| `ConfirmPickup` | manager | R14–R17, S6–S9, S13 |

`ConfirmPickup` runs inside `TransactionRunner`: freeze order + `WalletCharger::charge(householdId, cycleId, amount)`.
If either fails, nothing is stored. A household with no order or no lines gets `NothingToPickUp` (R17).

## Wallet

Domain
- **`Movement`** `final readonly`: id, householdId, type (`TopUp|Charge|Correction`), signed `Money` amount,
  occurredAt, recordedBy, `?note`, `?cycleId`. Invariants: top-up > 0; charge ≤ 0 with cycleId;
  correction ≠ 0 with a non-empty note (R18, R21).
- Port: `MovementRepository` — append-only: `add`, `balanceOf`, `statementOf`. No update, no delete (R21).

Use cases
| Use case | Actor | Covers |
| --- | --- | --- |
| `RecordTopUp` | manager | R18, S12 |
| `RecordCorrection` | manager | R21, S12 |
| `ChargeForPickup` | — (called by Ordering) | R15, R19 |
| `GetWalletStatement` | member of household / manager | R19, R20, S8 |

## Membership

Domain
- `Household` (id, name). `Account` (id, name, email, role, `?householdId`, `?producerId`, `?activatedAt`).
  A member account has exactly one household (R24); a producer account has exactly one producer.
- Ports: `HouseholdRepository`, `AccountRepository`, `InvitationSender`.

Use cases
| Use case | Actor | Covers |
| --- | --- | --- |
| `CreateHousehold` | manager | R22 |
| `AddMember` → sends invitation | manager | R22, R23, S15 |
| `RemoveMember` (deactivates the account; history kept) | manager | R24 |
| `InviteProducerAccount` | manager | R25 |
| `AcceptInvitation` (sets password, activates) | invitee | R23, S15 |

Infrastructure: accounts are the `users` table through `App\Models\User` (A6 exception).
`InvitationSender` uses Laravel's password broker with a custom "set your password" notification.
Login rejects accounts without `activated_at` or deactivated (S15).

## Persistence (Postgres)

All ids are UUID v7 generated by repositories (`nextId()`); timestamps are `timestamptz` in UTC.

| Table | Columns | Constraints |
| --- | --- | --- |
| `households` | id, name | |
| `producers` | id, name, active, cutoff_days_before, cutoff_time | `CHECK (cutoff_days_before BETWEEN 0 AND 6)` |
| `users` | id, name, email, password?, role, household_id?, producer_id?, activated_at?, deactivated_at? | email unique; `CHECK` role ↔ household/producer consistency |
| `products` | id, producer_id, name, unit, price_cents, variable, active | `CHECK (price_cents > 0)` |
| `cycles` | id, pickup_date | `UNIQUE (pickup_date)` |
| `cycle_cutoffs` | cycle_id, producer_id, closes_at, overridden | `PK (cycle_id, producer_id)` |
| `orders` | id, cycle_id, household_id, picked_up_at?, charged_cents? | `UNIQUE (cycle_id, household_id)` |
| `order_lines` | id, order_id, product_id, producer_id, product_name, unit, unit_price_cents, quantity, delivered_quantity? | `UNIQUE (order_id, product_id)`; `CHECK (quantity > 0)`; `CHECK (delivered_quantity >= 0)` |
| `wallet_movements` | id, household_id, type, amount_cents, occurred_at, recorded_by, note?, cycle_id? | partial `UNIQUE (household_id, cycle_id) WHERE type = 'charge'` — second line of defence for R16 |

Concurrency: `SetOrderLine` and `ConfirmPickup` load the order with `SELECT … FOR UPDATE` inside the
transaction, so two members editing at once (S4) or a double click on confirm (S9) cannot race.

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
| `GET /manage/cycles/{cycle}/pickups/{household}` | `Manage/Pickup` | `GetPickupSheet` |
| `POST /manage/cycles/{cycle}/pickups/{household}` | — | `ConfirmPickup` |
| `/manage/households…`, `/manage/producers…` | `Manage/Households/*`, `Manage/Producers/*` | Membership + Catalog |
| `POST /manage/households/{household}/wallet/{top-ups,corrections}` | — | `RecordTopUp`, `RecordCorrection` |

Controllers map domain errors to responses in one place: an exception handler turns
`Guixa\*\Domain\Error\*` into a 422 with a translated message, so Vue shows it as a form error.

Client-side logic (Vitest): order running total and pickup sheet total, both display-only.

## Testing

| Level | What | Where |
| --- | --- | --- |
| Domain | Value objects and aggregates (`Money` rounding, `CutoffRule`, `Order`) | `tests/Unit/<Context>` |
| Application | **Every scenario S1–S15**, with in-memory repositories and `FrozenClock` | `tests/Application/<Context>` |
| Infrastructure | Each Eloquent repository against `guixa_test` (constraints, `FOR UPDATE`, numeric mapping) | `tests/Integration` |
| HTTP | Role gates, wiring, `assertInertia` props, error → 422 mapping | `tests/Feature` |
| Architecture | A1–A8 | `tests/Architecture` |
| Frontend | Display totals, A9 via ESLint | `resources/js/**/*.spec.ts` |

In-memory repositories live in `tests/Support/InMemory` and must pass the same contract tests
as the Eloquent ones, so the Application tests stay trustworthy.

## Decisions taken in this plan

- **D1** One command `ConfirmPickup(deliveredByProduct)` records quantities and charges at once;
  there is no stored "draft delivery". Simpler and atomic.
- **D2** A producer created after a cycle was opened is closed for that cycle; the manager can add
  them with `OverrideCutoff`.
- **D3** An overridden cutoff must be strictly before the pickup date at 00:00 (R6).
- **D4** Removing a member deactivates the account instead of deleting it (R24, R21 history).
