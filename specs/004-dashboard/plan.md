# 004 — Dashboard: attention and opportunities · Plan

**Status: Accepted in Q23 (2026-11-11).** [Spec](spec.md) R1–R16/S1–S13 was accepted in Q22.
Q23 accepts this design; implementation and task breakdown remain separate decisions. It replaces the
interim content owned by 001 T61 when delivered. Retired 002 T01–T04 remain retired; shipped 002 T05/R11 login
routing remains its own work.

## Readiness for this plan

| Concern | State | Evidence and limit |
| --- | --- | --- |
| Behavior | Ready | 004 spec Q22 accepts R1–R16/S1–S13 and states no open product question. 001 glossary/workflow and 003 powers govern eligibility and access. |
| Decisions | Ready | Q14 selects this first slice; Q20 excludes manual closure, Q21 refines reception stages, Q22 accepts exact first-slice rules. No additional product decision is proposed here. |
| Design | Accepted in Q23 | Contracts, state flow, navigation, failure behavior and verification are below. UI reuses the existing dashboard shell, role navigation and component/tokens. |
| External prerequisites | Readiness check before implementation | Source inspection confirms the existing 001/003 routes and role readers. Read-only Linear status on 2026-11-11 showed 001 DRA-183/DRA-206 and 003 DRA-248–255 Done; status alone is not feature acceptance. Confirm 001 feature closure and 003 delivered behavior on the implementation base. No new external service, package or credential. |
| Paths | Proposed and source-backed | Existing entry/destinations and candidate readers are named below from worktree source; new Dashboard module and narrow read queries are planned. A fresh implementation-base check is needed before work starts. |
| Verification | Planned | Application scenarios, HTTP/Inertia/authorization, frontend interaction, seeder fixture and measured responsive/accessibility checks below. No implementation check is claimed to have passed. |

## Existing pathway and boundary

`GET /dashboard` is already under `auth` and `verified` middleware (`routes/web.php:63–65`).
`DashboardController::show` currently combines member cycles and collection, staff collection counts, manager
totals and `RoleAreas::shortcuts` into `Dashboard.vue`
(`app/Http/Controllers/DashboardController.php:22–101`). This is the 001 T61 summary, not the accepted unified
list. Preserve the endpoint, login destination and authorized role shortcuts. Existing authorized destinations
include member `/cycles/{cycle}/order` and `/baskets[/{basket}]`, staff `/finance/baskets…`, reception
`/receptions/{cycle}/{producer}`, manager cycle/manual-send pages, producer `/producer/cycles/{cycle}` and
Catalogue `/catalogue/producers…` (`routes/web.php`). Direct screens continue to authorize their own current
operations.

This slice has no migration, write command, scheduler, notification, chart or polling. Add a `Dashboard`
**read-composition context** (planned) to `architecture/modules.php`; it owns no table and receives the
entry-point `Actor`. `src/Dashboard/Application/ListDashboard.php` (planned) authorizes that actor using
current gates and chooses allowed staff/member/producer scopes from its current identifiers. Its
`src/Dashboard/Domain/{OrderWorkRepository,BasketWorkRepository,OrderOpportunitiesRepository}.php` read ports
(planned) take only those explicit scopes and no actor. Implement them in
`src/Dashboard/Infrastructure/{Ordering,Sales}/` adapters (planned) that call narrow, actor-free internal
Application queries exported by the source modules; adapters map source DTOs to Dashboard-owned
`Domain/ValueObject` projections. Source contexts decide action eligibility and expose only the selected
scope's facts; Dashboard Application composes them, removes duplicate *action/record* identities, groups and
orders them, and returns `src/Dashboard/Application/{DashboardData,AttentionGroupData,OpportunityData}.php`
DTOs (planned). `DashboardController` calls one `ListDashboard` query and maps destination identifiers to
existing route URLs; it neither selects records nor computes eligibility. Vue renders generated DTO types and
translations, formats cooperative-local dates, and dispatches navigation or refresh only. Update exact
manifest dependencies/exports and generated TypeScript types (constitution A1–A17, especially A4/A5/A10–A17).
Avoid importing foreign Domain or Infrastructure into Dashboard Application, direct cross-owner table reads,
Eloquent in Application, or business logic in a controller/DTO/Vue.

