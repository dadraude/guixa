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
## 2026-10-24 · Request

> Yes, start with the behavior-preserving member ordering redesign. Update the SDD plan, tasks and log first, keep the UI in Catalan, and leave new features for a separate spec.

## 2026-10-24 · Change

`plan.md` (Member ordering presentation): scoped the redesign to `Orders/Index`, `Orders/Edit`,
presentational ordering components and necessary ordering navigation/layout. Preserve current
requests, drafts, totals, permissions and states; use existing theme primitives and the accepted
Catalan translation architecture. Added component boundaries and focused regression/browser checks.
Why: the user approved a behavior-preserving redesign, so this follows the plan-only route; no `R#`
or `S#` changes, new saved-state model, repeat-order or other new feature is included.

`tasks.md`: added T37 after T14, T15, T17 and T36, with explicit readiness and completion checks;
T33 now also waits for T37. T36 remains unchanged.
Why: the shared translation system is accepted in the plan but absent from the current Vue bootstrap,
Vite configuration and dependencies; the only tracked language file is `lang/en/errors.php`.
Source implementation is pending the translation prerequisite or a user-approved split of T36's
foundation from its app-wide migration. No source changes, test runs or browser checks performed.


## 2026-10-24 · Q&A

Question: "May I split out and implement T36’s shared translation foundation for this redesign, leaving the remaining app-wide migration in T36?"
Answer: "Yes, split out and implement T36’s shared translation foundation, then continue the member ordering redesign. Update the SDD documents first and leave the remaining app-wide migration in T36."

## 2026-10-24 · Change

`plan.md` (Translations and Member ordering presentation), `tasks.md`: split T38's shared translation
foundation from T36; T37 now follows T38, not the app-wide migration. T36 keeps the remaining pages,
emails, framework messages, domain errors and exhaustive coverage. T33 also waits for T38.
Why: the user explicitly approved this dependency split so the member redesign can proceed without
silently expanding into translation of the entire application. Locale defaults and the shared server
locale form one foundation; existing local environment overrides remain untouched.

## 2026-10-24 · Change

T38 (`plan.md`, Translations): implemented `laravel-vue-i18n` 2.9.0 with eager shared JSON
loading and synchronous initial language activation, the Vite PHP dictionary plugin, shared
Inertia locale, Catalan config/example defaults, display-only `ca-ES` formatters and focused
PHP/Vue checks. Client and SSR production builds pass; D18's fallback is not needed.
Existing `.env` overrides remain untouched; installations configured as `en` must explicitly
set `APP_LOCALE=ca` and `APP_FALLBACK_LOCALE=ca` to receive Catalan server errors.

T37 (`plan.md`, Member ordering presentation): implemented cycle cards, responsive labelled
product rows, normal-flow totals and translated ordering navigation. The editor's draft,
save/remove callbacks and arithmetic match the baseline; domain logic, routes and role filters
are unchanged. T36 still owns the remaining application, framework and email migration.

Verification: observed RED before the foundation/presentation changes, then 17 focused Vitest
tests and the standalone PHP translation test (3 assertions) passed. Type checking, affected
lint/format checks, diff whitespace checks and client/SSR builds passed. A fresh independent
verifier reproduced 17 passing tests and type checking and found no change-caused blocker.
Its scratch PHP runner and extra interaction harness were unavailable; the parent reran the
real standalone PHP test successfully and exercised browser interactions separately.

Production assets were inspected with synthetic Inertia fixtures in an isolated localhost
server (no database or real account mutations), at 375 px light and 1280 px dark. No horizontal
overflow was observed; ordering inputs/buttons measured 44 px high. Verified drafts/totals,
Enter and keyboard-button save, marked removal, closed/cancelled states and mobile navigation.
Observed requests retained PUT/DELETE endpoints, quantity payloads and `order,errors` partials.
Screenshots are local session artifacts, not repository fixtures. The temporary server and
verification scratch copies were removed. Database-backed HTTP checks and full CI were not run.

`tasks.md`: recorded local implementation evidence for T38/T37 without claiming CI, native
review or delivery completion. Native review is enabled; preflight needs an explicit untracked
selection excluding the unrelated `.env.swp`. No review approval, commit, push, PR or Linear
mutation has occurred. Delivery slicing remains the user's decision under `ask-on-risk`.

## 2026-10-24 · Request

> Use stacked review slices: translation foundation first, then the member ordering redesign. Complete the remaining checks and native review before committing, and don’t push or open PRs yet.

## 2026-10-24 · Change

Delivery uses stacked-to-main review slices, with T38 before T37. All applicable local checks and
native review must precede commits. Hosted CI remains unavailable without the separately prohibited
push/PR operations; no CI success will be inferred from local checks. An isolated PostgreSQL
database is used for the remaining HTTP checks, never the app or shared test databases.

Verification follow-up: isolated PostgreSQL ordering/role-access HTTP and translation tests passed
(40 tests, 275 assertions); affected PHPStan analysis passed. The task-owned database was confirmed
absent before creation and after deletion, with loopback/exact-database guards before migrations.

First staged slice: 388 authored changed lines before this evidence entry (lockfile excluded).
An isolated checkout of the index, without the redesign or `.env`, passed 13 frontend tests,
type checking, and client/SSR builds. The optional `fontaine` warning is non-fatal; nothing installed.

## 2026-10-24 · Change

T38 native review: the user granted the candidate-specific review. Risk, readability and reliability
returned no findings; the exact provider acknowledgement completed and consumed the review authority.
The reviewed foundation remains staged unchanged; no commit or publication has occurred.

T37 verification follow-up: checked empty cycle and producer lists, the existing domain-error alert,
and 200% text at 375 px with actual production assets. The enlarged cycle heading/date initially
clipped despite a zero page-overflow measurement. Added break opportunities to the heading and card
text; visual and element-boundary rechecks pass. No ordering behavior changed. Client/SSR builds,
types, affected lint and all 17 focused tests pass after this correction.

Delivery slicing: separated the cycle-list and editor tests so each stays with its presentation.
The best cohesive split is foundation, cycle list/shared navigation, then editor/product row. The
editor alone is 408 authored source/test lines before verification notes; splitting its row away
from the consuming page would leave an incomplete presentation change. A small size exception is
recommended rather than removing tests or compressing code to satisfy the 400-line guide.

Second-slice check: the cycle-list/navigation delta is 250 authored lines; an isolated index checkout
passed 14 focused tests and type checking. Native review could not start with the uncommitted staged
foundation as its base: it returned `staged_workspace_overlay_recovery_unavailable` and no new
review authority. Work is preserved pending the user's sequencing decision; nothing is committed.

## 2026-10-24 · Request

> Yes, commit only the approved translation foundation now, then complete native review for each remaining slice before committing it. Don’t push or open PRs.

## 2026-10-24 · Change

T38 committed locally as `0c56c738bed223e1e614e1385f6339061d63789e`; its tree exactly matches the
native-reviewed foundation. The user authorized this baseline commit, followed by native review
before each subsequent slice's commit. T37 remains uncommitted; no push or PR is authorized.

## 2026-10-24 · Request

> Yes, commit the cycle-list/navigation slice without native review, then continue with native review of the editor slice before committing it. Don’t push or open PRs.

## 2026-10-24 · Change

The user declined native review only for the cycle-list/navigation candidate and explicitly
authorized its local commit without review. Existing local verification passed; no native approval
is claimed. The editor remains a separate slice requiring native review before its commit.

Cycle-list/navigation committed as `3f3b8e839e166199a9a41c4b79df4741937750fe` under that exception.
The remaining editor candidate contains the presentational product row, consuming page and its
regression tests; previously observed local checks still apply because these source bytes are unchanged.

## 2026-10-24 · Change

T38/T37 local delivery evidence updated in `tasks.md` and the ODD logbook. The user granted editor
review with "1"; readability and reliability returned no findings, and exact acknowledgement
consumed the review authority. Editor commit `9dec36d` exactly matches the reviewed snapshot.
Foundation `0c56c73` was also reviewed; cycle-list/navigation `3f3b8e8` used the explicit review
exception. This documentation-only closeout leaves those source commits unchanged.

Local implementation and the requested checks are complete: 17 frontend tests, 40 isolated PHP
tests/275 assertions, types/lint, affected PHPStan, client/SSR builds and responsive/keyboard/browser
interaction checks passed. Hosted CI was not run; there was no push, PR or Linear update. T36 and
the wider weekly-orders feature remain pending. Existing `.env` overrides were not changed.

## 2026-10-24 · Request

> Yes, rebase the three branches onto current main, fix the CI failure, and push with --force-with-lease only after the required checks pass. Preserve all three PRs and don’t merge anything.

## 2026-10-24 · Change

PRs #191/#192/#193 were rebased sequentially onto `ba2532b`, preserving both append-only log
histories and their separate scopes. The hosted CI failure was reproduced under Catalan: the
domain-error test registered its fixture only in English. A ca/en dataset first reproduced the
failure, then passed when the fixture used the selected locale; production translations are unchanged.
All 31 original implementation files remain byte-identical. The ODD logbook records the correction.

Before leased updates: 55 affected PHP tests/340 assertions, the English locale regression, Pint,
per-slice frontend tests (13/14/17), types, and foundation/final client+SSR builds passed. Hosted CI
will rerun after publication; no success is claimed in advance and no PR merge is authorized.

## 2026-10-24 · Deviation

T22 (`plan.md`, reception use cases and Ordering domain): `AddUnorderedProduct` takes the received total (default 0), and adding a product nobody ordered again replaces its total, because R15 says a product nobody ordered cannot use way A and R14 says its total is only "initially" 0, so otherwise a typo in S40's total could never be corrected. A product somebody ordered is left as it is by `AddUnorderedProduct`. The reception shows a product when it has a line that is not marked or a recorded total; lines marked *will not be delivered* are left out, and `SetAllocation` refuses such a line with the new `LineMarkedNotDelivered`. New errors: `ProductNotInReception`, `HouseholdNotFound` (an unknown household; `HouseholdInactive` stays for a deactivated one) and `ProducerOrderNotSent`. `OrderLine` gains `allocatedByHand`, and allocation-only lines (`ordered` 0) are kept out of the member's order page, `GetCycleSummary`, the totals to send and `lineCountsOfHousehold` through `Order::orderedLines()`.
Why: the plan named the use cases but not these cases; an allocation-only line is not something a household ordered (R16), so R7, R12 and R13 must not show it.

T22 (`plan.md`, `SetReceivedTotal` and `Cycle`): clearing the validation (R17a) and the read-only distributed reception arrive with T23, which owns `validatedAt` and `distributedAt`; T22 only requires the producer order to be sent. The orders are not locked one by one: the cycle `FOR UPDATE` already serialises every reception change against `SetOrderLine`'s `FOR SHARE`. The received-total claims ignore done pickups (D12) because no pickup can be done before the pickup tasks.
Why: those states and pickups do not exist yet.

T22 (`plan.md`, HTTP & pages): the page component is `Manage/Reception/Show`, not `Manage/Reception`, to stay inside the task's allowed `resources/js/pages/Manage/Reception/**`; the `received_totals` table is created by T22's migration, since the earlier migrations only created the columns the ordering tasks used.
Why: the paths the task was allowed to write, and the table was not there.

## 2026-10-24 · Deviation

T22 review (`plan.md`, `AddUnorderedProduct`): a product somebody ordered is refused with the new `ProductAlreadyOrdered` instead of being left as it is, since R14 only lets a manager add a product nobody ordered. This replaces the "left as it is" wording of the T22 entry above.
Why: a call that silently does nothing hides the mistake.

## 2026-10-24 · Request

> Quan es reparteixen els productes, aquests ja han arribat.
>
> si, i a més a més els productes només han d'estar actius en el moment de fer la comanda.

## 2026-10-24 · Change

T22 (`reception.md` R14 and R16, `plan.md`): a product only has to be active when a household orders it. From the reception on, `AddUnorderedProduct` accepts any product of the producer that nobody ordered, `GetReception` offers deactivated products too, and `SetAllocation` snapshots a deactivated product as it is now (R10). Catalog gets one internal query, `ListProducerProductsForReception`, behind `ProductCatalog::allOf`, which the reception reads every product through; ordering keeps `GetOrderableProduct` and `ListOrderableProducts` unchanged. A product shows in the reception once it has a line or a recorded total.
Why: the user's rule above.

T22 (`reception.md` R14, `plan.md`): the producer does not have to be active either. This is deduced from the user's rule and from the T18 entry, where deactivating a producer leaves a sent producer order participating (S50), so its reception already exists and its goods have arrived. The user did not say it in these words; the coordinator confirmed the deduction.
Why: the same reasoning as for the product.

## 2026-10-24 · Request

> vull que la pàgina sigui moderna però que recordi al passat on tot eren horts

## 2026-10-24 · Q&A

Question:

> Vols aplicar aquesta identitat només a l’aplicació de gestió existent, o també crear una web pública de presentació de la cooperativa?

Answer:

> De moment només a l’aplicació de gestió existent. Actualitzem el pla seguint l’SDD i revisem cada document abans de passar al següent.

## 2026-10-24 · Change

`plan.md`, **Application visual identity**: added a proposed, behavior-preserving management-app
presentation amendment with the olive/cream/terracotta palette, typography, light/dark/system
appearance, a member-ordering visual pilot, wider existing-screen rollout and verification criteria.
Why: the user approved the modern agricultural-heritage direction and explicitly limited it to the
existing management application, with document-by-document review.

Route: **plan only** under `specs/README.md`. This amendment is pending approval and separate from
T37, whose original no-global-theme-replacement boundary is preserved. No public website, new
functional requirements, routes or domain changes are included. The accepted plan baseline remains
in force. `tasks.md`, the spec/area documents, constitution and application code are unchanged.
Next: review this plan amendment; only after approval, update and review `tasks.md` separately.

## 2026-10-24 · Q&A

Question:

> Aproves aquesta ampliació del pla per passar a les tasques?

Answer:

