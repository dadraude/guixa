# 001 — Weekly orders · Plan

Status: Accepted (2026-10-21) · Spec: [spec.md](spec.md) + areas (R1–R36c, S1–S92) · Rules: [constitution](../constitution.md)

Accepted amendment (2026-10-24): **Application visual identity** below. The user approved the plan;
the associated tasks were separately approved. The user subsequently authorized T39 implementation
on 2026-10-25. The T39 visual pilot was explicitly approved on DRA-191 on 2026-10-25;
T40 is implemented with [rendered evidence](../../docs/visual-checks/dra-191.md).
T41 is implemented for independent review with [rendered manager evidence](../../docs/visual-checks/dra-192.md).
T37/T38 reconciliation is complete: T37 = DRA-195 and T38 = DRA-194, both Done with evidence.
Their native prerequisite links to T39 / DRA-190 are recorded.

Accepted amendment (2026-10-26): **Layout rules** below, delivered by T43–T46 (DRA-197–DRA-200).
Implementation is not yet authorized.

Accepted amendment (2026-10-28): **Products, Sales baskets and Finance**. The user confirmed the
reviewed specification and technical proposal. The [spec](spec.md) and its areas record the amended
rules. This replaces the preceding cycle-bound pickup/stock-product implementation while preserving
unaffected ordering, reception, visual, translation and test conventions. The [task amendment](tasks.md)
is accepted (2026-10-28); implementation is a later stage. There is no production data: design the fresh
schema directly, without a legacy import, data backfill or compatibility layer.

## Bootstrap

