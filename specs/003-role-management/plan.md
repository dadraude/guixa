# 003 — Role management · Plan

Status: Accepted (2026-11-08; PR #352 merged) · Spec: [spec.md](spec.md) (R1–R16, S1–S13, accepted
2026-11-08) · Rules: [constitution](../constitution.md) · Design reference: [001 plan](../001-weekly-orders/plan.md)

This plan changes who may call existing use cases, adds the role management screen and two areas, and
records role changes. It introduces no new bounded context: roles stay in `Membership`, capabilities in
`Shared` and the app's gate registration, and each touched use case keeps its context. Everything not named
here keeps the 001 plan's design.

## Readiness

| Concern | State | Evidence |
| --- | --- | --- |
| Behavior | Ready | Spec accepted 2026-11-08 (history `…22-12-24.478Z…`); R1–R16 and S1–S13. The 001 glossary and Roles note already point to 003 for Finance and Validated reception. |
| Decisions | Ready | OQ1–OQ6 resolved (Q&A `…22-04-10.753Z…`). No open questions. Commissions superseded (README roadmap). The technical choices below are D1–D9 of this plan. |
| Design | Ready once this plan is accepted | Gates, routes, DTOs and persistence below. UI reuses existing pages, `components/ui/` and the layout rules; the new screen is a list page built from the same patterns as `Manage/Accounts/Index.vue`. No new design direction. |
| External prerequisites | Not applicable | No new service, package or credential. |
| Paths | Ready | Existing paths checked on `origin/main` at `7162f52b`; new ones are marked *planned*. |
| Verification | Ready | Application tests per scenario, HTTP authorization matrix, repository contracts, rendered check at 375 px. |

## What exists today

Checked on `origin/main` (`7162f52b`):

- `Guixa\Shared\Domain\Role` has `Manager`, `Finance`, `Member` (derived) and `Producer`.
  `DatabaseActorResolver` reads `account_roles`; `Actor::hasAnyRole` takes role names.
- `app/Providers/AppServiceProvider.php` defines the gates `manage` (Manager), `manage-sales` and
  `manage-wallet` (Manager, Finance), `view-stock-offers`, `producer`, `role`, and the policies
  `household`, `producer` (`owns`) and `Product` (`ProductPolicy`).
- `ProductPolicy::update` allows only the owning producer on a weekly product; `delete` is Manager for stock,
  owner for weekly. So today **the manager cannot edit weekly products**, which R3 now requires.
- Every reception use case (`GetReception`, `AddUnorderedProduct`, `SetReceivedTotal`, `SetAllocation`,
  `ValidateReception`, `MarkProducerDistributed`, `ReopenProducerOrder`) authorizes `manage`; the routes live
  under `/manage/cycles/{cycle}/receptions/{producer}` (`role:manager`).
- `SetProductAvailability` and `GetProducerCycleSummary` authorize `owns` the producer only.
- Return-item CRUD, `ListProducers`, `ListProductsOfProducer`, stock-product CRUD and
  `ChangeProductClassification` authorize `manage`. `ListStock` and `ListReturnItems` authorize `manage-sales`;
  `ListStockMovements` lets Manager/Finance see basket links and a producer its own lines without them.
- Roles are granted per row on `/manage/accounts` through `GrantManagerRole`, `RemoveManagerRole`,
  `GrantFinanceRole`, `RemoveFinanceRole` (`ManagerRoleController`, `FinanceRoleController`). Grants are
  idempotent; `RemoveManagerRole` locks managers and throws `LastManager`.
- `Account::isActiveManager()` checks the role and `deactivatedAt` only: a pending invitation counts, which
  R1 forbids.
- `Account::canLogIn()` already accepts any stored role, so a Catalogue- or Receiver-only account can log in
  without code changes once the roles exist (R2).
- `App\Http\RoleAreas` and `resources/js/composables/useAppNavigation.ts` build the role switch and sidebar.

## Shared: roles and capabilities

- **`Role`** gains `Catalogue = 'catalogue'` and `Receiver = 'receiver'`. A static `Role::staff()` returns
  `[Manager, Finance, Catalogue, Receiver]` in the screen's order (R9); `isStaff()` answers for one case (A17).