> Aprovo l’ampliació del pla. Actualitza les tasques i presenta-me-les per revisar-les abans de començar a implementar.

## 2026-10-24 · Change

`plan.md`, **Application visual identity**: recorded the user's explicit approval of the amendment
and updated its status to Accepted. Its scope and design are unchanged; task approval is still
required before implementation.

`tasks.md`: proposed T39–T42, linked to the accepted plan and existing rules/scenarios. T39 combines
the scoped theme with the ordering pilot and stops for explicit visual approval. T40 covers existing
dashboard/wallet/producer pages, T41 existing manager pages, and T42 settings/authentication plus
the final cross-role check. Each task includes its own regression and rendered evidence. Added a
tracked-page/shared-presentation inventory and a proposed T42 dependency for closure task T33.
Why: the user requested the next SDD document for review, not implementation. T37/T38 history,
T36 scope and existing scenario ownership remain unchanged. No new domain requirements or feature
pages are introduced; `Welcome` remains excluded.

All new tasks are pending approval and unstarted. No application code, tests, dependencies, Linear
issues, commits or remote state changed. Next: review the task amendment before starting T39.

## 2026-10-24 · Q&A

Question:

> Aproves aquestes quatre tasques?

Answer:

> Aprovo les tasques. No implementis res, primer hem de crear els linears corresponents

## 2026-10-24 · Change

`tasks.md`: recorded acceptance of T39–T42 and the T33 dependency. `plan.md`: synchronized the
task-approval status. The user explicitly withheld implementation and requested Linear tickets
first; all tasks remain unstarted and creation of tickets will not itself authorize implementation.
Why: preserve the user's task approval and explicit execution boundary separately.

The local execution profile identifies Linear workspace `draude`, team `DRA`, project `Coop`, but
does not authorize remote access. No Linear connection has been queried or mutated in this step;
confirmation of the destination and connected session is required before duplicate checks and
creation. No application code changed.

## 2026-10-24 · Q&A

Question:

> Aproves aquesta organització per crear el milestone i els quatre tiquets, mantenint pendent la reconciliació de T37/T38?

Answer:

> Sí, aprovo aquesta organització. Crea el milestone i els quatre tiquets amb les dependències, deixant pendent la reconciliació de T37/T38. No implementis res encara.

## 2026-10-24 · Change

Created the approved **Application visual identity** milestone
(`cda681c9-a478-422a-9693-e45968fe4147`) in the authorized `draude` / `DRA` / `Coop` Linear project,
using the current connected session. Created four root issues: T39 = DRA-190, T40 = DRA-191,
T41 = DRA-192, T42 = DRA-193. No redundant parent or related links were added.

Native blocking chain: DRA-190 → DRA-191 → DRA-192 → DRA-193 → DRA-183 (T33).
The seven existing DRA-183 blockers were preserved. Readback verified project/team, milestone,
root ownership, all requested relations, Backlog state, no assignee/delegate and no ready-for-agent
label. A full project listing confirmed one issue per T39–T42, with no remaining pagination.

`tasks.md`: added confirmed issue links, relationship mapping and execution gates. `plan.md`:
recorded ticket creation without lifting the implementation hold. Why: make the approved SDD
sequence actionable in the tracker while preserving the user's explicit no-implementation instruction.
T37/T38 reconciliation remains pending; no historical issue or Done state was invented. T40 also
requires explicit human visual-pilot approval, not merely completion of T39's code or tests.

The approved documents remain local and uncommitted; issue descriptions disclose the pending
repository publication rather than claiming their new sections are already on main. No application
code, tests, dependencies, commits, pushes, PRs or implementation were performed.

## 2026-10-24 · Request

> Reconciliem ara T37 i T38: comprova què està implementat i com ho hem de reflectir a Linear per deixar ben resoltes les dependències de T39. No implementis res encara.

## 2026-10-24 · Q&A

Question:

> Aproves aquesta reconciliació a Linear i l’actualització de les evidències SDD, mantenint aturada la implementació?

Answer:

> Sí, aprovo la reconciliació. Crea T37 i T38 com a Done amb les evidències, actualitza les dependències de T39 i l’SDD. Mantén T39 en Backlog i no implementis res encara.

## 2026-10-24 · Change

Created retrospective Done records in the authorized Draude / DRA / Coop Linear project:
T38 = DRA-194 in Phase 0 — Foundation; T37 = DRA-195 in Phase 3 — Cycles and ordering.
Their descriptions retain original scope and separate fresh checks from historical delivery evidence.
Native prerequisites: DRA-153 → DRA-194; DRA-168, DRA-169, DRA-170 and DRA-194 → DRA-195;
DRA-194 and DRA-195 → DRA-190 (T39). No parent-child or redundant related links were added.
The existing DRA-190 → DRA-191 → DRA-192 → DRA-193 → DRA-183 rollout remains unchanged.
T39 and the Application visual identity milestone now describe reconciliation as resolved, while
preserving Backlog and the explicit implementation hold. T36 remains separate, not a T39 prerequisite.

`plan.md`: replaced the stale no-evidence-yet statement with pointers to actual verification and
recorded the completed prerequisite reconciliation without changing the approved design.
`tasks.md`: added retrospective issue/milestone/dependency mappings, replaced stale pre-rebase
completion evidence with current commit identities, and resolved the pending T39 tracker note.
Why: the user approved reconciliation of delivered work, not reimplementation or starting T39.

Observed during reconciliation: commits `3356bc5`, `b679ade`, `b4f1a60` and `0a9e26d` are ancestors
of local merged HEAD `2553943b1de93bdd6eaf49bf44927649a0162b9a`; relevant source is unchanged since
`0a9e26d`. Five focused frontend suites passed 17 tests, independently rerun by the parent. Command:
`pnpm test resources/js/lib/i18n.spec.ts resources/js/lib/orderRows.spec.ts resources/js/lib/orderTotal.spec.ts resources/js/pages/Orders/Index.spec.ts resources/js/pages/Orders/Edit.spec.ts --no-cache --configLoader runner --fsModuleCachePath <external-scratch>/vitest-cache`.
The external test scratch directory was removed after verification.

Historical delivery verification recorded PRs #191/#192/#193 merged with passing CI. Earlier
backend/browser/build checks and native-review results remain historical evidence, with original
snapshot limitations; none was rerun or newly reviewed during this reconciliation. In particular,
the original no-publication statements above describe their earlier point in time and are preserved.
No application code, tests, dependencies, commits, pushes or PRs were changed or created. The SDD
updates remain local and uncommitted; Linear descriptions explicitly disclose pending publication.

## 2026-10-25 · Deviation

T23 (`plan.md`, `CycleSettlement`): the plan called it an Application service with `afterDistributed(cycle, settledNow)`, but architecture test A6 allows an Application class one public method, `__invoke`, so it is invoked as `CycleSettlement($cycle, $settledNow)`. `SendEmptyProducerOrders` builds it from the repositories it already receives, so its constructor is unchanged. `Order::drop(marked)` takes the marks `NotDeliveredMarks::of` returns instead of `drop(producerIds not participating)` plus the stored ones: a producer that does not participate is never sent, so its lines are already marked by derivation, and one set covers both (S28).
Why: the architecture rule, and one source of truth for what is dropped.

T23 (`plan.md`, `Cycle`, `ReopenProducerOrder`): the task says reopening before settlement only, and T30 owns it afterwards (S30, `reopenIfAllocated`, D12). Until then `Cycle::reopen` refuses a settled cycle with the planned `CycleAlreadySettled`, and a producer that is not distributed with the new `ProducerNotDistributed`. New `ProducerDistributed` makes a distributed reception read-only for `SetReceivedTotal`, `AddUnorderedProduct`, `SetAllocation`, `ValidateReception` and `MarkProducerDistributed` (R18). Reopening keeps the validation, since R17a clears it only when a received total changes; `AddUnorderedProduct` sets a received total, so it clears it too, while `SetAllocation` does not. Distributing checks, in order: sent, not distributed, validated (`ReceptionNotValidated`), balanced (`ReceptionUnbalanced`).
Why: the plan listed the errors for validate and balance but not these cases, and R17a/R18 are silent on their order and on reopening.

T23 (`plan.md`, `Order` pickup): `PickupStatus` has only `none`, `pending` and `nothingToCollect`; the done states arrive with the pickup tasks. At settlement an order with a line left whose allocation is above 0 is `pending`; an order whose lines are all 0 or all dropped is `nothingToCollect` (R24: nothing to hand over, so it must not wait for a manager); an order with no line at all stays `none`. Closing the cycle when every pickup is nothing to collect (R24b) is T30's.
Why: R19 gives a pending pickup to households with an allocation and R24 closes the others; the plan did not say what an order with no line or only dropped lines becomes.

T23 (`plan.md`, HTTP & pages): the reception page gets the validation state as a separate `distribution` prop (`validated`, `distributed`, `settled`) instead of new fields on `ReceptionRow`, to leave the generated `resources/js/generated/generated.d.ts` untouched. Manager-side error messages (`ReceptionNotValidated`, `ReceptionUnbalanced`, `ProducerDistributed`...) show the exception text, as the other reception errors do until T36 translates them; the page text is English like the rest of the reception page.
Why: the generated types were outside the task's allowed paths, and the reception page and its errors are not translated yet.

## 2026-10-25 · Deviation

T27 (`plan.md`, HTTP & pages): `Manage/Pickups/Index` lists pickups through the new `ListPickups` (`PickupSummaryData`: household, status, total) instead of `GetCycleSummary`, which only returns ordered totals per producer and has no pickup status. The page also lists the active households without a pickup, so a manager can open one (R19, S18).
Why: the plan named a use case that cannot supply the list the page needs.

T27 (`plan.md`, `GetPickup`, R21): `GetPickup` returns the lines and the total; the household's balance is not an Ordering port. `PickupController::show` reads it with Wallet's `GetBalance` (an Application query meant for Ordering and Membership, so architecture test A5 allows it) and `PickupRow::from` derives the resulting balance as balance − total. Stock and return lines in the pickup arrive with T28, and the Ordering `WalletLedger` port with T29.
Why: R21 asks the pickup to show the balance, `HouseholdBalances` belongs to the Membership to-settle list (T32), and inventing an Ordering port for a read was avoidable.

T27 (`plan.md`, `OpenPickup`): no `Cycle::closed` or `CycleClosed` exists until T30 closes cycles, so `OpenPickup` requires a settled cycle (`CycleNotSettled`) and nothing more about closing. `OpenPickup` also rejects a household whose order already has a pickup with `PickupAlreadyOpen`; an existing empty order (pickup `none`) becomes pending. `AdjustDeliveredQuantity` on an order that does not have a pickup throws `PickupNotPending`; on a household with no order, `PickupNotFound`.
Why: the task row names a closed cycle that cannot exist yet, and R19 is silent on a second open.

T27 (`plan.md`, `PickupData`): `PickupData` embeds `OrderLineData` with the optional pickup fields (`allocated`, `delivered`, `adjusted`, `amountCents`) and is shared by `GetPickup` and the member's `HouseholdOrderData::pickup` (R23a), whose `householdName` is null. `OrderLine::withAllocation` keeps `delivered` for now; clearing it when a new allocation arrives (D16) is T30's, with the reopen after settlement.
Why: one shape serves the manager and the member, and no allocation can change after settlement until T30.

T27 (`plan.md`, reception): the reception read model carries `delivered` per line and product, `difference = received − Σ delivered()` and `balanced` follow it, and `Reception::balancedForDistribution` ignores pickup adjustments; `ReceptionProductData` and `ReceptionProductRow` add `delivered`, `balancedForDistribution` and `hasAdjustments`, and the allocation data add `delivered` and `adjusted`. The reception page flags an adjusted product and enables Distribute by `balancedForDistribution`.
Why: R19 and D16 ask the adjustment to show and flag in the reception while blocking nothing.

## 2026-10-25 · Deviation

T28 (`pickup.md` R20, R20a, `plan.md` `Order`): the user decided the three points the spec left open.
1. A deactivated stock product cannot be added to a pickup (`StockProductInactive`); a deactivated return item can. A line that already exists survives the deactivation (S55) and can still be removed, but adding more of a deactivated stock product is also refused.
2. Adding a stock product or return item that is already on the pickup adds to the line's quantity. The stock check (R20) applies to the combined quantity, and the line keeps the price or amount it snapshotted when it was first added.
3. Stock and return lines change only while the pickup is `pending` (`PickupNotPending`). The available stock is the quantity on hand: stock is not reserved by other pending pickups, and the contention between them is checked again when the pickup is completed (R22, S45, T29). Quantities must be above zero (`InvalidLineQuantity`): whole units for a non-variable stock product, up to 3 decimals for a variable one, whole units for a return item. Removing a line removes it whole, and removing one the pickup does not have throws `StockLineNotInPickup` or `ReturnLineNotInPickup`.
Why: R20 and R20a are silent on all of this, and `log.md` (T25) deferred the deactivated product to T28.

T28 (`plan.md`, ports): Ordering gets `StockCatalog::offer` (a `StockOffer`: the snapshot fields, `active` and the available quantity) and `ReturnItemCatalog::find` (a `ReturnItemOffer`), both returning null for an unknown id; their adapters `StockModuleStockCatalog` and `StockModuleReturnItemCatalog` sit in `Ordering/Infrastructure/Stock` and call `GetStockOffer` and `GetReturnItem`. `Order::addStock` and `addReturn` take the line id as a last argument, as `setLine` does, and `PickupLocker` finds and locks the pickup for the four use cases. `PickupData` gains `stockLines` and `returnLines` and its `totalCents` includes them (`Order::total`). The tables are `order_stock_lines` and `order_return_lines`; routes are `POST …/pickups/{household}/stock-lines`, `DELETE …/stock-lines/{stockProduct}` and the same for `return-lines`.
Why: the plan named the aggregate methods and the ports without their shapes.

