# 001 — Weekly orders · Tasks

Status: Accepted (2026-10-21) · Spec: [spec.md](spec.md) + areas · Plan: [plan.md](plan.md)

Accepted amendment (2026-10-24): **Application visual identity — T39–T42** and the added T33
dependency. Corresponding Linear tickets have been created. On 2026-10-25 the user authorized T39
implementation only. The T39 visual pilot was explicitly approved on DRA-191 on 2026-10-25;
T40 is implemented with focused and rendered evidence. T41 is implemented for independent review
with [rendered manager evidence](../../docs/visual-checks/dra-192.md); T42 is implemented for
independent review with [rendered settings/authentication evidence](../../docs/visual-checks/dra-193.md).

Accepted amendment (2026-10-26): **Layout rules — T43–T46**, tracked in Linear as DRA-197–DRA-200.
Implementation is not yet authorized.

How to work a task:

- One task = one vertical slice: domain → use case → repository (in-memory + Eloquent) → HTTP → Vue page,
  whatever the task needs. Each task ends in one commit.
- Test first: write the listed scenarios as failing Application tests, then make them pass.
- **Done when**: its scenarios and rules pass at the level stated, architecture tests (T02) stay green,
  and the whole suite passes. Once T36 is done, every text a task adds has a `ca` translation (the translation
  test stays green), and every page it adds or changes is checked at 375 px wide (constitution, UI).
- A task depends on the tasks listed in *After*; anything else can go in any order.
- A scenario split across tasks lists its part, e.g. "S5 (snapshot)"; the last task finishes it.

