# 003 — Role management · Tasks

Status: Proposed (2026-11-08), awaiting the user's OK. Implementation and Linear publication are separate
scopes. Source: [spec](spec.md) (R1–R16, S1–S13) · [plan](plan.md) (accepted 2026-11-08, D1–D9) ·
[constitution](../constitution.md).

IDs are feature-global for 003 and start at T01; they do not continue 001's sequence.

## Architecture overview

Roles are rows in `account_roles` read by `DatabaseActorResolver`; every use case authorizes through a gate
in `AppServiceProvider`. Today only Manager and Finance are staff roles, granted per row on `/manage/accounts`,
and catalogue and reception use cases are Manager-only (`manage`) or producer-only (`owns`). The plan adds two
`Role` values, semantic gates, a role management screen and two areas that reuse existing pages under shared
prefixes.

The first missing behavior is granting a new staff role through a screen built for it. T01 builds that path
end to end (enum, CHECK constraint, use case, change log, read queries, page). Later tasks widen it: removal
and the accounts cutover (T02), the Catàleg area (T03–T05) and the Recepció area (T06–T07). No flag is needed:
each slice is safe on its own, because a role without its area simply grants nothing yet.

## Execution contract

- Each task owns its domain, application, adapters, persistence, HTTP, UI, translations, tests and affected
  docs together. Start with a failing public Application test (constitution, *Always TDD*).
- Every scenario gets at least one Application test; HTTP tests cover the authorization matrix and
  `assertInertia` props, including `can` flags (A13).
- A task that changes modules or tables updates `architecture/modules.php` and
  [docs/architecture/modules.md](../../docs/architecture/modules.md) and runs `tests/Architecture`.
- Changed pages follow the layout rules and are measured at 375 px, 1024 px/200% text and 1280 px, light and
  dark, by keyboard, before any capture (AGENTS.md, *Measure before screenshot*). All new copy is in
  `lang/ca.json` / `lang/ca/errors.php` (R16).
- `After` lists technical prerequisites with their reason. Shared surfaces (`AppServiceProvider`,
  `routes/web.php`, `RoleAreas`, `useAppNavigation.ts`, `DashboardController`) are handled by scheduling, not
  by dependency edges.
- A mismatch with the plan found while coding is written back to `plan.md` or this file in the task PR and
  logged as a **Deviation**; a mismatch with `spec.md` goes to a stacked spec-sync PR.