- **Capabilities** are semantic gates in `AppServiceProvider`, so use cases ask what the actor may do, not which
  role it holds (001 plan, *Shared*; D1). New and changed gates:

| Gate | Allowed roles | Used by |
| --- | --- | --- |
| `manage` | Manager | unchanged: cycles, households, accounts, producers' administration, stock entries/adjustments |
| `manage-roles` *(planned)* | Manager | grant, remove, list holders, search candidates (R1, R9–R11) |
| `administer-catalogue` *(planned)* | Manager, Catalogue | any producer's weekly and stock products, classification, per-cycle availability, return items (R3–R5) |
| `view-producers` *(planned)* | Manager, Catalogue | `ListProducers` read (R5) |
| `view-stock` *(planned)* | Manager, Finance, Catalogue | `ListStock`, `ListStockMovements` read-only (R4, 001 R28a) |
| `receive` *(planned)* | Manager, Finance, Receiver | `ListReceptionCycles`, `GetReception`, `AddUnorderedProduct`, `SetReceivedTotal`, `SetAllocation` (R6–R8) |
| `settle-reception` *(planned)* | Manager, Finance | `ValidateReception`, `MarkProducerDistributed`, `ReopenProducerOrder` (R6, R7) |
| `view-return-items` *(planned)* | Manager, Finance, Catalogue | `ListReturnItems` (Finance's basket picker, Catalogue's catalogue) |

- **`ProductPolicy`** becomes: `update` (weekly) = owning producer **or** `administer-catalogue`;
  `update` (stock) = `administer-catalogue`; `delete` follows `update` for both modes, with R1c's unused
  check unchanged. Policies keep receiving the explicit actor and product facts (001 plan).
- **`ProducerPolicy::owns`** is unchanged. `SetProductAvailability` authorizes `owns` **or**
  `administer-catalogue` (R3, 001 R3c).
- The policy is per actor, not per area. R3's "only through this role" (S5) is enforced by the areas: the
  Producer area's queries keep taking the actor's own `producerId` (001 R2), and only the Catàleg area routes
  take a `{producer}` parameter.

## Membership

### Domain

- `Account` replaces `grantManager`/`removeManager`/`grantFinance`/`removeFinance` with:
  - `grantRole(Role $role)`: rejects a non-staff role (`NotAStaffRole`), a deactivated account
    (`AccountDeactivated`, R2) and a role already held (`RoleAlreadyHeld`, R13). Pending and expired invitations
    are accepted (R1).
  - `removeRole(Role $role)`: rejects a non-staff role and a role not held (`RoleNotHeld`, R13). Works on a
    deactivated account (R2). The caller checks the last active manager.
  - `isActiveManager()` = Manager role **and** accepted invitation **and** not deactivated (R1). This also
    tightens 001 R36b's last-active-manager guard on deactivation: a pending manager no longer counts as the
    remaining one (D2).
- `CreateFirstManager` and producer role suspension/restoration keep their own methods; they are not staff
  grants (Producer is still given through its invitation, 001 R36).
- New errors in `Membership\Domain\Error` (translated under `errors.domain`): `NotAStaffRole`,
  `RoleAlreadyHeld`, `RoleNotHeld`. `LastManager` and `AccountDeactivated` are reused.
- New port **`RoleChangeLog`** *(planned)*: `record(accountId, Role, RoleChange::Granted|Removed, actorId,
  DateTimeImmutable)`. `RoleChange` is a backed enum (A17). Append-only; no read method is exposed (R2a).

### Use cases

| Use case | Actor | Covers |
| --- | --- | --- |
| `GrantStaffRole(actor, accountId, role)` *(planned)* | `manage-roles` | R1, R2, R2a, R10, R13, S1, S2 |
| `RemoveStaffRole(actor, accountId, role)` *(planned)* | `manage-roles` | R1, R2, R2a, R11, R13, S3, S9 |
| `ListStaffRoleHolders(actor)` *(planned)* | `manage-roles` | R9, R11, S2, S3 |
| `SearchStaffRoleCandidates(actor, role, query)` *(planned)* | `manage-roles` | R10, S1, S2 |

They replace `GrantManagerRole`, `RemoveManagerRole`, `GrantFinanceRole` and `RemoveFinanceRole`, which are
deleted with their controllers and routes (R12; D3).

