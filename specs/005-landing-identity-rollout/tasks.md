# 005 — Landing identity rollout: Catalogue role complete · Tasks

**Status: Accepted (2026-11-13, PR #418).** Accepting it authorized no implementation or ticket publication; the ticket batch is drafted in [ticket-drafts.md](ticket-drafts.md). Source: [accepted spec](spec.md) R1–R13/S1–S12
(slice 1 only); [plan](plan.md) (accepted, PR #417); [constitution](../constitution.md).
IDs are local to 005 and start at T01; 001 items are cited as "001 T84". This document authorizes no implementation, no
ticket publication and no Linear writes. Everything marked *unverified* was not run; this draft is from code reading
of the plan and spec only (worktree `origin/main`).

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

**Current dependency frontier (nothing merged on this base): T01 and T02.** After T01 integrates: T03, T04, T07, T08
(and T05/T06 once T03 integrates; confirmed hard dependency, Q&A 2026-11-13). T09 needs all of T02–T08.

Parallelism and hotspots (scheduling, not dependency edges): `resources/js/lib/pilotTheme.ts` (every task adds one
or two array entries: serialize merges, rebase conflicts are mechanical); `resources/css/app.css` `.landing` block
(T03–T08 may each add rules: reserve a distinct commented sub-block per task, e.g. `/* 005 T03 … */`, to avoid
textual conflicts); `lib/pilotTheme.spec.ts` and `composables/usePilotTheme.spec.ts` (same: one row per page);
`fixtures/catalogue-landing*.ts/.html` (T01 creates; later tasks only add states and a per-family manifest of their own);
`fixtures/landing.visual.json` (T01 only edits the control; nobody else touches it); `DemoSeeder.php` + its test (T02
only). Safe parallel set after T01: {T03, T04, T07, T08} are file-disjoint apart from the hotspots above; T05 and T06
share `StockProductFields`/field CSS and should not run simultaneously (T05 first, T06 probes the regression).

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
5. No Linear publication, branch/worktree or implementation authorization is implied; no tracker column exists yet.