## Canonical task registry

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T01 | <a id="t01"></a>[Grant staff roles from the role management screen](#t01-grant-staff-roles-from-the-role-management-screen) | R1 (grant), R2 (grant), R2a (grant), R9, R10, R13 (grant), R16 (screen); S1 (holder and search), S2 (listing and search) | — |
| T02 | <a id="t02"></a>[Remove staff roles and retire role toggles on the accounts page](#t02-remove-staff-roles-and-retire-role-toggles-on-the-accounts-page) | R1 (removal, last active manager), R2 (removal), R2a (removal), R11, R12, R13 (removal); S2 (removal), S3, S9, S10 | T01 |
| T03 | <a id="t03"></a>[Catàleg area with any producer's weekly products](#t03-catàleg-area-with-any-producers-weekly-products) | R2 (Catalogue login), R3 (product edits), R5 (producer list), R14 (Catàleg), R15 (Catalogue), R16; S1 (area), S4, S5 | T01 |
| T04 | <a id="t04"></a>[Catalogue administers stock products, classification and return items](#t04-catalogue-administers-stock-products-classification-and-return-items) | R4, R5 (return items, no producer administration), R14 (Catàleg refusals); S6 | T03 |
| T05 | <a id="t05"></a>[Catalogue sets per-cycle availability](#t05-catalogue-sets-per-cycle-availability) | R3 (availability), R5 (deactivated producers); S13 | T03 |
| T06 | <a id="t06"></a>[Recepció area: Receiver and Finance receive orders](#t06-recepció-area-receiver-and-finance-receive-orders) | R2 (Receiver login), R6 (receive, no settle), R7 (receive part), R8, R14 (Recepció), R15 (Receiver), R16; S7, S11 | T01 |
| T07 | <a id="t07"></a>[Finance validates, distributes and reopens receptions](#t07-finance-validates-distributes-and-reopens-receptions) | R6 (after validation and reopen), R7 (settle part); S8, S12 | T06 |

Frontier at proposal time: **T01**. After T01 merges: T02, T03 and T06 are ready and independent.

## Coverage

| Ref | Owner | Notes |
| --- | --- | --- |
| R1 | T01, T02 | T01 grants to active, pending and expired accounts; T02 owns "only accepted counts as active manager" and the last-manager rejection (D2). |
| R2 | T01, T02, T03, T06 | Login with no household: T03 (Catalogue), T06 (Receiver). No grant to deactivated: T01. Removal from deactivated: T02. Reactivation keeps roles: already 001 R36b; T02 adds a regression. |
| R2a | T01, T02 | Grant and removal respectively; nothing displays it. |
| R3 | T03, T05 | Product edits in T03; per-cycle availability in T05. |
| R4 | T04 | |
| R5 | T03, T04, T05 | Read-only producer list in T03; no producer administration and return items in T04; deactivated producers' products administrable in T05 (S13). |
| R6 | T06, T07 | T06: receive, no settle actions, validation cleared on change. T07: after Finance reopens. |
| R7 | T06, T07 | Finance's receive part in T06, settle part in T07. Manager keeps both throughout. |
| R8 | T06 | |
| R9, R10 | T01 | |
| R11, R12 | T02 | |
| R13 | T01, T02 | Grant-side and removal-side rejections. |
| R14 | T03, T04, T06 | Refusals for each area's routes in the task that adds them. |
| R15 | T03, T06 | One shortcut each. |
| R16 | every UI task | |
| S1 | T01, T03 | T01: holder listed, no longer offered. T03: logs in to *Catàleg*. |
| S2 | T01, T02 | T01: listing marks and search. T02: Carla can be removed. |
| S3, S9, S10 | T02 | |
| S4, S5 | T03 | |
| S6 | T04 | |
| S7, S11 | T06 | All three links in T06; the producer's products link is asserted on whichever route is current, so T06 does not wait for T03. |
| S8, S12 | T07 | |
| S13 | T05 | |

## Task details

### T01 Grant staff roles from the role management screen

- **Outcome.** A manager opens `/manage/roles`, sees the four staff groups with their holders, searches an
  account in a group and grants it the role. Catalogue and Receiver exist as roles but have no area yet.
- **Scope and path.** `Role::Catalogue`, `Role::Receiver`, `Role::staff()`, `isStaff()` in
  `src/Shared/Domain/Role.php`; migration adding the `account_roles.role` CHECK constraint (D6) and the
  `role_changes` table (D7). Membership: `Account::grantRole` with `NotAStaffRole`, `RoleAlreadyHeld`,
  `AccountDeactivated`; `RoleChangeLog` port, `EloquentRoleChangeLog` and in-memory double; `GrantStaffRole`;
  `ListStaffRoleHolders` (statuses, ordering; `canRemove` may be returned as false until T02);
  `SearchStaffRoleCandidates`; account repository read methods. Gate `manage-roles`. HTTP: `GET /manage/roles`
  with deferred `candidates` (D8), `POST /manage/roles/{role}/holders`. Page `Manage/Roles/Index.vue` with
  `components/roles/RoleGroup.vue`, `RoleHolderRow.vue`, `RoleCandidateSearch.vue` *(planned)*. Sidebar item
  *Roles* in the manager's *Administration* section.
- **Requirements.** R1 "Only a manager grants … A role can be granted only to an active account; an account
  whose invitation is pending or has expired can receive any role." R2 "while deactivated it cannot receive a
  new role." R2a. R9, R10, R13 for grants ("the role was already granted … the account was deactivated").
  Plan: *Membership*, *Persistence*, *Role management screen*.
- **Dependencies.** None.
- **Done when.**
  - Given Anna is active with no household and no role, when a manager searches "Anna" in Catalogue and grants
    it, then she is listed under Catalogue and no longer appears in that search, and one `role_changes` row
    records the grant, the manager and the instant (S1, R2a).
  - Given Bernat's invitation is pending and Carla is deactivated holding Receiver, when the manager opens the
    screen, then Carla is listed under Receiver marked deactivated; the Finance search offers Bernat marked
    pending and not Carla; an expired invitation is offered marked expired (S2, R10).
  - Given Anna already holds Catalogue (granted from another tab), when the grant is sent again, then it is
    rejected with the reason and the screen shows the current holders; nothing is recorded (R13).
  - Granting Producer or Member through the route is 404; a non-manager gets 403 on every route.
  - Groups in order Manager, Finance, Catalogue, Receiver; holders and candidates by name then email (contract
    tests on in-memory and Eloquent repositories).
- **First checkpoint.** Application test `GrantStaffRole` of Catalogue to an active account fails, then passes
  with the Eloquent repository writing `account_roles` and `role_changes`; inspect both rows.
- **Size and rollout.** Roughly 500–700 authored lines (medium confidence): one enum change, one migration, one
  use case, two queries, one page with three components. Kept whole because a grant without the screen is not
  observable. Safe intermediate: granted Catalogue/Receiver accounts can log in but have no area until T03/T06.
- **Limits.** No removal UI or use case (T02). Accounts page untouched (T02). No area, gate or dashboard change
  for the new roles.

### T02 Remove staff roles and retire role toggles on the accounts page

- **Outcome.** A manager removes any staff role from the screen with confirmation; the last active manager is
  protected; the accounts page only shows roles and links to the screen.
- **Scope and path.** `Account::removeRole` with `RoleNotHeld`; replace `grantManager`/`removeManager`/
  `grantFinance`/`removeFinance`. `isActiveManager()` requires `activatedAt`; `hasOtherActiveManager` adds
  `activated_at IS NOT NULL`, which also governs `DeactivateAccount` (D2). `RemoveStaffRole` with the
  membership lock, manager locks for Manager and `RoleChangeLog`. `ListStaffRoleHolders` returns real
  `canRemove`, `removeBlockedReason`, `isSelf`. HTTP `DELETE /manage/roles/{role}/holders/{account}`; own
  Manager removal redirects to the dashboard. Delete `GrantManagerRole`, `RemoveManagerRole`,
  `GrantFinanceRole`, `RemoveFinanceRole`, `ManagerRoleController`, `FinanceRoleController` and their routes
  and tests; `Manage/Accounts/Index.vue` keeps role badges (with Catalogue and Receiver), drops toggles, links
  to `/manage/roles`.
- **Requirements.** R1 "Only an account that has accepted its invitation counts as an active manager. Removing
  the Manager role from the last active manager is rejected." R2 "any of its roles can be removed" while
  deactivated. R2a, R11, R12, R13 for removal.
- **Dependencies.** T01: the screen, `ListStaffRoleHolders` and the change log this task extends.
- **Done when.**
  - Given Dolors is the only accepted active manager and Ernest holds Manager with his invitation pending, when
    she opens the screen, then her remove action is disabled with the reason; a forced request is rejected with
    `LastManager`. Given Ernest has accepted, when Dolors removes her own Manager role and confirms the warning,
    then she is redirected away and `/manage/roles` is 403 for her (S3).
  - Given only Dolors (accepted) and pending Ernest are managers, deactivating Dolors is rejected (D2).
  - Carla, deactivated, has Receiver removed (S2); reactivating an account keeps its remaining roles (R2).
  - Two managers remove Anna's Finance; the second gets the reason and current holders (S9). Concurrent removal
    of the last two accepted managers' Manager role: exactly one succeeds (integration test against Postgres).
  - Each removal records one `role_changes` row; a rejected one records none.
  - The accounts page shows Manager and Catalogue for an account with both, no grant/remove action, and a link
    to `/manage/roles`; the old role routes return 404 (S10).
- **First checkpoint.** Application test: S3's pending-manager case fails against today's `isActiveManager`,
  then passes.
- **Size and rollout.** Roughly 350–500 authored lines plus deletions reported separately. The accounts cutover
  belongs here because removing the old toggles is only safe once removal exists on the new screen.
- **Limits.** No change to invitations, household links or deactivation beyond the D2 guard.

### T03 Catàleg area with any producer's weekly products

- **Outcome.** A Catalogue account logs in to *Catàleg*, sees the producer list and edits any producer's weekly
  products; the manager gets the same powers through the same pages.
- **Scope and path.** Gates `administer-catalogue`, `view-producers`; `ProductPolicy` (`update`/`delete` weekly:
  owner or `administer-catalogue`); `CreateProduct` authorizes owner or `administer-catalogue`; `ListProducers`
  on `view-producers`; `ListProductsOfProducer` on `administer-catalogue` with per-product `can` flags.
  Routes under `/catalogue` (`role:manager,catalogue`): producers index, producer products page moved from
  `/manage/producers/{producer}/products` (`ProducerProductController`, page moved to
  `Catalogue/Producers/Products.vue`), weekly product create/edit/deactivate/reactivate/unused delete.
  Extract `components/WeeklyProductFields.vue` from `Producer/Products/{Create,Edit}.vue`. `RoleAreas`
  `catalogue`; *Catalogue* sidebar section for Catalogue without Manager; manager's *Catalog and stock* links to
  the moved page; `/manage/producers` links each producer there. Dashboard `areaShortcuts` with the Catalogue
  entry (R15).
- **Requirements.** R3 except availability: "Catalogue creates, edits and deactivates any producer's weekly
  products, under the same rules a producer has for its own (001 R1, R1c) … The manager has the same powers …
  the last saved edit wins and nobody is notified. A Catalogue account that is also a producer's account edits
  other producers' products only through this role." R5 "It sees the producer list, without creating, editing,
  deactivating or reactivating producers or inviting their accounts." R14, R15 for Catalogue.
- **Dependencies.** T01: `Role::Catalogue` and its stored value.
- **Done when.**
  - Given P's "Tomàquet" has lines in an unsettled cycle, a Catalogue account changes its price (saved) and its
    unit (rejected as for P); P's later edit replaces it; same for a manager (S4).
  - Given P's account holds Catalogue, `/producer/products` lists only P's products and `/catalogue/producers/Q/products`
    edits Q's (S5).
  - A Catalogue-only account with no household logs in and lands in *Catàleg* via the role switch; the
    dashboard shows its shortcut and no new card (S1, R2, R15).
  - The producer list marks deactivated producers and shows no create/deactivate/invite action to Catalogue;
    producer administration routes stay 403 for it (R5).
  - Receiver-only, Finance-only and member-only accounts get 403 on `/catalogue/*` (R14).
- **First checkpoint.** Application test: a Catalogue actor's `UpdateProduct` on another producer's weekly
  product fails with `AuthorizationException`, then passes.
- **Size and rollout.** Roughly 450–600 authored lines; route move plus one extracted component. Stock actions
  on the moved page keep their current Manager-only routes until T04.
- **Limits.** Stock CRUD, classification, return items and stock history stay Manager-only (T04). No
  availability (T05).

### T04 Catalogue administers stock products, classification and return items

- **Outcome.** From the producer products page a Catalogue account administers stock products, changes an unused
  product's stock flag, reads stock quantities and history, and edits the return-item catalogue.
- **Scope and path.** Stock CRUD (`CreateStockProduct`, `UpdateStockProduct`, `DeactivateStockProduct`,
  `ReactivateStockProduct`) and `ChangeProductClassification` to `administer-catalogue`; `ProductPolicy` stock
  branch; routes moved to `/catalogue/producers/{producer}/products/stock/...` and
  `/catalogue/products/{product}/classification`. Gate `view-stock` on `ListStock`, `ListStockMovements`;
  basket ids only for `manage-sales` (D5). Page `/catalogue/stock/{product}/movements` reusing
  `components/stock/MovementHistory.vue`; available quantity on the products page. Return-item commands to
  `administer-catalogue`, `ListReturnItems` to `view-return-items`, routes moved to `/catalogue/return-items`.
  Sidebar: *Return items* in Catalogue and manager sections.
- **Requirements.** R4 in full; R5 "Catalogue administers the return-item catalogue (001 R20a)"; R14 refusals.
- **Dependencies.** T03: `/catalogue` prefix, `administer-catalogue` gate and the moved products page.
- **Done when.**
  - Given a Catalogue account, it opens any producer's products, changes an unused product's stock flag, edits
    the return-item catalogue; it has no action to create or deactivate a producer, invite its account, record
    an entry or adjustment, or manage a cycle, and those routes are 403 (S6).
  - A producer's account holding Catalogue edits its own stock product through `/catalogue/…` and still cannot
    from `/producer/products` (R4, 001 R25b).
  - Catalogue's movement history shows quantities and no basket link; Finance and Manager still get links (D5).
  - Finance's basket return picker still lists return items (regression).
- **First checkpoint.** Application test: Catalogue `ChangeProductClassification` on an unused product fails,
  then passes.
- **Size and rollout.** Roughly 350–500 authored lines, mostly gate swaps, route moves and one page.
- **Limits.** `/manage/stock` entries, adjustments and set-available stay Manager-only (001 R26, R27).

### T05 Catalogue sets per-cycle availability

- **Outcome.** From a producer's Catàleg page, Catalogue and the manager mark a weekly product unavailable or
  available again in a cycle whose producer order is not sent; deactivated producers stay administrable.
- **Scope and path.** `SetProductAvailability` authorizes `owns` or `administer-catalogue`. Ordering queries
  `ListProducerUnsentCycles`, `GetProducerCycleAvailability` (D9) with a Catalog-facing read through the
  existing ports. Page `Catalogue/Producers/Availability.vue`, routes
  `GET /catalogue/producers/{producer}/availability` and
  `PUT /catalogue/producers/{producer}/cycles/{cycle}/products/{product}/availability`.
- **Requirements.** R3 "marks them unavailable or available again in a cycle while their producer order is not
  sent (001 R3c)"; R5 "Deactivated producers are listed and marked; Catalogue administers their weekly and stock
  products as for any producer, and they still cannot be ordered from."
- **Dependencies.** T03: Catàleg producer pages and `administer-catalogue`.
- **Done when.**
  - Given P is deactivated and Q's order in C is not sent, a Catalogue account sees P marked deactivated and
    edits its products; it marks Q's "Tomàquet" unavailable in C and a member can no longer newly order it
    there (S13).
  - After Q's order is sent, the toggle is absent (`canChange` false) and a forced request is rejected with
    `ProducerOrderSent`.
  - The availability page shows no cycle totals or cycle actions (D9, R14).
- **First checkpoint.** Application test: Catalogue `SetProductAvailability` for Q's product fails with
  `AuthorizationException`, then passes, and `GetOrderableProduct` in C refuses it.
- **Size and rollout.** Roughly 250–400 authored lines.
- **Limits.** The Producer area's `GetProducerCycleSummary` is unchanged.

### T06 Recepció area: Receiver and Finance receive orders

- **Outcome.** A Receiver account logs in to *Recepció*, sees the cycles with sent orders, opens a reception,
  sets received totals and allocations; Finance reaches the same pages. Validation, distribution and reopening
  stay with the manager until T07.
- **Scope and path.** Gates `receive` (Manager, Finance, Receiver) and `settle-reception` (Manager only in this
  task; Finance added in T07) on the plan's use cases. `ReceptionData` `canEdit`, `canValidate`, `canDistribute`,
  `canReopen` from `GetReception`; `Manage/Reception/Show.vue` moved to `Receptions/Show.vue` and reading only
  those flags. `ListReceptionCycles` with a read-repository method and reception state enum; page
  `Receptions/Index.vue`. Routes under `/receptions` (`role:manager,finance,receiver`) replacing
  `/manage/cycles/{cycle}/receptions/{producer}`; old GET redirects; manager cycle page links updated.
  `RoleAreas` `receiver`; sidebar *Reception* section, *Receptions* items for Finance and Manager; dashboard
  shortcut for Receiver.
- **Requirements.** R6 "Receiver opens the reception of any sent producer order, adds unordered weekly products
  to it, sets received totals and allocates to households (001 R14–R16). It cannot validate a reception or mark
  it distributed, and cannot reopen it … Changing a received total after validation clears the validation."
  R7 receive part. R8 in full. R14, R15 for Receiver.
- **Dependencies.** T01: `Role::Receiver` and its stored value.
- **Done when.**
  - Given P's order in C is sent, a Receiver opens P's reception from *Recepció*, sets a received total and
    changes a household's allocation; both are saved; the page has no validate, distribute, reopen or cycle
    action and those routes are 403 for it (S7).
  - Given a validated reception, a Receiver changes a received total and the validation is cleared (R6).
  - The cycle list shows only cycles with a sent order, closed ones included, newest pickup first; a distributed
    reception is read-only (`canEdit` false) (R8).
  - A Receiver-only account gets 403 on a cycle's settings, a producer's products page (whichever of `/manage/producers/{producer}/products` or `/catalogue/producers/{producer}/products` is current) and
    `/manage/roles` (S11).
  - A Finance account receives through the same pages (R7, receive part).
  - The old manager reception URL redirects; the manager keeps every action.
- **First checkpoint.** Application test: Receiver `SetReceivedTotal` fails with `AuthorizationException`, then
  passes, with the allocation persisted through the Eloquent repository.
- **Size and rollout.** Roughly 450–650 authored lines: gate swaps, `can` flags, one query, one new page, one
  moved page. Safe intermediate: Finance and Receiver can receive; settling is still the manager's.
- **Limits.** No Finance settling (T07). No change to allocation math, late-arrival or redistribution rules.

### T07 Finance validates, distributes and reopens receptions

- **Outcome.** Finance runs every reception step; a Receiver works on a reception Finance reopened under 001 R18.
- **Scope and path.** `settle-reception` adds Finance; `ValidateReception`, `MarkProducerDistributed`,
  `ReopenProducerOrder` already use it after T06. Tests for Finance and Receiver after a reopen.
- **Requirements.** R7 "Finance and the manager run every reception step: R14–R16, validation (R17a),
  distribution and reopening (R18)." R6 "After Finance or a manager reopens a reception, Receiver changes it
  under the same rules (001 R18), including late arrivals and redistributions and their effect on weekly
  baskets."
- **Dependencies.** T06: the `settle-reception` gate, moved routes and `can` flags.
- **Done when.**
  - Given a Receiver recorded P's reception and allocations are balanced, Finance validates it and marks it
    distributed, and P's order is distributed (S8).
  - Given P's order in C is distributed and Finance reopens it, a Receiver sets the received total of a product
    that had arrived at 0; it is a late arrival and each share goes to the household's weekly basket draft
    (S12). A redistribution of a product in a validated basket is still rejected (001 D12).
  - Finance sees validate/distribute/reopen in the page; Receiver still does not.
- **First checkpoint.** Application test: Finance `ValidateReception` fails, then passes.
- **Size and rollout.** Roughly 150–250 authored lines, mostly tests. Separate from T06 because it delivers
  Finance's settle powers on their own and keeps T06 reviewable.
- **Limits.** No change to settlement, basket synchronization or closure.
