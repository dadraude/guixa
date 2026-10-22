# 001 — Weekly orders · Plan

Status: Accepted (2026-10-21) · Spec: [spec.md](spec.md) + areas (R1–R36b, S1–S61) · Rules: [constitution](../constitution.md)

## Bootstrap

- Laravel 13 **Vue starter kit** (Inertia 3 + Vue 3 + TS + Vite, Tailwind 4 + shadcn-vue, Fortify auth). Public registration disabled.
- Postgres via `DB_CONNECTION=pgsql`; a separate `guixa_test` database for integration tests.
- `composer.json` autoloads `Guixa\` → `src/`.
- Dev tooling: `spatie/laravel-typescript-transformer`, Pest arch tests (A1–A8), Oxlint rules (A9), Vitest,
  Pint, Larastan, Oxfmt. Vite+ (`vp`) runs Oxlint, Oxfmt and Vitest.
- GitHub Actions workflow on pull requests: Pint `--test`, Larastan, `vp check` (Oxlint + Oxfmt), vue-tsc,
  Pest against a Postgres service, Vitest.
- `MAIL_MAILER=log` in `phpunit.xml`; Mailpit for local mail (invitations, D15).
- Laravel scheduler running every minute (`schedule:work` in dev, cron in production) for D10.
- `php artisan guixa:create-manager {name} {email}` creates the first manager at installation (R36a, D17).

## Bounded contexts

| Context | Owns | Spec |
| --- | --- | --- |
| `Shared` | `Money`, `Quantity`, `Clock`, `Actor`, `TransactionRunner` | Time |
| `Membership` | Households, accounts and their roles, invitations | R33–R36b |
| `Catalog` | Producers, products, default cutoff rule | R1–R3a, R5 (rule) |
| `Ordering` | Pickup weekday, cycles, participation, cutoffs, availability, orders, sending, reception, distribution, pickups | R3b–R24b |
| `Stock` | Stock products, return items, stock movements | R25–R28 |
| `Wallet` | Wallet movements and balance | R29–R32 |

```
Ordering ──port ProductCatalog──────▶ Catalog\Application    (orderable product + snapshot, ownership)
Ordering ──port ProducerCutoffRules─▶ Catalog\Application    (default rule, producer active)
Ordering ──port StockCatalog────────▶ Stock\Application      (stock product snapshot, available quantity)
Ordering ──port ReturnItemCatalog───▶ Stock\Application      (return item snapshot)
Ordering ──port StockSeller─────────▶ Stock\Application      (record a sale when a pickup is completed)
Ordering ──port WalletLedger────────▶ Wallet\Application     (charge or credit, top-up at pickup)
Ordering ──port ProducerNotifier────▶ Membership\Application (producer account email, for sending)
Ordering ──port Households──────────▶ Membership\Application (household name and active, for allocations and pickups)
Catalog  ──port ProducerCycles──────▶ Ordering\Application   (lines in unsettled cycles, stop participating)
Catalog  ──port ProducerAccess──────▶ Membership\Application (suspend or restore the producer role)
Membership ─port ProducerDirectory──▶ Catalog\Application    (does the producer exist, for `InviteProducerAccount`)
Membership ─port HouseholdBalances──▶ Wallet\Application     (to-settle list)
Membership ─port HouseholdPickups───▶ Ordering\Application   (pending pickups, to-settle list)
```

Ports live in the caller's `Domain`; their adapters live in the caller's `Infrastructure\<OtherContext>` and
only call the other context's use cases (rule A4). Every write that spans contexts runs inside one
`TransactionRunner` call, so it is all or nothing (R22).

## Shared

- **`Money`** `final readonly`: `int $cents`. `add`, `subtract`, `negate`, `multiply(Quantity): Money` rounding
  **half up** to the cent (R22); a negative amount (a credit) rounds by magnitude, so
  `negate()` before or after `multiply` gives the same cents. Built with `Money::ofCents()`. Formatting is a UI concern.
- **`Quantity`** `final readonly`: integer thousandths (`1.120 kg` → `1120`). Rejects negatives; `isZero()`,
  `isWhole()`, `ceilToWhole()` (R18a). Built with `Quantity::fromString('1.120')` (at most 3 decimals) or
  `Quantity::ofThousandths(1120)`; a negative or malformed value throws `Shared\Domain\Error\InvalidQuantity`.
  Stored as `numeric(10,3)`; mapped through strings, never floats.
- **`Clock`** port (`Shared\Domain`, so Domain code can use it under A1): `now(): DateTimeImmutable` and `timezone(): DateTimeZone` (the cooperative's timezone).
  `SystemClock` (Infrastructure, reads `config('guixa.timezone')` ← `COOP_TIMEZONE`) and `FrozenClock` (`tests/Support`,
  any timezone). `LocalTime::at(date, HH:MM, timezone)` resolves a local time to one instant: a time that does
  not exist moves forward by the gap, a repeated one takes its first occurrence (Time, S38).
- **`Actor`** (`Shared\Application`; `Role` and `NotAllowed` live in `Shared\Domain`) `final readonly`: `accountId` (UUID v7 string), `roles` (set of `Manager|Member|Producer`), `?householdId`,
  `?producerId`. Built by an HTTP middleware from the authenticated account and passed to every use case.
  Authorization is checked inside use cases (`assertManager()`, `assertMemberOf($householdId)`…),
  so scenarios test it without HTTP (R36a). A failed check throws `Shared\Domain\Error\NotAllowed`.
- **`TransactionRunner`** port (`Shared\Application`): `run(callable)` and `afterCommit(callable)`. DB adapter
  wraps `DB::transaction` and `DB::afterCommit`. Emails (sent orders, invitations) go out after commit, so a
  rolled-back write sends nothing.

## Membership

Domain
- `Household` (id, name, `?deactivatedAt`). Created active, even with no members (R35a).
- `Account` (id, name, email, roles, `?householdId`, `?producerId`, `?activatedAt`, `?deactivatedAt`).
  - A member belongs to at most one household (R35); moving relinks the same account; unlinking keeps it
    active without a household.
  - `grantManager` / `removeManager`: removing it from the last manager throws `LastManager` (R36a).
  - `deactivate`: unlinks from the household; the last active manager cannot be deactivated (R36b).
  - Login requires `activatedAt`, no `deactivatedAt`, and at least one effective role: a producer role counts
    only while its producer is active (R3a).
- `Invitation` (account id, SHA-256 hash of the token, `sentAt`): one per account, expires 7 days after it is
  sent (`now > sentAt + 7 days` is expired; 7 days means 168 elapsed hours, so the expiry is the same across a daylight-saving change and whatever timezone `sentAt` carries); issuing another replaces it, so resending or changing the email
  invalidates the previous link (R34). The plain token only exists in the email.
- `Email` value object: trimmed, lowercased, validated.
- Ports: `HouseholdRepository`, `AccountRepository`, `InvitationRepository`, `InvitationSender`, `AccountPasswords`
  (`change(accountId, plainPassword)`, so hashing stays in Infrastructure), `ProducerDirectory` (`exists(producerId)`, implemented
  by calling Catalog's `GetProducer`), `HouseholdBalances`, `HouseholdPickups`.

Use cases
| Use case | Actor | Covers |
| --- | --- | --- |
| `CreateHousehold`, `RenameHousehold` | manager | R33, S56 |
| `AddMember` → sends invitation (an inactive household is reactivated) | manager | R33, R34, R35a, S25 |
| `ResendInvitation`, `ChangeInvitationEmail` | manager | R34, S25 |
| `AcceptInvitation` (sets password, activates) | invitee | R34, S25 |
| `MoveMember` (an inactive target household is reactivated), `UnlinkMember` | manager | R35, R35a, S43 |
| `DeactivateHousehold` (returns the warning first), `ReactivateHousehold` | manager | R35a, S58 |
| `ListHouseholdsToSettle` | manager | R35a, S43, S58 |
| `ListHouseholds`, `GetHousehold` (members with pending invitation and its expiry) | manager | R33, R34 (admin pages) |
| `CanLogIn` (accepted invitation and not deactivated; the Fortify login adapter calls it after the password check) | — (login) | R34, R36b, S25 |
| `SendInvitation` (issues the invitation and emails it after commit; used by `AddMember`, `ResendInvitation`, `ChangeInvitationEmail`) | — (internal) | R34 |
| `InviteProducerAccount` (one per producer: an unknown producer throws `UnknownProducer`, a second account `ProducerAlreadyHasAccount`; creates a producer-role account and sends the usual invitation) | manager | R36, S57 |
| `GrantManagerRole`, `RemoveManagerRole` | manager | R36a, S44 |
| `DeactivateAccount`, `ReactivateAccount` | manager | R36b, S59 |
| `SuspendProducerRole`, `RestoreProducerRole` | — (called by Catalog) | R3a |
| `ListProducerAccounts` (producer id → account email and whether its invitation is pending; the producers admin page shows it and hides the invite form for producers that have one) | manager | R36, S57 |
| `GetProducerAccountEmail` | — (called by Ordering) | R12a |
| `GetHouseholds` (name, active) | — (called by Ordering) | R13, R16, R19 |
| `CreateFirstManager` (rejected once any manager exists) | — (installation command) | R36a |

A household is deactivated automatically when `MoveMember`, `UnlinkMember` or `DeactivateAccount` leaves it
without members (R35a). Deactivating it unlinks its members; its orders and pickups stay in Ordering.

Infrastructure: accounts are the `users` table through `App\Models\User` (A6 exception).
Households live in `households`; invitations in `invitations` (one row per account, `token_hash`, `sent_at`);
`users` gains `activated_at` and `deactivated_at`, and `users.household_id` references `households`.
`InvitationSender` emails the link `/invitations/{token}` (D15). Login (Fortify `authenticateUsing`) needs the right
password, `activated_at` set and `deactivated_at` empty; accepting an invitation also marks the email verified.
An account created by an invitation holds a random password nobody knows until the invitee sets theirs.

## Catalog

Domain
- `Producer` (id, name, active, `CutoffRule`).
- `CutoffRule` `final readonly` (in `Domain\ValueObject`): `daysBefore` (1–6) + `time` (`HH:MM`); anything else throws `InvalidCutoffRule`.
  `closesAt(pickupDate, timezone): DateTimeImmutable` subtracts the days from the bare date, then resolves the time through `LocalTime` (R5, S6, S38).
- `Product` (id, producerId, name, unit, `Money` price ≥ 0, variable, `?Quantity containersPerUnit` > 0,
  active). Unit and variable flag cannot change while the product has lines in an unsettled cycle
  (`ProducerCycles::hasUnsettledLines`, R1, S5).
- Ports: `ProducerRepository`, `ProductRepository`, `ProducerCycles`, `ProducerAccess`.

Use cases
| Use case | Actor | Covers |
| --- | --- | --- |
| `CreateProducer` (name and initial default cutoff, both required; the producer's account comes later through `InviteProducerAccount`) | manager | R36 |
| `ListProducers` (admin page: name, active, default cutoff) | manager | R36 |
| `GetProducer` (`?ProducerData`; the port behind Membership's `ProducerDirectory`) | — (internal query for Membership) | R36 |
| `DeactivateProducer`, `ReactivateProducer` | manager | R3a, S50 |
| `CreateProduct`, `UpdateProduct`, `DeactivateProduct`, `ReactivateProduct` | producer (own) | R1, R2, S5 |
| `SetDefaultCutoff` | producer (own) | R5 |
| `GetDefaultCutoff` (the producer's own rule, for its cutoff page) | producer (own) | R5 |
| `ListProducerProducts` | producer (own) | R2, S57 |
| `GetOrderableProduct`, `ListOrderableProducts` | — (internal queries for Ordering) | R3, R10 |
| `GetProducerCutoffRule` | — (internal query for Ordering) | R5 |

`DeactivateProducer`, in one transaction: `producer.deactivate()`, `ProducerAccess::suspend(producerId)`
and `ProducerCycles::stopParticipatingWhereNotSent(producerId)` (R3a, R6c, D13). Reactivating restores the
role only; participation is not restored.

## Ordering

### Domain

- **`PickupWeekday`** setting (`CooperativeSettings` repository): the weekday every new cycle must fall on.
  Changing it does not touch open cycles (R4c, D11).

- **`Cycle`** aggregate (id, pickupDate, `?cancelledAt`, `?settledAt`, `?closedAt`,
  `producerOrders: producerId → ProducerOrder`).
  **`ProducerOrder`** (entity): producerId, participating, `?cutoff` (instant), `?sentAt`, `?validatedAt`,
  `?distributedAt`.
  - `open(id, pickupDate, weekday, today, participants)`: pickup date after today and on the weekday (R4, R4c).
  - `addParticipant(producerId, CutoffRule, now)`: until settled; the cutoff is computed once, and is null if
    that moment already passed (R4a, R5). `stopParticipating` / `participateAgain`: only while not sent (R6c).
  - `setCutoff(producerId, at, now)`: not sent (R6b), `at > now`, and
    `pickupDate − 6 days 00:00 ≤ at < pickupDate 00:00` (R6). `closeNow(producerId, now)`, `clearCutoff(producerId)`.
  - `isOpenFor(producerId, now)`: participating, not sent and `now < (cutoff ?? pickupDate 00:00)` (R6a, R8).
    `isOpenForMembers(now)`: any participating producer open (R4a).
  - `send(producerId, now)`: participating, closed, not sent (R12a, S36).
  - `validate(producerId)`; `clearValidation(producerId)` when a received total changes (R17a).
  - `markDistributed(producerId, balanced, now)`: sent, validated and balanced; settles the cycle the first
    time every participating producer is distributed and returns whether it did (R18).
  - `reopen(producerId)`: rejected once the cycle is closed (R18, R24b).
  - `cancel(now)`: rejected once any producer order is sent (R4b).
  - `close(now, everyPickupDone)`: settled, every participating producer distributed and every pickup done (R24b).

- **`Order`** aggregate — everything a household has in one cycle. Created on the first ordered line, on an
  allocation to a household without one (R16), or when a manager opens a pickup for a household without one
  (stock-only pickup, R19).
  - `orderLines`, keyed by productId. **`OrderLine`**: id, productId, producerId, name, unit, unitPrice,
    variable, `?containersPerUnit`, `ordered` (0 for an allocation-only line), `allocated`, `allocatedByHand`,
    `?delivered` (set only by a pickup adjustment; `delivered()` = `delivered ?? allocated`, D16),
    `createdAt`, `addedBy`, `notDelivered`, `dropped`. Lines marked `notDelivered` or `dropped` count in no
    total, claim, reception or container count.
  - `stockLines`, keyed by stockProductId. **`StockLine`**: name, unit, unitPrice, variable,
    `?containersPerUnit`, quantity.
  - `returnLines`, keyed by returnItemId. **`ReturnLine`**: name, amount, whole quantity (R20a).
  - `pickup`: `none | pending | completed | notCollected | nothingToCollect`, `?doneAt`, `?charged Money`.
  - Ordering: `setLine(OrderableProduct, Quantity, Cycle, actor, now)` checks the producer is open (R8), zero
    removes (R9), whole units for non-variable products (R9), snapshots name/unit/variable/price/containers
    (R10), sets `allocated = ordered`, and keeps `createdAt` unless the line is new (R15 tie). No balance
    check (R11). `removeMarkedLine` is allowed after the cutoff (R8).
  - Reception: `allocate(productId, Quantity)` marks `allocatedByHand` (R16); `allocateNew(product snapshot,
    Quantity)` adds an allocation-only line (R16, S39); `reallocate(productId, Quantity)` clears the mark (R15).
    All three clear `delivered`: a new allocation replaces a pending pickup's adjustment (R18).
  - Settlement: `drop(producerIds not participating)` drops those lines and the `notDelivered` ones, then the
    pickup becomes `pending`, or `nothingToCollect` if every allocation is 0 (R18, R24).
    `reopenIfAllocated()` turns `nothingToCollect` back into `pending` (R24).
  - Pickup: `adjustDelivered(productId, Quantity)` sets `delivered`, in the product's step; `allocated`
    stays as the reception left it (R19, D16);
    `addStock(StockOffer, Quantity)` checks `quantity ≤ available` (R20); `addReturn(ReturnItem, int)`;
    their removals.
  - `total(): Money` = Σ round(delivered() × unitPrice) + Σ round(quantity × unitPrice) − Σ quantity × amount,
    each line half up (R22). Negative is a credit.
  - `complete(now)` and `markNotCollected(now)` (drops stock and return lines, R24a) throw
    `PickupNotPending` unless pending (R23); every mutator throws once the pickup is done.

- **Will not be delivered** (R3b, R6c, D9): until a producer order is sent, the mark is derived when reading
  an order (product inactive, unavailable in the cycle, or producer not participating). `send` stores
  `notDelivered` on the marked lines of that producer, so later catalog changes do not affect them.

- **`ProductAvailability`**: `(cycleId, productId)` rows for products marked unavailable in a cycle; only while
  the producer order is not sent (R3c).

- **`ReceivedTotal`** (cycleId, producerId, productId, `Quantity`): absent means "equal to the ordered total";
  a manager adds one with 0 for a product nobody ordered (R14).

- **`ProportionalAllocator`** domain service (R15). Pure function, integer arithmetic only:
  ```
  allocate(target: Quantity, step: 1 unit | 0.001, claims: list<householdId, ordered, createdAt, lineId>)
    → map<householdId, Quantity>   // Σ result == target, always
  ```
  `share_i = target × ordered_i / Σ ordered`, floored to `step`; leftover steps go one by one to the
  largest remainders, ties by `createdAt`, then by line id (UUID v7, the record order). Exact on thousandths.
  `claims` are `ProportionalClaim` value objects (`Ordering\Domain\ValueObject`) (`householdId`, `ordered`, `createdAt`, `lineId`) and `step` is a
  `Quantity` (1.000 or 0.001). It works in steps, as `floor(T/S)·o + floor((T mod S)·o/S)` with S = Σ ordered, so no
  intermediate product exceeds (Σ ordered)². No claims or a zero ordered total throws `NobodyOrderedProduct`; a target or
  claim that is not a multiple of the step throws `InvalidArgumentException` (a caller bug).

- **`Reception`** read model (not an aggregate): per product of a producer in a cycle, `ordered total`,
  `received total`, every household's `allocated`, `delivered()` and `allocatedByHand`,
  `difference = received − Σ delivered()` and `balanced` (shown and flagged, R17, R19), and
  `balancedForDistribution`: `received − Σ allocated` is 0, so pickup adjustments do not count (R18, S61);
  plus the producer order's validated and distributed flags (R17a, R18).

- **`ContainerCount`** read model: per household, Σ ceil(delivered() × containersPerUnit) over order lines plus
  Σ ceil(quantity × containersPerUnit) over stock lines; and the cycle total (R18a, S60).

- Errors (`Ordering\Domain\Error`): `NotOnPickupWeekday`, `PickupDateNotInFuture`, `CycleAlreadyExists`,
  `CycleCancelled`, `CycleAlreadySettled`, `CycleClosed`, `ProducerNotParticipating`, `ProducerClosed`,
  `ProducerOrderSent`, `ProducerOrderNotSent`, `ProducerStillOpen`, `CutoffOutOfRange`, `ProductNotOrderable`,
  `FractionalQuantityNotAllowed`, `NoHousehold`, `ReceptionNotValidated`, `ReceptionUnbalanced`,
  `ManualAllocationsWouldBeLost`, `NobodyOrderedProduct`, `CycleNotSettled`, `NotEnoughStock`,
  `PickupNotPending`, `HouseholdInactive`.

- Ports: `CooperativeSettings`, `CycleRepository`, `OrderRepository`, `ProductAvailabilityRepository`,
  `ReceivedTotalRepository`, `ProductCatalog`, `ProducerCutoffRules`, `StockCatalog`, `ReturnItemCatalog`,
  `StockSeller`, `WalletLedger`, `ProducerNotifier`, `Households`.

### Use cases

| Use case | Actor | Covers |
| --- | --- | --- |
| `SetPickupWeekday` | manager | R4c, S37 |
| `OpenCycle` (with participants) | manager | R4, R4a, R5, S6, S38, S51 |
| `AddParticipant`, `StopParticipating`, `ParticipateAgain` | manager | R4a, R6c, S50, S51 |
| `SetCutoff`, `CloseProducerNow`, `ClearCutoff` | manager | R6, R6a, R6b, S6, S34, S35 |
| `CancelCycle` | manager | R4b, S37 |
| `SetProductAvailability` | producer (own) | R3c, S48 |
| `SetOrderLine` (quantity 0 removes) | member of household | R3, R7–R11, S1–S5, S33, S49, S51 |
| `RemoveMarkedLine` | member of household | R3b, R8, S32 |
| `GetHouseholdOrder` (with *will not be delivered* marks and, once settled, the pickup) | member of household | R3b, R7, R23a, S4 |
| `ListMemberCycles` (open, pending, cancelled, closed) | member | R4a, R23a |
| `SendProducerOrder` → notifies the producer | manager | R12a, R6b, S34, S36 |
| `SendEmptyProducerOrders` (scheduled) | — (scheduler) | R12a, D10 |
| `GetProducerCycleSummary` | producer (own) | R12, S7, S8 |
| `GetCycleSummary` (totals + per household) | manager | R13, S52 |
| `HasUnsettledLines`, `StopParticipatingWhereNotSent` | — (called by Catalog) | R1, R3a |
| `ListPendingPickups` | — (called by Membership) | R35a |
| `GetReception` | manager | R14, R17 |
| `AddUnorderedProduct` | manager | R14, S40 |
| `SetReceivedTotal(overwriteManual?)` → runs `ProportionalAllocator` | manager | R15, R17a, S10–S12, S31 |
| `SetAllocation` | manager | R16, R17, S13, S39, S40 |
| `ValidateReception` | manager | R17a, S47 |
| `MarkProducerDistributed` (settles the cycle when it is the last) | manager | R18, R24, S13, S20, S27–S29, S47, S61 |
| `ReopenProducerOrder` | manager | R18, S29, S30, S46 |
| `GetContainerCount` | manager | R18a, S60 |
| `OpenPickup` (household without an order) | manager | R19, S18 |
| `GetPickup` (lines, stock and return lines, balance, total, resulting balance) | manager | R19, R21, S27 |
| `AdjustDeliveredQuantity` | manager | R19, S14 |
| `AddStockToPickup`, `RemoveStockFromPickup` | manager | R20, S17, S55 |
| `AddReturnToPickup`, `RemoveReturnFromPickup` | manager | R20a, S41 |
| `CompletePickup(topUp?)` | manager | R21–R23, S9, S14–S16, S18, S19, S21, S22, S26, S41, S45, S53, S55 |
| `MarkPickupNotCollected` | manager | R24a, S42 |

`SetReceivedTotal(cycle, product, received)`:
1. The producer order is sent and not distributed (R14, R18); the product was sent or added (R14, R15).
2. If any line of the product is `allocatedByHand` and `overwriteManual` is not true, throw
   `ManualAllocationsWouldBeLost` listing those households; nothing is stored. The page shows the
   warning and resubmits with `overwriteManual=true` on confirm, or does nothing on cancel (R15, S31).
3. Claims = every household that ordered the product and whose pickup is not done, with its `ordered`
   quantity; `target = received − Σ allocated of done pickups` (D12), so the reception stays balanced for
   distribution whatever done pickups delivered (D16). Before settlement no pickup is done.
4. `reallocate` each line with the result, store the `ReceivedTotal` and clear the validation (R17a).

`SetAllocation` also requires a sent, not distributed producer order and an active household (`Households`,
`HouseholdInactive`), and rejects a done pickup.

`OpenPickup` requires a settled, not closed cycle (`CycleNotSettled`, `CycleClosed`) and an active household
with no order in it (R19, R24b).

`MarkProducerDistributed(cycle, producer)`, in one transaction with the cycle `FOR UPDATE`:
1. Build the `Reception`; `cycle.markDistributed(producerId, reception.balancedForDistribution, now)`.
2. `CycleSettlement::afterDistributed(cycle, settledNow)`, an Application service:
   - if this settled the cycle: every order `drop(nonParticipating)` (R18, R24);
   - if the cycle was already settled (a reopen): every `nothingToCollect` order `reopenIfAllocated()` (R24, S30);
   - `cycle.close(...)` if every pickup is done (R24b).

`SendEmptyProducerOrders` (D10), per producer order whose cutoff passed with no line, in one transaction
with the cycle `FOR UPDATE`: `send`, `validate`, `markDistributed(producerId, true, now)`, then the same
`CycleSettlement::afterDistributed`, so a cycle settled by the scheduler drops lines and closes like any other.

`CompletePickup(cycle, household, topUp?)`, in one transaction:
1. Load the cycle `FOR SHARE` and assert it is settled and not closed; load the order `FOR UPDATE`;
   `order.complete(now)`.
2. For each stock line, in stock product id order, `StockSeller::sell(stockProductId, quantity, orderId)` — locks the stock product
   and fails with `NotEnoughStock` if it went below the quantity since it was added (R22, R27, S45).
3. `WalletLedger::topUp(...)` if any, then `WalletLedger::charge(household, cycle, total)`: a negative total
   is a credit, 0 records nothing (R21, R22).
4. `cycle.close(...)` if this was the last pickup (R24b).
Any failure rolls everything back. `MarkPickupNotCollected` follows steps 1, 3 (no top-up) and 4.

## Stock

Domain
- **`StockProduct`** (id, name, unit, `Money` price ≥ 0, variable, `?Quantity containersPerUnit`, active) (R25).
- **`ReturnItem`** (id, name, `Money` amount > 0, active) (R25).
- **`StockMovement`** `final readonly`: id, stockProductId, type (`Entry|Adjustment|Sale`), signed `Quantity`,
  occurredAt, recordedBy, `?note`, `?cycleId`, `?orderId`. Invariants: entry > 0; adjustment ≠ 0 with a
  non-empty note; sale < 0 with an orderId (R26–R28).
- `available = Σ movements`; a movement that would make it negative is rejected (R27).
- Ports: `StockProductRepository`, `ReturnItemRepository`, `StockMovementRepository` (append-only).
- `StockProduct` and `ReturnItem` are mutable entities with a private constructor, `create` and `restore`; `change`
  re-checks every rule, so create and update share them. Rule breaches throw `NameRequired`, `UnitRequired`,
  `InvalidPrice`, `InvalidContainersPerUnit` (zero or not a valid quantity) and `InvalidReturnAmount`.
  Only deactivation exists (no reactivation).

Use cases
| Use case | Actor | Covers |
| --- | --- | --- |
| `CreateStockProduct`, `UpdateStockProduct`, `DeactivateStockProduct` | manager | R25 |
| `CreateReturnItem`, `UpdateReturnItem`, `DeactivateReturnItem` | manager | R25 |
| `ListStockProducts`, `ListReturnItems` (inactive ones included, by name) | manager | R25 |
| `RecordStockEntry` | manager | R26, S23 |
| `RecordStockAdjustment` (optional cycle) | manager | R27, S23 |
| `ListStock` (with available quantity) | manager | R28 |
| `GetStockOffer`, `GetReturnItem` | — (internal queries for Ordering) | R20, R20a |
| `SellStock` | — (called by Ordering) | R22, R28 |

## Wallet

Domain
- **`Movement`** `final readonly`: id, householdId, type (`TopUp|Charge|Credit|Correction`), signed `Money`
  amount, occurredAt, recordedBy, `?note`, `?cycleId`. Invariants: top-up > 0 with no cycle; charge < 0 and
  credit > 0, both with a cycleId; correction ≠ 0 with a non-empty note (R29, R32).
- Port: `MovementRepository` — append-only: `add`, `balanceOf`, `statementOf`. No update, no delete (R32).
- A deactivated household's wallet still accepts top-ups and corrections (R35a).

Use cases
| Use case | Actor | Covers |
| --- | --- | --- |
| `RecordTopUp` | manager (also called by Ordering at pickup) | R29, S19, S24 |
| `RecordCorrection` (optional cycle) | manager | R32, S24, S54 |
| `ChargeForPickup` (charge, credit or nothing) | — (called by Ordering) | R22, R30 |
| `GetBalance` | — (internal query) / manager | R21, R35a |
| `GetWalletStatement` | member of household / manager | R30, R31, S21 |

## Persistence (Postgres)

All ids are UUID v7 generated by repositories (`nextId()`); timestamps are `timestamptz` in UTC.

| Table | Columns | Constraints |
| --- | --- | --- |
| `cooperative_settings` | id, pickup_weekday | single row: `CHECK (id = 1)`, `CHECK (pickup_weekday BETWEEN 1 AND 7)` |
| `households` | id, name, deactivated_at? | |
| `users` | id, name, email, password?, household_id?, producer_id?, activated_at?, deactivated_at? | email unique; producer_id unique (R36) |
| `account_roles` | user_id, role | `PK (user_id, role)` |
| `invitations` | account_id, token_hash, sent_at | `PK (account_id)` (one live invitation per account); token_hash unique |
| `producers` | id, name, active, cutoff_days_before, cutoff_time (`time`) | `CHECK (cutoff_days_before BETWEEN 1 AND 6)`; `users.producer_id` references it |
| `products` | id, producer_id, name, unit, price_cents, variable, containers_per_unit?, active | `CHECK (price_cents >= 0)`; `CHECK (containers_per_unit > 0)` |
| `cycles` | id, pickup_date, cancelled_at?, settled_at?, closed_at? | partial `UNIQUE (pickup_date) WHERE cancelled_at IS NULL` |
| `cycle_producers` | cycle_id, producer_id, participating, closes_at?, sent_at?, validated_at?, distributed_at? | `PK (cycle_id, producer_id)` |
| `product_unavailabilities` | cycle_id, product_id | `PK (cycle_id, product_id)` |
| `orders` | id, cycle_id, household_id, pickup_status, pickup_done_at?, charged_cents? | `UNIQUE (cycle_id, household_id)` |
| `order_lines` | id, order_id, product_id, producer_id, product_name, unit, unit_price_cents, variable, containers_per_unit?, ordered_quantity, allocated_quantity, allocated_by_hand, delivered_quantity?, created_at, added_by, not_delivered, dropped | `UNIQUE (order_id, product_id)`; `CHECK (ordered_quantity >= 0)`; `CHECK (allocated_quantity >= 0)`; `CHECK (delivered_quantity >= 0)` |
| `received_totals` | cycle_id, producer_id, product_id, received_quantity | `PK (cycle_id, product_id)`; `CHECK (received_quantity >= 0)` |
| `order_stock_lines` | id, order_id, stock_product_id, name, unit, unit_price_cents, variable, containers_per_unit?, quantity | `UNIQUE (order_id, stock_product_id)`; `CHECK (quantity > 0)` |
| `order_return_lines` | id, order_id, return_item_id, name, amount_cents, quantity | `UNIQUE (order_id, return_item_id)`; `CHECK (quantity > 0)` |
| `stock_products` | id, name, unit, price_cents, variable, containers_per_unit?, active | `CHECK (price_cents >= 0)`; `CHECK (containers_per_unit > 0)` |
| `return_items` | id, name, amount_cents, active | `CHECK (amount_cents > 0)` |
| `stock_movements` | id, stock_product_id, type, quantity, occurred_at, recorded_by, note?, cycle_id?, order_id? | `CHECK` type ↔ sign/note/order_id |
| `wallet_movements` | id, household_id, type, amount_cents, occurred_at, recorded_by, note?, cycle_id? | partial `UNIQUE (household_id, cycle_id) WHERE type IN ('charge', 'credit')` — second line of defence for R23 |

Concurrency:
- Every `Cycle` mutation loads the cycle `FOR UPDATE`. `SetOrderLine` loads it `FOR SHARE` and the order
  `FOR UPDATE`, so a cutoff change or a send cannot slip in while a line is being saved (R6b, R8).
- `SetReceivedTotal` and settlement lock every affected order of the cycle, in id order (no deadlocks).
- `ReopenProducerOrder` locks the cycle `FOR UPDATE` and `CompletePickup` `FOR SHARE`, so a cycle cannot
  close and reopen at once (R18, R24b).
- An order is created with `INSERT … ON CONFLICT (cycle_id, household_id) DO NOTHING` and then loaded
  `FOR UPDATE`, so two members adding a household's first lines at once share one order.
- `SellStock` and stock adjustments lock the `stock_products` row, so availability is checked under the lock.
  `CompletePickup` sells its stock lines in stock product id order, so two pickups selling the same
  products cannot deadlock.
- `RemoveManagerRole` and `DeactivateAccount` lock the manager accounts, so two of them cannot remove the
  last manager together (R36a, R36b).

## HTTP & pages

Middleware `role:<role>` (any of several: `role:manager,producer`) gates route groups; use cases still check the `Actor`. One login can reach several
role areas (R36a); the layout shows a switch between them.

| Route | Page / action | Use case |
| --- | --- | --- |
| `GET /` | `Orders/Index` | `ListMemberCycles` |
| `GET /cycles/{cycle}/order` | `Orders/Edit` (order, then pickup once settled) | `GetHouseholdOrder`, `ListOrderableProducts` |
| `PUT /cycles/{cycle}/order/lines/{product}` | partial reload of `order` prop | `SetOrderLine` |
| `DELETE /cycles/{cycle}/order/lines/{product}` | partial reload | `RemoveMarkedLine` |
| `GET /wallet` | `Wallet/Statement` | `GetWalletStatement` |
| `GET /producer/products` … | `Producer/Products/*` | Catalog CRUD |
| `GET/PUT /producer/cutoff` | `Producer/Cutoff` | `GetDefaultCutoff`, `SetDefaultCutoff` |
| `GET /producer/cycles/{cycle}` | `Producer/CycleSummary` | `GetProducerCycleSummary` |
| `PUT /producer/cycles/{cycle}/products/{product}/availability` | partial reload | `SetProductAvailability` |
| `PUT /manage/settings/pickup-weekday` | `Manage/Settings` | `SetPickupWeekday` |
| `GET/POST /manage/cycles` | `Manage/Cycles/Index` | `OpenCycle` |
| `GET /manage/cycles/{cycle}` | `Manage/Cycles/Show` (totals, producers, containers) | `GetCycleSummary`, `GetContainerCount` |
| `DELETE /manage/cycles/{cycle}` | — | `CancelCycle` |
| `POST/DELETE /manage/cycles/{cycle}/producers/{producer}` | — | `AddParticipant`, `ParticipateAgain`, `StopParticipating` |
| `PUT/DELETE …/producers/{producer}/cutoff`, `POST …/close` | — | `SetCutoff`, `ClearCutoff`, `CloseProducerNow` |
| `POST …/producers/{producer}/send` | — | `SendProducerOrder` |
| `GET /manage/cycles/{cycle}/receptions/{producer}` | `Manage/Reception` | `GetReception` |
| `POST …/receptions/{producer}/products` | partial reload | `AddUnorderedProduct` |
| `PUT …/receptions/{producer}/products/{product}/received` | partial reload | `SetReceivedTotal` |
| `PUT …/receptions/{producer}/products/{product}/allocations/{household}` | partial reload | `SetAllocation` |
| `POST …/receptions/{producer}/validate` | partial reload | `ValidateReception` |
| `POST/DELETE …/receptions/{producer}/distribution` | — | `MarkProducerDistributed`, `ReopenProducerOrder` |
| `GET /manage/cycles/{cycle}/pickups` | `Manage/Pickups/Index` (pending / done) | `GetCycleSummary` |
| `GET/POST /manage/cycles/{cycle}/pickups/{household}` | `Manage/Pickups/Show` | `GetPickup`, `OpenPickup` |
| `PUT …/pickups/{household}/lines/{product}` | partial reload | `AdjustDeliveredQuantity` |
| `POST/DELETE …/pickups/{household}/stock-lines[/{stockProduct}]` | partial reload | `AddStockToPickup`, `RemoveStockFromPickup` |
| `POST/DELETE …/pickups/{household}/return-lines[/{returnItem}]` | partial reload | `AddReturnToPickup`, `RemoveReturnFromPickup` |
| `POST …/pickups/{household}/complete`, `…/not-collected` | redirect to pickups index | `CompletePickup`, `MarkPickupNotCollected` |
| `/manage/stock…`, `/manage/return-items…` | `Manage/Stock/{Index,Create,Edit}`, `Manage/ReturnItems/{Index,Create,Edit}` | Stock CRUD (T24); entries and adjustments (T25) |
| `/manage/households…`, `/manage/producers…`, `/manage/accounts…` | `Manage/Households/*`, `Manage/Producers/*`, `Manage/Accounts/*` | Membership + Catalog |
| `GET/POST /manage/producers`, `POST /manage/producers/{producer}/account` | `Manage/Producers/Index` (list with each producer's account email and invitation state, create form, invite form only for producers without an account) | `ListProducers` + `ListProducerAccounts` (the controller joins them into `ProducerRow`), `CreateProducer`, `InviteProducerAccount` |
| `POST /manage/households/{household}/wallet/{top-ups,corrections}` | — | `RecordTopUp`, `RecordCorrection` |

The pickup page follows the steps the manager works through: check the household's lines (from the
reception), adjust what is needed, add stock and returns, enter a top-up if they pay, complete — or mark it
not collected. Each step before completion is saved on the server, so a pickup can be left and resumed.

Controllers map domain errors in one place: an exception handler turns `Guixa\*\Domain\Error\*`
into a 422 with a translated message (`errors.<ClassName>` in `lang/`, else the exception message), so Vue
shows it as a form error: JSON requests get the 422, Inertia requests a redirect back with a `domain` error. `Shared\Domain\Error\NotAllowed`
becomes a 403 instead.

Inertia prop DTOs live in `App\Http` and are named `<Noun>Row` (e.g. `StockProductRow`, `HouseholdRow`); their TypeScript types are generated.

Client-side logic (Vitest): order running total, pickup resulting balance, both display-only.

## Testing

| Level | What | Where |
| --- | --- | --- |
| Domain | Value objects, `LocalTime`, `CutoffRule`, `ProportionalAllocator` (property: Σ result == target), `Cycle`, `Order` | `tests/Unit/<Context>` |
| Application | **Every scenario S1–S61**, with in-memory repositories and `FrozenClock` | `tests/Application/<Context>` |
| Infrastructure | Each Eloquent repository against `guixa_test` (constraints, locks, numeric mapping) | `tests/Integration` |
| HTTP | Role gates, wiring, `assertInertia` props, error → 422 and `NotAllowed` → 403 mapping | `tests/Feature` |
| Architecture | A1–A8 | `tests/Architecture` |
| Frontend | Display totals, A9 via Oxlint | `resources/js/**/*.spec.ts` |

In-memory repositories live in `tests/Support/InMemory` and must pass the same contract tests
as the Eloquent ones, so the Application tests stay trustworthy. Each port has one
`<Noun>RepositoryContract` trait in `tests/Support/Contracts`; the in-memory test (PHPUnit `TestCase`) and the
Eloquent test (`Tests\TestCase` with `RefreshDatabase`) both use it and only implement `repository()`. In-memory repositories extend
`InMemoryRepository` (id-keyed store, `nextId()` returning UUID v7). `ProbeNoteRepository` shows the shape.

## Decisions taken in this plan

- **D7** Stock is not reserved when added to a pickup; it is checked when added and again, under lock,
  when the pickup is completed. Stock lines are part of the `Order` aggregate until then.
- **D9** *Will not be delivered* is derived on read until the producer order is sent, then stored on the
  line by `send`. Nothing has to react to catalog or participation changes before that (R3b, R6c).
- **D10** Producer orders that close with no line to send are sent, validated and distributed by a command
  the scheduler runs every minute (`SendEmptyProducerOrders`), which can settle the cycle through the same
  `CycleSettlement` step as `MarkProducerDistributed`. A cutoff passes
  without any request, so nothing else would notice it (R12a).
- **D11** The pickup weekday is a single-row `cooperative_settings` table, not configuration: R4c lets a
  manager change it. It is still one value per installation, as the constitution allows for settings a
  manager changes in the app.
- **D12** Re-allocating after settlement (a reopened producer) leaves completed and not collected pickups
  as they were: their allocations are subtracted from the received total before splitting the rest (R18).
- **D13** Producer deactivation is a Catalog use case that calls Membership (producer role) and Ordering
  (participation) through ports, in one transaction (R3a).
- **D14** Cancelling a cycle deletes its orders and lines and keeps the cycle with `cancelled_at`, so members
  still see it as cancelled (R4b).
- **D15** Invitations are their own `Invitation` record with a 7-day expiry, not Laravel's password broker; a new
  token replaces the previous one, so resending or changing the email invalidates the old link (R34). The broker's
  expiry is shared with password resets and it reads the real clock, so S25's expiry could not be tested with
  `FrozenClock` at the Application level.
- **D16** An order line keeps the reception's `allocated` and, apart, the `delivered` quantity a pickup
  adjustment sets. The charge, what members see and the container count use `delivered()`; the reception
  shows and flags the difference it makes, but distributing checks `allocated` only (R18, R19, S61).
  A new allocation (way A or B, after a reopen) clears a pending pickup's adjustment.
- **D17** The first manager is created by an Artisan command calling `CreateFirstManager`, which sends the
  usual invitation and is rejected once any manager exists (R36a: "outside the app").

Dropped: D1 (pickup is a sequence of saved steps, R19–R22). D2 and D3 became spec rules (R4a, R6). D4 was
replaced by R35 and R36b (unlinking keeps the account; deactivation is separate). D5, D6 and D8 became spec
rules (R18, R15 warning, R15 tie rule).