## Read contracts and ownership

| Owner / proposed contract | Authorized facts returned | Rule |
| --- | --- | --- |
| Ordering `src/Ordering/Application/ReadDashboardOrderWork.php` *(planned internal, no actor)* | A scoped read for Manager manual-send work and Receiver/Finance/Manager reception work: producer/cycle identifiers, names, scheduled date, sent-order identity, current reception stage and allocation-balance fact. Scope names are explicit, e.g. `manualSend` or `reception`, chosen only after Dashboard's entry-point gate check. | R5–R6/R9/R12–R14. Manual send requires a participating, non-empty, **unsent** order, producer ordering closed and a notifyable account under the *same* 001 R12a rule. Reuse `Cycle::producersReadyToSend` plus `SendableTotals` path used by `ListCycles` (`src/Ordering/Application/ListCycles.php:12–38`), not a cutoff heuristic. For receptions, reuse `ReceptionLoader::load` as a read-only calculation or a batch equivalent; it constructs a snapshot without persisting (`src/Ordering/Domain/ReceptionLoader.php`). `ListReceptionCycles` has sent/validated/distributed state but no blocker flag. Delivered rule (Deviation `history/2026-11-11T21-32-32.759Z-DRA-260-645d434b-c504-4031-97fc-cd4ca2cb9823.md`): the account check is `ProducerNotifier::canNotify`, the rule `SendProducerOrder` enforces, which `ListCycles` does not apply. Never call a command or persist/open a reception on read; defaults and received-vs-basket variance do not prove allocation balance. |
| Ordering `src/Ordering/Application/ReadDashboardOrderOpportunities.php` *(planned internal, no actor)* | Member scope takes the current household ID and returns open ordering windows with applicable cutoffs and destination cycle IDs; producer scope takes only the current producer ID and returns its sent summary destinations. | R1/R11/R12/S2–S3/S10. Reuse `ListMemberCycles` rules and existing cutoff/producer order rules inside Ordering, without invoking its actor-gated UI query as a forged actor. Do not infer submission obligation from a saved order. No household names in producer output. |
| Sales `src/Sales/Application/ReadDashboardBasketWork.php` *(planned internal, no actor)* | `__invoke(householdId)` returns only that household's pending collections and `__invoke()` all households' (T01; draft preparation/validation joins in T03 through its own query, see the T01 Deviation). Each includes basket UUID, source/cycle or optional pickup context, household display label where allowed, domain-derived action stage and destination ID. | R4/R7–R8/R14. Existing `ListMemberPendingCollections` and `ListPendingCollections` establish collection behavior and include closed/cycleless baskets (`src/Sales/Application/…`); the internal query reuses their repository/domain rule without forging an actor. The narrow projection uses owning Basket's effective-content and eligibility rules, not amount, `totalCents`, cycle state or active-household filtering. `ListBaskets` is broad detail, not a dashboard contract. |
| Dashboard `ListDashboard(actor)` | `attention[]`, `opportunities[]`, permitted shortcuts or existing shortcut prop, `lastUpdatedAt` instant and server-provided visibility flags; each attention group contains action code, context label/date, distinct-record count, accessible text, permitted destination and stable IDs. | R1–R3/R11–R16. The time is from `Clock` only after all source reads succeed. A role with no context gets explanatory no-context copy, not fabricated zero metrics. |

The entry-point Dashboard query authorizes the explicit `Actor` and may call an internal reader only with a
gate-approved scope and current household/producer ID. Internal source queries take no actor (A10), are not
web entry points, and are manifest-exported only to Dashboard adapters (A11). Source module queries still
enforce their own record eligibility; Dashboard owns role selection and union. Shared staff work is included
without assignment. Manager inherits its accepted Finance/Catalogue/Receiver capabilities, while Receiver
never receives distribution work and Catalogue-only never receives stock or reception pending work. Neither
membership history nor another producer's records leak into output. Existing `RoleAreas::shortcuts` remains
governed by its current role mapping, including 003 R15.

## Action classification, grouping and priority

