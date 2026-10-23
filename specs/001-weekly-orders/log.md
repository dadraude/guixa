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