T28 (`plan.md`, page): `Manage/Pickups/Show` gets a stock section and a return section (`components/pickup/StockLines.vue`, `ReturnLines.vue`) with a labelled select, a quantity field and a remove button per line, all 44 px high. They are editable only while the pickup is pending. `PickupController::show` passes `stockProducts` (active only, with the available quantity) and `returnItems` (all, since a deactivated one can be added), and `PickupRow` carries `stockLines` and `returnLines`. Errors show through the page's existing `domain` error.
Why: the page is where a manager adds and removes the lines (R20, R20a), and the product decisions above set which options it offers.

T28 (`plan.md`, review fixes): the member's order page lists the pickup's stock and return lines read-only, since the pickup total includes them (R22, R23a). The quantity fields accept at most 100000 for a return line and 11 characters for a stock line, and their validation errors show next to the fields. `ReturnItemRow` gains `amountCents` so the page formats amounts as euros. The seven new domain errors have Catalan messages in `lang/ca/errors.php`; `lang/en/errors.php` stays empty by convention. `EloquentOrderRepository::ofCycle` loads the stock and return lines of all its orders with one query each.
Why: an independent review found the member page, the error messages, the unbounded integer, the extra queries, the money format and the snapshot test gaps.

T28 (`plan.md`, review fixes): the add forms post `stock_quantity` and `return_quantity` instead of `quantity`, so a rejected quantity shows only in its own section and never mixes with the delivered-quantity form's `quantity` error.
Why: all three forms shared one error key, so one rejection appeared under both the Stock and Returns sections.

## 2026-10-25 · Deviation

T29 (`plan.md`, `CompletePickup`): the `StockSeller` and `WalletLedger` ports live in Ordering's Application layer, not in its Domain, because both take the `Actor` (Stock's `SellStock` and Wallet's `RecordTopUp` and `ChargeForPickup` record who acted) and architecture rule A1 keeps `Shared\Application` out of Domain. `StockSeller::sell` takes the `StockLine` and raises Ordering's `NotEnoughStock` when the stock is short, since A4 keeps Stock's own error out of Ordering; `StockModuleStockSeller` re-reads the stock to tell a shortage from any other refusal. `Order::complete` returns the total and stores `pickup_done_at` and `charged_cents`, columns that already existed.
Why: the plan named the ports without their layer or shape, and the domain must stay free of application types.

T29 (`plan.md`, `CompletePickup` step 4): the use case does not close the cycle and does not assert that it is not closed. `Cycle` has no closed state until T30 (`log.md`, T27), and a closed cycle has no pending pickup anyway.
Why: the task row for T29 does not list R24b, and closing belongs to T30.

T29 (`plan.md`, HTTP & pages): `POST …/pickups/{household}/complete` takes an optional `top_up` in euros (the same format as the wallet top-up) and redirects to the pickups index. `Manage/Pickups/Show` gets `components/pickup/CompleteForm.vue` while the pickup is pending. `PickupNotPending` and `InvalidTopUpAmount` get Catalan messages in `lang/ca/errors.php`; the translation test's list is not extended because `tests/Unit` was outside this task's surfaces.
Why: R21 asks for the top-up at the same time as the completion, and a double completion (S22) shows as a form error.


## 2026-10-25 · Q&A

Q: "For T30, if a pickup was allocated 1 kg but adjusted to 1.120 kg before being marked ‘not collected’, which quantity should be charged? R24a says allocations, while plan D16 says charges use the adjusted quantity."
A: "Charge the adjusted 1.120 kg"

## 2026-10-25 · Change

T30 (DRA-180): R24a now explicitly charges delivered quantities including pickup adjustments when marked not collected, matching the decision above and D16. Stock and return lines are still dropped.


## 2026-10-25 · Q&A

Q: "When redistributing after completed pickups, should we preserve the existing validation rejections if the remaining quantity would be negative or there are no eligible households to receive it?"
A: "when this happens, ask what to do. sometimes we want to do it only on not completed or all"

Q: "If the manager chooses ‘all pickups’, should completed pickups remain frozen with differences handled through corrections, or should the app change their quantities and automatically adjust their wallet charges?"
A: "Change quantities and adjust charges automatically"
Correction: "sorry, for the last one we should choose first option so we se the change as a new line for each new adjustmen"

Q: "should ‘all pickups’ update their quantities while adding new wallet correction lines, or leave completed quantities unchanged and let the manager enter corrections separately? Both preserve the original charge."
A: "the compelted ones should have new lines added and charged, but the not completed can be updated"

## 2026-10-25 · Change

T30 (DRA-180), R18/R23, S63/S64 and D12/D16: the latest answer above is authoritative. Redistribution
requires an explicit unfinished-only/all choice when done pickups are affected. Pending lines update;
done pickups retain original lines and append signed adjustment lines with atomic wallet corrections.
Subsequent changes use the latest effective quantity/amount, so identical repeated requests record nothing.
Stock/returns and original completion/charge history remain unchanged; closed cycles remain final.

## 2026-10-25 · Deviation

T30 locking: finalization now takes the cycle FOR UPDATE before the pickup order lock. The planned shared
cycle lock would require an upgrade when closing and can deadlock two final pickups. Ordinary pickup edits
retain FOR SHARE. The concurrent final-pickup integration test verifies persisted closure and both completions.
The focused Inertia HTTP test needed a Vite manifest, so a local asset build was run as its prerequisite.
The copied task-local environment used English locales; only that ignored task environment was corrected to
Catalan before testing translations. The source environment was not modified.


## 2026-10-25 · Deviation

T30/S64: when an explicit all-pickups target equals the original delivered weight but differs from the
original allocation, reception still needs a stored target. A zero-quantity/zero-charge reconciliation line
records that change; the wallet records no zero movement. Once both effective quantities equal the target,
repeating it appends nothing. The UI distinguishes the original charge from the current total and labels
zero-charge reconciliation explicitly. Final pickups show their current wallet balance without subtracting
the already-recorded pickup charge a second time.

## 2026-10-25 · Verification

T30 focused backend validation: 289 tests / 1467 assertions passed across the affected application,
integration and HTTP files. Final lifecycle guards (new household allocations after settlement and
removal of a dropped line from a frozen pickup) then passed with 112 tests / 366 assertions in the
four affected application files. Six frontend files passed 34 tests; the final dialog touch-target
change passed its six component tests again. Affected PHPStan and frontend lint passed; Pint applied.
Full suites and build/lint gates remain CI responsibilities.

At 375px, task-local Vite fixtures rendered the actual manager pickup, pending pickup, pickup index,
reception, member pickup and cycle pages without horizontal overflow. The redistribution dialog kept
focus inside, supported Escape cancellation and sent the explicit all-pickups choice; all three buttons
measured 44px. Its redundant small icon close control is hidden. This is fixture rendering with mocked
Inertia props, not authenticated end-to-end coverage. Artifacts and harness are ignored under
`.vitest/dra180-visual/` (check.mjs, evidence.json and *-375.png). An initial harness CSS scan issue and
an animation-time measurement failed browser checks; after stopping, the user explicitly authorized
one corrected run, which passed. The final scoped close-icon fix was then validated successfully.


## 2026-10-25 · Change

Glossary (`spec.md`, Producer): a producer grows, gathers or makes the products it supplies, and has at most one account instead of exactly one.
Why: a producer exists without an account until its invitation is accepted, as the code already allows; and the Commissions grilling (`docs/commissions/2026-10-25-commission-decisions.md`) excluded resellers from the term.


## 2026-10-25 · Deviation

T32 (`plan.md`, Membership use cases): `GetHouseholdDeactivationWarning` is a separate read query that returns the balance and pending pickups before the manager submits `DeactivateHousehold`; the command then unlinks members and deactivates the household in one transaction. The warning is rendered on the household page.
Why: a read query lets the manager review current settlement data without making the deactivation command two-phase or storing warning state. R35a and S58 behavior are unchanged.


## 2026-10-25 · Deviation

T32 (`plan.md`, Membership): concurrent deactivation and member linking need one transaction-scoped Membership lock before the use cases read household or account state. Account deactivation takes it before the manager-row lock, avoiding an inverted lock order.
Why: a member added or moved while a manager deactivates the target must see its committed inactive state and reactivate it; pre-lock snapshots could leave a linked member in an inactive household.


## 2026-10-25 · Deviation

T32 (`plan.md`, Membership persistence): existing-account detail saves retain the stored household link; `saveHouseholdLink` persists only an explicit link change made by a serialized Membership command. Creating a new account still stores its initial link.
Why: a role, invitation or other account-detail save made from an earlier snapshot could otherwise restore a link after deactivation or a move. The user approved this bounded persistence change; invitation and password transaction flow is unchanged.


## 2026-10-25 · Deviation

T39 (`plan.md`, Application visual identity): the explicit pilot boundary is a reactive layout opt-in
for `Orders/Index` and `Orders/Edit`, provided to the portaled sheet, dropdown, dialog, tooltip and
toaster. A class on each portal root carries the same semantic tokens outside the shell subtree;
the body and root receive no pilot token override. This was needed because Reka and Sonner render
some content under `body`, beyond the layout's inherited CSS variables. Navigation to another page
removes the opt-in; no preference storage or ordering behavior changed.


## 2026-10-25 · Deviation

T39 (`plan.md`, Visual system): at 200% text, the icon beside the order title left too little width
and split the Catalan heading mid-word. The header now wraps the icon above the title when needed.
Rendered 375 px evidence also exposed a light control border below 3:1; the scoped border token was
darkened to meet the boundary requirement. The task-local visual fixture initially omitted the
existing bundled Instrument Sans face, so a local build was used only to provide that asset, and
the evidence distinguishes the mocked browser fixture from the HTTP regression tests.


## 2026-10-25 · Deviation

T39 visual review: the shared `InputError` red utility colors missed 4.5:1 on the pilot cream and
dark backgrounds. The order editor now scopes its error text to the pilot destructive token without
changing other pages. The product quantity input no longer overrides the semantic input border;
its pilot fill uses the card color so the light and dark unfocused boundaries reach 3:1. Portaled
dialog and sheet backdrops now carry the same reactive pilot boundary as their content, including
reduced-motion handling. A true 375 px CDP viewport replaced cropped command-line Chrome mobile
captures; Welcome and both ordering appearances showed no horizontal page overflow. The task-local
visual evidence was refreshed with computed colors, focus and motion measurements.


## 2026-10-25 · Deviation

T39 post-publication visual feedback: “Comprovant el mode dark, veig que és molt verd tot i costa
diferenciar més els elements.” The dark-only pilot tokens now use stepped neutral charcoal/slate
canvas, cards, sidebar and popover surfaces with warm off-white text; olive stays on primary,
selected and focus states. The quantity input has a darker neutral inset, and the dialog panel
uses the popover surface to separate it from the canvas. The light token block, scope and ordering
behavior are unchanged. This adjusts the accepted pilot's dark presentation before visual
approval of any wider rollout.


## 2026-10-25 · Deviation

T39 visual evidence correction: review found that the normal-motion dialog screenshot had been
taken during its entrance animation, making underlying card text appear through the panel. The
user then said “ok!” to one additional review round for the correction. The task-local CDP harness
now waits for an open state, opacity 1 and finished root animations before capturing the dialog or
user menu.
The refreshed screenshots show settled panels; the palette and application behavior did not change.


## 2026-10-25 · Change

T40 (`plan.md`, `tasks.md`, Application visual identity): the approved T39 pilot now extends through
the existing Dashboard, Wallet/Statement and producer pages after the explicit visual approval
recorded on DRA-191. The plan records one reused route opt-in and presentation classes; the task
records focused and rendered evidence in `docs/visual-checks/dra-191.md`. No R#/S# or product
behavior changed. CI and independent review remain separate completion gates.


## 2026-10-25 · Deviation

T40 (`plan.md`, Visual system): the existing shared `InputError` utility colors did not use the
approved scoped destructive pair on the newly themed wallet and producer forms. The T40 page
boundary now applies that existing token to form errors without restyling excluded pages.
Mobile wallet and product tables repeat their original column headings beside each value; those
new visible labels use the existing translation mechanism and narrowly added `lang/ca.json` keys.
No value, form request or domain state changed.


## 2026-10-25 · Deviation

T40 (`plan.md`, Verification and review gate): a valid 59-character product name without spaces
extended past the producer cycle summary panel at 375 px. The name now wraps within that panel;
the task-local browser fixture records both unsent and sent long-name states. The review also
expanded 200% text and keyboard-focus evidence to every T40 view, including both wallet roles
and controls below the initial viewport. Product data and availability requests are unchanged.


## 2026-10-25 · Change

T40 (`plan.md`, `tasks.md`, Application visual identity): current-status sentences that still
described T40 as unstarted or T39's approval as pending were reconciled with the recorded pilot
approval and T40 implementation for independent review. Explicitly historical planning snapshots
remain historical. This changes status documentation only.


## 2026-10-25 · Deviation

T40 (`plan.md`, Verification and review gate): the first 200% text sweep found that the manager
wallet form grid kept an intrinsic minimum width, placing its cards 36 px beyond the 375 px
viewport even though document scroll width stayed 375 px. The narrow layout now uses a zero-minimum
grid column; the browser fixture asserts panel bounds in addition to document overflow. Both
manager forms and their offscreen controls remain reachable without changing requests or amounts.


## 2026-10-25 · Deviation

T40 (`plan.md`, Verification and review gate): at 1280 px with 200% text, the producer product
table's status and action cells extended beyond the viewport although document and panel bounds
appeared valid. A task-local browser probe first failed with action cells reaching x=1641.81.
The product list now uses a container query to present rows as cards when six columns cannot fit;
normal 1280 px text still uses the table. The fixture checks table-cell edges and horizontal
visibility of the keyboard-focused action. No product status, action destination or form request
changed.


## 2026-10-25 · Deviation

T40 (`plan.md`, Verification and review gate): the wallet movement table in both roles overflowed
its panel at 200% text on 1024 and 1280 px desktops. A browser probe first failed at 1280 px with
the table ending at x=1280 beyond panel content x=1152 and the main clipping edge x=1264. Wallet
movement rows now reflow as cards when their container cannot hold the columns; normal 1280 px
text retains the table. The fixture checks text and table bounds against panel content and clipping
ancestors, plus focused-control horizontal visibility. Wallet amounts, roles and requests are unchanged.