- **Write path.** One transaction: `lockMembershipChanges()`, then `lockManagers()` when the role is Manager
  (as `RemoveManagerRole` does today), then re-read the account (`AccountNotFound`), apply
  `grantRole`/`removeRole`, check `isActiveManager() && ! hasOtherActiveManager()` before removing Manager
  (`LastManager`), save, and `RoleChangeLog::record` with the `Clock`'s instant. A rejected change records
  nothing. Re-reading under the lock is what turns a stale screen into R13's rejection (S9).
- **`hasOtherActiveManager`** adds `activated_at IS NOT NULL` (R1, S3).
- **`ListStaffRoleHolders`** returns four `StaffRoleGroupData` in `Role::staff()` order, each with
  `StaffRoleHolderData`: account id, name, email, `status` (`AccountStatus` enum: active, invitation pending,
  invitation expired, deactivated), `isSelf`, `canRemove`, and `removeBlockedReason` (the last active manager,
  R11). Expiry uses the `Invitation` rule and the `Clock` (001 D15). Ordering by name, then email, lives in the
  read repository (A16). `canRemove` is computed in the use case from the same rule the write path enforces,
  so the page only reads flags (A13).
- **`SearchStaffRoleCandidates`** returns at most 20 `StaffRoleCandidateData` (id, name, email, status) whose
  name or email contains the query, case-insensitive, that are not deactivated and do not hold the role,
  ordered by name then email (R10). An empty query returns nothing. Producer and household are not exposed (R9).
- **`ListAccounts`** keeps returning each account's roles, now including Catalogue and Receiver (R12).

### Infrastructure

- `DatabaseAccountRepository` maps the two new role values; `listStaffRoleHolders()` and
  `searchStaffRoleCandidates(role, query, limit)` *(planned)* are read methods joining `users`,
  `account_roles` and `invitations`.
- `DatabaseRoleChangeLog` *(planned)* inserts into `role_changes`.

## Catalog, Stock and Ordering: Catalogue powers

No new aggregates or rules: R3–R5 reuse the 001 rules under a wider gate.

- **Weekly products (R3, S4, S5).** `CreateProduct` authorizes `owns` **or** `administer-catalogue` for the
  given producer. `UpdateProduct`, `DeactivateProduct`, `ReactivateProduct` and `DeleteUnusedProduct` already
  ask `ProductPolicy`, so the policy change is enough. R1's unsettled-line guard and the last-write-wins
  behavior are unchanged; nothing notifies the producer.
- **Stock products and classification (R4).** `CreateStockProduct`, `UpdateStockProduct`,
  `DeactivateStockProduct`, `ReactivateStockProduct` and `ChangeProductClassification` move from `manage` to
  `administer-catalogue`. A producer's account with Catalogue reaches its own stock products through the
  Catàleg routes, which pass the `{producer}` explicitly (001 R25b keeps the Producer area read-only).
- **Stock read (R4).** `ListStock` and `ListStockMovements` authorize `view-stock`. In `ListStockMovements`,
  basket links are returned only to `manage-sales` (Manager, Finance); a Catalogue-only actor gets
  `basketId = null`, because the basket pages would refuse it (R14; D5). `RecordStockEntry`,
  `RecordStockAdjustment` and `SetStockAvailable` stay `manage` (001 R26, R27).
- **Producers (R5, S6, S13).** `ListProducers` authorizes `view-producers`; it already lists deactivated
  producers with their active flag. `ListProductsOfProducer` authorizes `administer-catalogue` and adds
  `canEdit`, `canChangeClassification` and `canDelete` per product (A13); the page-level create-stock flag is
  `canCreateStock` (`manage`) until T04. Producer creation,
  deactivation, reactivation and account invitation stay `manage`. A deactivated producer's products are
  administrable; ordering them is still refused by `GetOrderableProduct` (001 R3).
- **Return items (R5).** The four return-item commands move to `administer-catalogue`; `ListReturnItems`
  to `view-return-items`.
