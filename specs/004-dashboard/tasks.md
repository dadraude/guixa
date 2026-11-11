# 004 — Dashboard: attention and opportunities · Tasks

**Status: Accepted in Q24 (2026-11-11).** Source: [accepted spec](spec.md) R1–R16/S1–S13;
[accepted plan](plan.md); [constitution](../constitution.md). These IDs are local to 004, starting at T01.
This document does not authorize implementation, ticket publication, or a feature verdict. Before admission,
confirm 001 feature closure and delivered 003 behavior on the implementation base; current read-only tracker
Done statuses are supporting evidence, not that verdict.

## Architecture and safe sequence

`GET /dashboard` already renders the 001 T61 summary from `DashboardController` and `Dashboard.vue`; 002 T05
owns login routing. Keep both paths working throughout. T01 establishes the new read-only Dashboard
composition through a grouped collection result in **one attention list**, replacing only existing collection
summaries. T02–T04 add reception, draft and send groups to that same list; T05 adds opportunities below it.
The untouched interim summaries remain available meanwhile, and no partial read makes a global “caught up” or
completeness claim. T06 adds return/refresh, known read-error and last-success behavior to the list without
depending on all families. T07 applies complete priority/dedup/empty semantics and removes superseded interim
content only when every reader and the failure contract are present. No flag, disposable family UI or new
workflow command is needed.

Existing candidate seams are Sales `ListMemberPendingCollections`, `ListPendingCollections`, `ListBaskets`;
Ordering `ListReceptionCycles`, `ListCycles`, `ListMemberCycles`, `ReceptionLoader`; and existing task routes
in `routes/web.php`. New internal source reads take explicit scopes/IDs, no actor; Dashboard entry query
checks the actor's current gates, and Dashboard adapters map exported source DTOs to its own projections
(A4/A10/A11). No source reader writes a reception. `DashboardController` translates the query to authorized
route URLs and props only; no filtering/sorting there or in Vue (A5/A13/A16). Each task starts with a failing
public Application test and includes its touched contracts, adapter, HTTP/UI, translations, tests,
architecture declarations and docs together. Planned paths are marked below; all others are existing.

## Execution and evidence contract

- All affected UI states use `lang/ca` copy, generated DTO TypeScript, existing `Dashboard.vue` shell and
  `resources/js/components/ui/`/semantic tokens. Inspect existing row/card variants before adding a component.
  Controls and links show their server supplied `can` flags, without Vue status/kind decisions. Every visible
  family gets measured at 375 and 1280 px, light/dark, and risky 1024/200% text plus keyboard/44 px checks;
  use the visual runner's measurement mode first.
- Application tests cover each allocated S# through the public query and real source adapters. HTTP tests
  check guest/auth wiring, `assertInertia` props, allowed links and direct destination refusal/current state.
  A focused no-write assertion checks that reading an unopened reception persists no reception/received-total
  record. Existing route tests and changed-contract consumers remain regression checks. The eventual task
  owner reports changed paths and check results; the entries below state planned checks, not passing results.
- `architecture/modules.php` and `docs/architecture/modules.md` change with each new/changed export, with
  architecture tests. Regenerate DTO types using the repository's existing generator. No migration, table,
  package, job, background poll or dashboard write route. A design mismatch goes to plan/tasks plus a new
  Deviation in the task PR; a spec mismatch needs the workflow's spec-sync decision path.
- `database/seeders/DemoSeeder.php` and `tests/Feature/Database/DemoSeederTest.php` are checked for every
  visible family. T02 owns a balanced validated reception awaiting distribution; T04 owns an eligible manual
  send; T07 owns the combined-role/empty dashboard demo state. T01/T03/T05 add fixture/assertion coverage for
  visible collection, draft and opportunity states if current fixtures lack them. If no seeder edit is needed,
  the task handoff states why.

## Canonical task registry