## Phase 0 — Foundation

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T01 | Bootstrap: Laravel 13 Vue starter kit, Postgres (`guixa`, `guixa_test`), `Guixa\` → `src/`, public registration off, scheduler, Pest + Vitest running, Pint + Larastan + Vite+ (Oxlint, Oxfmt), GitHub Actions CI, mail (`log` in tests, Mailpit in dev) | Constitution | — |
| T02 | Architecture tests A1–A8 in `tests/Architecture` (loop over contexts) and Oxlint rules for A9 | A1–A9 | T01 |
| T03 | `Shared`: `Money` (half-up `multiply`), `Quantity` (thousandths, `ceilToWhole`), `Clock` with the cooperative's timezone (`SystemClock` from `COOP_TIMEZONE`, `FrozenClock`), `LocalTime`, `TransactionRunner` (with `afterCommit`) | R22 rounding, Time | T01 |
| T04 | `Actor` with several roles from the authenticated account, `role:<role>` middleware, domain error → 422 and `NotAllowed` → 403 handler, shared Inertia layout with a switch between role areas | Roles, R36a | T01 |
| T05 | Test support: in-memory repository base and contract-test pattern shared with Eloquent repositories | Plan · Testing | T03 |
| T36 | Remaining app-wide translation migration: pages, layouts and components not covered by T37, email subjects/bodies, Laravel framework Catalan messages, remaining domain errors, `ca-ES` formatting and exhaustive translation coverage | Constitution (UI, *Translatable*), Plan · Translations, D18 | T38 |
| T38 | Shared translation foundation: validate `laravel-vue-i18n` with Inertia 3/Vite+ (D18 fallback if incompatible), shared `lang/ca.json` Vue/Vite loading, Laravel locale defaults and shared locale, `ca-ES` formatters, focused PHP/Vue translation tests | Constitution (UI, *Translatable*), Plan · Translations, D18 | T04 |

**Done when** T01: app boots on Postgres, the starter tests pass, lint and Larastan are clean, and the
CI workflow is green on the T01 pull request. T02: a deliberate violation in a
scratch class makes each rule fail, then is removed. T03: unit tests for rounding (e.g. 1.120 × €20.00 = €22.40),
negative quantity rejected, string ↔ thousandths mapping, and `LocalTime` on both daylight-saving days.
T36: every existing page, email and error message shows Catalan, each page also checked at 375 px wide; the
translation test fails on a deliberately untranslated text, which is then removed.

## Phase 1 — Membership

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T06 | `CreateHousehold`, `RenameHousehold`, `AddMember` + invitation email, `AcceptInvitation`, `ResendInvitation`, `ChangeInvitationEmail`; login refused until accepted; 7-day expiry; households admin pages | R33, R34, S25, S56 | T04, T05 |
| T34 | `CreateFirstManager` (rejected once any manager exists) and the `guixa:create-manager` Artisan command, sending the usual invitation (D17) | R36a (first manager) | T06 |
| T07 | `MoveMember`, `UnlinkMember`, `GrantManagerRole`, `RemoveManagerRole`, `DeactivateAccount`, `ReactivateAccount`, accounts admin pages | R35, R36a, R36b, S43 (move), S44, S59 | T06 |

**Done when** T07: two concurrent `RemoveManagerRole` calls on the last two managers (integration test)
leave one manager.

## Phase 2 — Catalog

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T08 | `Producer` + `CutoffRule` (1–6 days), `CreateProducer`, `InviteProducerAccount`, `SetDefaultCutoff`, producers admin page and producer cutoff page | R5, R36 | T06 |
| T09 | Product CRUD by its producer (price ≥ 0, containers per unit), `ListProducerProducts`, producer products pages | R1 (fields), R2, S57 | T08 |
| T10 | `GetOrderableProduct`, `ListOrderableProducts`, `GetProducerCutoffRule` (internal queries) | R3 (catalog part) | T09 |

**Done when** T08: `CutoffRule` unit tests in two timezones (e.g. `Europe/Madrid`, `America/Mexico_City`),
including the weeks when daylight saving time changes (S38).

## Phase 3 — Cycles and ordering

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T11 | `SetPickupWeekday`, `Cycle`, `OpenCycle` with participants (cutoffs from rules, none if passed; asks for the pickup weekday, permanent or provisional, while the cooperative has none), `AddParticipant`, cycles admin page and settings page; cycle picker on the stock adjustment form (R27) | R4, R4a (adding), R4c, R5, S6 (opening), S37 (weekday), S38 | T10 |
| T12 | `SetCutoff`, `CloseProducerNow`, `ClearCutoff`; open until the cutoff or the pickup date at 00:00 | R6, R6a, S6, S34 (move), S35 | T11 |
| T13 | `Order` ordering part, `SetOrderLine` (snapshot with containers, `addedBy`), `GetHouseholdOrder`, `Orders/Edit` page with running total | R7–R11, S1–S4, S5 (snapshot), S33, S49, S51 | T12, T07 |
| T14 | `ListMemberCycles`, `Orders/Index` page: open cycles, pending pickups, cancelled and closed cycles | R4a (open for members), R23a (before settlement) | T13 |
| T15 | `SetProductAvailability`, *will not be delivered* derived on read, `RemoveMarkedLine`, product deactivation; unit and variable flag locked while the product has unsettled lines (`HasUnsettledLines`) | R1 (lock), R3, R3b, R3c, S5 (lock), S32, S48 | T13 |
| T16 | `StopParticipating`, `ParticipateAgain`, `DeactivateProducer`, `ReactivateProducer` (producer role, participation where not sent) | R3a, R6c, S50 | T15 |
| T17 | `CancelCycle` (orders deleted, cycle shown as cancelled) | R4b (cancel before sending), S37 (cancel) | T13 |
| T18 | `SendProducerOrder` + producer email, cutoff frozen once sent, marked lines stored as not delivered, `SendEmptyProducerOrders` scheduled command (sends only) | R6b, R12a (sending), R4b (rejected once sent), S34 (frozen), S36, S37 (sent), R6c (refused once sent), R3a/S50 (sent order unchanged on deactivation) | T16 |
| T19 | `GetProducerCycleSummary`, `Producer/CycleSummary` page with availability per product | R12, S7, S8 | T18 |
| T20 | `GetHouseholds` (Membership) behind Ordering's `Households` port, `GetCycleSummary` (totals per producer and product, per household), `Manage/Cycles/Show` page | R13, S52 | T13 |
| T35 | `GetCycleSummary` carries `cancelled` and returns no totals for a cancelled cycle; `Manage/Cycles/Show` shows a "Cancelled" notice instead of totals | R13 (cancelled), S62 | T17, T20 |
| T37 | Behavior-preserving member ordering redesign: `Orders/Index`, `Orders/Edit`, presentational ordering components and necessary ordering navigation/layout; Catalan translations, 375 px layout, labelled 44 px controls; preserve per-line save, totals and existing states | Constitution (UI), R4a, R4b (cancelled), R7–R11, R3b; existing T13–T15/T17 scenarios | T14, T15, T17, T38 |

**T37/T38 status (2026-10-24):** both are Done after approved retrospective reconciliation.
T38 = [DRA-194](https://linear.app/draude/issue/DRA-194/t38-shared-catalan-translation-foundation)
in **Phase 0 — Foundation**, after T04 / DRA-153.
T37 = [DRA-195](https://linear.app/draude/issue/DRA-195/t37-behavior-preserving-member-ordering-redesign)
in **Phase 3 — Cycles and ordering**, after T14 / DRA-168, T15 / DRA-169, T17 / DRA-170 and T38 / DRA-194.
These are independent root issues with native dependencies, not children of T39. Their original
scopes remain unchanged; the remaining app-wide translation migration stays in T36.

**Done when** T38: Vue and PHP resolve the same Catalan source keys and placeholders; the
Inertia/Vite+ compatibility build and focused translation tests pass. No new language-selection UI.

**Done when** T37: the plan's Member ordering presentation checks pass, including actual 375 px and
desktop inspection, light/dark appearance and keyboard use. Existing save/remove requests, drafts,
totals, cancellation and disabled behavior remain unchanged; touched UI copy comes from `lang/`.
Record focused regression results and browser evidence before completion; no new feature scenarios.

**T38/T37 reconciled implementation evidence (2026-10-24):** current commits are T38 `3356bc5`
plus locale regression correction `b679ade`, T37 cycle list/navigation `b4f1a60` and editor `0a9e26d`.
All are ancestors of local merged HEAD `2553943b1de93bdd6eaf49bf44927649a0162b9a` (PR #193).
Relevant application/test/build-config source is unchanged between `0a9e26d` and that HEAD.

Fresh reconciliation checks: five frontend suites (`i18n`, `orderRows`, `orderTotal`, `Orders/Index`,
`Orders/Edit`), **17 tests passed**, independently repeated by the parent with the same result.
Historical evidence, not rerun during reconciliation: 40 isolated PostgreSQL HTTP/role-access/
translation tests (275 assertions), types/lint, affected PHPStan, client/SSR builds, and synthetic
browser fixtures at 375 px light / 1280 px dark with keyboard/save/remove, empty/error and 200% text.
The subsequent stack correction passed 55 affected PHP tests / 340 assertions and locale regression.
The prior delivery verification recorded PRs #191–#193 merged with passing CI; this reconciliation
did not re-query remote CI or rerun backend, builds or browser inspection. Frontend tests alone do
not prove browser layout or interaction. Original native-review evidence belongs to the original
pre-rebase snapshots retained in `log.md`, not a new review of these rebased commits.
T36 remains pending; neither these Done records nor their prerequisite links authorize T39 or close
the whole feature. Reconciliation documentation is local and pending repository publication.

**Done when** T13: two concurrent `SetOrderLine` calls for a household with no order yet (integration test)
create one order with both lines.
T18: a `SetOrderLine` racing a `SendProducerOrder` either lands before the send or is rejected.
T18: S50's clause "cycle B (order sent) ... B is unchanged": deactivating a producer leaves a sent producer order participating, and stopping it by hand is refused.

## Phase 4 — Reception and distribution

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T21 | `ProportionalAllocator` domain service (tie by creation, then line id) | R15, S10–S12 (math), S31 (math) | T03 |
| T22 | `ReceivedTotal`, `GetReception`, `AddUnorderedProduct`, `SetReceivedTotal(overwriteManual?)`, `SetAllocation` (allocation-only lines, active household only), `Manage/Reception` page with the overwrite/cancel warning; lines marked not delivered are left out of claims and the reception | R14–R17, S10–S12, S13 (balance), S31, S39, S40 (allocation) | T18, T20, T21 |
| T23 | `ValidateReception`, `MarkProducerDistributed`, `ReopenProducerOrder` before settlement, `CycleSettlement` (drop lines, pending or nothing-to-collect pickups); `SendEmptyProducerOrders` also validates and distributes, settling through `CycleSettlement` (D10); receptions read-only when distributed | R12a (empty orders), R17a, R18 (before settlement), R24, S13, S20, S27 (settlement), S28, S29, S40, S47 | T22 |

**Done when** T21: property test — for random claims and targets, Σ allocations == target, every allocation
is a multiple of the step, and no household gets more than one step above its exact share.

## Phase 5 — Stock

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T24 | `StockProduct` CRUD (price ≥ 0, containers per unit), `ReturnItem` CRUD, `Manage/Stock` and `Manage/ReturnItems` pages | R25 | T04, T05 |
| T25 | `RecordStockEntry`, `RecordStockAdjustment` (optional cycle), `ListStock`, `GetStockOffer`, `GetReturnItem`, `SellStock` (append-only movements, never below 0, row lock) | R26–R28, S23 | T24 |

## Phase 6 — Wallet

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T26 | `Movement` (top-up, charge, credit, correction), `RecordTopUp`, `RecordCorrection`, `ChargeForPickup`, `GetBalance`, `GetWalletStatement`, `Wallet/Statement` page and manager top-up/correction forms | R29–R32, S24 | T06 |

**Done when** T26: the partial unique index rejects a second charge or credit for the same household and cycle.

## Phase 7 — Pickup

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T27 | `GetPickup`, `OpenPickup` (stock-only pickup creates an empty order; active household, settled and not closed cycle), `AdjustDeliveredQuantity` (sets `delivered`, shown and flagged in the reception, D16), pickups only once settled, `Manage/Pickups/Index` and `Manage/Pickups/Show`; member sees the pickup once settled | R19, R21, R23a, S27 | T23, T26 |
| T28 | `AddStockToPickup`, `RemoveStockFromPickup`, `AddReturnToPickup`, `RemoveReturnFromPickup` | R20, R20a, S17 | T27, T25 |
| T29 | `CompletePickup(topUp?)`: freeze, sell stock, top-up, charge or credit, all in one transaction; the total uses `delivered()` and skips dropped and not-delivered lines | R21–R23, S9, S14–S16, S18, S19, S21, S22, S26, S41, S45, S53, S55 | T28 |
| T30 | `MarkPickupNotCollected`, closing the cycle, `ReopenProducerOrder` after settlement (done pickups kept, nothing-to-collect pickups reopened; both added to `CycleSettlement`), distributing again ignores pickup adjustments (`balancedForDistribution`), a new allocation clears a pending pickup's adjustment (D16), explicit unfinished-only/all redistribution with append-only done-pickup adjustment lines and wallet corrections, no reopen once closed | R18 (after settlement), R24a, R24b, S30, S42, S46, S54, S61, S63, S64 | T29 |
| T31 | `GetContainerCount` on `Manage/Cycles/Show` (`delivered()`, dropped and not-delivered lines skipped) | R18a, S60 | T29, T20 |

**Done when** T29: integration tests show that a failure while selling stock rolls back the charge and
the top-up, that two concurrent completions charge once, and that two pickups selling the same stock
products, added in opposite order, both complete.

## Phase 8 — Households

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T32 | `DeactivateHousehold` (warning first), `ReactivateHousehold`, automatic deactivation when the last member leaves, reactivation when `AddMember` or `MoveMember` links someone to an inactive household, `ListHouseholdsToSettle` (balances from Wallet, pending pickups from Ordering) | R35a, S43, S58 | T07, T30 |

## Application visual identity — T39–T42

**Status: Accepted — T39 approved for rollout; T40 implemented; T41 implemented for review (2026-10-25); T42 implemented for review (2026-10-26).** Implements the accepted
[Application visual identity](plan.md#application-visual-identity) amendment through the **plan only**
route. No new `R#` or `S#`: the references below identify existing behavior to preserve, not new
domain work. T37/T38 and their original scopes are unchanged; T36 remains separate.

