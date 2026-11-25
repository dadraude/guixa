# 005 — Landing identity rollout: Tasks

**Status: Accepted (2026-11-13, PR #418).** Accepting it authorized no implementation or ticket publication; the ticket batch is drafted in [ticket-drafts.md](ticket-drafts.md). Source: [accepted spec](spec.md) R1–R13/S1–S12

**Slices 3-7 (T17–T45): Accepted (2026-11-16, PR #450).** Drafted from the accepted slice 3-7 spec and plan (PRs #447–#449). Acceptance authorized ticket publication (done: [linear-publication.md](linear-publication.md)) and starting C1 tasks T17 and T22; each later checkpoint waits for the user's approval of the previous one (R20). Slices 1 and 2 (T01–T16) keep their accepted status.
(slice 1 only); [plan](plan.md) (accepted, PR #417); [constitution](../constitution.md).
IDs are local to 005 and start at T01; 001 items are cited as "001 T84". This document authorizes no implementation, no
ticket publication and no Linear writes. Everything marked *unverified* was not run; this draft is from code reading
of the plan and spec only (worktree `origin/main`).

**Checkpoint C1 (T17–T23): approved (2026-11-19).** T23 (DRA-295) recorded the C1 acceptance and asked for visual approval (Q&A `history/2026-11-19T21-23-30.594Z-DRA-295-5b4045f8-9319-41c0-b8cc-a38da89b3c85.md`); the user approved the seven C1 pages and the received-total dialog in light and dark (Q&A `history/2026-11-19T22-22-49.544Z-DRA-295-18f726e1-6cd2-40fe-b0ff-b007f478fe5c.md`). C2 (T24) may start (S30).

**Checkpoint C2 (T24–T30): approved (2026-11-22).** T30 (DRA-306) recorded the C2 acceptance and asked the user for visual approval of the nine C2 pages and their overlays in light and dark (Q&A `history/2026-11-20T18-10-46.715Z-DRA-306-0e4e1132-8fe4-4c97-a9fe-bdeeb42d8012.md`; multi-role case left open in Deviation `history/2026-11-20T18-07-42.179Z-DRA-306-680247b1-c5dc-4677-92e6-5fd01d7c1bbb.md`; variant-off decision in Deviation `history/2026-11-20T18-07-42.128Z-DRA-306-f00cf996-da33-4bfc-849f-3bdd7a509bd3.md`). The user approved C2 with no page or mode named for correction (Q&A `history/2026-11-22T22-08-22.798Z-DRA-306-f02f624c-0841-49ac-b6d5-e9e4806f03e6.md`). C3 (T31) may start (S30).

**Checkpoint C3 (T31–T33): open, awaiting approval.** T33 (DRA-309) recorded the C3 acceptance and asked the user for visual approval of the four C3 pages and the statement select popup in light and dark. The current state (verdicts, remainders, owners, condition scope and approval question) is Q&A `history/2026-11-25T06-34-04.046Z-DRA-309-9c2b2c1e-8bc7-46c4-b6ca-051389aade5c.md`; it replaces those statements of the earlier T33 entries. Measurements are in Q&A `history/2026-11-24T23-17-16.266Z-DRA-309-983aa583-dc16-4ddf-a07a-499c26e60e2a.md` (diff argument as restated in Deviation `history/2026-11-25T06-25-35.636Z-DRA-309-6ab30c5d-69aa-4009-9f45-63db8babbc67.md`), unmeasured items in Deviation `history/2026-11-24T23-16-50.258Z-DRA-309-c745d845-2df1-4882-b8fa-f2bcb17e9e00.md`, and the demo seeder read in Deviation `history/2026-11-24T23-16-50.353Z-DRA-309-42c1ef9c-0265-4296-92e6-d5321f9e1867.md`, corrected in Deviation `history/2026-11-25T06-25-35.581Z-DRA-309-7898130f-286f-4c19-b3fd-35fb3ac7f5a2.md`. By the user's decision (for the R5, R6 and R9 items it named) and the record's reading of R20 (for the other unmeasured R5–R9 items), visual approval alone does not complete C3: it stays open until a new C3 task measures those items or the user accepts the gap (OQ5); no ticket for that task is published. No approval is recorded; C4 (T34) does not start until C3 is complete (R20, S30), and approval alone does not satisfy T34's "C3 approved" dependency.

## Architecture and safe sequence

A page joins the landing look only by exact name in `landingPages` (`resources/js/lib/pilotTheme.ts`); the shell and
portals already take the class (001 T84/T85). No Application behaviour changes, so the work per page is: admit the page
(contract in plan, "Contract for admitting a page"), measure R4–R9 for its declared states, fix only inside `.landing`
selectors of `resources/css/app.css`, flip the Vitest assertions, add the page spec where none exists. T01 is the tracer:
it creates the first fixture and `catalogue-landing-shared` manifest, re-points the negative control (D4), admits
`Catalogue/Producers/Index` and proves the first checkpoint. T03–T08 reuse that fixture and control. T02 (seeder) is
independent. T09 is the acceptance record and the visual-approval request; it writes no code unless a correction is
needed. A flag is not used: removing the `landingPages` entry is the reversible switch (contract item 7). Mixed look
between merges is accepted (spec OQ1); S1/R3 are verified only on the integrated base (T09).

**Slice 2 (Producer role complete, T10–T16).** Source: [accepted spec](spec.md) "Slice 2" R14–R19/S13–S18 and the
accepted slice 2 plan in [plan.md](plan.md) ("Slice 2 — Producer role complete · Plan", D6–D11 accepted by the user
2026-11-15). Same mechanism: one exact-name `landingPages` entry per page; the six Producer pages are already in
`pilotPages` (`resources/js/lib/pilotTheme.ts:10-15`), so no `pilotPages` edit is expected (*to confirm when reading*).
T10 is the tracer: it creates the `producer-landing` fixture and `producer-landing-shared` manifest, re-points the three
slice 1 probes that name Producer pages (D7) **before** any Producer page is admitted, measures the page-title size of the
six pages (D6), and admits `Producer/Products/Index`. T11–T14 reuse that fixture, each admitting its own page(s) with its own
manifest. T15 (seeder) is independent. T16 is the acceptance record and the visual-approval request; it writes no code
unless a correction is needed. The reversible switch is again the `landingPages` entry (D8: no single switch for six pages).

**Slices 3-7 (Rest of the application, T17–T45).** Source: [accepted spec](spec.md) "Slice 3-7" R20–R30/S19–S30 and the accepted slice 3-7 plan in
[plan.md](plan.md) (D12–D23). Five checkpoints in strict order, each ending in a closure task that asks the user for approval before the next opens (R20):
C1 Finance and reception (T17–T23), C2 Manager administration (T24–T30), C3 Wallet and member (T31–T33), C4 Sign-in and settings (T34–T39), C5 Dashboard,
errors and promotion (T40–T45). The first task of each checkpoint is its tracer (fixture, shared change, first admission): T17 also owns the select popup (D12),
T34 the auth shell variant (D15). T44 is the last step: the global promotion that removes the older opt-in, reversible as one change.

## Execution and evidence contract (all tasks)

- Tests first: write the failing check (Vitest admission/regression, or runner assertion with the failing value recorded),
  watch it fail for the right reason, then the minimum change. A test reading only a CSS class is not evidence of look.
- Measurement, not screenshots (`AGENTS.md` "Measure before screenshot"): visual runner default measurement mode,
  explicit `cases` not the Cartesian default, run bound to a fixed base SHA in ignored `.atl/<task>/visual`. One cropped
  image only for an unexplained failure, new direction or user request.
- Scope CSS only under `.landing` / `.dark .landing`; never `:root`, `public/theme-colors.css` or `.pilot-theme` base
  rules. No markup, class or prop change in a shared component (`MovementHistory`, `WeeklyProductFields`,
  `ProductAdministrationActions`, `ActiveSwitch`, `StatusIndicator`, `NativeDialog`) unless a shared-consumer probe shows
  unmigrated consumers unchanged. No PHP, route, prop, validation or `lang/` edit (R10), except T02 (seeder only).
- Every task runs unchanged the R10 guards that touch its pages: `tests/Feature/Catalogue/CatalogueAreaTest.php`,
  `ProducerAvailabilityTest.php`, `tests/Feature/Manage/ReturnItemsPageTest.php`,
  `tests/Feature/Stock/StockMovementHistoryPagesTest.php`, `tests/Feature/Http/RolePermissionMatrixTest.php`, plus
  `layout-rules.spec.ts` (8 px grid). Handoff states "no PHP behaviour changed".
- Closing checks per task: focused Vitest, `vp check --fix` (formats Markdown), type check, candidate-bound CI. Coverage
  ratchet: report the CI coverage total in the PR body; no `app/`/`src/` code is touched except T02 (*unverified*).
- **Acceptance record (profile "Project conventions").** Each task retains, in its handoff/PR, a slice acceptance record:
  each allocated `R#`/`S#`, task row, observable scenario, test assertion and result, reviewed commit, verdict and the
  named remainder owner. A passing test is evidence only for its assertions. Partial allocations say what is pending.
  T09 consumes these records for the feature-wide mapping.
- **Demo seeder (AGENTS.md).** T02 owns the seeder change. Every other task's handoff states "Demo seeder: see T02" or
  "no change" with the reason; none edits `DemoSeeder.php` (hotspot).
- History: each Deviation/Change/Q&A in its own `history/` file via `node .agent-workflow/scripts/history.mjs add`;
  commit with the change; never edit existing entries or `log.md`.
- UI reuse: compose only from `ui/button`, `ui/input`, `ui/label`, `ui/badge`, `NativeDialog`, `Heading`,
  `StatusIndicator`, `ActiveSwitch`, `InputError`, `MovementHistory`, existing `.pilot-*` classes under
  `resources/js/components/`. A new component or shared-markup change needs a measured gap recorded in the task handoff
  (`component_plan` per `skills/agent-workflow/references/visual.md`). No `ui/select` work (no Scope page uses one).
- Per-page "admission checklist" (plan contract, items 1–7), repeated by T01 and T03–T08 for their pages: exact-name
  entry; `pilotPages` membership; page-level measurement before the entry merges; Vitest lists flipped
  (`lib/pilotTheme.spec.ts`, `composables/usePilotTheme.spec.ts`, AppLayout loop with near-miss names showing `''`);
  control stays re-pointed; shared-consumer probe if shared CSS is touched; variant-off fixture state restores prior values.
- **Allocation convention for this draft.** Each page task owns R4–R9 and the R10/R11 guards for *its* pages (the plan's
  slicing table omits some of these per task; see Coverage gaps). Area-wide R3, S1, S2, S8 re-run, S9, S11 aggregate,
  S12 are completed only by T09.

## Canonical task registry

No Linear column: tickets are not published. "After" lists genuine prerequisites only.

| ID | Task | Acceptance owner | After |
| --- | --- | --- | --- |
| T01 | <a id="t01"></a>[Show the producer list in the landing identity, with admission contract and controls re-pointed](#t01-show-the-producer-list-in-the-landing-identity-with-admission-contract-and-controls-re-pointed) | R1/R3-start/R4–R9 for `Producers/Index`; R2 and S8 (first navigation states); S4 producers portion; R10/R11 guards; S2 Manager variant on this page | — |
| T02 | <a id="t02"></a>[Show the Catalogue states in the demo seeder](#t02-show-the-catalogue-states-in-the-demo-seeder) | R13 (complete); supports S4, S5 | — |
| T03 | <a id="t03"></a>[Show a producer's products and the classification dialog in the landing identity](#t03-show-a-producers-products-and-the-classification-dialog-in-the-landing-identity) | R1/R4–R9 for `Products`; S4 products portion; S7 dialog on `Products` + toast; shell overlays on a Catalogue page | T01 |
| T04 | <a id="t04"></a>[Show producer availability in the landing identity](#t04-show-producer-availability-in-the-landing-identity) | R1/R2/R4–R9 for `Availability`; S5 | T01 |
| T05 | <a id="t05"></a>[Show weekly product create and edit forms in the landing identity](#t05-show-weekly-product-create-and-edit-forms-in-the-landing-identity) | R1/R4–R9 for `WeeklyProduct/{Create,Edit}`; S3 weekly portion; S7 dialog on `WeeklyProduct/Edit`; S10 specs `Create`, `Edit` | T01; T03 (dialog styling) |
| T06 | <a id="t06"></a>[Show stock product create and edit forms in the landing identity](#t06-show-stock-product-create-and-edit-forms-in-the-landing-identity) | R1/R3 (D3)/R4–R9 for `Manage/Stock/{Create,Edit}`; S3 stock portion; S7 dialog on `Manage/Stock/Edit`; S10 spec `Stock/Create` | T01; T03 (dialog styling) |
| T07 | <a id="t07"></a>[Show the return-item list and forms in the landing identity](#t07-show-the-return-item-list-and-forms-in-the-landing-identity) | R1/R4–R9 for `Manage/ReturnItems/*`; S3 return-item portion; S4 table portion; S10 specs `Index`, `Create`, `Edit` | T01 |
| T08 | <a id="t08"></a>[Show the stock movement history in the landing identity](#t08-show-the-stock-movement-history-in-the-landing-identity) | R1/R4–R9 for `Catalogue/Stock/Movements`; S6; S10 spec `Movements` | T01 |
| T09 | <a id="t09"></a>[Record slice acceptance and request visual approval](#t09-record-slice-acceptance-and-request-visual-approval) | R3 (area-wide), R10, R11, R12; S1, S2, S8 (re-run), S9, S10, S11 (aggregate), S12 | T02–T08 |
| T10 | <a id="t10"></a>[Show the producer product list in the landing identity, with fixture and controls re-pointed](#t10-show-the-producer-product-list-in-the-landing-identity-with-fixture-and-controls-re-pointed) | R14/R15 (title measurement)/R16/R17 for `Producer/Products/Index`; S13 (start), S14 (list), S16 (toggle, reactivate busy and error), S17 (first states) | — |
| T11 | <a id="t11"></a>[Show the producer product forms and classification dialog in the landing identity](#t11-show-the-producer-product-forms-and-classification-dialog-in-the-landing-identity) | R14–R16 for `Producer/Products/{Create,Edit}`; R18 (specs `Create`, `Edit`); S14 (forms), S16 (dialog) | T10 |
| T12 | <a id="t12"></a>[Show the producer default cutoff in the landing identity](#t12-show-the-producer-default-cutoff-in-the-landing-identity) | R14–R16 for `Producer/Cutoff`; R18 (spec `Cutoff`); S15 (cutoff valid and invalid) | T10 |
| T13 | <a id="t13"></a>[Show the producer cycle summary in the landing identity](#t13-show-the-producer-cycle-summary-in-the-landing-identity) | R14–R16 for `Producer/CycleSummary`; R18 (spec `CycleSummary`); S15 (summary, availability toggle) | T10 |
| T14 | <a id="t14"></a>[Show the producer stock movement history in the landing identity](#t14-show-the-producer-stock-movement-history-in-the-landing-identity) | R14, R16 for `Producer/Stock/Movements`; R17; R18 (spec `Movements`, `layout-rules.spec.ts`); S16 (history) | T10 |
| T15 | <a id="t15"></a>[Show the Producer states in the demo seeder](#t15-show-the-producer-states-in-the-demo-seeder) | R19 (demo data); supports S14–S16 | — |
| T16 | <a id="t16"></a>[Record slice 2 acceptance and request visual approval](#t16-record-slice-2-acceptance-and-request-visual-approval) | R15 (area-wide, against slice 1), R17, R18, R19; S13, S17, S18 | T10–T15 |
| T17 | <a id="t17"></a>[Show the reception detail, the select popup and the received-total dialog in the landing identity](#t17-show-the-reception-detail-the-select-popup-and-the-received-total-dialog-in-the-landing-identity) | R21–R26 for `Receptions/Show`, `ReceivedTotalForm`; R24 (select, owner); S19 reception; S20; S22 total dialog; S26 start | — |
| T18 | <a id="t18"></a>[Show the reception list in the landing identity](#t18-show-the-reception-list-in-the-landing-identity) | R21, R22, R25, R26 (`Receptions/Index` spec) for `Receptions/Index`; S19 | T17 |
| T19 | <a id="t19"></a>[Show the finance basket list and detail in the landing identity](#t19-show-the-finance-basket-list-and-detail-in-the-landing-identity) | R21, R22, R25 for `Finance/Baskets/{Index,Show}`; S19 baskets (amounts to the cent) | T17 |
| T20 | <a id="t20"></a>[Show the new finance basket form in the landing identity](#t20-show-the-new-finance-basket-form-in-the-landing-identity) | R21, R22, R24 (reuse), R25, R26 (`Create` spec) for `Finance/Baskets/Create`; S19, S20 | T17 |
| T21 | <a id="t21"></a>[Show the finance stock list and movement history in the landing identity](#t21-show-the-finance-stock-list-and-movement-history-in-the-landing-identity) | R21–R23, R25, R26 (`Movements` spec) for `Finance/Stock/{Index,Movements}`; S19; S26 (`MovementHistory` probe) | T17 |
| T22 | <a id="t22"></a>[Show the Finance and reception states in the demo seeder, if a read shows a gap](#t22-show-the-finance-and-reception-states-in-the-demo-seeder-if-a-read-shows-a-gap) | R27 (C1 states); supports S19 | — |
| T23 | <a id="t23"></a>[Record checkpoint C1 acceptance and request visual approval](#t23-record-checkpoint-c1-acceptance-and-request-visual-approval) | R20, R27, R30 for C1; S19, S20, S26, S27, S30 for C1 | T17–T22 |
| T24 | <a id="t24"></a>[Show the roles and accounts pages in the landing identity, with fixture and probes re-pointed](#t24-show-the-roles-and-accounts-pages-in-the-landing-identity-with-fixture-and-probes-re-pointed) | R21–R26 for `Manage/Roles/Index`, `Manage/Accounts/Index`, `RoleHolderRemove`; S21 start; S22 holder removal; S26 start | T23 approved |
| T25 | <a id="t25"></a>[Show the households and the settlement details in the landing identity](#t25-show-the-households-and-the-settlement-details-in-the-landing-identity) | R21, R22, R24, R25, R26 (`Households/{Index,Show}`, `SettlementDetails` specs); S21; S22 settlement | T24 |
| T26 | <a id="t26"></a>[Show the producer table and the manager settings in the landing identity](#t26-show-the-producer-table-and-the-manager-settings-in-the-landing-identity) | R21, R22, R24, R25, R26 (`Producers/Index`, `Settings` specs); S21 | T24 |
| T27 | <a id="t27"></a>[Show the cycles and their dialogs in the landing identity](#t27-show-the-cycles-and-their-dialogs-in-the-landing-identity) | R21, R22, R24, R25, R26 (`CycleCreateDialog` spec); S21; S22 cycle dialogs | T24 |
| T28 | <a id="t28"></a>[Show the manager stock movements and the stock edit dialog in the landing identity](#t28-show-the-manager-stock-movements-and-the-stock-edit-dialog-in-the-landing-identity) | R21–R26 (`StockEditDialog` spec); S21; S22 stock edit; S26 (last `MovementHistory` probe) | T24 |
| T29 | <a id="t29"></a>[Show the Manager states in the demo seeder](#t29-show-the-manager-states-in-the-demo-seeder) | R27 (C2 states); supports S21, S22 | — |
| T30 | <a id="t30"></a>[Record checkpoint C2 acceptance and request visual approval](#t30-record-checkpoint-c2-acceptance-and-request-visual-approval) | R20, R27, R30 for C2; S21, S22, S26, S27, S30 for C2 | T24–T29 |
| T31 | <a id="t31"></a>[Show the wallet statement to every audience in the landing identity](#t31-show-the-wallet-statement-to-every-audience-in-the-landing-identity) | R21, R22, R24 (reuse), R25 for `Wallet/Statement`; S23 start; S26 start | T30 approved |
| T32 | <a id="t32"></a>[Show the member baskets and the stock list in the landing identity](#t32-show-the-member-baskets-and-the-stock-list-in-the-landing-identity) | R21, R22, R25, R26 (`Stock/Index` spec) for `Member/Baskets/{Index,Show}`, `Stock/Index`; S23 | T31 |
| T33 | <a id="t33"></a>[Record checkpoint C3 acceptance and request visual approval](#t33-record-checkpoint-c3-acceptance-and-request-visual-approval) | R20, R27, R30 for C3; S23, S26, S27, S30 for C3 | T31, T32 |
| T34 | <a id="t34"></a>[Show the auth shell and the sign-in cards in the landing identity](#t34-show-the-auth-shell-and-the-sign-in-cards-in-the-landing-identity) | R21–R23, R25, R26 (`Login`, `ForgotPassword`, `ResetPassword`, `AuthSimpleLayout` specs); S24 start; S26 start | T33 approved |
| T35 | <a id="t35"></a>[Show verification, confirmation, two-factor challenge and invitation in the landing identity](#t35-show-verification-confirmation-two-factor-challenge-and-invitation-in-the-landing-identity) | R21, R22, R25, R26 (four specs) for `VerifyEmail`, `ConfirmPassword`, `TwoFactorChallenge`, `AcceptInvitation`; S24 | T34 |
| T36 | <a id="t36"></a>[Show the profile, appearance and delete-account dialog in the landing identity](#t36-show-the-profile-appearance-and-delete-account-dialog-in-the-landing-identity) | R21, R22, R25, R26 (`Profile`, `Appearance` specs) for `settings/{Profile,Appearance}`, `DeleteUser`; S25 | T34 |
| T37 | <a id="t37"></a>[Show security, two-factor setup and passkeys in the landing identity](#t37-show-security-two-factor-setup-and-passkeys-in-the-landing-identity) | R21, R22, R25, R26 (`Security` spec) for `settings/Security`, `TwoFactorSetupModal`, `ManagePasskeys`, `PasskeyRegister`; S24 two-factor; S25 | T34 |
| T38 | <a id="t38"></a>[Show an account with two-factor in the demo seeder](#t38-show-an-account-with-two-factor-in-the-demo-seeder) | R27 (C4 state); supports S25 | — |
| T39 | <a id="t39"></a>[Record checkpoint C4 acceptance and request visual approval](#t39-record-checkpoint-c4-acceptance-and-request-visual-approval) | R20, R27, R30 for C4; S24, S25, S26, S27, S30 for C4 | T34–T38 |
| T40 | <a id="t40"></a>[Show the dashboard in the landing identity](#t40-show-the-dashboard-in-the-landing-identity) | R21, R22, R28 (dashboard) for `Dashboard`; S28 start | T39 approved |
| T41 | <a id="t41"></a>[Show the error page, the first paint and the tab icons in the landing identity](#t41-show-the-error-page-the-first-paint-and-the-tab-icons-in-the-landing-identity) | R28 (error page, first paint, tab icons); S28 | T39 approved |
| T42 | <a id="t42"></a>[Cover the welcome page and its transition to the app](#t42-cover-the-welcome-page-and-its-transition-to-the-app) | R26 (`Welcome` spec), R28 (transition); S24 (full), S28 | T39 approved |
| T43 | <a id="t43"></a>[Remove the unused layouts](#t43-remove-the-unused-layouts) | R29 (orphans); S29 start | T39 approved |
| T44 | <a id="t44"></a>[Promote the landing identity to the whole application](#t44-promote-the-landing-identity-to-the-whole-application) | R29 (promotion, inventory), R30; S29 | T40–T43 |
| T45 | <a id="t45"></a>[Record checkpoint C5 closure and request feature acceptance](#t45-record-checkpoint-c5-closure-and-request-feature-acceptance) | R20, R27, R30 for C5; S27–S30 | T44 |

**Current dependency frontier (nothing merged on this base): T01 and T02.** After T01 integrates: T03, T04, T07, T08
(and T05/T06 once T03 integrates; confirmed hard dependency, Q&A 2026-11-13). T09 needs all of T02–T08.

**Slice 2 dependency frontier (slice 2 spec and plan accepted; nothing of slice 2 merged on this base): T10 and T15.**
After T10 integrates: T11, T12, T13, T14 (mutually independent). T16 needs all of T10–T15. Slice 2 tasks do not depend on
slice 1 tasks being merged in code terms beyond the integrated base they start from (slice 1 T01–T09 are integrated and the
visual approval of 2026-11-15 is logged); slice 1 outcomes they reuse are Deviations T03, T06 (D3 outcome), T07, T09.

Parallelism and hotspots (scheduling, not dependency edges): `resources/js/lib/pilotTheme.ts` (every task adds one
or two array entries: serialize merges, rebase conflicts are mechanical); `resources/css/app.css` `.landing` block
(T03–T08 may each add rules: reserve a distinct commented sub-block per task, e.g. `/* 005 T03 … */`, to avoid
textual conflicts); `lib/pilotTheme.spec.ts` and `composables/usePilotTheme.spec.ts` (same: one row per page);
`fixtures/catalogue-landing*.ts/.html` (T01 creates; later tasks only add states and a per-family manifest of their own);
`fixtures/landing.visual.json` (T01 only edits the control; nobody else touches it); `DemoSeeder.php` + its test (T02
only). Safe parallel set after T01: {T03, T04, T07, T08} are file-disjoint apart from the hotspots above; T05 and T06
share `StockProductFields`/field CSS and should not run simultaneously (T05 first, T06 probes the regression).

Slice 2 parallelism and hotspots (scheduling, not dependency edges): `resources/js/lib/pilotTheme.ts` (T10, T11, T12, T13,
T14 each add one or two `landingPages` entries: serialize merges); `lib/pilotTheme.spec.ts` and
`composables/usePilotTheme.spec.ts` (one flipped or added row per page; T11 and T14 also move the formerly negative
`Producer/Products/Create`, `Producer/Products/Edit` and `Producer/Stock/Movements` rows); `resources/css/app.css`
`.landing` block (one distinct commented sub-block per task: `/* 005 T10 … */`, `/* 005 T11 … */`, `/* 005 T12 … */`,
`/* 005 T13 … */`, `/* 005 T14 … */`; if two tasks change the same block, the later one re-runs the earlier one's
manifest as its probe); `resources/js/fixtures/producer-landing*.ts/.html` (T10 creates; later tasks only add states and a
manifest of their own); `resources/js/fixtures/catalogue-landing-visual.ts` and the three slice 1 manifests that cite the
Producer probes (T10 only, D7); `DemoSeeder.php` + its test (T15 only). Safe parallel set after T10: {T11, T12, T13, T14}
are file-disjoint apart from the hotspots above. T15 runs in parallel with everything. `Producer/Products/Index`,
`Create`, `Edit` and `Stock/Movements` share `ActiveSwitch`, `WeeklyProductFields`, `ProductAdministrationActions`,
`MovementHistory` with already-admitted Catalogue pages, so their rules are reused, not rewritten.

Slice 3-7 parallelism and hotspots (scheduling, not dependency edges): `resources/js/lib/pilotTheme.ts` (every page task adds rows: serialize
merges); `lib/pilotTheme.spec.ts`, `composables/usePilotTheme.spec.ts` and `layout-rules.spec.ts` (one row per page); `resources/css/app.css`
`.landing` block (one commented sub-block per task, `/* 005 T17 … */` and so on); `components/ui/select/SelectContent.vue` (T17 only);
`layouts/AuthLayout.vue` and `layouts/auth/AuthSimpleLayout.vue` (T34 only until T44); the slice 1 and 2 fixtures and manifests that cite probe
pages (T21, T24, T27, T28 only, D14); `DemoSeeder.php` + its test (T22, T29, T38, serialized; each after the cycles). Safe parallel sets after each
tracer: C1 {T18, T19, T20, T21}; C2 {T25, T26, T27, T28}; C4 {T35, T36, T37}; C5 {T40, T41, T42, T43}. Seeder tasks run in parallel with everything
in their checkpoint. T44 runs alone.

## Coverage and conservation

| Ref | Owner(s) and what each completes |
| --- | --- |
| R1 | T01, T03–T08 one page group each (11 pages: Index; Products; Availability; WeeklyProduct Create/Edit; Movements; Stock Create/Edit; ReturnItems Index/Create/Edit); T09 re-confirms all 11 on the integrated base. |
| R2 | T01 (first navigation/leak states, re-pointed control), T04 (Availability previous/next page), every task keeps the control green; T09 re-runs S8 on the integrated base. |
| R3 | T01 starts (cross-page probe skeleton), T06 resolves D3 (manager-shell divergence); T09 completes the cross-page probe over all 11 pages. |
| R4 | Each page task for its pages (no hero/texture, `background-image` and `welcome` assertions). |
| R5 | Each page task for its pages (contrast families listed in plan R5). |
| R6 | Each page task for its pages (44 px, real-Tab focus lists, dialog Escape/focus return in T03/T05/T06). |
| R7 | Each page task, one 1024 px/200 % case per composition: list T03 (`Products`) + T01/T07 baseline; form T06 (`Manage/Stock/Edit`; T05/T07 baseline); history T08; availability T04; table T07; dialog T03. Tabular-nums: `Products` T03, `MovementHistory` T08, amount column T07. |
| R8 | Each page task for its states (long, empty, invalid, busy, inactive); the seeded states come from T02. |
| R9 | Each page task (state text present; danger resolves to `--destructive`; no ochre on status). |
| R10 | Each task: diff has no PHP/route/prop/`lang` change (T02: seeder only) and the guards run unchanged; T09 aggregates and reads S9 coverage. |
| R11 | Each task flips only the look-asserting lines listed in the plan; T01 flips the shared ones (`catalogue-unmigrated`, `data-s2-*`, Index lines 199/212–215/250–253); T09 runs the full family suite. |
| R12 | T01–T08 produce the per-page measurement text; T09 assembles the record and requests approval; approval is a user Q&A entry, never made by a task. |
| R13 | T02 (complete). |
| S1 | T09 only (needs all pages on the integrated base). |
| S2 | T01 (Manager variant on a Catalogue page + a Manager-only unmigrated page); T09 completes across all pages. |
| S3 | Split: weekly T05; stock T06; return items T07. Complete when the last of the three merges; T09 confirms. |
| S4 | Split: producers T01; products T03; return items T07. T02 supplies the seeded rows. Complete when T07 merges; T09 confirms. |
| S5 | T04 (needs T02 seed state for demo; fixture states for the measured result). |
| S6 | T08. |
| S7 | Split: `Products` dialog + toast T03; `WeeklyProduct/Edit` T05; `Manage/Stock/Edit` T06. Complete when T06 merges. |
| S8 | T01 first states; T04 availability pair; T09 re-run on integrated base. |
| S9 | T09 (reads existing guards; adds one assertion only for a proven gap — *to confirm*). |
| S10 | New specs: T05 (`WeeklyProduct/Create`, `Edit`), T06 (`Manage/Stock/Create`), T07 (`ReturnItems/{Index,Create,Edit}`), T08 (`Catalogue/Stock/Movements`); existing specs stay green in every task; T09 confirms the set. |
| S11 | Each page task measures its pages; T09 aggregates across the 11 pages. |
| S12 | T09 (approval is the user's decision; a task never records it). |

Every R1–R13 and S1–S12 has an owner. No criterion is deferred. Slices 2–7, mail, error pages, first paint, favicon,
global promotion and Dashboard restyle are outside.

### Coverage and conservation — slice 2

| Ref | Owner(s) and what each completes |
| --- | --- |
| R14 | One page group each: T10 `Products/Index`; T11 `Products/Create`, `Edit` and the dialog; T12 `Cutoff`; T13 `CycleSummary`; T14 `Stock/Movements` (6 pages); shell overlays re-measured on a Producer page in T10; T16 re-confirms all six on the integrated base. |
| R15 | T10 measures the title of the six pages (D6) and starts the cross-page probe; each page task checks its own title; T16 completes the probe over the six pages **and** `Catalogue/Producers/Products` (slice 1). |
| R16 | R5–R9 per page task for its pages and compositions: product list T10; product form and dialog T11; cutoff form T12; cycle summary T13; movement history T14. Seeded states come from T15; states that are not seeded (nothing to deliver, D11) come from the fixture and Vitest. |
| R17 | Each page task (presentation-only diff, guards unchanged); T16 aggregates and runs one multi-role navigation case. |
| R18 | T11 (`Create.spec.ts`, `Edit.spec.ts`), T12 (`Cutoff.spec.ts`), T13 (`CycleSummary.spec.ts`), T14 (`Stock/Movements.spec.ts`, `layout-rules.spec.ts` row); T10 keeps `Producer/Products/Index.spec.ts` green; T16 confirms the set. |
| R19 | Demo data: T15 (complete). Acceptance record and approval request: T16 (approval is the user's decision, never made by a task). |
| S13 | T10 starts; each page task adds its page; complete in T16 (needs all six pages on the integrated base). |
| S14 | Split: list T10; forms T11. Complete when T11 merges; T15 supplies the seeded rows; T16 confirms. |
| S15 | Split: cutoff T12; summary and availability toggle T13. Complete when both merge; T16 confirms. |
| S16 | Split: toggle, reactivate busy and error T10; dialog and delete-unused busy and error T11; history T14. Complete when T14 merges; T16 confirms. |
| S17 | T10 (first navigation and leak states, re-pointed controls); T16 completes (multi-role navigation, access refusals read from existing guards). |
| S18 | T16 only. |

Every R14–R19 and S13–S18 has an owner. No criterion is deferred. Slices 3–7 (see [discovery](discovery.md)), mail, error pages,
the Dashboard links to these pages, first paint, favicon and global promotion are outside.

### Coverage and conservation — slices 3-7

| Ref | Owner(s) and what each completes |
| --- | --- |
| R20 | One closure task per checkpoint: T23 (C1), T30 (C2), T33 (C3), T39 (C4), T45 (C5); each asks for approval and never records it. |
| R21 | One page group each: C1 T17–T21; C2 T24–T28; C3 T31, T32; C4 T34–T37; C5 T40; closure tasks re-confirm on the integrated base. |
| R22 | Each page task for its pages and compositions (contrast, 44 px, tabular figures, state text, no overflow). |
| R23 | T17 (select), T21 and T28 (`MovementHistory`, last probe), T27 (`NativeDialog`), T34 (auth shell), T36 (settings layout); each closure task reads the probe table. |
| R24 | T17 owns the select; reused by T20, T24–T28, T31 (no select edit after T17). |
| R25 | Each page task (presentation-only diff, PHP guards unchanged); T31 multi-role, T36 appearance preference. |
| R26 | Specs by page: T17 (`SelectContent`), T18, T20, T21, T24, T25, T26, T27, T28, T32, T34, T35, T36, T37, T42; existing specs stay green in every task. |
| R27 | Seeder: T22 (C1, if a gap), T29 (C2), T38 (C4); C3 states by the read in T33. Acceptance records: every closure task. |
| R28 | T40 (dashboard), T41 (error page, first paint, tab icons), T42 (transition). |
| R29 | T43 (orphans), T44 (promotion and inventory). |
| R30 | Every closure task re-runs slice 1 and 2 manifests; T44 re-measures after the promotion. |
| S19 | Split by C1 page: T17, T18, T19, T20, T21; complete when T21 merges; T23 confirms. |
| S20 | T17 (select, keyboard and touch); T20 (second user). |
| S21 | Split by C2 page: T24–T28; complete when the last merges; T30 confirms. |
| S22 | Overlays: total dialog T17; holder removal T24; settlement T25; cycle dialogs T27; stock edit T28. |
| S23 | T31, T32; T33 confirms. |
| S24 | Split: sign-in cards T34; other auth pages T35; two-factor T37; full after hydration T42. |
| S25 | T36, T37; T39 confirms. |
| S26 | Probes from T17 on (T21, T24, T27, T28, T34, T36); each closure task checks the next-checkpoint probe pages. |
| S27 | Each page task measures its pages; each closure task aggregates. |
| S28 | T40, T41, T42; T45 confirms. |
| S29 | T43 start; T44 complete; T45 confirms. |
| S30 | Each closure task (T23, T30, T33, T39, T45); approval is the user's decision. |

Every R20–R30 and S19–S30 has an owner. No criterion is deferred. Mail templates, new dashboard capability (004), new pages,
routes and copy remain outside.

## Task details

### T01 Show the producer list in the landing identity, with admission contract and controls re-pointed

- **Outcome.** A Catalogue (or Manager) account sees `Catalogue/Producers/Index` in the landing identity, light and dark,
  with shell and overlays, while the re-pointed unmigrated control, Welcome and the three earlier landing pages are
  unchanged. This is also the tracer for every later page task.
- **Requirements/scenarios.** R1, R2, R4–R9 for this page; R3 start; S4 (producers: empty, several, long name,
  deactivated), S8 (first states), S2 (Manager variant on this page and on one Manager-only unmigrated page), R10/R11
  guards. Remainder: R3 complete and S1/S2/S8 final → T09; S4 products/return items → T03/T07.
- **Plan sections.** Architecture overview; Contract for admitting a page; R1, R2, R3 (D3 probe), R4–R9; Test plan
  (edited assertions); Visual evidence plan incl. First checkpoint; D4, D5.
- **Files/surfaces reserved.** Edit: `resources/js/lib/pilotTheme.ts` (`landingPages` row), `resources/css/app.css`
  `.landing` sub-block (only if a probe fails), `resources/js/lib/pilotTheme.spec.ts` (lines ~184–185, 199, 212–215),
  `resources/js/composables/usePilotTheme.spec.ts` (~250–253), `resources/js/fixtures/landing.visual.json`
  (`catalogue-unmigrated` view and `data-s2-*` expectations re-pointed to `Manage/Accounts/Index`),
  `resources/js/fixtures/landing-visual.ts` (control wiring, ~114/126/185). New *(planned)*:
  `resources/js/fixtures/catalogue-landing.html`, `catalogue-landing-visual.ts`, `catalogue-landing-shared.visual.json`.
  History entries (own files). Fixture paths *unverified*; confirm against `landing-orders-visual.ts` layout.
- **Dependencies.** None; the shell, tokens and portals exist (001 T84/T85, integrated).
- **UI reuse.** `pages/Catalogue/Producers/Index.vue`, `StatusIndicator`, `ui/button`, `ui/badge`; no new component.
- **Done when.**
  - First checkpoint: `Catalogue/Producers/Index` at 375 px light with a deactivated producer first measures the primary
    element at green `rgb(36, 107, 87)` (failing value recorded), then after admission `rgb(40, 101, 133)` on
    `rgb(250, 241, 220)`.
  - Given the page in light and dark at 375/1280 px (and 1024 px/200 % text as the list composition baseline), the runner
    reports `.reference-style.landing` count 1, `.pilot-theme:not(.landing)` count 0, `.welcome-page` count 0, no
    `background-image` in `#main-content`, H1/muted/button/badge contrast at 4.5 (3 for borders and focus), interactive
    targets >= 44 px, no horizontal overflow, real-Tab focus order.
  - Given empty, several, very long name and deactivated producer fixture states, text "Deactivated" is present on the
    deactivated row and nothing clips.
  - Given navigation Producers/Index -> `Manage/Accounts/Index` -> back with the mobile sheet and with the account menu
    open, light and dark and system preference, the control keeps green `rgb(36, 107, 87)`/`rgb(248, 247, 244)` light and
    `rgb(167, 215, 193)`/`rgb(27, 30, 28)` dark; Index recovers the landing colours (S8 first states).
  - Vitest: `pilotVariantFor('Catalogue/Producers/Index')` returns `'landing'`; near-miss names
    (`Catalogue/Producers/Index/Extra`, `Catalogue/Producers`, `Manage/Stock/Movements`) return `''`; the shell,
    dialog and sheet take `landing` for the page and lose it after leaving; existing `Index.spec.ts` (5 cases) green.
  - Variant-off fixture state restores the exact pre-slice values (reversibility).
  - Manager variant of the same page shows identical computed colours; one Manager-only unmigrated page is unchanged.
  - R10 guards pass unchanged. Handoff: "Demo seeder: see T02".
- **First checkpoint.** As above: record the green failure first, admit, re-measure blue.
- **Size.** ~250–400 authored lines (medium confidence; plan estimate), excluding the manifest JSON. One larger tracer
  is justified because the fixture, control re-pointing and first admission cannot be demonstrated apart.
- **Limits.** No other page admitted. No shared-bootstrap prefactor (D5). Does not choose D3 beyond recording the probe.
- **Acceptance record.** Required at close: R1/R2/R4–R9 + S4 producers + S8 first states + S2 Manager variant, evidence,
  verdict, and remainder owners above.

### T02 Show the Catalogue states in the demo seeder

- **Outcome.** Running the demo seeder yields a deactivated producer, an inactive weekly product, a product marked
  unavailable in the unsent cycle and two return items (one inactive), so the user can inspect every R8 state.
- **Requirements/scenarios.** R13 (complete). Supports S4, S5. Remainder: none.
- **Plan sections.** Seeder decision (items 1–4).
- **Files/surfaces reserved.** `database/seeders/DemoSeeder.php`, `tests/Feature/Database/DemoSeederTest.php` only.
  Use existing use cases inside the existing transaction, after the cycles: `CreateProducer`+`DeactivateProducer`
  (`src/Catalog/Application`), `CreateProduct`+`DeactivateProduct`, `SetProductAvailability` on cycle `[2]`
  (`src/Ordering/Application`), `CreateReturnItem`+`DeactivateReturnItem` (`src/Stock/Application`).
- **Dependencies.** None.
- **Done when.**
  - Test "seeds the Catalogue states": one deactivated producer not passed to `OpenCycle`, one inactive product with no
    order lines, one product unavailable in the unsent cycle `[2]` whose producer order is not sent, two return items of
    which one is inactive; `biel@demo.guixa.test` (Catalogue) opens `/catalogue/producers`, its products, availability
    and `/catalogue/return-items` (extend the `'receiver'` path list).
  - Counts at `DemoSeederTest.php:20-23` updated; idempotency table list (`:199`) gains `return_items` and
    `producer_cycle_availability` (*table names unverified: confirm in migrations*); dashboard assertions (`:50-64`,
    attention counts, "Catalogue maintenance") unchanged. Whole file run (*unverified*), no flake under `PGTZ=UTC`.
  - Seeder idempotent on second run.
- **First checkpoint.** Failing assertion "one deactivated producer exists"; seed it; inspect counts.
- **Size.** ~80–150 lines (plan estimate; medium). PHP-only exception: no UI; justified because data setup is the
  entire outcome and is independent of the presentation tasks.
- **Limits.** No new use case, migration or domain change; no change to cycle/reception assertions.
- **Acceptance record.** R13 with the test assertions listed; verdict; no remainder.

### T03 Show a producer's products and the classification dialog in the landing identity

- **Outcome.** `Catalogue/Producers/Products` and the `ProductAdministrationActions` dialog it opens show the landing
  identity, light and dark; the toast after saving is readable.
- **Requirements/scenarios.** R1, R4–R9 for `Products`; S4 products (empty, several, long name, inactive product);
  S7 on this page (dialog opened by keyboard, Escape closes with no request, focus returns, toast); shell overlays
  (dropdown, sheet, tooltip) on a Catalogue page. Remainder: S7 other dialogs → T05/T06; S4 return items → T07.
- **Plan sections.** R1 table (`Products`), R5, R6, R7 (tabular-nums on quantity/price), R8, R9; Visual evidence plan;
  Shared consumers.
- **Files/surfaces reserved.** `lib/pilotTheme.ts` row; `app.css` `.landing` sub-block for list/dialog; the two Vitest
  lists; `catalogue-landing-lists.visual.json` *(planned)* (or extend shared manifest); fixture states. The `NativeDialog`
  and `ProductAdministrationActions` markup is *not* edited (consumers: `Manage/Cycles/*`, `StockEditDialog`,
  `Producer/Products/Edit`).
- **Dependencies.** T01: fixture, manifest skeleton and re-pointed control the measurements run against.
- **UI reuse.** `Products.vue`, `ProductAdministrationActions.vue` (`NativeDialog`), `ActiveSwitch`, `StatusIndicator`,
  `ui/button`, `Heading`.
- **Done when.**
  - Baseline 375/1280 light and dark plus 1024 px/200 % text (list composition) and 375/200 % for the dialog: contrast,
    44 px targets, no overflow, `tabular-nums` on quantity/price cells (check which carry `pilot-amount`; *unverified*).
  - Inactive product shows text "Deactivated" (via `ActiveSwitch`); long name wraps (`wrap-anywhere`); `can` flags off
    (Catalogue-only versus Manager) keep the same actions.
  - Classify and delete dialogs: landing surface/ink/focus, Escape sends no request, focus returns to the opener,
    `NativeDialog.spec.ts` green. Dialog inheritance inside the top layer measured in browser (*unverified in plan*).
  - Shared-consumer probe: `Producer/Products/Edit` and `Manage/Cycles/*` dialog computed values unchanged.
  - Vitest: `Products.spec.ts` (5) green, admission rows flipped. R10 guards pass. Handoff: "Demo seeder: see T02".
- **First checkpoint.** `Products` at 375 light with an inactive product and the classify dialog open: measure dialog
  background and button colour failing (green/default) then passing.
- **Size.** ~200–350 lines (plan; medium).
- **Limits.** No Availability, forms or return items. No change to dialog copy or actions.
- **Acceptance record.** As the convention; remainder owners T05/T06/T07/T09.

### T04 Show producer availability in the landing identity

- **Outcome.** `Catalogue/Producers/Availability` shows the landing identity with its own layout rules (it was in neither
  list), and marking a product unavailable/available keeps behaviour.
- **Requirements/scenarios.** R1, R2, R4–R9 for `Availability`; S5. Remainder: S8 re-run → T09.
- **Plan sections.** R1, R2, R8 (deactivated producer, unavailable product), D1, Contract item 2, Risks (layout gap).
- **Files/surfaces reserved.** `lib/pilotTheme.ts` (`pilotPages` **and** `landingPages`, D1), the two Vitest lists
  (add `isPilotThemePage` row for Availability), `app.css` `.landing` sub-block if needed, `catalogue-landing-availability.visual.json`
  *(planned)*, fixture states.
- **Dependencies.** T01: fixture and control.
- **UI reuse.** `Availability.vue` (`min-h-11` buttons, `StatusIndicator`), `ui/button`.
- **Done when.**
  - Given Availability before the change, admitting to `landingPages` alone yields landing colours without `pilot-*`
    layout rules (recorded failing measurement); with `pilotPages` it matches the other Catalogue pages' layout (D1).
  - States: producer deactivated (danger indicator + notice, text), product available, unavailable ("Unavailable" +
    "Mark available"), `canChange` true and false; text conveys state beyond colour; targets >= 44 px; baseline
    375/1280 light/dark; 1024 px/200 % (availability composition) no overflow.
  - Toggle round trip through the real router in a focused probe sends the same request as before; previous and next page
    keep their own look (R2). `Availability.spec.ts` (4) green; Vitest admission rows.
  - Handoff: "Demo seeder: see T02" (the unavailable-product demo state comes from T02).
- **First checkpoint.** Measure Availability with `landingPages` only (layout missing) then with `pilotPages` added.
- **Size.** ~150–250 lines (plan; medium). **Limits.** No change to the availability use case or copy.
- **Acceptance record.** S5 and R1/R2/R4–R9 for this page; remainder T09 (S8 re-run, area-wide).

### T05 Show weekly product create and edit forms in the landing identity

- **Outcome.** `WeeklyProduct/Create` and `Edit` show the landing identity; fields, errors and disabled state meet R5/R6;
  the new page specs exist.
- **Requirements/scenarios.** R1, R4–R9 for both forms (**R7 and R9 added to the plan's allocation**, see gaps); S3 weekly
  (empty, invalid, server error, keyboard-only); S7 on `WeeklyProduct/Edit` (delete/classify dialog); S10 for `Create`
  and `Edit`. Remainder: S3 stock/return items -> T06/T07; S7 stock -> T06.
- **Plan sections.** R5 (Input dark `dark:bg-input/30`, field border 3:1), R6 (`pilot-t44-page`), R8 (`InputError`, busy),
  Test plan new specs rows 1–2.
- **Files/surfaces reserved.** `lib/pilotTheme.ts` rows (2), `app.css` `.landing` field sub-block, Vitest lists, new
  `pages/Catalogue/Producers/WeeklyProduct/Create.spec.ts` and `Edit.spec.ts` *(planned)*,
  `catalogue-landing-forms.visual.json` *(planned)*. `WeeklyProductFields.vue` markup not edited (consumer:
  `Producer/Products/{Create,Edit}`).
- **Dependencies.** T01 (fixture/control). T03 for the dialog styling the Edit page reuses (hard dependency, confirmed).
- **UI reuse.** `WeeklyProductFields`, `ui/input`, `ui/label`, `InputError`, `ui/button`, `ProductAdministrationActions`.
- **Done when.**
  - Specs fail first then pass: Create title "New product", submit `data-test="save-product"` "Create", `errors.name`
    next to the Name input via `InputError`, submit `disabled` while `processing`; Edit prefilled fields, delete/classify
    only when `canDelete`/`canClassify`, dialog opens and Cancel sends nothing.
  - Measured: each field has a persistent label and border >= 3:1 (light and dark, including `dark:bg-input/30`), error
    text contrast, error not clipped, disabled submit distinguishable by `disabled` attribute and label; baseline plus
    375/200 % riskiest state; keyboard-only reading order with visible focus; 1024 px/200 % form composition.
  - Shared-consumer probe on `Producer/Products/Edit` unchanged. R10 guards pass.
- **First checkpoint.** Failing `errors.name` spec for `Create`, then the measured Name field border on `Create`.
- **Size.** ~250–400 lines (plan; medium). **Limits.** No copy/validation change; no Select.
- **Acceptance record.** As convention; remainder S3/S7 to T06/T07, T09.

### T06 Show stock product create and edit forms in the landing identity

- **Outcome.** `Manage/Stock/Create` and `Edit` (served to Catalogue by `StockProductController`) show the landing identity
  and match the other Catalogue forms in palette and type.
- **Requirements/scenarios.** R1, R3 (D3 divergence resolved here), R4–R9; S3 stock; S7 on `Manage/Stock/Edit`; S10
  `Manage/Stock/Create` spec. Remainder: S3 return items -> T07; area-wide R3 -> T09.
- **Plan sections.** R3, D3, R8 (locked-unit, errors), Test plan row 4.
- **Files/surfaces reserved.** `lib/pilotTheme.ts` rows (2; note `Manage/Stock/Index` already admitted, near-miss
  `Manage/Stock/Movements` must stay `''`), `app.css` `.landing` sub-block, Vitest lists, new
  `pages/Manage/Stock/Create.spec.ts` *(planned)*, `catalogue-landing-stock.visual.json` *(planned)*. `AppLayout.vue`
  `managerTheme` is **not** edited (D3).
- **Dependencies.** T01. T03 for the dialog styling (hard dependency, confirmed). T05 is a scheduling preference only (shared field CSS).
- **UI reuse.** `StockProductFields`, `ui/input`, `InputError`, `ProductAdministrationActions`; `coop-manager-shell`
  applies here, not on `Catalogue/*`.
- **Done when.**
  - Specs: `Create` title, create action, errors for name/unit/price/containers via `StockProductFields`; existing
    `Edit.spec.ts` (2) green.
  - Cross-page probe records H1 family/size/weight, background, primary colour, field border, max-width and padding for
    `Manage/Stock/*` against the T05 pages; any gap is equalized inside `.landing` only (D3 outcome recorded).
  - Locked-unit state, all-errors state at 375/200 %, used product without classify actions, dialog on Edit: contrast,
    44 px, no overflow; shared-consumer probe on `Manage/Stock/Movements` and `Producer/Products/Edit` unchanged.
- **First checkpoint.** Failing `Create` error-display spec; then the D3 probe values.
- **Size.** ~150–300 lines (plan; medium). **Limits.** No `managerTheme` extension, no `Manage/Stock/Index` change.
- **Acceptance record.** As convention; completes S3 weekly+stock with T05 and S7 with T03/T05.

### T07 Show the return-item list and forms in the landing identity

- **Outcome.** `Manage/ReturnItems/Index`, `Create` and `Edit` show the landing identity, with the table reflow, amounts
  and inactive items legible.
- **Requirements/scenarios.** R1, R4–R9; S3 return items; S4 table (empty, several, long name, inactive item); S10 specs
  `Index`, `Create`, `Edit`. Remainder: area-wide items -> T09.
- **Plan sections.** R7 (table, amount `tabular-nums`), R8, Test plan rows 5–6.
- **Files/surfaces reserved.** `lib/pilotTheme.ts` rows (3), `app.css` `.landing` sub-block, Vitest lists, new
  `pages/Manage/ReturnItems/{Index,Create,Edit}.spec.ts` *(planned)*, `catalogue-landing-returns.visual.json` *(planned)*.
  `ReturnItemFields.vue` markup not edited.
- **Dependencies.** T01.
- **UI reuse.** `ActiveSwitch`, `ReturnItemFields`, `InputError`, `pilot-list`/`pilot-data-table`/`pilot-cell-label`.
- **Done when.**
  - Specs: `Index` rows with `ActiveSwitch` text for active and inactive item, empty text, create link; `Create`/`Edit`
    title, primary action, `errors.name`/`amount` via `InputError`.
  - Measured: amount column `tabular-nums` aligned, inactive item text "Deactivated", empty state explains itself, switch
    track/thumb contrast in both appearances (`app.css:678-725`, *unverified*), 1024 px/200 % table composition,
    no overflow, 44 px including switch hit area; shared-consumer probe on `Manage/Accounts/Index` (`ActiveSwitch`)
    unchanged.
- **First checkpoint.** Failing `Index` inactive-text spec; then measured switch contrast.
- **Size.** ~250–400 lines (plan; medium). **Limits.** No new page, field or action.
- **Acceptance record.** As convention; completes S3 and S4 with T05/T06 and T01/T03.

### T08 Show the stock movement history in the landing identity

- **Outcome.** `Catalogue/Stock/Movements` reads the same entries in the landing identity, read-only.
- **Requirements/scenarios.** R1, R4–R9 (**R5, R6, R8 added to the plan's allocation**, see gaps); S6; S10 `Movements`
  spec. Remainder: none beyond T09 aggregation.
- **Plan sections.** R7 (quantity/balance `tabular-nums`), Shared consumers (`MovementHistory` in three unmigrated
  pages), Test plan row 3.
- **Files/surfaces reserved.** `lib/pilotTheme.ts` row, `app.css` `.landing` sub-block, Vitest lists, new
  `pages/Catalogue/Stock/Movements.spec.ts` *(planned)*, `catalogue-landing-history.visual.json` *(planned)*.
  `MovementHistory.vue` not edited (`MovementHistory.spec.ts`, 6 cases, green).
- **Dependencies.** T01.
- **UI reuse.** `MovementHistory`, `pilot-t44-page`.
- **Done when.** Spec: H1 = product name, available quantity line, `MovementHistory` rendered, no entry/adjustment/count
  control (001 R28a). Measured at baseline plus 1024 px/200 % history composition and empty history; `tabular-nums`
  on quantity/balance; mandatory leakage probe: `Producer/Stock/Movements`, `Manage/Stock/Movements` and
  `Finance/Stock/Movements` computed values unchanged; direct link to a quantity-changing action still refused
  (existing `StockMovementHistoryPagesTest.php`, `RolePermissionMatrixTest.php` unchanged).
- **First checkpoint.** Failing read-only spec, then the leakage probe values on the three consumers.
- **Size.** ~120–250 lines (plan; medium). **Limits.** No history component change.
- **Acceptance record.** S6 with assertions; R1/R4–R9.

### T09 Record slice acceptance and request visual approval

- **Outcome.** The integrated base has one acceptance record mapping every R1–R13/S1–S12 to task, evidence, verdict and
  remainder, and the user has been asked for visual approval of the Catalogue pages in light and dark.
- **Requirements/scenarios.** R3 (area-wide), R10, R11, R12; S1, S2, S8 (re-run), S9, S10, S11 (aggregate), S12.
- **Plan sections.** R2, R3, R10 (S9 note), R11, R12, Visual evidence plan (negative/leak and navigation cases).
- **Files/surfaces reserved.** History entries (Q&A for approval, plus Deviations/Changes if any); `tasks.md` status
  edits; no code unless a correction is found (then recorded as a Deviation and, if non-trivial, a new task).
- **Dependencies.** T02–T08 integrated: all 11 pages admitted and the seeded states exist for the user's review.
- **Done when.**
  - All manifests run on the integrated base SHA: the 11 pages share H1 font family/size/weight, background, primary
    button colour, field border, max-width and padding (R3 probe); navigation states with sheet and account menu pass
    in both modes (S8); Manager variant of each page identical (S2); Welcome and the three earlier landing pages pass.
  - S9: existing guards cover refused Receiver-only, producer-without-Catalogue and Catalogue-on-`/manage/stock` and
    cycle settings URLs; add one assertion only for a proven gap (*to confirm when reading them*).
  - Full family suite green: `Catalogue/Producers/{Index,Products,Availability}.spec.ts`, `Manage/Stock/Edit.spec.ts`,
    `MovementHistory.spec.ts`, `NativeDialog.spec.ts`, `layout-rules.spec.ts`, `pilotTheme` specs, the PHP guards,
    `DemoSeederTest.php`, plus the seven new specs.
  - The record lists, per page, R5–R9 as measured text (no screenshots unless an unexplained failure) and the user is
    asked for approval. Approval is logged only after the user's decision, as a Q&A naming the evidence covered. Until then
    the status is "awaiting approval" and no slice 2 spec starts.
- **First checkpoint.** Run the shared manifest on the integrated base and read the R3 probe table.
- **Size.** Documentation and evidence only (~0 code). **Limits.** Does not approve on the user's behalf; no slice 2.
- **Acceptance record.** This task's output is the feature-slice record; verdict "pending approval" until the user answers.

### Slice 2 task details

Conventions of "Execution and evidence contract" apply unchanged, with "Catalogue" read as "Producer", the fixture
`producer-landing` (role `producer`) in place of `catalogue-landing`, and the demo seeder owner T15 in place of T02.
Pages are under `resources/js/pages/` (lowercase). Each page task states in its handoff "no PHP behaviour changed" and
"Demo seeder: see T15". Every fixture, manifest and spec path below is *(planned)* and *unverified*; confirm against
`catalogue-landing-visual.ts` before creating.

### T10 Show the producer product list in the landing identity, with fixture and controls re-pointed

- **Outcome.** A producer sees `Producer/Products/Index` in the landing identity, light and dark, with shell and
  overlays, while the re-pointed unmigrated controls, Welcome, the slice 1 pages and the other five Producer pages are
  unchanged. This is also the tracer for every later Producer page task.
- **Requirements/scenarios.** R14, R15 (title measurement of the six pages, D6), R16 and R17 for `Products/Index`; S13
  start; S14 list (no products, several, very long name, inactive product, no/inactive/sold-out stock product); S16 toggle
  and reactivate with busy and error states; S17 first navigation states. Remainder: S13/S17/R15 final -> T16;
  forms and delete-unused busy and error (on `Products/Edit`) -> T11; other pages -> T12–T14.
- **Plan sections.** Architecture overview (Title size, Shared consumers); Contract for admitting a Producer page; R14,
  R15, R16 (product list), R17; Test plan; Visual evidence plan incl. First checkpoint; Risks; D6, D7, D8, D9.
- **Files/surfaces reserved.** Edit: `resources/js/lib/pilotTheme.ts` (`landingPages` row for `Producer/Products/Index`),
  `resources/css/app.css` `.landing` sub-block `/* 005 T10 … */` (only if a probe fails; stock table cells are the likely
  overflow at 375 px/200 %), `resources/js/lib/pilotTheme.spec.ts` and `resources/js/composables/usePilotTheme.spec.ts`
  (positive row for `Producer/Products/Index`; near-miss loop keeps a Producer-prefixed near name returning `null`),
  `resources/js/fixtures/catalogue-landing-visual.ts` (probe re-pointing, ~`:447`, `:458`, `:460`, URL map `:506`, `:517`,
  `:525`) and its uses in `catalogue-landing-history.visual.json` (~`:709`), `catalogue-landing-stock.visual.json`
  (~`:1777`), `catalogue-landing-lists.visual.json` (~`:2616`) (line numbers from the plan, *to confirm when reading*).
  New: `resources/js/fixtures/producer-landing.html`, `producer-landing-visual.ts`, `producer-landing-shared.visual.json`
  (controls, navigation, title probe; plus a producer-list manifest `producer-landing-lists.visual.json`). The older
  `dra-*`/`reference-*` manifests are left (manual-only). History entries (own files).
- **Dependencies.** None; the shell, tokens, portals and slice 1 pages are integrated.
- **UI reuse.** `pages/Producer/Products/Index.vue`, `ActiveSwitch`, `StatusIndicator`, `ui/button`, `ui/badge`; no new
  component.
- **Done when.**
  - First checkpoint: `Producer/Products/Index` at 375 px light with the producer role first measures the primary element
    at green `rgb(36, 107, 87)` (failing value recorded), then after admission `rgb(40, 101, 133)` (T84 constants).
  - Given the re-pointed controls, the history probe measures `Manage/Stock/Movements` and `Finance/Stock/Movements`, the
    dialog probe `Manage/Cycles/Index`, and the `WeeklyProductFields` probe is dropped; the slice 1 manifests that cited
    `Producer/Stock/Movements`, `Producer/Products/Create` and `Producer/Products/Edit` run green on the base **before** the
    `landingPages` row is added (D7), and still green after.
  - Given the six Producer pages in the fixture, the runner reports `h1.pilot-heading` font size at 375 and 1280 px,
    light and dark; expected 24 px (`fontSizeRem: 1.5`) from `.pilot-t44-page`. `pilot-title-compact` is added only to a page
    that measures otherwise and recorded as a Deviation (D6, *to confirm when reading whether the other five pages mount
    in the fixture before their own task; if not, each page task measures its own title and T10 records the expectation*).
  - Given `Products/Index` in light and dark at 375/1280 px (and 1024 px/200 % text, product-list composition), the runner
    reports `.reference-style.landing` count 1, `.pilot-theme:not(.landing)` count 0, `.welcome-page` count 0, no
    `background-image` in `#main-content`, H1/muted/button/badge contrast at 4.5 (3 for borders and focus), interactive
    targets >= 44 px (including `ActiveSwitch` and stock table controls), no horizontal overflow, real-Tab focus order.
  - Given no products, several, very long name and unit, inactive product, no stock products, inactive and sold-out stock
    product: text "Deactivated" is present on the inactive row, `Inactive` and `Sold out` are text badges, and nothing clips.
  - Given toggle Active and reactivate, the busy and error states keep text and contrast; the real router
    in a focused probe sends the same requests as before (R17).
  - Given navigation Producer products -> `Manage/Accounts/Index` -> back, with the mobile sheet and with the account menu
    open, light and dark and system preference, the control keeps green and `Index` recovers the landing colours (S17 first
    states); one multi-role navigation case shows the other area keeps its own look.
  - Variant-off fixture state restores the exact pre-slice values (reversibility). Welcome and the slice 1 pages unchanged.
  - Vitest: `pilotVariantFor('Producer/Products/Index')` returns `'landing'`; the shell, dialog and sheet take `landing`
    and lose it after leaving; existing `Producer/Products/Index.spec.ts` green. R10 guards
    (`ProducerPagesTest.php`, `ProductPagesTest.php`, `ProducerProductsPageTest.php`, `RolePermissionMatrixTest.php`) pass
    unchanged, plus `layout-rules.spec.ts`. Handoff: "Demo seeder: see T15".
- **First checkpoint.** As above: record the green failure first, re-point the probes, admit, re-measure blue.
- **Size.** ~250–400 authored lines (plan; medium confidence), excluding manifest JSON. At the upper bound because the
  fixture, the probe re-pointing and the first admission cannot be demonstrated apart; the title measurement of the
  other five pages is the first item to cut if the diff passes ~400 lines.
- **Limits.** No other Producer page admitted. No shared-bootstrap extraction (D5/D9). No markup change in `ActiveSwitch`.
- **Acceptance record.** Required at close: R14/R16/R17 for `Products/Index`, R15 title measurement, S14 list, S16
  toggle/reactivate, S17 first states, evidence, verdict, remainder owners above.

### T11 Show the producer product forms and classification dialog in the landing identity

- **Outcome.** `Producer/Products/Create` and `Edit`, and the `ProductAdministrationActions` dialog opened on `Edit`,
  show the landing identity; fields, errors and busy state meet R5/R6; the new page specs exist.
- **Requirements/scenarios.** R14–R16 for both forms and the dialog; R17; R18 specs `Create`, `Edit`; S14 forms (invalid and
  valid submit); S16 dialog (opened by keyboard, Escape closes with no request, focus returns) and delete-unused busy and error on `Edit`
  (reallocated from T10). Remainder: S14/S16 final -> T16.
- **Plan sections.** R14 (overlays), R16 (product form and dialog), Test plan rows `Products/Create`, `Products/Edit`; D7,
  D8; Shared consumers (`WeeklyProductFields`, `ProductAdministrationActions` now fully migrated).
- **Files/surfaces reserved.** `lib/pilotTheme.ts` rows (2); `lib/pilotTheme.spec.ts` and `composables/usePilotTheme.spec.ts`
  (move `Producer/Products/Create` and `Producer/Products/Edit` from the negative rows, ~`:224-227` and
  `:248-259`/`:376-379`, to the positive ones); `app.css` `.landing` sub-block `/* 005 T11 … */` if a probe fails; new
  `pages/Producer/Products/Create.spec.ts` and `Edit.spec.ts`; `producer-landing-forms.visual.json`. `WeeklyProductFields.vue`
  and `ProductAdministrationActions.vue` markup not edited.
- **Dependencies.** T10: fixture and re-pointed dialog probe (`Manage/Cycles/Index`).
- **UI reuse.** `WeeklyProductFields`, `ui/input`, `ui/label`, `InputError`, `ui/button`, `ProductAdministrationActions`
  (`NativeDialog`); field pattern already measured in slice 1 T05.
- **Done when.**
  - Specs fail first then pass (SSR `renderToString` with `installTranslations`, mirroring `Products/Index.spec.ts`):
    `Create` title "New product", submit "Create", field error rendered through `InputError`, submit `disabled` while
    `processing`; `Edit` prefilled fields, delete-unused control present, dialog opens and Cancel sends no request.
  - Measured at 375/1280 px light and dark: persistent label, field border >= 3:1 (including `dark:bg-input/30`), error
    text contrast, error not clipped, disabled submit distinguishable by `disabled` and label; 1024 px/200 % form
    composition and 375 px/200 % dialog no overflow; keyboard-only reading order with visible focus; long product name
    and invalid form state.
  - Dialog opened by keyboard on the producer role: landing surface/ink/focus, Escape sends no request, focus returns to
    the opener, `NativeDialog.spec.ts` green (the toast is the synthetic Sonner probe already accepted in the slice 1 T03
    Deviation, cited, not repeated).
  - Given delete-unused on `Edit`, the busy and error states keep text and contrast, and the real router in a focused probe
    sends the same request as before (same assertions as the T10 toggle; R17).
  - Shared-consumer probe: `NativeDialog` base on `Manage/Cycles/Index` unchanged. R10 guards pass.
- **First checkpoint.** Failing `errors.name` spec for `Create`, then the measured Name field border on `Create` before
  and after admission.
- **Size.** ~200–350 lines (plan; medium). **Limits.** No copy/validation change; no new field or action.
- **Acceptance record.** As convention; S14 and S16 dialog portions; remainder T16.

### T12 Show the producer default cutoff in the landing identity

- **Outcome.** `Producer/Cutoff` shows the landing identity, and saving a valid or invalid value keeps its behaviour.
- **Requirements/scenarios.** R14–R16 for `Cutoff`; R17; R18 spec `Cutoff`; S15 cutoff (valid and invalid). Remainder: S15
  summary -> T13; area-wide -> T16.
- **Plan sections.** R16 (cutoff form), Test plan row `Cutoff`, Risks (touch targets).
- **Files/surfaces reserved.** `lib/pilotTheme.ts` row; the two Vitest lists (positive row for `Producer/Cutoff`);
  `app.css` `.landing` sub-block `/* 005 T12 … */` if a probe fails; new `pages/Producer/Cutoff.spec.ts`;
  `producer-landing-cutoff.visual.json`.
- **Dependencies.** T10: fixture and control.
- **UI reuse.** `Cutoff.vue` (`data-test="save-cutoff"`), `ui/input`, `ui/label`, `InputError`, `ui/button`.
- **Done when.**
  - Spec fails first then passes: title "Default cutoff", both fields prefilled, `errors.days_before` and
    `errors.time`/`errors.domain` rendered, `save-cutoff` disabled while `processing`.
  - Measured: cutoff form at baseline plus 1024 px/200 %, validation-error state (text message present, field border
    >= 3:1 from `--input`, error not clipped), busy submit; 44 px targets; no overflow; real-Tab focus order.
  - Given a valid and an invalid value through the real router in a focused probe, the same request, message and
    redirect result as before.
  - Admission Vitest rows; R10 guards pass (`ProducerPagesTest.php`).
- **First checkpoint.** Failing `Cutoff` error-display spec; then the green-to-blue measurement on the page.
- **Size.** ~120–220 lines (plan; medium). **Limits.** No change to the cutoff rule or copy.
- **Acceptance record.** S15 cutoff with assertions; remainder S15 summary T13, area-wide T16.

### T13 Show the producer cycle summary in the landing identity

- **Outcome.** `Producer/CycleSummary` shows the landing identity for sent (read-only quantities) and not-sent
  (availability toggle) cycles, with the same figures and the same toggle result.
- **Requirements/scenarios.** R14–R16 for `CycleSummary`; R17; R18 spec `CycleSummary`; S15 summary (with and without
  lines) and availability toggle. Remainder: S15 cutoff -> T12; area-wide -> T16.
- **Plan sections.** R16 (cycle summary), Test plan row `CycleSummary`, Visual evidence plan (cycle states), Risks (touch
  targets of the toggle).
- **Files/surfaces reserved.** `lib/pilotTheme.ts` row; the two Vitest lists (positive row for `Producer/CycleSummary`);
  `app.css` `.landing` sub-block `/* 005 T13 … */` if a probe fails; new `pages/Producer/CycleSummary.spec.ts`;
  `producer-landing-cycle.visual.json`.
- **Dependencies.** T10: fixture and control.
- **UI reuse.** `CycleSummary.vue` (`data-test="availability"`), `StatusIndicator`, `ui/button`,
  `pilot-data-table`/`pilot-cell-label`.
- **Done when.**
  - Spec fails first then passes: sent with lines shows quantities and no toggle; sent without lines shows "Nothing to
    deliver."; not sent shows `Available`/`Unavailable` text and the matching `Mark available`/`Mark unavailable` button;
    no products shows "You have no products to offer.".
  - Measured: all four states at baseline, 1024 px/200 % cycle-summary composition; quantity cells use `tabular-nums`
    (which cells carry `pilot-amount`: *to confirm when reading*); toggle target >= 44 px; available and unavailable differ
    by text as well as colour; no overflow; danger resolves to `--destructive`, no ochre on status.
  - Given the toggle through the real router in a focused probe, the same request as before; "nothing to deliver" comes from
    the fixture and Vitest, not the seeder (D11).
  - Admission Vitest rows; R10 guards pass (`ProducerCycleSummaryPageTest.php`).
- **First checkpoint.** Failing not-sent spec (`Available`/`Unavailable` text and button label); then toggle target
  measurement.
- **Size.** ~180–300 lines (plan; medium). **Limits.** No change to figures, availability use case or copy.
- **Acceptance record.** S15 summary and toggle with assertions; remainder S15 cutoff T12, area-wide T16.

### T14 Show the producer stock movement history in the landing identity

- **Outcome.** `Producer/Stock/Movements` reads the same entries in the landing identity, read-only, and the Finance
  and Manager histories are unchanged.
- **Requirements/scenarios.** R14, R16 for `Stock/Movements` (R5–R9 for this page, as every page task owns them); R17;
  R18 spec `Movements` and the 8 px grid row; S16 history. Remainder: S16 final -> T16.
- **Plan sections.** R16 (movement history), Shared consumers (`MovementHistory`: `Finance/Stock/Movements`,
  `Manage/Stock/Movements` unmigrated), Test plan rows `Stock/Movements` and layout grid, D7.
- **Files/surfaces reserved.** `lib/pilotTheme.ts` row; the two Vitest lists (move `Producer/Stock/Movements` from the
  negative to the positive row); `resources/js/layout-rules.spec.ts` (add `pages/Producer/Stock/Movements.vue`, ~`:11-15`);
  `app.css` `.landing` sub-block `/* 005 T14 … */` if a probe fails; new `pages/Producer/Stock/Movements.spec.ts`;
  `producer-landing-history.visual.json`. `MovementHistory.vue` not edited (`MovementHistory.spec.ts` green).
- **Dependencies.** T10: fixture and the re-pointed history probe.
- **UI reuse.** `MovementHistory`, `pilot-t44-page`, `pilot-data-table`, `pilot-cell-label`.
- **Done when.**
  - Spec fails first then passes: title, available-quantity line, `MovementHistory` rendered, no entry/adjustment/count
    control.
  - Measured: baseline plus 1024 px/200 % history composition, stock product with no movements, long movement list;
    `tabular-nums` on quantity and balance; pagination links >= 44 px; no overflow.
  - Mandatory leakage probe: `Manage/Stock/Movements` and `Finance/Stock/Movements` computed values unchanged before and
    after the row is added; `MovementHistory` markup not touched.
  - Direct link to a quantity-changing action or to another producer's history still refused
    (`StockMovementHistoryPagesTest.php`, `RolePermissionMatrixTest.php` unchanged).
  - `layout-rules.spec.ts` green with the new page in the 8 px list. R10 guards pass.
- **First checkpoint.** Failing read-only spec, then the leakage probe values on the two unmigrated consumers.
- **Size.** ~120–250 lines (plan; medium). **Limits.** No history component change.
- **Acceptance record.** S16 history with assertions; R14/R16/R17; remainder T16.

### T15 Show the Producer states in the demo seeder

- **Outcome.** Running the demo seeder yields every Producer state the user must review: a producer with no products, an
  inactive, a sold-out and a no-movements stock product, a long name and unit, a history that spans more than one page and
  a second inactive weekly product on a different producer.
- **Requirements/scenarios.** R19 (demo data, complete). Supports S14–S16. Remainder: none.
- **Plan sections.** Seeder decision, slice 2 (items 1–5, including the 51+ movement correction); D10, D11.
- **Files/surfaces reserved.** `database/seeders/DemoSeeder.php` (new `createProducerStates()` called after
  `createCatalogueStates`, inside the existing transaction) and `tests/Feature/Database/DemoSeederTest.php` only. Use
  existing use cases: `CreateProducer` + `Account::inviteProducer` (as `createCatalog`), the stock-creation use case
  (*name to confirm when reading, `src/Catalog/Application`*), `SetStockAvailable`, `RecordStockEntry`,
  `RecordStockAdjustment` (`src/Stock/Application`), `DeactivateStockProduct`
  (`src/Catalog/Application/DeactivateStockProduct.php`), `CreateProduct` + `DeactivateProduct`.
- **Dependencies.** None.
- **Done when.**
  - Test "seeds the Producer states" fails first, then passes, asserting: a fourth producer with an account and no products
    (created after the cycles, in no cycle); for `hort@` a stock product with a long name and long unit and no movements, a
    stock product sold out (`SetStockAvailable` quantity `0`) and a deactivated stock product (`DeactivateStockProduct`);
    one existing stock product (not the no-movements one) with **51 or more further** `RecordStockEntry`/
    `RecordStockAdjustment` calls so its history exceeds `ListStockMovements::PER_PAGE` (50) and spans a second page, the
    other stock products keeping one opening movement; a second inactive unused weekly product `Coca de sucre` on `forn@`
    alongside `Coca de recapte` (D10).
  - Counts at `DemoSeederTest.php:20-23` updated (producers 4 -> 5 and products/users as applicable, *to confirm when
    reading*); `'producer'` path list extended with `/producer/cutoff`, a cycle summary and a stock movement history; the new
    account's `/producer/products` opens (empty state); idempotency table list gains any newly read table; dashboard
    assertions (`:46-64`) unchanged. Whole file run, no flake under `PGTZ=UTC`; seeder idempotent on second run.
  - The "cycle with nothing to deliver" state is **not** seeded (D11); the handoff says it is covered by the fixture and
    Vitest and why (it would shift cycles, receptions and dashboard assertions).
- **First checkpoint.** Failing assertion "one producer without products exists"; seed it; inspect counts.
- **Size.** ~100–180 lines (plan; medium). PHP-only exception: no UI; data setup is the entire outcome and is independent
  of the presentation tasks.
- **Limits.** No new use case, migration or domain change; no change to cycle, reception or dashboard assertions; slice 1
  Catalogue states (T02) untouched.
- **Acceptance record.** R19 demo data with the test assertions listed; verdict; no remainder.

### T16 Record slice 2 acceptance and request visual approval

- **Outcome.** The integrated base has one acceptance record mapping every R14–R19/S13–S18 to task, evidence, verdict and
  remainder, and the user has been asked for visual approval of the Producer pages in light and dark.
- **Requirements/scenarios.** R15 (area-wide, against slice 1), R17, R18, R19; S13, S17, S18.
- **Plan sections.** R15, R17, R18, R19, Visual evidence plan (negative/leak and navigation cases), Seeder decision.
- **Files/surfaces reserved.** History entries (Q&A for approval, plus Deviations/Changes if any); `tasks.md` status
  edits; no code unless a correction is found (then recorded as a Deviation and, if non-trivial, a new task).
- **Dependencies.** T10–T15 integrated: all six pages admitted and the seeded states exist for the user's review.
- **Done when.**
  - All Producer manifests run on the integrated base SHA: the six pages share H1 font family, size (24 px), weight,
    background, primary button colour, field border, max-width and padding, and so does `Catalogue/Producers/Products`
    (slice 1) as comparison (R15 probe); navigation Producer -> unmigrated -> Producer with sheet and account menu passes
    in both modes (S17); one multi-role account keeps its other area's look; Welcome and the slice 1 pages pass.
  - S17 access: the existing guards (`ProducerPagesTest.php`, `ProductPagesTest.php`, `ProducerProductsPageTest.php`,
    `ProducerCycleSummaryPageTest.php`, `StockMovementHistoryPagesTest.php`, `RolePermissionMatrixTest.php`) cover another
    producer's data, a Catalogue-only account and a member; add one assertion only for a proven gap (*to confirm when
    reading them*).
  - Full suite green: `Producer/Products/Index.spec.ts`, the four new page specs plus `Create.spec.ts`/`Edit.spec.ts`,
    `MovementHistory.spec.ts`, `NativeDialog.spec.ts`, `layout-rules.spec.ts`, `pilotTheme` specs, the PHP guards,
    `DemoSeederTest.php`.
  - The record lists, per page, R5–R9 as measured text (no screenshots unless an unexplained failure) and the user is asked
    for approval of the six Producer pages in light and dark. Approval is logged only after the user's decision, as a Q&A
    naming the evidence covered. Until then the status is "awaiting approval" and no slice 3 spec starts (S18).
- **First checkpoint.** Run the shared manifest on the integrated base and read the R15 probe table.
- **Size.** Documentation and evidence only (~0 code). **Limits.** Does not approve on the user's behalf; no slice 3.
- **Acceptance record.** This task's output is the slice 2 record; verdict "pending approval" until the user answers.

### Slice 3-7 task details

Source: [accepted spec](spec.md) "Slice 3-7" (R20–R30, S19–S30, OQ4–OQ8) and the accepted slice 3-7 plan in [plan.md](plan.md)
("Slice 3-7 — Rest of the application · Plan", D12–D23 accepted by the user, 2026-11-16, with Corrections 1–7). Every
fixture, manifest, spec and line reference below is *(planned)* and *unverified*: confirm against the files when reading.

**Conventions for T17–T45.** The "Execution and evidence contract" applies unchanged, with these readings:

- "Page task" means T17–T21, T24–T28, T31–T32, T34–T37 and T40–T42. Each admits its pages by the seven-point contract
  (plan, "Contract for admitting a page (reuse)"): exact-name `landingPages` row; `pilotPages` membership (T24 adds
  `Manage/Roles/Index`, T32 adds `Member/Baskets/{Index,Show}` to **both** sets, Correction 6, D23); page measured before the
  row merges; `lib/pilotTheme.spec.ts`, `composables/usePilotTheme.spec.ts` and `layout-rules.spec.ts` rows; controls
  re-pointed first when a probe is admitted (D14); shared-consumer probe from the plan's per-checkpoint table; variant-off
  fixture state restores the prior values.
- Each page task owns R21, R22 and R25 for its pages, R26 for the specs it names (written first), and states in its handoff
  "no PHP behaviour changed" and the demo-seeder owner (T22 if it applies, T29, T38, otherwise "no change" with the reason).
- Fixtures: one per checkpoint (D13): `rest-c1-landing` … `rest-c5-landing`, each with a `*-shared.visual.json` manifest (controls,
  navigation) and one manifest per task for its own pages. The tracer of the checkpoint creates the fixture; later tasks add
  states and a manifest of their own. No bootstrap extraction.
- Sub-blocks in `resources/css/app.css`: `/* 005 T17 … */` and so on, one per task.
- Re-run the OQ8 inventory (`rg` over `resources/js/pages/**` and the overlay components, D19) at the start of each checkpoint's
  tracer and record a page not in the plan's table as a Deviation before admitting anything.
- **Closure tasks** (T23, T30, T33, T39, T45) write no code unless a correction is found (then a Deviation and, if non-trivial,
  a new task). They run the checkpoint manifests and the slice 1 and slice 2 manifests on the integrated base SHA (R30), list R5–R9 as
  measured text per page, and ask the user for one approval of the checkpoint in light and dark (D22). Approval is logged only after
  the user's decision, as a Q&A naming the evidence. Until then the status is "awaiting approval" and the next checkpoint does not start.

## Checkpoint C1 — Finance and reception (T17–T23)

PHP guards for C1 (run unchanged per task that covers the page): `tests/Feature/Sales/BasketPagesTest.php`,
`tests/Feature/Finance/FinanceBasketAccessTest.php`, `tests/Feature/Ordering/ReceptionPageTest.php`,
`tests/Feature/Stock/StockMovementHistoryPagesTest.php`, `tests/Feature/Http/RolePermissionMatrixTest.php`.

### T17 Show the reception detail, the select popup and the received-total dialog in the landing identity

- **Outcome.** A user with reception access sees `Receptions/Show`, its select popups and the received-total dialog in the landing
  identity, light and dark, while old-theme pages that use `FormSelect` are unchanged. This is the tracer of C1 and the owner of the select.
- **Requirements/scenarios.** R21–R26 for `Receptions/Show` and `ReceivedTotalForm`; R24 (owner); S19 reception; S20 (select, light/dark,
  keyboard and touch); S22 total dialog; S26 start. Remainder: other C1 pages T18–T21; C1 closure T23; select reuse by C2/C3 pages.
- **Plan sections.** Architecture overview (gaps, Pages by checkpoint, Select users); R22 (C1 rows); R23; R24; Test plan; Visual evidence
  plan incl. first checkpoint C1; D12, D13, D14.
- **Files/surfaces reserved.** Edit: `resources/js/components/ui/select/SelectContent.vue` (`usePilotVariantClass`, as
  `ui/dialog/DialogContent.vue`), `resources/js/lib/pilotTheme.ts` (row `Receptions/Show`), `resources/css/app.css` `/* 005 T17 … */`
  (popup surface, items, scroll buttons), `lib/pilotTheme.spec.ts`, `composables/usePilotTheme.spec.ts`, `layout-rules.spec.ts`. New:
  `components/ui/select/SelectContent.spec.ts`, `resources/js/fixtures/rest-c1-landing.html`, `rest-c1-landing-visual.ts`,
  `rest-c1-landing-shared.visual.json`, `rest-c1-landing-receptions.visual.json`. Existing select baseline in `selects.visual.json` is read-only.
- **Dependencies.** None; shell, tokens, portals and slices 1 and 2 are integrated.
- **Done when.**
  - First checkpoint: `Receptions/Show` at 375 px light first measures the select popup and primary action at green
    `rgb(36, 107, 87)` (failing value recorded), then after the select change and admission at landing blue `rgb(40, 101, 133)`,
    with the old-theme control `Manage/Accounts/Index` unchanged.
  - `SelectContent.spec.ts` fails first, then passes: the popup root carries the variant class when a variant is provided and no
    class otherwise (SSR render with `providePilotVariant`); `FormSelect.spec.ts` stays green.
  - Keyboard case: open with Enter/Space, arrow, select, Escape closes and focus returns, on a landing page and on
    `Manage/Accounts/Index`; same option chosen; touch targets of items >= 44 px; popup text/surface and highlight meet 4.5.
  - The old-theme select popup on `Manage/Accounts/Index` equals the `selects.visual.json` baseline in computed values (R23).
  - `Receptions/Show` at 375/1280 px light/dark plus 1024 px/200 % (list-and-allocation composition): `.reference-style.landing` count 1,
    `.pilot-theme:not(.landing)` 0, no `background-image`, contrast, 44 px targets, no overflow, real-Tab order; reception states
    (open, received, discrepancy, validation error, busy) read as text.
  - `ReceivedTotalForm` dialog opens by keyboard, traps and returns focus, Escape closes, error text uses `InputError`; toast and
    account menu carry `landing`.
  - Re-pointed probes are not needed here (`Manage/Accounts/Index` stays old-theme until T24); the probe table for C1 is recorded.
  - R25 guards pass unchanged. Handoff: "no PHP behaviour changed"; "Demo seeder: see T22".
- **First checkpoint.** As above: record the green value first.
- **Size.** ~250–400 authored lines (plan; medium), excluding manifest JSON. The select, fixture and first admission cannot be shown apart;
  the total-dialog measurement is the first item to move to T18 if the diff passes ~400 lines.
- **Limits.** No second page admitted; no `FormSelect` markup change unless `contentClass` overrides colours (*read first*); no bootstrap extraction.
- **Acceptance record.** Required at close: R24 owner evidence, R21–R26 for the two surfaces, S20, S22, S26 start, remainder owners above.

### T18 Show the reception list in the landing identity

- **Outcome.** `Receptions/Index` appears in the landing identity with its rows and empty state, and has a page spec.
- **Requirements/scenarios.** R21, R22, R25, R26 (`Receptions/Index.spec.ts`); S19. Remainder: closure T23.
- **Plan sections.** Architecture overview (C1 row); R22; Test plan; D8.
- **Files/surfaces reserved.** `lib/pilotTheme.ts` row, `app.css` `/* 005 T18 … */` only if a probe fails, the three admission specs,
  new `pages/Receptions/Index.spec.ts`, case additions in `rest-c1-landing-visual.ts` and manifest `rest-c1-landing-receptions-list.visual.json`.
- **Dependencies.** T17 (fixture, select).
- **Done when.**
  - `Index.spec.ts` fails first, then passes: title, rows, empty state (`ReceptionPageTest.php:254-255,304` named as the PHP source of the component name).
  - 375/1280 px light/dark plus 1024 px/200 %: landing carrier count 1, `.pilot-theme:not(.landing)` 0, contrast, 44 px row links and filters, no overflow; empty, one row, many rows and long supplier name keep text and fit.
  - R25 guards unchanged. Handoff: "no PHP behaviour changed".
- **First checkpoint.** Admission spec row flips from `''` to `'landing'` (failing first).
- **Size.** ~100–200 lines (plan; medium). **Limits.** No new component; `pickup/*Lines` tables are measured, rewritten only under `.landing`.
- **Acceptance record.** R21/R22/R25/R26 for `Receptions/Index`, S19 part, evidence, verdict, remainder T23.

### T19 Show the finance basket list and detail in the landing identity

- **Outcome.** A finance user sees `Finance/Baskets/Index` and `Show` in the landing identity, amounts to the cent in tabular figures.
- **Requirements/scenarios.** R21, R22, R25 for the two pages; S19 baskets. Existing `Baskets/Index.spec.ts` and `Show.spec.ts` stay green. Remainder: `Create` T20; closure T23.
- **Plan sections.** R22 (basket list/form/detail with amounts); R25; Test plan; D8.
- **Files/surfaces reserved.** `lib/pilotTheme.ts` (two rows), `app.css` `/* 005 T19 … */` (row layout in `components/baskets/*`, money columns), admission specs, `rest-c1-landing-baskets.visual.json`, fixture states (empty, long name, large amount, sent, unsent).
- **Dependencies.** T17.
- **Done when.**
  - Both pages at 375/1280 px light/dark plus 1024 px/200 %: money cells right-aligned and `font-variant-numeric: tabular-nums` (probe which cells carry `pilot-amount`), "Sent"/"Unsent" present as text, no overflow, 44 px targets, contrast 4.5.
  - Amounts shown equal the fixture's cents (no rounding change); `BasketPagesTest.php` and `FinanceBasketAccessTest.php` pass unchanged.
- **First checkpoint.** Probe the money column before CSS; record the failing value if any.
- **Size.** ~150–250 lines (plan; medium). **Limits.** No change to `financeBasketRow.ts` logic.
- **Acceptance record.** As T18 for the two pages, S19 baskets.

### T20 Show the new finance basket form in the landing identity

- **Outcome.** `Finance/Baskets/Create` appears in the landing identity, with the household select and amount fields.
- **Requirements/scenarios.** R21, R22, R24 (reuse), R25, R26 (`Finance/Baskets/Create.spec.ts`); S19, S20 (second select user). Remainder: closure T23.
- **Plan sections.** R22 (select and amount fields first); R24; Test plan.
- **Files/surfaces reserved.** `lib/pilotTheme.ts` row, `app.css` `/* 005 T20 … */`, admission specs, new `pages/Finance/Baskets/Create.spec.ts`, `rest-c1-landing-basket-form.visual.json`.
- **Dependencies.** T17 (select).
- **Done when.**
  - `Create.spec.ts` fails first, then passes: title, one primary action, household select, error through `InputError` (`FinanceBasketAccessTest.php:49` names the component).
  - Select and amount field at 375/1280 px light/dark, invalid, server-error and busy-submit states; the popup is the one from T17 (no select edit); keyboard case reused on this page; no overflow, 44 px, contrast.
- **First checkpoint.** Failing spec first. **Size.** ~100–180 lines. **Limits.** No `ui/select` edit.
- **Acceptance record.** R24 reuse evidence, R26, S19/S20 part.

### T21 Show the finance stock list and movement history in the landing identity

- **Outcome.** `Finance/Stock/Index` and `Finance/Stock/Movements` appear in the landing identity; `MovementHistory` on the still old-theme pages is unchanged.
- **Requirements/scenarios.** R21–R23, R25, R26 (`Finance/Stock/Movements.spec.ts`); S19; S26 (leakage probe). Remainder: C2 consumer `Manage/Stock/Movements` T28.
- **Plan sections.** R22 (finance stock and history); R23; probe table (C1 row); Test plan.
- **Files/surfaces reserved.** `lib/pilotTheme.ts` (two rows), `app.css` `/* 005 T21 … */`, admission specs, new `pages/Finance/Stock/Movements.spec.ts`, `rest-c1-landing-stock.visual.json`. **Re-point first** the slice 1 and 2 history probes that cite `Finance/Stock/Movements` (`catalogue-landing-visual.ts`, `producer-landing-visual.ts` and their manifests) to `Manage/Stock/Movements` (D14, plan probe table), before the row merges.
- **Dependencies.** T17.
- **Done when.**
  - Re-pointed probes run green on the base before the row is added, and green after.
  - `Movements.spec.ts` fails first: title, `MovementHistory`, entry/adjust controls only for the permitted role.
  - Both pages measured per the method; `MovementHistory` computed values on `Manage/Stock/Movements` and `Producer/Stock/Movements` equal their baseline (S26); tabular quantities; no overflow.
  - `StockMovementHistoryPagesTest.php`, `RolePermissionMatrixTest.php` pass unchanged.
- **First checkpoint.** Probe re-pointing green before admission. **Size.** ~200–350 lines (plan; medium, likely to exceed). **Limits.** No `MovementHistory` markup change unless unmigrated consumers' values are shown unchanged.
- **Acceptance record.** R21–R23 for two pages, S19, S26 part; remainder T28.

### T22 Show the Finance and reception states in the demo seeder, if a read shows a gap

- **Outcome.** The demo shows a sent and an unsent basket, reception states and sold-out stock with a long history, or the handoff says why none is needed.
- **Requirements/scenarios.** R27 (C1 states); supports S19. Remainder: none.
- **Plan sections.** Seeder decision, slice 3-7 (row 1); D20.
- **Files/surfaces reserved.** Read `database/seeders/DemoSeeder.php` and `tests/Feature/Database/DemoSeederTest.php` first. Edit them only if a state is absent.
- **Dependencies.** None.
- **Done when.**
  - The read confirms or refutes each state (member has both a sent and an unsent basket; reception states; sold-out and long history). All present: no code change, the handoff records the evidence and the task closes as "no change".
  - Otherwise: failing assertion per missing state, seeded after the cycles through existing use cases, `DemoSeederTest.php` whole file green under `PGTZ=UTC`, idempotent.
- **First checkpoint.** The read. **Size.** 0–120 lines (PHP-only exception). **Limits.** No new use case, migration or domain change; cycle, reception and dashboard assertions unchanged.
- **Acceptance record.** R27 C1 part with the read evidence or assertions.

### T23 Record checkpoint C1 acceptance and request visual approval

- **Outcome.** One acceptance record maps C1's R20–R30 allocations and S19, S20, S22, S26, S27, S30 to task, evidence, verdict and remainder, and the user has been asked for visual approval of the seven C1 pages and the total dialog in light and dark.
- **Requirements/scenarios.** R20, R27, R30; S19, S20, S26, S27, S30 for C1.
- **Plan sections.** R20, R27, R30; Visual evidence plan (C1 row); D22.
- **Files/surfaces reserved.** History entries only (Q&A for approval; Deviations/Changes if any); `tasks.md` status. No code unless a correction is found.
- **Dependencies.** T17–T22 integrated.
- **Done when.**
  - C1 manifests plus the slice 1 and slice 2 manifests run on the integrated base SHA (the select and `MovementHistory` changed); one old-theme page of C2 and one of C3 unchanged (S26).
  - Per page R5–R9 as measured text (no screenshots unless an unexplained failure); full Vitest suite and the C1 PHP guards green; `DemoSeederTest.php` green.
  - Approval requested of the user; logged only after the decision. Until then "awaiting approval" and C2 does not start (S30).
- **First checkpoint.** Run the shared C1 manifest on the integrated base. **Size.** Documentation and evidence only. **Limits.** Does not approve on the user's behalf; no C2 work.
- **Acceptance record.** This task's output is the C1 record; verdict "pending approval" until the user answers.
- **Status.** Awaiting approval. Record (R20, R21–R26, R27, R30; S19, S20, S22, S26, S27, S30; R28, R29 have no C1 allocation; per-page R5–R9 text, manifest counts 122 + 272 + 146, Vitest 506, Pest guards green) is in the Q&A cited in the status line at the top; verdict pending approval, remainder owner: the user's decision, then T24.

## Checkpoint C2 — Manager administration (T24–T30)

PHP guards for C2: `tests/Feature/Manage/RolesPageTest.php`, `tests/Feature/Membership/HouseholdPagesTest.php`,
`tests/Feature/Membership/AccountPagesTest.php`, `tests/Feature/Ordering/CyclesPagesTest.php`,
`tests/Feature/Stock/StockMovementHistoryPagesTest.php`, `tests/Feature/Http/RolePermissionMatrixTest.php`. Probe pages still old-theme while C2 runs:
`Wallet/Statement`, `Member/Baskets/Index`, `settings/Profile`, `auth/Login`, `Dashboard` (plan probe table).

### T24 Show the roles and accounts pages in the landing identity, with fixture and probes re-pointed

- **Outcome.** A manager sees `Manage/Roles/Index` (with the holder-removal dialog) and `Manage/Accounts/Index` in the landing identity; the slice 1 and 2 probes that named `Manage/Accounts/Index` now point to later pages. Tracer of C2.
- **Requirements/scenarios.** R21–R26 for the two pages and `RoleHolderRemove`; R26 (`Roles/Index.spec.ts`); S21 start; S22 holder removal (destructive control not primary); S26 start. Remainder: T25–T28; closure T30.
- **Plan sections.** Architecture overview (C2 row, Correction 6); R22 (account tables, role groups); R24; Test plan; D14, D23.
- **Files/surfaces reserved.** `lib/pilotTheme.ts` (two rows; `Manage/Roles/Index` added to **both** sets), `app.css` `/* 005 T24 … */`, admission specs, new `pages/Manage/Roles/Index.spec.ts`, `rest-c2-landing.html`, `rest-c2-landing-visual.ts`, `rest-c2-landing-shared.visual.json`, `rest-c2-landing-roles-accounts.visual.json`; probe re-pointing in `catalogue-landing-visual.ts`, `producer-landing-visual.ts` and their manifests (select/`ActiveSwitch` control `Manage/Accounts/Index` → `Wallet/Statement`).
- **Dependencies.** C1 approved (T23 approval entry).
- **Done when.**
  - OQ8 inventory re-run recorded. Re-pointed controls run green on the base **before** the rows merge, green after.
  - `Roles/Index.spec.ts` fails first: role with no holder shows text, remove control present; `RoleHolderRemove.spec.ts` stays green.
  - First checkpoint: `Manage/Roles/Index` at 375 px light measures the global look today (record), then landing blue `rgb(40, 101, 133)` after admission.
  - Both pages measured per the method plus 1024 px/200 %; deactivated account row reads as text; `ActiveSwitch` and select unchanged in markup; holder-removal dialog by keyboard, destructive action resolves to `--destructive`, not primary (S22); invalid/busy states keep text and contrast.
  - Select popup on `Manage/Accounts/Index` now landing, from T17's change (no select edit).
  - Guards pass unchanged. Handoff: "no PHP behaviour changed"; "Demo seeder: see T29".
- **First checkpoint.** As above. **Size.** ~250–400 lines (plan; medium, likely to exceed): split the fixture/probe re-pointing from the roles page if it passes ~400.
- **Limits.** No renaming of roles copy; no change to `RoleGroup` logic.
- **Acceptance record.** R21–R26 for two pages, S21 start, S22 holder removal, S26 start, remainder owners above.

### T25 Show the households and the settlement details in the landing identity

- **Outcome.** `Manage/Households/Index` and `Show` and the `SettlementDetails` overlay appear in the landing identity; unpaid and settled balances read as text plus amount.
- **Requirements/scenarios.** R21, R22, R24, R25, R26 (`Households/Index.spec.ts`, `Households/Show.spec.ts`, `SettlementDetails.spec.ts`); S21; S22 settlement. Remainder: closure T30; seeded balances T29.
- **Plan sections.** R22 (household detail with settlement); R24; Test plan.
- **Files/surfaces reserved.** `lib/pilotTheme.ts` (two rows), `app.css` `/* 005 T25 … */`, admission specs, the three new specs, `rest-c2-landing-households.visual.json`.
- **Dependencies.** T24.
- **Done when.** Specs fail first (deactivated row text, settlement shown, `SettlementDetails` by keyboard); both pages and the overlay measured per the method (deactivated household, unpaid, settled, long names, select on `Show`); money to the cent unchanged; `HouseholdPagesTest.php` unchanged.
- **First checkpoint.** Failing specs. **Size.** ~250–400 lines (plan; medium, likely to exceed). **Limits.** No `SettlementDetails` markup change beyond classes under the variant.
- **Acceptance record.** As T24 for these surfaces.
- **Status.** Implemented (DRA-302): admission rows, `/* 005 T25 */` (tabular balances, wrapping status pill), three specs, `rest-c2-landing-households.visual.json`. `SettlementDetails` is inline, not an overlay, and the variant-off state is left to T30 (Deviations DRA-302 `37829198`, `76bfd001`). Remainder: closure T30; seeded balances T29.

### T26 Show the producer table and the manager settings in the landing identity

- **Outcome.** `Manage/Producers/Index` and `Manage/Settings` appear in the landing identity with their select and invalid/server-error states.
- **Requirements/scenarios.** R21, R22, R24, R25, R26 (`Producers/Index.spec.ts`, `Settings.spec.ts`); S21. Remainder: closure T30.
- **Plan sections.** R22 (producer table, settings form); R24; Test plan.
- **Files/surfaces reserved.** `lib/pilotTheme.ts` (two rows), `app.css` `/* 005 T26 … */`, admission specs, the two specs, `rest-c2-landing-producers-settings.visual.json`.
- **Dependencies.** T24.
- **Done when.** Specs fail first; the producer table at 375 px has no overflow; settings form invalid and server-error states keep text and contrast; select popup is T17's; guards unchanged.
- **First checkpoint.** Failing specs. **Size.** ~150–250 lines. **Limits.** None beyond the contract.
- **Acceptance record.** As T24 for these pages.

### T27 Show the cycles and their dialogs in the landing identity

- **Outcome.** `Manage/Cycles/Index` and `Show`, `CycleCreateDialog` and `CycleManageDialog` appear in the landing identity. The slice 1 and 2 dialog probe that named `Manage/Cycles/Index` is re-pointed first.
- **Requirements/scenarios.** R21, R22, R24, R25, R26 (`CycleCreateDialog.spec.ts`); S21; S22 cycle dialogs. Remainder: closure T30.
- **Plan sections.** R22 (cycle list/detail and dialogs); R24; probe table; D14; Test plan.
- **Files/surfaces reserved.** `lib/pilotTheme.ts` (two rows), `app.css` `/* 005 T27 … */`, admission specs, new `CycleCreateDialog.spec.ts`, `rest-c2-landing-cycles.visual.json`; probe re-pointing of the `NativeDialog` control (`Manage/Cycles/Index` → `Wallet/Statement` dialog-free page or `settings/Profile` delete dialog, *to confirm*).
- **Dependencies.** T24.
- **Done when.** Re-pointed dialog control green before and after; `CycleCreateDialog.spec.ts` fails first; cycle states (unsent, sent, closed), busy and disabled actions as text; both dialogs by keyboard (focus trap, Escape, return); `CyclesPagesTest.php` unchanged; existing `Cycles/Index.spec.ts`, `Show.spec.ts`, `CycleManageDialog.spec.ts` green.
- **First checkpoint.** Probe re-pointing green before admission. **Size.** ~250–400 lines (plan; medium). **Limits.** `NativeDialog` markup unchanged unless probe shows unmigrated consumers unchanged.
- **Acceptance record.** As T24 for these surfaces.

### T28 Show the manager stock movements and the stock edit dialog in the landing identity

- **Outcome.** `Manage/Stock/Movements` appears in the landing identity and `StockEditDialog` (already under the landing `Manage/Stock/Index`) is measured and covered by a spec. The last unmigrated `MovementHistory` consumer moves.
- **Requirements/scenarios.** R21–R26 (`StockEditDialog.spec.ts`, Correction 2); S21; S22 stock edit; S26 (last probe). Remainder: closure T30.
- **Plan sections.** R22; R23; probe table (C2 row); Correction 2; Test plan.
- **Files/surfaces reserved.** `lib/pilotTheme.ts` row, `app.css` `/* 005 T28 … */`, admission specs, new `pages/Manage/Stock/StockEditDialog.spec.ts`, `rest-c2-landing-stock.visual.json`; probe re-pointing of the `MovementHistory` control (`Manage/Stock/Movements` → none: the shared-consumer check ends here and is replaced by the before/after on `Producer/Stock/Movements`).
- **Dependencies.** T24.
- **Done when.** Spec fails first; `Manage/Stock/Movements` measured (51+ movement history from slice 2 seeder); `StockEditDialog` by keyboard; no unmigrated `MovementHistory` consumer remains (`rg` recorded); `StockMovementHistoryPagesTest.php` unchanged; `Manage/Stock/Movements.spec.ts` green.
- **First checkpoint.** Failing spec. **Size.** ~150–300 lines. **Limits.** None beyond the contract.
- **Acceptance record.** As T24; S26 probe ended.

### T29 Show the Manager states in the demo seeder

- **Outcome.** The demo shows a deactivated household, a deactivated member account, a role with no holder and, if a read shows them absent, one settled and one unpaid household.
- **Requirements/scenarios.** R27 (C2 states); supports S21, S22. Remainder: none.
- **Plan sections.** Seeder decision, slice 3-7 (rows 2–5); D20.
- **Files/surfaces reserved.** `database/seeders/DemoSeeder.php` (new method after the cycles, inside the transaction) and `tests/Feature/Database/DemoSeederTest.php` only. Existing deactivation use cases in `src/Membership/Application` (*names to confirm when reading*).
- **Dependencies.** None.
- **Done when.** One failing assertion per state; seeded through existing use cases; one role left without a holder and asserted; counts updated; `'manager'` path list extended with each new C2 URL; whole file green under `PGTZ=UTC`; idempotent on second run; cycle, reception and dashboard assertions unchanged.
- **First checkpoint.** Failing assertion for "a deactivated household exists". **Size.** ~100–200 lines (PHP-only exception). **Limits.** No new use case, migration or domain change.
- **Acceptance record.** R27 C2 part with assertions.

### T30 Record checkpoint C2 acceptance and request visual approval

- **Outcome.** One acceptance record for C2 (nine pages, five overlays, S21, S22) and a request for visual approval in light and dark.
- **Requirements/scenarios.** R20, R27, R30; S21, S22, S26, S27, S30 for C2.
- **Plan sections.** R20, R27, R30; Visual evidence plan (C2 row); D22.
- **Files/surfaces reserved.** History entries; `tasks.md` status.
- **Dependencies.** T24–T29 integrated.
- **Done when.** As T23 for C2: C2, C1, slice 1 and slice 2 manifests on the integrated base SHA; one C3 and one C4 page unchanged (S26); multi-role navigation case; full Vitest and C2 PHP guards green; `DemoSeederTest.php` green; approval requested, never recorded by the task.
  - Task deviation (DRA-300): decide the variant-off fixture state for C1 and C2 pages (page-task contract point 7), citing DRA-294 `177f0307` (6) and DRA-300 `ca774442`.
- **First checkpoint.** Run the C2 shared manifest. **Size.** Documentation and evidence only. **Limits.** No C3 work.
- **Acceptance record.** C2 record; verdict "pending approval".
- **Status.** Awaiting approval. Record (R20, R27, R30; S21, S22, S26, S27, S30; R28, R29 have no C2 allocation; per-page R5–R9 text, manifest counts 187 + 122 + 260 + 144, Vitest 550, multi-role tracked case open (Deviation), Pest guards and `DemoSeederTest` green) is in the Q&A cited in the status line at the top; the DRA-300 task deviation is decided there (no variant-off fixture state, Deviation cited at the top); verdict pending approval, remainder owner: the user's decision, then T31.

## Checkpoint C3 — Wallet and member (T31–T33)

PHP guards for C3: `tests/Feature/Wallet/WalletPagesTest.php`, `tests/Feature/Stock/StockDiscoveryTest.php`, `tests/Feature/Sales/MemberBasketReadTest.php`,
`tests/Feature/Http/RolePermissionMatrixTest.php`. Probe pages still old-theme: `settings/Profile`, `auth/Login`, `Dashboard`.

### T31 Show the wallet statement to every audience in the landing identity

- **Outcome.** `Wallet/Statement` appears in the landing identity for a member, a manager and a finance user, amounts to the cent. Tracer of C3.
- **Requirements/scenarios.** R21, R22, R24 (reuse), R25 (multi-role, cents); S23 start; S26 start. Remainder: T32; closure T33.
- **Plan sections.** R22 (wallet statement); R25; Test plan; probe table (C3 row).
- **Files/surfaces reserved.** `lib/pilotTheme.ts` row, `app.css` `/* 005 T31 … */`, admission specs, `rest-c3-landing.html`, `rest-c3-landing-visual.ts`, `rest-c3-landing-shared.visual.json`, `rest-c3-landing-wallet.visual.json`; re-point of any control that named `Wallet/Statement` (D14; *read first*).
- **Dependencies.** C2 approved (T30 approval entry).
- **Done when.** OQ8 inventory re-run recorded; statement measured with empty, negative balance, long lines, running balance in tabular figures; select popup is T17's; one navigation case reaches the page as member, manager and finance with the variant derived by page name; `WalletPagesTest.php` unchanged; existing `Statement.spec.ts` green.
- **First checkpoint.** Record the global look first, then landing blue. **Size.** ~200–350 lines. **Limits.** No calculation or formatting change.
- **Acceptance record.** R21/R22/R24/R25 for the page, S23 start.

### T32 Show the member baskets and the stock list in the landing identity

- **Outcome.** `Member/Baskets/Index`, `Member/Baskets/Show` and `Stock/Index` appear in the landing identity; sent and unsent baskets read as text; `Stock/Index` has a spec.
- **Requirements/scenarios.** R21, R22, R25, R26 (`Stock/Index.spec.ts`); S23. Remainder: closure T33.
- **Plan sections.** R22 (stock list, member baskets); Correction 6; D23; Test plan.
- **Files/surfaces reserved.** `lib/pilotTheme.ts` (the two `Member/Baskets/*` names added to **both** sets; `Stock/Index` row), `app.css` `/* 005 T32 … */`, admission specs, new `pages/Stock/Index.spec.ts`, `rest-c3-landing-member.visual.json`.
- **Dependencies.** T31.
- **Done when.** `Stock/Index.spec.ts` fails first (title, list, empty state, no write control); pages measured with empty, long names, sent and unsent baskets; the existing member basket specs green; `StockDiscoveryTest.php`, `MemberBasketReadTest.php` unchanged.
- **First checkpoint.** Failing spec. **Size.** ~200–300 lines. **Limits.** No new pages or routes.
- **Acceptance record.** R21/R22/R25/R26 for three pages, S23.

### T33 Record checkpoint C3 acceptance and request visual approval

- **Outcome.** One acceptance record for C3 and a request for visual approval.
- **Requirements/scenarios.** R20, R27, R30; S23, S26, S27, S30 for C3.
- **Plan sections.** R20, R27, R30; Visual evidence plan (C3 row); D22.
- **Files/surfaces reserved.** History entries; `tasks.md` status.
- **Dependencies.** T31, T32 integrated.
- **Done when.** As T23 for C3; one C4 page (`settings/Profile` or `auth/Login`) unchanged; seeder handoff states "no change" with the read evidence for sent/unsent baskets and settled/unpaid balances (or points to T29); approval requested, never recorded by the task.
- **First checkpoint.** Run the C3 shared manifest. **Size.** Documentation and evidence only. **Limits.** No C4 work.
- **Acceptance record.** C3 record; verdict "pending approval".
- **Status.** Awaiting approval; C3 stays open. The authoritative record state is the Q&A named as current state in the C3 status line at the top. R20 pending (C3 open); R21, R22, R24, R25 partly met; R23 reported under S26; R26 met; R27 partly met (demo data met at demo level, measured text incomplete); R28, R29 have no C3 allocation; R30 met; S23 met (journey at 375 px light only); S26, S27 partly met; S30 pending approval. Per-page R5–R9 text, manifest counts 106 + 187 + 118 + 260 + 144 + 74 (`dashboard`), Vitest 560, Pest guards 31 and `DemoSeederTest` 20 green are in the measurements entry cited there; demo seeder: no change. Verdict pending approval. Remainder: the visual approval (user); for the unmeasured R5–R9 items, a new C3 task or the user's acceptance of the gap; the other open items stay with the user, except the C4 pages' own measurement (T39) and the full PHP suite (CI); then T34.

## Checkpoint C4 — Sign-in and settings (T34–T39)

PHP guards for C4: `tests/Feature/Auth/*`, `tests/Feature/Settings/SecurityTest.php`, `tests/Feature/Settings/ProfileUpdateTest.php`,
`tests/Feature/Membership/InvitationFlowTest.php` (it exercises `invitations/{token}`; whether it asserts the `auth/AcceptInvitation` component
name is *to confirm in T35*). Run unchanged; no test change to sign-in, two-factor, passkey or destinations. Probe pages still old-theme: `Dashboard`, `Welcome`.

### T34 Show the auth shell and the sign-in cards in the landing identity

- **Outcome.** `auth/Login`, `auth/ForgotPassword` and `auth/ResetPassword` appear in the landing identity on the landing paper background, light and dark; other layouts are unchanged. Tracer of C4.
- **Requirements/scenarios.** R21–R23, R25, R26 (`Login.spec.ts`, `ForgotPassword.spec.ts`, `ResetPassword.spec.ts`, `AuthSimpleLayout.spec.ts`); S24 start; S26 start. Remainder: T35–T37; closure T39.
- **Plan sections.** Architecture overview (auth shell gap); D15; R22 (sign-in cards); R23; Test plan; Visual evidence plan incl. first checkpoint C4.
- **Files/surfaces reserved.** `layouts/AuthLayout.vue` and `layouts/auth/AuthSimpleLayout.vue` (variant prop, provide, class on the `.material-auth` root, `usePilotVariantClass` for portalled overlays; empty string when absent), `lib/pilotTheme.ts` (three rows), `app.css` `/* 005 T34 … */`, admission specs, new `layouts/auth/AuthSimpleLayout.spec.ts`, three page specs, `rest-c4-landing.html`, `rest-c4-landing-visual.ts`, `rest-c4-landing-shared.visual.json`, `rest-c4-landing-signin.visual.json`.
- **Dependencies.** C3 approved (T33 approval entry).
- **Done when.**
  - OQ8 inventory re-run recorded. First checkpoint: `auth/Login` at 375 px light measures the card background and primary at the global look (record), then landing after the shell variant, with `settings/Profile` unchanged.
  - `AuthSimpleLayout.spec.ts` fails first: the carrier is present only when the variant is provided, unchanged otherwise.
  - Three pages at 375/1280 px light/dark plus 1024 px/200 %; invalid, server-error and busy submit states keep text and contrast; each page spec: title, one primary action, `InputError` after its field, busy disables submit; focus order by real Tab; reduced motion respected.
  - Auth to app navigation case (login → dashboard) with system preference; Pest `Auth/*` unchanged.
- **First checkpoint.** As above. **Size.** ~250–400 lines (plan; medium, likely to exceed): split the shell variant from the three cards if it passes ~400.
- **Limits.** No new layout (D15); `AppLayout` and `settings/*` shell unchanged.
- **Acceptance record.** R21–R23/R25/R26 for the shell and three pages, S24 start, S26 start.

### T35 Show verification, confirmation, two-factor challenge and invitation in the landing identity

- **Outcome.** `auth/VerifyEmail`, `ConfirmPassword`, `TwoFactorChallenge` and `AcceptInvitation` appear in the landing identity.
- **Requirements/scenarios.** R21, R22, R25, R26 (four page specs); S24. Remainder: closure T39.
- **Plan sections.** R22 (invitation); R25; Test plan.
- **Files/surfaces reserved.** `lib/pilotTheme.ts` (four rows), `app.css` `/* 005 T35 … */`, admission specs, four new specs, `rest-c4-landing-verification.visual.json`.
- **Dependencies.** T34.
- **Done when.** Specs fail first; pages measured with invalid code, server error, busy and expired link states; the two-factor code field is operable by keyboard and touch (44 px); `InvitationFlowTest.php` and `Auth/*` unchanged; the component-name assertion for `AcceptInvitation` is confirmed or added to a Pest test only if a gap is proven (then a Deviation).
- **First checkpoint.** Failing specs. **Size.** ~200–350 lines. **Limits.** No flow or copy change.
- **Acceptance record.** As T34 for four pages.

### T36 Show the profile, appearance and delete-account dialog in the landing identity

- **Outcome.** `settings/Profile`, `settings/Appearance` and the `DeleteUser` dialog appear in the landing identity; the appearance preference still offers light, dark and system and stores the same value.
- **Requirements/scenarios.** R21, R22, R25, R26 (`Profile.spec.ts`, `Appearance.spec.ts`); S25. Remainder: closure T39.
- **Plan sections.** R22 (profile, appearance, dialogs); R25 (appearance preference); R23 (settings layout is shared, `pilot-settings`); Test plan.
- **Files/surfaces reserved.** `lib/pilotTheme.ts` (two rows), `app.css` `/* 005 T36 … */`, admission specs, two new specs, `rest-c4-landing-profile.visual.json`.
- **Dependencies.** T34.
- **Done when.** `Appearance.spec.ts` fails first: three options present, stored value untouched; delete-account dialog by keyboard (focus trap, Escape, return), destructive action not primary; pages measured per the method incl. invalid and server-error; the settings layout carries no change in markup; `ProfileUpdateTest.php` unchanged.
- **First checkpoint.** Failing specs. **Size.** ~200–350 lines. **Limits.** Settings layout markup unchanged unless a probe shows unmigrated consumers unchanged.
- **Acceptance record.** As T34 for these surfaces, S25 part.

### T37 Show security, two-factor setup and passkeys in the landing identity

- **Outcome.** `settings/Security` with the `TwoFactorSetupModal`, `ManagePasskeys` and `PasskeyRegister` appear in the landing identity.
- **Requirements/scenarios.** R21, R22, R25, R26 (`Security.spec.ts`); S24 two-factor; S25. Remainder: closure T39.
- **Plan sections.** R22 (security, dialogs); D21; Test plan.
- **Files/surfaces reserved.** `lib/pilotTheme.ts` row, `app.css` `/* 005 T37 … */`, admission specs, new `Security.spec.ts`, `rest-c4-landing-security.visual.json`; existing `PasskeyRegister.spec.ts` and `PasskeyVerify.spec.ts` stay green.
- **Dependencies.** T34.
- **Done when.** `Security.spec.ts` fails first; the setup modal (QR, recovery codes) and the passkey list/add/remove measured from the fixture (WebAuthn data is not seeded, D21); modals by keyboard; codes use tabular figures and wrap without overflow at 375 px; `SecurityTest.php` unchanged.
- **First checkpoint.** Failing spec. **Size.** ~250–400 lines (plan; medium, likely to exceed). **Limits.** No change to WebAuthn or Fortify behaviour.
- **Acceptance record.** As T34 for the page and three overlays.

### T38 Show an account with two-factor in the demo seeder

- **Outcome.** The demo includes one account with two-factor enabled so `settings/Security` shows that state.
- **Requirements/scenarios.** R27 (C4 state); supports S25. Remainder: none.
- **Plan sections.** Seeder decision, slice 3-7 (row 6); D21.
- **Files/surfaces reserved.** `database/seeders/DemoSeeder.php`, `tests/Feature/Database/DemoSeederTest.php` only; the Fortify action for the secret and recovery codes (*to confirm when reading*).
- **Dependencies.** None.
- **Done when.** Failing assertion "an account has two-factor enabled"; seeded after the cycles through the Fortify action; no passkey record (D21); counts and path lists updated; whole file green under `PGTZ=UTC`; idempotent.
- **First checkpoint.** Failing assertion. **Size.** ~40–100 lines (PHP-only exception). **Limits.** No migration or auth change.
- **Acceptance record.** R27 C4 part with the assertion.

### T39 Record checkpoint C4 acceptance and request visual approval

- **Outcome.** One acceptance record for C4 (ten pages, four overlays, S24, S25) and a request for visual approval.
- **Requirements/scenarios.** R20, R27, R30; S24 (after hydration, OQ7), S25, S26, S27, S30 for C4.
- **Plan sections.** R20, R27, R30; Visual evidence plan (C4 row); D22.
- **Files/surfaces reserved.** History entries; `tasks.md` status.
- **Dependencies.** T34–T38 integrated.
- **Done when.** As T23 for C4; the auth shell change re-measured on one slice 1 and one slice 2 page; `Dashboard` and `Welcome` unchanged; system-appearance and reduced-motion cases; approval requested, never recorded by the task.
- **First checkpoint.** Run the C4 shared manifest. **Size.** Documentation and evidence only. **Limits.** No C5 work.
- **Acceptance record.** C4 record; verdict "pending approval".

## Checkpoint C5 — Dashboard, errors and promotion (T40–T45)

PHP guards for C5: `tests/Feature/Dashboard/DashboardTest.php` (*path to confirm*), `tests/Feature/Http/RolePermissionMatrixTest.php`. Fixture `rest-c5-landing`.

### T40 Show the dashboard in the landing identity

- **Outcome.** `Dashboard` appears in the landing identity per role; capability stays as 004 defined (no card added, removed or reordered).
- **Requirements/scenarios.** R21, R22, R28 (dashboard); S28 start. Remainder: T41, T42; closure T45.
- **Plan sections.** R28 (dashboard); Test plan; Visual evidence plan (C5 row).
- **Files/surfaces reserved.** `lib/pilotTheme.ts` row, `app.css` `/* 005 T40 … */`, admission specs, `rest-c5-landing.html`, `rest-c5-landing-visual.ts`, `rest-c5-landing-shared.visual.json`, `rest-c5-landing-dashboard.visual.json`; `Dashboard.spec.ts` stays green.
- **Dependencies.** C4 approved (T39 approval entry).
- **Done when.** OQ8 inventory re-run recorded; dashboard measured per role at 375/1280 px light/dark plus 1024 px/200 %; cards keep their links and order; `DashboardTest` unchanged.
- **First checkpoint.** Record the global look, then landing blue. **Size.** ~100–250 lines. **Limits.** No capability change (004).
- **Acceptance record.** R21/R22/R28 dashboard.

### T41 Show the error page, the first paint and the tab icons in the landing identity

- **Outcome.** The 403, 404 and 500 pages, the background before hydration and the tab icons use the landing palette, light and dark.
- **Requirements/scenarios.** R28 (error page, first paint, tab icons); S28. Remainder: promotion T44.
- **Plan sections.** R28; D16, D17; OQ4, OQ7; Test plan (error page).
- **Files/surfaces reserved.** `resources/views/errors/minimal.blade.php` (inline CSS, landing literals, `prefers-color-scheme`, no Vite assets, copy and `@yield` structure unchanged), `resources/views/app.blade.php` (fallback colours `#faf1dc`/`#1c201f`; links for `favicon.svg`, `favicon.ico`, `apple-touch-icon.png`), `public/favicon.svg`, `public/favicon.ico`, `public/apple-touch-icon.png`, new `tests/Feature/ErrorPagesTest.php` (*confirm none exists*), fixture cases in `rest-c5-landing-*.visual.json`.
- **Dependencies.** C4 approved.
- **Done when.** `ErrorPagesTest.php` fails first: the view renders for 403/404/500 with the landing literals and no illustration; computed styles read from the rendered views; `html` background at first paint on `/`, `/login`, `/dashboard` measured with the stylesheet unloaded, light and dark; icon dimensions and dominant colours read from the files as text; the `<head>` links all three icons.
- **First checkpoint.** Failing `ErrorPagesTest.php`. **Size.** ~100–250 lines plus binary icons. **Limits.** `resources/views/mail/**` untouched (Correction 5); no illustration; no new copy.
- **Acceptance record.** R28 error page, paint, icons; S28 part.

### T42 Cover the welcome page and its transition to the app

- **Outcome.** `Welcome` has a spec and its transition to the sign-in and app pages is measured without a flash; `WelcomePage.vue` is edited only if the measurement fails.
- **Requirements/scenarios.** R26 (`pages/Welcome.spec.ts`), R28 (transition); S24 (full), S28. Remainder: closure T45.
- **Plan sections.** R28 (transition); D18; Correction 3; Test plan.
- **Files/surfaces reserved.** New `pages/Welcome.spec.ts`, `rest-c5-landing-welcome.visual.json`; `components/WelcomePage.vue` only under a Deviation.
- **Dependencies.** C4 approved.
- **Done when.** `Welcome.spec.ts` fails first (renders `WelcomePage`: title, sign-in link); `WelcomePage.spec.ts` green; `getComputedStyle(html).backgroundColor` and the header/CTA colours match the landing tokens across `/` → `/login` → `/dashboard`, light and dark.
- **First checkpoint.** Failing spec. **Size.** ~50–150 lines. **Limits.** No restyle of `WelcomePage.vue` unless the measurement fails.
- **Acceptance record.** R26 `Welcome`, R28 transition, S24 full.

### T43 Remove the unused layouts

- **Outcome.** `layouts/auth/AuthCardLayout.vue`, `layouts/auth/AuthSplitLayout.vue`, `layouts/app/AppHeaderLayout.vue` and `components/AppHeader.vue` are gone, with nothing importing them.
- **Requirements/scenarios.** R29 (orphans); S29 start. Remainder: promotion T44.
- **Plan sections.** Architecture overview (Orphans for C5); R29 item 4; D17.
- **Files/surfaces reserved.** The four files, any spec that only covers them.
- **Dependencies.** C4 approved.
- **Done when.** `rg` over `resources` and `tests` re-run in the task finds no importer (a page added meanwhile that imports one is a Deviation); `vp check`, type check and the full Vitest suite pass; the build still emits.
- **First checkpoint.** The `rg` result recorded before deletion. **Size.** Deletions only. **Limits.** No other refactor.
- **Acceptance record.** R29 orphans with the `rg` evidence.

### T44 Promote the landing identity to the whole application

- **Outcome.** Every page, including the ones never migrated, uses the landing identity; the older green opt-in and the per-page admission are removed. The way back is one revert.
- **Requirements/scenarios.** R29 (promotion, inventory), R30; S29. Remainder: closure T45.
- **Plan sections.** R29 items 1–6; D17, D19; Test plan (inventory); Risks (global promotion).
- **Files/surfaces reserved.** `public/theme-colors.css` (`:root` palette), `resources/css/app.css` (token blocks under `.landing` / `.dark .landing` become global; rules kept, no renaming), `resources/js/lib/pilotTheme.ts` (remove `pilotPages`, `landingPages`, `pilotVariantFor`, `isPilotThemePage`), `layouts/AppLayout.vue`, `layouts/AuthLayout.vue`, `AppSidebarLayout.vue`, `AuthSimpleLayout.vue`, `composables/usePilotTheme.ts` (keep the class only where a rule needs it), specs of the removed functions and each page spec's `pilotVariantFor` assertion, the inventory spec (`layout-rules.spec.ts` or a new one, D19), `app.blade.php` fallbacks if T41 left any.
- **Dependencies.** T40–T43 integrated and C1–C4 approved.
- **Done when.**
  - Before values (computed colours and fonts) recorded for one page per checkpoint and for one slice 1 and one slice 2 page; after the change they are equal (S29), light and dark, plus a navigation case with the mobile menu open.
  - The inventory spec fails first (a page not resolving the landing identity), then passes: `resources/js/pages/**` and the overlay components, `app.blade.php` and `errors/minimal.blade.php`; `resources/views/mail/**` excluded.
  - Slice 1 and 2 manifests run on the integrated base after the change (R30). Everything lands in one change that reverts cleanly.
- **First checkpoint.** Record the before values. **Size.** Large but mechanical (~250–400 lines excluding deletions; *unverified*); if it exceeds the prompt, split deletions of dead helpers from the token move only if each part stays revertible alone.
- **Limits.** No renaming of `.pilot-*` or `.landing`; no copy, route or behaviour change.
- **Acceptance record.** R29 and S29 with before/after text, inventory result, remainder: none.

### T45 Record checkpoint C5 closure and request feature acceptance

- **Outcome.** One acceptance record for C5 and for the whole of slices 3-7, and a request for the user's visual approval; their approval allows the feature-wide acceptance review.
- **Requirements/scenarios.** R20, R27, R30; S27–S30 for C5; the feature-wide mapping R20–R30/S19–S30.
- **Plan sections.** R20, R27, R30; Visual evidence plan (C5 row); D22.
- **Files/surfaces reserved.** History entries; `tasks.md` status.
- **Dependencies.** T44 integrated.
- **Done when.** As T23 for C5 with all earlier manifests re-run on the integrated base; full Vitest, Pest and the seeder test green; approval requested, never recorded by the task; the feature-wide review (`acceptance` stage) starts only after approval.
- **First checkpoint.** Run all manifests on the integrated base SHA. **Size.** Documentation and evidence only. **Limits.** Does not approve on the user's behalf.
- **Acceptance record.** Closing record; verdict "pending approval".

## Coverage notes and settled decisions

1. **Plan allocation gaps filled.** The plan's slicing table omits R4 for T02–T08, R7 and R9 for T05, R5/R6/R8
   for T08, R5–R7 for T04 and R5 for T03; every page task here owns R4–R9 for its pages (allocation reading, no
   requirement change).
2. **T03 before T05/T06 is a hard dependency** (dialog styling measured once). Confirmed by the user, 2026-11-13.
3. **S2 allocation.** The plan assigns S2 to T09; T01 also carries a Manager-variant check so the fixture is built once.
   T09 still owns S2.
4. **Unverified items carried from the plan:** dialog inheritance in the top layer, `pilot-amount` cell coverage, switch
   contrast, seeder table names (`return_items`, `producer_cycle_availability`), runner path with no command run, and
   the ~150–170 case estimate (`run.mjs plan` decides). Pest test names and filters not run.
5. When drafted, no Linear publication, branch/worktree or implementation authorization was implied; acceptance on 2026-11-16 and the C1–C5 publication (see the status line) superseded this.
6. **Slice 2 decisions D6–D11 are accepted** by the user, 2026-11-15 (plan, "Decisions the plan proposes, slice 2"):
   D6 no `pilot-title-compact` on Producer pages unless a page measures other than 24 px (then a Deviation); D7 the three
   slice 1 probes naming Producer pages are re-pointed once, in T10, before any admission (history probe to
   `Manage/Stock/Movements` and `Finance/Stock/Movements`, dialog probe to `Manage/Cycles/Index`, `WeeklyProductFields`
   probe dropped); D8 one page or family per task with its own reversible `landingPages` entry; D9 new `producer-landing`
   fixture and manifests, no shared-bootstrap extraction; D10 a second inactive weekly product `Coca de sucre` on `forn@`
   instead of changing `Coca de recapte`; D11 "cycle with nothing to deliver" is not seeded (fixture and Vitest only).
7. **Older manifests left.** `dra-198`, `dra-205`, `dra-208`, `dra-209`, `reference-all-pages` and `reference-gates`
   `.visual.json` still list Producer pages; they are manual-only (no CI step runs them) and describe the pre-landing look, so
   no task edits them. The CI guard that changes is `lib/pilotTheme.spec.ts`.
8. **Seeder history size.** `ListStockMovements::PER_PAGE` is 50 (`src/Stock/Application/ListStockMovements.php:20`), so T15
   seeds 51 or more further movements on one existing stock product so the history spans a second page.
9. **Allocation convention for slice 2.** As in slice 1, each page task (T10–T14) owns R5–R9 and the R17 guard for its own
   pages even where the plan's slicing table lists only "R14–R16" or a subset; area-wide R15, S13, S17 and S18 close only in
   T16. The R19 seeder part is T15; approval is T16 and never a task's own decision.
10. **Unverified items carried from the slice 2 plan:** fixture mounting of the five non-tracer pages before their own task
    (title measurement in T10), dialog inheritance for the producer role, `pilot-amount` cell coverage in the stock table
    and cycle summary, seeder table names and counts, the stock-creation use case name, plan line numbers for the probe
    re-pointing, and the ~95–125 case estimate (`run.mjs plan` decides). No test, build or command was run.

11. **Slice 3-7 decisions D12–D23 and Corrections 1–7 are accepted** by the user, 2026-11-16 (plan acceptance entry; Corrections recorded as a
    Change). D12 select owner `ui/select/SelectContent.vue` (T17); D13 one fixture per checkpoint; D14 probes re-pointed in the admitting
    task before the row; D15 auth shell variant on `AuthLayout`/`AuthSimpleLayout` (T34); D16 error page inline CSS with palette literals (T41);
    D17 one reversible promotion change (T44, orphans in T43); D18 `Welcome` covered, not restyled (T42); D19 inventory spec at each
    checkpoint start; D20 seeder tasks per checkpoint with a gap (T22 conditional, T29, T38); D21 two-factor seeded, passkeys from the fixture;
    D22 one approval per checkpoint; D23 `Member/Baskets/*` and `Manage/Roles/Index` admitted with both sets in their page task.
12. **Allocation convention for slices 3-7.** As before, each page task owns R21, R22, R25 for its pages and R26 for the specs it names; the plan's
    slicing table lists R26 and R24 only where they first appear. Area-wide S26, S27, S30 close only in the checkpoint closure tasks.
13. **Size markers.** T17, T21, T24, T25, T27, T34, T37, T44 are the likeliest to pass the 400-line prompt; each names its split.
14. **T22 may close as "no change".** The seeder read decides; no ticket is needed if nothing is missing.
15. **Unverified items carried from the slice 3-7 plan:** the select popup's `contentClass` colour overrides, the probe re-pointing targets
    in T27 and T28, `pilot-amount` cell coverage in the basket, wallet and settlement tables, use-case names for the C2 seeder, the Fortify action
    for T38, the `DashboardTest`/`ErrorPagesTest` paths, the `AcceptInvitation` component-name assertion, and the case estimates
    (C1 90–120, C2 110–140, C3 45–60, C4 95–120, C5 50–70; `run.mjs plan` decides). No test, build or command was run.
16. **No tracker mapping yet.** Tickets for T17–T45 are not published; publication is a separate user decision after this breakdown is accepted.

## Linear mapping — slice 1

Published 2026-11-13 ([record](linear-publication.md)). Parent: DRA-270 (no task ID, no label).

| Task | Issue |
| --- | --- |
| T01 | DRA-271 |
| T02 | DRA-272 |
| T03 | DRA-273 |
| T04 | DRA-274 |
| T05 | DRA-277 |
| T06 | DRA-278 |
| T07 | DRA-275 |
| T08 | DRA-276 |
| T09 | DRA-279 |

## Linear mapping — slice 2

Published 2026-11-15 ([record](linear-publication.md#slice-2-producer-role-complete)). Parent: DRA-280 (no task ID, no label).

| Task | Issue   |
| ---- | ------- |
| T10  | DRA-281 |
| T11  | DRA-283 |
| T12  | DRA-284 |
| T13  | DRA-285 |
| T14  | DRA-286 |
| T15  | DRA-282 |
| T16  | DRA-287 |

## Linear mapping — slices 3-7, checkpoint C1

Published 2026-11-16 ([record](linear-publication.md#slices-3-7-checkpoint-c1-finance-and-reception)). Parent: DRA-288 (no task ID, no label).

| Task | Issue   |
| ---- | ------- |
| T17  | DRA-289 |
| T18  | DRA-291 |
| T19  | DRA-292 |
| T20  | DRA-293 |
| T21  | DRA-294 |
| T22  | DRA-290 |
| T23  | DRA-295 |

## Linear mapping — slices 3-7, checkpoints C2 to C5

Published 2026-11-17 ([record](linear-publication.md#slices-3-7-checkpoints-c2-to-c5)). Parents: DRA-296 (C2), DRA-297 (C3), DRA-298 (C4), DRA-299 (C5); none has a task ID or label.

| Task | Issue   | Task | Issue   |
| ---- | ------- | ---- | ------- |
| T24  | DRA-300 | T35  | DRA-312 |
| T25  | DRA-302 | T36  | DRA-313 |
| T26  | DRA-303 | T37  | DRA-314 |
| T27  | DRA-304 | T38  | DRA-310 |
| T28  | DRA-305 | T39  | DRA-315 |
| T29  | DRA-301 | T40  | DRA-316 |
| T30  | DRA-306 | T41  | DRA-317 |
| T31  | DRA-307 | T42  | DRA-318 |
| T32  | DRA-308 | T43  | DRA-319 |
| T33  | DRA-309 | T44  | DRA-320 |
| T34  | DRA-311 | T45  | DRA-321 |