## 2026-10-25 · Deviation

T40 (`plan.md`, Verification and review gate): both manager wallet forms remained side by side at
1024 px with 200% text despite each card being too narrow for labels and buttons. A task-local
browser probe first failed at 1280 px when correction button text reached x=1168.73 beyond card
content x=1152; the 1024 px cards also showed overlapping labels. The form grid now stacks by
available container width, and button text wraps within its card. Normal 1280 px text retains two
columns. The fixture checks headings, labels, inputs and button text against panel content, plus
neighboring producer forms at enlarged desktop text. Form requests and wallet amounts are unchanged.


## 2026-10-25 · Deviation

T40 (`plan.md`, Verification and review gate): at 1024 px with 200% text, the unsent producer
cycle summary's side-by-side availability forms squeezed “Pa de pagès” to a zero-width name box
and “Mel de muntanya” to 26.625 px. A browser probe failed before the fix. The rows now stack by
available list width; both names have 286 px boxes and their text and controls fit within the row.
The visual fixture covers all eight T40 views at 1024 px/200% in both themes with keyboard focus
and relevant text/container bounds. Availability requests and sent quantities are unchanged.


## 2026-10-25 · Deviation

T41 (`plan.md`, Application visual identity): the approved shared theme reached the existing
manager routes, but the stock and return-item tables could not retain their desktop columns on
phones or at 200% text without obscuring labels and actions. Their rows now reflow by available
container width and repeat translated column names visually inside each cell; the desktop table
semantics and action destinations remain. The initial 156-case rendered sweep also found six
controls outside the viewport in Accounts, Household Show and Cycles at 375 px/200% text.
Manager-page-width form/list stacking brought those controls inside the viewport and gave
account names, emails and household forms usable space at 1024 px/200% text. The final visual
matrix checks the affected cases in light and dark. No data, request, validation or role rule
changed.


## 2026-10-25 · Deviation

T41 (`plan.md`, Verification and review gate): independent review filled a cycle cutoff at
375 px/200% and found the native datetime-local control clipped after `06/11/202`, hiding
the remaining year digit and time. The reviewer also measured the stock adjustment cycle
select at x65–352 beyond its form content edge x311 and card edge x343, although document
scroll width stayed 375. The cycle form now gives date and time separate required controls
with a readable full-value preview, while its one submitted `cutoff` field and update action
remain unchanged. The movement select uses a shrinkable grid track and repeats the full
selected option below the control; the existing `cycle_id` and reset behavior remain.
Populated-field browser checks and focused form-serialization tests failed before the fixes
and passed after them in both themes. No domain, validation or permission rule changed.


## 2026-10-25 · Deviation

T41 (`plan.md`, Visual system): the shared destructive Button variant applied white text and
a translucent destructive background in the dark cancellation portal, yielding 3.58:1
contrast on the rendered dialog. A portal-scoped rule now pairs the theme's destructive
foreground with its solid destructive background for this confirmation button. Normal and
hover states measure at least 6.86:1 across light and dark. The shared Button primitive,
other dialog controls, cancellation action, and processing state remain unchanged.


## 2026-10-26 · Deviation

T41 (`plan.md`, Visual system): a third independent review found the pilot-themed cycle
cancellation title overlapping the portaled Close target at 375 px/200% text, while the
two footer actions measured 293 × 36 px at 375 px/100%. The scoped cancellation portal
now reserves vertical space for Close, fits within the viewport, and gives the footer
actions at least 44 px height. It uses tighter phone spacing so the enlarged title and
both footer actions remain fully visible without smaller text. Red/green rendered checks
cover overlap, target geometry, hover contrast, and focus in both themes. No cancellation
request, control destination, or processing behavior changed.


## 2026-10-26 · Deviation

T41 (`plan.md`, Verification and review gate): the shared visual harness checked the full
declared state matrix and found that a long household name made the Accounts Move control
escape its form at 1024 px/100% text. The existing household form now allows its select
to shrink and its controls to wrap inside the account card. The same request field,
button, and action remain. The shared manifest includes this long-content case and
comparable manager rows at 1024 px in both themes; no account behavior changed.
The same sweep found an unbroken long stock name expanding the desktop table at 1280 px
and pushing row actions beyond the viewport. The shared stock/return table style now uses
fixed columns and wraps the name cell; both desktop tables keep their semantics and all
row actions remain visible. The four light/dark long-name desktop captures are retained.


## 2026-10-26 · Deviation

T42 (`plan.md`, Visual system / Verification and review gate): the shared visual harness and
the acceptance probes found presentation defects that the plan's single theme boundary did not
anticipate, all fixed without changing authentication, security or settings behavior. The
settings layout used the viewport `lg` breakpoint, which placed the navigation beside the content
at 1024 px with 200% text although the app sidebar leaves only a narrow column; it now follows
the content width, and its gutter stays 1rem until the content is narrower than 20rem. The
two-factor recovery-codes block stayed focusable and readable by assistive technology while
collapsed (zero height, transparent); it is now not rendered visually or in the accessibility
tree until shown. The passkey list item, the OTP slots (authentication and the setup modal), the
setup modal QR code and button row, the auth card, panel padding and the settings destructive
buttons were reflowed or re-colored for 375 px/200% text and 3:1 or 4.5:1 contrast; a Radix
dialog focuses its first control without scrolling, so tall dialogs scroll the focused control
into view. The four additional components (`TwoFactorSetupModal`, `TwoFactorRecoveryCodes`,
`PasskeyItem`, `PasskeyRegister`) were admitted for this. `InputError`, `Welcome` and the shared UI
primitives are unchanged.

Pre-existing T39 defect fixed in T42 by user decision: at 1024 px with 200% text, the Orders edit
product rows kept four fixed `rem` columns chosen by the viewport breakpoint, so the app sidebar
left the product name no width (one letter per line) and the price overlapped the quantity label.
`OrderProductRow` now chooses its columns from the row's own width (container query) and exposes
the same accessible names for quantity, save and remove through `aria-label` instead of hidden
text. Requests, drafts, totals and marked-line behavior are unchanged. The Orders edit spec, the
Orders index spec and the ordering HTTP tests were run; the regression manifest keeps and now
passes every 1024 px/200% and 375 px/200% case for Orders index and edit.

## 2026-10-26 · Change

`plan.md` (new Layout rules section, header): concrete layout rules for the existing visual identity.
Spacing between and inside containers uses 8 px steps (Tailwind `2/4/6/8/12`), with the 4 px step only
inside a single control; controls keep the constitution's 44 × 44 px minimum; a five-size type scale;
one fixed padding per page, panel, form, list-row and action-row pattern; one left edge per page with
end-aligned amounts. Delivery is tracer bullets: a tracer slice on the member ordering flow, then one
slice per existing-screen inventory, each with a static spacing check and a rendered check. Material
Design's layout principles are adopted, not its components, elevation, colours, shapes or motion.
Why: the 2026-10-26 inventory found pages mixing on-grid and off-grid Tailwind steps (`p-5`, `gap-3`,
`py-1.5`) with no agreed subset, and the visual identity's Layout bullet asked only for "consistent
spacing". The user asked to apply Material Design's rules ("i meant the rules"), chose the plan-only
route with tasks and Linear, and asked for the delivery as tracer bullets ("we want tracer bullets").

## 2026-10-26 · Change

`tasks.md` (new Layout rules section, header): T43 is the tracer slice (patterns, type scale, target
rule and static check on `Orders/Index`, `Orders/Edit` and their shell, stopping for visual approval);
T44, T45 and T46 apply the rules to the T40, T41 and T42 inventories, each after T43 and its approval,
independent of each other. T33's dependencies are unchanged. Not yet tracked in Linear.
Why: follows the `plan.md` Layout rules change above. The user approved writing the plan amendment,
tasks and log entry with these slices ("yes").

## 2026-10-26 · Change

`plan.md` and `tasks.md` (Layout rules status, headers; new Linear tracking table): the Layout rules
amendment and T43–T46 move from Proposed to Accepted and are tracked in Linear as DRA-197 (T43,
blocked by DRA-193) and DRA-198, DRA-199, DRA-200 (T44–T46, each blocked by DRA-197), in the
Application visual identity milestone, Backlog. Implementation is not authorized by this change.
Why: the user approved the tasks and asked for the Linear issues with T43 blocking T44–T46 ("yes").

## 2026-10-27 · Change

`stock-wallet.md` R25 and `plan.md` Stock rules, use cases, and HTTP routes: managers can reactivate
inactive stock products and return items. The list puts an active switch first, allowing the manager to
deactivate or reactivate directly. Reactivation preserves product details, return amounts, and movement
history. Why: the user explicitly selected reactivation for the new switch after the earlier rule allowed
only deactivation.
## 2026-10-26 · Change

`tasks.md` and `docs/visual-checks/dra-197.md` (T43 approval): the user explicitly approved the
merged member ordering layout pilot as the basis for the manager rollout, T45. The manager screens
use the existing Page, Panel, Notice, List and Action row patterns. The user asked for no screenshot
work and offered to verify the finished layout, so T45 records browser geometry measurements and
leaves aesthetic approval with the user.
Why: the project requires explicit human approval of T43 before T45, and the user supplied it
while selecting manager screens as the first area to improve.

## 2026-10-26 · Deviation

`plan.md` (Application visual identity / Modern Minimal theme replacement): the
user requested the Modern Minimal shadcn registry theme after reviewing the
manager screens. This replaces the earlier agricultural palette and typography
inside the existing opt-in `pilot-theme` boundary, while retaining its screen
scope and the T43–T46 layout rules. The React registry item's semantic tokens,
fonts and shadows are adapted to shadcn-vue rather than installing React
components. Light primary and destructive fills are darkened to keep white text
at 4.5:1; input edges and omitted dark pairs are completed for contrast. The
user will inspect the live layout; no new screenshots were requested or taken.

## 2026-10-27 · Deviation

`plan.md` (Application visual identity / Readability refinement): the user
requested a font with softer forms that is easier to read. The opted-in
application theme uses locally bundled Nunito for headings and interface text
in place of Inter and Source Serif 4, while JetBrains Mono remains for code.
The existing theme boundary and fallback behavior remain in place.

## 2026-10-27 · Deviation

`plan.md` (Application visual identity / Unified warm application palette):
the user asked to replace the separate blue and manager palettes with one soft,
warm system and to assign consistent visual roles to actions. The semantic
light/dark tokens now share cream, olive, sage and clay across operational
screens. The shared button variants use primary, secondary, outlined and
destructive treatments; text inputs and select triggers retain a distinct
bordered field treatment. The `Welcome` artwork remains outside the themed
application screens.

## 2026-10-27 · Deviation

`plan.md` (Application visual identity / Modern visual refinement): after
seeing the unified warm preview, the user asked for a more modern appearance.
The semantic palette now uses near-white surfaces, charcoal text, one deep
green primary and restrained neutral accents; dark surfaces are neutral
charcoal. The primary, secondary, outline and destructive action meanings and
the bordered field treatment stay consistent.

## 2026-10-27 · Deviation

`plan.md` (Application visual identity / App-wide visual rollout): the user
requested the modern visual system throughout the application. The remaining
Laravel starter Welcome page now uses Guixa branding and the shared palette;
shared error text, navigation progress, document fallback and favicon are also
aligned. Existing route behavior and button roles are retained.

## 2026-10-27 · Change

`plan.md` (Reference style refresh) and `tasks.md` (T47–T51): the user supplied four
Common Ground interface screenshots, asked to apply their style to Guixa using its
own palette, and approved the mapped proposal and light/dark concept preview with
“approved!”. The first code pilot uses the manager Cycles and Producers indexes
and shared shell; the dashboard and settings slices follow their in-flight layout
work. Why: keep Guixa's workflows and semantic colors while adopting the reference's
clear navigation, page hierarchy and calm operational panels in reviewable steps.

## 2026-10-27 · Change

After T47 merged in PR #239, the user identified repeated page titles in the top bar,
breadcrumb and content heading, requested the breadcrumb at the top, and asked that the
same style cover all pages. The continuation applies the shared shell to all signed-in
routes, aligns authentication and Welcome with the palette, and converts the remaining
bare manager workflow pages to the established panel pattern. T48–T51 are authorized
together; the exact route inventory and visual matrix are in the all-page rollout note.

## 2026-10-27 · Change

`plan.md` (Layout rules) and `tasks.md` (T46 Done when): the user's instruction,
“don't change new ui/layout if it's not an improvement over the current”
requires retaining sound settings and authentication spacing even where the earlier
grid would replace it. T46 therefore permits only documented, measured existing
file/class exceptions in the static check; all other spacing remains strict. This
keeps the approved T43 patterns where they improve the current screens without
regressing the PR #240 baseline. No product behavior or `R#`/`S#` changes.

## 2026-10-28 · Deviation

`plan.md` (Translations, Testing) and `tasks.md` (T36): the user clarified that all
applicable static user-facing text must be translatable, including existing Catalan
literals, accessible labels, placeholders, validation, notification mail and
framework error pages. DRA-205 therefore checks source-level translation calls in
addition to the displayed Catalan result, and uses whole English source messages
with placeholders where values interrupt a sentence. Actual Laravel error pages
use the framework's translated titles and a locale-aware document language.
Installed Laravel-Lang Catalan framework files are pinned with their upstream
license. Proper names, user data and machine identifiers remain untranslated.
The stale T33/Testing scenario bounds are corrected to S1–S64 to match the
accepted scenarios; this changes no scenario or business behavior.


## 2026-10-28 · Request

> Hem d'ampliar specs de 001: els productes provenen d'un productor. A cada producte cal marcar si és d'stock ja que aquests no entren dins la comanda setmanal sinó que s'afegeixen manualment quan es va a recollir la comanda i el membre decideix agafar-ne.

## 2026-10-28 · Q&A

Q: Qui ha de gestionar les fitxes dels productes d’estoc, ara que també tindran un productor associat?

