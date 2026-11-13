# 005 — Landing identity rollout: Catalogue role complete · Plan

**Status: Accepted (2026-11-13, PR #417; D1–D5 confirmed by the merge).** Covers delivery slice 1 only ([spec](spec.md) R1–R13, S1–S12).
It authorizes no tasks, tickets or implementation. Slices 2–7 stay in [discovery](discovery.md). The visual direction
is the accepted 001 plan, "Llenguatge visual de la portada a tota l'aplicació" (`specs/001-weekly-orders/plan.md:1130`);
the landing (`resources/js/components/WelcomePage.vue`) wins if the two differ. Paths are relative to the repository
root unless marked *(planned)*. Everything marked *unverified* was not run or measured while drafting; no command, test
or browser check was executed for this document (code reading only, worktree `origin/main` at 667e3c03).

## Readiness for this plan

| Concern | State | Evidence and limit |
| --- | --- | --- |
| Behavior | Ready | Spec accepted 2026-11-13 (merge of PR #416); its status line, the README row and the two factual corrections below are applied in the same change as this plan. |
| Decisions | Ready, with 4 plan-level choices | OQ1/OQ2 closed (Q&A 2026-11-13T13:27). Choices D1–D4 below are design decisions this plan proposes; none changes a requirement. |
| Design | Ready once D1–D4 settle | Mechanism, tokens and overlays already exist (T84/T85). New work is admission, per-page measurement, small `.landing` CSS adjustments and fixtures. |
| External prerequisites | Ready | None. No service, package, credential or migration. T84/T85 integrated; T85 visual approval granted 2026-11-13 (Q&A 13:16). |
| Paths | Ready | All 11 pages, shared components and routes verified below. New files are marked *(planned)*. |
| Verification | Ready, planned | Visual runner (`skills/agent-workflow/scripts/visual/run.mjs`), Vitest, Pest exist. Nothing has been run yet. |

### Spec corrections (applied with this plan; Change entry in history)

1. **`StockEditDialog` is not on any Catalogue page.** S7 says "today `StockEditDialog` on a producer's products" and R7
   names `StockEditDialog` as a composition. `StockEditDialog` is imported only by `resources/js/pages/Manage/Stock/Index.vue:9,272`,
   which is out of Scope. The overlay a Catalogue account actually opens is the `NativeDialog` inside
   `resources/js/components/ProductAdministrationActions.vue:68`, rendered by `Catalogue/Producers/Products.vue:195`,
   `Catalogue/Producers/WeeklyProduct/Edit.vue:82` and `Manage/Stock/Edit.vue:105` (classify / delete-unused
   confirmation). The plan measures that dialog for S7 and R7. Applied to `spec.md` S7 and R7.
2. **`components/ReturnItemFields.vue`** (used by `ReturnItems/Create.vue:6`, `Edit.vue:6`) is a Scope component the spec
   does not name; the plan includes it. Clarification only.
3. **R9 versus existing tokens.** `.pilot-theme.landing` maps `--pilot-warning` to ochre (`resources/css/app.css:2217,2223`).
   No Scope page renders a `warning` tone (the only tones are `success`/`danger`: `Catalogue/Producers/Index.vue:51`,
   `Availability.vue:49`), so R9 is not violated by this slice, but the rule is latent for later slices. No action here;
   recorded as a risk.

## Architecture overview (source-backed)

**What already works.** A page joins the landing look only through exact name in `landingPages`
(`resources/js/lib/pilotTheme.ts:54-58`, today `Manage/Stock/Index`, `Orders/Index`, `Orders/Edit`); `pilotVariantFor`
(`:60-62`) returns `'landing'`. `AppLayout.vue:11-13` derives `pilotTheme = isPilotThemePage(page.component)` and
`pilotVariant = pilotVariantFor(page.component)` and passes both to `AppSidebarLayout.vue`, which provides them
(`:33-34`) and writes `pilot-theme … landing` on the shell element (`:60`). `usePilotVariantClass`
(`composables/usePilotTheme.ts:33-36`) gives the class to the portalled `DialogContent`, `SheetContent`,
`DropdownMenuContent`/`SubContent`, `TooltipContent` and `Sonner` (grep of `components/ui/*`). The `<dialog>` of
`components/manager/NativeDialog.vue` is not portalled: it renders inside the shell DOM and inherits the custom
properties from `.landing` (top layer keeps DOM-ancestor inheritance; *unverified in browser*; T84 measured it for
`StockEditDialog`). `.landing` / `.dark .landing` redefine the shadcn tokens (`--background`, `--primary`, `--input` =
3:1 field border, `--ring`, sidebar family, `resources/css/app.css:2124-2237`); `.pilot-theme.landing` restates the
status tokens to out-rank `public/theme-colors.css` (`:2214-2226`); `.landing .manager-dialog` adds border and shadow (`:2229`).
Components consume tokens through Tailwind classes: `ui/button/index.ts` (`bg-primary`, `border-input`, `ring-ring`),
`ui/input/Input.vue` (`border-input`, `focus-visible:ring-ring`, `aria-invalid:border-destructive`), `ui/label`,
`ui/badge/index.ts` (`bg-muted` for `outline`). None of the four reads theme context or hardcodes a hex, so they follow
`.landing` automatically; they need no code, only measurement. `.manager-button`/`.manager-dialog` also use tokens
(`:1737-1790`). Hardcoded values below line 2119 are only two `color-mix(... #000)` hover shades (`:1023,1440`).

**What is missing (the first behaviours to establish).**

- Ten of the 11 pages are in the legacy green list `pilotPages` (`pilotTheme.ts:23-26,33-38,18`) but not in `landingPages`.
  **`Catalogue/Producers/Availability` is in neither list**, and every `pilot-*` layout rule is scoped under `.pilot-theme`
  (`app.css:145,188,235,260,391-446`, `:678-725`), so Availability has none of its own layout styling today
  (`Availability.vue:37` uses `pilot-page pilot-layout-page pilot-manager-page`). Admitting it to `landingPages` alone would
  give it landing colours without the layout rules. It must also join `pilotPages` (D1).
- `managerTheme` (`AppLayout.vue:14-18`: prefix `Manage/` or `Finance/Baskets/`) adds `coop-manager-shell`, which carries
  padding, 44 px minimums and `max-width: 64rem` (`app.css:1697-1735`). So `Manage/Stock/{Create,Edit}` and
  `Manage/ReturnItems/*` get it and the `Catalogue/*` pages do not. Within one area (R3) the same composition renders with
  different spacing rules. Measure first (D3).
- The tracked T84 manifest uses `Catalogue/Producers/Index` as its **unmigrated control** (view `catalogue-unmigrated`,
  `fixtures/landing.visual.json:504-`; `data-s2-*` green expectations in every `stock-navigation` state, e.g. `:756-`;
  `fixtures/landing-visual.ts:114,126,185`). Vitest also asserts `Catalogue/Producers/Index` and `Manage/Stock/Create` have no
  variant (`lib/pilotTheme.spec.ts:184,199,213`; `composables/usePilotTheme.spec.ts:250-253`). Admitting those pages breaks
  these assertions by design (R11 allows editing assertions that describe the changed look).
- Seeder lacks every R8/R13 state (see *Seeder decision*).
- No page spec for 7 of the 11 pages (see *Test plan*).

**Shared consumers (leakage surface, R2).** A change that alters a shared component's markup or non-`.landing` CSS also
changes pages outside Scope: `MovementHistory` → `Producer/Stock/Movements`, `Manage/Stock/Movements`,
`Finance/Stock/Movements`; `WeeklyProductFields` → `Producer/Products/{Create,Edit}`; `ProductAdministrationActions` →
`Producer/Products/Edit`; `ActiveSwitch` → `Manage/Stock/Index` (landing), `Manage/Accounts/Index`, `Producer/Products/Index`;
`StatusIndicator` → seven other pages; `NativeDialog` → `Manage/Cycles/*`, `StockEditDialog`. **Rule: Scope styling lands only
under `.landing` selectors in `app.css`; no markup, class or prop change in a shared component unless a regression probe
shows the unmigrated consumers' computed values unchanged.**

## Contract for admitting a page to `landingPages`

A page name enters `landingPages` (`lib/pilotTheme.ts`) only when all hold; the task that admits it proves them:

1. **Exact name, no prefix, no role** (existing comment at `pilotTheme.ts:53`). One array entry per page.
2. **Also in `pilotPages`** so the `pilot-*` rules apply (D1); today only Availability is missing.
3. **Page-level measurement passed before the entry merges**: R5–R9 measured for the page's declared states (manifest below).
4. **Tests flipped in the same change**: the page's name moves from the negative lists in `lib/pilotTheme.spec.ts` and
   `composables/usePilotTheme.spec.ts` to the positive list; `pilotVariantFor` gets one positive row per page; the AppLayout
   spec loop covers the new page and still shows `''` for the near-miss names (`Catalogue/Producers/Index/Extra`,
   `Catalogue/Producers`, `Manage/Stock/Movements`).
5. **Control page re-pointed**: the unmigrated control in the T84 manifest moves to a page not planned for this slice (D4).
6. **Shared-consumer probe**: if the task touched CSS that a shared component's other consumers can match, an unmigrated
   consumer (`Producer/Products/Edit`, `Manage/Stock/Movements`, `Manage/Accounts/Index`) is measured and unchanged.
7. **Reversible**: removing the entry restores the exact previous computed values (variant-off fixture state, as T84).

## Per-requirement design

Files are `resources/js/...` unless stated. "Pattern" is the composition for R7. Page → class wrapper lines verified in source.

### R1 — Every Scope page in the landing set (S1, S2)

Add the 11 names to `landingPages` (`lib/pilotTheme.ts:54`) across the page tasks, and `Catalogue/Producers/Availability` to
`pilotPages` (`:1-49`). No change to `AppLayout.vue`, `AppSidebarLayout.vue` or `usePilotTheme.ts`: the shell and
portals already take the class. Overlays on Scope pages: shell dropdown/sheet/tooltip/toast (done by T84; re-measured on a
Catalogue page) and the `ProductAdministrationActions` `NativeDialog` (inherits `.landing`). Manager sees the same pages
(theming is per page, spec Roles): S2 re-measures one Manager page and one Manager-only unmigrated page.

| Page (route) | Source | Wrapper | Pattern |
| --- | --- | --- | --- |
| `Catalogue/Producers/Index` | `pages/Catalogue/Producers/Index.vue:24` | `pilot-page pilot-layout-page pilot-manager-page` | list |
| `Catalogue/Producers/Products` | `Products.vue:64` | same | list + overlay |
| `Catalogue/Producers/Availability` | `Availability.vue:37` | same | availability |
| `…/WeeklyProduct/Create` | `WeeklyProduct/Create.vue:24` | `pilot-layout-page pilot-t44-page max-w-3xl` | form |
| `…/WeeklyProduct/Edit` | `WeeklyProduct/Edit.vue:55` | same | form + overlay |
| `Catalogue/Stock/Movements` | `Catalogue/Stock/Movements.vue:29` | `pilot-layout-page pilot-t44-page` | movement history |
| `Manage/Stock/Create` | `Manage/Stock/Create.vue:22` | `pilot-page pilot-layout-page pilot-manager-page max-w-xl` | form |
| `Manage/Stock/Edit` | `Manage/Stock/Edit.vue:76` | same | form + overlay |
| `Manage/ReturnItems/Index` | `ReturnItems/Index.vue:27` | same (list/table) | table |
| `Manage/ReturnItems/Create`, `Edit` | `Create.vue:22`, `Edit.vue:26` | same | form |

Controllers: `app/Http/Controllers/Catalogue/{ProducerController:17,ProducerProductController:25,ProducerAvailabilityController:29,
WeeklyProductController:28,53,StockMovementController:22,StockProductController:30,53,ReturnItemController:22,29,44}.php`; routes
`routes/web.php:117-157` (`role:manager,catalogue`). None edited.

### R2 — Page-scoped, no leakage (S8)

Mechanism unchanged (class on shell and portals only on admitted pages). Evidence: extend the T84 navigation states to
Catalogue pages: (a) from a migrated Catalogue page to an unmigrated Manager page and back with the mobile sheet open and
with the account menu open, in light and dark, system preference preserved; (b) computed colours of the unmigrated page equal
the pre-slice constants (green `rgb(36, 107, 87)` / `rgb(248, 247, 244)` light, `rgb(167, 215, 193)` / `rgb(27, 30, 28)` dark,
as T84 already records). Welcome and the three earlier landing pages: their existing views stay in the suite and must pass.

### R3 — One identity within the area (S1)

Same tokens and shell for all 11 pages by construction. Residual risk is layout divergence from `coop-manager-shell`
(D3). Evidence: a cross-page probe of H1 font family/size/weight, page background, primary button colour, field border colour,
content max-width and padding on all 11 pages in the same manifest run.

### R4 — Operational hierarchy (S1)

No hero, texture or illustration: the landing CSS under `.landing` adds no background images; assertion `[class*="welcome"]`
count 0 and no `background-image` on `#main-content` descendants. Empty-state copy is existing text
(`Index.vue:29`, `Products.vue:46-49`, `Availability.vue:54-60`, `ReturnItems/Index.vue:47`), no new component.

### R5 — Contrast (S11)

Tokens are fixed by T84 (`--input` 3:1 field border; `--ring`; `--primary-foreground`). Per page, measure with the runner's
`contrast` assertion on: H1, muted paragraphs (`text-muted-foreground`, `--landing-muted-ink`), field borders (Input border
vs card/paper), error text (`text-destructive` = `--landing-danger`, `app.css:2141`; dark `#f0a199`), primary and outline
buttons (`bg-card text-foreground border-input`), `Badge outline`, `StatusIndicator` text, `ActiveSwitch` label and track,
sidebar text. Known unverified spots to probe first: Input in dark (`dark:bg-input/30`, `ui/input/Input.vue`, blends
`--input` #8b9698 at 30%); switch track/thumb vs background (`.pilot-theme .pilot-status-switch`, `app.css:678-725`);
disabled submit (`opacity-50` in `ui/button/index.ts`) — WCAG exempts disabled controls but R8 asks for more-than-colour
distinction, so assert `disabled` attribute plus the existing label change if any. Gradients/alpha backgrounds are
"unverified" by the runner (visual.md) and need a focused computed-colour check.

### R6 — Touch and keyboard (S3, S7, S11)

44 px is provided by `.pilot-t44-page` rules (WeeklyProduct, Movements; `app.css:188-231`, under `.pilot-theme`),
`.pilot-manager-page button/a` min-height rules (`:427-440`) and `.coop-manager-shell` (`:1724-1736`, Manage pages only),
and explicit `min-h-11` on dialog and availability buttons (`ProductAdministrationActions.vue`, `Availability.vue:112`).
`ui/input` is `h-9` (36 px) by default; the manager shell and t44 rules lift it. **Probe every `input`, `a`, `button`,
`label:has(checkbox)` and the `ActiveSwitch` hit area on each page** — the `Catalogue/*` pages with `pilot-manager-page`
but no `coop-manager-shell` are the likely failures (Index, Products, Availability). Fix in `.landing` scope only. Focus
order by `focus` lists in the manifest (real Tab); Escape/return for the `NativeDialog` is already implemented
(`NativeDialog.vue:12-37`, spec `NativeDialog.spec.ts:23`) and re-asserted on Scope pages.

### R7 — Layout (S4, S11)

Compositions and their one 1024 px/200 % case each: list (`Producers/Index`, `Products`, `ReturnItems/Index`), form
(WeeklyProduct, Stock, ReturnItem create/edit), movement history, availability, and the `ProductAdministrationActions`
dialog (replacing `StockEditDialog`, see corrections). Tabular figures: `Products.vue` quantity/price cells,
`MovementHistory.vue` quantity/balance columns, `ReturnItems/Index.vue` amount column — assert
`font-variant-numeric: tabular-nums` on them (class `pilot-amount` where used; *check which cells carry it*). Container-query
list/table reflow already exists (`pilot-list`, `pilot-data-table`, `pilot-cell-label`).

### R8 — States (S3, S4, S5)

Each state is a fixture state (below). Page behaviours confirmed in source: deactivated producer (`Index.vue:51` danger
indicator + muted row `:36`; `Availability.vue:46-52` danger indicator + notice), inactive product (`ActiveSwitch.vue`
label "Deactivated" + `Products.vue:163`), unavailable product (`Availability.vue:97-110` text "Unavailable" + toggle
"Mark available"), inactive return item (`ReturnItems/Index.vue:84-86` ActiveSwitch), busy/disabled (`Form` `processing` →
`Button :disabled`, `WeeklyProductFields.vue`; `ProductAdministrationActions` `processing`), validation error per field
(`InputError` under each input in `StockProductFields.vue` and `ReturnItemFields.vue`), long names (`wrap-anywhere`,
`break-words` classes present; truncation not used — *unverified at 200 % text*). StatusIndicator is `white-space: nowrap`
(`app.css:1574-1582`): a long label at 375 px/200 % can overflow — probe it.

### R9 — Semantics kept (S4, S5)

Success under landing is the dark blue (`--pilot-success` = `--welcome-blue-hover`, `app.css:2215`), so "Active" is not
distinguished from primary by colour alone; the text label does the work (`StatusIndicator` shows the label; `ActiveSwitch`
shows Active/Deactivated). Assertions: text present for every indicator state; danger still resolves to `--destructive`
(`.material-status[data-tone=danger]`, `:1600`); no ochre on any status element in Scope.

### R10 — Nothing but presentation (S6, S9)

No PHP, route, prop, validation or translation edit (`lang/` untouched; asserted by diff review). Existing guards re-run
unchanged: `tests/Feature/Catalogue/CatalogueAreaTest.php`, `ProducerAvailabilityTest.php`, `tests/Feature/Manage/ReturnItemsPageTest.php`,
`tests/Feature/Stock/StockMovementHistoryPagesTest.php`, `tests/Feature/Http/RolePermissionMatrixTest.php` (Catalogue rows at
`:55,91`). S9 (access unchanged) needs no new test if those cover refused Receiver/producer/Catalogue-on-`/manage/stock`
URLs (*to confirm when the closure task reads them; add one assertion only for a proven gap*). Any visual correction found
during work is recorded as a Deviation.

### R11 — Existing tests green; regression for specless pages (S10)

See *Test plan*. Edited assertions are limited to the lists named in the admission contract.

### R12 — Evidence by measurement, approval by the user (S11, S12)

See *Visual evidence plan*. Approval is a Q&A history entry recorded by the closure task after the user decides; no task
records it on the user's behalf.

### R13 — Demo data shows the states (S4, S5)

See *Seeder decision*: not covered today; extension required.

## Test plan (TDD per scenario)

Order inside every page task: write the failing check first, watch it fail for the right reason, then the minimum change.
The constitution places scenario tests at Application level; this slice adds no Application behaviour, so the executable
specs are Vitest (admission, regression), the Pest suites already named (behaviour unchanged) and the visual runner
(presentation). A test that only reads a CSS class is not accepted as evidence of look (as T84 first-checkpoint rule).

**Existing tests that must stay green unchanged in meaning (R11):** `pages/Catalogue/Producers/{Index,Products,Availability}.spec.ts`
(5 + 5 + 4 cases), `pages/Manage/Stock/Edit.spec.ts` (renders `StockProductFields`, 2 cases), `components/stock/MovementHistory.spec.ts`
(6), `components/manager/NativeDialog.spec.ts` (1), `layout-rules.spec.ts` (static grid check; lists all Scope files,
`:43-57` — new markup must keep the 8 px grid), `lib/pilotTheme.spec.ts`, `composables/usePilotTheme.spec.ts`; Pest suites in R10.

**Assertions edited because the look changes (only these):** `lib/pilotTheme.spec.ts` lines 184-185 (Stock Create/Edit), 199
(Catalogue Index), `:212-215` loop; `composables/usePilotTheme.spec.ts` `:250-253` loop; `fixtures/landing.visual.json`
`catalogue-unmigrated` and `data-s2-*` expectations (D4).

**New regression specs (S10: title, one primary action, error display where the page has a form).** Pattern to mirror:
`Manage/Stock/Edit.spec.ts` (SSR `renderToString` of the real component with `installTranslations`) and
`Availability.spec.ts` for page-level mounts.

| New spec *(planned)* | Scenario assertions (each failing first) |
| --- | --- |
| `pages/Catalogue/Producers/WeeklyProduct/Create.spec.ts` | title "New product"; submit button "Create" (`data-test="save-product"`); `errors.name` message rendered next to the Name input via `InputError`; submit `disabled` while `processing`. |
| `…/WeeklyProduct/Edit.spec.ts` | prefilled fields; delete/classify controls only when `canDelete`/`canClassify`; dialog opens from the trigger and Cancel sends nothing (reuses `NativeDialog` behaviour). |
| `pages/Catalogue/Stock/Movements.spec.ts` | H1 = product name, available quantity line, `MovementHistory` rendered, no entry/adjustment/count control (001 R28a, S6). |
| `pages/Manage/Stock/Create.spec.ts` | title, create action, field errors for name/unit/price/containers (`StockProductFields`). |
| `pages/Manage/ReturnItems/Index.spec.ts` | rows with `ActiveSwitch` text for active and inactive item; empty text; create link. |
| `…/ReturnItems/Create.spec.ts`, `Edit.spec.ts` | title, primary action, `errors.name`/`amount` shown via `InputError` (`ReturnItemFields.vue`). |

**Admission specs (per page task, contract item 4):** extend `lib/pilotTheme.spec.ts` (`pilotVariantFor` rows, `isPilotThemePage`
row for Availability) and `composables/usePilotTheme.spec.ts` (shell/dialog/sheet landing for the new name; absent after leaving).

**Pest:** no new Pest test is planned except the seeder (below). Handoffs state "no PHP behaviour changed".

## Visual evidence plan (measurement first)

Follows `skills/agent-workflow/references/visual.md` and `AGENTS.md` "Measure before screenshot": no screenshot unless an
unexplained failure, a new direction or the user's request (then one, cropped/scaled). Precedent sizes: T84 manifest 65
cases (`fixtures/landing.visual.json`), T85 111 cases (`landing-orders.visual.json`).

**Fixture** *(planned)*: `fixtures/catalogue-landing.html`, `fixtures/catalogue-landing-visual.ts`, modelled on
`landing-orders-visual.ts` (real `createInertiaApp`, real `AppLayout`, pages by `import.meta.glob`, `http.setClient` for
error/busy states, deterministic props, role `catalogue` with a Manager variant for S2). Fixtures prove presentation only,
never persistence or authorization (stated in the file header as T85 does). Duplication of the bootstrap already exists in the
two landing fixtures; a shared bootstrap module is a prefactor and is **not** scheduled unless the third copy proves painful
(D5). Manifests *(planned)* per family so each task verifies independently: `catalogue-landing-lists.visual.json`,
`-availability`, `-forms`, `-stock`, `-returns`, `-history`, plus `-shared` (negative controls and navigation) owned by T01 and
extended by later tasks. A run is bound to a fixed base SHA, in an ignored `.atl/<task>/visual` directory.

**Dimensions by risk (explicit `cases`, not the Cartesian default):**

- Baseline for every page, default state: 375 and 1280 px, light and dark, 100 % text = 4 cases per page (44 for 11 pages).
- 1024 px at 200 % text, light and dark: one page per composition (list: `Products`; form: `Manage/Stock/Edit` with locked
  unit; movement history; availability; table: `ReturnItems/Index`) = 10 cases. Add 375 px/200 % for the riskiest form
  (`Manage/Stock/Edit` with all errors) and the dialog = 4 cases.
- Risk states at 375 light plus 1280 dark (2 cases each): long name per list/table/form header (5), empty list (4:
  producers, products, return items, movements), validation errors per form (4 forms), busy submit (2), domain error banner
  (`errors.domain`, 2), deactivated producer (Index, Availability), inactive product (Products), unavailable product
  (Availability, `canChange` true and false), inactive return item, locked-unit stock edit, used product without classify
  actions, `can` flags off (Catalogue-only versus Manager). About 40 states, ≈ 80 cases.
- Overlays: classify dialog and delete dialog open on `Products`; Escape; focus return; account menu; mobile sheet; toast
  from `lib/flashToast.ts` (~ 10 cases).
- System appearance light/dark, reduced motion, fallback font (3 cases each as T84).
- Negative/leak: unmigrated control (re-pointed), Welcome, variant-off for one Catalogue page, navigation with sheet and
  menu open in both modes (≈ 14 cases).
- **Estimate ≈ 150–170 cases** in total across manifests (*uncertain; `run.mjs plan` decides*). If the coordinator finds this
  excessive, the first cut is the per-state 1280-dark twin, not the compositions.

**Assertions per case family** (runner vocabulary: `count`, `text`, `attributes`, `styles`, `fontSizeRem`, `minWidth/minHeight`,
`contrast`): `.reference-style.landing` count 1; `.pilot-theme:not(.landing)` count 0 (except sheet overlay, as T85);
`.welcome-page` count 0; H1/muted/error/button/badge/field-border `contrast` 4.5 or 3; interactive `minWidth/minHeight` 44;
tabular-nums on quantity/price/balance/amount cells; state texts (`Deactivated`, `Unavailable`, `Mark available`); no
`background-image` in `#main-content` (R4); `focus` lists with real Tab for every control; horizontal overflow via the
runner's layout measurements. Anything the runner marks unverified (gradients, alpha) gets a focused computed-colour probe
(`measureLayout`/`measureFocus` from `skills/agent-workflow/scripts/visual/measure.mjs`).

**First checkpoint (T01):** open `Catalogue/Producers/Index` at 375 px light with a deactivated producer; first measurement
must still show green (`rgb(36, 107, 87)`) on the primary element — record the failing value — then admit the page and
re-measure `rgb(40, 101, 133)` / `rgb(250, 241, 220)` (T84 constants), with Welcome and the re-pointed control unchanged.

## Seeder decision

Read in full: `database/seeders/DemoSeeder.php` (299 lines), `tests/Feature/Database/DemoSeederTest.php` (249 lines).
Current data: 3 producers all active (`PRODUCERS` `:66-79`), 6 weekly products all active (`:66-79`), 2 stock products
(`createStock :228`), Catalogue holder `biel@demo.guixa.test` (`STAFF_ROLES :61-64`), cycles `[0]`,`[1]` already sent,
cycle `[2]` unsent (producer 2 closed, `:144`), **no deactivated producer, no inactive product, no product marked unavailable
in a cycle, no return item at all** (`CreateReturnItem` is never called). All four R13 states are missing: **the seeder must
change** (and its handoff says what, per `AGENTS.md`).

Proposal, all through existing use cases, inside the existing transaction, after cycles are created so cycle/reception
assertions are untouched:

1. A 4th producer deactivated (`CreateProducer` then `DeactivateProducer`, `src/Catalog/Application`), not passed to `OpenCycle`.
2. An inactive weekly product on an active producer (`CreateProduct` then `DeactivateProduct`), no order lines.
3. One product of the first producer marked unavailable in the unsent cycle `[2]` (`SetProductAvailability`,
   `src/Ordering/Application`); the producer's order is not sent there, so Availability offers the toggle (S5).
4. Two return items, one deactivated (`CreateReturnItem` then `DeactivateReturnItem`, `src/Stock/Application`).

`DemoSeederTest.php` changes: counts at `:20-23` (`products` 8 →, `producers` 3 →, users if the producer gets an account) and
the idempotency table list (`:199`, add `return_items` and `producer_cycle_availability` — *table names to confirm in
migrations*); new test "seeds the Catalogue states" asserting one deactivated producer, one inactive product, one
unavailable product in an unsent cycle, one inactive return item, and that `biel` (Catalogue) opens `/catalogue/producers`,
its products, availability, `/catalogue/return-items` (extend the `'receiver'` path list). Risk: `ListDashboardTest`-style
assertions at `:50-64` (attention counts, "Catalogue maintenance") must not change — verify by running the whole file
(*unverified*).

## Risks

| Risk | Evidence | Mitigation |
| --- | --- | --- |
| Availability gets landing colours but no layout | `pilotPages` lacks it; `.pilot-*` under `.pilot-theme` | D1; admission contract item 2; measured in its own task |
| Same composition renders differently on `Catalogue/*` vs `Manage/*` | `AppLayout.vue:14-18`, `app.css:1697-1735` | D3; cross-page probe in R3 |
| Admission breaks T84 evidence and Vitest negatives | `landing.visual.json:504-`, `pilotTheme.spec.ts:199` | D4; edit only asserted-look lines, done in T01 |
| Leakage through shared components | consumer list above | `.landing`-only CSS; shared-consumer probe; no shared markup edits |
| Touch targets < 44 px on `Catalogue/*` pages without manager shell | `ui/input` `h-9`; only `.pilot-theme` button/anchor rules | probe first; fix in `.landing` scope |
| `white-space: nowrap` status label clips at 200 % | `app.css:1574-1582` | probe; allow wrap under `.landing` only if it fails |
| Dark input fill from `dark:bg-input/30` | `ui/input/Input.vue` | measure before touching |
| Ochre-as-warning token latent | `app.css:2217,2223` vs R9 | none in Scope; record for slice 2+ |
| Seeder additions shift dashboard fixtures | `DemoSeederTest.php:50-64` | add after cycles; run whole seeder test |
| Mixed look between merges | OQ1 accepted | order below minimizes the window; S1/R3 verified on integrated base only |
| Fixture bootstrap duplication (third copy) | `landing-visual.ts` 578 lines, `landing-orders-visual.ts` 348 | D5; defer extraction |
| Runner/browser not exercised while drafting | no command run | T01 first checkpoint proves the path |

## Decisions the plan proposes (to confirm)

- **D1 — Availability admission.** Add it to `pilotPages` and `landingPages` (as Orders were in T85). Alternative: derive
  `pilotTheme` from `landingPages` in `AppLayout.vue` (touches the shell for every page; rejected for this slice).
- **D2 — Spec corrections.** Applied: corrections 1 and 2 edited into `spec.md` and logged as a Change; correction 3 is a recorded risk only.
- **D3 — Manager shell divergence.** Do not extend `managerTheme` to `Catalogue/`; measure R3 first and equalize inside
  `.landing` only if a probe shows a gap. Extending `managerTheme` would restyle nothing outside Scope but changes a shell
  rule shared with Manage pages, so it needs its own justification.
- **D4 — Negative control.** Re-point T84's `catalogue-unmigrated` and `data-s2-*` to a page outside slices 1–2
  (proposal: `Manage/Accounts/Index`, no shared component with Scope except `ActiveSwitch`/`StatusIndicator`, which doubles as
  a leakage probe). Each later slice re-points again; a permanent synthetic control is out of scope.
- **D5 — Fixture shape.** One new fixture bootstrap plus per-family manifests; no shared-bootstrap prefactor now.

## Ordering and dependencies

Reason-bearing edges only; numbering is not sequencing. T01 first (admission contract, re-pointed controls, first fixture,
Index). T03–T08 each need T01's fixture and re-pointed control (they reuse the admission contract and the manifest
skeleton). T02 (seeder) has no code dependency and may run in parallel. T09 needs every admitted page.

```
T01 ──► T03 Products ──► (dialog reused by) T05, T06
   ├──► T04 Availability
   ├──► T05 WeeklyProduct forms
   ├──► T06 Stock forms
   ├──► T07 Return items
   └──► T08 Movements
T02 seeder (independent) ──► T09 (user review needs the states)
T03..T08 ──► T09 closure
```

T05/T06 share the field pattern (`StockProductFields`); T05 establishes the first form measurements, T06 reuses them. If
T05 changes `.landing` field CSS, T06's probe covers the regression instead of re-deciding the field look.

## Proposed task slicing (bounded outcomes; names and allocation only; task text is for `tasks.md` after approval)

| ID | Outcome (name) | Requirements / scenarios | Notes |
| --- | --- | --- | --- |
| T01 | Producer list in the landing identity, with admission contract and controls re-pointed | R1, R2, R3 (start), R4–R9 for `Producers/Index`; S4 (list: producers), S8; R10 guards | Tracer: first fixture + `catalogue-landing-shared` manifest, `landingPages` row, flipped specs, control re-pointing. Est. 250–400 authored lines (medium confidence). |
| T02 | Demo seeder shows Catalogue states | R13; supports S4, S5 | `DemoSeeder.php` + `DemoSeederTest.php`. Est. 80–150 lines. Independent of UI tasks. |
| T03 | Producer's products and classification dialog in the landing identity | R1, R6, R7, R8, R9 for `Products`; S4 (products), S7 (dialog, shell overlays, toast) | `ProductAdministrationActions` dialog, `ActiveSwitch`, inactive and long states, `can` flags off. Est. 200–350. |
| T04 | Availability page in the landing identity | R1, R2, R8, R9 for `Availability`; S5 | Adds `pilotPages` entry (D1); toggle round trip through the real router in a focused probe. Est. 150–250. |
| T05 | Weekly product create and edit forms | R1, R5, R6, R8 for forms; S3 (weekly), S10 (new specs `Create`, `Edit`) | First forms: fields, errors, disabled; delete/classify dialog on Edit. Est. 250–400. |
| T06 | Stock product create and edit forms | R1, R5–R8; S3 (stock), S7 (dialog on Edit), S10 (`Stock/Create` spec) | Locked-unit state, `coop-manager-shell` interplay (D3). Est. 150–300. |
| T07 | Return-item list and forms | R1, R5–R9; S3 (return items), S4 (table), S10 (3 specs) | Table composition + `ReturnItemFields`. Est. 250–400. |
| T08 | Stock movement history | R1, R7, R9; S6, S10 (`Movements` spec) | Shared `MovementHistory` with three unmigrated consumers: leakage probe mandatory. Est. 120–250. |
| T09 | Slice acceptance record and visual approval request | R3 (area-wide), R10, R11, R12; S1, S2, S8 (re-run), S9, S10, S11, S12 | Integrated-base run of all manifests; maps R→task→evidence→verdict→remainder; asks the user for visual approval and logs it as Q&A; no code unless a correction is needed. |

Every R and S has an owner: R1–R9 per page tasks plus T09 for the area-wide check; R10, R11 in each task and T09; R12 T01–T08
evidence and T09 approval; R13 T02. Scenario sharing is explicit: S1 and S12 are completed only by T09; S3, S4, S7, S10, S11
are split by page across tasks and complete only when their last page task merges. Nothing is deferred.

Each task is tests-first, ships its code, specs, manifest/fixture states and history entries together, and ends with
focused Vitest, `vp check --fix` (also formats Markdown), type check and candidate-bound CI. Each task's handoff states
"Demo seeder: see T02" or "no change" per `AGENTS.md`. A flag is not used: the admission entry is the reversible switch
(contract item 7).

## Rules for the implementation

- **Laravel-first.** Backend untouched. No controller, use case, DTO, route, translation or migration edit. The seeder uses
  existing Application use cases only (constitution A10: actors from `ActorResolver`, already the seeder's pattern).
- **UI reuse (no new components without gap evidence).** `component_plan` per task per `visual.md`: reuse `ui/button`,
  `ui/input`, `ui/label`, `ui/badge`, `NativeDialog`, `Heading`, `StatusIndicator`, `ActiveSwitch`, `InputError`,
  `MovementHistory`, existing `.pilot-*` classes. Expected change surface is `resources/css/app.css` `.landing` selectors and
  `lib/pilotTheme.ts`. A new component or a shared-markup change needs a measured gap recorded in the task and the review.
- **Scope CSS only under `.landing`** (and `.dark .landing`); never `:root`, `public/theme-colors.css`, or `.pilot-theme` base
  rules (leakage, contract item 6). Values reuse existing `--welcome-*`/`--landing-*` tokens; a new token needs a contrast figure.
- **No promotion** of landing values to global, no removal of `pilotPages`, no orphan layout deletion, no `ui/select` work
  (no Scope page imports `FormSelect`; verified by grep of Scope pages and components for `Select`: none).

## Limits

Covers accepted-to-be 005 R1–R13/S1–S12 only. Slices 2–7, mail, error pages, first-paint, favicon, global promotion and the
Dashboard restyle are outside. Plan acceptance, task breakdown, publication and implementation are separate user decisions;
the spec acceptance gate stands first.