- **Per-cycle availability (R3, S13).** `SetProductAvailability` authorizes `owns` or `administer-catalogue`;
  its cycle share lock and sent guard are unchanged. Two narrow Ordering queries feed the Catàleg page
  without exposing cycle totals or administration (R14):
  - `ListProducerUnsentCycles(actor, producerId)` *(planned)*: cycles in which the producer participates and
    its order is not sent, pickup date ascending.
  - `GetProducerCycleAvailability(actor, cycleId, producerId)` *(planned)*: the producer's active weekly
    products with `available` and `canChange` (false once sent).
  Both authorize `owns` or `administer-catalogue`. The Producer area keeps `GetProducerCycleSummary`.

## Ordering: reception for Receiver and Finance

- **Authorization (R6, R7, S7, S8, S12).** `GetReception`, `AddUnorderedProduct`, `SetReceivedTotal` and
  `SetAllocation` authorize `receive`; `ValidateReception`, `MarkProducerDistributed` and
  `ReopenProducerOrder` authorize `settle-reception`. Every guard stays in the domain: a received-total change
  after validation clears it (001 R17a), a distributed reception is read-only, and after a reopen the late
  arrival and redistribution rules apply to any `receive` actor, including their effect on weekly basket
  drafts through `SalesBaskets` (001 R18, D12).
- **Allowed actions.** `ReceptionData` gains `canEdit` (`receive` and not distributed), `canValidate`,
  `canDistribute` and `canReopen` (`settle-reception` and the existing state guards), computed in
  `GetReception` (A13). `Manage/Reception/Show.vue` shows controls from these flags only.
- **Cycle list (R8).** `ListReceptionCycles(actor)` *(planned)*, `receive`: every cycle with at least one sent
  producer order, newest pickup date first, each with its sent producer orders and their reception state
  (sent, validated, distributed) as an enum. Closed and cancelled cycles are included. No cycle commands are
  reachable from it. Under 001 a cycle can only be cancelled before any send, so a cancelled cycle never
  appears in practice; the query does not exclude it (R8).

## Persistence (Postgres)

| Table | Change |
| --- | --- |
| `account_roles` | New allowed values `catalogue`, `receiver`. Add a CHECK constraint on `role` limited to `manager`, `finance`, `producer`, `catalogue`, `receiver` (D6). |
| `role_changes` *(planned)* | id (UUID v7), user_id → users, role, change (`granted`/`removed`), actor_id → users, occurred_at timestamptz. Append-only; index on user_id. Owner: Membership (A12). |

`architecture/modules.php` declares `role_changes` under Membership. No data backfill: existing Manager and
Finance holders keep their rows; earlier grants have no history (R2a starts with this feature).

## HTTP & pages

All route middleware is backed by the use-case gates above; a direct link outside an actor's roles is 403
(R14, S11).

### Role management screen (R9–R13)

| Route | Page/action |
| --- | --- |
| `GET /manage/roles` *(planned)* | `Manage/Roles/Index` *(planned)*, `role:manager`. Props: `groups` (from `ListStaffRoleHolders`); deferred `candidates` for the group and query in `?role=&q=`, loaded by an Inertia partial reload (`only: ['candidates']`) as the manager types, debounced. |
| `POST /manage/roles/{role}/holders` *(planned)* | `GrantStaffRole` with `account_id`; back to the screen. |
| `DELETE /manage/roles/{role}/holders/{account}` *(planned)* | `RemoveStaffRole` after the confirmation dialog; a manager removing its own Manager role is redirected to the dashboard (R11, S3). |

- `{role}` binds only staff values; anything else is 404.
- Domain errors (`RoleAlreadyHeld`, `RoleNotHeld`, `AccountDeactivated`, `LastManager`) map to a validation
  error on the group and redirect back, so the page reloads `groups` with the current holders (R13, S9).
- Each holder row shows name, email and a status badge (pending, expired, deactivated) as text, not colour
  alone. The remove button is disabled with its visible reason when `canRemove` is false. The confirmation for
  removing one's own Manager role carries the lost-access warning (R11).
- Components *(planned)*: `components/roles/RoleGroup.vue`, `RoleHolderRow.vue`, `RoleCandidateSearch.vue`,
  built from `components/ui/` (`card`, `badge`, `button`, `input`, `alert-dialog`) and the layout rules' panel,
  list-row and action-row patterns. No new primitive.