Options:

- El gestor (Recommended) — Manté la regla actual: el gestor administra la fitxa i n’assigna el productor.
- El productor — Cada productor administra també les fitxes dels seus productes d’estoc.
- Tots dos — Tant el gestor com el productor poden administrar aquestes fitxes.

A: El gestor (Recommended)


## 2026-10-28 · Q&A

Q: Si un mateix article, com un oli, es ven tant per comanda setmanal com a la recollida, com el representem?

Options:

- Dues fitxes (Recommended) — Cada fitxa té una sola modalitat: producte d’estoc o producte de comanda setmanal.
- Una fitxa amb dues modalitats — Un mateix producte pot entrar a la comanda setmanal i també vendre’s des de l’estoc.
- No permetre els dos circuits — Cada article només es pot vendre per un dels dos circuits.

A: Dues fitxes (Recommended)


## 2026-10-28 · Q&A

Q: Si es desactiva un productor però encara queden productes seus al local, es poden continuar venent a la recollida?

Options:

- Sí, fins esgotar-los (Recommended) — Es pot vendre l’estoc restant si la fitxa del producte continua activa.
- No, bloquejar-ne la venda — Desactivar el productor impedeix també afegir els seus productes d’estoc a les recollides.

A: Sí, fins esgotar-los (Recommended)


## 2026-10-28 · Q&A

Q: Un cop creada una fitxa, s’ha de poder canviar entre producte d’estoc i producte de comanda setmanal?

Options:

- Només abans d’usar-la (Recommended) — Es pot corregir la modalitat fins que tingui comandes o moviments; després cal crear una altra fitxa.
- Si no té operacions pendents — Permet canviar-la quan no quedi estoc ni comandes o recollides pendents, conservant-ne l’historial.
- No, mai — La modalitat és fixa des de la creació; qualsevol canvi requereix una nova fitxa.

A: Només abans d’usar-la (Recommended)


## 2026-10-28 · Q&A

Q: Qui pot canviar la marca «d’estoc» d’una fitxa que encara no s’ha utilitzat?

Options:

- Només el gestor (Recommended) — El gestor decideix el circuit; el productor continua administrant les seves fitxes de comanda setmanal.
- Gestor i productor — El productor també pot canviar el circuit de les seves fitxes abans del primer ús.

A: Només el gestor (Recommended)


## 2026-10-28 · Q&A

Q: Si una fitxa té assignat el productor equivocat, quan pot corregir-lo el gestor?

Options:

- Abans del primer ús (Recommended) — Un cop hi ha comandes, línies de recollida o moviments, es conserva la procedència i es crea una altra fitxa.
- També després d’usar-la — El canvi afecta operacions futures; l’historial ha de conservar el productor original de cada operació.
- Mai — El productor és fix des de la creació; qualsevol correcció requereix una nova fitxa.

A: Els productes s'haurien d'afegir des del productor, i per tan no es poden canviar de productor. Caldrà crear-ne un de nou i desactivar o eliminar l'anterior.


## 2026-10-28 · Q&A

Q: Quan podem eliminar una fitxa de producte?

Options:

- A — Només si mai s’ha utilitzat (recomanada): sense comandes, línies de recollida ni moviments. Si té historial, només es desactiva.
- B — Mai: sempre es desactiva, encara que s’hagi creat per error.
- C — Si no té operacions pendents: es pot eliminar conservant les dades de les operacions passades.

A: A


## 2026-10-28 · Q&A

Q: Els membres han de poder consultar l’estoc abans d’anar a recollir la comanda?

Options:

- A — No ampliar la consulta (recomanada): l’estoc queda fora del selector setmanal. Els membres veuen a la recollida els productes que el gestor hi ha afegit.
- B — Sí, catàleg informatiu: poden consultar productes, preus i existències, però no reservar-los ni afegir-los a la comanda.

A: B


## 2026-10-28 · Q&A

Q: Al catàleg d’estoc, mostrem també els productes sense existències?

Options:

- A — Sí, indicant «esgotat» (recomanada): es mostren tots els productes d’estoc actius, encara que la quantitat disponible sigui zero.
- B — Només els disponibles: els productes sense existències s’amaguen fins que tornen a tenir estoc.

A: A


## 2026-10-28 · Q&A

Q: El productor pot consultar els seus productes d’estoc, encara que els gestioni el gestor?

Options:

- A — Sí, en mode consulta (recomanada): pot veure les fitxes, els preus i les existències dels seus productes d’estoc, sense modificar-los.
- B — No: amb el rol de productor només veu els seus productes de comanda setmanal.

A: A


## 2026-10-28 · Q&A

Q: Si un producte d’estoc ja té moviments o línies de recollida, se’n pot canviar la unitat o la marca de pes variable? Per exemple, passar de vendre mel per pots a vendre-la per kg.

Options:

- A — No (recomanada): cal crear una fitxa nova, per no barrejar quantitats amb significats diferents.
- B — Sí, si no queda estoc ni recollides pendents: el canvi afecta les operacions futures i l’historial conserva les dades originals.

A: A


## 2026-10-28 · Q&A

L’ampliació de la 001 recollirà aquest acord:

- Cada producte es crea dins la fitxa d’un productor i no pot canviar de productor.
- Cada fitxa és d’estoc o de comanda setmanal. Un article venut pels dos circuits té dues fitxes.
- Només el gestor pot canviar-ne la modalitat, abans del primer ús.
- El productor gestiona els productes setmanals; el gestor, els d’estoc. El productor pot consultar el seu estoc sense modificar-lo.
- L’estoc s’afegeix manualment a la recollida. Es poden vendre existències d’un productor desactivat si el producte continua actiu.
- Els membres disposen d’un catàleg informatiu amb preus i existències, inclosos els productes «esgotats», sense reserves.
- Les fitxes mai utilitzades es poden eliminar; les utilitzades, només desactivar. Després del primer ús, la unitat i la marca de pes variable d’un producte d’estoc queden fixades.

Q: Confirmes aquest acord per actualitzar l’spec?

Options:

- A — Sí, aplica’l a l’spec.
- B — Cal revisar algun punt.

A: A

## 2026-10-28 · Change

`spec.md` (status, glossary, roles and diagrams notice): products now belong to exactly one producer
for their lifetime and have an exclusive weekly or stock classification. Members consult the
informational stock catalogue; producers consult their own stock without administering it.
Why: the user confirmed the producer, stock and visibility decisions above. The preceding diagrams,
plan and tasks are explicitly awaiting the amendment's design stage.

## 2026-10-28 · Change

R1–R3c (`ordering.md`), S65–S70: products are created from a producer's record; that producer never
changes. Only a manager changes an unused product's classification, transferring administration
to the actor responsible for the new classification. First use includes orders, receptions, pickups
and stock movements and remains irreversible after removing lines or cancelling a cycle. Only unused
products may be deleted. Stock is excluded from weekly ordering; producer deactivation does not
prevent selling remaining active stock.
Why: the user confirmed the product identity, ownership, first-use and deletion rules. Counting
reception as use also prevents reclassifying or deleting goods already being distributed.

## 2026-10-28 · Change

R25–R25c (`stock-wallet.md`), S71–S74: managers create stock products from a producer's record and
administer them. The unit and variable flag are fixed after the first movement or pickup line.
Producers consult only their own stock; members consult active stock across producers, with current
prices and quantities, zero stock shown as sold out, and no reservation or weekly ordering.
Why: the user confirmed manager administration, producer consultation, member visibility and
protection against changing the meaning of recorded stock quantities.

## 2026-10-28 · Change

R20 (`pickup.md`), S75–S76: stock products chosen by a member are added manually to a pending pickup;
weekly products cannot be added as stock. Stock sales do not require the producer's participation,
cutoff or per-cycle availability and remain possible for an inactive producer's active products.
The existing settled-cycle, stock-availability, price/container snapshot and atomic charge rules remain.
The previously recorded T28 rule on inactive stock products is made explicit: existing lines survive
and can be removed, but new lines or increases are refused.
Why: distinguish the supplying producer from weekly participation without bypassing pickup or stock
rules, and preserve the already decided deactivation behaviour.

## 2026-10-28 · Change

R14 and R16 (`reception.md`), S77: only weekly products can enter reception or allocation, including
unrequested goods. Stock goods use entries and pickup stock lines instead.
Why: once every product belongs to a producer, the old permission to receive any of that producer's
products would otherwise admit stock into the weekly distribution circuit.

## 2026-10-28 · Change

`specs/README.md` (feature 001 status): records the 2026-10-28 product/producer/stock amendment and
that the corresponding plan and task revisions are pending.
Why: the accepted 2026-10-21 plan and tasks do not yet describe the amended behaviour. The user
approved the spec amendment; this change does not proceed to the plan, tasks or implementation.

Validation scope: documentation only, with no executable behaviour changed. This is the constitution's
test-first exception for documentation; application tests are deferred to implementation of the new
acceptance scenarios. Validate global requirement/scenario identifiers and references, local Markdown
links, append-only history, and whitespace. No application, database or UI checks are claimed here.


## 2026-10-28 · Change

R26 (`stock-wallet.md`) and S76 (`pickup.md`): stock entries and adjustments accept only products
marked as stock; weekly products cannot acquire stock movements or be sold through stock lines.
R1c also makes clear that the deletion restriction does not forbid the ordinary edits allowed by
R1 and R25a.
Why: keep the confirmed exclusive classification consistent across inventory, ordering, reception
and pickup, and avoid interpreting the used-product deletion rule as a ban on editing prices or names.


## 2026-10-28 · Request

> Endavant, amplia el plan.md amb els canvis de l’spec que hem acordat.

The user invoked `grill-me`; continue the technical interview one question at a time with options,
then amend the plan from the confirmed design. The product behaviour remains as agreed in the spec.


## 2026-10-28 · Q&A

Q: On ha de viure la fitxa del producte?

Options:

- A — Un únic model `Product` a `Catalog` (recomanada): concentra productor, dades i marca d’estoc. `Stock` gestiona les existències, els moviments i els retorns. Requereix migrar les fitxes d’estoc actuals preservant-ne els identificadors i l’historial.
- B — Mantenir els dos models actuals: `Catalog` conserva els productes setmanals i `Stock` els d’estoc, afegint-hi el productor. Redueix la migració inicial, però canviar de modalitat implica traspassar la fitxa entre els dos models.

A: A, com que no tenim res a producció no cal migrar res.

Plan direction: one Catalog product identity and stock flag; Stock owns inventory movements and return
items. The user states there is no production data, so design the fresh schema without a legacy data
migration, import, backfill or compatibility layer. This records a plan decision, not execution of any
database reset.


## 2026-10-28 · Q&A

Q: Com recordem que un producte ja s’ha utilitzat? Cal conservar aquest fet encara que després s’esborri una línia o es cancel·li una comanda.

Options:

- A — Una marca permanent al producte (recomanada): s’activa amb el primer ús confirmat i no es reinicia. Permet bloquejar l’eliminació i el canvi de modalitat sense mantenir un historial addicional.
- B — Un registre d’usos immutable: guarda cada ús i el seu origen. Aporta traçabilitat detallada, però afegeix estructura que l’spec actual no exigeix.

A: potser necessitem una sessió per la part de comanda, continguts i cistella validada + stocl

This response redirects the interview; it does not select a first-use storage design. That technical
question remains open while the order and pickup section is explored.

## 2026-10-28 · Q&A

Q: Quan dius «una sessió», et refereixes a:

Options:

- A — Una secció de l’app: mostrar la comanda prevista i la cistella final de recollida, amb les quantitats reals i l’estoc afegit.
- B — Un concepte nou del model: una sessió de compra amb contingut editable i una cistella validada.
- C — Una sessió de treball separada: aprofundir en comanda, cistella i estoc abans de continuar el pla.

La meva interpretació inicial és A.

A: A+C

The user confirms the application section and a focused design interview before resuming the technical
plan. Clarify what the proposed validated basket means against existing reception/distribution/pickup
states; do not silently introduce a new session aggregate or checkout/validation step. The unified
Catalog product and fresh-schema decisions remain settled.


## 2026-10-28 · Q&A

A l’spec actual, «validada» qualifica la recepció del productor.

Q: Què vols dir amb «cistella validada»?

Options:

- A — La cistella assignada després del repartiment (recomanada): mostra què ha arribat per a la família. A la recollida encara s’ajusten quantitats i s’hi afegeix estoc abans de cobrar.
- B — La recollida completada: inclou quantitats finals, estoc i cobrament; ja és immutable.
- C — Una validació nova: un pas explícit per confirmar la cistella, separat del repartiment i del cobrament.

A: A, un cop validada es fà el carrec i la unitat pot pagar.

The user selects the allocated basket and proposes charging once it is validated, with payment then
possible for the household. This may change R19/R22 (currently charged only on pickup completion),
so resolve the exact charge moment before amending the spec or technical plan. Stock added later,
quantity corrections and the meaning of household payment remain dependent decisions. Do not infer
online payments, a new payment provider or replacement of the household wallet from this answer.


## 2026-10-28 · Q&A

Entesos: la cistella repartida genera el càrrec abans de la recollida, i la unitat ja pot pagar-lo.

Q: Com cobrem l’estoc que s’hi afegeixi després?

Options:

- A — Càrrec addicional en completar la recollida (recomanada): l’estoc es cobra junt quan el gestor confirma què s’emporta la unitat.
- B — Càrrec immediat per cada afegit: cada producte afegit ja modifica el moneder, encara que la recollida continuï oberta.

A: Pot ser que un membre no hagi fet comanda però vulgui estoc o que després de validar i omplir la cistella recordi que vol estoc. Per tan necessitem un procés que ens permeti crear una cistella per tal d'afegir continguts de nou.

The user requires basket creation both without a weekly order and after another basket has already
been validated and filled. Explore successive baskets for a household, retaining the original validated
basket and charge rather than silently reopening it. R19 already permits a stock-only pickup, but
R19/R23 currently allow one pickup per household and cycle, so the additional-basket lifecycle needs
a spec amendment before completing the technical plan. Scope in time/cycle, basket validation,
stock charging and household payment still need to be made precise. This response proposes the
process rather than directly selecting one of the previous charging options.


