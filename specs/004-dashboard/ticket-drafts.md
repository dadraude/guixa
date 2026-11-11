# 004 Dashboard — proposed Linear ticket batch (draft only)

**Publication state:** Approved snapshot, now published; see [publication record](linear-publication.md). The original proposal below is retained as publication evidence.

**Original draft state:** Unpublished proposal. No Linear issue, relationship, label or status has been written. Source is the accepted [spec](spec.md), [plan](plan.md) and [tasks](tasks.md) in local content commit `e837c5a1`, with Q24 acceptance metadata in local commit `f3dab910`; these are not yet verified public GitHub sources. Relative links resolve from the proposed canonical `specs/004-dashboard/ticket-drafts.md` location. Before any separately authorized publication, read back tracker identities and publish/verify the source branch before adding remote source URLs. Discovery searched DRA/Coop issues for 004/dashboard/attention/opportunities/manual-send/refresh/caught-up/task phrases with complete pagination and found no current 004 parent/leaves; this bounded search is not a universal duplicate guarantee. Target workspace `draude`, team `DRA` (Draude), project `Coop` (`P-DRA-3`). Propose `Backlog` for all eight issues; no assignee, cycle, priority, due date, milestone or `ready-for-agent` label. No implementation task is admitted; 001 closure and delivered 003 behavior still need implementation-base confirmation.

## Native graph proposal

Draft keys identify proposed issues only. Replace them with returned Linear IDs when publication is separately authorized. Parent has no task ID and no ready label. Every child has `parentId: P004`; it is **not** blocked by the grouping parent. Propose parent `relatedTo: [DRA-206, DRA-248]` for inherited 001/003 context only, never as blockers; children have no additional related links. Retired 002 DRA-201–204 are Canceled and unparented; leave them and their old chain untouched. Existing DRA-216 (001 T61), DRA-183/206 and DRA-248–255 remain untouched. No issue is reparented.

| Draft key | Proposed title | `parentId` | `blockedBy` | Reason |
| --- | --- | --- | --- | --- |
| P004 | Dashboard: attention and opportunities — first slice | none | none | Grouping outcome only; no executable ID. |
| T01 | T01 — Show grouped pending basket collections | P004 | none | First end-to-end Dashboard read/list contract. |
| T02 | T02 — Add read-only reception groups to attention | P004 | T01 | Uses its Dashboard composition and list/group contract. |
| T03 | T03 — Add basket preparation and validation groups | P004 | T01 | Extends its Sales adapter and list/group contract. |
| T04 | T04 — Add eligible manual-send groups | P004 | T01 | Uses its Dashboard list/group contract; Ordering shared-file contention is scheduling, not a blocker. |
| T05 | T05 — Show scoped ordering, producer and Catalogue opportunities | P004 | T01 | Uses its Dashboard composition and page contract. |
| T06 | T06 — Refresh on return and expose read failures | P004 | T01 | Needs a real Dashboard read/list to refresh; independent of other families. |
| T07 | T07 — Complete attention priority and caught-up dashboard | P004 | T02, T03, T04, T05, T06 | Needs every work/opportunity family and failure contract before global caught-up can be truthful. |

Technical frontier in this proposed graph: **T01**, subject to implementation admission. Once T01 is Done, T02–T06 become dependency-ready; shared source files still need scheduling. T07 follows all five. Do not add a blocking link from P004 to any child.

## P004 — Dashboard: attention and opportunities — first slice

**Proposed native fields:** team `DRA`; project `Coop`; `parentId: none`; `blockedBy: []`; `relatedTo: [DRA-206, DRA-248]` (context only); state `Backlog`; labels `[]`; assignee/cycle/priority/due date/milestone unset.

**Human summary.** Give every signed-in account one permission-scoped dashboard that puts actual pending work ahead of opportunities and links to current authorized task screens. Complete this selected first slice when collection, reception, basket draft, eligible send and permitted opportunity paths are visible together, correctly grouped and ordered, refreshed on return or request, and accessible on phone and keyboard.