- Emit one current action per underlying record at a time for each workflow stage. Reception: unvalidated →
  `check_delivery_and_allocations` (with `allocation_difference` explanation if blocked); validated with
  blocking allocation difference → `resolve_allocations`; validated and balanced → `mark_distributed` for
  Finance/Manager only; distributed → absent. Receiver sees only the first or resolution work it can perform
  and no validate/distribute claim. A received-total edit that clears validation reclassifies on next read.
  Compare allocation totals with received totals using the existing R18 gate, not variance against basket
  actuals (R5–R6/S5–S6).
- Draft basket with at least one active line (`Basket::canBeValidated()`, the condition `validated()`
  enforces) → `validate_basket`. `prepare_basket` stays in the contract but is unreachable under 001
  R19a/R22 (Deviation `history/2026-11-11T21-10-37.278Z-DRA-259-7a081a1b-4086-460a-be3a-94358cd86276.md`). Exclude validated, cancelled, effectively empty/no-collection cases according to
  existing Sales rules. Monetary zero is not an exclusion. Validated, effective, uncollected basket →
  `collect_basket` for member or Finance/Manager. Include late, closed-cycle, inactive-household staff
  and cycleless (including independent stock) baskets. Wallet balance never gates collection
  (R4/R7–R8/R14/S4/S7).
- Canonical dedup key is `(action code, owning record type, stable record key)`, before grouping. Use basket
  UUID for basket actions and `(cycle UUID, producer UUID)` for producer sending and reception actions; an
  unopened reception has no persisted reception UUID to rely on. A combined member/Finance account's basket
  collection therefore appears once even if both scoped reads emit it. Distinct actions on the same record
  retain separate keys. Group key is `(action code, cycle UUID)` when a cycle context exists, otherwise
  `(action code, scheduled date or explicit no-cycle context)`; an independent stock basket has no cycle
  attribution and takes the no-cycle context, and no cycle's state is an eligibility filter. Count distinct
  stable record keys, never sum staff and member counts. Show a permitted compact cycle/date and producer or
  household context with count; use the existing authorized task list for multiple records, and detail for one. Do not expand an
  unbounded list of names into a row. A group destination is selected from the actor's permitted view (staff
  list for mixed staff collection). If a list lacks an exact filter, it still exposes the records for
  inspection; no new action route is needed (R2–R3/R14/S1/S4).
- Sort pending groups first by existing actual deadline (overdue then upcoming), then by scheduled pickup date
  ascending, undated last. Pickup dates do not create deadlines, and a passed ordering cutoff is not a
  manual-send deadline. For equal dates use stage rank send → reception → basket draft/validation →
  collection, then locale-aware displayed context label, action code, cycle/context ID, and final record/group
  ID. Stable IDs break name ties so a rename cannot change identity. Sort opportunities separately by fixed
  kind rank and stable context ID; do not place them among pending. Keep all cycles, including overlapping and
  closed, until underlying action eligibility changes (R12–R14/S8–S9).
- Opportunities are open member ordering, own producer sent-summary review, Catalogue maintenance and the
  permitted existing role-area shortcuts. An inactive product or sent producer summary is never pending merely
  for existing. No manual close-cycle or empty-order scheduler item (R9–R11/S7/S10).

## HTTP, freshness and page states

Keep the dashboard route. Return generated typed sibling props `attention`, `opportunities`, `lastUpdatedAt`,
`areaShortcuts` (from `RoleAreas::shortcuts`; it replaced the planned `navigation` prop), `readFailed`, and `can` for the dashboard sections, delivered together on each read. The server
sets `can.showCaughtUp` only after a successful empty read and `can.showNoContext` from current permitted
contexts; Vue does not infer business state. Action and context kinds use backed Domain enums, rendered
through translation keys rather than Vue state/kind comparisons. A typed
`src/Dashboard/Domain/Error/ReadUnavailable.php` (planned) represents a known failed source read. The HTTP
adapter maps it to `readFailed: true`, null section data and null `lastUpdatedAt` on an initial GET, with
caught-up/no-context flags false. Success supplies section data and the successful-read timestamp with
`readFailed: false`. Authorization failures remain 403 and unexpected failures follow normal error handling;
neither is converted to empty success. All text comes from `lang/ca`, with action/kind translation keys rather
than literal Vue/PHP copy.