### Linear tracking (2026-10-24)

Workspace `draude`, team `DRA`, project [Coop](https://linear.app/draude/project/coop-f80f57e1496d).
Milestone: **Application visual identity** (`cda681c9-a478-422a-9693-e45968fe4147`). The milestone
groups four root issues: no parent issue or redundant contextual relations were created.

| Task | Linear issue | Native blocked-by relation | Additional gate |
| --- | --- | --- | --- |
| T39 | [DRA-190](https://linear.app/draude/issue/DRA-190/t39-scoped-visual-theme-and-ordering-pilot) | DRA-195 (T37, Done), DRA-194 (T38, Done) | Visual pilot approved on DRA-191, 2026-10-25 |
| T40 | [DRA-191](https://linear.app/draude/issue/DRA-191/t40-visual-identity-for-dashboard-wallet-and-producer-screens) | DRA-190 | Explicit human approval of the T39 visual pilot; task status alone is insufficient |
| T41 | [DRA-192](https://linear.app/draude/issue/DRA-192/t41-visual-identity-for-existing-manager-screens) | DRA-191 | Preserve the approved rollout sequence |
| T42 | [DRA-193](https://linear.app/draude/issue/DRA-193/t42-visual-identity-for-settings-and-authentication-with-final) | DRA-192 | Final cross-role checks |

DRA-193 also blocks [DRA-183 / T33](https://linear.app/draude/issue/DRA-183/t33-traceability-check-and-close-feature-001).
Its original blockers DRA-187, DRA-182, DRA-174, DRA-181, DRA-168, DRA-170 and DRA-158 were preserved.
Readback confirmed the milestone, native dependencies and absence of duplicate T39–T42 issues.

At creation, all four issues were **Backlog**, unassigned, undelegated and without `ready-for-agent`.
This snapshot was not permission to execute; the later authorization applies only to T39.
T37/T38 reconciliation is resolved through their evidenced Done records and native prerequisite
links. T36 is not a prerequisite for T39. Completed prerequisites alone did not lift the original hold.
Issue descriptions flag that the approved SDD amendment is local and pending repository publication;
their repository-file links do not imply that this amendment has already been pushed to `main`.

### Existing-screen inventory

Paths below are relative to `resources/js/pages/`, based on tracked Vue pages on 2026-10-24.
This is a file inventory, not a claim that every flow has been exercised or every feature completed.
Before implementing each task, confirm the existing routes and reachable states without adding
missing functionality. Future screens inherit the visual system through their original feature tasks.

| Task | Existing pages to style and verify |
| --- | --- |
| T39 | `Orders/Index.vue`, `Orders/Edit.vue` and their shared shell (pilot only) |
| T40 | `Dashboard.vue`, `Wallet/Statement.vue`; `Producer/Cutoff.vue`, `Producer/CycleSummary.vue`, `Producer/Products/{Index,Create,Edit}.vue` |
| T41 | `Manage/Accounts/Index.vue`, `Manage/Households/{Index,Show}.vue`, `Manage/Producers/Index.vue`, `Manage/Cycles/{Index,Show}.vue`, `Manage/Settings.vue`, `Manage/Stock/{Index,Create,Edit,Movements}.vue`, `Manage/ReturnItems/{Index,Create,Edit}.vue` |
| T42 | `settings/{Appearance,Profile,Security}.vue`; `auth/{AcceptInvitation,ConfirmPassword,ForgotPassword,Login,ResetPassword,TwoFactorChallenge,VerifyEmail}.vue` |
| Excluded | `Welcome.vue`: no visual or routing change; verify it stays unchanged |

`Wallet/Statement.vue` is shared: T40 covers both its member view and existing manager controls;
T41 checks the manager navigation into it without duplicating its implementation.

Shared presentation candidates are `resources/css/app.css`, `layouts/AppLayout.vue`,
`layouts/app/{AppSidebarLayout,AppHeaderLayout}.vue`, `layouts/AuthLayout.vue`, `layouts/auth/*.vue`,
`layouts/settings/Layout.vue`, and the existing `AppShell`, `AppContent`, `AppSidebar`,
`AppSidebarHeader`, `AppHeader`, `Heading`, `AppearanceTabs`, navigation and UI primitives under
`resources/js/components/`. These are inspection targets, not a mandate to edit every file. Preserve
logo content, navigation destinations, role selection, authentication and all component behavior.

### Ordered tasks

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T39 | Implement the scoped theme and ordering pilot together: palette pairs, light/dark variants, heading typography, shell and existing order pages; include focused regressions and rendered evidence | [Plan · Visual system](plan.md#visual-system), [Delivery sequence](plan.md#delivery-sequence), Constitution (UI); R4a/R4b, R7–R11, R3b and existing T37 scenario coverage | T37, T38; task approval |
| T40 | Extend the approved theme to the existing dashboard, wallet and producer pages; verify both wallet roles, product forms, availability and cutoff presentation without changing their behavior | [Plan · Scope and invariants](plan.md#scope-and-invariants); existing T08, T09, T15, T19, T26 rules/scenarios (including S7, S8, S24, S57) and existing dashboard role access | T39 **and explicit visual pilot approval** |
| T41 | Extend the same theme to the existing manager pages, preserving data-entry forms, lists, confirmations and navigation; include their presentation and regression checks | [Plan · Scope and invariants](plan.md#scope-and-invariants); existing T06–T08, T11, T17, T20, T24–T25, T35 rules/scenarios, including cancelled-cycle S62 | T40 |
| T42 | Extend the theme to existing settings/authentication screens; preserve entry flows and appearance selection; finish the cross-role visual consistency and regression review | [Plan · Verification and review gate](plan.md#verification-and-review-gate), Constitution (UI); existing T06/T07/T34 membership and role rules/scenarios, plus existing authentication/settings checks | T41 |

Each task owns its presentation changes, applicable tests and browser evidence together. No new
domain/use-case tests are invented for colors. For a meaningful testable markup or interaction
contract, observe the focused failing test before changing it, then verify the existing behavior
still holds. Pure styling is verified by rendered checks; retain existing functional scenarios.
Use focused local checks and full CI validation as the plan specifies. A task is not complete while
applicable verification is failed, missing or pending; unavailable CI is reported, never assumed.

### Completion evidence and checkpoints

- **T39:** one reusable theme definition, attached only to pilot layouts; other screens and `Welcome`
  stay unchanged. Verify the five palette anchors, semantic state colors, readable foreground pairs,
  Instrument Sans body text and the initial Georgia heading proposal. Inspect 375 px/desktop,
  light/dark/system, 200% text, keyboard/focus, 44 px targets, and existing empty/error/closed/cancelled/
  marked-line states. Preserve save/remove/Enter requests, drafts, totals and preference persistence.
  Include menus, dialogs, popovers and toasts rendered outside the layout subtree: scoped tokens must
  reach in-scope overlays without leaking into excluded pages. Present screenshots and observed
  checks to the user; **stop before T40 until the visual pilot is explicitly approved**.

T39 implementation evidence (2026-10-25): [rendered pilot matrix](../../docs/visual-checks/dra-190.md),
focused frontend/HTTP regressions, scoped light/dark tokens and portal checks. The pilot is ready
for human visual review; approval was subsequently recorded on DRA-191. CI completion is separate.
- **T40:** reuse the same theme rather than a second palette; check every inventoried T40 page in
  both themes and at both widths, including wallet amounts, member/manager controls, producer forms,
  empty/error/disabled states and navigation. No price, quantity, balance or availability logic changes.

T40 implementation evidence (2026-10-25): [rendered page and state matrix](../../docs/visual-checks/dra-191.md),
test-first route opt-in, existing focused HTTP regressions for dashboard, both wallet roles, producer
cutoff/products (including CRUD), cycle totals and availability. The rendered matrix includes
every T40 view at 200% text and keyboard focus, both wallet roles, and a long product name.
It checks wallet cells against panel and clipping-ancestor edges, product cells against viewport
edges, and focused actions for horizontal visibility at enlarged text. Wallet and product rows
reflow by available container width when their columns cannot fit, preserving normal desktop tables.
Manager wallet forms also stack when their content width cannot fit both cards; rendered checks
cover card content, button text, and neighboring cutoff/product forms at enlarged desktop text.
The cycle summary stacks names above availability controls when its list width is constrained;
the rendered matrix covers all eight T40 views at 1024 px/200% in both themes with focus and
text/container bounds checks.
No new domain scenario was introduced. Full CI and
independent review are pending. The newly visible mobile row labels share Catalan translations with
their desktop headers; existing wallet amounts and producer prices remain unchanged.
- **T41:** check every inventoried manager page with the same visual/accessibility matrix; preserve
  existing field labels, validation, confirmation dialogs, cancelled states, permissions and action
  destinations. Recheck manager access to the shared wallet. Do not add reception/pickup pages or
  any other not-yet-implemented feature to satisfy the rollout.

T41 implementation evidence (2026-10-25): [rendered page and state matrix](../../docs/visual-checks/dra-192.md),
test-first route opt-in and responsive stock/return row labels, focused manager/cycle/wallet HTTP
regressions, and keyboard focus at enlarged text. The fourteen existing manager views use the
same theme boundary. Manager navigation from household detail to the shared wallet is checked;
wallet implementation remains T40. The first independent review found a filled cutoff truncated
at 200% text and a stock adjustment cycle select beyond its card. Focused red/green rendered and
submission checks cover the corrected controls in both themes; the same cutoff request value and
movement cycle ID are retained. A second review found insufficient dark cancellation-button
contrast; a portal-scoped foreground/background pair and normal/hover rendered assertions now
clear 4.5:1 in both themes. A third review found title/Close overlap at 200% text and
36 px footer targets at normal phone text; scoped portal spacing and target sizes now have
rendered geometry checks in both themes. No new domain scenario was introduced. Full CI and independent
review are pending.
The committed T41 visual manifest expands all fourteen manager views and applicable states
through the shared runner's full width/theme/text matrix. Its long-content case detected
and verified the Accounts household-select wrap at 1024 px/100% and fixed-column
stock/return desktop tables at 1280 px; shared report and gallery
stay in untracked task artifacts.
- **T42:** check every inventoried settings/authentication page with the same matrix, including
  validation, keyboard submission, password/2FA controls, invitation and verification states as
  already supported. Exercise light/dark/system selection and its existing persistence. Revisit the
  pilot and one existing page per role for shared-theme regressions, and verify `Welcome` remains
  unchanged. Record any inaccessible state or unavailable proof as pending, not passed.

T42 implementation evidence (2026-10-26): [rendered page and state matrix](../../docs/visual-checks/dra-193.md),
test-first route opt-in (`pilotTheme.spec.ts`: the three settings and seven authentication pages,
and the actual `AuthLayout` flag), focused authentication, settings and invitation HTTP regressions,
and acceptance checks outside the shared harness (contrast, 44 px targets, appearance persistence,
Enter submission, selected navigation, settings gutter, Welcome probe). The shared manifest declares
228 cases. It exercises the seven authentication pages, the three settings pages with member,
producer and manager navigation, and their dialogs (measured on the dialog itself), in both themes,
system appearance, reduced motion and fallback fonts. Its regression views cover Orders index and
edit (several row states), Dashboard, Producer Cutoff and manager Cycles at 375 and 1280 px in both
themes at 100% text, and 1024 px/200% in light; the three pages with forms add a 375 px/200% error
state in both themes, and Orders add 1024/200 and 375/200 variants. The fixture mounts real pages
and layouts with mocked transport. By user decision, a pre-existing T39 defect found here
(Orders/Edit product rows at 1024 px/200% text) is fixed in T42. No authentication or security
logic changed. Real passkey/WebAuthn ceremonies, the real 2FA QR code and server round trips are
pending (mocked transport). Full CI and independent review are pending.

For every task, apply all checks in the plan's verification section, including contrast, asset and
reduced-motion checks; record actual results per affected page and relevant focused test command.
Any added/changed copy uses `lang/`; these tasks do not absorb T36 or claim its completion. Optional
botanical decoration must stay within the accepted plan and must not delay a usable pilot.

**Execution gate:** the user supplied the T39 implementation instruction and then explicitly
approved its visual pilot on DRA-191 on 2026-10-25. T40, T41 and T42 are implemented for independent
review. Pilot approval does not grant publication or feature completion. T33 includes T42 as a
completion dependency.

## Layout rules — T43–T46

**Status: Accepted (2026-10-26); implementation not yet authorized.** Implements the accepted
[Layout rules](plan.md#layout-rules) amendment through the **plan only** route, as tracer bullets:
T43 takes one flow from rules to tokens to screens to rendered check; T44–T46 reuse it per area.
No new `R#` or `S#`.

### Linear tracking (2026-10-26)

Project [Coop](https://linear.app/draude/project/coop-f80f57e1496d), milestone **Application visual
identity**. All four issues were created in **Backlog**, unassigned and without `ready-for-agent`.

| Task | Linear issue | Native blocked-by relation | Additional gate |
| --- | --- | --- | --- |
| T43 | [DRA-197](https://linear.app/draude/issue/DRA-197/t43-layout-rules-tracer-on-member-ordering) | DRA-193 (T42, Done) | — |
| T44 | [DRA-198](https://linear.app/draude/issue/DRA-198/t44-layout-rules-for-dashboard-wallet-and-producer-screens) | DRA-197 | Explicit visual approval of T43 |
| T45 | [DRA-199](https://linear.app/draude/issue/DRA-199/t45-layout-rules-for-manager-screens) | DRA-197 | Explicit visual approval of T43 |
| T46 | [DRA-200](https://linear.app/draude/issue/DRA-200/t46-layout-rules-for-settings-and-authentication-screens) | DRA-197 | Explicit visual approval of T43 |

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T43 | Tracer: define the page, panel, form, list-row and action-row patterns, the type scale and the target rule; apply them to `Orders/Index`, `Orders/Edit` and their shell; add the static spacing check; present rendered captures for visual approval | [Plan · Layout rules](plan.md#layout-rules), Constitution (UI); existing T37/T39 ordering scenario coverage | T42 |
| T44 | Apply the layout rules to the T40 inventory (dashboard, wallet, producer pages); add them to the static check | [Plan · Layout rules](plan.md#layout-rules); existing T40 coverage | T43 **and explicit visual approval** |
| T45 | Apply the layout rules to the T41 inventory (manager pages); add them to the static check | [Plan · Layout rules](plan.md#layout-rules); existing T41 coverage | T43 **and explicit visual approval** |
| T46 | Apply the layout rules to the T42 inventory (settings and authentication pages); add them to the static check | [Plan · Layout rules](plan.md#layout-rules); existing T42 coverage | T43 **and explicit visual approval** |

Each task's pages are the matching row of the [Existing-screen inventory](#existing-screen-inventory)
(T39's row for T43). T44–T46 do not depend on each other and touch disjoint pages; a pattern change
they need in `resources/css/app.css` is coordinated so two slices do not edit the same rule at once.

**Done when (each task):** its pages use only the plan's spacing grid and patterns, the static check
covers its files and passes, every control is at least 44 × 44 px, and a visual check in
`docs/visual-checks/` records the rendered matrix (375 px, 1024 px/200% text, 1280 px; light and
dark) with the shared left edge and no horizontal overflow. Existing requests, props, validation,
permissions and translations are unchanged and their tests pass. T43 stops for explicit visual
approval before T44–T46 start.

## Closure

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T33 | Traceability check: every `R#` and `S#` is named in at least one test (`->group('S14')` or test name); fill the gaps; set feature 001 to *Done* in `specs/README.md` | R1–R36b, S1–S62 | T14, T17, T19, T31, T32, T34, T35, T36, T37, T38, T42 |

## Coverage

| Scenarios | Task | Scenarios | Task | Scenarios | Task |
| --- | --- | --- | --- | --- | --- |
| S1–S4 | T13 | S23 | T25 | S43 | T07, T32 |
| S5 | T13, T15 | S24 | T26 | S44 | T07 |
| S6 | T11, T12 | S25 | T06 | S45 | T29 |
| S7, S8 | T19 | S26 | T29 | S46 | T30 |
| S9 | T29 | S27 | T23, T27 | S47 | T23 |
| S10–S12 | T21, T22 | S28, S29 | T23 | S48 | T15 |
| S13 | T22, T23 | S30 | T30 | S49 | T13 |
| S14–S16 | T29 | S31 | T21, T22 | S50 | T16, T18 |
| S17 | T28 | S32 | T15 | S51 | T13 |
| S18, S19 | T29 | S33 | T13 | S52 | T20 |
| S20 | T23 | S34 | T12, T18 | S53 | T29 |
| S21, S22 | T29 | S35 | T12 | S54 | T30 |
| | | S36 | T18 | S55 | T29 |
| | | S37 | T11, T17, T18 | S56 | T06 |
| | | S38 | T11 | S57 | T09 |
| | | S39 | T22 | S58 | T32 |
| | | S40 | T22, T23 | S59 | T07 |
| | | S41, S42 | T29, T30 | S60 | T31 |
| | | | | S61 | T30 |
| | | | | S62 | T35 |
