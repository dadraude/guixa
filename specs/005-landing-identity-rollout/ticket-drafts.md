# 005 Landing identity rollout, slice 1 (Catalogue role) — proposed Linear ticket batch

**Draft for review, nothing written to Linear.** Publishing needs your separate approval of this exact batch (profile: new tickets are ask-first).

**Source.** Merge commit `fa428042c7f51eae34cf48314a399cb9cb8734bc` on `origin/main` (verified contained in `origin/main`) of `https://github.com/dadraude/guixa`. Source links below use `SRC` = `https://github.com/dadraude/guixa/blob/fa428042c7f51eae34cf48314a399cb9cb8734bc/specs/005-landing-identity-rollout`. Files: `SRC/spec.md` (accepted, PR #416), `SRC/plan.md` (accepted, PR #417), `SRC/tasks.md` (PR #418).

**Target.** Workspace `draude`, team `DRA`, project `Coop` (read back and verify identities before any write). Proposed state `Backlog` for all ten issues. No assignee, cycle, priority, due date, milestone or `ready-for-agent` label (same as the 004 precedent). Discovery for duplicates (open 005/landing/Catalogue-identity titles in DRA/Coop) has NOT been run by this draft; the coordinator must do it before publishing.

## Decisions settled before review

1. **Tasks accepted.** `tasks.md` was accepted by the merge of PR #418; its status line is updated in the PR that adds this draft.
2. **Titles:** plain `Tnn — Title` (user choice, 2026-11-13), as 004 did next to 003.
3. **Parent `relatedTo`:** the three landing pilot tickets for 001 T83, T84 and T85 only, as context and never as blockers. The role-management parent is left out: no 003 task file maps it.
4. **No tracker identifier appears in any body** below. Linear relates issues mentioned by identifier in a parent body (004 precedent), so context issues are named by 001 task ID and linked only through the native `relatedTo` field. Read back the parent's relations after creation.
5. **Parent closure** follows the profile: the parent is set Done when every child is Done. T09 requests your visual approval, which is recorded separately in history; it is not a tracker completion criterion.

Duplicate search in DRA/Coop (2026-11-13, query "Catalogue landing", full pagination): no matching 005 parent or leaves. Bounded search, not a universal guarantee.

## Architecture overview (short)

A page joins the landing look only by an exact name in `landingPages` (`resources/js/lib/pilotTheme.ts`); the shell and portals already take the class (001 T84/T85, integrated). No Application behaviour changes. Per page: admit the page, measure R4-R9 for its declared states, fix only inside `.landing` selectors of `resources/css/app.css`, flip the Vitest assertions, add the page spec where none exists. T01 is the tracer: it creates the first fixture and the `catalogue-landing-shared` manifest, re-points the negative control and admits `Catalogue/Producers/Index`. Removing a `landingPages` entry is the reversible switch; no feature flag. T02 (demo seeder, PHP only) is independent. T09 is the acceptance record and the visual-approval request.

## Native graph proposal

Draft keys identify proposed issues only; replace with returned Linear IDs after publication. The parent has no task ID and no label. Every child has `parentId: P005`; no child is blocked by the parent. Relations are published only as native fields. No existing issue is reparented or edited.

| Draft key | Proposed title | `parentId` | `blockedBy` | Reason |
| --- | --- | --- | --- | --- |
| P005 | Landing identity: Catalogue role complete (slice 1) | none | none | Grouping outcome only; no executable ID. `relatedTo`: DRA-264, DRA-265, DRA-269 (context only). |
| T01 | T01 — Show the producer list in the landing identity, with admission contract and controls re-pointed | P005 | none | Tracer: first fixture, manifest, control and admission. |
| T02 | T02 — Show the Catalogue states in the demo seeder | P005 | none | Independent seeder data; no UI prerequisite. |
| T03 | T03 — Show a producer's products and the classification dialog in the landing identity | P005 | T01 | Needs T01's fixture, manifest skeleton and re-pointed control. |
| T04 | T04 — Show producer availability in the landing identity | P005 | T01 | Needs T01's fixture and control. |
| T05 | T05 — Show weekly product create and edit forms in the landing identity | P005 | T01, T03 | T01 fixture/control; T03 dialog styling that the Edit page reuses (hard dependency, confirmed by the user 2026-11-13). |
| T06 | T06 — Show stock product create and edit forms in the landing identity | P005 | T01, T03 | Same reasons as T05. T05 is a scheduling preference only (shared field CSS), not an edge. |
| T07 | T07 — Show the return-item list and forms in the landing identity | P005 | T01 | Needs T01's fixture and control. |
| T08 | T08 — Show the stock movement history in the landing identity | P005 | T01 | Needs T01's fixture and control. |
| T09 | T09 — Record slice acceptance and request visual approval | P005 | T02, T03, T04, T05, T06, T07, T08 | Needs every page admitted and the seeded states in place. (T01 is reached through T03-T08.) |

Checks: no cycles or self-links; every edge names a result the successor needs; T05/T06 hold both edges (T03 does not imply T01 in Linear, so both are listed explicitly, as requested). Every leaf reaches the feature gate T09 (T01 via T03-T08, T02 directly).

**Current frontier (nothing from 005 merged on this base): T01 and T02.** After T01 integrates: T03, T04, T07, T08 become dependency-ready; T05 and T06 additionally need T03. T09 needs T02-T08. Publication does not complete a prerequisite and does not admit any task. Hotspots are scheduling, not edges (tasks.md "Parallelism and hotspots"): `pilotTheme.ts` entries and its two spec lists (serialize merges), the `.landing` block of `app.css` (one commented sub-block per task), `DemoSeeder.php` (T02 only), and T05/T06 sharing field CSS.

Follow-up after publication (not done here): record the issue mapping in tasks.md and a `linear-publication.md` for 005, as 004 did.

---

## P005 — Landing identity: Catalogue role complete (slice 1)

**Proposed native fields:** team `DRA`; project `Coop`; `parentId: none`; `blockedBy: []`; `relatedTo: [DRA-264, DRA-265, DRA-269]` (context only); state `Backlog`; labels `[]`; assignee/cycle/priority/due date/milestone unset.

**Body (submit as the description):**

```markdown
**Human summary.** A person with the Catalogue role works through every page of the Catalogue area in the same blue, cream and Nunito identity as the public landing and the already migrated stock and orders screens, in light and dark appearance. This slice changes presentation only. Nothing a person can do changes: routes, props, validation, copy and permissions stay as they are.

**Scope.** Eleven pages: the producer list, a producer's products (with the classification dialog), producer availability, the weekly product create and edit forms, the stock product create and edit forms, the return-item list and forms, and the stock movement history. Managers see the same pages, so one approval covers both roles.

**Completion criteria.**
- T01 to T09 are integrated, and the acceptance record on the integrated base maps every requirement R1 to R13 and scenario S1 to S12 to a task, measured evidence and a verdict.
- All eleven pages share one identity (headings, background, primary button, field border, width, padding) and a page that is not migrated keeps its previous look, including after navigation.
- Measured contrast, 44 px targets, keyboard focus, layout at 375 px and 200 % text, and the empty, long, invalid, busy and inactive states are met on every page. Measurements are text, not screenshots.
- Existing tests of this family stay green and the demo seeder shows the Catalogue states.
- T09 has asked for the user's visual approval of the Catalogue pages in light and dark. The approval is recorded in the feature history, not in the tracker; until it is logged no later slice starts.

**Not in this slice.** Other roles' areas (roadmap slices 2 to 7), mail, error pages, first paint, favicon, global promotion of the style and the Dashboard restyle.

**Context.** Continues the landing rollout started by 001 T83 (Welcome), T84 (stock pilot) and T85 (member orders), which are linked to this issue as related context only. They are not prerequisites.

Sources: [spec](SRC/spec.md), [plan](SRC/plan.md), [tasks](SRC/tasks.md).

+++ Agent notes: grouping context

- The task registry in `tasks.md` stays authoritative; this parent has no task ID and carries no `ready-for-agent` label. Its status does not block children.
- Mixed look between merges is accepted (spec OQ1). Area-wide R3, S1, S2, S8 re-run, S9, S11 aggregate and S12 are completed only by T09, on the integrated base.
- Each task runs the same R10 guards unchanged and states "no PHP behaviour changed". Only T02 touches PHP (seeder).
- Approval is the user's decision, logged as a Q&A that names the evidence it covered. No task approves on the user's behalf.
+++
```

(Resolve `SRC` to the full URL when publishing; do not leave the placeholder.)

---

## T01 — Show the producer list in the landing identity, with admission contract and controls re-pointed

**Proposed native fields:** team `DRA`; project `Coop`; `parentId: P005`; `blockedBy: []`; `relatedTo: []`; state `Backlog`; labels `[]`; assignee/cycle/priority/due date/milestone unset.

```markdown
**Human summary.** A Catalogue or Manager account sees the producer list in the landing identity, in light and dark, with the shell and overlays. The unmigrated control page, the Welcome page and the three earlier landing pages look exactly as before. This is the tracer for every later page: it builds the first fixture and shared measurement manifest, and re-points the negative control.

**Acceptance (excerpt).**
- First checkpoint: with a deactivated producer first, the primary element at 375 px in light measures the old green `rgb(36, 107, 87)` (record the failure), then `rgb(40, 101, 133)` on `rgb(250, 241, 220)` after admission.
- Light and dark at 375 and 1280 px (plus 1024 px at 200 % text): one landing root, no non-landing pilot root, no Welcome root, no background image in the main content, contrast 4.5 (3 for borders and focus), targets at least 44 px, no horizontal overflow, real-Tab focus order.
- Empty, several, very long name and deactivated producer states: the text "Deactivated" is present and nothing clips.
- Producer list to the Accounts page and back, with the mobile sheet and with the account menu open, in light, dark and system preference: the control page keeps its previous green colours and the list recovers the landing colours.
- A Manager sees identical computed colours; one Manager-only unmigrated page is unchanged. Variant-off restores the exact pre-slice values.
- Existing page spec (5 cases) and the R10 guards pass unchanged.

**Owns:** R1, R2, R4 to R9 for this page; R3 (start); S4 producers portion; S8 first states; S2 Manager variant on this page; R10/R11 guards. **Remainder:** R3 complete and S1/S2/S8 final in T09; S4 products and return items in T03 and T07.

**Depends on:** nothing. The shell, tokens and portals already exist (001 T84/T85).

Sources: [task row](SRC/tasks.md#t01-show-the-producer-list-in-the-landing-identity-with-admission-contract-and-controls-re-pointed), [spec](SRC/spec.md) R1-R9, S2, S4, S8, [plan](SRC/plan.md) sections "Contract for admitting a page", R1-R9, "Visual evidence plan", D4, D5.

+++ Agent notes: executable handoff

- **Files.** Edit `resources/js/lib/pilotTheme.ts` (`landingPages` row), `resources/css/app.css` `.landing` sub-block (only if a probe fails), `resources/js/lib/pilotTheme.spec.ts` (about lines 184-185, 199, 212-215), `resources/js/composables/usePilotTheme.spec.ts` (about 250-253), `resources/js/fixtures/landing.visual.json` (the `catalogue-unmigrated` view and `data-s2-*` expectations re-pointed to `Manage/Accounts/Index`), `resources/js/fixtures/landing-visual.ts` (control wiring, about 114/126/185). New (planned, paths unverified; confirm against the `landing-orders-visual.ts` layout): `resources/js/fixtures/catalogue-landing.html`, `catalogue-landing-visual.ts`, `catalogue-landing-shared.visual.json`. History entries in their own files.
- **UI reuse.** `pages/Catalogue/Producers/Index.vue`, `StatusIndicator`, `ui/button`, `ui/badge`. No new component.
- **Vitest.** `pilotVariantFor('Catalogue/Producers/Index')` returns `'landing'`; near-miss names (`Catalogue/Producers/Index/Extra`, `Catalogue/Producers`, `Manage/Stock/Movements`) return `''`; the shell, dialog and sheet take `landing` for the page and lose it after leaving.
- **Execution contract (all 005 tasks).** Tests first; measurement not screenshots (AGENTS.md "Measure before screenshot"), explicit `cases`, run bound to a fixed base SHA in ignored `.atl/<task>/visual`. CSS only under `.landing` / `.dark .landing`; never `:root`, `public/theme-colors.css` or `.pilot-theme` base rules. No markup or prop change in shared components. R10 guards unchanged: `tests/Feature/Catalogue/CatalogueAreaTest.php`, `ProducerAvailabilityTest.php`, `tests/Feature/Manage/ReturnItemsPageTest.php`, `tests/Feature/Stock/StockMovementHistoryPagesTest.php`, `tests/Feature/Http/RolePermissionMatrixTest.php`, plus `layout-rules.spec.ts`. Closing: focused Vitest, `vp check --fix`, type check, candidate-bound CI, coverage total in the PR body. Handoff states "no PHP behaviour changed" and "Demo seeder: see T02". Retain the slice acceptance record in the handoff/PR.
- **Admission checklist (plan items 1-7).** Exact-name entry; `pilotPages` membership; page-level measurement before the entry merges; Vitest lists flipped with near-miss names showing `''`; control stays re-pointed; shared-consumer probe if shared CSS is touched; variant-off state restores prior values.
- **First checkpoint.** Record the green failure, admit, re-measure blue.
- **Size.** About 250-400 authored lines (medium confidence), excluding the manifest JSON. One larger tracer is justified: fixture, control re-pointing and first admission cannot be shown apart.
- **Limits.** No other page admitted. No shared-bootstrap prefactor (D5). Does not choose D3 beyond recording the probe.
+++
```

---

## T02 — Show the Catalogue states in the demo seeder

**Proposed native fields:** team `DRA`; project `Coop`; `parentId: P005`; `blockedBy: []`; `relatedTo: []`; state `Backlog`; labels `[]`; assignee/cycle/priority/due date/milestone unset.

```markdown
**Human summary.** After this task the demo data shows every Catalogue state that the page tasks need to inspect: a deactivated producer, an inactive weekly product, a product marked unavailable in the unsent cycle, and two return items (one inactive). The Catalogue demo account can open the pages that show them.

**Acceptance (excerpt).**
- Test "seeds the Catalogue states": one deactivated producer not passed to opening the cycle, one inactive product with no order lines, one product unavailable in the unsent cycle whose producer order is not sent, two return items of which one is inactive. The Catalogue demo account opens the producers page, its products, availability and the return-items page.
- Existing counts and the idempotency table list in `DemoSeederTest.php` are updated; dashboard assertions stay unchanged. The seeder is idempotent on a second run and the whole test file passes under `PGTZ=UTC`.

**Owns:** R13 (complete); supports S4 and S5. **Remainder:** none.

**Depends on:** nothing. PHP-only exception to the vertical rule: the data setup is the entire outcome and is independent of the presentation tasks.

Sources: [task row](SRC/tasks.md#t02-show-the-catalogue-states-in-the-demo-seeder), [spec](SRC/spec.md) R13, [plan](SRC/plan.md) section "Seeder decision".

+++ Agent notes: executable handoff

- **Files.** `database/seeders/DemoSeeder.php` and `tests/Feature/Database/DemoSeederTest.php` only (hotspot; no other 005 task edits them).
- **Use existing use cases** inside the existing transaction, after the cycles: `CreateProducer` + `DeactivateProducer` (`src/Catalog/Application`), `CreateProduct` + `DeactivateProduct`, `SetProductAvailability` on cycle `[2]` (`src/Ordering/Application`), `CreateReturnItem` + `DeactivateReturnItem` (`src/Stock/Application`).
- **Test edits.** Counts at about `DemoSeederTest.php:20-23`; idempotency table list (about `:199`) gains `return_items` and `producer_cycle_availability` (table names unverified: confirm in migrations); extend the `'receiver'` path list for the Catalogue demo account; dashboard assertions (about `:50-64`) unchanged.
- **First checkpoint.** Failing assertion "one deactivated producer exists"; seed it; inspect counts.
- **Size.** About 80-150 lines (medium confidence).
- **Limits.** No new use case, migration or domain change; no change to cycle or reception assertions.
+++
```

---

## T03 — Show a producer's products and the classification dialog in the landing identity

**Proposed native fields:** team `DRA`; project `Coop`; `parentId: P005`; `blockedBy: [T01]`; `relatedTo: []`; state `Backlog`; labels `[]`; assignee/cycle/priority/due date/milestone unset.

```markdown
**Human summary.** A producer's products page and the classification dialog it opens show the landing identity in light and dark. The toast after saving is readable. Dialog actions and copy do not change.

**Acceptance (excerpt).**
- Baseline at 375 and 1280 px, light and dark, plus 1024 px at 200 % text for the list and 375 px at 200 % for the dialog: contrast, 44 px targets, no overflow, tabular figures on quantity and price.
- An inactive product shows the text "Deactivated"; a long name wraps; Catalogue-only and Manager variants keep the same actions.
- Classify and delete dialogs show landing surface, ink and focus; Escape sends no request; focus returns to the opener; the dialog spec passes.
- Shared-consumer probe: the producer-facing product edit page and the cycle pages' dialogs keep their computed values.
- The page spec (5 cases) and the R10 guards pass; admission rows are flipped.

**Owns:** R1, R4 to R9 for the products page; S4 products portion; S7 on this page (keyboard open, Escape, focus return, toast); shell overlays (dropdown, sheet, tooltip) on a Catalogue page. **Remainder:** S7 on other dialogs in T05 and T06; S4 return items in T07.

**Depends on:** T01 (fixture, manifest skeleton and re-pointed control the measurements run against).

Sources: [task row](SRC/tasks.md#t03-show-a-producers-products-and-the-classification-dialog-in-the-landing-identity), [spec](SRC/spec.md) R1, R4-R9, S4, S7, [plan](SRC/plan.md) sections R1, R5-R9, "Visual evidence plan", "Shared consumers".

+++ Agent notes: executable handoff

- **Files.** `lib/pilotTheme.ts` row; `app.css` `.landing` sub-block for list and dialog (use a `/* 005 T03 ... */` comment block); the two Vitest lists; `catalogue-landing-lists.visual.json` (planned, or extend the shared manifest); fixture states. `NativeDialog` and `ProductAdministrationActions` markup is not edited (consumers: `Manage/Cycles/*`, `StockEditDialog`, `Producer/Products/Edit`).
- **UI reuse.** `Products.vue`, `ProductAdministrationActions.vue` (`NativeDialog`), `ActiveSwitch`, `StatusIndicator`, `ui/button`, `Heading`.
- **Unverified in plan.** Dialog inheritance inside the top layer (measure in browser); which cells carry `pilot-amount`.
- **First checkpoint.** `Products` at 375 px light with an inactive product and the classify dialog open: measure dialog background and button colour failing (green/default), then passing.
- **Size.** About 200-350 lines (medium). **Limits.** No Availability, forms or return items; no change to dialog copy or actions.
- Shared execution contract and R10 guards: as in T01 notes. Handoff: "Demo seeder: see T02".
+++
```

---

## T04 — Show producer availability in the landing identity

**Proposed native fields:** team `DRA`; project `Coop`; `parentId: P005`; `blockedBy: [T01]`; `relatedTo: []`; state `Backlog`; labels `[]`; assignee/cycle/priority/due date/milestone unset.

```markdown
**Human summary.** The producer availability page shows the landing identity with its own layout rules (it was in neither list before). Marking a product unavailable or available behaves exactly as before.

**Acceptance (excerpt).**
- Before the change, admitting the page to the landing set alone gives landing colours without the pilot layout rules (record the failing measurement); with both lists it matches the other Catalogue pages' layout (plan D1).
- States: producer deactivated (danger indicator and notice, as text), product available, unavailable ("Unavailable" and "Mark available"), change allowed and not allowed. State is conveyed beyond colour; targets at least 44 px; 375 and 1280 px light and dark; 1024 px at 200 % text without overflow.
- A toggle round trip through the real router sends the same request as before. The previous and next pages keep their own look (R2).
- The page spec (4 cases) passes; admission rows added.

**Owns:** R1, R2, R4 to R9 for this page; S5. **Remainder:** S8 re-run in T09.

**Depends on:** T01 (fixture and control).

Sources: [task row](SRC/tasks.md#t04-show-producer-availability-in-the-landing-identity), [spec](SRC/spec.md) R1, R2, R8, R9, S5, [plan](SRC/plan.md) sections R1, R2, R8, D1, Risks (layout gap).

+++ Agent notes: executable handoff

- **Files.** `lib/pilotTheme.ts` (both `pilotPages` and `landingPages`, D1); the two Vitest lists (add an `isPilotThemePage` row); `app.css` `.landing` sub-block if needed; `catalogue-landing-availability.visual.json` (planned); fixture states.
- **UI reuse.** `Availability.vue` (`min-h-11` buttons, `StatusIndicator`), `ui/button`.
- **First checkpoint.** Measure with the landing list only (layout missing), then with the pilot list added.
- **Size.** About 150-250 lines (medium). **Limits.** No change to the availability use case or copy.
- The unavailable-product demo state comes from T02 ("Demo seeder: see T02"); measurements use fixture states. Shared execution contract and R10 guards: as in T01 notes.
+++
```

---

## T05 — Show weekly product create and edit forms in the landing identity

**Proposed native fields:** team `DRA`; project `Coop`; `parentId: P005`; `blockedBy: [T01, T03]`; `relatedTo: []`; state `Backlog`; labels `[]`; assignee/cycle/priority/due date/milestone unset.

```markdown
**Human summary.** The weekly product create and edit forms show the landing identity. Fields, errors and the disabled submit meet the contrast and keyboard rules, and both pages get the page specs they lacked.

**Acceptance (excerpt).**
- New specs fail first, then pass: Create shows the title "New product", the submit "Create", the name error next to the Name input, and the submit disabled while processing. Edit shows prefilled fields, delete and classify only when allowed, and a dialog that opens and sends nothing on Cancel.
- Measured: each field has a persistent label and a border of at least 3:1 in light and dark (including the dark input background); error text contrast; error not clipped; the disabled submit is distinguishable by attribute and label; baseline plus 375 px at 200 % in the riskiest state; keyboard-only reading order with visible focus; 1024 px at 200 % form composition.
- Shared-consumer probe: the producer-facing product edit page is unchanged. R10 guards pass.

**Owns:** R1, R4 to R9 for both forms (R7 and R9 added to the plan's allocation); S3 weekly (empty, invalid, server error, keyboard-only); S7 on the edit page; S10 for the two new specs. **Remainder:** S3 stock and return items in T06 and T07; S7 stock in T06.

**Depends on:** T01 (fixture and control); T03 (dialog styling that the edit page reuses; hard dependency confirmed 2026-11-13).

Sources: [task row](SRC/tasks.md#t05-show-weekly-product-create-and-edit-forms-in-the-landing-identity), [spec](SRC/spec.md) R1, R4-R9, S3, S7, S10, [plan](SRC/plan.md) sections R5, R6, R8, "Test plan".

+++ Agent notes: executable handoff

- **Files.** `lib/pilotTheme.ts` rows (2); `app.css` `.landing` field sub-block; Vitest lists; new `pages/Catalogue/Producers/WeeklyProduct/Create.spec.ts` and `Edit.spec.ts` (planned); `catalogue-landing-forms.visual.json` (planned). `WeeklyProductFields.vue` markup is not edited (consumer: `Producer/Products/{Create,Edit}`).
- **UI reuse.** `WeeklyProductFields`, `ui/input`, `ui/label`, `InputError`, `ui/button`, `ProductAdministrationActions`.
- **Detail.** Input dark `dark:bg-input/30` and field border 3:1 (plan R5); `pilot-t44-page` (plan R6); `InputError` and busy state (plan R8).
- **First checkpoint.** Failing name-error spec for Create, then the measured Name field border on Create.
- **Size.** About 250-400 lines (medium). **Limits.** No copy or validation change; no Select.
- Scheduling: T05 and T06 share field CSS and should not run simultaneously (T05 first; T06 probes the regression). Not a blocking edge. Shared execution contract: as in T01 notes. "Demo seeder: see T02".
+++
```

---

## T06 — Show stock product create and edit forms in the landing identity

**Proposed native fields:** team `DRA`; project `Coop`; `parentId: P005`; `blockedBy: [T01, T03]`; `relatedTo: []`; state `Backlog`; labels `[]`; assignee/cycle/priority/due date/milestone unset.

```markdown
**Human summary.** The stock product create and edit pages (served to Catalogue by the stock product controller) show the landing identity and match the other Catalogue forms in palette and type. This task resolves the open divergence with the Manager shell (plan D3).

**Acceptance (excerpt).**
- Specs: Create shows the title, the create action and errors for name, unit, price and containers; the existing Edit spec (2 cases) stays green.
- A cross-page probe records heading family, size and weight, background, primary colour, field border, max width and padding for the stock pages against the weekly forms from T05. Any gap is equalised inside the landing selectors only, and the D3 outcome is recorded.
- Locked-unit state, all-errors state at 375 px and 200 %, a used product without classify actions, and the dialog on Edit: contrast, 44 px, no overflow. Shared-consumer probe: the stock movements page and the producer-facing product edit page are unchanged.

**Owns:** R1, R3 (D3 divergence resolved here), R4 to R9; S3 stock; S7 on the stock edit page; S10 for the stock create spec. **Remainder:** S3 return items in T07; area-wide R3 in T09.

**Depends on:** T01 (fixture and control); T03 (dialog styling; hard dependency confirmed 2026-11-13). T05 is a scheduling preference only.

Sources: [task row](SRC/tasks.md#t06-show-stock-product-create-and-edit-forms-in-the-landing-identity), [spec](SRC/spec.md) R1, R3, R4-R9, S3, S7, S10, [plan](SRC/plan.md) sections R3, D3, R8, "Test plan".

+++ Agent notes: executable handoff

- **Files.** `lib/pilotTheme.ts` rows (2; `Manage/Stock/Index` is already admitted and the near-miss `Manage/Stock/Movements` must stay `''`); `app.css` `.landing` sub-block; Vitest lists; new `pages/Manage/Stock/Create.spec.ts` (planned); `catalogue-landing-stock.visual.json` (planned). `AppLayout.vue` `managerTheme` is not edited (D3).
- **UI reuse.** `StockProductFields`, `ui/input`, `InputError`, `ProductAdministrationActions`. The Manager shell class applies here, not on `Catalogue/*`.
- **First checkpoint.** Failing Create error-display spec, then the D3 probe values.
- **Size.** About 150-300 lines (medium). **Limits.** No `managerTheme` extension; no change to `Manage/Stock/Index`.
- Shared execution contract: as in T01 notes. "Demo seeder: see T02".
+++
```

---

## T07 — Show the return-item list and forms in the landing identity

**Proposed native fields:** team `DRA`; project `Coop`; `parentId: P005`; `blockedBy: [T01]`; `relatedTo: []`; state `Backlog`; labels `[]`; assignee/cycle/priority/due date/milestone unset.

```markdown
**Human summary.** The return-item list and its create and edit forms show the landing identity. The table reflows on a phone, amounts align, and inactive items are legible. Three pages get the specs they lacked.

**Acceptance (excerpt).**
- Specs: Index shows rows with the switch text for active and inactive items, the empty text and the create link; Create and Edit show the title, primary action, and name and amount errors through the shared error component.
- Measured: the amount column uses tabular figures and aligns; an inactive item shows "Deactivated"; the empty state explains itself; switch track and thumb contrast in both appearances; 1024 px at 200 % table composition; no overflow; 44 px including the switch hit area.
- Shared-consumer probe: the Accounts page (which uses the same switch) is unchanged.

**Owns:** R1, R4 to R9; S3 return items; S4 table portion; S10 for the three new specs. **Remainder:** area-wide items in T09.

**Depends on:** T01 (fixture and control).

Sources: [task row](SRC/tasks.md#t07-show-the-return-item-list-and-forms-in-the-landing-identity), [spec](SRC/spec.md) R1, R4-R9, S3, S4, S10, [plan](SRC/plan.md) sections R7, R8, "Test plan".

+++ Agent notes: executable handoff

- **Files.** `lib/pilotTheme.ts` rows (3); `app.css` `.landing` sub-block; Vitest lists; new `pages/Manage/ReturnItems/{Index,Create,Edit}.spec.ts` (planned); `catalogue-landing-returns.visual.json` (planned). `ReturnItemFields.vue` markup is not edited.
- **UI reuse.** `ActiveSwitch`, `ReturnItemFields`, `InputError`, `pilot-list`, `pilot-data-table`, `pilot-cell-label`.
- **Unverified in plan.** Switch contrast rules (about `app.css:678-725`).
- **First checkpoint.** Failing Index inactive-text spec, then measured switch contrast.
- **Size.** About 250-400 lines (medium). **Limits.** No new page, field or action.
- Shared execution contract: as in T01 notes. "Demo seeder: see T02".
+++
```

---

## T08 — Show the stock movement history in the landing identity

**Proposed native fields:** team `DRA`; project `Coop`; `parentId: P005`; `blockedBy: [T01]`; `relatedTo: []`; state `Backlog`; labels `[]`; assignee/cycle/priority/due date/milestone unset.

```markdown
**Human summary.** The Catalogue stock movement history shows the same entries in the landing identity and stays read-only. The shared history component is used by three other, unmigrated pages, which must not change.

**Acceptance (excerpt).**
- Spec: the heading is the product name, the available quantity line and the history are shown, and there is no entry, adjustment or count control (001 R28a).
- Measured at baseline, at 1024 px with 200 % text, and with an empty history; tabular figures on quantity and balance.
- Mandatory leakage probe: the producer, Manager and Finance movement pages keep their computed values. A direct link to a quantity-changing action is still refused (existing movement-pages and permission-matrix tests unchanged).

**Owns:** R1, R4 to R9 (R5, R6, R8 added to the plan's allocation); S6; S10 for the movements spec. **Remainder:** none beyond T09 aggregation.

**Depends on:** T01 (fixture and control).

Sources: [task row](SRC/tasks.md#t08-show-the-stock-movement-history-in-the-landing-identity), [spec](SRC/spec.md) R1, R4-R9, S6, S10, [plan](SRC/plan.md) sections R7, "Shared consumers", "Test plan".

+++ Agent notes: executable handoff

- **Files.** `lib/pilotTheme.ts` row; `app.css` `.landing` sub-block; Vitest lists; new `pages/Catalogue/Stock/Movements.spec.ts` (planned); `catalogue-landing-history.visual.json` (planned). `MovementHistory.vue` is not edited (its spec, 6 cases, stays green).
- **UI reuse.** `MovementHistory`, `pilot-t44-page`.
- **First checkpoint.** Failing read-only spec, then the leakage probe values on the three consumers.
- **Size.** About 120-250 lines (medium). **Limits.** No history component change.
- Shared execution contract: as in T01 notes. "Demo seeder: see T02".
+++
```

---

## T09 — Record slice acceptance and request visual approval

**Proposed native fields:** team `DRA`; project `Coop`; `parentId: P005`; `blockedBy: [T02, T03, T04, T05, T06, T07, T08]`; `relatedTo: []`; state `Backlog`; labels `[]`; assignee/cycle/priority/due date/milestone unset.

```markdown
**Human summary.** On the integrated base, produce one acceptance record that maps every requirement R1 to R13 and scenario S1 to S12 to a task, measured evidence, a verdict and a remainder owner. Then ask the user for visual approval of the Catalogue pages in light and dark. This task writes no code unless a correction is found.

**Acceptance (excerpt).**
- All manifests run on the integrated base commit: the eleven pages share heading font family, size and weight, background, primary button colour, field border, max width and padding (R3 probe); navigation states with the sheet and the account menu pass in both modes (S8); the Manager variant of each page is identical (S2); Welcome and the three earlier landing pages pass.
- S9: existing guards cover the refused Receiver-only, producer-without-Catalogue and Catalogue-on-stock/cycle-settings URLs; add one assertion only for a proven gap.
- The full family suite is green: the page specs for producers, products, availability and stock edit, the movement-history and dialog specs, `layout-rules.spec.ts`, the pilot-theme specs, the PHP guards, `DemoSeederTest.php` and the seven new specs.
- The record lists R5 to R9 per page as measured text. The user is asked for approval; approval is logged only after their decision, as a Q&A naming the evidence covered. Until then the status is "awaiting approval" and no later slice starts.

**Owns:** R3 (area-wide), R10, R11, R12; S1, S2, S8 (re-run), S9, S10, S11 (aggregate), S12. Does not approve on the user's behalf.

**Depends on:** T02 to T08 integrated (all eleven pages admitted and the seeded states exist for review). T01 is reached through T03 to T08.

Sources: [task row](SRC/tasks.md#t09-record-slice-acceptance-and-request-visual-approval), [spec](SRC/spec.md) R3, R10-R12, S1, S2, S8-S12, [plan](SRC/plan.md) sections R2, R3, R10-R12, "Visual evidence plan".

+++ Agent notes: executable handoff

- **Files.** History entries only (Q&A for approval, plus Deviations or Changes if any) and `tasks.md` status edits. No code unless a correction is found (then record a Deviation and, if non-trivial, propose a new task).
- **First checkpoint.** Run the shared manifest on the integrated base and read the R3 probe table.
- **Size.** Documentation and evidence only. **Limits.** No slice 2.
- Documentation-only exception to the vertical rule: its output is the feature-slice record. Verdict stays "pending approval" until the user answers. Follow the feature-acceptance reference in the profile for the feature verdict, reported separately from the grouping parent's status.
+++
```