## 2026-10-28 · Q&A

Q: Quan s’han de poder crear aquestes cistelles noves?

Options:

- A — Durant les recollides (recomanada): el gestor pot crear-ne per a unitats sense comanda i obrir-ne d’addicionals després de validar una primera cistella.
- B — En qualsevol moment: també es poden fer compres d’estoc fora de les recollides, encara que no hi hagi cap cicle setmanal en curs.

A: a i b

The user authorizes both pickup-time baskets and stock purchases at any time, without requiring a
weekly cycle. This explicitly expands the prior out-of-scope item for stock sales outside pickup in
spec.md and the roadmap. Basket creation permissions, validation, charging, payment, stock checks
and linkage to weekly orders still require clarification before drafting the amended requirements
and technical plan. No online checkout, reservation or member editing permission is inferred.


## 2026-10-28 · Q&A

Q: Qui pot crear i omplir aquestes cistelles d’estoc?

Options:

- A — Només el gestor (recomanada): el membre indica què vol i el gestor ho afegeix, tant durant una recollida com en una compra independent.
- B — El gestor i els membres: cada membre també pot preparar una cistella per a la seva unitat. Això ampliaria el catàleg informatiu que havíem acordat; després concretaríem qui la valida.

A: necessitem un rol d'economiques que és qui valida, afegeix stock a la cistella, fa càrrecs, cobra

The user introduces an Econòmiques access role responsible for basket validation, adding stock to
baskets, charges and collections. This does not itself grant catalogue editing or stock-inventory
adjustment permissions. Resolve coexistence with Manager/Admin and who may prepare/create a basket;
do not infer that members may edit a basket, that Finance can edit stock product records, or that
collections require online payment processing. The earlier Commissions decisions (2026-10-25)
preserve all commission powers for Admin, so any exclusive Finance permission must be explicit.


## 2026-10-28 · Q&A

Q: El gestor també ha de poder validar cistelles, fer càrrecs i registrar cobraments?

Options:

- A — Sí, gestor i Econòmiques (recomanada): Econòmiques assumeix aquestes funcions i el gestor conserva tots els permisos, com preveuen els acords de Comissions.
- B — Només Econòmiques: un gestor necessita tenir també aquest rol per fer operacions econòmiques.

A: a

Econòmiques adds access to the agreed basket and collection operations; Manager retains all existing
permissions, consistent with the earlier Admin/Commissions direction. This does not yet decide
whether household members may prepare draft baskets or how payment is recorded.


## 2026-10-28 · Q&A

Q: Reservem la creació i l’edició de cistelles d’estoc a Econòmiques i al gestor?

Options:

- A — Sí (recomanada): el membre consulta el catàleg i indica què vol; Econòmiques o el gestor creen i omplen la cistella.
- B — Els membres també poden preparar-les: creen esborranys per a la seva unitat; Econòmiques o el gestor els revisen i validen abans de generar el càrrec.

A: A

Stock basket creation and editing are limited to Econòmiques and Manager; members retain the
informational catalogue and do not create or edit basket drafts. Existing manager access is retained.


## 2026-10-28 · Q&A

Q: Com hem de controlar què ha pagat cada unitat?

Options:

- A — Només amb el saldo global del moneder (recomanada): els càrrecs resten saldo i els cobraments l’augmenten. Un pagament pot cobrir diverses cistelles o deixar saldo a favor.
- B — Saldo global i pagaments per cistella: també registrem a quines cistelles s’aplica cada pagament i quines estan pagades, parcialment pagades o pendents.

A: A

The household wallet remains the global balance: basket validation creates a debit and recording
money received creates a credit. Payments are not allocated to individual baskets and there is no
per-basket paid/partially-paid/unpaid status. One receipt may cover multiple charges or leave credit.
Payment channels and who records receipts remain separate details; no online processing is inferred.


## 2026-10-28 · Q&A

Q: Quan s’ha acabat el repartiment, com es crea la cistella de cada unitat que ha fet comanda?

Options:

- A — Automàticament com a esborrany (recomanada): conté les quantitats assignades. Econòmiques o el gestor la revisen i la validen; aleshores es genera el càrrec.
- B — Manualment des de la comanda: Econòmiques o el gestor creen la cistella quan la volen preparar, carregant-hi les quantitats assignades. El càrrec també es genera en validar-la.

A: A

After distribution, each household with a weekly order receives an automatically generated draft
basket containing its allocated quantities. Finance or Manager reviews and explicitly validates it;
creation alone does not generate a wallet charge. Stock-only/additional baskets remain created by
Finance or Manager under the earlier decisions.


## 2026-10-28 · Q&A

Q: En validar una cistella amb estoc, descomptem també les existències?

Options:

- A — Sí, càrrec i sortida d’estoc alhora (recomanada): es comprova que n’hi hagi prou i es confirmen les dues operacions juntes. Si falta estoc, no es valida ni es cobra.
- B — En entregar-la físicament: la validació genera el càrrec, però cal reservar els productes fins que la unitat se’ls emporti.

A: A

Basket validation checks available stock and atomically records the wallet charge and stock sale.
If stock is insufficient, neither movement nor validation is committed. Physical handover does not
sell the stock or charge the basket again. This is a stock sale at validation, not a stock reservation.


## 2026-10-28 · Q&A

Q: Com es confirma que una unitat ha pagat?

Options:

- A — Registre manual del cobrament (recomanada): Econòmiques o el gestor comproven que han rebut els diners —en efectiu o per transferència— i registren l’entrada al moneder.
- B — Pagament integrat a l’app: la unitat paga des de l’aplicació i el moneder s’actualitza quan el servei de pagament confirma el cobrament.

A: A

Receipts are recorded manually by Finance or Manager after confirming money was received, including
cash or bank transfer, as a credit to the household wallet. There is no in-app payment gateway or
automatic payment-provider integration in this amendment. The household global balance remains the
payment accounting model; a receipt is not assigned to individual baskets.


## 2026-10-28 · Q&A

Q: Econòmiques també ha de poder corregir errors en càrrecs, cobraments o estoc d’una cistella validada?

Options:

- A — Sí, amb moviments correctors i un motiu obligatori (recomanada): es conserva l’original i es registra la correcció del moneder o de l’estoc que correspongui.
- B — Només el gestor: Econòmiques valida i cobra, però les correccions posteriors les fa el gestor.

A: A

Finance, as well as Manager, may correct basket charges, receipts and stock associated with a validated
basket through new correcting movements with a mandatory reason. Original basket, wallet and stock
records remain unchanged. This concerns corrections to the agreed basket/receipt operations, not a
new grant to edit product catalogue records or perform unrelated inventory administration.


## 2026-10-28 · Q&A

Q: Hem de registrar també si una cistella validada ja s’ha entregat físicament?

Options:

- A — Sí (recomanada): queda «validada, pendent de recollir» fins que Econòmiques o el gestor marquen l’entrega. El càrrec ja està fet i no es repeteix.
- B — No: la validació dona la cistella per finalitzada; l’app no controla si s’ha recollit.

A: A i el propi membre la pot marcar com a recollida també.

Validated baskets remain pending physical collection until marked collected. Finance and Manager
may mark collection, and a member may also mark validated baskets of their own household collected.
Record the actor and collection time. This action records handover only: it never charges the wallet,
sells stock again or records a payment. Member access does not include creating or editing baskets,
validating them, recording receipts or acting on another household's baskets.


## 2026-10-28 · Q&A

Q: Si Econòmiques o el gestor donen per «no recollida» una cistella validada, què fem amb l’estoc que ja s’havia cobrat?

Options:

- A — Mantenir la venda i el càrrec (recomanada): es registra que no s’ha recollit. Si cal retornar productes o diners, es fan moviments correctors amb motiu.
- B — Retornar l’estoc i abonar-ne l’import automàticament: es registren els moviments inversos. Els productes de comanda setmanal continuen cobrats.

A: A, ho fem perquè potser recullen la cistella un altre dia. No és que ho hagin abandonat.

The user retains the sale and charge but clarifies that delayed collection is not abandonment.
A validated basket remains pending collection after the planned pickup date and can be collected
on a later day. Merely missing the date must not mark it terminally not collected, cancel its contents,
return stock or credit the wallet. Corrections remain explicit with a reason. Do not interpret the
letter A without the user's clarification as approval of an abandonment/terminal-state policy.


## 2026-10-28 · Q&A

Q: Podem tancar el cicle setmanal quan el repartiment està acabat i totes les cistelles de comanda estan validades, encara que quedin recollides pendents?

Options:

- A — Sí (recomanada): el cicle queda tancat i les recollides continuen gestionant-se després, sense alterar-ne el contingut ni repetir càrrecs.
- B — Esperar les recollides: el cicle continua obert fins que totes les cistelles s’han recollit. Mentrestant es poden obrir cicles nous.

A: A, però cal un avís visual de que hi ha comandes pendents de recollir.

A weekly cycle may close once distribution is finished and all weekly-order baskets are validated,
without waiting for physical collection. Pending collection remains actionable after cycle closure,
with no repeat charge or stock sale. A persistent visual indication must expose outstanding
collections even for closed cycles. Basket contents/financial history stay protected as agreed;
recording a later handover does not reopen the weekly cycle. Map the indication to authorized views
in the plan; the user requested a visual indication, not new notifications or reminders.


## 2026-10-28 · Q&A

Q: Econòmiques també pot ajustar les quantitats reals dels productes setmanals abans de validar la cistella? Per exemple, passar d’1 kg demanat a 1,120 kg entregat.

Options:

- A — Sí (recomanada): es conserva la quantitat demanada i el càrrec es calcula amb la quantitat real.
- B — Només el gestor pot ajustar-les: Econòmiques afegeix estoc i valida la cistella amb les quantitats que ja s’han assignat.

A: A

Finance, as well as Manager, may adjust actual weekly-product quantities in a draft basket before
validation. Original ordered quantities are retained, and the charge uses actual quantities with the
applicable product snapshot. Validated baskets remain protected; later mistakes use the correcting
movements already agreed, rather than rewriting the original basket or charge.


## 2026-10-28 · Q&A

Q: Com guardem que un producte ja s’ha utilitzat, per impedir eliminar-lo o canviar-lo entre estoc i comanda setmanal?

Options:

- A — Una marca permanent al producte (recomanada): s’activa amb el primer ús en una comanda, recepció, cistella o moviment d’estoc. Es conserva encara que després s’eliminin les línies.
- B — Un historial immutable d’usos: guarda cada ús i el seu origen. Aporta més detall, però afegeix estructura que el funcionament acordat no necessita.

A: ja tenim les comandes i cistelles on queda reflectit.

The user proposes deriving product use from existing business records instead of adding redundant
product state or a separate usage ledger. The current implementation removes order lines and deletes
orders on cancellation (plan D14), so those references are not currently sufficient for the agreed
irreversible first-use rule. Clarify retention of removed/cancelled document lines; do not silently
weaken the rule to currently active references or claim existing destructive storage retains history.


## 2026-10-28 · Q&A

Q: Conservem també les línies retirades i les comandes o cistelles cancel·lades com a historial?

Options:

- A — Sí (recomanada): deixen de comptar en el contingut vigent, però mantenen les referències als productes. Així deduïm el primer ús dels mateixos documents, sense una marca addicional.
- B — Mantenim l’esborrat físic dels esborranys: aleshores necessitem una marca permanent al producte per recordar que s’havia utilitzat.

A: A

Retain removed lines and cancelled orders/baskets as history, excluded from current contents and
operational totals but preserving product references. Derive first-use evidence from these business
records, receptions and immutable stock movements. Do not add a redundant product usage flag or a
separate usage ledger. This supersedes destructive cancellation/removal in the prior plan, including
D14, while retaining the spec's irreversible first-use rule and cancelled-cycle visible behaviour.


## 2026-10-28 · Q&A

Q: On situem el model de cistella?

Options:

- A — Dins d’`Ordering`, separat de la comanda (recomanada): `Order` conserva la comanda setmanal i `Basket` gestiona el contingut real, la validació i la recollida. Les cistelles d’estoc poden existir sense cicle. Reaprofitem les connexions actuals amb estoc i moneder.
- B — En un context nou, `Sales`: concentra totes les cistelles i vendes, mentre `Ordering` conserva comandes i repartiment. Separa més les responsabilitats, però requereix noves connexions entre contextos.

A: Quina seria la separació més SOLID que aporta un bon manteniment a futur? Quina creus que seria la millor estrcturació comparat amb e-comerce o eines similars?

The user requests a reasoned architecture recommendation before selecting the option. The previous
A recommendation prioritized reuse and a smaller initial refactor; compare against long-term
cohesion and independent basket/sales lifecycle. No architecture choice is accepted by this response.
Research primary references: commercetools Carts and Orders overview distinguishes mutable carts,
confirmed transaction records and post-purchase processes; Microsoft tactical DDD recommends small
aggregates for independent lifecycles; Fowler Bounded Context describes explicit context models and
relationships. Apply these as analogies, not requirements to copy a generic commerce platform or
introduce distributed transactions/microservices.


## 2026-10-28 · Q&A

Q: Quina opció fixem al pla?

Options:

- A — `Basket` dins d’`Ordering`.
- B — `Sales` separat (recomanada).

A: B

Create a Sales bounded context for baskets, draft contents, actual quantities, validation, corrections
and collection tracking. Ordering retains weekly demand, cycles, cutoffs, producer orders, reception
and distribution. Catalog owns the single product model, Stock its inventory ledger and return items,
Wallet the household global balance and movements, Membership accounts/households/permissions.
Keep a modular monolith with a shared local transaction for basket validation, stock sale and wallet
charge. Connect contexts through narrow ports and Application adapters; do not share mutable domain
entities. This long-term cohesion choice supersedes the earlier proposal to keep Basket in Ordering.


## 2026-10-28 · Q&A

