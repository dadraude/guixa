# 001 — Weekly orders · Plan

Status: Accepted (2026-10-21) · Spec: [spec.md](spec.md) + areas (R1–R36b, S1–S61) · Rules: [constitution](../constitution.md)

Accepted amendment (2026-10-24): **Application visual identity** below. The user approved the plan;
the associated tasks were separately approved. The user subsequently authorized T39 implementation
on 2026-10-25. The T39 visual pilot was explicitly approved on DRA-191 on 2026-10-25;
T40 is implemented with [rendered evidence](../../docs/visual-checks/dra-191.md).
T41 is implemented for independent review with [rendered manager evidence](../../docs/visual-checks/dra-192.md).
T37/T38 reconciliation is complete: T37 = DRA-195 and T38 = DRA-194, both Done with evidence.
Their native prerequisite links to T39 / DRA-190 are recorded.

Accepted amendment (2026-10-26): **Layout rules** below, delivered by T43–T46 (DRA-197–DRA-200).
Implementation is not yet authorized.

## Bootstrap

- Laravel 13 **Vue starter kit** (Inertia 3 + Vue 3 + TS + Vite, Tailwind 4 + shadcn-vue, Fortify auth). Public registration disabled.
- Postgres via `DB_CONNECTION=pgsql`; a separate `guixa_test` database for integration tests.
- `composer.json` autoloads `Guixa\` → `src/`.
- Dev tooling: `spatie/laravel-typescript-transformer`, Pest arch tests (A1–A8), Oxlint rules (A9), Vitest,
  Pint, Larastan, Oxfmt. Vite+ (`vp`) runs Oxlint, Oxfmt and Vitest.
- GitHub Actions workflow on pull requests: Pint `--test`, Larastan, `vp check` (Oxlint + Oxfmt), vue-tsc,
  Pest against a Postgres service, Vitest.
- `MAIL_MAILER=log` in `phpunit.xml`; Mailpit for local mail (invitations, D15).
- Translations: `laravel-vue-i18n` and the `laravel-lang` Catalan files (dev dependency); `APP_LOCALE=ca` and
  `APP_FALLBACK_LOCALE=ca` (Translations, D18).
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
- **`Actor`** (`Shared\Application`; `Role` and `NotAllowed` live in `Shared\Domain`) `final readonly`: `accountId` (UUID v7 string), `roles` (set of `Manager|Member|Producer`; `Member` is derived: present exactly when `householdId` is set, never stored), `?householdId`,
  `?producerId`. Built by an HTTP middleware from the authenticated account and passed to every use case.
  Authorization is checked inside use cases (`assertManager()`, `assertMemberOf($householdId)`…),
  so scenarios test it without HTTP (R36a). `assertMemberOf($householdId)` checks that the account is linked to that household (R35). A failed check throws `Shared\Domain\Error\NotAllowed`.
- **`TransactionRunner`** port (`Shared\Application`): `run(callable)` and `afterCommit(callable)`. DB adapter
  wraps `DB::transaction` and `DB::afterCommit`. Emails (sent orders, invitations) go out after commit, so a
  rolled-back write sends nothing.

## Membership

Domain
- `Household` (id, name, `?deactivatedAt`). Created active, even with no members (R35a).
- `Account` (id, name, email, roles, `?householdId`, `?producerId`, `?activatedAt`, `?deactivatedAt`).
  - A member belongs to at most one household (R35); moving relinks the same account; unlinking keeps it
    active without a household.
  - `inviteManager` (the first manager: no household, cannot log in until the invitation is accepted, R36a); `AccountRepository::hasManager()` lets `CreateFirstManager` refuse once any manager exists.
  - `grantManager` / `removeManager`: removing it from the last manager throws `LastManager` (R36a).
  - `deactivate`: unlinks from the household; the last active manager cannot be deactivated (R36b). A deactivated
    account cannot be linked to a household until it is reactivated (`AccountDeactivated`); reactivating leaves it
    without a household.
  - "Last manager" counts active managers (holding the role, not deactivated): removing the role from the only
    active manager throws `LastManager`, deactivating the only active manager throws `LastActiveManager`.
  - Login requires `activatedAt`, no `deactivatedAt`, and at least one effective role: a household link (a member),
    the manager role, or a producer role, which counts only while its producer is active (R3a). `account_roles` holds
    only `manager` and `producer`; a migration deleted the stored `member` rows.
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
| `MoveMember` (an inactive target household is reactivated; it also links an unlinked account; moving to the current household does nothing), `UnlinkMember` | manager | R35, R35a, S43 |
| `GetHouseholdDeactivationWarning` (reads balance and pending pickups before the action), `DeactivateHousehold` (unlinks members and deactivates atomically), `ReactivateHousehold` | manager | R35a, S58 |
| `ListHouseholdsToSettle` | manager | R35a, S43, S58 |
| `ListHouseholds`, `GetHousehold` (members with pending invitation and its expiry) | manager | R33, R34 (admin pages) |
| `CanLogIn` (accepted invitation and not deactivated; the Fortify login adapter calls it after the password check) | — (login) | R34, R36b, S25 |
| `SendInvitation` (issues the invitation and emails it after commit; used by `AddMember`, `ResendInvitation`, `ChangeInvitationEmail`) | — (internal) | R34 |
| `InviteProducerAccount` (one per producer: an unknown producer throws `UnknownProducer`, a second account `ProducerAlreadyHasAccount`; creates a producer-role account and sends the usual invitation) | manager | R36, S57 |
| `GrantManagerRole`, `RemoveManagerRole` | manager | R36a, S44 |
| `DeactivateAccount`, `ReactivateAccount` | manager | R36b, S59 |
| `ListAccounts` (roles, household name, active, invitation pending) | manager | R36a, R36b (accounts admin page) |
| `SuspendProducerRole`, `RestoreProducerRole` | — (called by Catalog) | R3a |
| `ListProducerAccounts` (producer id → account email and whether its invitation is pending; the producers admin page shows it and hides the invite form for producers that have one) | manager | R36, S57 |
| `GetProducerAccountEmail` | — (called by Ordering) | R12a |
| `GetHouseholds` (household ids → name and active by id; unknown ids left out) | — (called by Ordering) | R13, R16, R19 |
| `CreateFirstManager` (rejected once any manager exists) | — (installation command) | R36a |

A household is deactivated automatically when `MoveMember`, `UnlinkMember` or `DeactivateAccount` leaves it
without members (R35a), through the shared `DeactivateEmptyHousehold` service. Deactivating it unlinks its members; its orders and pickups stay in Ordering.
Membership-link changes and household-state writes take one transaction-scoped Membership lock before reading accounts or households; account deactivation takes that lock before manager-row locks. A command re-reads its inputs after locking, so manual deactivation, add/move/unlink and reactivation serialize with current membership state.
`AccountRepository::save` keeps an existing account's stored household link while saving account details and roles; new accounts keep their initial link. Membership-changing commands persist the link separately through `saveHouseholdLink` under that lock, so a stale account-detail save cannot restore an old link.

Infrastructure: accounts are the `users` table through `App\Models\User` (A6 exception).
Households live in `households`; invitations in `invitations` (one row per account, `token_hash`, `sent_at`);
`users` gains `activated_at` and `deactivated_at`, and `users.household_id` references `households`.
`InvitationSender` emails the link `/invitations/{token}` (D15). Login (Fortify `authenticateUsing`) needs the right
password, `activated_at` set and `deactivated_at` empty; accepting an invitation also marks the email verified.
An account created by an invitation holds a random password nobody knows until the invitee sets theirs.
Passkey login does not go through `authenticateUsing`, so `Passkeys::authorizeLoginUsing` asks `CanLogIn` too, and the
`LogOutDeactivatedAccount` web middleware ends the session an account already had when it is deactivated (R36b).
`AccountRepository::lockManagers()` locks every manager row in id order (`SELECT … FOR UPDATE`) and `hasOtherActiveManager()`
reads them afterwards, in a new statement, so a waiting transaction sees the other one's commit.

## Catalog

Domain
- `Producer` (id, name, active, `CutoffRule`).
- `CutoffRule` `final readonly` (in `Domain\ValueObject`): `daysBefore` (1–6) + `time` (`HH:MM`); anything else throws `InvalidCutoffRule`.
  `closesAt(pickupDate, timezone): DateTimeImmutable` subtracts the days from the bare date, then resolves the time through `LocalTime` (R5, S6, S38).
- `Product` (id, producerId, name, unit, `Money` price ≥ 0, variable, `?Quantity containersPerUnit` > 0,
  active). Unit and variable flag cannot change while the product has lines in an unsettled cycle
  (`ProducerCycles::hasUnsettledLines`, R1, S5): `UpdateProduct` asks the port and passes the answer to `Product::change`, which throws `ProductHasUnsettledLines` when the unit or the variable flag would change. The port's adapter calls Ordering's `HasUnsettledLines`.
  Ownership is checked in each use case with `Actor::assertProducerOf(product.producerId)` (R2).
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
| `ListProducerProducts` (inactive ones included, by name; returns `ProductData`) | producer (own) | R2, S57 |
| `GetOrderableProduct` (`?OrderableProductData`: null unless the product and its producer are active), `ListOrderableProducts` (by producer id: its active products by name, empty when the producer is unknown or inactive) | — (internal queries for Ordering) | R3, R10 |
| `GetProducerCutoffRule` (`?ProducerCutoffRuleData`: active flag and the default rule) | — (internal query for Ordering) | R5 |

`DeactivateProducer`, in one transaction: `producer.deactivate()`, `ProducerAccess::suspend(producerId)`
and `ProducerCycles::stopParticipatingWhereNotSent(producerId)` (R3a, R6c, D13). Reactivating restores the
role only; participation is not restored.

`ProducerAccess` (`suspend`, `restore`; adapter `MembershipProducerAccess` calls Membership's `SuspendProducerRole` and
`RestoreProducerRole` by producer id). Suspending takes the `producer` row out of the account's `account_roles` and keeps
`users.producer_id`, so restoring puts it back; a producer with no account yet is left alone. `ProducerCycles` gains
`stopParticipatingWhereNotSent(producerId)`, whose adapter calls Ordering's use case of the same name.

## Ordering

### Domain

- **`PickupWeekday`** value object (ISO day 1–7) kept by the `CooperativeSettings` port: the weekday every new cycle must fall on.
  Changing it does not touch open cycles (R4c, D11). There is no default: it is null until a manager sets it (R4c); the `cooperative_settings` migration creates the row empty. `PickupWeekdayNotSet` and `PickupWeekdayAlreadySet` guard `OpenCycle`.
- **`CutoffRule`** (`Ordering\Domain\ValueObject`): Ordering's own copy of a producer's default rule (days before, time) with `closesAt`, since A4 forbids
  using Catalog's. The `ProducerCutoffRules` port hands it over; its Catalog adapter returns null for an unknown or deactivated producer.

- **`Cycle`** aggregate (id, pickupDate, `?cancelledAt`, `?settledAt`, `?closedAt`,
  `producerOrders: producerId → ProducerOrder`).
  **`ProducerOrder`** (entity): producerId, participating, `?cutoff` (instant), `?sentAt`, `?validatedAt`,
  `?distributedAt`.
  - `open(id, pickupDate, weekday, today, participants)`: pickup date after today and on the weekday (R4, R4c).
  - `addParticipant(producerId, CutoffRule, now)`: until settled (the settled state arrives with T20+); the cutoff is computed once, and is null if
    that moment already passed (R4a, R5). A producer already in the cycle is left as it is, so the call is idempotent and never recomputes. `stopParticipating` / `participateAgain`: only while not sent (R6c); a sent order is refused with `ProducerOrderSent`.
  - `setCutoff(producerId, at, now)`: not sent (R6b; a sent order is refused with `ProducerOrderSent`, also for `closeNow` and `clearCutoff`), `at > now`, and
    `pickupDate − 6 days 00:00 ≤ at < pickupDate 00:00` (R6). `closeNow(producerId, now)`, `clearCutoff(producerId)`.
  - `isOpenFor(producerId, now)`: participating, not sent and `now < (cutoff ?? pickupDate 00:00)` (R6a, R8).
    `isOpenForMembers(now)`: any participating producer open (R4a).
  - `send(producerId, now)`: not sent (`ProducerOrderSent`), participating (`ProducerNotParticipating`) and closed (`ProducerStillOpen`); stores `sentAt`, which freezes the cutoff and the participation (R6b, R6c, R12a, S36). `producersReadyToSend(now)` lists the participating, closed, unsent producers (D10); `totalsToSend(producerId, orders, marks)` is the ordered quantity per product over the unmarked lines, and `markedLines(...)` the marked ones per order (D9).
  - `assertSent(producerId)`: the producer is in the cycle (`ProducerNotInCycle`) and its order is sent (`ProducerOrderNotSent`), which a reception needs (R14).
  - `validate(producerId, now)`; `clearValidation(producerId)` when a received total changes (R17a, T23). `assertReceptionEditable(producerId)`: sent (`ProducerOrderNotSent`) and not distributed (`ProducerDistributed`), which `validate`, `markDistributed` and the three reception writers (`SetReceivedTotal`, `AddUnorderedProduct`, `SetAllocation`) call, so a distributed reception is read-only (R18).
  - `markDistributed(producerId, balanced, now)`: sent, not yet distributed, validated (`ReceptionNotValidated`) and balanced (`ReceptionUnbalanced`); settles the cycle the first
    time every participating producer is distributed and returns whether it did (R18). The cycle stores `settledAt`; the producer order stores `validatedAt` and `distributedAt`.
  - `reopen(producerId)`: only a distributed producer (`ProducerNotDistributed`), before or after settlement; a closed cycle is refused with `CycleClosed` (R18, R24b). Reopening keeps the validation, which only a changed received total clears (R17a).
  - `cancel(now)`: rejected with `ProducerOrderSent` once any producer order is sent (R4b, S37) and with `CycleCancelled` once already cancelled. A cancelled cycle refuses `addParticipant`, `setCutoff`, `closeNow`, `clearCutoff` and `assertOpenFor` with `CycleCancelled`, and `isOpenFor` is false.
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
  - Ordering: `setLine(OrderableProduct, Quantity, Cycle, lineId, addedBy, now)` checks the producer participates and is open
    (`Cycle::assertOpenFor`, R3, R8; `ProducerNotParticipating`, `ProducerClosed`), whole units for non-variable products (R9),
    snapshots name/unit/variable/price/containers (R10), sets `allocated = ordered`, and keeps `createdAt` and `addedBy` of an
    existing line (R15 tie). `removeLine(productId, Cycle, now)` is the zero quantity (R9): it checks the line's producer is open
    and leaves a product that is not in the order alone. `SetOrderLine` picks between them. No balance check (R11).
    `removeMarkedLine(productId, marked)` is allowed after the cutoff (R8); `marked` comes from `NotDeliveredMarks::of(cycle, order)`, it ignores a product that is not in the order and throws `LineNotMarked` for a line that is not marked.
  - T13 carries only the ordering part: `OrderLine` has id, productId, producerId, snapshot, `ordered`, `allocated`, `createdAt`, `addedBy`; the other
    fields come with the tasks that use them, though the `orders` and `order_lines` tables have every column.
  - Reception (T22): `allocate(productId, Quantity)` marks `allocatedByHand` (R16); `allocateNew(OrderableProduct, Quantity, lineId, addedBy, now)` adds an allocation-only line with `ordered` 0
    and the product as it is now (R16, S39); `reallocate(productId, Quantity)` clears the mark (R15). A non-variable product is allocated in whole units (`FractionalQuantityNotAllowed`).
    `OrderLine` gains `allocatedByHand` (default false) and `withAllocation(Quantity, byHand)`. `orderedLines()` are the lines with `ordered` > 0: the member's order page, `GetCycleSummary`, the totals to send and
    `lineCountsOfHousehold` use them, so an allocation-only line never shows as something a household ordered (R7, R12, R13).
    All three clear `delivered` once pickups exist: a new allocation replaces a pending pickup's adjustment (R18).
  - Settlement: `drop(marked)` takes the product ids `NotDeliveredMarks::of` marks (stored for a sent producer; derived for one that does not participate, so these lines are dropped too) and flags those lines `dropped`, then the
    pickup becomes `pending`, or `nothingToCollect` if every allocation of the lines left is 0 (R18, R24). An order with no line at all keeps pickup `none`. T23 knows `PickupStatus` `none`, `pending` and `nothingToCollect`; the done states arrive with the pickup tasks.
    `reopenIfAllocated()` turns `nothingToCollect` back into `pending` (R24).
  - Pickup: `openPickup()` (`none` to `pending`, `PickupAlreadyOpen` otherwise); `adjustDelivered(productId, Quantity)` sets `delivered` on a pending pickup (`PickupNotPending`, `ProductNotInPickup`), in the product's step; `allocated`
    stays as the reception left it (R19, D16);
    `addStock(StockOffer, Quantity, lineId)` checks `quantity ≤ available` (R20), refuses a deactivated product (`StockProductInactive`) and adds to a line
    already on the pickup, checking the combined quantity and keeping the line's snapshot; `addReturn(ReturnItemOffer, int, lineId)` does the same without a stock
    check and accepts a deactivated item; `removeStock(stockProductId)` and `removeReturn(returnItemId)` remove the whole line (`StockLineNotInPickup`,
    `ReturnLineNotInPickup`). All four need a pending pickup (`PickupNotPending`) and a quantity above zero (`InvalidLineQuantity`); see the 2026-10-25 Deviation in `log.md`.
  - `total(): Money` = Σ round(delivered() × unitPrice) + Σ round(quantity × unitPrice) − Σ quantity × amount,
    each line half up (R22). Negative is a credit.
  - `complete(now)` and `markNotCollected(now)` (drops stock and return lines, R24a) throw
    `PickupNotPending` unless pending (R23); every mutator throws once the pickup is done.

- **Will not be delivered** (R3b, R6c, D9): until a producer order is sent, the mark is derived when reading
  an order (product inactive, unavailable in the cycle, or producer not participating). `send` stores
  `notDelivered` on the marked lines of that producer, so later catalog changes do not affect them.

- **`ProductAvailability`**: `(cycleId, productId)` rows for products marked unavailable in a cycle; only while
  the producer order is not sent (R3c). `ProductAvailabilityRepository`: `markUnavailable`, `markAvailable`, `unavailableIn(cycleId)`.
- **`NotDeliveredMarks`** domain service (`ProductCatalog` + `ProductAvailabilityRepository`): `of(cycle, order)` returns the product ids of the marked lines: the cycle does not have the producer participating, the product is unavailable in the cycle, or `ProductCatalog::orderable` is null (R3b, R6c). `of` derives the mark while the producer order is not sent and reads the stored one (`OrderRepository::notDeliveredProductIds`) once it is, so a later reactivation does not lift it and a later deactivation does not add one; `derivedOf` is the derivation alone, which `send` uses to decide what to store with `OrderRepository::markNotDelivered` (D9).

- **`ReceivedTotal`** (cycleId, producerId, productId, `Quantity`): absent means "equal to the ordered total";
  a manager adds one for a product nobody ordered, with 0 unless given (R14). `ReceivedTotalRepository`: `set(ReceivedTotal)` replaces the product's total in the cycle; `ofProducer(cycleId, producerId): array<productId, Quantity>`.

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

- **`Reception`** read model (not an aggregate; T22 builds it in `Domain` with `Reception::of(producerId, orders, marksByOrder, receivedTotals, catalog)` from `ReceptionProduct` and `ReceptionLine`, and `ReceptionLoader` reads it once the producer order is sent): per product of a producer in a cycle, `ordered total`,
  `received total`, every household's `allocated`, `delivered()` and `allocatedByHand`,
  `difference = received − Σ delivered()` and `balanced` (shown and flagged, R17, R19), and
  `balancedForDistribution`: `received − Σ allocated` is 0, so pickup adjustments do not count (R18, S61); `Reception::balancedForDistribution()` is true when every product is;
  plus the producer order's validated and distributed flags (R17a, R18; they arrive with T23).
  T22 shows a product when it has a line that is not marked or a recorded `ReceivedTotal`; lines marked *will not be delivered* are left out, and a product with neither a line nor a recorded total is not shown, whether or not it is active.
  Until a pickup adjusts a line `delivered()` is `allocated`, so `difference` and `balancedForDistribution` agree; from T27 `ReceptionLine` carries `delivered`, and the reception page flags an adjusted product (`hasAdjustments`) and distributes by `balancedForDistribution`.

- **`ContainerCount`** read model: per household, Σ ceil(delivered() × containersPerUnit) over order lines plus
  Σ ceil(quantity × containersPerUnit) over stock lines; and the cycle total (R18a, S60).

- Errors (`Ordering\Domain\Error`): `NotOnPickupWeekday`, `PickupDateNotInFuture`, `CycleAlreadyExists`,
  `InvalidPickupWeekday`, `InvalidPickupDate`, `CycleNotFound`, `ProducerNotActive` (unknown or deactivated producer, S50),
  `CycleCancelled`, `CycleClosed`, `ProducerDistributed`, `ProducerNotDistributed`, `ProducerNotParticipating`, `ProducerClosed`,
  `ProducerOrderSent`, `ProducerOrderNotSent`, `ProducerStillOpen`, `ProducerHasNoAccount`, `ProductNotInReception`, `ProductAlreadyOrdered`, `HouseholdNotFound`, `LineMarkedNotDelivered`, `CutoffOutOfRange`, `ProductNotOrderable`, `LineNotMarked`,
  `FractionalQuantityNotAllowed`, `NoHousehold`, `ReceptionNotValidated`, `ReceptionUnbalanced`,
  `ManualAllocationsWouldBeLost`, `NobodyOrderedProduct`, `CycleNotSettled`, `NotEnoughStock`,
  `PickupNotPending`, `HouseholdInactive`.

- `Cycle` and `ProducerOrder` carry only the fields T11 needs (pickup date, participation, cutoff); cancelled, settled, closed, sent, validated and
  distributed come with the tasks that use them, though the tables have the columns. `CycleRepository` adds `getForUpdate` (the `FOR UPDATE` lock)
  and `existsOnPickupDate` (cancelled cycles do not count).
- `OrderRepository`: `nextId`, `add`, `ofHousehold(cycleId, householdId): ?Order`, `hasUnsettledLinesOf(productId)` (a line in a cycle with no `settled_at`, R1), `ofCycle(cycleId): list<Order>` (one query for the orders, one for all their lines), `lineCountsOfHousehold(householdId): array<cycleId, int>` (one grouped query, for the member cycle list) and `lockFor(cycleId, householdId): Order`, which creates the empty
  order if there is none and locks it (`INSERT … ON CONFLICT DO NOTHING`, then `FOR UPDATE`, R7). `ProductCatalog`: `orderable(productId): ?OrderableProduct`
  and `orderableOf(producerId)`, both with Ordering's own `OrderableProduct` value object (A4); its Catalog adapter calls `GetOrderableProduct` and `ListOrderableProducts`.
  `CycleRepository` adds `getForShare` (the `FOR SHARE` lock).
- Ports: `CooperativeSettings`, `CycleRepository`, `OrderRepository`, `ProductAvailabilityRepository`,
  `ReceivedTotalRepository`, `ProductCatalog`, `ProducerCutoffRules`, `StockCatalog`, `ReturnItemCatalog`,
  `StockSeller`, `WalletLedger`, `ProducerNotifier`, `Households`.
- `ProducerNotifier` (Ordering port): `canNotify(producerId)` (the producer has an account) and `notify(producerId, pickupDate, totals)`, with the totals as name, unit and `Quantity`; its Membership adapter reads the account email through `GetProducerAccountEmail` and sends a mail with the totals and no household names.
- `OrderRepository` adds `markNotDelivered(orderId, productIds)` and `notDeliveredProductIds(orderId)` (the `not_delivered` column); `CycleRepository` stores `sentAt` in `cycle_producers.sent_at`.
- `ProductCatalog` adds `allOf(producerId)`, which returns every product of the producer whatever its state and its producer's: Catalog's new internal query `ListProducerProductsForReception` (no actor, `OrderableProductData`) backs it, and `GetOrderableProduct` / `ListOrderableProducts` keep the R3 rules for ordering. The reception (`AddUnorderedProduct`, the options in `GetReception`, the snapshot of `SetAllocation`) uses them, since a product only has to be active when a household orders it (R14, R16).
- `Households` (Ordering port): `find(householdIds): array<id, HouseholdInfo>`, unknown ids left out; its Membership adapter calls `GetHouseholds`, which reads
  them with one `HouseholdRepository::getMany(ids)` query.

### Use cases

| Use case | Actor | Covers |
| --- | --- | --- |
| `SetPickupWeekday`, `GetPickupWeekday` (null while unset) | manager | R4c, S37 |
| `OpenCycle` (with participants and an optional `weekday` + `permanent` flag; the date is `YYYY-MM-DD`; returns the cycle id. While the cooperative has no weekday, a `weekday` must be given: permanent also saves it, in the same transaction, and provisional saves nothing. A `weekday` given when the cooperative has one is refused), `ListCycles` (`CycleData`: pickup date and participants with ISO 8601 cutoff in the cooperative's timezone) | manager | R4, R4a, R5, S6, S38, S51 |
| `AddParticipant`, `StopParticipating`, `ParticipateAgain` (a producer not in the cycle is refused with `ProducerNotInCycle`, a cancelled cycle with `CycleCancelled`, and a deactivated producer cannot participate again: `ProducerNotActive`; the cutoff it had is kept; both are idempotent) | manager | R4a, R6c, S50, S51 |
| `SetCutoff` (`at` is ISO 8601; without an offset it is read in the cooperative's timezone), `CloseProducerNow`, `ClearCutoff` (a producer not in the cycle is refused with `ProducerNotInCycle`; `ListCycles` participants carry `open`, from `isOpenFor`) | manager | R6, R6a, R6b, S6, S34, S35 |
| `CancelCycle` (deletes the cycle's orders in the same transaction; `ListCycles` cycles carry `cancelled`; a cancelled cycle refuses line changes, new participants and cutoff changes with `CycleCancelled`, and cancelling twice too; cancelling once any producer order is sent is refused with `ProducerOrderSent`) | manager | R4b, S37 |
| `SetProductAvailability` (`cycleId`, `productId`, `available`; refused with `ProducerOrderSent` once the producer's order is sent (R3c); the product must be orderable, so an unknown or deactivated one is refused with `ProductNotOrderable`, and its producer must be the actor; `SetOrderLine` refuses an unavailable product with `ProductNotOrderable` and `GetHouseholdOrder` stops offering it) | producer (own) | R3c, S48 |
| `SetOrderLine` (the household is the actor's, so an account without one is refused with `NoHousehold`; `quantity` is a string of at most 3 decimals, 0 removes) | member of household | R3, R7–R11, S1–S5, S33, S49, S51 |
| `RemoveMarkedLine` (`cycleId`, `productId`; a line that is not marked is refused with `LineNotMarked`) | member of household | R3b, R8, S32 |
| `GetHouseholdOrder` (the lines with their snapshot, and the participating producers with whether each is open and the products it offers; each line carries `notDelivered`, the derived *will not be delivered* mark (R3b); the pickup, once settled, arrives with the pickup tasks) | member of household | R3b, R7, R23a, S4 |
| `ListMemberCycles` (`MemberCycleData`: pickup date, whether it is open for members, and how many lines the household has in it; the pending, cancelled and closed states arrive with their tasks: T17 cancelled, the pickup tasks pending and closed) | member | R4a, R23a |
| `SendProducerOrder` (the cycle `FOR UPDATE`; `Cycle::send`, then the marked lines are stored as not delivered, then the producer is emailed the totals after commit; a producer with no account is refused with `ProducerHasNoAccount` before anything is stored, unless it has no line to send; the `POST …/producers/{producer}/send` route and its button come with the later task that adds the send button, not T18) | manager | R12a, R6b, R6c, S34, S36 |
| `SendEmptyProducerOrders` (scheduled every minute in `routes/console.php`; T18 only sends: per cycle `FOR UPDATE`, each participating, closed, unsent producer with no line to send is sent and its marked lines stored, nobody is notified; from T23 it then validates and distributes it, and settles the cycle through `CycleSettlement`) | — (scheduler) | R12a, D10 |
| `GetProducerCycleSummary` (the producer's orderable products by name with whether each is available in the cycle; `sent` and, once its order is sent, the total to deliver per product with the marked lines left out, never household names; null quantities before; a cancelled cycle or one without the producer is `CycleNotFound`) | producer (own) | R12, S7, S8 |
| `GetCycleSummary` (ordered quantity per product under its producer, per household; households Membership does not know show their id; carries `cancelled`, and a cancelled cycle has no totals) | manager | R13, S52, S62 |
| `HasUnsettledLines(productId): bool`, `StopParticipatingWhereNotSent(producerId)` (T16: stops the producer in every cycle that is not cancelled and where it participates; a sent order is skipped, so its participation stays, S50) | — (called by Catalog) | R1, R3a |
| `ListPendingPickups` | — (called by Membership) | R35a |
| `GetReception` (`ReceptionData`: per product the ordered, received and allocated totals, the signed `difference`, `balanced`, `nobodyOrdered`, `hasManualAllocations` and every household's ordered and allocated quantity with `byHand`; plus the producer's products, deactivated ones included, that are not in the reception; a producer order that is not sent is refused with `ProducerOrderNotSent`) | manager | R14, R17 |
| `AddUnorderedProduct(received = 0)` (a product of the producer, active or not; a product somebody ordered is refused with `ProductAlreadyOrdered`; adding a product nobody ordered again replaces its total, which is how the total is corrected, since way A cannot split it, R15) | manager | R14, S40 |
| `SetReceivedTotal(overwriteManual?)` → runs `ProportionalAllocator` | manager | R15, R17a, S10–S12, S31 |
| `SetAllocation` (a household Membership does not know is refused with `HouseholdNotFound`, a deactivated one with `HouseholdInactive`; a line marked *will not be delivered* with `LineMarkedNotDelivered`) | manager | R16, R17, S13, S39, S40 |
| `ValidateReception` | manager | R17a, S47 |
| `MarkProducerDistributed` (settles the cycle when it is the last) | manager | R18, R24, S13, S20, S27–S29, S47, S61 |
| `ReopenProducerOrder` (T23: before settlement only) | manager | R18, S29, S30, S46 |
| `GetContainerCount` | manager | R18a, S60 |
| `OpenPickup` (household without a pickup, active, in a settled cycle: `CycleNotSettled`, `HouseholdNotFound`, `HouseholdInactive`, `PickupAlreadyOpen`; closed cycles are refused with `CycleClosed`) | manager | R19, S18 |
| `GetPickup` (lines with ordered, allocated and delivered, and the total; stock and return lines arrive with T28; the household's balance and the resulting balance are read by the controller with Wallet's `GetBalance`, see the 2026-10-25 Deviation in `log.md`), `ListPickups` (`PickupSummaryData` per household with a pickup: status and total; settled cycles only, `CycleNotSettled` otherwise) | manager | R19, R21, S27 |
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
3. When done pickups have this product, require `includeCompleted` explicitly (`RedistributionChoiceRequired`).
   Unfinished-only claims exclude done households and subtract their effective allocations from the total.
   A negative remainder or no eligible claims raises `RedistributionCannotExcludeCompleted`, offering all or cancel.
   All claims include done households; pending lines reallocate, done lines append `PickupAdjustment` and a signed
   Wallet correction, in the same transaction. A product nobody ordered remains `NobodyOrderedProduct` (R15).
4. Store the received total and clear validation. Allocation-only lines not in claims go to zero. A done line's
   first baseline is its original delivered quantity; subsequent changes use its latest target. An unchanged
   effective allocation and delivered target appends nothing. A first target equal to already charged delivered
   weight but different from original allocation records a zero-charge reconciliation to balance reception;
   the wallet omits zero and the UI labels that record as reconciliation.
   The cycle `FOR UPDATE` already serialises every reception change and `SetOrderLine` needs it `FOR SHARE`, so T22 does not lock the orders one by one.

`SetAllocation` also requires a sent, not distributed producer order and an active household (`Households`,
`HouseholdInactive`), and rejects a done pickup.

`OpenPickup` requires a settled, not closed cycle (`CycleNotSettled`, `CycleClosed`) and an active household
with no order in it (R19, R24b).

`MarkProducerDistributed(cycle, producer)`, in one transaction with the cycle `FOR UPDATE`:
1. Build the `Reception`; `cycle.markDistributed(producerId, reception.balancedForDistribution, now)`.
2. `CycleSettlement(cycle, settledNow)`, an Application class with the single `__invoke` the architecture tests require (A6):
   - if this settled the cycle: every order `drop(marks)` (R18, R24);
   - if the cycle was already settled (a reopen): every `nothingToCollect` order `reopenIfAllocated()` (R24, S30); T30;
   - `cycle.close(...)` if every pickup is done (R24b); T30.

`SendEmptyProducerOrders` (D10), per producer order whose cutoff passed with no line, in one transaction
with the cycle `FOR UPDATE`: `send`, `validate`, `markDistributed(producerId, true, now)`, then the same
`CycleSettlement`, so a cycle settled by the scheduler drops lines and closes like any other.

`CompletePickup(cycle, household, topUp?)`, in one transaction:
1. Load the cycle `FOR UPDATE` and require settlement; load the order `FOR UPDATE`; a done pickup remains
   `PickupNotPending` on duplicate finalization. A closed cycle has no pending pickups;
   `order.complete(now)`.
2. For each stock line, in stock product id order, `StockSeller::sell(stockProductId, quantity, orderId)` — locks the stock product
   and fails with `NotEnoughStock` if it went below the quantity since it was added (R22, R27, S45).
3. `WalletLedger::topUp(...)` if any, then `WalletLedger::charge(household, cycle, total)`: a negative total
   is a credit, 0 records nothing (R21, R22).
4. `cycle.close(...)` if this was the last pickup (R24b).
Any failure rolls everything back. `MarkPickupNotCollected` follows steps 1, 3 (no top-up) and 4.
`StockSeller` and `WalletLedger` are Ordering Application ports (they take the `Actor`) with the adapters `StockModuleStockSeller` and `WalletModuleLedger`; the pickup stores `pickup_done_at` and `charged_cents`. `PickupLocker` takes an exclusive cycle lock for finalization and closes after the order is saved.

## Stock

Domain
- **`StockProduct`** (id, name, unit, `Money` price ≥ 0, variable, `?Quantity containersPerUnit`, active) (R25).
- **`ReturnItem`** (id, name, `Money` amount > 0, active) (R25).
- **`StockMovement`** `final readonly`: id, stockProductId, type (`Entry|Adjustment|Sale`), `SignedQuantity` (a Stock value object,
  since `Quantity` is never negative),
  occurredAt, recordedBy, `?note`, `?cycleId`, `?orderId`. Invariants: entry > 0; adjustment ≠ 0 with a
  non-empty note; sale < 0 with an orderId (R26–R28).
- `available = Σ movements`; a movement that would make it negative is rejected (R27).
- Ports: `StockProductRepository`, `ReturnItemRepository`, `StockMovementRepository` (append-only: `add`, `forProduct`, `available`,
  `availableByProduct`, and `availableForUpdate`, which locks the `stock_products` row until the transaction ends).
- **`StockLedger`** domain service: `record(StockMovement)` reads `availableForUpdate`, throws `NotEnoughStock` if the movement would take it
  below 0, then adds it. `RecordStockEntry`, `RecordStockAdjustment` and `SellStock` call it inside a `TransactionRunner` run.
- `StockProduct` and `ReturnItem` are mutable entities with a private constructor, `create` and `restore`; `change`
  re-checks every rule, so create and update share them. Rule breaches throw `NameRequired`, `UnitRequired`,
  `InvalidPrice`, `InvalidContainersPerUnit` (zero or not a valid quantity) and `InvalidReturnAmount`.
  Deactivation and reactivation preserve each item's data and history.

Use cases
| Use case | Actor | Covers |
| --- | --- | --- |
| `CreateStockProduct`, `UpdateStockProduct`, `DeactivateStockProduct`, `ReactivateStockProduct` | manager | R25 |
| `CreateReturnItem`, `UpdateReturnItem`, `DeactivateReturnItem`, `ReactivateReturnItem` | manager | R25 |
| `ListStockProducts`, `ListReturnItems` (inactive ones included, by name) | manager | R25 |
| `RecordStockEntry` | manager | R26, S23 |
| `RecordStockAdjustment` (optional cycle) | manager | R27, S23 |
| `ListStock` (with available quantity, as `StockLevelData`) | manager | R28 |
| `GetStockOffer` (a `StockLevelData`, `active` flag included: a deactivated product is still returned, Ordering decides), `GetReturnItem` | — (internal queries for Ordering) | R20, R20a |
| `SellStock` (takes the `Actor` only to record who sold; no role check) | — (called by Ordering) | R22, R28 |

## Wallet

Domain
- **`Movement`** `final readonly`: id, householdId, type (`TopUp|Charge|Credit|Correction`), signed `Money`
  amount, occurredAt, recordedBy, `?note`, `?cycleId`. Invariants: top-up > 0 with no cycle; charge < 0 and
  credit > 0, both with a cycleId; correction ≠ 0 with a non-empty note (R29, R32).
- Port: `MovementRepository` — append-only: `add`, `balanceOf`, `statementOf` (newest first). No update, no delete (R32).
  `add` throws `PickupAlreadyCharged` for a second charge or credit of the same household and cycle (R23); the
  partial unique index is the second line of defence.
- A deactivated household's wallet still accepts top-ups and corrections (R35a).

Use cases
| Use case | Actor | Covers |
| --- | --- | --- |
| `RecordTopUp` | manager (also called by Ordering at pickup, with the manager's `Actor`) | R29, S19, S24 |
| `RecordCorrection` (optional cycle) | manager | R32, S24, S54 |
| `ChargeForPickup` (charge, credit or nothing; takes the signed total in cents) | manager (called by Ordering with the pickup's `Actor`, who is `recordedBy`) | R22, R30 |
| `GetBalance` | manager (internal query for Ordering and Membership adapters) | R21, R35a |
| `GetWalletStatement` | member of household / manager | R30, R31, S21 |

## Persistence (Postgres)

All ids are UUID v7 generated by repositories (`nextId()`); timestamps are `timestamptz` in UTC.

| Table | Columns | Constraints |
| --- | --- | --- |
| `cooperative_settings` | id, pickup_weekday | single row: `CHECK (id = 1)`, `CHECK (pickup_weekday BETWEEN 1 AND 7)`; `pickup_weekday` is null until set (R4c) |
| `households` | id, name, deactivated_at? | |
| `users` | id, name, email, password?, household_id?, producer_id?, activated_at?, deactivated_at? | email unique; producer_id unique (R36) |
| `account_roles` | user_id, role | `PK (user_id, role)` |
| `invitations` | account_id, token_hash, sent_at | `PK (account_id)` (one live invitation per account); token_hash unique |
| `producers` | id, name, active, cutoff_days_before, cutoff_time (`time`) | `CHECK (cutoff_days_before BETWEEN 1 AND 6)`; `users.producer_id` references it |
| `products` | id, producer_id, name, unit, price_cents, variable, containers_per_unit?, active | `CHECK (price_cents >= 0)`; `CHECK (containers_per_unit > 0)` |
| `order_pickup_adjustments` | id, order_id, product_id, name, unit, unit_price_cents, quantity_delta_thousandths, amount_cents, target_quantity, recorded_at, recorded_by | append-only inserts; ordered by recorded_at then id; target ≥ 0 |
| `cycles` | id, pickup_date, cancelled_at?, settled_at?, closed_at? | partial `UNIQUE (pickup_date) WHERE cancelled_at IS NULL` |
| `cycle_producers` | cycle_id, producer_id, participating, closes_at?, sent_at?, validated_at?, distributed_at? | `PK (cycle_id, producer_id)` |
| `product_unavailabilities` | cycle_id, product_id | `PK (cycle_id, product_id)`; both reference their tables |
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
- `ReopenProducerOrder`, `CompletePickup` and `MarkPickupNotCollected` lock the cycle `FOR UPDATE` before
  order locks. Finalization can close the cycle; using `FOR SHARE` then upgrading would deadlock concurrent
  final pickups. This also serializes all redistribution adjustments and wallet corrections against completion.
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
| `GET /` | `Orders/Index` for a member with a verified email (the `Welcome` page for guests, accounts that are not members and members whose email is not verified) | `ListMemberCycles` |
| `GET /cycles/{cycle}/order` | `Orders/Edit` (producers with what they offer, the order's lines and a running total; `cancelled` from `HouseholdOrderData`, shown as a notice with no producers or lines when the cycle is cancelled, R4b, S37; the pickup once settled arrives later; a line marked *will not be delivered* shows the mark, counts as zero in the total and has a Remove action instead of the quantity box, R3b, R6c, R8; the lines of a producer that no longer takes part stay under that producer's name, marked) | `GetHouseholdOrder` (it lists the offered products itself, and the controller names the producers with Catalog's `GetProducer`) |
| `PUT /cycles/{cycle}/order/lines/{product}` (`quantity`; 0 removes the line) | partial reload of `order` prop | `SetOrderLine` |
| `DELETE /cycles/{cycle}/order/lines/{product}` | partial reload | `RemoveMarkedLine` |
| `GET /wallet` | `Wallet/Statement` (member's own household) | `GetWalletStatement` |
| `GET /manage/households/{household}/wallet` | `Wallet/Statement` with the top-up and correction forms | `GetWalletStatement` |
| `GET /producer/products`, `GET /producer/products/create`, `GET /producer/products/{product}/edit` | `Producer/Products/Index`, `Create`, `Edit` | `ListProducerProducts` |
| `POST /producer/products`, `PUT /producer/products/{product}` | redirect to the list | `CreateProduct`, `UpdateProduct` |
| `DELETE /producer/products/{product}`, `PUT /producer/products/{product}/reactivation` | redirect to the list | `DeactivateProduct`, `ReactivateProduct` |
| `GET/PUT /producer/cutoff` | `Producer/Cutoff` | `GetDefaultCutoff`, `SetDefaultCutoff` |
| `GET /producer/cycles/{cycle}` | `Producer/CycleSummary` (products with availability and a toggle while not sent; the totals once sent) | `GetProducerCycleSummary` |
| `PUT /producer/cycles/{cycle}/products/{product}/availability` (`available`: boolean; the `Producer/CycleSummary` page uses it) | partial reload | `SetProductAvailability` |
| `GET /manage/settings`, `PUT /manage/settings/pickup-weekday` | `Manage/Settings` | `GetPickupWeekday`, `SetPickupWeekday` |
| `GET/POST /manage/cycles` | `Manage/Cycles/Index` (cycles with named participants, active producers to pick, the pickup weekday or null; while it is null the open form also shows a weekday picker and a permanent/provisional choice, sent as `pickup_weekday` and `permanent`) | `ListCycles`, `OpenCycle` |
| `GET /manage/cycles/{cycle}` | `Manage/Cycles/Show` (totals, producers, containers; a "Cancelled" notice instead of totals when the cycle is cancelled) | `GetCycleSummary`, `GetContainerCount` |
| `POST /manage/cycles/{cycle}/cancel` | — (the cycles page shows a cancel button that opens a confirmation dialog warning that every household's orders are discarded and it cannot be undone, and a cancelled cycle with its producer controls hidden) | `CancelCycle` |
| `POST/PUT/DELETE /manage/cycles/{cycle}/producers/{producer}` | — | `AddParticipant`, `ParticipateAgain`, `StopParticipating` |
| `POST/DELETE /manage/producers/{producer}/deactivation` | redirect back | `DeactivateProducer`, `ReactivateProducer` |
| `PUT/DELETE …/producers/{producer}/cutoff`, `POST …/close` | — | `SetCutoff`, `ClearCutoff`, `CloseProducerNow` |
| `POST …/producers/{producer}/send` | — | `SendProducerOrder` |
| `GET /manage/cycles/{cycle}/receptions/{producer}` | `Manage/Reception/Show` (per product the totals, a balanced or unbalanced flag, the received-total form, the households' allocations with a box to allocate to another household, and a form to add a product nobody ordered; setting a received total when an allocation was set by hand opens a dialog that warns they will be lost and either confirms, sending `overwrite_manual`, or cancels without a request, R15, S31; an unknown cycle, a producer not in it or an order that is not sent is a 404; from T23 an extra `distribution` prop (`validated`, `distributed`, `settled`) drives the Validate, Mark as distributed and Reopen buttons, and a distributed reception shows no edit forms) | `GetReception` |
| `POST …/receptions/{producer}/products` (`product_id`, `received`) | partial reload | `AddUnorderedProduct` |
| `PUT …/receptions/{producer}/products/{product}/received` (`received`, `overwrite_manual`) | partial reload | `SetReceivedTotal` |
| `PUT …/receptions/{producer}/products/{product}/allocations/{household}` (`quantity`) | partial reload | `SetAllocation` |
| `POST …/receptions/{producer}/validate` | partial reload | `ValidateReception` |
| `POST/DELETE …/receptions/{producer}/distribution` | — | `MarkProducerDistributed`, `ReopenProducerOrder` |
| `GET /manage/cycles/{cycle}/pickups` | `Manage/Pickups/Index` (the pickups with status and total, and the active households that have none, to open one) | `ListPickups`, `ListHouseholds` |
| `GET/POST /manage/cycles/{cycle}/pickups/{household}` | `Manage/Pickups/Show` (`PickupRow`: lines, total, and the balance and resulting balance from Wallet's `GetBalance`) | `GetPickup`, `GetBalance`, `OpenPickup` |
| `PUT …/pickups/{household}/lines/{product}` | partial reload | `AdjustDeliveredQuantity` |
| `POST/DELETE …/pickups/{household}/stock-lines[/{stockProduct}]` | partial reload | `AddStockToPickup`, `RemoveStockFromPickup` |
| `POST/DELETE …/pickups/{household}/return-lines[/{returnItem}]` | partial reload | `AddReturnToPickup`, `RemoveReturnFromPickup` |
| `POST …/pickups/{household}/complete`, `…/not-collected` | redirect to pickups index | `CompletePickup`, `MarkPickupNotCollected` |
| `/manage/stock…`, `/manage/return-items…` | `Manage/Stock/{Index,Create,Edit,Movements}`, `Manage/ReturnItems/{Index,Create,Edit}` | Stock CRUD (T24), plus `PUT /manage/stock/{stockProduct}/reactivation` and `PUT /manage/return-items/{returnItem}/reactivation`; `GET /manage/stock/{stockProduct}/movements` and `POST …/entries`, `POST …/adjustments` (T25): `ListStock`, `RecordStockEntry`, `RecordStockAdjustment`. The stock index lists `StockLevelRow`s (available quantity). The movements page lists the cycles (`ListCycles`) in the adjustment form's optional cycle picker; `cycle_id` must exist in `cycles` (friendly validation; `stock_movements.cycle_id` also has a foreign key to `cycles`, added by T11) |
| `PUT/DELETE /manage/accounts/{account}/household`, `PUT/DELETE …/manager-role`, `POST/DELETE …/deactivation` | redirect back | `MoveMember`/`UnlinkMember`, `GrantManagerRole`/`RemoveManagerRole`, `DeactivateAccount`/`ReactivateAccount` |
| `/manage/households…`, `/manage/producers…`, `/manage/accounts…` | `Manage/Households/*`, `Manage/Producers/*`, `Manage/Accounts/*` | Membership + Catalog |
| `GET/POST /manage/producers`, `POST /manage/producers/{producer}/account` | `Manage/Producers/Index` (list with each producer's account email and invitation state, create form, invite form only for producers without an account) | `ListProducers` + `ListProducerAccounts` (the controller joins them into `ProducerRow`), `CreateProducer`, `InviteProducerAccount` |
| `POST /manage/households/{household}/wallet/{top-ups,corrections}` | — | `RecordTopUp`, `RecordCorrection` |

The pickup page follows the steps the manager works through: check the household's lines (from the
reception), adjust what is needed, add stock and returns, enter a top-up if they pay, complete — or mark it
not collected. Each step before completion is saved on the server, so a pickup can be left and resumed.

Controllers map domain errors in one place: an exception handler turns `Guixa\*\Domain\Error\*`
into a 422 with a translated message (`errors.<ClassName>` in `lang/ca/errors.php`; a missing key falls back to
the exception message, and the translation test fails on it), so Vue
shows it as a form error: JSON requests get the 422, Inertia requests a redirect back with a `domain` error. `Shared\Domain\Error\NotAllowed`
becomes a 403 instead.

Inertia prop DTOs live in `App\Http` and are named `<Noun>Row` (e.g. `StockProductRow`, `HouseholdRow`); their TypeScript types are generated.

Client-side logic (Vitest): order running total, pickup resulting balance, both display-only.

### Member ordering presentation (T37)

Behavior-preserving redesign of `Orders/Index` and `Orders/Edit`, following the constitution's UI
rules and the [UX discovery](../../docs/ux-discovery/2026-10-24-guixa-discovery.md). No new requirements,
routes, permissions, DTO fields or ordering rules. Implement after the shared translation foundation
in T38 is available; do not hard-code Catalan or introduce a page-local translation mechanism.

- **Cycle list:** a clear page heading and grouped, full-width cycle links. Keep the existing open,
  closed-to-orders and cancelled grouping, pickup date, line count, destinations and empty state.
- **Order editor:** producer sections with a visible open/closed label; product rows stack on phones
  and align into columns when space permits. Each quantity field has a visible, associated label;
  price/unit, line amount and the running total remain easy to scan. Keep the total in normal flow
  so it cannot cover fields or keyboard focus. Do not add cutoff information absent from current props.
- **Interaction:** preserve explicit per-line Save and Enter submission, draft-driven totals,
  partial reloads, retained scroll position, disabled rules, existing errors and marked-line removal.
  Cancelled orders keep their notice and hide producers/lines. Do not add autosave, checkout, a new
  saved-state model, repeat-order, wallet information, notifications or telemetry.
- **Presentation:** reuse the existing semantic theme tokens, font, icons and UI primitives. Use
  consistent spacing, readable amounts, visible keyboard focus and text as well as colour for state.
  Support light/dark/system appearance, 375 px without horizontal overflow and targets of at least
  44 × 44 px. All visible and accessible copy uses T38's shared `lang/` translations and Catalan formatting;
  formatting must not change quantity request values or monetary calculations.
- **Shell boundary:** only ordering-relevant navigation/layout adjustments; preserve role visibility
  and destinations. No global theme replacement, manager/producer screen redesign or unrelated
  starter-kit cleanup in this slice.

Component boundaries: the route pages retain Inertia orchestration and current draft/save state;
presentational cycle links receive a cycle, producer sections receive their group, and product rows
receive their row, draft, disabled/saving state and display amounts. Product rows emit draft updates,
save and remove events; they do not call the router or duplicate `orderRows`/`orderTotal` logic.
Extract only those presentation blocks needed by the redesign, under `components/orders/`.

Verification: preserve the existing helper tests (`orderRows.spec.ts`, `orderTotal.spec.ts`) and the
HTTP page contracts in `OrdersPagesTest.php`. Check the rendered pages at 375 px and desktop, in light
and dark modes, with keyboard navigation and enlarged text: empty/open/closed/cancelled cycles,
editable/closed producer rows, marked lines, errors and totals. These existing tests do not prove
rendered layout; record browser evidence separately. Use focused local checks; full validation stays
with CI under the project execution profile. Historical browser and delivery checks are recorded in
`log.md`; current reconciliation evidence and its limits are summarized in `tasks.md`. T37 is complete
as DRA-195, separately from the staged visual identity amendment below.

## Application visual identity

**Status: Accepted (2026-10-24); T39 approved for rollout, T40 implemented and T41 implemented for review (2026-10-25); T42 implemented for review (2026-10-26).** Route: plan only,
because this changes presentation without changing the spec's requirements or acceptance scenarios.
The task amendment is accepted; the T39 visual review was approved on DRA-191 before T40 began.

### Scope and invariants

Apply a modern, agricultural-heritage identity to the existing management application: member,
producer and manager screens, shared navigation, account settings and existing authentication
screens. Keep the interface practical; the reference is vegetable gardens and local food production,
not a promotional shop or a simulated antique interface.

This is a new presentation slice, not an extension or reopening of T37. T37's font-reuse and
no-global-theme-replacement boundaries describe that earlier slice and remain unchanged. This
accepted amendment separately defines the shared theme and heading typography changes below.

- Preserve routes, role visibility, permissions, Inertia props, validation, data and business rules.
  Preserve navigation destinations, save/submit behavior, quantities, monetary calculations and
  existing empty, loading, error, disabled, cancelled and settled states wherever applicable.
- No public website, promotional landing page, new registration flow, checkout, autosave, repeat-order
  feature or new domain information. Leave the guest `Welcome` page and its routing unchanged.
- Preserve light/dark/system appearance and the saved appearance preference. Theme styling does not
  change how that preference is stored or selected.
- Follow the constitution's phone-first, touch-target, plain-language and translation rules. This
  work neither completes T36's app-wide translation migration nor changes its scope; any copy
  added or changed here uses the existing `lang/` mechanism.

### Visual system

Use the existing semantic variables in `resources/css/app.css` and existing Tailwind/shadcn-vue
primitives; do not introduce another component library or scatter palette literals across pages.
Scope the new token values and heading typography to an explicit management/auth layout theme
boundary, not unconditional `:root` or body overrides that would restyle `Welcome`. During the
pilot, attach that same theme only to the pilot screens; expand its application after visual approval.
Keep one theme definition rather than copying page-specific palettes.

| Role | Light-theme reference | Application |
| --- | --- | --- |
| Background | Cream `#F7F3E8` | Main canvas and quiet surfaces |
| Primary | Olive `#3F5D3A` | Main actions, links and selected navigation |
| Accent | Terracotta `#B85C38` | Sparse decorative highlights, not error or warning semantics |
| Secondary surface | Pale sage `#E3EBDD` | Grouped content and supporting panels |
| Foreground | Green charcoal `#283127` | Body text, labels and values |

These are palette anchors, not permission to combine any two colors. Define and verify paired
foreground/background values for buttons, cards, popovers, inputs and the sidebar, including hover,
focus and disabled states. Keep destructive, warning and success states distinct from brand accents
and accompany meaningful status colors with text or icons. Derive dark-theme counterparts rather
than reusing light surface values; check the same states in both themes.

The 2026-10-25 T39 visual review refined the dark counterpart after the user found the first
render too uniformly green. Its canvas, cards, sidebar and popovers use stepped neutral charcoal
surfaces with warm off-white text and neutral muted text. Olive remains on primary actions,
selected navigation and focus; quantity inputs sit on a darker neutral inset. The light palette
anchors above and all ordering behavior remain unchanged.

- **Typography:** retain Instrument Sans for body text, navigation, forms, tables and amounts. Add a
  shared heading token with `Georgia, serif` as the initial system-font proposal, reserved for page
  titles and prominent section headings. Check Catalan diacritics, wrapping and hierarchy in the
  visual pilot. No font package or external font service is required by this amendment.
- **Layout:** use clear hierarchy, consistent spacing, restrained borders and modest corner rounding.
  Avoid making operational lists or forms less useful merely to add whitespace or decoration.
- **Agricultural references:** small botanical line illustrations or a subtle paper texture may appear
  in non-interactive header areas, never behind data, controls, alerts or tables. Decorative images
  are ignored by assistive technology. No full-screen photography, sepia filter or wood background.
  Real photographs are optional, must have permission for use, and are not a dependency of this slice.
- **Motion:** retain functional feedback and respect reduced motion; no new ornamental animation.

### Delivery sequence

1. Prepare a visual pilot of the existing member cycle list and order editor, including their shared
   shell, at 375 px and desktop widths in light and dark modes. Use this to review the theme, serif
   headings and readability before wider rollout. Do not change the guest homepage or ordering flow.
2. After visual approval, apply the shared tokens and heading treatment consistently to the existing
   member, producer, manager, settings and authentication screens. Inventory the screens and shared
   components in the task-planning step so global-token effects are not mistaken for isolated changes.
3. Complete the presentation and regression checks below, recording observed results per delivery.
   Future screens inherit the approved system when their own tasks are implemented; this slice does
   not bring unimplemented features forward.

For T39, `AppLayout` recognizes only `Orders/Index` and `Orders/Edit` and passes a reactive opt-in
through `AppSidebarLayout`. The layout provides that flag to portal wrappers. The shell, mobile
sheet, dropdown, dialog, tooltip and toaster attach `pilot-theme` only while opted in; `app.css`
declares the palette once under that class, including dark counterparts. No unconditional root or
body token is changed, and an Inertia page change removes the opt-in without changing the saved
light/dark/system preference. The page header wraps at enlarged text sizes; only page and prominent
section headings receive the Georgia token. The [T39 visual check](../../docs/visual-checks/dra-190.md)
records the observed browser matrix and its mocked-transport limit.

The pilot is a review checkpoint, not a second theme implementation. Reuse the same semantic tokens
and presentation components throughout. Preserve existing page orchestration and the constitution's
component boundaries; do not refactor business logic or shared interaction behavior for visual polish.

#### 2026-10-26 Modern Minimal theme replacement

The user's later request to apply the [Modern Minimal registry theme](https://shadcnstudio.com/r/themes/modern-minimal.json)
supersedes this section's cream, olive, terracotta, Instrument Sans and Georgia
visual choices within the existing `pilot-theme` boundary. Use the registry's
semantic light/dark palette, Inter headings, Source Serif 4 body text, JetBrains
Mono code text and restrained shadows. Keep the accepted layout, touch targets,
behavior and opt-in scope, so the guest `Welcome` page retains its own styling.
The registry item targets React; adapt its tokens to the existing shadcn-vue
setup without installing React components. Where the registry omits a dark
semantic pair, supply a readable counterpart. Adjust the light primary,
destructive and input-edge tones only as needed for the accepted contrast
requirements; record exact values and checks in the DRA-199 visual record.

#### 2026-10-27 Readability refinement

The user's request for softer, easier-to-read lettering replaces the Inter
heading and Source Serif 4 body pairing with the self-hosted Nunito variable
family. Use it for headings, body text, navigation and controls inside the
existing `pilot-theme` boundary, including manager pages and portal content.
Keep JetBrains Mono for code and a system sans-serif fallback. The guest
`Welcome` page remains outside the themed boundary.

#### 2026-10-27 Unified warm application palette

The user's request for one calmer, warmer palette supersedes the separate blue
application and green/aqua/lavender manager color assignments. Use one set of
semantic light/dark tokens for member, producer, manager, settings and auth
screens, with a cream canvas, quiet card surfaces, olive primary and restrained
clay accent. Dark mode uses stepped warm charcoal surfaces rather than a green
wash. The guest `Welcome` page keeps its own hardcoded illustration and colors.

Actions have a stable hierarchy across roles: the filled olive button is the
primary action, a pale sage fill is secondary, a bordered card-surface button
is an alternative secondary action, and destructive actions use the dedicated
red token. Clay is for incidental emphasis and hover states, never a competing
primary button. Shared text inputs and select triggers have a card surface and
visible field border at every width, so their role differs from filled actions.
Keep semantic success, warning and error colors distinct and verify text,
control-edge and focus contrast in light and dark modes.

#### 2026-10-27 Modern visual refinement

After reviewing the warm preview, the user found the cream, olive and clay
combination too retro. Keep the action hierarchy above, but use a nearly white
warm-neutral canvas (`#F8F7F4`), white cards, charcoal text and a single deep
green (`#246B57`) for primary actions. Secondary surfaces are muted gray-green;
hover emphasis is a low-chroma neutral rather than a separate colorful fill.
Dark mode uses neutral stepped charcoal surfaces with a soft green primary.
Retain Nunito and the established control sizes. Avoid gradients and decorative
color blocks; let contrast, spacing and labels convey the action hierarchy.

T40 extends the same reactive opt-in to Dashboard, Wallet/Statement and the five existing producer
pages. Their current page components retain their props, actions and form contracts. Shared page,
panel, empty-state and mobile table presentation classes live under the pilot theme, so the two
wallet roles and producer lists use the approved token pairs without a second palette. The
scoped destructive token also colors their existing form errors, and newly visible phone row labels
reuse the desktop header translations. The
[T40 visual check](../../docs/visual-checks/dra-191.md) records rendered states and focused
regressions, including an unbroken long product name that must wrap within the cycle summary;
the manager wallet's phone grid also uses a zero-minimum column so enlarged text cannot push its
cards beyond the viewport. Product rows reflow inside a container query when enlarged text no
longer fits six table columns. Wallet movement rows likewise reflow by available panel width at
enlarged text, while normal desktop widths retain tables. The fixture verifies wallet cells within
their panel and clipping ancestors in both roles. Its transport is mocked
and does not prove persistence.
Manager wallet forms reflow by available content width when enlarged text cannot fit two cards;
their button text wraps within each card. At normal desktop width the two-column form remains.
The rendered fixture checks form headings, labels, inputs, and button text within their panels.
Producer cycle-summary names and availability controls also stack by available list width at
enlarged text, leaving usable name space while retaining ordinary desktop rows and requests.
The T40 visual sweep includes every affected view at 1024 px and 200% text in both themes,
checking keyboard focus and relevant text/container bounds alongside 375 and 1280 px.

T41 adds the fourteen existing manager route names to the same reactive opt-in. Their route
components retain existing props, form actions, validation and destinations; shared page and
card rules present their operational content on the approved surfaces. Stock and return-item
tables retain desktop columns and become labeled rows when the available width cannot hold
them. Their phone labels reuse the desktop translations. Manager forms and list headers stack
at narrow widths, including 200% text, while ordinary desktop rows keep their existing
structure. The [T41 visual check](../../docs/visual-checks/dra-192.md) records the rendered
matrix and its mocked-transport limit; the household detail's wallet link still enters the
shared T40 Wallet/Statement view. At enlarged phone text, cycle cutoff entry uses separate
required date and time controls plus a visible full-value preview; a single hidden `cutoff`
field retains the existing local `YYYY-MM-DDTHH:mm` request value and action. The stock
adjustment cycle select and its full selected-label preview stay inside the form card. No
manager request or permission rule changes. The cancellation portal uses the theme's solid
destructive background and matching foreground on its confirmation button so normal and
hover text both meet the 4.5:1 contrast gate in light and dark. At phone widths the
cancellation portal gives Close a separate top row and both footer actions 44 px or larger
targets; its enlarged layout keeps the title clear of Close and the footer visible.
The T41 shared visual manifest exercises every manager view and applicable state across the
full width, theme and text matrix. Its long-content sweep also constrains the Accounts
household select and Move control inside their card at 1024 px, and long stock/return names
inside desktop table columns at 1280 px.

T42 adds `settings/{Appearance,Profile,Security}` and the seven `auth/*` pages to the same
reactive opt-in. Settings reuse `AppLayout`; `AuthLayout` now reads the page name and passes the
same flag to `AuthSimpleLayout`, which provides the theme to its portals and renders the form card
as the shared `.pilot-panel`. Only those exact page names opt in, so `Welcome` has no layout,
no theme class and no changed asset. The settings navigation and its content switch between
stacked and side-by-side by the available content width, not the viewport, because the app
sidebar narrows the content at enlarged text. Account, passkey and recovery-code sections are
panels; literal red, green and neutral colours on these pages resolve to the scoped destructive,
success and border tokens, destructive buttons in the settings page and its portals pair the
theme's destructive foreground, and dialogs that may exceed a phone viewport scroll internally
and keep the focused control visible. Existing authentication, passkey, two-factor, invitation,
verification and appearance behavior, requests and persistence are unchanged. The Orders edit product rows, a T39 layout that fixed its columns by viewport and failed at 1024 px with 200% text, now choose their columns by the row's own width. The
[T42 visual check](../../docs/visual-checks/dra-193.md) records the rendered matrix, the
checks outside the shared harness and the pending items.

### Verification and review gate

- **Visual/accessibility:** check every affected page at 375 px and desktop widths, with 200% text,
  keyboard navigation and visible focus; no clipped content or horizontal page scrolling. Preserve
  at least 44 × 44 px touch targets and access without hover. Verify normal text contrast of at least
  4.5:1, large text at least 3:1, and relevant control boundaries/focus indicators at least 3:1 against
  adjacent colors. Test light, dark and system appearance, not only the default palette.
- **States and roles:** exercise existing populated, empty, loading, error and disabled states where
  applicable, plus ordering's closed/cancelled/marked-line cases. Check each role's existing navigation
  and entry screens without changing authorization. Record browser evidence separately from tests.
- **Regression:** retain existing scenario tests and run affected frontend and HTTP checks when
  implementing. Add focused tests if markup changes affect labels, navigation or interaction; static
  layout/color changes require rendered checks rather than invented domain scenarios. Follow the
  project execution profile: focused local checks, full validation in CI, with unavailable checks
  reported rather than treated as passed.
- **Assets:** verify readable fallback typography, no layout shifts caused by decorative assets and
  no new runtime dependency on externally hosted imagery or fonts.

Approval of this section authorizes preparation of linked tasks only. Task approval and the visual
pilot review remain separate checkpoints. No task IDs, completed checks or implementation are claimed
by this amendment; `spec.md`, the area requirements and the constitution are unchanged.

## Layout rules

**Status: Accepted (2026-10-26).** Route: plan only, because this
changes spacing, sizes and alignment without changing the spec's requirements or acceptance
scenarios. It makes the [Application visual identity](#application-visual-identity) Layout bullet
("consistent spacing") concrete; palette and typography follow the latest
accepted visual-system amendment above, while scope and invariants stay as accepted.

The 2026-10-26 inventory found no arbitrary spacing values (`p-[13px]`) in `resources/js` outside the
excluded `Welcome.vue`, but no
agreed subset of Tailwind's 4 px scale either: pages mix `gap-1/2/3/4/6`, `p-3/4/5/6/8` and
`px-2/3/4/5/6`, and off-grid steps such as `p-5`, `gap-3` and `py-1.5` sit next to on-grid ones.
The rules below adopt Material Design's layout principles (8 px grid, minimum target size, type
scale, fixed component padding, page margins), not its components, elevation, colours, shapes or
motion. No component library is added.

### Rules

- **Spacing grid:** space between and inside containers uses 8 px steps: Tailwind `2`, `4`, `6`, `8`
  and `12` (8, 16, 24, 32, 48 px) in `gap-*`, `space-*`, `p*-*` and `m*-*`. The 4 px step (`1`) is
  allowed only inside a single control or inline group, such as an icon beside its label or a
  badge's padding. Other steps (`3`, `5`, `1.5`, `7`…) and arbitrary values are not used.
- **Targets:** keep the constitution's 44 × 44 px minimum for every interactive control, including
  checkboxes, radio items, icon buttons and row actions. Where a visual control is smaller, its hit
  area reaches 44 px; adjacent targets keep at least 8 px between them. Material's 48 px is not
  adopted, because the constitution already sets 44 px.
- **Type scale:** five sizes with Tailwind's default line heights: `text-xs` for metadata only,
  `text-sm` for secondary text and labels, `text-base` for body text and form inputs, `text-lg` for
  section headings and `text-2xl` for page titles. Form inputs stay at 16 px or more on phones.
  Headings use the current theme's heading token; amounts use tabular figures.
- **Component padding:** each pattern has one fixed padding and internal gap, so pages stop setting
  them one by one:

  | Pattern | Phone | From `md` | Inside |
  | --- | --- | --- | --- |
  | Page | `px-4 py-6` | `px-6 py-8` | `gap-6` between sections |
  | Panel / card | `p-4` | `p-6` | `gap-4` between blocks |
  | Form | — | — | `gap-6` between fields; `gap-2` from label to input to error |
  | List row | `py-4` | `py-4` | `gap-2` inside the row; `gap-4` before its actions |
  | Action row | — | — | `gap-2` between buttons; primary action last |

- **Alignment:** headings, panels, forms and tables on a page share the page's left edge. Text and
  labels are start-aligned; amounts and quantities are end-aligned in tables and lists. Icons align
  to the first text line's centre.

Express the patterns through the existing `.pilot-*` presentation classes in `resources/css/app.css`
or, where markup repeats, a presentation component; one definition per pattern. The shared shadcn-vue
primitives under `resources/js/components/ui/` keep their internal padding, but their rendered size
must still meet the target rule. The pilot tasks may tune the values above after their visual review;
the tuned value replaces the one here and is logged as a **Change**.

### Delivery: tracer bullets

Deliver the rules as thin vertical slices, each from rule to token to screens to rendered check,
reviewable and mergeable on its own. The first slice proves the whole path on one flow before the
others reuse it.

1. **Tracer (T43):** the member ordering flow and its shell (`Orders/Index`, `Orders/Edit`), as in the
   T39 pilot. Define the patterns above, apply them to those screens, add the static check below, and
   present light/dark captures at 375 px, 1024 px/200% text and 1280 px for visual approval.
2. **Area slices (T44–T46):** after that approval, apply the same patterns to the T40, T41 and T42
   screen inventories in [tasks.md](tasks.md#existing-screen-inventory). Each slice adds a pattern
   only when its screens need one T43 did not; it fixes off-grid values in its own screens only.

Each slice's off-grid fixes stay within its inventory, so slices after T43 do not touch the same pages
and can run in parallel.

### Verification

- **Static check:** a focused test fails when a page, layout or presentation component in a migrated
  slice uses a spacing utility outside the grid above, or an arbitrary spacing value. Each slice adds
  its files to the checked set; `components/ui/` and `Welcome.vue` are excluded.
- **Rendered check:** the shared visual harness (`skills/agent-workflow/scripts/visual/`) runs the slice's screens at
  375 px, 1024 px/200% text and 1280 px in light and dark, checking 44 px targets, the shared left
  edge and no horizontal overflow. Record the results in the slice's visual check.
- **Regression:** everything in the visual identity
  [Verification and review gate](#verification-and-review-gate) still applies. No request, prop,
  validation, permission or translation changes; no new `R#` or `S#`.

## Translations

Constitution, UI (*Translatable*). `ca` is the default and fallback locale and the only one shipped.

**Delivery split (2026-10-24):** T38 provides the shared Vue/Vite translation foundation, Laravel locale
configuration, `lang/ca.json`, reusable `ca-ES` presentation formatters, and focused translation checks.
T37 consumes that foundation for member ordering and the shell visible on those screens, including
ordering error translations. T36 retains the remaining app-wide pages, emails, framework messages,
domain errors and exhaustive translation coverage; it is not completed by T38 or T37. Existing local
`.env` overrides are not changed; installations must set `APP_LOCALE=ca` and `APP_FALLBACK_LOCALE=ca`
(or omit the overrides) to use the new defaults. The frontend uses the shared server locale, not a
separate page-local translator. No language selector or second shipped locale is added.

The foundation is checked through Laravel's public translator and the installed Vue plugin, including
placeholder substitution and PHP/JSON parity. Ordering regression checks use the existing public
HTTP and display-helper seams; rendered UI checks cover the presentation contract without adding
business rules. The library compatibility build is a focused prerequisite check, not a replacement
for CI's full validation.

- `laravel-vue-i18n` (its Vite plugin and Vue plugin) loads `lang/` into Vue, so PHP `__()` and Vue `trans()` / `$t`
  read the same files. Its fit with Inertia 3 and Vite+ is checked first; if it does not fit, `vue-i18n` with
  messages generated from `lang/` at build time (D18).
- Keys are the English source strings, in `lang/ca.json` (`"Save order": "Desa la comanda"`); placeholders use
  Laravel's `:name` form. Domain errors keep their own file, `lang/ca/errors.php`, keyed by error class short name
  (`lang/en/errors.php` moves there).
- Laravel's own messages (validation, auth, passwords, pagination) come from the `laravel-lang` Catalan files in
  `lang/ca/*.php`.
- Emails (invitations, sent producer orders): subject and body through `__()`.
- Dates, numbers and money: Vue formats with `Intl` and `ca-ES`; PHP uses the same locale where it formats (emails).
- Starter-kit and shadcn-vue components' own texts (e.g. screen-reader labels) go through `trans()` too.
- Every key used in PHP and Vue has a `ca` translation; a test fails otherwise (Testing).

## Testing

| Level | What | Where |
| --- | --- | --- |
| Domain | Value objects, `LocalTime`, `CutoffRule`, `ProportionalAllocator` (property: Σ result == target), `Cycle`, `Order` | `tests/Unit/<Context>` |
| Application | **Every scenario S1–S61**, with in-memory repositories and `FrozenClock` | `tests/Application/<Context>` |
| Infrastructure | Each Eloquent repository against `guixa_test` (constraints, locks, numeric mapping) | `tests/Integration` |
| HTTP | Role gates, wiring, `assertInertia` props, error → 422 and `NotAllowed` → 403 mapping | `tests/Feature` |
| Architecture | A1–A8 | `tests/Architecture` |
| Translations | Every key used in PHP (`__()`, `trans()`) and Vue (`trans()`, `$t`), and every domain error class, has a `ca` translation | `tests/Architecture` |
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
  manager changes in the app. Its value is null until a manager sets it, directly or when opening a cycle as permanent (R4c).
- **D12** Re-allocating after settlement requires an explicit unfinished-only/all choice whenever the product
  has completed or not-collected pickups. Unfinished-only subtracts their effective allocations before splitting
  the remainder. All includes their original ordered claims, retaining original pickup lines and appending
  immutable `PickupAdjustment` records (product snapshot, signed quantity/charge delta, resulting target,
  actor/time) plus matching Wallet corrections in the same transaction. The first money/quantity baseline is
  the original delivered line, then the latest adjustment target. Repeated identical targets append nothing.
  `order_pickup_adjustments` persists those records; reception reads effective quantities, pickup displays
  original lines plus all adjustments. Original charged amount/time and stock/return lines never change.
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
  A new allocation (way A or B, after a reopen) clears a pending pickup's adjustment. Explicit all-pickups
  redistribution replaces a done line's effective reception quantity through a separate adjustment record,
  preserving its original delivered quantity; signed wallet corrections use separately rounded old/new amounts.
- **D17** The first manager is created by an Artisan command calling `CreateFirstManager`, which sends the
  usual invitation and is rejected once any manager exists (R36a: "outside the app").
- **D18** Translations use `laravel-vue-i18n`, so PHP and Vue share the files in `lang/` (constitution, UI), with
  English source strings as keys: code stays in English as the constitution requires, and a missing Catalan
  translation shows English, which the translation test catches before merge.

Dropped: D1 (pickup is a sequence of saved steps, R19–R22). D2 and D3 became spec rules (R4a, R6). D4 was
replaced by R35 and R36b (unlinking keeps the account; deactivation is separate). D5, D6 and D8 became spec
rules (R18, R15 warning, R15 tie rule).