- Laravel 13 **Vue starter kit** (Inertia 3 + Vue 3 + TS + Vite, Tailwind 4 + shadcn-vue, Fortify auth). Public registration disabled.
- Postgres via `DB_CONNECTION=pgsql`; a separate `guixa_test` database for integration tests.
- `composer.json` autoloads `Guixa\` → `src/`.
- Dev tooling: `spatie/laravel-typescript-transformer`, Pest arch tests (A1–A8, A10–A17; A13–A17 are the presentation rules, enforced by `PresentationRulesTest`), Oxlint rules (A9), Vitest,
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
| `Shared` | `Money`, `Quantity`, `Clock`, `Actor`, `TransactionRunner` | Time, R22, R36c |
| `Membership` | Households, accounts, roles, invitations; grants/revokes Finance | R33–R36c |
| `Catalog` | Every product, immutable producer, stock flag, metadata and catalogue permissions | R1–R3c, R5, R25–R25c |
| `Ordering` | Weekly demand, cycles, cutoffs, producer orders, reception and distribution | R3b–R18a, R24b |
| `Sales` | Baskets, actual contents, validation, immutable sale snapshots, corrections and collection | R19–R24b |
| `Stock` | Return items, immutable stock movements, availability and movement history | R20a, R26–R28a |
| `Wallet` | Global household balance, charges, confirmed receipts and corrections | R21–R22, R29–R32 |

This is a modular monolith: one application and database, with context-local domain models.
A weekly `Order` represents household demand and allocation; a `Basket` represents the goods actually
sold. A stock basket need not reference any weekly order or cycle. A basket added after a validated one is a
new independent basket with no cycle attribution. Containers are counted per household, not per cycle
(see the household container balance).

```text
Ordering -> Catalog       ProductCatalog / ProducerCutoffRules (weekly products only)
Ordering -> Sales         SalesBaskets (create/sync drafts; validation status and effective quantities)
Ordering -> Membership    ProducerNotifier / Households
Sales    -> Catalog       product identity/eligibility through its catalogue adapter
Sales    -> Ordering      WeeklyBasketSource (ready source, source lock, cycle closure) / WeeklySourceOrders (cycle of a source order, orders of a cycle) / CyclePickupDates (pickup date of each cycle)
Sales    -> Catalog/Stock StockProducts (stock identity and product lock; Catalog adapter) / StockOfferCatalog / StockQuantities / StockSeller / StockCorrector / ReturnItems
Sales    -> Wallet        WalletLedger (one charge/credit; reasoned corrections)
Sales    -> Membership    Households (identity and active state) / AccountNames (who recorded adjustments and collection marks)
Stock    -> Catalog       StockProductRepository (stock identity/metadata and product lock; Catalog adapter)
Stock    -> Membership    AccountNames (author names of movements, deactivated accounts included)
Stock    -> Ordering      CyclePickupDates (pickup date shown for a movement's cycle)
Catalog  -> Ordering      ProducerCycles / ProductUsage (weekly rules and retained references)
Catalog  -> Ordering/Sales/Stock   ProductUsage (retained use: Ordering `CheckProductUsage`, Sales `HasProductHistory`, Stock `CheckProductUsage`) / StockAvailability (pure quantity query)
Catalog  -> Membership    ProducerAccess
Membership -> Catalog     ProducerDirectory
Wallet   -> Membership    AccountNames (author names of movements, deactivated accounts included)
Membership -> Wallet      HouseholdBalances
Membership -> Sales       HouseholdBaskets (unresolved drafts and collections for to-settle views)
```

Ports belong to the caller's `Domain` (or `Application` when their interface requires `Actor`).
Adapters belong to that caller's `Infrastructure/<OtherContext>` and call only the other context's
Application use cases (A4). Translate DTOs at the adapter: no cross-context Domain imports or shared
mutable aggregates. Queries that only expose data depend on their repositories, not on a facade
that eagerly constructs every command; this avoids constructor cycles between reciprocal ports.
All cross-context writes in validation, redistribution or correction use the same local
`TransactionRunner`; notifications are after commit and never carry charge/stock consistency.

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
- **`Actor`** (`Shared\\Application`; `Role` lives in `Shared\\Domain`) `final readonly`: `accountId` (UUID v7 string), `roles` (set of `Manager|Finance|Member|Producer`; `Member` is derived: present exactly when `householdId` is set, never stored), `?householdId`,
  `?producerId`. Built by an HTTP middleware from the authenticated account and passed to every use case.
  It carries identity and role facts; it does not throw permission exceptions.
- Authorization stays inside use cases through Laravel `Gate::forUser($actor)->authorize(...)`, so
  direct calls and scenarios without HTTP enforce the same permissions (R36a, R36c). Broad abilities
  use gates; resource ownership and product access use policy methods registered in the service
  provider. Policies receive the explicit actor and resource IDs or facts, without loading Eloquent
  models into Application. A failed check throws Laravel's `AuthorizationException`, rendered as 403.
- Finance permissions use semantic abilities for managing sales and wallets (Manager or Finance),
  and a household collection policy (Manager, Finance, or a member of that household). Membership
  requires the account's household link (R35). Preserve the separate manager/producer/member
  permissions for unrelated operations; there is no global Manager bypass. Basket state, quantities,
  cutoffs and other business invariants remain in framework-free domain objects. A future Commissions
  implementation can resolve the same capabilities without changing every use case.
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
    the manager or finance role, or a producer role, which counts only while its producer is active (R3a). `account_roles` holds
    only explicit roles `manager`, `finance` and `producer`; Member is derived and never inserted.
  `HouseholdBaskets` includes unresolved drafts and validated pending collections, so deactivating
  a household cannot hide its unvalidated obligations from the to-settle view.
- `Invitation` (account id, SHA-256 hash of the token, `sentAt`): one per account, expires 7 days after it is
  sent (`now > sentAt + 7 days` is expired; 7 days means 168 elapsed hours, so the expiry is the same across a daylight-saving change and whatever timezone `sentAt` carries); issuing another replaces it, so resending or changing the email
  invalidates the previous link (R34). The plain token only exists in the email.
- `Email` value object: trimmed, lowercased, validated.
- Ports: `HouseholdRepository`, `AccountRepository`, `InvitationRepository`, `InvitationSender`, `AccountPasswords`
  (`change(accountId, plainPassword)`, so hashing stays in Infrastructure), `ProducerDirectory` (`exists(producerId)`, implemented
  by calling Catalog's `GetProducer`), `HouseholdBalances`, `HouseholdBaskets`.

Use cases
| Use case | Actor | Covers |
| --- | --- | --- |
| `CreateHousehold`, `RenameHousehold` | manager | R33, S56 |
| `AddMember` → sends invitation (an inactive household is reactivated) | manager | R33, R34, R35a, S25 |
| `ResendInvitation`, `ChangeInvitationEmail` | manager | R34, S25 |
| `AcceptInvitation` (sets password, activates) | invitee | R34, S25 |
| `MoveMember` (an inactive target household is reactivated; it also links an unlinked account; moving to the current household does nothing), `UnlinkMember` | manager | R35, R35a, S43 |
| `GetHouseholdDeactivationWarning` (reads balance, unresolved basket drafts and pending collections before the action), `DeactivateHousehold` (unlinks members and deactivates atomically), `ReactivateHousehold` | manager | R35a, S58 |
| `ListHouseholdsToSettle` | manager | R35a, S43, S58 |
| `ListHouseholds`, `GetHousehold` (members with pending invitation and its expiry) | manager | R33, R34 (admin pages) |
| `CanLogIn` (accepted invitation and not deactivated; the Fortify login adapter calls it after the password check) | — (login) | R34, R36b, S25 |
| `SendInvitation` (issues the invitation and emails it after commit; used by `AddMember`, `ResendInvitation`, `ChangeInvitationEmail`) | — (internal) | R34 |
| `InviteProducerAccount` (one per producer: an unknown producer throws `UnknownProducer`, a second account `ProducerAlreadyHasAccount`; creates a producer-role account and sends the usual invitation) | manager | R36, S57 |
| `GrantManagerRole`, `RemoveManagerRole` | manager | R36a, S44 |
| `GrantFinanceRole`, `RemoveFinanceRole` (existing accounts; role is effective independently of household membership) | manager | R36c, S84 |
| `DeactivateAccount`, `ReactivateAccount` | manager | R36b, S59 |
| `ListAccounts` (roles, household name, active, invitation pending) | manager | R36a, R36b (accounts admin page) |
| `SuspendProducerRole`, `RestoreProducerRole` | — (called by Catalog) | R3a |
| `ListProducerAccounts` (producer id → account email and whether its invitation is pending; the producers admin page shows it and hides the invite form for producers that have one) | manager | R36, S57 |
| `GetProducerAccountEmail` | — (called by Ordering) | R12a |
| `GetWalletHousehold` (a household's id, name and active state for the wallet pages) | finance, manager | R31 |
| `GetAccountNames` (account id → name in one batch, deactivated accounts included) | — (called by Stock, Sales, Wallet) | R28a, R23a, R31 |
| `ListActiveHouseholds`, `ListOtherHouseholds` (every household but the given one, for `MoveMember`), `CountHouseholds`, `CountAccounts` | manager | R33, R36a (admin pages, dashboard counts) |
| `GetHouseholds` (household ids → name and active by id; unknown ids left out) | — (called by Ordering) | R13, R16, R19 |
| `CreateFirstManager` (rejected once any manager exists) | — (installation command) | R36a |

A household is deactivated automatically when `MoveMember`, `UnlinkMember` or `DeactivateAccount` leaves it
without members (R35a), through the shared `DeactivateEmptyHousehold` service. Deactivating it unlinks its members; weekly orders remain in Ordering, while baskets and collection history remain in Sales.
Membership-link changes and household-state writes take one transaction-scoped Membership lock before reading accounts or households; account deactivation takes that lock before manager-row locks. A command re-reads its inputs after locking, so manual deactivation, add/move/unlink and reactivation serialize with current membership state.
`AccountRepository::save` keeps an existing account's stored household link while saving account details and roles; new accounts keep their initial link. Membership-changing commands persist the link separately through `saveHouseholdLink` under that lock, so a stale account-detail save cannot restore an old link.

Infrastructure: accounts are the `users` table through `App\Models\User` (A6 exception).
Households live in `households`; invitations in `invitations` (one row per account, `token_hash`, `sent_at`);
`users` gains `activated_at` and `deactivated_at`, and `users.household_id` references `households`.
`InvitationSender` emails the link `/invitations/{token}` (D15). Login (Fortify `authenticateUsing`) needs the right
password, `activated_at` set and `deactivated_at` empty; accepting an invitation also marks the email verified.
The login adapter keeps case-insensitive account lookup, then uses Fortify's configured guard provider to validate
the password and, only for an eligible account, rehash it when `hashing.rehash_on_login` allows. Fortify retains
session login and the two-factor challenge pipeline.
An account created by an invitation holds a random password nobody knows until the invitee sets theirs.
Passkey login does not go through `authenticateUsing`, so `Passkeys::authorizeLoginUsing` asks `CanLogIn` too, and the
`LogOutDeactivatedAccount` web middleware ends the session an account already had when it is deactivated (R36b).
`AccountRepository::lockManagers()` locks every manager row in id order (`SELECT … FOR UPDATE`) and `hasOtherActiveManager()`
reads them afterwards, in a new statement, so a waiting transaction sees the other one's commit.

## Catalog

### Domain and administration

- `Producer` retains its id, name, active flag and default `CutoffRule` (R3a, R5). The producer id of a
  product is fixed at creation; there is no reassign-producer command.
- One `Product` entity owns id, producerId, name, unit, `Money` price, variable flag,
  `?Quantity containersPerUnit`, active and `stock: bool`. There is no parallel Stock product entity
  with duplicated metadata. Selling the same article through both circuits means two product ids.
- Administration depends on current mode. Producers create/edit/deactivate/reactivate their own
  weekly products; Manager creates stock products from a producer's record and administers them.
  Manager alone may change the stock flag before any use; the new mode transfers administration.
  Finance does not administer product records. Product deletion is a distinct unused-only action;
  deactivation retains used records. Existing deactivation actions do not become physical deletes.
- Weekly unit/variable changes retain R1's unsettled-active-line guard. Stock unit/variable changes
  are forbidden after any stock movement or basket line, even if all quantities later return to zero.
  Name, price and containers remain editable under the relevant snapshot rules.
- `ProductUsage` is a read port: `hasAny(productId)` includes retained order/basket lines, receptions
  and stock movements; `hasStockUse(productId)` includes basket stock lines and movements.
  Adapters ask Ordering, Sales and Stock's internal history queries. These queries intentionally
  include removed/cancelled history. No usage bool, timestamp or independent usage ledger is added.
  The existing unsettled-line query continues to exclude removed/cancelled lines.
- Every operation that first references a product acquires the same Catalog product-row lock before
  eligibility checks and persisting that reference. Reclassification, protected unit/variable edits
  and unused deletion acquire that lock before checking history. Commands run in one transaction;
  a failed order/basket/entry does not create a committed use. History reads alone are not the race guard.

### Application interface

| Use cases / queries | Actor and rules |
| --- | --- |
| `CreateProduct`, `UpdateProduct`, `DeactivateProduct`, `ReactivateProduct` | Current catalogue administrator according to mode; producer ownership for weekly records |
| `ChangeProductClassification` | Manager; unused only; never changes producer |
| `DeleteUnusedProduct` | Current administrator; history check under lock |
| `ListProducerProducts` | Own producer; editable weekly records only in T54 |
| `ListProducerStockProducts` | Own producer in T54; read-only own stock including inactive records, with current availability |
| `ListProductsOfProducer`, `IsProductUsed`, `GetProductProducer` | Manager catalogue read across both modes, retained-use flag for allowed actions and producer context |
| `ListStockOfferProducts` | Internal Sales read in T54; active stock metadata including products of inactive producers, without quantity |
| `GetOrderableProduct`, `ListOrderableProducts` | Internal; active weekly products of active producers only |
| `ListProducerProductsForReception` | Internal; weekly products including inactive records/producers; never stock |
| `GetStockProduct`, `ListStockProducts` | Internal metadata queries; mode stock, with producer id/name; stock sale does not require producer active |
| `LockWeeklyProduct`, `LockStockProduct` | Internal; lock, verify the expected mode and operation-specific active rules, return a snapshot |
| `CreateStockProduct`, `UpdateStockProduct`, `DeactivateStockProduct`, `ReactivateStockProduct` | Manager; stock-mode administration from the producer context |
| `CreateProducer`, `ListProducers`, `DeactivateProducer`, `ReactivateProducer` | Existing Manager permissions |
| `GetProducer`, `GetProducerCutoffRule`, `SetDefaultCutoff`, `GetDefaultCutoff` | Existing internal/own-producer rules |

`StockAvailability` maps product ids to Stock's pure quantity query in one batch; do not query one
product at a time. The Stock metadata queries above do not call that composition query back.
The producer products HTTP page composes the weekly-only and own-stock queries. Its stock section
has no edit actions; Manager's cross-producer catalogue remains a separate interface.
The product lock is exposed through a Catalog Application interface and a caller-owned port;
Stock/Sales/Ordering never import the Catalog entity or persistence model.

Producer deactivation keeps the existing single transaction: Catalog deactivates it, calls
Membership's suspend-role use case and Ordering's stop-participating-where-not-sent use case.
Reactivation restores the role, not old participation. Stock products remain independent: active
ones can still be sold; account deactivation and Finance/member roles keep their own existing rules.

## Ordering

### Weekly model

`CooperativeSettings`, `PickupWeekday`, the local `CutoffRule`, `Cycle` and `ProducerOrder` keep the
accepted cutoff, participation, sending, reception-validation and distribution rules. The catalogue
interface now admits only weekly products, including the reception path for unsolicited goods.
A product's cycle availability also applies only to weekly products.

`Order` owns one household's weekly demand in one cycle, unique on household/cycle. It contains
weekly `OrderLine`s: stable id, product/producer ids, product snapshot, ordered and allocated quantity,
manual-allocation flag, creation actor/time, not-delivered/dropped flags and optional removal time.
It no longer owns stock lines, return lines, pickup state, actual delivered quantities, a charged
amount or a completion timestamp. Those belong to Sales. Allocation-only lines retain ordered 0.

Removing a line archives it; active readers exclude it. Re-adding creates a new line id/time, preserving
R15's tie rule. Cancelling a cycle archives its orders/lines in the same transaction, not deleting them.
Members still see cancellation with no active contents/totals. History/first-use queries deliberately
see archived references. Active totals, source snapshots, claims and member views never include them.

`ProportionalAllocator` stays a pure integer-arithmetic domain function. It floors each proportional
share to the product step (whole units or 0.001), then gives residual steps to descending remainders,
breaking ties by original active-line creation time and UUID v7 record order. Result sum equals target.
No claims/zero ordered total remains `NobodyOrderedProduct`; manual-allocation overwrite still requires
explicit confirmation. Do not change these algorithms as part of the Sales split.

`ReceivedTotal` and `Reception` keep the sent/validated/distributed guards and received-versus-allocated
balance. Sales supplies effective actual quantities once a basket exists; before that actual equals
allocation. Reception flags the resulting actual-versus-allocation difference, but distribution checks
allocated quantities, preserving R18/R19 and S61. Actual quantities have one mutable owner, Sales;
Ordering does not maintain another delivered column to synchronize back on every basket edit.

### Settlement, reopening and closure

1. `MarkProducerDistributed`, under the cycle lock, verifies validation and allocation balance.
2. At first settlement, drop marked/nonparticipating lines from active fulfilment, retaining history.
   Through `SalesBaskets`, create one idempotent base draft per source order with eligible allocations
   and its full ordered product snapshots. Retrying settlement cannot create a second base basket.
3. After a permitted reopen, a product whose previous received total was 0 is a late arrival: its
   allocations are added to the household's open weekly draft (base or late), reviving a zero-content
   base while the cycle is open. A household whose weekly baskets in the cycle are all validated gets
   one late weekly basket draft from the same source order with only the late lines. A household has
   at most one unvalidated weekly draft per cycle; source uniqueness is one base plus at most one open
   late draft per source order.
4. Any other allocation change after a reopen is a redistribution: synchronize it into unvalidated
   weekly drafts, resetting actual overrides on the affected weekly lines as the earlier plan did and
   keeping stock/return lines intact. A validated basket is financially frozen even if physically
   pending collection: under the cycle lock, reject the redistribution of a product that any validated
   weekly basket of the cycle contains, before anything changes, and point staff to that basket's
   corrections (T58/T68). Reception never appends adjustments or Wallet corrections to validated baskets.
5. Close when settled, every participating reception is distributed and every base and late weekly
   basket is validated or has nothing to collect. Stock-only/supplementary baskets do not gate weekly closure, even when
   they reference the cycle. Collection remains possible after closure, with a visible pending indicator.
   Closed weekly orders/receptions cannot reopen; later sale corrections retain their original records.

Delivered by T59: after settlement an unvalidated base follows reception changes at once (sync on every
allocation write and on re-distribution), resetting the actual quantity only of lines whose allocation
changed; a validated base is never synchronized. Weekly validation and actual-quantity edits are refused
while a producer is reopened after settlement. A scheduler-created base has no creator (`baskets.created_by`
is nullable). The drafts-only/all choice and reception adjustments to validated baskets are removed
(Change 2026-11-03). Staff reach a cycle's bases through `/finance/baskets?pending=1&cycle=`; the cycle pickups index is removed (T72, DRA-234)
and only the old per-household pickup URL remains, redirecting to the basket.

Ordering's `SalesBaskets` port creates/synchronizes sources and reads validation/effective-quantity
summaries. Sales's `WeeklyBasketSource` calls narrow Ordering use cases for source readiness, its
cycle lock and closure. DTOs are translated by adapters; avoid a shared domain entity or facade
constructor cycle. Stock-only validation does not call Ordering readiness/closure at all.

### Application interface

| Use cases / queries | Actor and rules |
| --- | --- |
| `SetPickupWeekday`, `GetPickupWeekday`, `OpenCycle`, `ListCycles` | Existing Manager rules; no weekday default; permanent/provisional setup unchanged |
| `AddParticipant`, `StopParticipating`, `ParticipateAgain` | Manager; active producer, not sent, preserve cutoff |
| `SetCutoff`, `CloseProducerNow`, `ClearCutoff` | Manager; existing time bounds and sent guards |
| `CancelCycle` | Manager; before any send; retain archived orders/lines |
| `SetProductAvailability` | Own producer; weekly only, before send |
| `SetOrderLine`, `RemoveMarkedLine` | Member of the household; existing cutoff/quantity/snapshot rules; archive removals |
| `GetHouseholdOrder`, `ListMemberCycles` | Own household; link to its Sales base and related baskets after settlement |
| `SendProducerOrder`, `SendEmptyProducerOrders` | Manager / existing every-minute scheduler; notify totals after commit; reuse settlement |
| `GetProducerCycleSummary`, `GetCycleSummary` | Own producer / Manager, with existing privacy and cancellation rules |
| `GetReception`, `AddUnorderedProduct`, `SetReceivedTotal`, `SetAllocation` | Manager; weekly only; existing sent/editable/quantity guards |
| `ValidateReception`, `MarkProducerDistributed`, `ReopenProducerOrder` | Manager; existing balance guards and closed-cycle restriction |
| `GetContainerCount` | Manager; per cycle, the expected container count of its orders, plus each household's accumulated balance read from Sales (validated lines minus return lines) |
| `HasUnsettledLines`, `CheckProductUsage`, `StopParticipatingWhereNotSent` | Internal queries/command for Catalog; usage includes cancelled/removed records |
| `WeeklySourceData`, `LockWeeklyBasketSource`, `CloseCycleAfterBasketValidation`, `ReadSourceOrderCycles`, `ReadCycleSourceOrders`, `ReadCyclePickupDates` | Internal Sales interface; snapshots/readiness/closure, source-order and pickup-date lookups only |

The existing `ProductCatalog`, `ProducerCutoffRules`, `ProducerNotifier`, `Households`, repository
and clock ports remain, with a Sales port replacing the pickup-facing Stock/Wallet ports. Sending
retains derived-before-send/stored-after-send not-delivered marks (D9); empty sent orders still
validate/distribute through the scheduler and common settlement (D10).

## Sales

### Basket aggregate and history

`Basket` is independent of `Order`: id, householdId, optional sourceOrderId (base weekly basket),
draft/validated/cancelled/nothing-to-collect state, creation/validation
actor/time and the immutable validated total. A base basket is unique per source order; any number of
stock-only baskets can exist for a household without a source order or cycle.
A weekly base uses its source cycle through its source order: the basket stores no cycle column, and Sales
resolves a source order's cycle, or a cycle's source orders, through Ordering's narrow read exports. Any other basket, including one added
after a validated basket, has no cycle. It never imports the weekly contents again.

The household container balance is a derived read model, not a stored duplicate: per household, the sum
over validated basket lines of the effective quantity × snapshotted containers per unit (rounded up per
line), minus the containers of validated return lines. It is not grouped by cycle and creates no money
movement; the container price is already in the product price.

Lines have their own stable ids and kind: weekly, stock or return. Weekly lines retain source line id,
ordered/allocated snapshot and actual quantity; stock lines reference the single Catalog product;
return lines reference Stock's return item. Every line stores its applicable name/unit/price/variable/
containers snapshot (return lines store name/amount). No price lookup rewrites validated history.
Stock quantity additions retain the first active line's price/container snapshot and check the combined
quantity. Removed draft lines are archived. Re-adding produces a new active line/snapshot.
Weekly actual quantity may be zero; stock/return additions must be positive and respect the original steps.

Only Finance/Manager creates/edits stock baskets, changes actual weekly quantities, adds/removes stock
and return lines and validates. Members consult their own baskets and stock catalogue but never edit
contents or validate. Catalogue/inventory administration remains Manager-only; Finance's stock access
is sales and linked corrections. Source or household ownership is always derived/verified server-side.

A draft is uncharged and stock is not reserved. Stock-only drafts can be cancelled while retaining
history. A base with no positive quantity and no stock/return contents has nothing to collect and
requires no charge; a late arrival or permitted redistribution can restore it to draft before cycle closure. Free goods
or a zero net total with real contents can still be validated. A return-only basket may yield a credit.

### ValidateBasket: the transactional seam

`ValidateBasket` is the public Sales Application use case. Within one `TransactionRunner`:

1. Authorize Finance/Manager. For a weekly source, acquire its cycle/source lock and require settlement
   with the latest synchronized source. Stock-only baskets need no cycle.
2. Lock/reload the basket, reject cancelled/already-validated drafts, and validate its effective contents.
   Original weekly snapshots are retained; actual quantities determine the charge. Reject stale source edits
   rather than applying a view of allocation that changed concurrently.
3. Lock stock product identities in product-id order through the caller-owned Catalog/Stock interface,
   then check availability and record stock sales by basket id. Existing inactive-product lines may still
   be validated as previously agreed; no new/increased inactive product line is allowed.
4. Compute the total with line-level half-up rounding: weekly actuals + stock − return credits. Append
   one Wallet charge/credit by basket id (no movement for zero). A negative household balance remains allowed.
5. Save the immutable validation snapshot and validation actor/time. For a weekly source, attempt cycle
   closure under the same source-cycle lock. Any failure rolls back validation, stock and money together.

Physical collection does not belong to this transaction's financial effects. A confirmed receipt is
recorded through Wallet independently: real money received is not undone because a basket fails validation.
`StockSeller` and `WalletLedger` are Sales-owned ports, not Domain imports. Idempotency is defended by
basket state plus unique source references in stock/wallet persistence; duplicate validation never creates
another sale or charge. No eventually consistent event is used for charge/stock invariants.

**Lock order.** Every weekly mutation locks cycle, then basket, then product (in product-id order),
never the reverse. Stock add/remove on a weekly draft takes no cycle lock: it locks only the basket
(`AddStockToBasket` also locks the stock product it adds), which serializes it with source sync and
validation, since those lock the same basket row after the cycle.

### Collection and corrections

Validation creates pending collection. Finance, Manager or a current member of that same household
may mark the validated basket collected, recording actor/time. Repeated collection is a no-op that
preserves the first record and never charges/sells stock again. There is no payment-status condition:
receipts and the global Wallet remain independent. Delayed collection stays pending after the pickup
date and after cycle closure; there is no date-based abandonment or automatic stock return/refund.

When goods were already taken and reported later, staff creates the stock basket, keeps the recording
instant, validates it and records collection. The sale is never backdated to a cycle. If stock
records do not permit the sale, reconcile the inventory discrepancy through the existing justified
inventory process before validating; do not allow negative stock to bypass R27.

Corrections append actor/time/reason and signed quantity/amount/stock changes linked to the basket
or original receipt. Original lines, validation amount/time and ledger movements remain intact.
Finance/Manager may correct the agreed basket/receipt operations; they do not thereby acquire unrelated
product or inventory administration. Cross-context corrective stock/money changes are atomic, preserve
stock non-negativity and are idempotent by correction id. Distinguish pure financial correction from
physical goods actually returned. Reception redistribution never uses this mechanism: a validated
basket blocks redistribution of its products, and staff correct it individually.
For a validated independent basket's amount-only correction, Sales appends a `basket_adjustments`
row and Wallet appends a correction with the same identity in one transaction. The basket id is
the original source, including when a zero-total basket has no base charge or credit. Its effective
total is the immutable validated total minus the sum of signed Wallet correction amounts. Replay
with the same basket, actor, amount and trimmed reason has one effect; changed contents under the
same identity are rejected. Amount-only corrections never change quantities or Stock movements.
A physical goods correction uses the same `CorrectBasket` command with the returned quantity of
each stock product. Sales appends one signed `basket_adjustment_lines` row per product (negative: goods
left the basket) to the correction; Stock records one `adjustment` movement per line through
`CorrectBasketStock`, linked to the basket and to the correction id and carrying the reason. Its
product-row lock and ledger keep stock non-negative, and a movement is unique per correction and
product. Sales, Stock and Wallet effects share one transaction, so a later failure rolls back every
earlier write. A product's effective quantity is its immutable line quantity plus the signed
correction lines; a return cannot exceed it. The read model exposes original and effective quantity
and amount per line, and `pendingCollection` is false once every stock line's effective quantity is
zero and the basket has no active return lines (returned empties still need collection), while the
validated state and original contents remain.
A mistaken collection mark is corrected by staff with a reason and retained collection history,
without financial changes. A full contents reversal leaves no effective goods pending collection.
Collection history uses a basket-local append sequence assigned while holding the basket lock.
Event time and correction identity remain intact but do not determine causal display order when
collection, correction and recollection share a stored second. Replaying a correction does not append
another event or sequence number.
`GetBasket` and `GetMemberBasket` resolve the recorders of a basket's adjustments and collection
events in one batch through Sales' `AccountNames` port (Membership adapter over `GetAccountNames`)
and pass the names to `BasketData`. `BasketAdjustmentData` and `BasketCollectionEventData` carry
`authorName` in place of the account id, so no account identifier reaches the page (R23a); an
unknown account gives an empty name. List queries resolve no names: only the basket pages show history.

### Application interface

| Use cases / queries | Actor and purpose |
| --- | --- |
| `CreateWeeklyBasket`, `SyncWeeklyBasket` | Internal Ordering calls; idempotent source order, authoritative allocation snapshots |
| `CreateStockBasket` | Finance/Manager; active household; no cycle |
| `SetBasketActualQuantity`, `AddStockToBasket`, `RemoveStockFromBasket`, `AddReturnToBasket`, `RemoveReturnFromBasket` | Finance/Manager; draft only; archive removed references |
| `CancelDraftBasket` | Finance/Manager; stock-only draft, no money/stock effect; retain history |
| `ValidateBasket` | Finance/Manager; single charge/stock transaction |
| `MarkBasketCollected` | Finance/Manager or own household member; validated only, no financial effect |
| `CorrectBasket`, `CorrectBasketCollection` | Finance/Manager; mandatory reason, append-only history |
| `ListBaskets`, `GetBasket` | Finance / Manager; existing operational reads remain staff-only |
| `ListMemberBaskets`, `GetMemberBasket` | Current own-household member; repository filters by household for list and identifier reads |
| `ListPendingCollections`, `ListMemberPendingCollections`, `ReadPendingCollections` | All (optionally one weekly cycle) for Finance/Manager; own household for members through the separate member query; internal read without actor; all use `BasketRepository::pendingCollections`; include closed-cycle and cycleless baskets, exclude drafts |
| `ListStockOffers` | T54 Member or Manager; active stock including zero, inactive producer allowed; no reservation/edit capability. Finance sales-staff access belongs to a later slice. |
| `HasProductHistory`, `ReadOpenHouseholdBaskets`, `ReadWeeklyBasketStates`, `ReadHouseholdContainerBalances` | Internal Catalog/Ordering/Membership queries; include history only where requested. Effective weekly quantities reach Ordering through its `SalesBaskets` port |

Pending collection lists are ordered by validation instant, then UUID; monetary movement lists keep
their existing recorded-time/id ordering. Per-basket container totals sum rounded-up line snapshot counts; the household balance sums validated
baskets of any day and subtracts return lines.
In T54, `ListStockOffers` composes Catalog's `ListStockOfferProducts` metadata with Stock's
`GetAvailableQuantities` through Sales-owned read ports. One quantity batch supplies current
availability, with zero for products without movements. This discovery query has no basket,
reservation or stock-write dependency; later basket operations remain planned separately.

## Stock

`Stock` owns `ReturnItem`, immutable `StockMovement`, `SignedQuantity` and `StockLedger`.
It no longer owns catalogue product metadata or `StockProduct` CRUD. All stock product references
are Catalog `productId`s. `ReturnItem` retains its name, positive fixed amount and active flag,
with Manager-only administration; returns in baskets retain the already agreed snapshot rules.

`StockMovement`: id, productId, type (entry/adjustment/sale), signed quantity, recorded instant/actor,
optional note/cycleId, and optional basketId/correctionId. Entries are positive, adjustments are
non-zero with a mandatory note, sales are negative with a basket source. Availability is the sum
of committed movements; it never goes negative. A basket sale is recorded on validation, not collection.

`StockProductRepository` is a Stock-owned port (Sales has its own `StockProducts` port) whose Catalog adapter locks and verifies the authoritative
product id/mode. The same product-row lock protects both stock consistency and classification/deletion
races. The Stock repository reads/inserts only its own movement tables; it does not import a Catalog
model or directly lock a foreign-context persistence class. All stock writers use this protocol.
No duplicate lock-anchor product entity/table is introduced. Catalogue metadata composition uses
Catalog's pure internal metadata queries; quantity-only queries never call Catalog back.

| Use cases / queries | Actor and rules |
| --- | --- |
| `CreateReturnItem`, `UpdateReturnItem`, `DeactivateReturnItem`, `ReactivateReturnItem` | Existing Manager-only administration |
| `ListReturnItems` | Manager catalogue view and Finance's read-only basket return picker; no Finance editing |
| `RecordStockEntry`, `RecordStockAdjustment` | Manager; stock mode only; positive entry / non-zero justified adjustment |
| `ListStock`, `ListActiveStock` | Manager; metadata from Catalog, current quantity from ledger. `ListStock` also serves Finance's read-only stock list (R28a) |
| `ListStockMovements` | Manager or Finance for any stock product; a producer for their own (`owns` producer gate on the product's `producerId`), including inactive products and producers; weekly products are rejected as not stock. Returns one page of `StockMovementLine`s newest first, the total and the product header (name, unit, available) that Finance and producer pages show even for a deactivated product (R28a) |
| `SetStockAvailable` | Manager; sets the available quantity after an inventory count: inside one transaction it locks the product, reads the available quantity under that lock and records an adjustment of the difference (R27). The caller passes the note already translated, since Application takes no framework i18n (A2) |
| `AppendStockAdjustment` | Internal step for `RecordStockAdjustment` and `SetStockAvailable`; builds and records the adjustment, takes no lock: the caller holds the product lock in its transaction |
| `GetReturnItem` | Internal read of a return item snapshot for Sales through its own return-items port |
| `SellBasketStock` | Internal Sales operation, records the already-authorized actor and the basket source; locks and checks stock |
| `CorrectBasketStock` | Internal Sales correction with source/reason; cannot make stock negative |
| `GetAvailableQuantities` | T54 quantity-only batch query for Catalog and Sales discovery; no Catalog callback |
| `CheckProductUsage` | Internal movement-history query for Catalog |

`ListStockMovements` reads the product's whole ledger oldest first, ordered by `(occurred_at, id)`
(UUID v7 ids break ties stably), and accumulates the available stock after each movement, so the
balance needs no stored column and the newest line equals `available` (R28). It then reverses the
list and slices page `n` of 50; per-product ledgers are small, so no SQL window function or cursor is
needed. A line's displayed type is `correction` when an adjustment has a `correctionId` (`CorrectBasketStock`
records corrections as adjustments carrying the reason as note); otherwise its `MovementType`.
`StockMovementLine` carries date, type, signed quantity, balance after, note, author name, optional
cycle id and pickup date, and `?basketId`. Author names and cycle pickup dates are resolved in one
batch each through `AccountNames` and `CyclePickupDates`. For an actor reaching the product only as
its producer, the use case sets `basketId` to null, so the basket never leaves the server; Manager and
Finance get it on sale and correction lines. The history is read-only and takes no lock.

Sale uniqueness is by basket/product; corrections have their own stable source ids. Product ids
remain locked in ascending order for multi-product writes. Finance accesses sale/correction use cases
through Sales and does not gain arbitrary stock entry, adjustment or catalogue CRUD permissions.

## Wallet

`Movement` remains an immutable signed-money record: id, householdId, type
(`TopUp|Charge|Credit|Correction`), amount, recording instant/actor, note and optional cycle,
basket or corrected-movement reference. Charge/credit originates in a validated basket, not the
household/cycle pair. A standalone basket has no cycle. Manual receipts are positive top-ups with
no required basket allocation; corrections are non-zero and require a reason.

`MovementRepository` is append-only: add, balance and statement, ordered by recorded instant/id.
Enforce one base charge/credit per basket; independent baskets of the same household/cycle can each
charge once. Amount zero records no movement. Existing negative-balance policy remains. Original
movements cannot be edited or deleted; corrections preserve their references and reason.
For confirmed receipt corrections, the statement form supplies the original receipt movement id and a
client-generated correction id. The Wallet command verifies that the original is a top-up of the same
household and treats a retry with the same id and contents as one movement; changed contents under an
existing id are rejected. The database keeps the corrected-movement id nullable only for retained
cycle-scoped Ordering redistribution corrections until their separate cutover. Receipt HTTP requests
cannot supply a cycle id or use that legacy path.

| Use cases / queries | Actor and rules |
| --- | --- |
| `RecordTopUp` (confirmed cash/bank receipt) | Finance/Manager; positive; independent of basket validation |
| `RecordCorrection` | Finance/Manager for agreed receipt/basket corrections; mandatory reason/source |
| `ChargeForBasket` | Internal Sales call carrying the authorized actor and basket id; charge/credit/zero |
| `GetBalance` | Internal query for authorized Sales/Membership compositions |
| `ReadHouseholdBalance` | Internal, no actor; Membership's household settlement (R35a) |
| `GetWalletStatement` | Own household member / Finance / Manager; author names in one batch through Wallet's `AccountNames` port |

`MovementData` and `WalletMovementRow` carry `authorName` in place of the recording account id, so the
statement never shows an identifier (R31); an unknown account gives an empty name. The port mirrors
Stock's: a Wallet `Domain` interface with a Membership adapter over `GetAccountNames`.

No paid/partially-paid state exists on a basket. A receipt can cover several charges or leave credit.
No payment-provider integration, producer invoicing, cash payout or automated bank reconciliation is
introduced by this amendment. Household deactivation retains balances, receipts and corrections.

## Persistence (Postgres)

Design the fresh schema directly: no production data migration, legacy stock-product import, UUID
remapping or dual-schema compatibility. Reorder creation migrations so Catalog products precede
referencing tables and Sales baskets precede stock/wallet sale-source references. The Wallet creation
migration defines its final basket and correction references directly after baskets; no Wallet alteration
chain or production backfill is required before launch. Existing unrelated
settings, accounts, invitations, producers and return-item constraints remain.
All ids are UUID v7; money is integer cents, quantities numeric(10,3), times timestamptz in UTC.

| Table | Relevant fields / constraints |
| --- | --- |
| `products` | id, immutable producer_id FK, stock bool, name/unit/price_cents/variable/containers_per_unit?/active; existing positive bounds; no usage marker |
| `account_roles` | PK(user_id, role); explicit manager/producer/finance values; member remains derived |
| `cycles`, `cycle_producers`, `product_unavailabilities`, `received_totals` | Existing cycle/cutoff/reception model; weekly-product eligibility enforced through use cases |
| `orders` | id, cycle_id, household_id, cancelled_at?; unique(cycle_id, household_id); no pickup/charge columns |
| `order_lines` | Existing weekly snapshot/ordered/allocated/manual/creation fields plus removed_at?; no delivered column; one active line per order/product using a partial unique index |
| `baskets` | id, household_id, source_order_id?, kind weekly/stock, state, created/validated/cancelled actor/time, validated_total_cents?; source order unique when present; source/household consistency checked at creation |
| `basket_lines` | id, basket_id, kind weekly/stock/return, product_id? or return_item_id?, source_order_line_id?, product/return snapshot, ordered/allocated snapshot?, actual quantity, removed_at?; kind-specific checks and one active source/item per basket |
| `basket_adjustments` | id, basket_id, original line/source reference, signed quantity/amount/stock delta or target, reason, actor/time; append-only, unique correction source; validated original fields remain unchanged |
| `basket_collection_events` | id, basket_id, collected/corrected-to-pending event, actor/time, reason on corrections; append-only; derived current collection state |
| `return_items` | Existing id/name/positive amount/active |
| `stock_movements` | product_id -> products, signed quantity, type, recorded actor/time, note?, cycle_id?, basket_id?, correction_id?; sale requires basket; one sale per basket/product |
| `wallet_movements` | household_id, signed amount/type, recorded actor/time/note, optional cycle_id/basket_id/corrected_movement_id/correction_id; one base charge/credit per basket |

`stock_products`, `order_stock_lines`, `order_return_lines`, `order_pickup_adjustments` and the old
household/cycle unique-charge index are replaced by the models above. History FKs must not cascade
away used product references. Cancel/archive operations retain their rows; current repository methods
filter removed/cancelled contents, while explicit history methods do not. Re-adding an archived line
creates a new row/time so proportional tie-breaking stays correct.

Current Basket and Cycle repository list reads load their children through ordered Eloquent
relationships, one child query per relationship rather than one query per parent. Basket lines include
removed history and retain their stored decimal snapshots; ordered basket adjustments and collection
events retain correction history by basket-local append sequence. Cycle producers retain
participation and timestamp state. Single-record reads and locks still load children after
selecting or locking the parent, and protected basket
snapshot comparisons continue to use the stored lines.

Concurrency and atomicity:
- Weekly mutations lock their stable-key source cycle with `FOR NO KEY UPDATE` before affected
  orders/baskets; acquire multiple ids in stable order. Existing weekly line-edit guards use a
  conflicting shared/mutation lock so cutoff/send cannot race an order edit. Cycle ids never change.
  Stock-only operations do not acquire any cycle lock: they carry no cycle reference.
- Source synchronization and weekly basket validation serialize on that source-cycle lock, preventing
  validation of stale allocation or closure during unfinished distribution.
- Lock a basket before its state-changing operations. Product first-reference writes and catalogue
  protected edits/deletion share the Catalog product-row lock; check eligibility again under that lock.
- Multi-stock operations lock product ids ascending before availability checks and wallet writes.
  A sale/adjustment cannot use a stale read of available stock or bypass the stock-mode guard.
- Validation and cross-context corrections have one local transaction. Unique basket/correction sources
  protect replay in addition to domain-state guards. Collection events have no stock/wallet writes.
- Manager last-account protections retain their current locks. Finance needs no last-holder guard:
  Manager retains the Finance capabilities. No database-reset action is part of this documentation task.

## HTTP & pages

Retain existing member ordering, producer catalogue/cutoff, Manager cycle/reception, household/account,
return-item and inventory administration routes with their narrowed product-mode rules. Existing
manager-cycle pickup pages are replaced by Sales basket routes; do not retain a second completion
endpoint that still charges/sells on physical collection. There is no production compatibility surface.
All route middleware is backed by Application gate/policy checks against the explicit actor; member-owned ids are verified server-side.

| Route / area | Page/action and use cases |
| --- | --- |
| `/cycles/{cycle}/order` and its line mutations | Existing `Orders/Edit` and SetOrderLine; weekly only, references to base/related baskets; archived lines hidden |
| `/baskets`, `/baskets/{basket}` | `Member/Baskets/Index`, `Member/Baskets/Show`; member's own household, ordered-versus-actual content, validation/collection state and global wallet link |
| `PUT /baskets/{basket}/collection` | Own-household member action; validated basket only; `MarkBasketCollected`, no financial effect |
| `/stock` | T54 `Stock/Index`; member read-only stock catalogue, active products incl zero sold out, producer/unit/current price/quantity |
| `/finance/baskets`, `/finance/baskets/create`, `/finance/baskets/{basket}` | `Finance/Baskets/Index`, `Create`, `Show`; Manager or Finance; household selection, draft/validated/pending filters, immutable history |
| `POST /finance/baskets` | `CreateStockBasket`; takes only `household_id` (active household, no cycle) |
| `PUT {basket}/weekly-lines/{product}`, `POST/DELETE .../stock-lines` (delete by product), `POST/DELETE .../return-lines` (delete by return item) | Draft actual quantities and stock/return contents; archive removals |
| `POST .../validation`, `PUT .../collection`, `POST .../corrections` | `ValidateBasket`, `MarkBasketCollected`, `CorrectBasket`; distinct actions with distinct effects |
| `POST .../cancellation`, `POST .../collection-corrections` | Cancel a stock draft / justified staff correction of collection state |
| `/finance/households/{household}/wallet` and receipt/correction posts | The only household wallet route (T72; the `/manage/households/{household}/wallet` copy is removed). Reuse `Wallet/Statement` and confirmed top-up/correction forms, gated Finance/Manager; a Manager also gets `householdReturnHref` to the manager household page, Finance-only gets none |
| `/manage/producers/{producer}/products` | Manager catalogue page listing both modes and their allowed actions; entry from producer administration |
| `/manage/producers/{producer}/products/...` | Stock product creation/edit/deactivate/reactivate/unused-delete from producer context; Manager only |
| `/producer/products/...` | T54 index composes own weekly administration and read-only own stock with quantities; no stock-mode change by producer |
| `/manage/products/{product}/classification` | Manager-only unused-mode change; no producer reassignment |
| `/manage/stock/{product}/movements` and entries/adjustments | Existing inventory administration patterns; Catalog ids, Manager-only general inventory operations. The movements page adds the paged history (`?page=`, `ListStockMovements`) below the level and forms; sale and correction lines link to `/finance/baskets/{basket}` |
| `/finance/stock`, `/finance/stock/{product}/movements` | Manager or Finance; read-only stock list (`ListStock` with `includeInactive`: deactivated products are listed too, marked inactive, each with its History link) and the paged history, with basket links; no entry/adjustment forms (R28a, R36c) |
| `/producer/stock/{product}/movements` | Producer, own stock products only, linked from the stock section of `/producer/products`; the paged history without basket or household (R25b, R28a) |
| `POST /manage/cycles/{cycle}/producers/{producer}/send` | `SendProducerOrderManually` (wraps `SendProducerOrder` with `refuseEmpty`) from the producer management dialog of `Manage/Cycles/Index`; Manager only; the button shows when `canSendManually` (closed, participating, unsent and with lines to send; empty orders are sent by the scheduler, so the use case refuses them under the cycle lock with `ProducerOrderEmpty`) and confirms first because it emails the producer; the row shows a Sent status via `sent`; domain errors (still open, already sent, empty, no account) show in the dialog (R12a, S36) |
| `/manage/accounts/{account}/finance-role` | Grant/remove Finance on the existing account administration page, Manager only |

`RoleAreas`, shared Inertia `roles`/`roleAreas` and `useAppNavigation` expose a Finance area for Finance
and Manager accounts. Central capability checks are reusable by future Commissions; no commission
CRUD is delivered. Existing producer privacy and member household scoping remain.

Persistent pending-collection indication:
- Member Dashboard and basket list show their own validated pending baskets, including closed-cycle
  and cycleless purchases. Use pending/preparation labels accurately; draft is not ready for collection.
- Finance/Manager see all pending collections with household, validation date.
- Cycle list/detail retain a pending-count badge/link after weekly closure, counting weekly baskets only. Collection removes the corresponding pending item without reopening or recharging.
- Adapt feature 002's dashboard data source from pending pickup cycle ids to Sales basket summaries read through `ListMemberPendingCollections` (members) and `ListPendingCollections` (staff) (member
  `pendingPickups` lists basket id, validation date, weekly and late flags; Finance/Manager get the total); `?pending=1` on
  `/baskets` and `/finance/baskets` (with `cycle` for staff) lists them, and cycle list/detail link to that view through
  `SalesBaskets::pendingCollectionCounts` (the dashboard reads the Sales summaries, not that port); preserve unrelated dashboard cards. An overdue date alone never produces an abandoned/cancelled state.

Concrete reuse inspected before planning:
- `resources/js/components/StockProductFields.vue` supplies catalogue field presentation; retain it
  for producer/Manager forms with context-specific permissions and stock mode outside producer editing.
- `resources/js/components/orders/{OrderPageHeader,OrderProductRow}.vue` remain member-order patterns.
- `resources/js/components/pickup/{StockLines,ReturnLines,DeliveredForm,Adjustments}.vue` and
  `resources/js/pages/Manage/Pickups/{Index,Show}.vue` supply existing patterns to refactor into shared
  basket presentation. Replace `CompleteForm`'s combined charge/handover behavior with distinct controls.
- Existing `Wallet/Statement`, `Manage/Accounts/Index.vue`, `AppSidebar` and
  `resources/js/composables/useAppNavigation.ts` supply balance, role-grant and navigation patterns.
- New list/detail views are required because one household/cycle pickup cannot represent several
  independent baskets or staff validation followed by member self-collection. Reuse shared layouts,
  tokens and `components/ui/`; do not introduce another component library or redesign unrelated pages.

Keep `<Noun>Row` DTOs in App/Http and generate TypeScript types. Domain errors map to
Laravel `ValidationException` at the HTTP boundary, with translated messages under `errors.domain`
and the existing fallback to the exception message. Laravel handles JSON 422 responses through the central JSON-negotiation
rule and redirects form requests back with errors and safe old input; password fields must never be
flashed to the session. Domain exception classes remain framework-free. Authorization denials map to
403. Catalan copy/ca-ES presentation, 44 px targets, phone-first reflow and the shared visual manifest/report workflow remain mandatory.
Test totals and enabled actions as display-only logic; authorization and money/stock rules stay server-side.

The following accepted presentation sections retain their historical pilot inventories. The Sales
routes above define the new behavior/screen scope; the [accepted task amendment](tasks.md) maps it to T52–T63.

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

#### 2026-10-27 App-wide visual rollout

The user extended the modern visual direction to the whole application. All
operational, manager, account and authentication routes already use the shared
semantic palette. Replace the Laravel starter Welcome page with a Guixa landing
page using the same canvas, card, typography and action tokens. Align the shared
validation error, navigation progress indicator, document fallback background,
page title and browser favicon with those tokens and Guixa identity. Preserve
routes and authentication behavior. The existing `Button` serves the landing
page primary action; the new `WelcomePage` component keeps the live route and
visual fixture on identical markup. Check the guest and signed-in variants at
375 and 1280 px in both themes and the guest page at 1024 px with 200% root
text. Reuse the existing manager/shared-control manifest for action hierarchy.

#### 2026-11-11 Cooperative landing direction

For the public Welcome page, this supersedes the landing-specific styling in the
2026-10-27 rollout; the rest of that app-wide direction remains in force. The
user approved a lighter, content-first public landing for La Guixa. Keep a
near-white cream canvas, muted blue actions and icons, discreet ochre step
numbers, and a deep-blue contact area with cream text and button. Use a typographic approximation of the
`la guixa` wordmark because no vector original is available. The page has a
header with the cooperative link on desktop and member access at every
width; a compact mobile header with the wordmark and member access side by side
when space permits; a concise hero with a desktop-only line illustration of a
vegetable basket; three cooperative values; the
three-step weekly ordering process; a contact link to
`laguixamanresa@gmail.com`; Manresa; and a short footer. Do not add an address,
opening hours, textures, oversized dark panels or unrelated application-wide
branding changes. Keep mobile content in one column and the contact action in the
hero. Omit the process links from the header and hero; the cooperative link is
sufficient navigation. Preserve the process section. On desktop, place step numbers above their
labels; on mobile, beside them. The contact background spans the viewport while
its text aligns with the main content. Make controls at least 44 px tall, and preserve clear focus and contrast in both
themes. The `WelcomePage` remains a public composition surface and keeps the
existing server route and authenticated access destination. See the immutable
2026-11-11 Change history entry for the complete approved copy and visual source.

The subsequent 2026-11-11 user refinement gives the hero more of the first proposal's
character: a full-width muted-blue hero with cream heading/body, a cream primary
button, and a more prominent headline and desktop basket. The navigation remains
on the cream canvas in light appearance. This supersedes the cream-hero treatment
above only; the cooperative values and steps remain light and
spacious. The later contact refinement gives the contact area the hero's
deep-blue surface and cream text and button, while the small footer stays on
the cream canvas in light appearance. Preserve dark appearance contrast and
text-zoom reflow. In the subsequent
modern/rustic refinement, replace the provisional hand-coded basket with a
clean, proportionate illustration based on the second approved mockup. Add
screenprinted ink wear matching the explicitly selected original rustic mockup
only to the blue hero: irregular cream paper chips and weathered edges. Keep
solid blue behind copy/actions to preserve contrast under the stronger texture;
retain the new basket and clean lower sections.

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
  For T46 only, an existing off-grid utility may remain when a measured and inspected comparison
  finds that replacing it does not improve the current settings or authentication UI. Each retained
  file/class pair is documented in the T46 visual check and guarded by an exact allowlist in the
  static check; other utilities remain subject to the grid.
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
   screen inventories in [tasks.md](tasks/baseline.md#existing-screen-inventory). Each slice adds a pattern
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

## Reference style refresh

**Status: Accepted (2026-10-27).** Route: plan only. The user approved the
[reference-style proposal](../../docs/visual-design/2026-10-27-reference-style-proposal.md)
and its light/dark concept preview on 2026-10-27. This changes presentation, not any `R#`, `S#`,
route, permission, Inertia prop, validation rule or domain behavior. T47–T51 deliver it in slices.

Use the shared semantic palette in `public/theme-colors.css`: a warm near-white canvas, white cards,
deep green brand action and navigation, and neutral charcoal dark mode. The four supplied Common Ground
screens are references for hierarchy and surface treatment; their brand, data, labels and gold button
do not become Guixa content. Preserve the current role-aware destinations, appearance control,
account menu, mobile navigation, skip link, translations, status meanings and 44 px targets. Reuse the
existing page/panel/form/list/action patterns and components before adding another variant. Any new
visible copy uses `lang/`.

The first production pilot (T47) is the shared shell on the manager Cycles and Producers indexes.
The manager layout slice T45 is merged; the dashboard and settings layout slices T44 and T46 are
still separate at this amendment, so T47 avoids their page files. T47 may style only its opted-in
routes; it does not silently restyle every signed-in page. After reviewing the merged pilot, the user
requested the broader dashboard, member, producer, manager, settings, authentication and Welcome
rollout together. The [all-page inventory](../../docs/visual-design/2026-10-27-reference-style-rollout.md)
records the combined route scope and shared components. Reconcile T44/T46 if their layout work lands
before the combined rollout merges.

For each slice, record an exact route/state inventory and reused component paths before editing.
Use the shared visual runner with a committed fixture and manifest, including 375 and 1280 px in
light/dark for each affected route and 1024 px at 200% text for distinct layout patterns. Check
empty, invalid, disabled, long-content and open-dialog states where applicable; verify contrast,
keyboard focus, touch size, no clipping and reduced motion. Keep generated captures outside tracked
source, except the approved concept preview attached to the proposal.

**Integration reconciliation (2026-10-29):** T47 is integrated and its pilot-continuation
approval is recorded. Combined T48–T51 implementation is integrated through PR #240;
T44/#243 and T46/#242 are also integrated and their accepted layout work is retained.
The [evidence reconciliation](../../docs/visual-checks/2026-10-29-t47-t51-reconciliation.md)
records verified historical reports and the remaining producer/manager role and authenticated
Welcome coverage gates. Backlog on those new tickets does not mean the rollout must be rebuilt.
This annotation updates delivery evidence only; current spec 001 and T52–T63 retain ownership
of superseding product behavior.

## Llenguatge visual de la portada a tota l'aplicació — proposta de pla

**Estat: pla acceptat (2026-11-11); desglossament T84 acceptat (2026-11-11). Ruta: plan-only, 001.** La portada integrada a
PR #385 és la referència visual real. Aquesta esmena defineix com traslladar-ne el llenguatge a l'aplicació
sencera; no canvia encara codi, tiquets ni el comportament. L’usuari n’ha aprovat la direcció i ha demanat
el desglossament començant pel pilot d’Estoc de gestió. Substitueix la direcció cromàtica i el tractament
de marca de «Reference style refresh» (verd de Common Ground) per a la futura implementació; el codi
integrat continua sent la base fins que s’executi cada lliurament autoritzat. Es conserven els contractes funcionals i
de revisió: rutes, rols, permisos, props d'Inertia, validacions, traduccions, estats, navegació accessible i
les obligacions de les especificacions 003 i 004. El desplegament T47–T51 ja integrat és el punt de partida
tècnic; no es repeteix com si fos feina pendent. La proposta 002 de dashboard està retirada i no és una
dependència.

### Referència i regles de trasllat

La font visual és `resources/js/components/WelcomePage.vue` i les seves peces, amb el tractament real de PR
#385; no els esbossos anteriors. En mode clar: paper `#faf1dc`, tinta `#2c2c2b`, blau principal `#286585`
(hover `#1d526f`), blau suau `#e7f0f3`, ocre `#a05a10`, contorn `#d8d7d1` i focus `#a85f12`. En mode fosc:
fons `#1c201f`, tinta `#f2f0e9`, blau principal `#8fc5df` (hover `#b2d9e8`), blau suau `#27363d`, ocre
`#e5a650`, contorn `#4f5a5d` i focus `#f1bd6e`. En aparença fosca, el bloc principal de portada combina blau
fosc `#23485c` i crema `#faf1dc`. Aquests valors fixen la direcció; cada parella de superfície, text, acció,
estat i focus s'ha de verificar en el seu context. El càlcul sobre colors font dóna 5,68:1 per crema `#faf1dc`
sobre blau `#286585`, 12,43:1 per tinta sobre crema i 8,79:1 per tinta `#1c201f` sobre blau `#8fc5df`. No és
evidència de contrast en execució. Els contorns de portada només donen 1,28:1 en clar i 2,31:1 en fosc contra
els fons respectius: són decoratius; els camps i altres límits essencials requereixen un token propi d'almenys
3:1.

Tipografia: `Nunito Variable` per al cos i els títols, `JetBrains Mono` només per a codi o identificadors
tècnics. La portada usa un títol expressiu (`clamp(2.5rem, 6.5vw, 4.25rem)`, pes 900); les pantalles
operatives conserven una jerarquia compacta, amb títols, subtítols, xifres i etiquetes fàcils d'escanejar.
Conservar l'alineació i el format tabular de quantitats, imports i dates. Les CTA de portada tenen radi
`.4rem`; les superfícies de l'aplicació poden mantenir `.625rem` si la jerarquia i la consistència ho
justifiquen. No cal copiar la geometria del hero a formularis, taules o diàlegs.

La textura de PR #385 només apareix com a desgast discret a les franges superior i inferior de 48 px en mòbil.
Mai se situa darrere de text, controls, dades o accions. El tractament `pigment-wash` del cistell SVG és propi
de la il·lustració: no s'aplica com a filtre global ni es repeteix en icones o superfícies de treball. Els
elements comuns han de transmetre la mateixa calidesa amb color, tipografia, espai i contorns, sense
perjudicar la densitat d'informació.

### Inventari i base compartida

Abans d'editar cap pantalla, inventariar les rutes realment servides i les seves variants per rol, els estats
visibles i els components compartits; contrastar-ho amb els manifests històrics, que no acredita per si sol la
cobertura actual. Incloure autenticació, dashboard, comandes, catàleg, productors, estoc, finances, recepció,
recollida, configuració, compte, Welcome i estats buits, errors i overlays. Documentar per a cada família la
pàgina, el contenidor, els patrons reutilitzables i la mostra de dades de revisió. Els canvis en rutes noves o
moviments de components exigeixen actualitzar aquest inventari abans de retirar cap límit de desplegament.

La paleta d'execució viu a `public/theme-colors.css`, carregat després de `@vite` per
`resources/views/app.blade.php`; el verd `#246b57` és encara el valor principal actual. Definir-hi tokens
semàntics de superfície, tinta, acció i text sobre acció, hover, focus, camp, contorn, estats i gràfics per
als dos modes. El blau fosc/clar necessita parelles `primary`/`primary-foreground` diferents segons el mode;
comprovar-les en botons, enllaços i navegació. Reconciliar `resources/css/app.css` amb aquests tokens,
mantenint-hi la geometria i els patrons `.pilot-*` compartits. Evitar valors dispersos per pàgina i preservar
colors semàntics de perill, èxit i avís quan el seu significat requereix distingir-los de la marca.

`resources/js/layouts/AppLayout.vue` i `resources/js/lib/pilotTheme.ts` delimiten avui l'opt-in del tema.
Revisar aquesta llista contra l'inventari de rutes abans d'ampliar-la o eliminar-la. `usePilotTheme` dóna
context als portals: revisar conjuntament `ui/dialog`, `ui/sheet`, `ui/dropdown-menu`, `ui/tooltip`,
`ui/sonner` i `ui/select/SelectContent.vue`, que actualment no consumeix aquest context. El criteri és que un
overlay hereti el tema, el focus i els colors de la pàgina que l'obre, també quan es renderitza fora del seu
arbre DOM. Reutilitzar `AppSidebarLayout.vue`, `AppSidebarHeader.vue`, `AppSidebar.vue`, `Breadcrumbs.vue`,
`NavMain.vue` i els patrons de `components/ui/`; conservar breadcrumb a la capçalera, H1 en el contingut, skip
link, control d'aparença i navegació mòbil.

### Mapa de superfícies i components

Inventari de famílies observades a `resources/js/pages/` sobre el commit `cfda8e70`; els noms següents són
camins de pàgina, no noves rutes. Cada lliurament concretarà les variants de rol/estat contra les rutes
vigents.

| Família | Pàgines existents i abast |
| --- | --- |
| Públic i accés | `Welcome.vue`, `auth/{Login,AcceptInvitation,ConfirmPassword,ForgotPassword,ResetPassword,TwoFactorChallenge,VerifyEmail}.vue`; conservar destinació segons sessió |
| Membre | `Orders/{Index,Edit}.vue`, `Member/Baskets/{Index,Show}.vue`, `Wallet/Statement.vue`, `Stock/Index.vue` |
| Productor | `Producer/Products/{Index,Create,Edit}.vue`, `Producer/{Cutoff,CycleSummary}.vue`, `Producer/Stock/Movements.vue` |
| Catàleg | `Catalogue/Producers/{Index,Products,Availability}.vue`, `Catalogue/Producers/WeeklyProduct/{Create,Edit}.vue`, `Catalogue/Stock/Movements.vue`; comprovar també pantalles compartides servides pels controladors de catàleg |
| Administració | `Manage/Accounts/Index.vue`, `Manage/Roles/Index.vue`, `Manage/Households/{Index,Show}.vue`, `Manage/Producers/Index.vue`, `Manage/Cycles/{Index,Show}.vue`, `Manage/Settings.vue` |
| Estoc i envasos | `Manage/Stock/{Index,Create,Edit,Movements}.vue`, `Manage/ReturnItems/{Index,Create,Edit}.vue`; conservar diàlegs interns |
| Finances i recepció | `Finance/Baskets/{Index,Create,Show}.vue`, `Finance/Stock/{Index,Movements}.vue`, `Receptions/{Index,Show}.vue`; provar finances, recepció i administració segons permisos actuals |
| Compartit | `Dashboard.vue`, `settings/{Profile,Appearance,Security}.vue`; dashboard segons la versió integrada de 004, sense avançar funcionalitat pendent |

| Patró | Reutilització i canvi proposat |
| --- | --- |
| Navegació i marca | `components/{AppSidebar,AppSidebarHeader,NavMain,Breadcrumbs}.vue`: blau sòlid/crema, selecció amb indicador i text; adaptar el tractament tipogràfic del nom existent, conservar la icona compacta en col·lapsar. Sense textura sota enllaços |
| Taules i llistes | `resources/css/app.css`: `.pilot-data-table`, `.pilot-list`, `.pilot-amount` i container queries existents; superfícies planes, separadors llegibles, xifres tabulars, mateixa informació en reflow mòbil |
| Formularis | `components/FormSelect.vue`, `components/ui/input/Input.vue`, `components/InputError.vue`: camps amb superfície neta, contorn propi, etiqueta persistent i error associat; no substituir la validació existent |
| Botons | `components/ui/button/index.ts` i `.manager-button` a CSS: principal blau, secundari neutre/blau suau, perill vermell. Preservar busy/disabled, nom accessible i jerarquia |
| Diàlegs | `components/manager/NativeDialog.vue`, `components/ui/dialog/DialogContent.vue` i `pages/Manage/Stock/StockEditDialog.vue`: mateixa superfície i focus; conservar Escape, atrapament i retorn del focus |
| Missatges i estats | `components/{StatusIndicator,AlertError,InputError}.vue`, `components/ui/sonner/Sonner.vue`: semàntica independent del blau i l'ocre; text/icona a més del color |
| Decoració | `components/welcome/LandingHero.vue`, `resources/images/welcome/cooperative-basket.svg`: aquarel·la només en il·lustracions de capçalera o estats buits apropiats, petita i prescindible; vores orgàniques als extrems decoratius, geometria regular als controls |

Els camins abreujats de components i pàgines parteixen de `resources/js/`. No afegir el cistell a cada
pantalla: una taula, un error o una confirmació destructiva no necessiten il·lustració. No convertir l'ocre en
avís ni els colors de les verdures en estats de negoci.

L’aprovació literal consta a [l’entrada d’acceptació](history/2026-11-11T16-58-01.938Z-no-task-af456e57-d88a-4d3a-b411-52e529785370.md).
El [desglossament del pilot](tasks/landing-style-pilot.md) ha estat acceptat per separat; ni aquest pla
ni la preparació de tasques autoritzen implementar o publicar tiquets.

### Inventari de desplegament — esborrany (2026-11-13)

**Estat: esborrany pendent d'acceptació; no autoritza implementació.** Fotografia sobre `origin/main`
(`0074d383`; substitueix la fotografia `cfda8e70` del mapa anterior) d'allò que cada fase de la seqüència següent ha de cobrir. Fa servir el mateix inventari que
exigeix «Inventari i base compartida»: pàgines servides, components compartits i superfícies fora d'Inertia.

**Punt de partida (actualitzat 2026-11-13).** Quan es va redactar aquest inventari, el tema de la landing
(blau/crema) només era a `Welcome`. T84 està integrada (PR #408, aprovació visual a PR #409): la variant
`landing-stock` s'admet per coincidència exacta a `Manage/Stock/Index` (`lib/pilotTheme.ts`). La resta de
l'inventari està pendent. `isPilotThemePage` continua sent l'opt-in del tema verd anterior i **no** marca res
com a migrat a la landing.

**Pàgines (52 rutes, 56 fitxers a `resources/js/pages/`; la resta són components fills, vegeu «Fora del mapa»).**
«Tema verd» indica si la pàgina és a la llista de `pilotTheme.ts`; el que no hi és s'ha de revisar a la
fase de la seva família perquè el tancament de la fase 5 exigeix cap portal amb tema antic.

| Fase | Pàgines | Tema verd |
| --- | --- | --- |
| 1 (T84) | `Manage/Stock/Index` + `StockEditDialog`, shell i overlays | sí |
| 2 | `Orders/Index`, `Orders/Edit` | sí |
| 3 Catàleg | `Catalogue/Producers/{Index,Products}`, `WeeklyProduct/{Create,Edit}`, `Catalogue/Stock/Movements` | sí |
| 3 Catàleg | `Catalogue/Producers/Availability` | **no** |
| 3 Productor | `Producer/{Cutoff,CycleSummary}`, `Producer/Products/{Index,Create,Edit}`, `Producer/Stock/Movements` | sí |
| 4 Finances i recepció | `Finance/Baskets/{Index,Create,Show}`, `Finance/Stock/{Index,Movements}`, `Receptions/{Index,Show}` | sí |
| 4 Administració | `Manage/{Accounts/Index,Households/{Index,Show},Producers/Index,Cycles/{Index,Show},Settings}` | sí |
| 4 Administració | `Manage/Roles/Index` (conserva les decisions de 003) | **no** |
| 4 Estoc i envasos | `Manage/Stock/{Create,Edit,Movements}`, `Manage/ReturnItems/{Index,Create,Edit}` | sí |
| 4 Vistes de membre pendents | `Wallet/Statement`, `Stock/Index` | sí |
| 4 Vistes de membre pendents | `Member/Baskets/{Index,Show}` | **no** |
| 5 Accés | `auth/{Login,AcceptInvitation,ConfirmPassword,ForgotPassword,ResetPassword,TwoFactorChallenge,VerifyEmail}` | sí |
| 5 Preferències | `settings/{Profile,Appearance,Security}` | sí |
| 5 Dashboard | `Dashboard` (reconciliar amb 004) | sí |
| 5 Tancament | `Welcome` (ja és la referència; verificar transicions) | — |

Les quatre pàgines «no» ja porten classes `pilot-*` però no són a la llista: s'han de recollir a la fase
que toca, no es poden deduir del tema actual.

**Fora del mapa de famílies.** Superfícies que la taula del pla no nomena i que no es veuen en recórrer
només `pages/`:

| Superfície | Per què importa | Fase proposada |
| --- | --- | --- |
| `Manage/Households/SettlementDetails.vue` | Component fill de `Households/{Index,Show}`; sense classes `pilot-*` | 4 Administració |
| `Manage/Cycles/{CycleCreateDialog,CycleManageDialog}.vue` | Diàlegs de `Cycles/Index`; comprovar overlays | 4 Administració |
| `components/ui/select/SelectContent.vue` | El pla ja diu que no consumeix el context de tema | 1 (base compartida) |
| `resources/views/errors/minimal.blade.php` | Pàgina d'error de Laravel (403/404/500 sense Inertia): gris, tipografia del sistema, sense mode fosc ni marca; decidit 2026-11-13: només paleta i tipografia de la landing, sense il·lustració | 5 Tancament |
| `resources/views/mail/{invitation,producer-order}.blade.php` | Correus amb el tema per defecte de `x-mail::message`; sense tema propi | Fora d'abast: es manté l'estil per defecte de Laravel (decidit 2026-11-13) |
| `layouts/app/AppHeaderLayout`, `layouts/auth/{AuthCardLayout,AuthSplitLayout}` | Cap importació; no es renderitzen | Eliminar (decidit 2026-11-13), un cop confirmat que continuen sense consumidors |
| `layouts/settings/Layout.vue` | Ja previst a la fase 5, però cal comprovar-ne la navegació | 5 Preferències |
| `components/AppHeader.vue` | Només l'importa `AppHeaderLayout`; queda orfe en eliminar-lo | Eliminar amb el layout (fase 5) |
| `components/{TwoFactorSetupModal,TwoFactorRecoveryCodes,ManageTwoFactor,ManagePasskeys,PasskeyItem,PasskeyRegister,PasskeyVerify,DeleteUser}.vue` | Peces i diàlegs de `settings/Security` i `settings/Profile` | 5 Preferències |
| `components/{AppearanceMenu,NavUser,UserMenuContent}.vue` | Menús de compte i aparença del shell | 1 (base compartida) |
| `components/{AppShell,AppContent,AppLogo,AppLogoIcon}.vue`, `components/ui/sonner/Sonner.vue` | Xassís del shell, marca i avisos | 1 (base compartida) |
| `components/{orders,baskets,pickup,reception,roles,stock,manager}/*` | Components de pàgina: hereten la fase de les pàgines que els consumeixen; cada fitxa de fase els llista | Fase de la seva pàgina |
| `resources/views/app.blade.php`, `public/{favicon.svg,favicon.ico,apple-touch-icon.png}` | Fons de l'html abans de carregar el CSS (`--background`, `#fff`/`#1b1e1c`) i icones de pestanya; no hi ha `theme-color` ni manifest | 5 Tancament |

**Decisions pendents.** Cap. Correus, pàgina d'error i layouts sense ús: resolts, vegeu les entrades Q&A del
2026-11-13. Valors de color: verificat contra `WelcomePage.vue` a `origin/main` (`0074d383`); els valors del
pla (clar i fosc) coincideixen amb les variables `--welcome-*`. PR #398 només va reanomenar `hero` a `feature`
i va afegir colors propis del bloc de contacte (`#173e53`, `#15232a`, `#fff9eb`), que no són tokens
globals. Són la font vigent per definir els tokens de T84.

**Comprovacions que falten.** No s'ha obert cap pàgina al navegador; la llista prové del codi. Tampoc
s'ha revisat si les noves funcionalitats del dashboard (004) han afegit components fora de `Dashboard.vue`.

### Seqüència d'implementació futura

Aquestes són fases proposades, sense IDs de tasca ni estimació de dies. Cada família posterior es divideix en
lliuraments petits quan s'accepti el seu detall; no és una única PR de tota l'app.

1. **Pilot d'estoc i base compartida.** `Manage/Stock/Index.vue` + `StockEditDialog.vue`, shell i els seus overlays. Resultat demostrable: cercar un producte, obrir l'edició, llegir/modificar quantitat i tancar retornant el focus, amb la nova identitat. La taula d'escriptori, llista mòbil i formulari real redueixen més incertesa visual que començar per la landing o una pàgina simple. Sortida: mesures, regressió de les accions i aprovació visual del pilot.
2. **Comandes del membre.** `Orders/{Index,Edit}.vue`, `components/orders/{CycleCard,OrderPageHeader,OrderProductRow}.vue`. Depèn dels tokens i shell validats al pilot. Sortida: escollir cicle, editar quantitats i llegir totals amb la mateixa petició explícita de desament i la mateixa previsualització del servidor; incloure obert/tancat/cancel·lat, línia marcada i enllaç a cistella quan pertoqui.
3. **Catàleg i productor.** Primer `Catalogue/Producers/Products.vue` i formularis `WeeklyProduct/{Create,Edit}.vue`; després la família `Producer/` en lliuraments separats. Depèn de la jerarquia de camps/accions del pilot, no de canvis funcionals en comandes. Sortida per lliurament: mateix contingut i accions autoritzades, amb noms llargs, errors i controls desactivats llegibles.
4. **Finances, recepció i administració.** Lliuraments separats per cistelles/cartera, recepcions, cicles/llars i comptes/rols/configuració; incloure estoc/envasos i vistes de membre pendents. Depenen dels patrons aprovats que consumeixen. Sortida per família: revisar imports, totals, estats i accions sensibles sense canviar càlculs, permisos ni destinacions. La família de rols conserva les decisions de 003.
5. **Accés, preferències i tancament transversal.** Aplicar el sistema a `AuthLayout.vue`/`auth/AuthSimpleLayout.vue`, `settings/Layout.vue` i les seves pàgines; reconciliar `Dashboard.vue` amb 004. Aquestes famílies poden avançar després del pilot si no comparteixen fitxers en edició; només el tancament depèn de totes les anteriors. Verificar també Welcome i les transicions públic→accés→app. Sortida final: inventari complet sense rutes/portals amb tema antic, preferència clar/fosc/sistema preservada i revisió de totes les famílies.

**Contenció del pilot:** no canviar directament els mappings globals de `:root` de `public/theme-colors.css` a
la fase 1: restilarien totes les pantalles. Definir una sola família de valors de marca i aplicar-ne el
mapping semàntic només al pilot mitjançant una variant explícita del context de tema existent; `.pilot-theme`
ja cobreix moltes pàgines i no identifica per si sola aquest nou pilot. Estendre `AppLayout.vue`,
`lib/pilotTheme.ts` i `usePilotTheme.ts` per transmetre aquesta variant també als portals; mantenir el
comportament del flag actual durant la migració. És una extensió del sistema existent, sense duplicar paletes
per pantalla. El mapping global només es promou quan tots els consumidors s'han revisat; després es retira la
frontera temporal amb proves de navegació. La landing consumirà els mateixos valors de marca conservant els
seus rols hero/paper i la seva composició. Durant el pilot ha de mantenir el resultat integrat de #385.

Cada fase futura comença amb una fitxa de rutes/estats/components i acaba amb una revisió de patrons i
evidència reproduïble. No crear una nova pantalla ni un nou component si un patró existent cobreix la
necessitat. No modificar dades de negoci, regles d'autorització, contractes de rutes, props, validacions ni
còpia traduïda fora de correccions estrictament visuals.

### Criteris d'acceptació i evidència

Per cada ruta afectada i cada rol que en canvia el contingut, comprovar 375 i 1280 px en clar i fosc; per cada
patró de composició diferent, 1024 px amb text al 200% en tots dos modes. Incloure estats plens, buits,
llargs, invàlids i desactivats, així com menú, selector, diàleg, full i toast oberts quan correspongui.
Mesurar primer `getComputedStyle` i `getBoundingClientRect` o `measureLayout`/`measureFocus`, llegir
estructura i text, i executar el visual runner en mode de mesures. Una previsualització petita es reserva per
una direcció nova, una fallada inexplicada o una petició explícita. Verificar contrast mínim 4,5:1 en text
normal i 3:1 en contorns significatius i focus, text gran d’almenys 3:1, objectius tàctils de 44 × 44 px,
ordre de tabulació, focus visible, Escape i retorn del focus en overlays, absència de retall i desbordament,
preferència de sistema, moviment reduït i font de reserva. Registrar els valors mesurats i les excepcions
corregides abans de donar una família per acabada.

La validació funcional futura usa les proves de Vue/PHP existents per confirmar que els fluxos, rols i
permisos continuen iguals; afegir només comprovacions necessàries per riscos nous del tema o dels portals.
Aquesta esmena documental es valida ara per enllaços, identificadors, coherència amb el pla i registre
d'història; no exigeix proves d'aplicació. `database/seeders/DemoSeeder.php` no necessita cap canvi per una
proposta de pla; ja conté actors de tots els rols. En una implementació futura, revisar-lo i ampliar
`tests/Feature/Database/DemoSeederTest.php` només si calen dades o estats visibles nous per mostrar el
resultat.

**Preparació:** el comportament és fora d'abast i les obligacions 003/004 continuen vigents; la direcció
visual i les fases d’aquest pla estan acceptades; el desglossament es presenta per separat. Els camins de referència i els patrons pilots
s'han comprovat en el codi actual; l'inventari complet de rutes, la validació en execució i l'aprovació visual
de cada fase corresponen a la implementació posterior. No hi ha cap dependència externa pendent per decidir
sobre aquest pla.

### Riscos i proves de regressió

- **Propagació global accidental:** els tokens públics, `.reference-style`, estils natius i portals tenen abasts diferents. Mesurar una ruta pilot i una encara no migrada després d’una navegació Inertia, amb menú mòbil obert; conservar una sortida reversible per variant fins al tancament.
- **Pèrdua de densitat o clipping:** no copiar els espais ni el títol gegant del hero. Conservar les container queries, seccions i imports; comprovar text al 200% també a 375 px en el formulari/diàleg més arriscat i a 1024 px en cada patró. Cap scroll horitzontal de pàgina, dades ocultes o control fora d’abast.
- **Colors funcionals amb contrast insuficient:** comprovar hover, focus, invalid i disabled de cada parella; vores decoratives i filtres SVG no constitueixen una prova de contrast. Els camps mantenen contorn essencial ≥3:1 i errors recognoscibles sense color.
- **Canvis de negoci accidentals:** retenir proves de `pages/Manage/Stock/Index.spec.ts`, `pages/Manage/Stock/Edit.spec.ts`, `components/manager/NativeDialog.spec.ts`, `pages/Orders/{Index,Edit}.spec.ts`, `composables/useAppNavigation.spec.ts`, `composables/usePilotTheme.spec.ts` i `lib/pilotTheme.spec.ts`; seleccionar les de cada família i les proves HTTP d’autorització afectades, sense donar per cobertes rutes noves pels tests de Welcome.
- **Marca i rendiment:** Nunito local i fallback, espai reservat a la il·lustració; no repetir filtres d’aquarel·la en files. Confirmar menús amb fletxes/Enter i formularis amb teclat, no només el seu estat tancat. Les fixtures amb transport simulat no acrediten persistència ni permisos.

A cada lliurament: manifest de casos lligat al commit/base, dades deterministes, mesures de
font/color/mida/overflow i interaccions; comprovacions locals enfocades, tipus i format segons el diff, i CI
del projecte. L’aprovació estètica del nou pilot és un punt explícit abans d’estendre la direcció; les mesures
no la substitueixen. En aquesta sessió no s’han executat navegació, proves d’aplicació ni auditoria
d’accessibilitat: s’ha preparat el pla i verificat fonts i contrast de parelles planes.

### Fonts i límits de la descoberta

Base inspeccionada: `origin/main` al commit `cfda8e70c4a5a0c56c5f8f52ca07c91905b0351f`, amb [PR #385
fusionada](https://github.com/dadraude/guixa/pull/385) el 2026-11-11. Historial combinat de 001, 003 i 004
llegit amb `history.mjs read`; les decisions acceptades de «Reference style refresh» i les posteriors
refinacions de la landing es conserven com a historial; aquesta aprovació substitueix només la direcció visual descrita.

Descoberta Verify: projecte de graf `guixa`, generació `2026-11-01T14:55:31Z`, índex fast anterior a la base
actual. Les cerques, traça de `useAppNavigation` en les dues direccions i controls `check_index_coverage`
mostren fonts canviades, pàgines noves no registrades, CSS parcial i exclusions de `public/`, fixtures i
proves. Per això s’han contrastat els fitxers actuals directament, incloent paleta, layouts, navegació,
directoris de pàgines, portals, demo i rangs CSS afectats. No s’infereix absència de consumidors de la traça
ni cobertura funcional completa del graf.

S’ha aplicat `ui-ux-pro-max` amb cerca de sistema visual i una reformulació per a gestió densa, més consulta
Vue. Les paletes/fonts genèriques retornades no són adequades com a referència de marca i no s’han adoptat;
només se’n retenen criteris de densitat i accessibilitat compatibles amb el projecte. S’ha consultat
`vue-best-practices` per mantenir les fronteres dels components existents.

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
- Emails (invitations, sent producer orders): subject and body through `__()`. The sent producer
  order body uses a Laravel Blade view with escaped heading, product names and units; its mailable
  formats quantity strings for display while retaining the original totals for delivery assertions.
- Dates, numbers and money: Vue formats with `Intl` and `ca-ES`; PHP uses the same locale where it formats (emails).
- Starter-kit and shadcn-vue components' own texts (e.g. screen-reader labels) go through `trans()` too.
- Every key used in PHP and Vue has a `ca` translation; a test fails otherwise (Testing).
- T36 covers all applicable static user text, including originally Catalan literals, accessible copy,
  status and validation messages, framework error pages and notification mail. English source keys and
  Laravel `:parameter` substitution keep each whole message reorderable. A source-aware Vue scan
  checks static template text, visible attributes and display expressions; bounded exclusions cover
  proper names, user data and machine values. The four framework message files are pinned copies of
  Laravel-Lang Catalan translations with their upstream license in `lang/ca/`.

## Testing

| Level | What | Where |
| --- | --- | --- |
| Domain | Value objects, `LocalTime`, `CutoffRule`, `ProportionalAllocator` (property: Σ result == target), `Cycle`, `Order`, `Product`, `Basket` | `tests/Unit/<Context>` |
| Application | **Every scenario S1–S92**, with in-memory repositories and `FrozenClock` | `tests/Application/<Context>` |
| Infrastructure | Each Eloquent repository against `guixa_test` (constraints, locks, numeric mapping) | `tests/Integration` |
| HTTP | Role gates, wiring, `assertInertia` props, error → 422 and `AuthorizationException` → 403 mapping | `tests/Feature` |
| Architecture | A1–A17 (A9 through Oxlint); explicitly register Sales contracts as its slices land | `tests/Architecture` |
| Translations | Every key used in PHP (`__()`, `trans()`) and Vue (`trans()`, `$t`), and every domain error class, has a `ca` translation | `tests/Architecture` |
| Frontend | Display totals, A9 via Oxlint | `resources/js/**/*.spec.ts` |

The [current module registry](../../docs/architecture/modules.md) records the implemented pre-Sales
ownership and integration exports. A11/A12 check the manifest, module references and table access
without a database. T52–T63 update those declarations alongside the delivered slices and remove
superseded Ordering ownership at the planned cutover; this baseline does not implement that transition.

In-memory repositories live in `tests/Support/InMemory` and must pass the same contract tests
as the Eloquent ones, so the Application tests stay trustworthy. Each port has one
`<Noun>RepositoryContract` trait in `tests/Support/Contracts`; the in-memory test (PHPUnit `TestCase`) and the
Eloquent test (`Tests\TestCase` with `RefreshDatabase`) both use it and only implement `repository()`. In-memory repositories extend
`InMemoryRepository` (id-keyed store, `nextId()` returning UUID v7). `ProbeNoteRepository` shows the shape.

### Amendment verification

Use existing public Application seams with in-memory ports/repositories; add Sales tests for S78–S92
and update existing scenarios whose charge/collection boundary changed. Do not preserve obsolete
CompletePickup behavior behind a compatibility path. Contract-test both in-memory and Eloquent
history filtering, one active line, removed references, source uniqueness and sale/charge source ids.
Cross-context integration checks must prove rollback of a failed validation leaves basket, stock and
wallet unchanged, simultaneous baskets cannot oversell, stock sales take no cycle lock,
and classification/deletion cannot race first use.
Check redistribution rejection by validated baskets, late weekly baskets, replay, negative/zero totals, product deactivation,
late collection after cycle closure, Finance boundaries, own-household self-collection and manual receipts.
Rendered coverage includes draft/validated/collected, zero stock, empty, insufficient stock, long names,
closed-cycle pending indicators and narrow-role navigation at the shared workflow's required dimensions.
No screenshot is the first check: measure layout/styles/structure, then use the visual runner's report.
This document amendment itself has no executable behavior; validate links/identifiers/history/whitespace,
not application tests. Implementation and task creation require their own later scope.

## Decisions taken in this plan

- **D7** Draft basket stock is not reserved. Check at addition and again under product locks on
  validation; validation records stock sale and wallet charge atomically. Physical collection has no
  monetary or inventory effect. Lines belong to Sales Basket, not Ordering Order.
- **D9** *Will not be delivered* is derived on read until the producer order is sent, then stored on the
  line by `send`. Nothing has to react to catalog or participation changes before that (R3b, R6c).
- **D10** Producer orders that close with no line to send are sent, validated and distributed by a command
  the scheduler runs every minute (`SendEmptyProducerOrders`), which can settle the cycle through the same
  `CycleSettlement` step as `MarkProducerDistributed`. A cutoff passes
  without any request, so nothing else would notice it (R12a).
- **D11** The pickup weekday is a single-row `cooperative_settings` table, not configuration: R4c lets a
  manager change it. It is still one value per installation, as the constitution allows for settings a
  manager changes in the app. Its value is null until a manager sets it, directly or when opening a cycle as permanent (R4c).
- **D12** Reception changes before weekly closure never touch validated baskets (Change 2026-11-03).
  A late arrival (received 0 → >0) goes to the household's open weekly draft or one new late weekly
  basket; any other redistribution synchronizes drafts and is rejected when a validated basket contains
  the product. Validated baskets change only through individual Sales corrections (R23), which keep
  originals immutable and pair adjustments with Wallet corrections in one transaction.
- **D13** Producer deactivation is a Catalog use case that calls Membership (producer role) and Ordering
  (participation) through ports, in one transaction (R3a).
- **D14** Superseded destructive cancellation: retain cancelled orders/baskets and removed lines as
  history, excluding them from current contents/totals. Product use is derived from those references,
  receptions and stock movements; no duplicated product usage marker or separate usage ledger.
- **D15** Invitations are their own `Invitation` record with a 7-day expiry, not Laravel's password broker; a new
  token replaces the previous one, so resending or changing the email invalidates the old link (R34). The broker's
  expiry is shared with password resets and it reads the real clock, so S25's expiry could not be tested with
  `FrozenClock` at the Application level.
- **D16** Ordering owns ordered/allocated quantities; Sales owns actual quantities and validated
  sale snapshots. Reception reads effective actuals through a port to flag differences, while
  distribution checks allocations. Draft reallocation replaces affected actual overrides; validated
  adjustments retain the immutable original and separately rounded amount differences.
- **D17** The first manager is created by an Artisan command calling `CreateFirstManager`, which sends the
  usual invitation and is rejected once any manager exists (R36a: "outside the app").
- **D18** Translations use `laravel-vue-i18n`, so PHP and Vue share the files in `lang/` (constitution, UI), with
  English source strings as keys: code stays in English as the constitution requires, and a missing Catalan
  translation shows English, which the translation test catches before merge.

- **D19** Catalog owns the single Product identity, immutable producer and exclusive stock flag.
  Stock owns inventory/returns, not another product catalogue. Stock product creation starts from a producer.
- **D20** Sales is a separate bounded context in the modular monolith. Weekly Order and Basket have
  distinct lifecycles; independent stock baskets require no cycle. A source order creates only one base basket.
- **D21** Finance is an explicit role now, granted/revoked by Manager; Manager retains every Finance
  capability. Central checks allow later configurable Commissions without delivering that framework now.
- **D22** Source documents retain cancelled/removed references and preserve first-use evidence.
  Current/history repository interfaces have distinct filters; shared Catalog product locks prevent
  reclassification/deletion races with the first reference.
- **D23** There is no production data to migrate. Replace the fresh schema/model directly, without
  compatibility aliases, data backfills or database-reset actions in this documentation change.
- **D24** Sales validation and corrective money/stock operations use synchronous Application adapters
  within one local transaction, with stable lock order and unique source ids. Collection and confirmed
  receipts are independent operations; no eventual financial consistency or online payment integration.
- **D25** Weekly closure depends on distribution and base basket validation, not collection. Late
  collections retain pending visual indicators after closure. Supplementary baskets carry no cycle and never gate or reopen it;
  containers are a per-household balance.
- **D26** Stock movement history (R28a, T76) is computed on read from the immutable ledger: no stored
  balance column, no new table or index (`stock_movements (product_id, occurred_at)` already serves it).
  The producer view drops the basket in the use case, not in the page. One shared
  `resources/js/components/stock/MovementHistory.vue` renders the lines and previous/next page links
  for the three pages (Manager movements page, Finance stock history, producer stock history),
  following `Wallet/Statement.vue`'s phone-first table/stacked-row pattern and `components/ui/`
  (`badge` for the type, `button` links for paging). No pagination primitive exists in `components/ui/`,
  so paging is two links driven by Laravel's `LengthAwarePaginator` built in the controller from the
  use case's page and total. Type labels are translated under `stock.movement_types`.

Dropped: D1 (pickup is a sequence of saved steps, R19–R22). D2 and D3 became spec rules (R4a, R6). D4 was
replaced by R35 and R36b (unlinking keeps the account; deactivation is separate). D5, D6 and D8 became spec
rules (R18, R15 warning, R15 tie rule).