Q: Per donar accés a Econòmiques, afegim un rol específic o implantem ara el model de comissions?

Options:

- A — Rol específic d’Econòmiques (recomanada): el gestor l’assigna i el retira. Centralitzem les comprovacions de permisos perquè més endavant puguin dependre de comissions.
- B — Comissions configurables ara: implementem la creació de comissions, els seus membres i permisos per àrea. Econòmiques seria una d’aquestes comissions.

Recomano A per completar el flux acordat mantenint el model de comissions com una ampliació posterior.

A: A

Implement an explicit Finance role for this amendment, granted and revoked by Manager. Centralize
access checks so a later Commissions implementation can supply the same permissions. Do not add
configurable commission management now. Manager retains all Finance capabilities. Prepare the
consolidated specification and technical-plan proposal for confirmation before applying the new flow.


## 2026-10-28 · Q&A

Hi ha un detall a concretar per conservar el recompte d’envasos per cicle.

Q: Les cistelles addicionals d’estoc creades durant una recollida han de quedar vinculades al cicle?

Options:

- A — Sí, com a referència (recomanada): permet agrupar-les i comptar-ne els envasos. El vincle no reobre ni bloqueja el cicle. Les compres independents poden quedar sense cicle.
- B — Sempre independents: el recompte del cicle només inclou la cistella setmanal; les cistelles d’estoc es compten per separat.

A: Suposem que un membre va a recollir la comanda l'endemà del dia de recollida i agafa stock. Avisa als responsables perquè li carreguin aquell stock. Què hauríem de fer en aquest cas?

The user asks to test the association policy with a late-collection example, rather than selecting
an option yet. Proposed handling: retain the original validated weekly basket and charge; Finance
or Manager creates a separate stock basket for the household from the reported items, may link it
to the original cycle even when closed, validates it with one stock sale and wallet charge, and
records collection because the goods have already been taken. Ledger movements retain their own
recording date/time and actor; the cycle link is attribution, not backdating or a cycle reopen.
Do not infer member basket editing, automatic notice delivery or a new messaging feature.


## 2026-10-28 · Q&A

Proposed late-collection flow: keep the original weekly basket and charge; Finance records reported
stock in a new basket, validates it with a stock sale and additional wallet charge, and marks it
collected because the goods were already handed over. Retain each movement's own recording date.

Q: Ho fixem així?

Options:

- A — Sí, amb referència al cicle original quan provingui d’aquella recollida (recomanada).
- B — La compra d’estoc sempre queda sense cicle.

A: A

A supplementary stock basket originating from a weekly collection may reference the original cycle,
even when recorded after the pickup day or after cycle closure. This is attribution for grouping and
container counts, not a prerequisite to stock sales, backdating or reopening the cycle. Independent
stock baskets may have no cycle. Weekly order contents and prior basket charges remain immutable.


## 2026-10-28 · Q&A

The review package contained the full technical plan, the specification amendment and the context,
model and workflow diagrams. Its summary covered the separate Sales context, automatic weekly drafts,
Finance validation with atomic charge/stock sale, manual global-Wallet receipts, independent collection,
closed-cycle pending warnings, optional origin-cycle links, retained history and a fresh schema.

Q: Confirmes la proposta per aplicar-la a l’spec i al `plan.md`?

Options:

- A — Sí, aplica-la.
- B — Cal revisar algun punt.

A: A

## 2026-10-28 · Change

`spec.md` (status, glossary, time, roles, areas and scope): add Basket, validated basket, Finance and
separate physical collection. Stock sales outside weekly pickups are included. Spec and plan are
accepted for the amendment; task updates remain pending.
Why: the user confirmed the reviewed basket/Finance proposal after the recorded interview.

## 2026-10-28 · Change

R4b/R9 and S9/S37/S68 (`ordering.md`): cancellation and line removal retain history but clear current
contents/totals. Cycle-independent stock purchases survive weekly cancellation. Overlapping-cycle
charges originate from the selected basket, and collection has no financial effect.
Why: derive irreversible product use from existing source records and separate demand from Sales.

## 2026-10-28 · Change

R18/R18a and S27/S30/S40/S60/S63/S64/S77 (`reception.md`): settlement creates Sales drafts;
redistribution distinguishes unvalidated drafts from financially frozen validated baskets, not
physical collection. Keep the explicit choice and append-only signed adjustments before closure.
Container counts include attributed late stock supplements; stock stays outside weekly reception.
Why: preserve the accepted allocation algorithms while moving actual sales and collection to Sales.

## 2026-10-28 · Change

R19–R24b (`pickup.md`) now define baskets, validation and collection. Keep global scenario identifiers,
reconcile the earlier charge-on-completion examples, and add S78–S90 from the confirmed proposal.
Finance/Manager operate drafts, validate once with atomic charge/stock sale, record manual receipts and
reasoned corrections. Members can mark their own validated baskets collected. Late collection stays
pending; weekly closure depends on base validation and retains pending indicators. Additional baskets
can be cycleless or reference their origin cycle without reopening it.
Why: the user expanded the flow to successive stock baskets, purchases outside pickup, Finance access,
separate validation/receipt/collection and persistent warnings after closure.

## 2026-10-28 · Change

R25a/R25c/R28–R32/R35–R36c and S24/S58/S72 (`stock-wallet.md`): product-use references are basket lines;
stock is sold at validation; Finance/Manager record confirmed global receipts and append corrections.
Manager grants/revokes Finance. Keep existing basket drafts and pending collections after household
deactivation, and include both in to-settle obligations; new stock baskets still require active households.
Why: reflect the confirmed Finance role without granting unrelated catalogue/inventory/account powers
or losing existing obligations during the change from a single pickup to multiple baskets.

## 2026-10-28 · Change

`plan.md`: apply the reviewed single Catalog Product model and separate Sales context, internal ports,
Finance capabilities, retained-reference history, fresh schema, basket-level stock/Wallet uniqueness,
local transaction/locking protocol, source synchronization, independent collection, cycle attribution,
route/DTO/component reuse and verification plan. Update D7/D12/D14/D16 and add D19–D25. Preserve the
unaffected accepted presentation baseline. Source-cycle mutation locks remain compatible with FK
key-share references; verify attributed sales versus weekly validation to avoid inverted lock ordering.
Why: the user selected future maintenance through separate Sales, declined redundant usage state and
legacy-data migration, and confirmed the complete technical proposal.

## 2026-10-28 · Change

`diagrams.md`: replace the old parallel product/pickup model with Catalog Product, weekly Ordering,
Sales baskets, validation, independent receipts/collection and retained history. `specs/README.md`
records the accepted spec/plan amendment and pending task updates; remove outside-pickup stock sales
from the future roadmap because it is now included in 001.
Why: keep the context map, workflow and feature index consistent with the accepted documents.

Validation scope: documentation only. The constitution's documentation exception applies to TDD;
no application behaviour, database or UI is changed or exercised here. Check R#/S# uniqueness and
references, Given/When/Then coverage, local links, Markdown structure, append-only log, unchanged
unrelated presentation/task files, whitespace and main-checkout cleanliness. Application, concurrency,
HTTP, architecture and rendered-UI checks described by the plan belong to its implementation.


## 2026-10-28 · Change

Consistency review: R1b/R4 (`ordering.md`) and S72 (`stock-wallet.md`) now explicitly reference
basket use, including removed/cancelled drafts, and validation-based charges rather than physical
pickup charges. `plan.md` Membership keeps weekly orders in Ordering and baskets/collection in
Sales, and its deactivation warning includes unresolved drafts as well as balance/pending collection.
Why: remove four stale single-pickup references found in the independent bounded review; these are
transcription fixes to the already confirmed flow, not additional product decisions.


## 2026-10-28 · Change

`diagrams.md` (account cardinalities): the household and producer links of an account are optional,
matching Finance-only accounts, derived membership and producer records awaiting an account.
Why: the new Finance scenario explicitly permits login without a household; the ER diagram must not
imply every account has a household or producer while the spec/plan make both links optional.


## 2026-10-28 · Request

d'acord, busca l'skill primer en el projecte i apliquem el què calgui

## 2026-10-28 · Request

potser hauríem de tenir més d'un fitxer de tasques?

## 2026-10-28 · Change

`tasks.md`: draft T52–T63 from the accepted products/Sales/Finance amendment, retain T01–T51 IDs
and amend local T33 to semantic coverage of all 65 requirements and 90 scenarios. Keep this file the
canonical registry for workflow lookup; prerequisites describe behavior dependencies, not edit contention.
Why: the accepted plan needs independently verifiable deliveries and an unambiguous closure gate.

## 2026-10-28 · Change

`tasks/baseline.md`: move the original T01–T51 scopes, dates, approvals and evidence into an explicitly
historical snapshot, rebase its relative links and add task anchors. Current amended rules take
precedence over its obsolete deletion/charge-on-collection instructions; original evidence is not rerun.
Why: preserve provenance without mixing superseded behavior into executable task instructions.

## 2026-10-28 · Change

`tasks/catalog.md`: detail T52–T54 (unified producer catalogue tracer, retained-use guards and stock
visibility). `tasks/sales.md`: detail T55–T58 (independent purchase tracer, Finance/receipts, contents
and corrections). `tasks/weekly-and-collection.md`: detail T59–T63 and T33 (weekly writer cutover,
late supplements, self-collection, lifecycle, contract cleanup and semantic audit).
Why: the user suggested multiple task files; group by reviewable functional outcomes while preserving
one global ID/dependency registry, end-to-end slices and the accepted domain boundaries.

## 2026-10-28 · Change

`tasks/coverage.md`: allocate all 65 R# and 90 S# to retained and amendment owners, with explicit
partial-scenario completion and concurrency/atomicity/authorization obligations. This is responsibility,
not test-passing evidence. `tasks.md` records the read-only Linear check and draft relationship proposal;
no tracker issue, parent, status, label, description or relation was changed.
Why: avoid coverage gaps and duplicate existing IDs; separate local drafting from external publication.

## 2026-10-28 · Change

`spec.md`, `plan.md`, `specs/README.md`: replace pending-task wording with draft-for-review links,
point the plan's historical screen inventory to `tasks/baseline.md`, and document the canonical-index/
linked-detail convention. Spec behavior and accepted plan decisions are unchanged.
Why: make the split navigable and accurately distinguish accepted spec/plan from the proposed tasks.


## 2026-10-28 · Change

Task-draft consistency: T33 also names the architecture prerequisite T02; T55 explicitly verifies
S72 after removed/cancelled basket use, and T60 preserves an independent stock basket if its
referenced weekly cycle is cancelled. T62 depends on the weekly-source cutover T59 rather than
unrelated member UI delivery; T63 explicitly waits for both collection readers and household consumers.
Why: make partial-scenario responsibility and real dependency boundaries explicit without adding
product behavior or changing Linear.

Documentation validation: 63 unique canonical IDs, acyclic dependencies, every delivery task reachable
from T33, all 65 requirements and 90 scenarios allocated, 703 local links/anchors resolved, historical
baseline preserved apart from its declared navigation transformations, and prior log bytes unchanged.
Whitespace and main-checkout cleanliness are checked separately. No application/DB/UI checks are
claimed: this amendment only drafts task documents, using the constitution's test-first exception.


## 2026-10-28 · Q&A

Question: Aproves l’estructura i el repartiment?

A. Acceptar l’esborrany documental.
B. Revisar la mida o agrupació de les tasques.

Answer: A

## 2026-10-28 · Change

`tasks.md`, `tasks/catalog.md`, `tasks/sales.md`, `tasks/weekly-and-collection.md` and
`tasks/coverage.md`: mark the split document structure, T52–T63 and amended T33 scope accepted.
The historical baseline remains unchanged. Linear publication, existing tracker amendments and
application implementation remain separate scopes; no external operation was authorized by option A.
Why: the user explicitly accepted the concrete document draft and task breakdown on 2026-10-28.

## 2026-10-28 · Change

`spec.md`, `plan.md` and `specs/README.md`: replace draft-for-review status with accepted task-breakdown
links. Requirements, scenarios, task IDs, dependencies and acceptance criteria are unchanged.
Why: keep document navigation and feature status consistent with the user's acceptance.

## 2026-10-28 · Change

`tasks.md` and `tasks/weekly-and-collection.md`: record the authorized Linear publication and
current mappings. Created grouping parent DRA-206 and T52–T63 as DRA-207–DRA-218, all Backlog;
only executable children have the existing `ready-for-agent` label. Published 28 child prerequisite
edges and contextual T61/DRA-216 related links to feature-002 DRA-202/DRA-203.

Updated T33/DRA-183 to the semantic audit of all 65 requirements and S1–S90, keeping Todo and
its existing identity, fields, milestone, parents and relations. Retained the 11 original blockers
and added T02/DRA-151, T44/DRA-198, T46/DRA-200 and T63/DRA-218 (15 total). T36/DRA-205
was already Done at the fresh readback. No matching T47–T51 issues were found; T48 → T33,
T50 → T33 and T51 → T33 remain mandatory gates documented in T33 but pending native relation
mapping. No missing IDs were invented and no tasks outside T52–T63 were created.

Readback verified every created parent/child, project, label, blocker and contextual relation,
preserved existing issue parents/relations, and found no duplicates, cycles or self-blocking.
No remote failure or unknown outcome remains. The accepted sources are still local/unpublished;
repository locators in tickets do not claim the changes exist on main. No application implementation,
push, PR or merge was performed. This documentary synchronization uses the test-first exception;
structural document checks are recorded separately, with no application/DB/UI result claimed.
Why: the user explicitly accepted the documents and authorized publication to Linear in this session.

Structural validation after synchronization passed: 63 unique canonical IDs, acyclic task graph,
every delivery reaches T33, 65 requirements and 90 scenarios allocated, 716 local links/anchors
resolved, historical baseline unchanged and all 154,258 pre-publication log bytes preserved.
Only the two tracker-record documents and this log changed during publication; the other 12
accepted Markdown files remained byte-identical. `git diff --check` passed and main stayed clean.

## 2026-10-28 · Change