**Completion criteria.** T01–T07 are integrated; the first-slice acceptance review verifies 004 R1–R16/S1–S13 together on the current base. No dashboard workflow write, invented deadline, false caught-up state, or cross-role privacy leak. Retired 002 dashboard content stays retired; 002 T05 login destination and 001 T61 route remain regression constraints while the interim page content is replaced.

+++ Agent notes — grouping context

- Canonical sources: `specs/004-dashboard/spec.md` (Q22 accepted), `specs/004-dashboard/plan.md` (Q23 accepted), `specs/004-dashboard/tasks.md` (accepted T01–T07, Q24 accepted in local commit `f3dab910`), `specs/constitution.md`. Keep the task registry authoritative; no new parent task ID.
- The first slice excludes later household/Finance/producer/operational insights, charts, reminders, payment, notifications and new workflow states. The parent is not executable, carries no `ready-for-agent` label, and its status does not block children.
- Before implementation admission, confirm 001 feature closure and 003 delivered role behavior on the base. DRA-183/206 and DRA-248–255 are Done, but tracker status alone is not a fresh feature acceptance verdict.
+++

## T01 — Show grouped pending basket collections

**Proposed native fields:** team `DRA`; project `Coop`; `parentId: P004`; `blockedBy: []`; `relatedTo: []`; state `Backlog`; labels `[]`; assignee/cycle/priority/due date/milestone unset.

**Human summary.** A current member and Finance/Manager see pending basket collections as counted groups in one dashboard attention list, with links to their authorized basket screens. This replaces only the interim collection cards; unrelated summary content stays, and the page makes no global caught-up claim.

**Acceptance.** Given H has a validated late basket, a closed-cycle basket, independent stock basket attributed to a cancelled cycle and a cycleless stock basket, H's current member sees the four eligible records in clear cycle/no-cycle groups; a member of J sees none of H's work (S4). Given Finance also belongs to H, H's basket counts once alongside other households' staff collections (S1 collection portion). Collected and effectively fully reversed baskets disappear; changing H's wallet from zero to negative does not remove an effective pending basket. The links open permitted current screens, and dashboard GET performs no write.

+++ Agent notes — executable handoff