### Accounts page (R12, S10)

`Manage/Accounts/Index.vue` keeps the role badges (now with Catalogue and Receiver) and drops the grant/remove
controls; it links to `/manage/roles`. The `manager-role` and `finance-role` routes, `ManagerRoleController`
and `FinanceRoleController` are removed. Household, deactivation and invitation actions stay.

### Catàleg area (R3–R5, R14)

New `/catalogue` prefix *(planned)*, `role:manager,catalogue`. The manager reaches the same pages from its
*Catalog and stock* sidebar section, so there is one implementation of each page (D4).

| Route | Page/action |
| --- | --- |
| `GET /catalogue/producers` | `Catalogue/Producers/Index` *(planned)*: `ListProducers`, deactivated marked; no administration actions. |
| `GET /catalogue/producers/{producer}/products` | Moved from `/manage/producers/{producer}/products` (`Manage/Producers/Products.vue` → `Catalogue/Producers/Products.vue`): both modes with available quantity for stock, actions from `can` flags. |
| `…/products/weekly/create`, `POST …/products/weekly`, `…/weekly/{product}/edit`, `PUT`, deactivation, reactivation, unused delete | Weekly product CRUD for any producer, reusing the fields and submit button extracted from `Producer/Products/{Create,Edit}.vue` into `components/WeeklyProductFields.vue`; each page keeps its own Inertia `<Form>` (A9). |
| `…/products/stock/...` | Stock product CRUD moved from `/manage/producers/{producer}/products/...` (`StockProductController`). |
| `PUT /catalogue/products/{product}/classification` | Moved from `/manage/products/{product}/classification`. |
| `GET /catalogue/producers/{producer}/availability` and `PUT …/cycles/{cycle}/products/{product}/availability` | `Catalogue/Producers/Availability` *(planned)*: unsent cycles and per-product toggles (S13). |
| `GET /catalogue/stock/{product}/movements` | Read-only history with `components/stock/MovementHistory.vue` (001 D26); basket links only for Manager/Finance. |
| `/catalogue/return-items` and its commands | Moved from `/manage/return-items`. |

Manager-only inventory stays where it is: `/manage/stock` and `/manage/stock/{product}/movements` with entry,
adjustment and set-available forms. Producer administration stays on `/manage/producers`, which links each
producer to its Catàleg products page.

### Recepció area (R6–R8, R14)

New `/receptions` prefix *(planned)*, `role:manager,finance,receiver`, replacing
`/manage/cycles/{cycle}/receptions/{producer}` (D4):

| Route | Page/action |
| --- | --- |
| `GET /receptions` | `Receptions/Index` *(planned)*: `ListReceptionCycles`. |
| `GET /receptions/{cycle}/{producer}` | `Manage/Reception/Show.vue` moved to `Receptions/Show.vue`, controls from `can` flags. |
| `POST …/products`, `PUT …/products/{product}/received`, `PUT …/products/{product}/allocations/{household}` | Unchanged controllers, new prefix; `receive`. |
| `POST …/validate`, `POST`/`DELETE …/distribution` | Unchanged controllers, new prefix; `settle-reception`. |

The manager's cycle page links to `/receptions/{cycle}/{producer}`; the old GET path redirects there.

### Role switch, sidebar and dashboard (R14, R15)

- `RoleAreas` adds `catalogue` → *Catalogue* (`/catalogue/producers`) and `receiver` → *Reception*
  (`/receptions`), translated as *Catàleg* and *Recepció*.
- `useAppNavigation.navigationSectionsFor` adds a *Catalogue* section (Producers, Return items) for Catalogue
  without Manager, a *Reception* section for Receiver without Manager or Finance, a *Receptions* item in the
  Finance section, and a *Receptions* item in the manager's *Cooperative* section. Manager's *Catalog and stock*
  items point to the moved routes.
- `DashboardController` adds `areaShortcuts`: the Catalogue and Receiver entries of `RoleAreas::for($actor)`;
  `Dashboard.vue` renders them as links in the existing header area, no new card (R15).

## Translations (R16)

All new copy goes to `lang/ca.json` with English keys: role names, statuses, the screen's headings, search,
empty states, confirmations and the self-removal warning; `RoleAreas` labels; the three new domain errors in
`lang/ca/errors.php`. The translation coverage test (001 plan, *Translations*) catches a missing key.

