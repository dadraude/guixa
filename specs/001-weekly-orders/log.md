# 001 — Weekly orders: log

Append-only. See `specs/README.md` for the entry kinds.

## 2026-10-20 · Request

The original request was not kept: it was given in a conversation before this log existed, and no copy
survives. The spec's opening summary (`spec.md`) is the closest record of what was asked; the spec as
written starts at commit `8d84116` (2026-10-19).

## 2026-10-20 · Change

Out of scope (`spec.md`): aligned with the roadmap in `specs/README.md`. Online payments removed,
membership fees became a cooperative fee on product prices, tracking returned containers as stock became
returning containers to their producers, producer confirmation lost its "(v2)" tag. No `R#` or `S#` changed.
Why: each row was reviewed one by one when the roadmap was written (PR #137). Online payments are not
planned because top-ups stay manual (R29). Merged in PR #138.

## 2026-10-20 · Change

`plan.md`: settlement after a producer is distributed became one Application service,
`CycleSettlement::afterDistributed`, shared by `MarkProducerDistributed` and `SendEmptyProducerOrders` (D10).
`CompletePickup` sells its stock lines in stock product id order. No `R#` or `S#` changed.
Why: plan review. Settlement was written only inside `MarkProducerDistributed`, so a cycle settled by the
scheduler would skip dropping lines and closing; stock locks taken in line order let two pickups deadlock.

## 2026-10-20 · Change

`tasks.md`: T18's `SendEmptyProducerOrders` only sends; validating, distributing and settling empty producer
orders moved to T23 with `CycleSettlement`. T30 adds closing and reopening to `CycleSettlement`. T29's
*Done when* adds a test that two pickups selling the same stock products in opposite order both complete.
Why: follows the `plan.md` change above; `CycleSettlement` does not exist before T23.

## 2026-10-21 · Q&A

Q: "How do you want to settle #1? Should a pickup adjustment also block re-distributing, or should that
balance check leave it out?" (R19 says a pickup adjustment "blocks nothing", R18 requires every product
balanced to distribute, so a reopened producer with an adjusted pickup could not be distributed again.)
A: "leave pickup adjustments out of the balance check"

## 2026-10-21 · Change

R18 (`reception.md`): the balance required to distribute does not count adjustments made at pickup.
R19 (`pickup.md`): states that redistributing after a reopen ignores the adjustment. New S61 (`pickup.md`).
Why: the Q&A above, found while reviewing `plan.md`.

## 2026-10-21 · Change

`plan.md`, after the R18/R19 change and a plan review:
- Order lines keep `delivered` apart from `allocated`; the reception's `balancedForDistribution` ignores pickup
  adjustments (D16, S61).
- New `Households` port from Ordering to Membership (`GetHouseholds`): household name and active status.
- First manager: `guixa:create-manager` command and `CreateFirstManager` (D17).
- Lines marked `notDelivered` or `dropped` count in no total, claim, reception or container count.
- Authorization failures throw `Shared\Domain\Error\NotAllowed`, mapped to 403.
- Orders are created with `INSERT … ON CONFLICT` and then locked.
- `OpenPickup` and `SetAllocation` check for an active household (`HouseholdInactive`); `OpenPickup` also
  checks that the cycle is settled and not closed.
- The note that an inactive household is reactivated moved from `UnlinkMember` to `AddMember` and
  `MoveMember`.
- `TransactionRunner::afterCommit`: emails go out after commit.
Why: Ordering had no way to read households (R13, R16, R19); nothing created the first manager (R36a);
`total()` and claims did not exclude dropped lines (R3b, R18); a 422 for a permission failure; two first lines
at once broke the unique order constraint; a closed cycle could get a new pickup (R24b); the reactivation note
was on a use case with no target household; an email could go out for a rolled-back write.

## 2026-10-21 · Change

`tasks.md`, following the `plan.md` change above: new T34 (first manager, after T06). T03 adds
`TransactionRunner::afterCommit`; T04 maps `NotAllowed` to 403. T13's *Done when* races two first lines of a
household with no order. T20 adds `GetHouseholds` and the `Households` port, so T22 now also runs after T20;
T22 checks for an active household and leaves not-delivered lines out of claims. T27: `OpenPickup` checks and
`delivered`. T29 and T31 use `delivered()` and skip dropped lines. T30 covers S61. T32 adds reactivation on
`AddMember` and `MoveMember`. T33 covers S1–S61 and runs after T34.
Why: the plan review above.

## 2026-10-21 · Change

`plan.md` and `tasks.md`: status Draft → Accepted. `specs/README.md`: feature 001 in progress, task status in
Linear (Coop, DRA-150–DRA-183).
Why: the user approved `tasks.md` after PR #141 (plan review) was merged; coding can start with T01.

## 2026-10-21 · Deviation

T01: the Laravel 13 Vue starter kit ships Vite+ (`vp`): Oxlint and Oxfmt instead of ESLint and Prettier,
with Vitest bundled. `constitution.md` (Stack, A9), `plan.md` (Bootstrap, Testing) and `tasks.md` (T01, T02)
now name Oxlint and Oxfmt; A9 is enforced with `no-restricted-imports` and `no-restricted-globals` (for
`fetch`), since Oxlint has no `no-restricted-syntax`. Oxfmt ignores `specs/**`, so it never rewrites
`log.md`.
Why: the user chose to keep the starter kit's toolchain as it ships rather than swap it back to ESLint and
Prettier (asked during T01).