- **Source/ownership:** [canonical task](tasks.md#t01-show-grouped-pending-basket-collections); R1–R4/R8/R14 collection subset, S4 and S1 collection portion; inherited 001 R23a/R23b/R24/R24a. Source acceptance: “A group never sums a staff-wide count and its member subset”; collection includes closed and cycleless baskets.
- **Path/contracts:** Existing `app/Http/Controllers/DashboardController.php`, `resources/js/pages/Dashboard.vue`, `src/Sales/Application/{ListMemberPendingCollections,ListPendingCollections}.php`, `/baskets` and `/finance/baskets` routes. Planned `src/Dashboard/Application/{ListDashboard,DashboardData,AttentionGroupData}.php`, `src/Dashboard/Domain/BasketWorkRepository.php`, Dashboard Sales adapter/projection, internal no-actor `src/Sales/Application/ReadDashboardBasketWork.php`. Entry Actor chooses current household/staff scope; Sales owns effective pending eligibility; key `(collect_basket,basket UUID)`, group by cycle/date/no-cycle; controller maps destination IDs only. Update `architecture/modules.php`, `docs/architecture/modules.md`, generated DTO types and Catalan copy.
- **Checks:** Planned `tests/Feature/Dashboard/ListDashboardTest.php` and Sales internal-read assertions; existing `tests/Feature/DashboardTest.php`, `tests/Feature/Sales/PendingCollectionVisibilityTest.php`, `resources/js/pages/Dashboard.spec.ts`, architecture/type/format checks. Verify no-write GET and 375 px readable counts/links. Check `database/seeders/DemoSeeder.php` and extend `tests/Feature/Database/DemoSeederTest.php` if visible collection states are missing; otherwise state why.
- **First checkpoint:** failing public `ListDashboard` Application test for a validated closed-cycle basket, then pass through real Sales adapter and inspect grouped Inertia prop/rendered link.
- **Size/safety:** ~450–650 authored lines, low confidence, generated output/deletions separately. Larger first vertical path spans actor scope, port, adapter, DTO, HTTP, UI and test. No global empty state; unexpected read error follows normal HTTP handling until T06. No reception/draft/send/opportunity projection, final all-family priority (T07) or refresh (T06).
- **Shared visual and integration checks:** Use real Application adapters and authenticated HTTP/Inertia assertions; test doubles alone do not prove integration. Measure each affected populated role at 375/1280 px, light/dark, 100% text; distinct layouts at 1024 px/200% text and unique empty/error/long-name states at their riskiest settings. Verify keyboard/focus, readable text counts/actions, ≥44 × 44 px targets, no horizontal overflow or color/hover-only meaning. Use the committed visual fixture/manifest and DOM measurements before optional previews; retain independent task review and candidate-bound CI.
+++

## T02 — Add read-only reception groups to attention

**Proposed native fields:** team `DRA`; project `Coop`; `parentId: P004`; `blockedBy: [T01]`; `relatedTo: []`; state `Backlog`; labels `[]`; assignee/cycle/priority/due date/milestone unset.

**Human summary.** Receiver, Finance and Manager see the reception work they can currently perform in the existing attention list, identified by producer and cycle, and follow a link to the authorized reception screen. Reading the dashboard does not open or alter a reception.

**Acceptance.** Given an unopened sent order and a default-balanced unvalidated reception, Receiver and Finance see “check delivery and allocations”; the dashboard read persists no reception/received-total change (S5). An allocation total that differs from received total is labelled work to resolve, while received-vs-basket variance alone is not a distribution blocker. A validated balanced reception appears as “mark distributed” only for Finance/Manager; after distribution it leaves the list, and reopen/received-total changes reclassify on the next read (S6). Receiver gets no validate/distribute claim.

+++ Agent notes — executable handoff

- **Source/ownership:** [canonical task](tasks.md#t02-add-read-only-reception-groups-to-attention); R1/R3 reception subset, R5/R6/R10 distribution visibility, S5/S6. Inherited 001 R17/R18 and 003 `receive`/`settle-reception` permissions. One unvalidated sent order is one review action; defaults do not prove physical checking.
- **Path/contracts:** Planned no-actor `src/Ordering/Application/ReadDashboardOrderWork.php` reception scope and `src/Dashboard/Infrastructure/Ordering/` adapter. Use existing `CycleRepository::listWithSentOrders`, read-only `ReceptionLoader::load` or batch equivalent and Reception allocation balance rule. Stable record identity `(cycle UUID,producer UUID)` works before any reception is persisted. Add groups to the same Dashboard list; no dashboard command. Existing destination `/receptions/{cycle}/{producer}` enforces current actions. Declare exports/dependencies in `architecture/modules.php` and document them.
- **Checks:** Planned Ordering/Dashboard Application tests; existing `tests/Feature/DashboardTest.php`, `tests/Feature/Ordering/ReceptionPageTest.php`, `resources/js/pages/Dashboard.spec.ts`, architecture/type/format. Assert unchanged received-total/reception persistence after dashboard read; confirm direct command gates. Measure new rows at 375 px. Add unopened, unvalidated default and balanced validated demo cases/assertions in `DemoSeeder.php`/`DemoSeederTest.php` if absent.
- **First checkpoint:** failing public `ListDashboard` Application test for unopened sent order review; pass through real Ordering read adapter and verify no received-total row was inserted.
- **Dependency/safety:** T01 supplies Dashboard query/list/group contract. ~350–550 authored lines, low confidence from projection/batching. No new reception state, no global caught-up, no draft/send work.
- **Shared visual and integration checks:** Use real Application adapters and authenticated HTTP/Inertia assertions; test doubles alone do not prove integration. Measure each affected populated role at 375/1280 px, light/dark, 100% text; distinct layouts at 1024 px/200% text and unique empty/error/long-name states at their riskiest settings. Verify keyboard/focus, readable text counts/actions, ≥44 × 44 px targets, no horizontal overflow or color/hover-only meaning. Use the committed visual fixture/manifest and DOM measurements before optional previews; retain independent task review and candidate-bound CI.
+++

## T03 — Add basket preparation and validation groups

**Proposed native fields:** team `DRA`; project `Coop`; `parentId: P004`; `blockedBy: [T01]`; `relatedTo: []`; state `Backlog`; labels `[]`; assignee/cycle/priority/due date/milestone unset.

**Human summary.** Finance and Manager see eligible basket drafts in the same attention list, named “prepare” or “validate” according to the existing Sales rules. Effective goods or returns can require validation even when the amount is zero.

**Acceptance.** Given a settled cycle with a draft ready for validation, another needing preparation, an empty retained draft and a validated basket awaiting collection, Finance sees the first two as distinct action groups, excludes the empty draft and retains collection (S7). A zero-price or net-zero effective basket stays eligible. Automatic closure still follows 001 R24b after final weekly basket validation/no collection due, with no dashboard closure command. Member/Receiver/Catalogue do not see staff draft work.

+++ Agent notes — executable handoff

- **Source/ownership:** [canonical task](tasks.md#t03-add-basket-preparation-and-validation-groups); R7/R10, S7, inherited 001 R19a/R22/R24b. Use effective content/action eligibility, never `totalCents` as a work filter.
- **Path/contracts:** Extend planned no-actor `src/Sales/Application/ReadDashboardBasketWork.php`, Dashboard Sales adapter and `ListDashboard` with staff draft scope and action-kind group key. Existing staff basket destination `/finance/baskets…`; no dashboard mutation. Preserve collection path introduced by T01 and cycle/no-cycle context. Update DTO generation/translation/module export if changed.
- **Checks:** Planned Sales/Dashboard Application tests; existing `tests/Feature/DashboardTest.php`, `tests/Feature/Sales/PendingCollectionVisibilityTest.php`, `resources/js/pages/Dashboard.spec.ts`, architecture/type/format and closure regression. Check DemoSeeder and its test for visible eligible draft state. Measure group copy/count/link at 375 px and text scaling.
- **First checkpoint:** failing public query test for zero-price effective draft eligible to validate; pass through Sales read and assert the Dashboard action without amount-based eligibility.
- **Dependency/safety:** T01 provides the Sales adapter and list/group contract. ~230–380 authored lines, medium confidence. No new basket command or global caught-up; all-stage priority remains T07.
- **Shared visual and integration checks:** Use real Application adapters and authenticated HTTP/Inertia assertions; test doubles alone do not prove integration. Measure each affected populated role at 375/1280 px, light/dark, 100% text; distinct layouts at 1024 px/200% text and unique empty/error/long-name states at their riskiest settings. Verify keyboard/focus, readable text counts/actions, ≥44 × 44 px targets, no horizontal overflow or color/hover-only meaning. Use the committed visual fixture/manifest and DOM measurements before optional previews; retain independent task review and candidate-bound CI.
+++

## T04 — Add eligible manual-send groups

**Proposed native fields:** team `DRA`; project `Coop`; `parentId: P004`; `blockedBy: [T01]`; `relatedTo: []`; state `Backlog`; labels `[]`; assignee/cycle/priority/due date/milestone unset.

**Human summary.** Manager sees exactly the producer orders already eligible for manual sending, grouped in the attention list with a link to the existing cycle send screen. A passed cutoff is not labelled an overdue send deadline.

**Acceptance.** Given one participating, non-empty, unsent producer order whose producer closed ordering and has an account to notify, plus a still-open, empty, already-sent and nonparticipating case, only the first appears for Manager (S8). Finance/Receiver see no send group. Dashboard emits no send request, no close-cycle action, and no invented cutoff deadline.

+++ Agent notes — executable handoff

- **Source/ownership:** [canonical task](tasks.md#t04-add-eligible-manual-send-groups); R9/R10/R12, S8; inherited 001 R12a. Empty-order sending belongs to the scheduler.
- **Path/contracts:** Add `manualSend` scope to planned no-actor `src/Ordering/Application/ReadDashboardOrderWork.php`, using existing `Cycle::producersReadyToSend` plus `SendableTotals`/notifyable-account rule from `ListCycles`. Dashboard Ordering adapter projects `(cycle UUID,producer UUID)` and scheduled date; add send group to the same attention list and map existing `/manage/cycles…` destination. Reconcile shared class/adapter with T02 by execution scheduling, not a blocker. Update manifest/export docs, generated types and Catalan copy as needed.
- **Checks:** Planned Ordering/Dashboard Application tests; existing `tests/Feature/DashboardTest.php`, `tests/Feature/Ordering/CyclesPagesTest.php`, `resources/js/pages/Dashboard.spec.ts`, architecture/type/format. DemoSeeder and DemoSeederTest show one eligible send if missing. Measure text action/date/target at 375 px.
- **First checkpoint:** failing public query test for exactly one eligible producer among the five cases; pass via current Ordering rule and inspect Inertia link prop.
- **Dependency/safety:** T01 provides Dashboard list/group contract. ~200–330 authored lines, medium confidence. T02 behavior is not required; shared files are scheduled. No new send rule, scheduler work, send command or global caught-up.
- **Shared visual and integration checks:** Use real Application adapters and authenticated HTTP/Inertia assertions; test doubles alone do not prove integration. Measure each affected populated role at 375/1280 px, light/dark, 100% text; distinct layouts at 1024 px/200% text and unique empty/error/long-name states at their riskiest settings. Verify keyboard/focus, readable text counts/actions, ≥44 × 44 px targets, no horizontal overflow or color/hover-only meaning. Use the committed visual fixture/manifest and DOM measurements before optional previews; retain independent task review and candidate-bound CI.
+++

## T05 — Show scoped ordering, producer and Catalogue opportunities

**Proposed native fields:** team `DRA`; project `Coop`; `parentId: P004`; `blockedBy: [T01]`; `relatedTo: []`; state `Backlog`; labels `[]`; assignee/cycle/priority/due date/milestone unset.

**Human summary.** Members see open ordering as an opportunity with applicable cutoffs; producers see only their own sent-order review; Catalogue sees product/availability maintenance. These stay below attention and are never labelled unfinished obligations. Existing permitted area shortcuts remain.

**Acceptance.** After a member moves from H to J, only J's open order/cutoff context appears; a producer P account sees only P's sent summaries and no household or Q data (S2). A saved member order is not called unsubmitted (S3). An inactive product alone does not create pending work; Catalogue maintenance and producer review remain opportunities when there is no pending work, without a premature caught-up message (S10 portion). Removing relevant association or role removes the opportunity on a new read.

+++ Agent notes — executable handoff

- **Source/ownership:** [canonical task](tasks.md#t05-show-scoped-ordering-producer-and-catalogue-opportunities); R1/R11/R12/R14 opportunity subset; S2/S3 and S10 opportunity portions. 003 R15 continues to own role-area shortcuts.
- **Path/contracts:** Planned no-actor `src/Ordering/Application/ReadDashboardOrderOpportunities.php` and Dashboard Ordering adapter take current household/producer ID only. Reuse open-window/cutoff and own sent-summary rules; source query does not forge an Actor. Dashboard entry query gates role/association; Catalogue maintenance uses existing `/catalogue/producers…`, member `/cycles/{cycle}/order`, producer `/producer/cycles/{cycle}`. Keep `RoleAreas::shortcuts`; render opportunities below the same attention list, with generated DTO type and Catalan copy.
- **Checks:** Planned Ordering/Dashboard Application tests; existing `tests/Feature/DashboardTest.php`, `tests/Feature/Ordering/ProducerCycleSummaryPageTest.php`, `resources/js/pages/Dashboard.spec.ts`, architecture/type/format. DemoSeeder/Test for each visible opportunity if missing. Measure labels/cutoffs/links at 375 px and 200% text.
- **First checkpoint:** failing public query test for producer P's own sent summary without Q or household data; pass through current association scope into Ordering.
- **Dependency/safety:** T01 provides Dashboard composition/page contract. ~280–430 authored lines, medium confidence. No report/chart/new cutoff deadline, and no all-family caught-up until T07.
- **Shared visual and integration checks:** Use real Application adapters and authenticated HTTP/Inertia assertions; test doubles alone do not prove integration. Measure each affected populated role at 375/1280 px, light/dark, 100% text; distinct layouts at 1024 px/200% text and unique empty/error/long-name states at their riskiest settings. Verify keyboard/focus, readable text counts/actions, ≥44 × 44 px targets, no horizontal overflow or color/hover-only meaning. Use the committed visual fixture/manifest and DOM measurements before optional previews; retain independent task review and candidate-bound CI.
+++

## T06 — Refresh on return and expose read failures

**Proposed native fields:** team `DRA`; project `Coop`; `parentId: P004`; `blockedBy: [T01]`; `relatedTo: []`; state `Backlog`; labels `[]`; assignee/cycle/priority/due date/milestone unset.

**Human summary.** The current attention list reads fresh data on return or explicit refresh and shows the last successful read. A known or transport failure keeps prior data visibly stale with retry, or shows an error with no fabricated empty state on first load.

**Acceptance.** Given another volunteer collects a basket while the dashboard remains open, the old group stays until refresh/return; its destination shows current state. Successful refresh removes it and advances local last-updated time. Known initial failure has null data/time and error/retry; later failure retains the last successful group/time as stale, never caught up. After a member unlink or staff role removal, return/refresh drops inaccessible links; stale destination requests still refuse unauthorized actions (S11/S12 generic portions). There is no idle polling or dashboard write.

+++ Agent notes — executable handoff

- **Source/ownership:** [canonical task](tasks.md#t06-refresh-on-return-and-expose-read-failures); R1/R3 changed access, R15 generic, R16 refresh; S11/S12 generic portions. T07 owns full reception/Receiver-specific S11/S12 regression and final S13 all-state presentation.
- **Path/contracts:** Planned typed `src/Dashboard/Domain/Error/ReadUnavailable.php`; Dashboard read/adapter translates only known read failures and propagates authorization 403. Existing `DashboardController.php` sends sibling `attention`, `opportunities`, `lastUpdatedAt`, `navigation`, `readFailed`, `can` props together; initial known failure has null sections/time and false empty/no-context flags. `Dashboard.vue` uses existing `resources/js/pages/Manage/Roles/Index.vue` Inertia reload lifecycle, plus one history/BFCache return read. Retain last successful snapshot only for failed refresh/transport; show accessible busy/error/stale/retry, no polling. Source instant formatted in cooperative timezone.
- **Checks:** Planned `tests/Feature/Dashboard/ReadFreshnessTest.php`; existing `tests/Feature/DashboardTest.php`, `resources/js/pages/Dashboard.spec.ts`, direct destination authorization tests, type/format. Measure busy/error/focus at 375 px/200% text; assert no idle request and no write request. DemoSeeder needs no new data class unless a visible error fixture is introduced; record the choice.
- **First checkpoint:** failing public Application test for a known source failure raising `ReadUnavailable` without a caught-up result, then initial HTTP null/error props. Next failing frontend test: failed refresh retains old item/time, marks stale, offers retry.
- **Dependency/safety:** T01 gives a real list and read contract. ~330–520 authored lines, low confidence from history/BFCache/transport. T02–T05 are independent. No global caught-up until T07.
- **Shared visual and integration checks:** Use real Application adapters and authenticated HTTP/Inertia assertions; test doubles alone do not prove integration. Measure each affected populated role at 375/1280 px, light/dark, 100% text; distinct layouts at 1024 px/200% text and unique empty/error/long-name states at their riskiest settings. Verify keyboard/focus, readable text counts/actions, ≥44 × 44 px targets, no horizontal overflow or color/hover-only meaning. Use the committed visual fixture/manifest and DOM measurements before optional previews; retain independent task review and candidate-bound CI.
+++

## T07 — Complete attention priority and caught-up dashboard

**Proposed native fields:** team `DRA`; project `Coop`; `parentId: P004`; `blockedBy: [T02,T03,T04,T05,T06]`; `relatedTo: []`; state `Backlog`; labels `[]`; assignee/cycle/priority/due date/milestone unset.

**Human summary.** Complete the one attention list across every permitted work family, put it before opportunities in stable date/stage order, and show caught-up only after a successful complete empty read. Remove the superseded interim summary while preserving login, route and role shortcuts.

**Acceptance.** A member/Finance/Receiver account sees its own collection once among staff work plus permitted reception groups, with current authorized links (S1). Overlapping cycles, an older closed-cycle basket and an undated cycleless basket all remain visible in deterministic date/stage order (S9). No scheduled pickup alone becomes overdue; inactive product/sent summary stays opportunity. An empty successful all-family read shows caught-up with shortcuts, while a known failure never does; no-context is explained (S10/R14). Refresh after external distribution or removed Receiver role drops those groups/links (S11/S12 full). Final populated, caught-up, busy and error views meet S13 at 375 px, 200% text, light/dark and keyboard.

+++ Agent notes — executable handoff

- **Source/ownership:** [canonical task](tasks.md#t07-complete-attention-priority-and-caught-up-dashboard); R1–R3/R8/R10–R16 full-family integration; S1–S3/S7/S9–S13 complete. Other R4–R9 family results remain owned by T01–T05 and are regressed here. Retired 002 T01–T04 are excluded; shipped 002 T05 login destination and 001 T61 route persist.
- **Path/contracts:** `src/Dashboard/Application/ListDashboard.php` consumes all source projections; dedup `(action,record type,stable record key)` before grouping `(action,cycle or date/no-cycle context)`, with `(cycle UUID,producer UUID)` for unopened reception/order work. Sort actual deadlines, scheduled dates, send→reception→basket→collection, human context and IDs; opportunities sort separately. Server sends distinct count, action/context enums, permitted destination and `can.showCaughtUp`/`can.showNoContext` only after complete success. Existing `app/Http/Controllers/DashboardController.php` maps URLs; `resources/js/pages/Dashboard.vue` removes superseded cards while keeping one list, opportunities, T06 refresh and `RoleAreas` shortcuts. Update generated types and Catalan copy.
- **Checks:** Planned `tests/Feature/Dashboard/ListDashboardTest.php`; existing `tests/Feature/DashboardTest.php`, `resources/js/pages/Dashboard.spec.ts`, source-contract consumers, architecture/type/format and candidate-bound CI. Application S13 data check asserts action/count/date/time/visibility fields; browser measurement owns geometry/focus/keyboard. Update `database/seeders/DemoSeeder.php`/`tests/Feature/Database/DemoSeederTest.php` for combined-role and truly empty examples if absent. Review the integrated first slice against R1–R16/S1–S13 after merge.
- **First checkpoint:** failing public Application test with same-date send/reception/draft/collection and older collection; pass all-family sort/dedup, inspect Inertia props/rendered list, then remove interim cards.
- **Dependency/safety:** T02–T05 supply every work/opportunity family and T06 supplies failure semantics; partial global caught-up would misstate work. ~380–600 authored lines plus separately reported interim deletions, low confidence. Atomic final cutover with checkpoints: Application union, HTTP links/flags, UI removal, measured states. No flag or later insights.
- **Shared visual and integration checks:** Use real Application adapters and authenticated HTTP/Inertia assertions; test doubles alone do not prove integration. Measure each affected populated role at 375/1280 px, light/dark, 100% text; distinct layouts at 1024 px/200% text and unique empty/error/long-name states at their riskiest settings. Verify keyboard/focus, readable text counts/actions, ≥44 × 44 px targets, no horizontal overflow or color/hover-only meaning. Use the committed visual fixture/manifest and DOM measurements before optional previews; retain independent task review and candidate-bound CI.
+++