Published under [DRA-256](https://linear.app/draude/issue/DRA-256/dashboard-attention-and-opportunities-first-slice); see the [publication record](linear-publication.md).

| ID | Linear | Task | Acceptance owner | After |
| --- | --- | --- | --- | --- |
| T01 | [DRA-257](https://linear.app/draude/issue/DRA-257/t01-show-grouped-pending-basket-collections) | <a id="t01"></a>[Show grouped pending basket collections](#t01-show-grouped-pending-basket-collections) | R1/R2/R3 collection subset, R4, R8 collection subset, R14 collection persistence; S4, S1 collection portion | — |
| T02 | [DRA-258](https://linear.app/draude/issue/DRA-258/t02-add-read-only-reception-groups-to-attention) | <a id="t02"></a>[Add read-only reception groups to attention](#t02-add-read-only-reception-groups-to-attention) | R5/R6, R10 distribution visibility, R1/R3 reception subset; S5/S6 | T01: Dashboard composition/list contract |
| T03 | [DRA-259](https://linear.app/draude/issue/DRA-259/t03-add-basket-preparation-and-validation-groups) | <a id="t03"></a>[Add basket preparation and validation groups](#t03-add-basket-preparation-and-validation-groups) | R7, R10 basket visibility; S7 (with T01 collection) | T01: Dashboard composition/list contract |
| T04 | [DRA-260](https://linear.app/draude/issue/DRA-260/t04-add-eligible-manual-send-groups) | <a id="t04"></a>[Add eligible manual-send groups](#t04-add-eligible-manual-send-groups) | R9, R10 no manual closure, R12 send language; S8 | T01: Dashboard composition/list contract |
| T05 | [DRA-261](https://linear.app/draude/issue/DRA-261/t05-show-scoped-ordering-producer-and-catalogue-opportunities) | <a id="t05"></a>[Show scoped ordering, producer and Catalogue opportunities](#t05-show-scoped-ordering-producer-and-catalogue-opportunities) | R11, R1 opportunity privacy; S2/S3 opportunity portions | T01: Dashboard composition/list contract |
| T06 | [DRA-262](https://linear.app/draude/issue/DRA-262/t06-refresh-on-return-and-expose-read-failures) | <a id="t06"></a>[Refresh on return and expose read failures](#t06-refresh-on-return-and-expose-read-failures) | R15 generic contract, R1 current access after change, R16 refresh states; S11/S12 generic portions | T01: Dashboard read/list contract |
| T07 | [DRA-263](https://linear.app/draude/issue/DRA-263/t07-complete-attention-priority-and-caught-up-dashboard) | <a id="t07"></a>[Complete attention priority and caught-up dashboard](#t07-complete-attention-priority-and-caught-up-dashboard) | R1–R3 union/final links, R8 dedup, R10 absence, R12–R15 full family behavior, R16 final; S1/S2/S3/S7/S9–S13 complete | T02–T06: all families and failure contract |

Current dependency frontier: **T01**, subject to external readiness/admission above. After T01
integrates, **T02–T06** are technical frontier leaves: each extends the established list or read contract
without consuming another's behavior. T07 needs every family and the failure contract to make global caught-up
truthful. Shared Dashboard/Ordering files require scheduling and reconciliation of edits, not dependency
edges.

## Coverage and conservation

| Ref | Completion owner and retained behavior |
| --- | --- |
| R1 | T01–T05 own each scoped family; T06 proves changed-role/household refresh; T07 owns complete role union. Existing destination checks remain in their owning feature. |
| R2 | T01 establishes distinct action/record and group contracts in one list; T02–T04 feed it; T07 owns full mixed-role dedup and all-family identity. |
| R3 | T01–T05 provide existing list/detail URLs with no command; T06 checks stale destinations; T07 checks complete navigation. |
| R4 | T01. |
| R5–R6 | T02. |
| R7 | T03. |
| R8 | T01 staff collection, T06 correction/refresh, T07 full mixed-role dedup. |
| R9 | T04. |
| R10 | T02/T03 preserve distribution and basket visibility; T04/T07 exclude manual closure. Automatic close remains 001 R24b, with regression in T03/T07. |
| R11 | T05 opportunities; T07 caught-up persistence and shortcuts. 003 R15 retains area shortcut ownership. |
| R12 | T04 send/cutoff language, T05 opportunity cutoff, T07 all-family priority and cooperative-local time. |
| R13 | T01 stable key/group primitive; T02–T04 extend stage order as they join the list; T07 proves complete date/stage/tie ordering. |
| R14 | T01 basket persistence, T02/T04 cross-cycle work, T07 all-family empty/no-context/cancelled rules. |
| R15 | T06 owns the generic read/refresh/failure contract; T07 proves its complete all-family behavior. |
| R16 | Each UI task measures its touched state; T06 owns busy/error/refresh states; T07 owns S13's final populated, caught-up and all-state regression. |
| S1 | T01 collection portion, T02 reception portion; T07 owns complete mixed-role HTTP/Application result and links. |
| S2 | T01 current-member collection, T05 current-member ordering/own-producer review; T07 owns combined privacy result; T06 checks return after a change. |
| S3 | T01 collection, T05 ordering opportunity; T07 owns final priority/wording. |
| S4 | T01. |
| S5–S6 | T02. |
| S7 | T03, using T01 collection; T07 keeps automatic closure out of the final UI. |
| S8 | T04. |
| S9–S10 | T07. |
| S11–S12 | T06 generic refresh/role-change behavior with collection; T07 full reception/Receiver and all-family regression. |
| S13 | T07 final all-state browser verification, with T06 busy/error measurements. |

No accepted criterion is allocated to retired 002 T01–T04. The existing 002 T05 login destination and 001 T61
interim dashboard are regression constraints, not new 004 deliverables. Each task receives its allocated
acceptance and shared-invariant regression review after integration; after T07 the selected first delivery
slice receives its R1–R16/S1–S13 acceptance review on the current base, separately from task Done status.
Later 004 insight slices are outside this verdict.

## Task details

### T01 Show grouped pending basket collections

- **Outcome.** Current members and Finance/Manager see collection groups in the page's one attention list;
  existing unrelated interim summary stays available. No global empty claim.
- **Scope and path.** Introduce planned `src/Dashboard/Application/ListDashboard.php`,
  `DashboardData`/`AttentionGroupData`, `src/Dashboard/Domain/BasketWorkRepository.php`, Sales
  adapter/projection and planned internal `src/Sales/Application/ReadDashboardBasketWork.php` for member/staff
  collection only. Entry query authorizes current actor, sends current household ID or staff scope, merges by
  `(collect_basket,basket UUID)`, groups by cycle/date/no-cycle and gives list/detail destination identifiers.
  `app/Http/Controllers/DashboardController.php` maps them to existing `/baskets` or `/finance/baskets`
  routes; `resources/js/pages/Dashboard.vue` starts one attention list and removes only superseded collection
  summary cards. Update `architecture/modules.php`/`docs/architecture/modules.md`, generated types, Catalan
  labels, existing `tests/Feature/DashboardTest.php` and planned
  `tests/Feature/Dashboard/ListDashboardTest.php` plus Sales internal-read tests. Source read uses existing
  `BasketRepository::pendingCollections` rules (including effective reversal and inactive household staff
  work), not amount or wallet balance. Until T06, unexpected read failure follows the normal HTTP error path
  and never produces empty props or caught-up copy.
- **Requirements.** R1–R4/R8/R14 collection subset; S4 and S1's H-basket collection assertion; 001
  R23a/R23b/R24/R24a. Current page summary for unrelated areas remains from 001 T61.
- **Dependencies.** None; uses already delivered Sales and the existing dashboard route.
- **Done when.** Given H has a validated late basket, a closed-cycle basket and a cycleless stock basket,
  when H's current member opens the dashboard, then three distinct pending records appear in their
  cycle/no-cycle groups with authorized `/baskets` links; a member of
  J sees none of H's records (S4). Given Finance also belongs to H, the H basket counts once in the staff-wide
  collection group and a second household's basket is visible to Finance; a member-only actor sees no
  staff-wide group (S1 portion). Given a collected or effectively fully reversed basket, it is absent;
  changing H's wallet balance from zero to negative does not remove an effective pending basket. `GET
  /dashboard` persists no change and uses only GET destinations. At 375 px the list has visible counts/links
  and no horizontal overflow.
- **First checkpoint.** Failing public `ListDashboard` Application test for a current member's validated
  closed-cycle basket; make it pass through the real Sales adapter and assert group count, no-cycle/date
  context and route ID, then inspect rendered Inertia prop.
- **Size/rollout.** Roughly 450–650 authored lines (low confidence: new module/type plumbing); generated types
  and deletions separate. One larger vertical foundation is justified because the first observable group needs
  actor scope, adapter, DTO, controller, page and tests. Safe intermediate: one attention list has collection
  groups only, with no all-work or caught-up wording.
- **Limits.** No reception, draft, send or opportunity projection (T02–T05); no final complete priority/empty
  state (T07); no explicit refresh (T06).

### T02 Add read-only reception groups to attention

- **Outcome.** Receiver, Finance and Manager see current permitted sent-order reception work in the existing
  attention list and navigate to `/receptions/{cycle}/{producer}` without opening or changing it by dashboard
  read.
- **Scope and path.** Planned `src/Ordering/Application/ReadDashboardOrderWork.php` reception scope and
  Ordering adapter under `src/Dashboard/Infrastructure/Ordering/`. Reuse
  `CycleRepository::listWithSentOrders`, `ReceptionLoader::load` or a batch read, and existing Reception
  balance rule; project `(cycle UUID,producer UUID)` identity, stage and allocation blocker. `ListDashboard`
  adds reception groups to the **same** list with producer/cycle context, count and permitted list/detail URL,
  preserving stable stage order among present groups. Receiver can see check/resolve, Finance/Manager
  check/resolve/distribute; no dashboard command control. Update `architecture/modules.php`,
  `docs/architecture/modules.md`, planned Ordering/Dashboard query tests, existing
  `tests/Feature/DashboardTest.php` and `tests/Feature/Ordering/ReceptionPageTest.php` consumers. Seeder/test
  adds unopened, unvalidated default and balanced validated examples if missing.
- **Requirements.** R1/R3 reception subset, R5/R6, R10 distribution visibility; S5/S6. Source rules 001
  R17/R18 and 003 Receiver/Finance gates.
- **Dependencies.** T01 supplies Dashboard query/port/UI group contract. The source read is separately
  mergeable only once it can surface a real group through that contract.
- **Done when.** Given an unopened sent order and another default-balanced unvalidated reception, when
  Receiver or Finance opens the dashboard, both are “check delivery and allocations” by producer/cycle and the
  count of persisted reception/received-total rows is unchanged (S5). Given allocation total differs from
  received total, the group names the blocker; received-vs-basket variance alone does not. Given validation
  and balance, Finance/Manager sees “mark distributed,” Receiver sees no pending distribution; distributed
  leaves the list, and a reopen or received-total edit reclassifies on next GET (S6). Forced direct
  destination commands still use their existing gates; dashboard emits no POST/PUT. At 375 px the new rows
  have visible names/counts/links and no horizontal overflow.
- **First checkpoint.** Failing public `ListDashboard` Application test for an unopened sent order returning
  review work without inserting received totals; pass through real Ordering read adapter and assert the table
  remains unchanged.
- **Size/rollout.** Roughly 350–550 authored lines (low confidence: reception projection/batching). Reuses
  existing detail/list screen and no new state. A read-only query may be internally checkpointed at unopened,
  blocked and validated stages before UI completion.
- **Limits.** No basket draft or send work (T03/T04); no global caught-up claim before T07.

### T03 Add basket preparation and validation groups

- **Outcome.** Finance/Manager see actionable draft basket groups, correctly labelled preparation or
  validation, in the same attention list as collection.
- **Scope and path.** Extend planned `src/Sales/Application/ReadDashboardBasketWork.php` staff scope and
  Dashboard Sales adapter with Basket domain effective-content/action eligibility, source cycle or independent
  date context and existing `/finance/baskets` detail/list destination. Add draft groups to `ListDashboard`'s
  attention list and Catalan copy; grouping by action kind keeps preparation and validation distinct. Planned
  source/Dashboard Application tests, existing `tests/Feature/DashboardTest.php`,
  `tests/Feature/Sales/PendingCollectionVisibilityTest.php` and seeder tests cover effective goods/returns,
  zero/net-zero money, cancelled/empty drafts and automatic closure regression. Delivered rule (Deviation
  `history/2026-11-11T21-10-37.278Z-DRA-259-7a081a1b-4086-460a-be3a-94358cd86276.md`): `Basket::canBeValidated()`, the condition `validated()` enforces, makes any draft with
  an active line `validate_basket`; `prepare_basket` stays in the contract but no state reaches it today.
- **Requirements.** R7/R10, S7; 001 R19a/R22/R24b. T01's collection remains.
- **Dependencies.** T01 gives the Sales adapter, grouping contract and collection path to extend.
- **Done when.** Given a settled cycle with one effective draft ready for validation, an empty retained draft and
  a validated basket still to collect, when Finance opens the dashboard, then it sees the draft as validation
  work, excludes the empty draft, and retains the collection group (S7). The "draft needing preparation"
  case cannot be built under 001 R19a/R22 (Deviation `history/2026-11-11T21-10-37.278Z-DRA-259-7a081a1b-4086-460a-be3a-94358cd86276.md`). Zero price/net-zero goods or returns still counts when effective content exists.
  Given final weekly basket validation/no-collection-due, the existing 001 closure path operates without any
  dashboard close command. Receiver/Catalogue/member get no staff draft work.
- **First checkpoint.** Failing Application test for a zero-price effective draft that can validate (done); make the
  Sales read and Dashboard group pass without inspecting `totalCents` for eligibility.
- **Size/rollout.** Roughly 230–380 authored lines (medium confidence); no new persistence or command. No
  global caught-up claim.
- **Limits.** No manager send or other role opportunity work (T04/T05); all-stage priority belongs to T07.

### T04 Add eligible manual-send groups

- **Outcome.** Manager sees only producer orders that existing 001 R12a permits manual sending as groups in
  the same attention list, with a link to the cycle send screen.
- **Scope and path.** Add the `manualSend` scope to planned
  `src/Ordering/Application/ReadDashboardOrderWork.php` using `Cycle::producersReadyToSend` and
  `SendableTotals`/notifyable-account rule already used by `ListCycles`. Ordering adapter projects `(cycle
  UUID,producer UUID)` and scheduled date; reconcile the shared class/adapter with T02 if it integrates first.
  `ListDashboard` inserts send groups into its existing attention list with stable stage order and route to
  `/manage/cycles…`. Add planned source/Dashboard Application tests, existing
  `tests/Feature/DashboardTest.php` and `tests/Feature/Ordering/CyclesPagesTest.php` as changed-contract
  consumers, plus demo fixture/test for one eligible send.
- **Requirements.** R9/R10/R12; S8. No invented cutoff-as-deadline or manual cycle close.
- **Dependencies.** T01 supplies the Dashboard list/group contract. T02 shares an Ordering source
  query/adapter file, but its reception behavior is not required for manual send; coordinate file edits
  without a behavioral dependency.
- **Done when.** Given one non-empty participating closed-for-ordering producer with a notifyable account,
  another still open, one empty, one already sent and one nonparticipant, when Manager opens the dashboard,
  then exactly the first appears with one permitted send-screen link (S8). Finance/Receiver do not see send; a
  passed cutoff is not labelled overdue. No close-cycle action or send request originates on the dashboard.
- **First checkpoint.** Failing public query test for the one eligible producer among the five cases; pass
  using current Ordering rule and inspect rendered link prop.
- **Size/rollout.** Roughly 200–330 authored lines (medium confidence); reuses existing send screen and rules.
- **Limits.** No new send eligibility, scheduler change or send command; no full-list priority until T07.

### T05 Show scoped ordering, producer and Catalogue opportunities

- **Outcome.** Members, producers and Catalogue staff reach permitted tasks/reviews without those links being
  called unfinished work.
- **Scope and path.** Planned internal `src/Ordering/Application/ReadDashboardOrderOpportunities.php` and
  Dashboard Ordering adapter accept current household ID or producer ID; read open member windows/applicable
  cutoffs or own sent summaries. Dashboard entry query gates role/association and adds Catalogue's existing
  `/catalogue/producers` maintenance destination, keeping existing `RoleAreas::shortcuts`. Render
  opportunities **below the one attention list** with translations; add planned Ordering/Dashboard Application
  tests, existing `tests/Feature/DashboardTest.php` and `resources/js/pages/Dashboard.spec.ts` regressions for
  moved households, own producer, inactive product and changed role. Seeder/test ensures representative
  opportunity states.
- **Requirements.** R1/R11/R12 opportunity subset, R14 shortcuts; S2/S3 portions and S10 opportunities.
  Existing 003 R15 owns shortcuts themselves.
- **Dependencies.** T01's Dashboard composition and attention-list contract.
- **Done when.** Given H's member moves to J with J's open window, the dashboard shows J's order link/cutoffs
  and no H opportunity; a producer associated to P sees only P's sent summary, with no household or Q data
  (S2). Given a saved member order, it is labelled available ordering rather than awaiting submission (S3). A
  Catalogue-only account with an intentionally inactive product sees a maintenance opportunity, not a task; a
  producer sent summary is a review opportunity (S10). Removing role/association removes the opportunity on a
  new read.
- **First checkpoint.** Failing public query test for a producer P account seeing P's sent summary and no
  Q/household data; pass through current producer ID into Ordering internal query.
- **Size/rollout.** Roughly 280–430 authored lines (medium confidence); no charts, new state or cutoff
  deadline inference.
- **Limits.** No all-family caught-up copy before T07; no insights/reporting.

### T06 Refresh on return and expose read failures

- **Outcome.** Whatever attention groups are currently delivered refresh on return or explicit action, show
  the last successful read, and remain honest through known/transport failures and changing access.
- **Scope and path.** Planned `src/Dashboard/Domain/Error/ReadUnavailable.php` and read-result handling in
  `src/Dashboard/Application/ListDashboard.php`; adapters translate only known source read failures, never
  authorization 403. Existing `app/Http/Controllers/DashboardController.php` supplies sibling `attention`,
  `opportunities`, `lastUpdatedAt`, `navigation`, `readFailed`, `can` props together; on initial known failure
  it sends null section data/time and false caught-up/no-context flags. `resources/js/pages/Dashboard.vue`
  uses the existing `resources/js/pages/Manage/Roles/Index.vue` reload pattern for explicit refresh, fresh
  Inertia navigation and one BFCache/history return read. It retains last successful data/time only on failed
  refresh/transport and marks them stale with retry; no polling. Add Catalan busy/error/time copy, planned
  `tests/Feature/Dashboard/ReadFreshnessTest.php`, existing `tests/Feature/DashboardTest.php` and
  `resources/js/pages/Dashboard.spec.ts` regressions, plus measured busy/error states.
- **Requirements.** R1 changed access, R3 stale destination, R15 generic behavior, R16 refresh states; S11/S12
  generic portions. T07 owns their full reception/Receiver assertions and S13 final all-state presentation.
  The generic result contract has no dependency on unimplemented families and must not invent a global empty
  state.
- **Dependencies.** T01 supplies the Dashboard read, list props and client surface. T02–T05 are independent;
  use present groups in S11 when available, otherwise prove the same refresh behavior with a collection
  record. T07 owns the reception-specific S11 regression after all families integrate.
- **Done when.** Given a second volunteer collects a basket while the page stays open, its old group remains
  until refresh or return; the destination shows current state. A successful refresh removes it and advances
  the cooperative-local last-updated time. A known initial read failure has null sections/time and an
  accessible error/retry, never a zero/caught-up result; a failed later read or non-success transport retains
  the prior successful group/time visibly stale with retry. Given member unlink or staff role removal,
  return/refresh removes inaccessible groups/links and a stale destination still refuses a forbidden action;
  Finance keeps inactive-household staff work (S12). No idle request or dashboard write request occurs.
  Busy/error/focus messages are readable at 375 px/200% text and by keyboard.
- **First checkpoint.** Failing public Application test: a known source read failure raises `ReadUnavailable`
  and cannot return a caught-up result; pass through the read adapter, then assert initial HTTP null/error
  props. A failing `Dashboard.spec.ts` interaction test next proves failed refresh preserves last successful
  item/time with stale/error/retry.
- **Size/rollout.** Roughly 330–520 authored lines (low confidence: history/BFCache/transport lifecycle). It
  delivers generic freshness on a real attention list with no global empty claim; no disposable UI.
- **Limits.** No automatic background refresh, notification or analytics; no final caught-up state until T07.

### T07 Complete attention priority and caught-up dashboard

- **Outcome.** All delivered work families appear in one deterministic attention list ahead of opportunities;
  a successful complete empty read can say the person is caught up, and the interim summary content is
  retired.
- **Scope and path.** `src/Dashboard/Application/ListDashboard.php` consumes T01–T05 projections and T06
  read-failure contract. Dedup by `(action,record type,stable record key)` and group by `(action,cycle or
  date/no-cycle context)`; sort actual deadlines, scheduled date, stage send→reception→basket→collection,
  human context and stable IDs. Source `Clock` supplies cooperative-local time. Server DTOs set distinct
  count, action/context enums, permitted destination and `can.showCaughtUp`/`can.showNoContext` only after all
  reads succeed. Existing `app/Http/Controllers/DashboardController.php` maps all destinations, and
  `resources/js/pages/Dashboard.vue` removes superseded 001 T61 cards while retaining the established single
  list, opportunities, refresh and role shortcuts. Update generated DTO types, Catalan copy, planned
  `tests/Feature/Dashboard/ListDashboardTest.php`, existing
  `tests/Feature/DashboardTest.php`/`resources/js/pages/Dashboard.spec.ts`, and combined-role/empty demo
  fixture assertions in `DemoSeederTest.php`.
- **Requirements.** R1–R3 union/final navigation, R8 mixed-role dedup, R10 absence, R11 opportunity retention,
  R12–R15 full-family behavior, R16 final; S1–S3/S7/S9–S13 complete. Regress 002 T05 login destination and
  existing 001 T61 route while replacing only its page content.
- **Dependencies.** T02, T03, T04 and T05 provide every accepted work/opportunity family; T06 provides initial
  failure and stale-result semantics. T01 is transitive. Without all of these, a global caught-up claim would
  be false; the final cutover is atomic.
- **Done when.** Given a member/Finance/Receiver actor and H's basket in staff-wide collections, the list
  counts it once while showing other households' staff work and permitted reception work; each link opens a
  current authorized screen (S1). Given two overlapping cycles, an older closed-cycle basket and cycleless
  stock basket, all appear in date/stage order with undated last; human context then stable IDs break ties
  (S9). Scheduled pickup alone creates no overdue claim; a sent summary/inactive product stays an opportunity
  (S3/S10). Given zero eligible pending records after a complete successful read, `can.showCaughtUp` is true
  with opportunities/shortcuts retained; known failure from T06 keeps it false, and a no-context actor sees
  explanation rather than fabricated zero. Cancelled weekly work and close-cycle action are absent;
  the cycleless stock basket remains. A direct stale link cannot bypass
  authorization. Given a reception distributed elsewhere or a removed Receiver role, refresh/return drops its
  item/link; failed refresh retains the last successful snapshot visibly stale (S11/S12). At 375 px/200% text,
  light/dark and keyboard, final populated/caught-up/busy/error states preserve names, counts, dates, links,
  refresh status, ≥44 px targets and no horizontal overflow or color/hover-only meaning (S13).
- **S13 data contract.** A public `ListDashboard` Application test asserts server-provided action/count/date
  fields, visibility flags and successful-read time required for accessible rendering; known failure yields
  the error path rather than invented zero data. Browser tests own geometry, focus and keyboard evidence.
- **First checkpoint.** Failing public Application test with one send, reception, draft and collection on the
  same date plus an older collection; pass all-family ordering/dedup, then assert Inertia props and rendered
  list before removing interim cards.
- **Size/rollout.** Roughly 380–600 authored lines plus separately reported interim UI/controller deletions
  (low confidence: all-family integration and responsive cutover). Larger atomic final step is justified
  because partial global empty semantics would misstate work. Internal checkpoints: all-family Application
  result, HTTP links/flags, interim-content removal, measured final UI. No feature flag.
- **Limits.** No later insights, charts or workflow commands; refresh/failure contract stays owned by T06.