## 2026-10-21 · Deviation

T03: `plan.md` (Shared) now states details it left open: `Clock` lives in `Shared\Domain` (A1 lets Domain use
only Shared Domain, and `LocalTime` consumers need it there); `FrozenClock` lives in `tests/Support`;
`Money::multiply` rounds a negative amount by magnitude (the spec only says "half up"), so a credit mirrors
the charge; `Quantity` is built with `fromString` / `ofThousandths` and throws `InvalidQuantity`; `LocalTime`
needs its own first-occurrence step because PHP picks the second occurrence of a repeated local time.
Why: implementing T03 test-first showed these gaps and one wrong assumption (PHP's native resolution).

T02: the Pest architecture tests need to tell value objects, DTOs and use cases apart, which `constitution.md`
A7 did not say; A7 now states it (value objects in `Domain\ValueObject`, use case = `Application` class with
`__invoke`, any other `Application` class = DTO). T03 must put `Money`, `Quantity` and `LocalTime` in
`Shared\Domain\ValueObject`. The 15 starter-kit components in `resources/js/components` import `@inertiajs/*`,
so A9 lists them as exceptions in `vite.config.ts` (A9 note in the constitution) instead of refactoring them
here; new components are bound by the rule. The architecture tests also add a `tests/Architecture` suite to
`phpunit.xml`, and with no contexts in `src/` yet the loops run empty (Pest reports them as risky) until T03
adds `Shared`.
Why: the rules had to be checkable by shape alone, and fixing the starter kit's components is outside T02.
T04: `Actor` is in `Guixa\Shared\Application` (with `ActorResolver`; `DatabaseActorResolver` in
`Shared\Infrastructure`) and is the class other contexts depend on; `Role` and `NotAllowed` are in
`Shared\Domain`. `app/Http` never imports `Role` (A5): it asks the `Actor` by role name (`hasAnyRole`,
`roleNames`). The `role:` middleware accepts several roles (any of). A domain error answers 422 to JSON requests
and, for Inertia requests, a redirect back with a `domain` error (the Inertia form error convention).
Account ids are UUID v7 (Laravel's `HasUuids`), decided by the user: `users.id`, `sessions.user_id`,
`passkeys.user_id` and `account_roles.user_id` are uuids (original migrations edited in place, no data existed),
and `users.household_id` / `producer_id` are nullable uuids, so `Actor` ids are strings. T04 adds the
`account_roles` table and those two columns, with no foreign keys until their tables exist (T06 and the Catalog
task); `activated_at` / `deactivated_at` stay with T06. The role-area switch links to `/`, `/producer/products`
and `/manage/cycles`, which later tasks create. Its TypeScript types are generated from `Role` and `RoleArea`
(`resources/js/generated/generated.d.ts`, by `php artisan typescript:transform`), not written by hand.
`plan.md` (Shared, HTTP & pages) now says so.
`NavRoleAreas.vue` (new) and `AppSidebar.vue` use `@inertiajs/vue3`; the user kept the starter-kit nav
components exempt from A9, so `NavRoleAreas.vue` is added to the exception list in `vite.config.ts`
(`AppSidebar.vue` was already there).
Why: the plan did not say which layer holds `Actor`, how the 422 reaches Inertia, the type of account ids, or
where roles are stored before Membership exists.

## 2026-10-22 · Deviation

T21: `plan.md` did not say the allocator's input type, how `step` is passed, or what it does with an empty claim list or
off-step quantities. `ProportionalAllocator` takes `ProportionalClaim` value objects (`Ordering\Domain\ValueObject`) (household id, ordered, createdAt,
line id) and `step` as a `Quantity` (1.000 or 0.001); it throws `NobodyOrderedProduct` for no claims or a zero ordered
total, and `InvalidArgumentException` for a target or claim that is not a multiple of the step (a caller bug, since
non-variable products carry whole units). It works in steps and splits `target × ordered / Σ ordered` as
`floor(T/S)·o + floor((T mod S)·o/S)` (S = Σ ordered), so no intermediate product exceeds (Σ ordered)².
`plan.md` (Ordering · Domain) now says so.
Why: the plan left these gaps for the code to fill.

T05: `plan.md` (Testing) now says where the contract tests live and how they are shared: one
`<Noun>RepositoryContract` trait per port in `tests/Support/Contracts`, used by the in-memory test (PHPUnit
`TestCase`) and by the Eloquent test in `tests/Integration` (`Tests\TestCase` with `RefreshDatabase`); a trait,
not a base class, so the Eloquent test keeps the Laravel parent. The `Application` and `Integration` test suites do not exist in
`phpunit.xml` yet; the probe's in-memory test sits in `tests/Unit/Support`, and the task that adds the first real
repository adds its suite.
Why: the plan named `tests/Support/InMemory` but not the contract mechanism, and no real repository exists yet to prove it on.

T24: `plan.md` (Stock) now says how the two entities are shaped and which errors they throw, and lists the
`ListStockProducts` and `ListReturnItems` queries the manage pages need (they return inactive items too, by name).
The edit pages find their item in that list, so no `GetStockProduct` was added; `GetReturnItem` stays T25's snapshot
query for Ordering. Prices and amounts are typed in euros (`6.50` or `6,5`) and converted to cents by `App\Http\EuroAmount`
in the form request, so the use cases still take integer cents; the pages receive them back as decimal strings through
the `StockProductRow` / `ReturnItemRow` props (TypeScript types generated). Deactivation is the only state change (the plan
lists no reactivation). The first real repositories add the `Application` and `Integration` suites to `phpunit.xml`,
as T05 anticipated, and the manager sidebar gets "Stock" and "Return items" links because the role-area link
(`/manage/cycles`) does not exist yet.
Why: the plan did not say how entities validate on create and update, how money crosses the HTTP boundary, or how the
pages are reached.

## 2026-10-22 · Deviation

T06: D15 said invitations use Laravel's password broker. They are now an `Invitation` record (`invitations` table: account, SHA-256
token hash, `sent_at`), issued by an internal `SendInvitation` use case, with a new `InvitationRepository` port. Setting the password
goes through a new `AccountPasswords` port. Accepting checks `now > sentAt + 7 days` against the `Clock`, so S25's expiry runs at the
Application level; a resent or changed-email invitation replaces the row, so the old link stops working. `plan.md` (Membership, D15) now says so.
Why: the broker's expiry is configured for every password reset and it reads the real clock, so S25 could not be tested with `FrozenClock`.

T06: `plan.md` listed no query for the households admin pages. Added `ListHouseholds` and `GetHousehold` (manager only; the latter
returns each member's pending-invitation state and expiry). `plan.md` (Membership use cases) now lists them. `users.household_id` now has a
foreign key to `households` (T04 had left it open until this table existed); `producer_id` stays without one until Catalog.
Why: the pages need to read what the use cases write, and T04 promised the foreign key.

T06: login is refused until `activated_at` is set (and after `deactivated_at`, for T07), through Fortify's `authenticateUsing`
(`App\Actions\Fortify\AuthenticateAccount`). The Membership repositories' contract tests in `tests/Support/Contracts` run in `tests/Unit/Membership` (in-memory) and
`tests/Integration/Membership` (database), using the `Application`/`Integration` suites T24 added.
Why: R34 says an invited person cannot log in until they accept.

T07 (note from T06): passkey login is enabled in Fortify and does not go through `authenticateUsing`, so `CanLogIn` does not guard it.
Once `DeactivateAccount` exists, a deactivated account with a passkey could still log in. T07 must cover that path (R36b).
Why: found in the T06 review; no code changed in T06 because nothing can deactivate an account yet.

## 2026-10-22 · Deviation

T06: Inertia prop DTOs are named `App\Http\<Noun>Row` (`HouseholdRow`, `HouseholdSummaryRow`, `MemberRow`), like T24's `StockProductRow`,
not `App\Http\Props\*Prop`. `plan.md` (HTTP & pages) now says so.
Why: two naming schemes for the same kind of class.

## 2026-10-22 · Deviation

T07: `plan.md` (Membership) now says that "last manager" counts active managers, adds the `LastActiveManager` and `AccountDeactivated` errors
(a deactivated account cannot be moved or linked until reactivated), the `ListAccounts` use case for the accounts admin page, the shared
`DeactivateEmptyHousehold` service, and the manager-row lock behind `RemoveManagerRole`/`DeactivateAccount`. HTTP & pages lists the account routes.
Why: R36a/R36b are silent on deactivated managers (counting them would leave nobody able to manage), on linking a deactivated account, and on how the
accounts page reads its data; the plan had to pick.

## 2026-10-22 · Deviation

T07: the passkey path noted in T06 is covered: `Passkeys::authorizeLoginUsing` asks `CanLogIn`. Also added the `LogOutDeactivatedAccount` web middleware,
which ends the session of an account deactivated while logged in. `plan.md` (Membership, Infrastructure) records both.
Why: R36b says a deactivated account cannot log in; a session it already held would otherwise keep working.

## 2026-10-23 · Change

R35, R36a, Roles (`spec.md`, `stock-wallet.md`), glossary Account: Member stops being a stored role. An account is a member of household H exactly when it is
linked to H, and that link is what lets it order and see the wallet. An account with no household and no manager or producer role cannot log in until a manager links it.
`plan.md` (Actor, Membership login rule) follows; a migration drops the stored `member` rows. No `S#` changed.
Why: review of T07 asked whether linking a manager-only or producer account should make it a member. The user answered: "Tindria sentit treure el rol de membre i que el fet d'estar dins una llar sigui el què et permet fer comanda?"
and, asked whether an unlinked account with no other role can still log in: "No cal, que no pugui entrar fins que el vinculin".
T08: `plan.md` had no way to create a producer with its first cutoff, and Membership had no way to know a producer exists. `CreateProducer`
now takes the name and the initial default cutoff (both required: R5 says every producer has one and the spec names no standard value), and
Membership gets a `ProducerDirectory` port (`exists`) implemented over a new internal Catalog query `GetProducer`, so `InviteProducerAccount`
rejects an unknown producer without touching Catalog's Domain (A4). `plan.md` (context ports, Membership, Catalog) now says so.
Why: R36 needs the account to point at a real producer, and A4 forbids Membership from reading `producers` directly.

T08: added `ListProducers` (admin page) and `GetDefaultCutoff` (the producer's own cutoff page), and `users.producer_id` now has a foreign key
to `producers` (T06 left it open until this table existed). `GetProducerCutoffRule` stays in T10 as the internal query for Ordering.
`plan.md` (Catalog use cases, HTTP & pages, Persistence) now lists them.
Why: the pages need to read what the use cases write, and T06 promised the foreign key.

T08: `CutoffRule` lives in `Domain\ValueObject` (A7 finds value objects by that folder) and counts the days back on the bare pickup date before
resolving the local time, so a daylight-saving change cannot shift the day (S38). `plan.md` (Catalog domain) now says so.
Why: A7's architecture test only recognises value objects in that namespace.

## 2026-10-23 · Change

T08: the producers admin page shows whether each producer already has its account, with the account's email and whether its invitation is
still pending, and shows the invite form only for producers without one. Membership provides it through a new manager query
`ListProducerAccounts`; the controller joins it with Catalog's `ListProducers` into `ProducerRow`, so Catalog never reads Membership (A4).
`plan.md` (Membership use cases, HTTP & pages) now says so. No `R#` or `S#` changed.
User's answer to the review question "should the producers admin page show whether a producer already has its account (with its email) and hide the invite form for those producers?": "Si".
Why: without it a manager only learns a producer has an account by getting the S57 rejection.
T26: `ChargeForPickup` and `GetBalance` take the `Actor` and require a manager, where `plan.md` listed no actor. A movement records who recorded it
(`recordedBy`), and Ordering calls them from a manager's pickup; `GetBalance` stays manager-only until a caller needs more. They take and return integer
cents like the other use cases. `plan.md` (Wallet use cases) now says so.
Why: `Movement.recordedBy` has no value without an actor, and a use case that skips the role check would be the only one.

T26: added `GET /manage/households/{household}/wallet`, which reuses `Wallet/Statement` and carries the top-up and correction forms; `plan.md` listed only the
two POST routes. The manager household page links to it, and members get a Wallet entry in the sidebar. `plan.md` (HTTP & pages) now lists the route.
Why: the forms need a page with the household's balance and movements, and `Manage/Households/Show` is the membership page.

T26: R31 says members see each movement's cycle. `GetWalletStatement` returns `cycleId`, but the page shows no cycle label yet because cycles do not
exist until the Ordering context. The page gets the label when the cycle queries land (with the Ordering work).
For the same reason the correction form has no optional cycle field (R32) yet, although `CorrectionRequest` and `RecordCorrection` accept `cycle_id`; it gets its cycle picker when the Ordering context's cycle queries land, like the label. No task row names the wallet picker on this branch, so tasks.md is unchanged.
The page has no static breadcrumb: it serves both the member route (`/wallet`, member-only) and the manager route, and a static crumb would send managers to a 403.
Why: a raw cycle id is no use to a member.

T26: `wallet_movements` also has a `CHECK` per type (sign, cycle, note) and `recorded_by` references `users`; `cycle_id` has no foreign key (Ordering owns cycles).
`PickupAlreadyCharged` is the domain error for the partial unique index, so a double charge reads as a form error rather than a 500.
Why: the invariants of `Movement` hold in the database too; the cycle table is another context's.
T34: the plan did not name how `CreateFirstManager` knows a manager exists or builds the account. Added `Account::inviteManager` and
`AccountRepository::hasManager()` (in-memory, database and contract test); `plan.md` (Membership, Domain) now says so. The
check and the write run in one transaction. `guixa:create-manager` is `App\Console\Commands\CreateManager` and prints the
domain error and fails when a manager already exists or the email is taken.
Why: the use case needs both to enforce "rejected once any manager exists" (R36a, D17).

T25: `plan.md` had no way to hold a negative quantity, since `Quantity` rejects negatives. Added a Stock value object `SignedQuantity` for
movements, and a `StockLedger` domain service that checks "never below 0" under the `stock_products` row lock (new port method
`StockMovementRepository::availableForUpdate`, plus `forProduct`, `available` and `availableByProduct`). `plan.md` (Stock) now says so.
Why: R27 and R28 need signed movements and one place that enforces the floor for entries, adjustments and sales alike.

T25: `GetStockOffer` returns the same `StockLevelData` as `ListStock`, with an `active` flag, and does not reject a deactivated product; whether
a deactivated product can be added to a pickup is left to T28 (S55 only says an existing line survives deactivation). `SellStock` takes the
`Actor` to record who sold and does no role check, since only Ordering calls it. `plan.md` (Stock use cases) now says so.
Why: the spec is silent on adding a deactivated product, so the query reports the fact and does not decide.

T25: `plan.md` named no route or page for entries and adjustments. Added `GET /manage/stock/{stockProduct}/movements` (`Manage/Stock/Movements`,
with the two forms) and `POST …/entries`, `POST …/adjustments`; the stock index now shows the available quantity (`StockLevelRow`).
`stock_movements.cycle_id` and `order_id` have no foreign key until the Ordering tables exist. `plan.md` (HTTP & pages) now says so.
The adjustment form records no cycle until cycles exist (the controller passes none; `RecordStockAdjustment` already accepts one); T11 adds the cycle picker.
Why: R26 and R27 are manager actions, so they need a way to be performed.

## 2026-10-23 · Change

Added `diagrams.md` (Mermaid): bounded contexts and their ports, model relations and the user paths of manager, member and
producer, drawn from `plan.md`; `spec.md` (Areas) links it. No `R#`, `S#` or plan decision changed.
Why: the user asked for a diagram of model relations, domains and user paths (DRA-184).

## 2026-10-23 · Deviation

T09: `plan.md` listed `GET /producer/products` … without the routes or the product use case result. Added the product routes (list, create, edit,
store, update, `DELETE` to deactivate, `PUT …/reactivation`) and said `ListProducerProducts` returns `ProductData` with inactive products included;
`plan.md` (Catalog, HTTP & pages) now says so. The producer pages reuse the `StockProductFields` form component, since the fields are the same (R1).
Why: R1 names reactivation as a producer action only through the plan's `ReactivateProduct`, which needed a route.

T09: the unit and variable-flag lock (`ProducerCycles::hasUnsettledLines`) is left to T15, which lists "R1 (lock)"; `Product::change` takes every field until then.
`plan.md` (Catalog, Domain) now says so. A product of another producer is refused with `NotAllowed` (R2) through `Actor::assertProducerOf` in each use case.
Why: T09 covers "R1 (fields)" and T15 owns the lock and its port.

## 2026-10-23 · Deviation

T10: `plan.md` did not say what the Catalog queries take or return. `GetOrderableProduct(productId)` returns `?OrderableProductData` (the fields a line snapshots, R10),
null when the product is unknown or deactivated or its producer is deactivated; `ListOrderableProducts(producerId)` returns that producer's active products by name,
empty for an unknown or deactivated producer, so Ordering lists the participating producers itself; `GetProducerCutoffRule(producerId)` returns `?ProducerCutoffRuleData`
(active flag, days before, time). Availability in a cycle (R3c) stays with Ordering (T15). `plan.md` (Catalog use cases) now says so.
Why: R3's catalog part is "active product of an active producer"; the rest of R3 needs cycles, which do not exist yet.

## 2026-10-23 · Deviation

T11: `plan.md` did not say how Ordering reads a producer's rule across the A4 boundary, what `OpenCycle` takes and returns, or what the pickup weekday is before a manager chooses.
Ordering now has its own `CutoffRule` value object, filled by the `ProducerCutoffRules` port (Catalog adapter; null for an unknown or deactivated producer, so `ProducerNotActive` covers
S50's "cannot be made to participate in a new cycle"). `OpenCycle` takes `YYYY-MM-DD` and the producer ids (none allowed, R12a) and returns the cycle id; `addParticipant` leaves a producer
already in the cycle untouched (R5: never recomputed). `ListCycles` and `GetPickupWeekday` are added for the pages. The pickup weekday is Saturday (ISO 6) until changed, seeded by the migration (assumption: the spec's examples use Saturday).
`Cycle` holds only pickup date, participation and cutoff; the other states arrive with their tasks. `plan.md` (Ordering domain, use cases, HTTP & pages) now says so.
Why: R4, R4a, R4c and R5 need these decisions and the plan was silent.

T11: the stock adjustment's cycle picker (R27) lists every cycle and the request checks `cycle_id` exists in `cycles` as friendly validation, and the T11 migration adds the foreign key `stock_movements.cycle_id` → `cycles.id`
(the stock migration had deferred it until the cycles table existed). `wallet_movements.cycle_id` stays a plain id: its migration chose that because cycles belong to Ordering, not because the table was missing.
`plan.md` (HTTP & pages) now says so.
Why: R27 lets an adjustment reference a cycle, and T25 left the picker to T11.

## 2026-10-23 · Q&A

Q: Before a manager configures it, should the pickup weekday default to Saturday, or should opening a cycle be blocked until a manager has chosen one?
A: "a la configuració de la cooperativa s'ha d'indicar el dia de recollida, sense aquest valor indicat no es pot començar un cicle. el què podem fer és demanar aquest valor en iniciar el cicle i al confirmar demanar si és permanent o provisional."

## 2026-10-23 · Change

R4c (`ordering.md`): no default pickup weekday. A cycle cannot be opened without one; opening a cycle when the cooperative has none asks the manager for it, and on confirming they choose permanent (it becomes the cooperative's weekday) or provisional (only this cycle; the next opening asks again).
Why: the Q&A above, raised by the T11 review; it replaces the T11 Deviation's Saturday assumption.

## 2026-10-23 · Change

`plan.md` (Ordering domain, use cases, persistence, HTTP & pages, D11) and `tasks.md` (T11): following the R4c change above, there is no default pickup weekday. `cooperative_settings.pickup_weekday` is nullable and the migration creates the row empty;
`GetPickupWeekday` returns null while unset. `OpenCycle` takes an optional `weekday` and a `permanent` flag: with a cooperative weekday it uses that and refuses a given one (`PickupWeekdayAlreadySet`); without one and none given it
refuses (`PickupWeekdayNotSet`); a given one validates the pickup date, and permanent also saves it in the same transaction while provisional saves nothing. The cycles page shows the weekday picker and the permanent/provisional choice only while the
cooperative has none; the settings page shows "not set".
Why: the R4c decision in the Q&A above. It supersedes the Saturday sentence of the T11 Deviation.

## 2026-10-23 · Deviation

T12 (`plan.md`, Ordering domain and use cases): `Cycle` has no sent state until T18, so `setCutoff`, `closeNow` and `clearCutoff` do not check "not sent" (R6b, S34 frozen) yet; T18 adds it with `send`. `SetCutoff` takes `at` as an ISO 8601 string (no offset means the cooperative's timezone) and rejects anything else with `InvalidCutoff`; a producer absent from the cycle is refused with `ProducerNotInCycle`. `ParticipantData` gains `open` (R6a, from `Cycle::isOpenFor`) so the cycles page and tests can read it.
Why: the plan did not say how `at` arrives, what happens for a producer outside the cycle, or where R6a is observable before T13.

## 2026-10-23 · Deviation

T13 (`plan.md`, Ordering domain, use cases, HTTP & pages): the plan folded zero into `setLine` and listed products through a separate `ListOrderableProducts` call. Now `Order::setLine` takes the snapshot product, the line id and `addedBy`, `Order::removeLine` is the zero quantity, and `Cycle::assertOpenFor` raises `ProducerNotParticipating` or `ProducerClosed` (R3, R8, S2, S51).
`OrderRepository` has `ofHousehold` and `lockFor` (creates the empty order and locks it; the unique `(cycle_id, household_id)` plus `FOR UPDATE` keep concurrent first lines in one order), `CycleRepository` gains `getForShare`, and Ordering has its own `ProductCatalog` port and `OrderableProduct` value object over Catalog's `GetOrderableProduct` / `ListOrderableProducts`.
`GetHouseholdOrder` returns the participating producers (open flag, offered products) with the lines, so the page needs no second call; the controller names the producers through `GetProducer`. There is no `DELETE` route: zero removes (R9), and `RemoveMarkedLine` with its route is T15's.
An account without a household is refused with `NoHousehold` in the use cases and with 403 by `role:member` over HTTP (S33). The running total is display-only in `resources/js/lib/orderTotal.ts` (Vitest).
Why: the plan was silent on these signatures and on how the page gets what it offers.

T13 assumptions the spec does not state: a line changed by another member of the household keeps its original `addedBy` (the plan says "keeps `createdAt` unless the line is new" and names `addedBy` only as a field); availability in a cycle (R3c) and the *will not be delivered* mark (R3b) are not checked yet, they belong to T15; a cycle's cancelled state (R4b) is not checked either, T17 adds it.
Why: `addedBy` records who added the line; the other rules are listed under later tasks in `tasks.md`.

## 2026-10-23 · Deviation

T13 (`plan.md`, HTTP & pages): the lines of a producer that no longer takes part in the cycle (R6c) are shown read-only under that producer's name, and the controller names every producer that has a line, not only the participants (`OrderLineRow` carries `producerName`). This is an interim choice the user made. T15 replaces it with the *will not be delivered* mark and the remove action (R3b, R6c, R8).
Why: until T15 nothing marks those lines, and a heading without a name left the member unable to tell whose they were.

## 2026-10-23 · Deviation

T15 (`plan.md`, Catalog domain, Ordering domain, use cases, HTTP & pages, tables): the plan did not say how the *will not be delivered* mark is derived or who owns the unit and variable-flag lock. Now a `NotDeliveredMarks` domain service (catalog + availability port) returns the marked product ids of an order (product not orderable, unavailable in the cycle, or producer not participating); `GetHouseholdOrder` puts `notDelivered` on every line and stops offering unavailable products, and `RemoveMarkedLine` uses the same service. A line that is not marked is refused with the new `LineNotMarked`; a product that is not in the order is ignored, like a zero quantity.
`SetOrderLine` refuses a product unavailable in the cycle with `ProductNotOrderable` (R3c). `SetProductAvailability(cycleId, productId, available)` takes the product from `ProductCatalog::orderable`, so an unknown or deactivated product is refused with `ProductNotOrderable`, and its producer must be the actor. The `product_unavailabilities` table arrives with a migration; `PUT /producer/cycles/{cycle}/products/{product}/availability` exists, and its page is T19's.
The lock: `UpdateProduct` asks Catalog's `ProducerCycles::hasUnsettledLines`, answered by Ordering's `HasUnsettledLines` through `OrderRepository::hasUnsettledLinesOf` (a line in a cycle with no `settled_at`), and `Product::change` throws `ProductHasUnsettledLines` when the unit or the variable flag would change. `Orders/Edit` replaces T13's interim read-only lines with the mark and a Remove action; marked lines count as zero in the running total.
Assumptions the spec does not state: marking availability does not require the producer to participate in the cycle; with no sent state before T18, every producer order counts as "not sent", so `send` storing `notDelivered` (R3b) and the "only while not sent" checks come with T18.
Why: the plan was silent on these signatures, and T13 had left the interim display for T15 to replace.
T20: `plan.md` (Membership use cases, Ordering) now says that `GetHouseholds` takes household ids and answers name and active status by id (unknown ids left out), that Ordering's `Households` port is `find(householdIds)`, and that `OrderRepository` gains `ofCycle`. `GetCycleSummary` totals the ordered quantity per product (grouped by product id, the name and unit from the line snapshot) and names a household Membership does not know by its id.
Why: the plan named the use case and port but not their shape, and R13 and S52 do not say how an unknown household shows.

T20 (review): `OrderRepository::ofCycle` loads a cycle's lines in one query, and Membership's `HouseholdRepository` gains `getMany(ids)` (one `whereIn`), so the cycle summary does not query per household. `plan.md` records both.
T14 (`plan.md`, use cases, HTTP & pages): `Cycle` has no cancelled, settled or closed state and there are no pickups yet, so `ListMemberCycles` lists every cycle by pickup date with `open` (`Cycle::isOpenForMembers`, R4a) and the household's `lineCount` (R23a, before settlement). The page shows two sections, open for orders and closed to orders. The pending-pickup, cancelled and closed sections arrive with T17 and the pickup tasks, which also decide when a past cycle stops being listed.
`GET /` now renders `Orders/Index` for a member with a verified email and `Welcome` for guests, accounts that are not members and members whose email is not verified, because the role areas send the member area to `/`.
Why: the plan listed the four states before the domain had them, and `/` was already the public welcome page.

## 2026-10-23 · Change

R13 (`ordering.md`): a cancelled cycle still opens for the manager and is shown as cancelled, with no totals. New scenario S62 (R4b, R13). The "Orders" link on the cycles list stays, since it leads to a valid page.
Why: the spec was silent on the summary of a cancelled cycle (found while delivering T17 on top of T20, DRA-187). The user decided: "mostrar-lo com a cancel·lat".

## 2026-10-23 · Change

`plan.md` (use cases, HTTP & pages): `GetCycleSummary` carries `cancelled` and returns no totals for a cancelled cycle (R13, S52, S62); `Manage/Cycles/Show` shows a "Cancelled" notice instead of totals. No new port or table: `Cycle::isCancelled()` exists since T17, and cancelling deletes the cycle's orders.
Why: follows the R13 / S62 change above.

## 2026-10-23 · Change

`tasks.md`: new task T35 (R13 cancelled, S62; after T17 and T20), S62 added to Coverage, and T33 now covers S1–S62 and waits for T35. Tracked in Linear as DRA-187.
Why: follows the R13 / S62 and plan changes above.

## 2026-10-23 · Deviation

T17 (`plan.md`, Ordering domain, use cases, HTTP & pages): cancelling is `POST /manage/cycles/{cycle}/cancel`, not `DELETE /manage/cycles/{cycle}`, because the cycle is kept with `cancelled_at` (D14). `Cycle::cancel` does not check "no producer order sent" (R4b, S37) yet: the sent state arrives with `send` in T18, which adds the check, as T12 did for R6b. A cancelled cycle also refuses `addParticipant`, `setCutoff`, `closeNow`, `clearCutoff` and line changes with `CycleCancelled` (the T13 assumption that T17 adds the cancelled check), and cancelling twice is refused the same way. `CycleData` and `CycleRow` carry `cancelled`, and `OrderRepository` gains `deleteOfCycle`. `HouseholdOrderData` and `OrderRow` also carry `cancelled`, so the member's order page shows a "cancelled" notice and no producers or lines (S37 "members see it as cancelled"). `tasks.md` now splits S37 and R4b: T17 covers "R4b (cancel before sending), S37 (cancel)" and T18 covers "R4b (rejected once sent), S37 (sent)".
Why: the plan did not say which verb or which guards, and the sent state does not exist before T18.

## 2026-10-23 · Change

T17 (`plan.md`, HTTP & pages): cancelling a cycle asks the manager to confirm first, in a dialog that says every household's orders are discarded and it cannot be undone. User decision: "sí, que demani confirmació abans de cancel·lar".
Why: the cancel discards every household's orders irreversibly (R4b).

## 2026-10-24 · Change

`constitution.md` (new UI section): pages are phone first (375 px wide, no horizontal scroll), touch targets are at least 44 × 44 px with nothing depending on hover, labels use plain language with one main action per screen, and a task with a page is not done until the page has been checked at 375 px wide. Applies to the 001 tasks still open; `plan.md` and `tasks.md` are unchanged.
Why: the UX discovery (`docs/ux-discovery/2026-10-24-guixa-discovery.md`, principle 3) found that members' digital skills are mixed and that the old app's lack of phone use is one reason it no longer fits. The user decided: "Yes, add the phone-first UI rule to specs/constitution.md through spec-flow, and link the discovery doc from specs/README.md."

## 2026-10-24 · Change

`constitution.md` (UI section): every text a person reads (pages, emails, validation and domain error messages) comes from a translation in `lang/`, never a literal in PHP or Vue. Catalan (`ca`) is the default and fallback locale and the only one shipped for now; another language is added as more `lang/` files. The existing pages, which have hardcoded English, are brought in line by a new task once `plan.md` says how.
Why: the user decided: "all the application must be translatable, and catalan be the main language for now".

## 2026-10-24 · Change

`plan.md` (Bootstrap, HTTP & pages, new Translations section, Testing, D18): translations use `laravel-vue-i18n`, so PHP `__()` and Vue `trans()` share `lang/`; keys are English source strings in `lang/ca.json`, domain errors move to `lang/ca/errors.php`, Laravel's own messages come from the `laravel-lang` Catalan files, emails go through `__()`, and dates and money are formatted with `ca-ES`. `APP_LOCALE` and `APP_FALLBACK_LOCALE` become `ca`. A new test checks that every key used in PHP and Vue, and every domain error class, has a `ca` translation. If `laravel-vue-i18n` does not fit Inertia 3 and Vite+, `vue-i18n` with messages generated from `lang/` replaces it.
Why: follows the constitution's *Translatable* rule above. The user decided: "Yes, write the translation approach into 001's plan.md as proposed, log it, then propose the task."

## 2026-10-24 · Change

`tasks.md`: new task T36 (Phase 0, after T04) sets up translations as `plan.md` (Translations, D18) says and moves every existing page, layout, component, email and error text to Catalan translations; its Done when asks for Catalan everywhere, each page checked at 375 px wide, and a translation test that fails on an untranslated text. "How to work a task" now says that, once T36 is done, every text a task adds has a `ca` translation and every page it adds or changes is checked at 375 px wide. T33 now waits for T36. Not yet tracked in Linear.
Why: follows the constitution (UI) and `plan.md` (Translations) changes above. The user decided: "Yes, add T36 to tasks.md as proposed, with the general Done-when rule and T33 waiting for it, and log it."

## 2026-10-24 · Deviation

T16 (`plan.md`, Ordering use cases and HTTP & pages): stopping participation is `DELETE /manage/cycles/{cycle}/producers/{producer}` and participating again is `PUT` on the same URL, because `POST` there already is `AddParticipant` and the plan listed `ParticipateAgain` under it without saying how the two differ. Deactivating and reactivating a producer are `POST` and `DELETE /manage/producers/{producer}/deactivation`, like the accounts. `ParticipateAgain` refuses a deactivated producer with `ProducerNotActive` (R3a) and keeps the cutoff the producer had. `Cycle::stopParticipating` does not check "order not sent" (R6c) yet: the sent state arrives with `send` in T18, which adds the check, as T12 and T17 did for R6b and R4b. `StopParticipatingWhereNotSent` skips cancelled cycles.
Why: the plan did not say which verbs, which guards, or what happens to the cutoff, and the sent state does not exist before T18. No Vue controls were added for these routes in T16: the task's allowed surfaces had no `resources/js/pages`; the stop, participate-again and (de)activate buttons wait for a coordinator decision.

## 2026-10-24 · Deviation

T16 (`plan.md`, Catalog `ProducerAccess` and Membership): the producer role is suspended by removing the `producer` row from `account_roles` and kept on `users.producer_id`, so restoring puts the same row back; `AccountRepository` gains `ofProducer(producerId)` to find the account, and `SuspendProducerRole` / `RestoreProducerRole` take only the producer id and ignore a producer without an account. The plan said Login counts a producer role "only while its producer is active" and left the storage open.
Why: Login and the actor resolver already read `account_roles`, so no other query has to learn about producer state.

## 2026-10-24 · Deviation

T18 (`plan.md`, Ordering domain, ports and use cases): `Cycle::send` throws `ProducerOrderSent`, `ProducerNotParticipating` or `ProducerStillOpen`; once sent, `setCutoff`, `closeNow`, `clearCutoff`, `stopParticipating`, `participateAgain` and `cancel` throw `ProducerOrderSent`, and `StopParticipatingWhereNotSent` skips the sent order (R6b, R6c, R4b, S34, S37, S50). `ProducerNotifier` is `canNotify(producerId)` plus `notify(producerId, pickupDate, totals)`; its Membership adapter reads the email through `GetProducerAccountEmail` and mails the totals after commit. `OrderRepository` gains `markNotDelivered` and `notDeliveredProductIds`. `SendEmptyProducerOrders` is scheduled every minute and only sends (T23 adds validate and distribute).
Why: the plan named the use cases and the port but not their shape.

T18 (`plan.md`, `SendProducerOrder`): a producer with no account is refused with the new `ProducerHasNoAccount`, before anything is stored, so the order stays unsent until the manager invites the producer's account. A producer with no line to send needs no email, so the scheduler and the manager can still send it. User decision: option 1, fail when the producer has no account.
Why: R12a says the producer is notified and the docs did not say what happens without an account.

T18 (`plan.md`, HTTP & pages): the `POST …/producers/{producer}/send` route and its button are not part of T18 (the task row lists no page); they come with the later task that adds the send button. Coordinator decision.
Why: the plan lists the route, but T18's allowed surfaces and row have no controller or page.

## 2026-10-24 · Deviation

T18 (`plan.md`, Ordering domain and use cases): `NotDeliveredMarks::of` reads the stored mark (`OrderRepository::notDeliveredProductIds`) for the lines of a sent producer order and derives it for the others, so `GetHouseholdOrder` and `RemoveMarkedLine` keep a sent line marked after its product is reactivated and leave a delivered line unmarked after its product is deactivated (D9). `derivedOf` is the derivation alone, which `SendProducerOrder` and `SendEmptyProducerOrders` use to decide what to store. `SetProductAvailability` is refused with `ProducerOrderSent` once the producer's order is sent, for marking unavailable and available (R3c), which the T15 entry handed to T18.
Why: the plan said `send` stores the mark so later catalog changes do not affect it, which only holds if the stored mark is what is read; and R3c allows marking only while the order is not sent.

T19 (`plan.md`, Ordering use cases and HTTP & pages): `GetProducerCycleSummary` answers the producer's own orderable products with their availability in the cycle; before its order is sent the quantities are null (S8), and once sent they are the totals to deliver with the marked lines left out and no household names (S7, R12a). A cancelled cycle, or one the producer does not take part in, is `CycleNotFound` (404), and an actor with the producer role but no producer is `NotAllowed`. `GET /producer/cycles/{cycle}` renders `Producer/CycleSummary`, which carries the availability toggle (the `PUT` route T15 left without a page). No page links to it yet: the producer's list of cycles is not in T19's row.
Why: R12 says a producer sees nothing before the order is sent but R3c needs the producer to mark availability before then, and the spec is silent on cancelled cycles and on a cycle without the producer.