`tasks.md` and `tasks/weekly-and-collection.md`: record the explicitly authorized follow-up
publication of T47–T51 as standalone Coop issues DRA-219–DRA-223, Backlog, ready-for-agent,
unassigned and without delegation. Dependencies are T45 → T47, T47 → T48/T49/T51 and T49 → T50.
T48/DRA-220 relates to integrated T44/DRA-198; T51/DRA-223 relates to integrated T46/DRA-200
for the accepted layout reconciliation. These are contextual links, not additional blockers.

Added T48/DRA-220, T50/DRA-222 and T51/DRA-223 as native blockers of T33/DRA-183 and replaced
its obsolete pending-mapping wording. All 18 canonical closure prerequisites are now native;
T33 remains Todo with its existing identity, metadata and other relations preserved. DRA-206
still groups only T52–T63. No visual task was marked Done or authorized for implementation.
The historical baseline, including its dated unpublished-task statement, remains unchanged.

The user also authorized a documentation PR and requested a dashboard compatibility check.
Source-publication guidance now points to the documentation branch pending merge, rather than
claiming the sources must remain unpublished. Feature 002 records its own derived Sales alignment.
Why: “Crea també T47–T51 a Linear segons les specs acceptades i afegeix els bloquejos pendents de
T48, T50 i T51 a T33”; “fem PR del què tenim si és necessari també”; “després comprova la part
del dashboard si cal actualitzar-la també”.

## 2026-10-28 · Change

`plan.md` and `tasks.md`: reconcile the documentation amendment with origin/main at b719dd5.
Retain DRA-205's source-level translation, whole-message placeholder and framework-language
clarifications in the current translation contract and T36 execution note. The historical task
baseline remains unchanged; current accepted S1–S90 replaces only the superseded S1–S64 bounds.
Both feature logs preserve the complete upstream log prefix and every byte of this amendment's
existing appended entries. No application source was changed by the documentation integration.
Why: publish the authorized PR against current main without discarding its merged clarifications.


## 2026-10-29 · Deviation

T48–T51 (Reference style refresh): complete the missing manager/producer role-shell evidence
and authenticated Welcome state in a task-owned fixture/manifest, preserving the accepted rollout
and historical visual reports. The new coverage retains every declared manager/producer state,
uses explicit role and public route context, and includes the remaining signed-in shells affected
by the shared navigation rule. Application and Catalog/Sales behavior is unchanged.

The rendered selected-navigation probe found white text on the light sidebar accent at 4.43037:1,
below the accepted 4.5:1 contrast threshold. Both existing desktop and portalled-mobile accent
mixes now use 12% of the same palette surface over brand instead of 16% (calculated 4.83767:1).
Dark tokens and the accepted visual direction remain unchanged. The focused rendered regression,
shared visual report and independent review must verify the final committed candidate; this entry
does not claim their pending results as passed.

Why: the user requested completing the outstanding T48–T51 visual gates with correct roles and
Welcome authenticated, reusing valid evidence without repeating rollout or interfering with T52.
The coordinator authorized only the two measured contrast corrections needed by that existing gate.
The full delivery record is in `docs/visual-checks/2026-10-29-reference-gates-completion.md`.
No T52 files/worktree, task relations, historical baseline or earlier log entry is changed. This
independent append is kept separate from the still-open reconciliation PR #250 and must be preserved
alongside its history when those documentary branches integrate.


## 2026-10-29 · Deviation

T50 (Reference style refresh), independent visual review round 1: the reception summary's fixed
four-column breakpoint let the actual Catalan text ranges for Ordered and Received overlap by
3.06 px at 1024 px with 200% text, in default/manual-allocation states and both themes. The
document itself had no horizontal overflow; the inherited visual selector omitted `dt`/`dd`, so
its earlier geometry result did not establish this summary's acceptance.

The owning `Manage/Reception/Show.vue` summary now uses the existing auto-fit/minmax responsive
grid approach with an 8 rem metric minimum rather than the viewport-only four-column breakpoint.
The task fixture's summary coverage and a direct text-range intersection regression must include
the owning labels/values, the adjusted fourth metric and risky phone/enlarged-text combinations.
No command, quantity, permission, translation or domain state is changed. Final candidate evidence
and a fresh independent review follow the correction; previous reports retain their own identity.

Why: completing the user-requested T50 no-clipping visual gate requires resolving this confirmed
preexisting collision rather than treating a clean page width as sufficient evidence. This bounded
presentation correction reuses the established responsive grid formula and leaves T52's code and
worktree untouched. All earlier log entries and the historical baseline are preserved.


## 2026-10-29 · Deviation

T50 (Reference style refresh), expanded reception reflow checks: the newly retained 375 px/200%
cases exposed received-total fields and submit buttons that were 256 px wide inside a 245 px
product content area. The same mechanism affected the owning page's nobody-ordered form.
The adjusted and unbalanced status badges also exceeded that content area (484 px and 339 px),
and the adjusted badge exceeded its 432 px content area at 1024 px/200%.

The existing received-total component and nobody-ordered form now constrain their field grids
and inputs to the available width; their submit buttons can wrap and grow vertically. Only the
two variable-text status badge call-sites can wrap within the panel. The shared Badge primitive,
AllocationRow and AddAllocationForm remain unchanged: their visible control bounds passed the
same targeted sweep, including the additional-household branch. Requests, emitted values and
domain decisions remain unchanged. The expanded fixture retains all prior cases and adds these
real owning branches and risky combinations; final verification must cover the corrected candidate.

Why: the user requested completing the outstanding visual gates, and the profile requires no
clipping at enlarged text. A page-level overflow pass did not establish content containment.
These bounded presentation corrections resolve the measured pattern without changing the accepted
rollout or T52's source/worktree. All earlier log entries and the historical baseline remain intact.

The focused recheck showed the field wrapper itself was bounded but its implicit auto grid track
still kept the input's 256 px intrinsic width. Both owning field wrappers therefore use the existing
single-column `grid-cols-1` utility (a zero-minimum fractional track), allowing the label and input
to shrink inside the 245 px content area. This is part of the same measured containment correction.
## 2026-10-29 · Change

`plan.md` (Reference style refresh), `tasks.md` (T47–T51 delivery reconciliation), reference-style
proposal/rollout status, and `docs/visual-checks/2026-10-29-t47-t51-reconciliation.md`: reconcile
accepted pilot/rollout against integrated `origin/main` at `1a9a41a`. PRs #239/#240, T44/#243,
T46/#242 and T36/#245 are integrated; #248/#249 are merged and main CI passed at `1a9a41a`.
The original continuation approval was consulted and preserved. Five retained report sets
(19/164/85/192/514 cases) passed historical manifest/runner/capture integrity and declared
coverage checks, without claiming a current-HEAD browser run. T47/DRA-219 is reconciled to Done;
T48–T51 retain Backlog with integrated implementation and exact role/state evidence gaps recorded.
T36's fixture defaults manager/producer views to member navigation, and Welcome lacks an
authenticated state. These are verification gaps, not a mandate to repeat rollout or confirmed
production defects.

Why: the user requested reconciliation with actual code, integration and existing visual evidence,
preserving accepted work and declaring missing gates. This is plan-only tracking; no R#/S#,
application behavior, fixture, schema or baseline changes. Historical baseline and all earlier
log entries stay byte-identical. T33 remains Todo with 18 native blockers and feature 001 stays
open. No T52–T63 implementation, deployment, new ticket/relation, reparenting or PR merge occurs.


## 2026-10-29 · Deviation

T53 (Retained history and protected catalogue): the existing Manager stock index lists only
stock products. An unused weekly product therefore had no Manager-owned path to the
weekly-to-stock classification action. A Manager producer-products page now lists both modes
from the producer record and exposes classification only for unused records. It links to
stock editing for stock records; weekly editing and deletion remain producer-owned unless
the actor separately holds that producer role. Both classification directions return to
this page so the transferred administrator and permitted next action remain clear. The
Catalog application supplies this read and retained-use flag without granting Manager
general weekly administration. The route and application-query map in `plan.md` records
this access path. This is a page and navigation addition within the accepted R1b/R1c
permissions, not a product rule change.

The pre-Sales Ordering schema still contains temporary pickup stock lines. T53 retains
their removed and zeroed references for classification and stock unit protection until
the accepted Sales split replaces that table. Current reads, totals and claims filter the
archived rows. Product use is derived from Ordering/reception and Stock histories only
at this phase; Sales adds its references in T55. No usage marker or parallel ledger is
introduced.

The DRA-208 visual fixture covers eight affected pages and 88 declared cases using the
actual page components, existing shells, fields, buttons and dialogs. Exploratory
measurements found Manager action links exceeding their content area at 375 px/200%
and an overlay heading exceeding its 151 px text area. Shared action call sites now
wrap; the confirmation title uses the existing short translated action label while its
body retains the explanation. All 28 distinct fixture states passed a first-case
geometry recheck. Final committed-HEAD visual and keyboard evidence will be generated
after candidate base alignment; this entry does not claim that pending gate as passed.


## 2026-10-29 · Deviation

T53/DRA-208 independent review round 1: four pre-existing activation commands still read an
unlocked Product and saved a full snapshot. A concurrent unused delete could be undone by a
stale activation save, and an intervening stock unit change followed by first stock use could
be reverted by a stale stock activation. All four weekly/stock deactivate/reactivate commands
now share the product transaction lock, reload the current row, recheck current-mode authority
and change only active state from the current snapshot. Public cross-connection tests prove
waiting and the final absence of deleted products or preservation of a newly protected unit.
The earlier stale-mode repository guard alone did not cover same-mode metadata overwrite.

Cycle unavailability marks are scheduling metadata, not product-use history. The fresh schema
now cascades a product delete through those marks so an otherwise unused weekly product can
be deleted before or after classification. Retained order, reception and stock-history FKs
continue protecting used products; no usage marker or product-spec rule changes are added.

The visual fixture now drives the actual classification and unused-delete callbacks through
rejected Inertia responses on all three owning pages, adding eight cases to the original
88-case manifest. Action errors display in alert regions and the shared verifier checks
geometry and keyboard focus. The new final committed-HEAD report replaces the older 88-case
evidence after the correction is committed.

The first expanded 96-case browser run exposed one additional focus-scroll pattern on the Manager
producer-products page: after a rejected action, keyboard wraparound brought the heading's add-stock
link under the sticky breadcrumb at 1024 px/200% text. The link now has enough scroll margin for its
focused bounds and hit target to clear that header. A direct Tab path measured the corrected focus;
the final full runner must still bind the result to the amended commit. Comparable Manager heading
actions and the other rejected-action pages were inventoried in the full run, with no failure there.


## 2026-10-29 · CI contract correction

The first published CI run found four off-grid spacing utilities introduced on the producer edit,
Manager stock edit, Manager producer catalogue and shared product-action surfaces. Those utilities
now use the existing spacing grid. The layout rule covers both newly added presentation surfaces,
so future spacing drift on the Manager catalogue and shared action dialog is checked with the
already migrated pages.

Three existing HTTP assertions expected successful weekly or temporary pickup stock-line removal
to erase the database rows. They now assert that the current order, member cycle count, pickup and
pickup total exclude the removed lines while their historical rows retain `removed_at`. Assertions
for rejected writes still require no committed reference. This reconciles the HTTP contract with
the accepted T53 history rule; the product behaviour is unchanged.

## 2026-10-29 · Deviation

The module-contract baseline adds constitution rules A11/A12 and an executable registry of current
module exports, permitted callers, dependencies and migration-created table owners. The plan's
Bootstrap and Testing sections now reference those checks and the current-contract document.
Sales registration and the transfer of pickup ownership remain part of the accepted T52–T63 slices;
this tooling change does not deliver or reorder them, alter business behavior or change schema.

Fresh review found that callback writes could pass the Actor resolver's read-only exception, a
reassigned dynamic-table parameter could bypass call-site ownership checks, and variable SQL on an
assigned connection could escape declaration checks. Rejection fixtures now cover those cases,
nullsafe calls, static dynamic-helper callers and direct business-model access from host code.
Exceptions remain limited to the existing resolver reads, framework authentication integration,
Ordering's unchanged private table parameter and Membership's exact table-free advisory-lock SQL.
The contract document records the supported syntax and static-analysis limits explicitly.

## 2026-10-29 · Change

The user authorized adding module-contract requirements to the task files and existing Linear tickets
while keeping IDs, dependencies and statuses unchanged. The shared task execution contract now requires
manifest/documentation/rejection-test maintenance and the complete architecture suite whenever a slice
changes interfaces or persistence. T54/DRA-209 registers the delivered Sales query surface;
T55/DRA-210 declares basket ownership and integration permissions; T59/DRA-214 reconciles contracts
with the weekly writer cutover; T63/DRA-218 removes retired entries and exceptions with their code.
Stock retains inventory ownership and Wallet retains ledger ownership. T54 and T55 keep their existing
independent prerequisites. These are delivery/check clarifications under A11/A12; product requirements,
the canonical task registry rows and native ticket dependencies/statuses are unchanged.

## 2026-10-30 · Deviation

T54/DRA-209 keeps the accepted read-only stock discovery behavior while reconciling its delivered
interfaces in `plan.md`. The producer products page now composes the existing weekly-only
`ListProducerProducts` with an own-producer `ListProducerStockProducts` query that includes inactive
own stock and current availability; the earlier plan assigned that stock query to Manager. Sales
`ListStockOffers` composes active Catalog stock metadata, including inactive producers, with one
Stock `GetAvailableQuantities` batch through Sales-owned read ports. The Stock-side
`ListStockOffers` basket query remains a future interface; existing `GetStockOffer` and
`GetReturnItem` stay Ordering reads. The quantity export now names both current Catalog and Sales
consumers. The plan's HTTP table marks the member stock page and producer composition as delivered
in T54. This is a plan-only interface split: role permissions, product visibility, read-only scope,
future basket work and product requirements are unchanged. The original historical Request was not
available to this task; this entry refers to the accepted T54 plan and delivered design only.