## Testing

| Level | What | Where |
| --- | --- | --- |
| Domain | `Account::grantRole`/`removeRole` (staff only, deactivated, already/not held), `isActiveManager` with pending/expired/accepted, `Role::staff()` order | `tests/Unit/Membership` |
| Application | **S1–S13**, each at least one test with in-memory repositories and `FrozenClock`; S4/S5 in Catalog, S7/S8/S12 in Ordering, S13 across Catalog and Ordering | `tests/Application/<Context>` |
| Contracts | `RoleChangeLog`, the new account read methods (ordering, filters, status, limit) on in-memory and the Database repositories | `tests/Support/Contracts`, `tests/Integration` |
| Integration | Two concurrent `RemoveStaffRole(Manager)` on the last two accepted managers: exactly one succeeds (R1, R13) | `tests/Integration` |
| HTTP | Authorization matrix: each new or moved route against Manager, Finance, Catalogue, Receiver, Producer, member-only (S11); `assertInertia` props and `can` flags; old routes gone or redirecting | `tests/Feature` |
| Architecture | A11/A12 with `role_changes`; A13 for the new pages | `tests/Architecture` |
| Frontend | Candidate search debounce and disabled remove action rendering | `resources/js/**/*.spec.ts` |

Rendered check of `/manage/roles`, the Catàleg pages and `Receptions/Index` at 375 px, 1024 px/200% text and
1280 px, light and dark, by keyboard, 44 px targets, measured first (AGENTS.md, *Measure before screenshot*).

## Delivery outline

For `tasks.md`, after this plan is accepted; not a task breakdown:

1. **Tracer:** Catalogue role end to end through the role screen: `Role::Catalogue`, `GrantStaffRole`/
   `RemoveStaffRole`, `role_changes`, `/manage/roles` (S1–S3, S9) and the accounts page change (S10). Proves
   the role, write path, screen and switch.
2. Catàleg area: weekly products for any producer, manager included (S4, S5), then stock/classification/return
   items/read-only stock (S6), then availability and deactivated producers (S13).
3. Receiver role and Recepció area: gates, moved routes, `can` flags, `ListReceptionCycles` (S7, S8, S11, S12).
4. Dashboard shortcuts and navigation (R15), if not already absorbed by 2 and 3.

## Decisions taken in this plan

- **D1** Capabilities are semantic gates (`administer-catalogue`, `receive`, `settle-reception`, …), not role
  checks in use cases. Manager keeps every power by being listed in each gate; there is no global bypass
  (001 plan, *Shared*).
- **D2** "Active manager" means Manager role, accepted invitation and not deactivated everywhere: removal
  (R1) and the existing deactivation guard (001 R36b) share `isActiveManager` and `hasOtherActiveManager`.
  Two different meanings of "last manager" would let a deactivation leave only a pending manager.
- **D3** One pair of use cases (`GrantStaffRole`, `RemoveStaffRole`) parameterised by `Role` replaces four
  role-specific ones. Grants and removals stop being idempotent: a no-op is reported (R13).
- **D4** The Catàleg and Recepció areas are route prefixes shared with the manager (and Finance for
  receptions), replacing the manager-only paths, so each page has one implementation and its `can` flags
  decide controls. No production compatibility surface beyond a GET redirect for the old reception URL.
- **D5** A Catalogue-only actor's stock history omits basket links, because the basket pages are not part of
  its roles (R14). Finance and Manager keep them (001 R28a).
- **D6** `account_roles.role` gets a CHECK constraint now that the set of stored roles grows, so an unknown
  role cannot be written by a future bug.
- **D7** `role_changes` is a separate append-only Membership table written in the grant/remove transaction;
  `account_roles` stays the current-state table read by `ActorResolver`. Nothing reads `role_changes` yet (R2a).
- **D8** Candidate search is an Inertia partial reload of a deferred prop, not a JSON endpoint, keeping the
  constitution's no-separate-API rule.
- **D9** Catalogue's per-cycle availability uses two narrow Ordering queries instead of widening
  `GetProducerCycleSummary`, so Catalogue sees products and availability but no cycle totals (R14).