An initial visit makes a current server read. Explicit refresh uses Inertia's existing reload pattern
(`resources/js/pages/Manage/Roles/Index.vue:35–64`) for all dashboard section, timestamp, error and visibility
props together, preserving prior visible data while an accessible busy message announces the read. When
`readFailed` is true, the client retains the prior successful data and timestamp, marks them stale and offers
retry; with no prior success it shows only error/retry. A transport failure or non-success HTTP response
follows the same stale/error display without treating the response as success; 403 continues to remove
inaccessible content through the app's authorization/error path. The last-successful timestamp changes only
with successful data. Returning via a fresh Inertia visit reads anew; browser history/BFCache restoration
triggers one read on return rather than trusting cached props. Do not poll while the page stays open, flash
the layout for a fast read, or move focus unexpectedly. Current destination authorization and workflow guards
resolve stale links (R3/R15/S11–S12). Render the timestamp in the cooperative timezone and keep its source
instant unambiguous across DST.

`Dashboard.vue` stays a composition page. Use its existing layout, `resources/js/components/ui/` button/card
primitives, shared links, semantic tokens and current role navigation; a small presentational row component is
justified only if the repeated attention/opportunity row actually needs it. Preserve text action, count,
context/date, status and focus indication at 375 px and 200% text, light/dark, with keyboard order and ≥44 ×
44 px targets. No color/hover-only meaning, horizontal overflow or chart (R16/S13). Prepare an exact
component/route/state inventory before implementation; any new visual direction follows the project's explicit
visual-design approval gate.

## Verification and demo evidence planned

- Test-first Application scenarios S1–S12 through the public Dashboard query and owning source projections:
  mixed-role union/collection dedup; current household move and producer privacy; member open ordering plus
  validated collection; closed/late/cycleless basket; unopened and default reception read
  without persistence; blocking allocation vs basket variance, validated distribution and Receiver exclusion;
  effective draft vs zero amount, cancellation and automatic closure absence; manual-send exact eligibility
  and scheduler exclusion; cross-cycle/date/stage/tie ordering; no-work opportunities; refresh/role changes.
  Cover each R1–R15 with the owning query and composed result. Reuse existing domain checks rather than mirror
  their calculations in dashboard tests.
- HTTP/Inertia tests verify guest/auth route, actor-specific typed props and permitted URLs, direct
  destination 403/current-state behavior, and failed/unknown read behavior. Frontend tests cover refresh
  callbacks, return/BFCache read, last-successful time, busy/error/retry, caught-up/no-context copy,
  translated labels and keyboard-visible links. Verify idle page makes no requests and dashboard causes no
  state-changing request. Existing `tests/Feature/DashboardTest.php` covers only guest redirect and component;
  add meaningful integration assertions. Keep architecture/module contract and generated-type checks.
- Check `database/seeders/DemoSeeder.php` against each visible role/action state. Its existing actors and
  unfinished reception are a starting point; add missing representative eligible manual-send, balanced
  validated distribution and mixed/empty context fixtures as needed, with matching assertions in
  `tests/Feature/Database/DemoSeederTest.php`. No change to a visible data class should be left unrepresented
  without a stated reason.
- S13/R16 presentation is owned by rendered browser verification plus focused Vue interaction assertions; the
  Application-level S13 check asserts action codes, counts, dates and refresh-state data sufficient for the
  page to translate and render, not geometry. Follow `skills/agent-workflow/references/visual.md`:
  representative populated role views at 375/1280 in light/dark, distinct layouts at 1024/200% text, and
  riskiest empty, error, long-name and focus states. Use DOM/text/measurements first; optional small preview
  only when the visual gate calls for it. Run focused PHP/Vue tests, type generation/check, formatting,
  static/architecture checks and candidate-bound CI in the eventual implementation worktree. These are planned
  checks, not current results.

## Limits and acceptance boundary

This plan covers only accepted 004 R1–R16/S1–S13. Later insight/reporting slices, reminders, payments,
invoices, assignments, analytics and new workflow state remain outside it. Q23 settles these read contracts and UI states; executable task allocation, implementation authorization and feature
acceptance remain separate workflow gates. The principal implementation risks to check early are source
read-only behavior for unopened receptions, exact Sales draft eligibility, current actor changes on return,
and no cross-module/table ownership violation. No unresolved product question is identified.
