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

# Slice 2 — Producer role complete · Plan

**Status: Accepted 2026-11-15.** Covers delivery slice 2 only ([spec](spec.md) "Slice 2", R14–R19, S13–S18, OQ3
answered) and inherits slice 1 (R1–R13, S1–S12), whose plan above stays as written. It authorizes no tasks, tickets or
implementation. Slices 3–7 stay in [discovery](discovery.md). Paths are relative to the repository root. The directory is
`resources/js/pages/` (lowercase); the spec and the slice 1 plan write `Pages/`. Everything marked *unverified* was not run
or measured while drafting: no test, build or browser check was executed for this section (code reading only, worktree
`origin/main` at 43368c27). Slice 1 outcomes it reuses: Deviations T03, T06 (D3 outcome), T07 and T09, and the visual
approval of 2026-11-15 (history, `node .agent-workflow/scripts/history.mjs read 005-landing-identity-rollout`).

## Readiness for this plan (slice 2)

| Concern             | State                          | Evidence and limit                                                                                                                                                                                                                                                                        |
| ------------------- | ------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Behavior            | Ready | Slice 2 spec accepted 2026-11-15 (`spec.md:196-200`); R14–R19, S13–S18 are specific and OQ3 is answered (`spec.md:306`).                                                                                                                      |
| Decisions           | Ready, with 6 plan-level choices | OQ3 closed. D6–D11 below are design decisions this plan proposes; none changes a requirement.                                                                                                                                                                                              |
| Design              | Ready                          | Admission, tokens, overlays and `.landing` CSS exist and were exercised by 11 pages (`lib/pilotTheme.ts:55-70`, `resources/css/app.css:2124-2237`). New work is admission, per-page measurement, small `.landing` adjustments, fixtures and a seeder extension.                          |
| External prerequisites | Ready                       | None: no service, package, credential or migration. Slice 1 integrated (PR #434) and approved (Q&A 2026-11-15).                                                                                                                                                                          |
| Paths               | Ready                          | The six pages, controllers and shared components verified below. New files are marked *(planned)*.                                                                                                                                                                                       |
| Verification        | Ready, planned                 | Visual runner (`skills/agent-workflow/scripts/visual/run.mjs`), Vitest and Pest exist; nothing run for this draft. The runner path was proven by slice 1 (454 cases, 0 failed, history 2026-11-15).                                                                                       |
| Seeder              | Not ready                      | `database/seeders/DemoSeeder.php` lacks six R16 states (see *Seeder decision, slice 2*); closed by the seeder task, not by a decision.                                                                                                                                                   |
| Page-spec coverage  | Not ready                      | Only `pages/Producer/Products/Index.spec.ts` exists; `Cutoff`, `CycleSummary`, `Products/Create`, `Products/Edit`, `Stock/Movements` have none (R18). Closed inside the page tasks, tests first.                                                                                         |

### Corrections (for the coordinator to log as a Change; this section does not edit `spec.md` or the slice 1 text)

1. **Path casing.** `Pages/` in the spec and slice 1 plan is `resources/js/pages/` in the repository.
2. **`landingPages` location.** The set is now `resources/js/lib/pilotTheme.ts:55-70` (14 names after slice 1), not the
   three names quoted at `plan.md:39` and `:56-70` (the "What is missing" list of slice 1 describes the state before T01–T09:
   Availability in neither list, pending admission, 7 specless pages). Read the current code, not those lines.
3. **Slice 1 plan status line** (`plan.md:3`) says "slice 1 only", which stays true; slice 2 carries its own status line above.
4. **The slice 1 probes for shared consumers named three Producer pages** (`Producer/Stock/Movements`, `Products/Create`,
   `Products/Edit`) as unmigrated (`plan.md:95-96`, T03/T05/T08 evidence). Slice 2 admits them, so those controls move
   (D7). Not a spec error; a consequence the spec's R2 implies.

## Architecture overview (slice 2, source-backed)

**What already works.** Admission is one exact-name entry in `landingPages` (`lib/pilotTheme.ts:55-70`); the page must also
be in `pilotPages` (`:1-53`) and all six Producer pages are (`:10-15`). `pilotVariantFor` (`:72-74`) returns `'landing'`;
`AppLayout.vue`, `AppSidebarLayout.vue` and `composables/usePilotTheme.ts` need no change (slice 1 plan, *Architecture
overview*; the shell, portals and `NativeDialog` already take the class). Landing CSS lives only under `.landing` /
`.dark .landing` in `resources/css/app.css:2124-2237` and `.pilot-theme.landing` (`:2214`), plus the slice 1 sub-blocks
(`/* 005 T09 */` at `:2400`, `.landing .pilot-title-compact h1.pilot-heading` at `:2406`).

| Page (route group `role:producer`) | Source                                  | Wrapper                                       | Composition (R16)                                                           |
| ---------------------------------- | --------------------------------------- | --------------------------------------------- | --------------------------------------------------------------------------- |
| `Producer/Products/Index`          | `pages/Producer/Products/Index.vue:27`  | `pilot-layout-page pilot-t44-page`            | product list with `ActiveSwitch`, stock table (`producer-stock-inactive`, `producer-stock-sold-out`, `:196-207`) |
| `Producer/Products/Create`         | `Products/Create.vue:21`                | `pilot-layout-page pilot-t44-page max-w-3xl`  | product form (`WeeklyProductFields`)                                        |
| `Producer/Products/Edit`           | `Products/Edit.vue:46`                  | same                                          | product form + `ProductAdministrationActions` dialog (delete unused, `:26-37`) |
| `Producer/Cutoff`                  | `pages/Producer/Cutoff.vue:27`          | `pilot-layout-page pilot-t44-page max-w-3xl`  | cutoff form (`data-test="save-cutoff"`, `InputError` on `days_before` and `time`/`domain`, `:55,66`) |
| `Producer/CycleSummary`            | `pages/Producer/CycleSummary.vue:21`    | `pilot-layout-page pilot-t44-page`            | cycle summary: sent (read-only quantities, `:29-43` "Nothing to deliver."), not sent (availability toggle, `data-test="availability"`, `:86-108`) |
| `Producer/Stock/Movements`         | `pages/Producer/Stock/Movements.vue:29` | `pilot-layout-page pilot-t44-page`            | movement history (`MovementHistory`, `:40`), read-only                      |

Controllers: `app/Http/Controllers/Producer/{CutoffController,CycleController,ProductAvailabilityController,ProductController,StockMovementController}.php`
(none edited). Cycle summary is rendered by `CycleController.php:23`.

**Title size (R15, OQ3).** All six roots carry `pilot-t44-page`, and `.pilot-theme .pilot-t44-page h1.pilot-heading` sets
`font-size: 1.5rem; line-height: 2rem` (`resources/css/app.css:192-195`). Slice 1 measured exactly that composition at 24 px
at every width (Deviation T09: WeeklyProduct and `Catalogue/Stock/Movements`), and used `.landing .pilot-title-compact`
(`:2406`) only for pages without `pilot-t44-page`. The plan therefore expects the six pages to be 24 px already; the first
task measures it (D6) before any class is added. *Unverified in browser.*

**Layout shell.** `Producer/` is not `Manage/` or `Finance/Baskets/`, so `managerTheme` (`AppLayout.vue:14-18`) does not
apply and `coop-manager-shell` is absent: the pages get `.pilot-t44-page` spacing and 44 px minimums (`app.css:188-231`)
and nothing from the manager shell, the same situation as `WeeklyProduct/*` in T05. Slice 1's T06 gap list (width,
header and form margins) therefore does not recur for the forms; lists and the table keep the width the composition
already has (D3 precedent: widths per composition, type and palette equal).

**Shared consumers (leakage surface, R2).** After slice 2, every consumer of `WeeklyProductFields` and
`ProductAdministrationActions` is migrated (`Catalogue/Producers/WeeklyProduct/*`, `Products`, `Manage/Stock/Edit`, the three
Producer pages). Still unmigrated:

| Shared component / rule                                            | Unmigrated consumers after slice 2                                  | Probe used instead                                    |
| ------------------------------------------------------------------ | ------------------------------------------------------------------- | ----------------------------------------------------- |
| `components/stock/MovementHistory.vue`                             | `Finance/Stock/Movements`, `Manage/Stock/Movements`                 | those two pages (already in the fixture)              |
| `components/ActiveSwitch.vue`, `.pilot-status-switch` (`:678-725`) | `Manage/Accounts/Index`                                             | the slice 1 control, kept                             |
| `NativeDialog` base (`components/manager/NativeDialog.vue`)        | `Manage/Cycles/*`                                                   | `Manage/Cycles/Index` (already routed in the fixture) |
| `.pilot-layout-panel` (`:235`), `.pilot-data-table` (`:594`), `.pilot-t44-page` (`:188-204`) | other `pilot-t44-page`/panel pages outside the landing set | `Manage/Accounts/Index` plus `Manage/Cycles/Index`    |

**Rule (unchanged from slice 1):** slice 2 styling lands only under `.landing` selectors; no markup, class or prop change
in a shared component unless a probe shows the unmigrated consumers' computed values unchanged.

## Contract for admitting a Producer page (reuse)

The seven-point admission contract of slice 1 (exact name; also in `pilotPages`; measured before merge; tests flipped in the
same change; control re-pointed; shared-consumer probe; reversible) applies unchanged. Slice 2 specifics:

- Point 2 holds already for all six names (`pilotTheme.ts:10-15`); no D1-style `pilotPages` edit is needed.
- Point 4: in `lib/pilotTheme.spec.ts` the negative rows `Producer/Stock/Movements`, `Products/Create`, `Products/Edit`
  (`:224-227`) become positive rows; add positive rows for `Producer/Products/Index`, `Producer/Cutoff`,
  `Producer/CycleSummary`; the near-miss loop (`:233-290`) keeps a Producer-prefixed near name returning `null`. In
  `composables/usePilotTheme.spec.ts` move the three names out of the negative lists (`:248-259`, `:376-379`) into the
  positive one (`:389-400`) and add the other three. `layout-rules.spec.ts:11-15` gains `pages/Producer/Stock/Movements.vue`
  (the only Producer page missing from the 8 px grid check).
- Point 5: the Producer probes in `fixtures/catalogue-landing-visual.ts` (`:447`, `:458`, `:460`, URL map `:506`, `:517`,
  `:525`) and their uses in `catalogue-landing-history.visual.json:709`, `catalogue-landing-stock.visual.json:1777`,
  `catalogue-landing-lists.visual.json:2616` are re-pointed once, before any Producer page is admitted (D7).
- Point 6: the shared-consumer probe uses the table above.

## Per-requirement design

### R14 — Every Producer page in the landing page set (S13, S16)

Add the six names to `landingPages` across the page tasks. No change to `AppLayout.vue`, `AppSidebarLayout.vue`,
`usePilotTheme.ts`. Overlays: shell dropdown/sheet/tooltip/toast (done by T84, re-measured on a Producer page) and the
`ProductAdministrationActions` `NativeDialog` on `Producer/Products/Edit` (inherits `.landing`; slice 1 T03/T05 measured
the same component). The products Edit page flashes no toast of its own: any toast evidence is the synthetic Sonner probe
already accepted in the T03 Deviation (2026-11-13T16:55), cited, not repeated. *Unverified for the producer role in the
fixture; the fixture adds role `producer`.*

### R15 — One identity within the area, consistent with slice 1 (S13)

Same tokens and shell by construction. Title: measure `h1.pilot-heading` font size on the six pages at 375 and 1280 px,
light and dark; expect 24 px (`fontSizeRem: 1.5`) from `.pilot-t44-page`. Add `pilot-title-compact` only to a page whose
measured size is not 24 px (D6). Cross-page probe as in slice 1 R3/T09: family, size, weight, page background, primary
button, field border, padding, content width, over the six pages **and** against one slice 1 page (`Catalogue/Producers/Products`)
so "consistent with slice 1" is a measured comparison, not an assertion.

### R16 — Measurable conditions (S14, S15, S16)

R5–R9 per page, as measured text, by the slice 1 method (contrast assertions, 44 px minimums, tabular figures, state texts,
ochre not used for status). Pattern-specific design:

- **Product list** (`Index.vue`): `ActiveSwitch` label and track (same component already measured on
  `Catalogue/Producers/Products`); inactive marker is text (`ActiveSwitch` "Deactivated", `Index.vue:70` muted row);
  stock table `Inactive` / `Sold out` are text badges (`:196-207`), so state differs by text as well as colour. Long name
  and long unit: `wrap-anywhere`/`break-words` present on the product list; the stock table cells are the likely overflow
  at 375 px/200 % (probe first, fix under `.landing` only).
- **Product form and dialog** (`Create`, `Edit`): the field pattern is `WeeklyProductFields`, already measured in T05; the new
  evidence is the producer role's page header and dialog opened by keyboard (Escape closes with no request, focus returns,
  `NativeDialog.vue:12-37`).
- **Cutoff form**: two fields with `InputError` (`Cutoff.vue:55,66`), disabled submit while `processing` (`:71`). Error state
  needs a text message (present) and the 3:1 field border from `--input`.
- **Cycle summary**: table of per-product quantities (tabular-nums on quantity cells: *check which cells carry
  `pilot-amount`*), `sent` state shows read-only quantities, not-sent shows `Available`/`Unavailable` text plus the
  `Mark available`/`Mark unavailable` button (`:86-108`), both states differ by text. Empty states "Nothing to deliver." and
  "You have no products to offer." (`:41-43`) are existing copy.
- **Movement history**: `MovementHistory` quantity and balance columns tabular; long list reflows through the existing
  container query (`pilot-data-table`, `pilot-cell-label`). Producers get no entry/adjustment control (read-only, spec R17).
- Hit areas: `.pilot-t44-page` gives 44 px to buttons, anchors and inputs (`app.css:188-231`); probe `ActiveSwitch`,
  the toggle in cycle summary and the pagination links in `MovementHistory` first.

### R17 — Producer privacy and behaviour unchanged (S14–S17)

Presentation only: no PHP, route, prop, validation, translation or calculation edit; the product actions (toggle,
reactivate, delete unused) are only restyled. Guards re-run unchanged (tests in the Test plan). S17 access: the existing
Pest suites already cover another producer's data and other roles' URLs (to confirm by reading when the closure task maps
them; add one assertion only for a proven gap). A multi-role account (producer + another area) uses the Producer page
in landing and its other area keeps its own look (R2): the variant derives from the page name, so it is by construction;
measured by one navigation case.

### R18 — Tests (S13–S16)

See *Test plan, slice 2*.

### R19 — Evidence, approval, demo data (S18)

See *Visual evidence plan* and *Seeder decision, slice 2*. Approval is a Q&A history entry recorded by the closure task after
the user decides; no task records it on the user's behalf.

## Test plan, slice 2 (TDD per scenario)

One failing check per rule or scenario before the minimum change. Application behaviour does not change, so the executable
specs are Vitest (admission, page regression), the Pest suites already named (behaviour unchanged) and the visual runner
(presentation). A test that only reads a CSS class is not evidence of look.

**Existing tests that stay green unchanged in meaning (R18/R11):** `pages/Producer/Products/Index.spec.ts`;
`components/stock/MovementHistory.spec.ts`; `components/manager/NativeDialog.spec.ts`; `layout-rules.spec.ts`;
`lib/pilotTheme.spec.ts` and `composables/usePilotTheme.spec.ts` (edited only as listed in the admission contract); Pest:
`tests/Feature/Catalog/ProducerPagesTest.php`, `tests/Feature/Ordering/ProducerCycleSummaryPageTest.php`,
`tests/Feature/Catalog/ProductPagesTest.php`, `tests/Feature/Manage/ProducerProductsPageTest.php`,
`tests/Feature/Stock/StockMovementHistoryPagesTest.php`, `tests/Feature/Http/RolePermissionMatrixTest.php`.

| Scenario / rule             | Failing check first                                                                                          | Where                                              |
| --------------------------- | ------------------------------------------------------------------------------------------------------------ | -------------------------------------------------- |
| R14/S13 admission (per page) | `pilotVariantFor('Producer/…') === 'landing'`; shell and dialog get `landing` and lose it on leaving         | `lib/pilotTheme.spec.ts`, `composables/usePilotTheme.spec.ts` |
| R18/S14 `Products/Create`   | title "New product"; submit "Create"; field error shown through `InputError`; submit disabled while processing | `pages/Producer/Products/Create.spec.ts` *(planned)* |
| R18/S14, S16 `Products/Edit` | prefilled fields; delete-unused control present; dialog opens and Cancel sends no request                   | `pages/Producer/Products/Edit.spec.ts` *(planned)* |
| R18/S15 `Cutoff`            | title "Default cutoff"; both fields prefilled; `errors.days_before` and `errors.time`/`errors.domain` rendered; `save-cutoff` disabled while processing | `pages/Producer/Cutoff.spec.ts` *(planned)*        |
| R18/S15 `CycleSummary`      | sent with lines: quantities, no toggle; sent without lines: "Nothing to deliver."; not sent: `Available`/`Unavailable` text and the matching button label; no products: "You have no products to offer." | `pages/Producer/CycleSummary.spec.ts` *(planned)*  |
| R18/S16 `Stock/Movements`   | title, available-quantity line, `MovementHistory` rendered, no entry/adjustment/count control               | `pages/Producer/Stock/Movements.spec.ts` *(planned)* |
| R18 layout grid             | `pages/Producer/Stock/Movements.vue` in the 8 px list                                                        | `layout-rules.spec.ts:11-15`                       |
| R17/S14–S16 guards          | no new test; Pest suites above re-run; handoff states "no PHP behaviour changed"                             | existing                                           |
| R19 seeder                  | new states asserted (see Seeder decision)                                                                    | `tests/Feature/Database/DemoSeederTest.php`        |

Spec pattern to mirror: `pages/Producer/Products/Index.spec.ts` and `pages/Manage/Stock/Edit.spec.ts` (SSR
`renderToString` of the real component with `installTranslations`).

## Visual evidence plan, slice 2 (measurement first)

Follows `skills/agent-workflow/references/visual.md` and `AGENTS.md` "Measure before screenshot": no screenshot unless an
unexplained failure, a new direction or the user asks (then one, cropped or at scale 0.3–0.5). Fixture *(planned)*:
`resources/js/fixtures/producer-landing.html` and `producer-landing-visual.ts`, modelled on `catalogue-landing-visual.ts`
(real `createInertiaApp`, real `AppLayout`, deterministic props, role `producer`, plus one multi-role variant for R17),
with manifests *(planned)* `producer-landing-lists`, `-forms`, `-cycle` (cutoff + summary), `-history`, `-shared`
(controls, navigation, title probe). It proves presentation only, never persistence or authorization (file header, as
slice 1). Runs are bound to a base SHA in an ignored `.atl/<task>/visual` directory.

**Dimensions by risk (explicit `cases`):**

- Baseline: six pages at 375 and 1280 px, light and dark, 100 % text = 24 cases.
- 1024 px at 200 % text, light and dark, one per composition: product list, product form, cutoff form, cycle summary,
  movement history = 10 cases; the dialog at 375 px/200 % = 2.
- Risk states at 375 light plus 1280 dark: no products (list), inactive product, long product name, no stock products, inactive
  and sold-out stock product, long unit, cutoff with validation errors, product form invalid, busy submit, cycle sent with
  lines, cycle sent with nothing to deliver, cycle not sent (available and unavailable), no products to offer, stock product
  with no movements, long movement list = about 16 states, ≈ 32 cases.
- Overlays: dialog by keyboard (open, Escape, focus return), account menu, mobile sheet, toast (Sonner probe) ≈ 8 cases.
- System appearance, reduced motion, fallback font ≈ 9 cases.
- Negative/leak: re-pointed controls (`Manage/Stock/Movements`, `Finance/Stock/Movements`, `Manage/Accounts/Index`,
  `Manage/Cycles/Index`), Welcome, variant-off for one Producer page, navigation Producer → unmigrated → Producer with the
  menu and the sheet open ≈ 12 cases.
- **Estimate ≈ 95–125 cases** (*uncertain; `run.mjs plan` decides*; slice 1 ran 454 for 11 pages). First cut if too large: the
  1280-dark twin of the risk states.

**Assertions:** `.reference-style.landing` count 1; `.pilot-theme:not(.landing)` count 0; `.welcome-page` count 0;
`#main-content h1:not(.sr-only)` `fontSizeRem: 1.5`; contrast on H1, muted, error, buttons, badges, field border; 44 px
`minWidth/minHeight` on every interactive element; tabular figures on quantity, balance and amount cells; state texts
(`Deactivated`, `Inactive`, `Sold out`, `Available`, `Unavailable`, `Nothing to deliver.`); no `background-image` in
`#main-content`; real-Tab `focus` lists; no horizontal overflow. Gradient/alpha cases get a focused computed-colour probe
(`skills/agent-workflow/scripts/visual/measure.mjs`). R9 colour-role use stays an accepted gap unless a task adds an
assertion (slice 1 remainder, history 2026-11-15).

**First checkpoint (T10):** open `Producer/Products/Index` at 375 px light with the producer role: the first measurement
must still show green (`rgb(36, 107, 87)`) on the primary element — record the failing value — then admit the page and
re-measure the landing blue (`rgb(40, 101, 133)`, T84 constants), with the re-pointed controls, Welcome and slice 1
pages unchanged.

## Seeder decision, slice 2

Read: `database/seeders/DemoSeeder.php` and `tests/Feature/Database/DemoSeederTest.php`. Today: three producers with accounts
(`hort@`, `forn@`, `formatgeria@demo.guixa.test`, `:70-84`), two stock products for the first producer only
(`createStock :256-262`, one entry movement each), cycles `[0]`,`[1]` sent, `[2]` unsent with `Tomàquets` unavailable, a
deactivated producer, one inactive weekly product (`Coca de recapte`, `:165`, the only inactive and unused one), and the
Catalogue states from slice 1 (`createCatalogueStates :160-172`). `DemoSeederTest.php` covers Catalogue states
(`:112-141`) and only `/dashboard` and `/producer/products` for the producer (`:187-218`). **The seeder must change** to
show these R16 states, none of which exists today: (a) a producer with no products, (b) an inactive and a sold-out stock
product, (c) a stock product with no movements, (d) a long movement list, (e) a long product name and a long unit, (f) a
producer with nothing to deliver. Proposal, through existing use cases, in a new `createProducerStates()` called after
`createCatalogueStates` so cycles, orders and receptions stay untouched:

1. A fourth producer **with an account and no products**, created after the cycles (`CreateProducer` + `Account::inviteProducer`
   as `createCatalog :243`), so it is in no cycle: (a) its product list is the empty state, and cutoff opens.
2. For the first producer (`hort@`, the only one with stock): a third stock product with a long name and a long unit and **no
   movements** (c, e), a fourth stock product set sold out with `SetStockAvailable` quantity `0` (b), and a fifth deactivated
   (b; the seeder calls `DeactivateStockProduct`, `src/Catalog/Application/DeactivateStockProduct.php:11`; the Producer list is read-only, `Producer/Products/Index.vue:164`). Stock edits go
   through `RecordStockEntry`/`SetStockAvailable` (`src/Stock/Application`).
3. Only one existing stock product (not the no-movements one of item 2) gets 51 or more further `RecordStockEntry`/
   `RecordStockAdjustment` calls so its history pages past one page (d; `ListStockMovements::PER_PAGE` is 50,
   `src/Stock/Application/ListStockMovements.php:20`). `DemoSeederTest.php` asserts the count; the other stock products keep
   one opening movement.
4. A second inactive weekly product on a different producer (`Coca de sucre`, `forn@`), so the product list can show a
   reactivate on one and a delete-unused on the other without the two actions competing for `Coca de recapte`
   (reactivate makes it active; delete removes it). Names are fixed, so repeated runs stay idempotent (the seeder is
   guarded by the demo transaction; confirm with the existing idempotency test).
5. (f) "A cycle with nothing to deliver" is **not seeded**: it needs a producer inside a sent cycle with no order lines,
   which requires the producer to exist before the cycles and shifts producer counts, receptions and the dashboard
   assertions at `DemoSeederTest.php:46-64`. It is covered by Vitest and the visual fixture (deterministic props). The
   handoff says so (D11).

`DemoSeederTest.php`: update counts (`:22` producers 4 → 5, products and users as applicable); extend the idempotency tables
only if a new table is read; add a test "seeds the Producer states" asserting a producer with no products, an inactive
stock product, a sold-out stock product, a stock product with no movements, a history longer than one page, two inactive
weekly products on different producers; extend the `'producer'` path list with `/producer/cutoff`, a cycle summary and a
stock movement history, and add the new account's `/producer/products` (empty). Risk: stock counts asserted elsewhere
in the file (*unverified; run the whole file*).

## Risks (slice 2)

| Risk                                                          | Evidence                                                                 | Mitigation                                                                     |
| ------------------------------------------------------------- | ------------------------------------------------------------------------ | ------------------------------------------------------------------------------ |
| Admitting the three probe pages breaks slice 1 evidence       | `catalogue-landing-visual.ts:447,458,460`; manifests cited above          | D7: re-point before any admission, in T10                                      |
| Unmigrated global `.pilot-theme` rules leak into other pages | `.pilot-layout-panel :235`, `.pilot-data-table :594`, `.pilot-t44-page :188-204` shared with unmigrated pages | `.landing` only; probes on `Manage/Accounts/Index` and `Manage/Cycles/Index` |
| `MovementHistory` restyle reaches Finance/Manage histories    | `components/stock/MovementHistory.vue` used by `Finance/Stock/Movements`, `Manage/Stock/Movements` | no markup change; probe both                                                   |
| `ActiveSwitch` restyle reaches `Manage/Accounts/Index`        | `components/ActiveSwitch.vue`                                            | probe retained                                                                 |
| No unmigrated consumer of the product dialog / fields remains | all `ProductAdministrationActions`, `WeeklyProductFields` consumers migrated | `NativeDialog` base probed through `Manage/Cycles/Index`; document the loss    |
| Title not 24 px on some Producer page                         | expectation from `app.css:192-195`, unmeasured here                       | D6: measure in T10 and per task                                                |
| Touch targets < 44 px in stock table and summary toggle       | `.pilot-t44-page` rules cover buttons and anchors, not every switch/label | probe first; fix under `.landing`                                              |
| Seeder additions change counts and dashboard assertions       | `DemoSeederTest.php:22,46-64`                                            | create after cycles; no nothing-to-deliver seed (D11); run whole file          |
| Pre-landing manifests still list the Producer pages          | `dra-198`, `dra-205`, `dra-208`, `dra-209`, `reference-all-pages`, `reference-gates` `.visual.json` | verified manual-only: no CI step or script runs them (`tests.yml` runs `vp test run`, which includes only `resources/js/**/*.spec.ts`). Leave them; they describe the pre-landing look. The CI guard that does change is `lib/pilotTheme.spec.ts` (T10) |
| Fixture bootstrap duplicated a fourth time                    | `catalogue-landing-visual.ts` 1653 lines                                 | D9: new fixture, extraction still deferred                                     |
| Producer privacy regression                                   | R17                                                                       | presentation-only diff review; existing Pest suites unchanged                  |

## Decisions the plan proposes, slice 2 (to confirm)

- **D6 — `pilot-title-compact` on Producer pages.** Recommendation: **do not add it**; the six roots carry `pilot-t44-page`,
  which already sets 24 px. T10 measures the six pages; add the class only to a page that measures otherwise (recorded as a
  Deviation if so).
- **D7 — Replacing the three probe pages.** Recommendation: re-point once, in T10, before admission: history probe to
  `Manage/Stock/Movements` and `Finance/Stock/Movements` (both unmigrated, already in the fixture); dialog probe to
  `Manage/Cycles/Index` (`NativeDialog` consumer); drop the `WeeklyProductFields` probe (no consumer left). Alternative
  (a permanent synthetic control page) rejected as in slice 1 D4.
- **D8 — Admission grouping.** Recommendation: one page task admits one page (or one family), each with its own reversible
  entry, as in slice 1; no single switch for all six, so a failed measurement blocks only its page.
- **D9 — Fixture shape.** Recommendation: new `producer-landing` fixture and manifests (role `producer`); no shared bootstrap
  extraction now (D5 stands), revisit if a fifth copy is needed.
- **D10 — Seeder reactivate/delete conflict.** Recommendation: add `Coca de sucre` as a second inactive unused weekly product
  on `forn@` (item 4) instead of changing `Coca de recapte`.
- **D11 — Nothing-to-deliver seed.** Recommendation: not seeded (fixture and Vitest only); the seeder handoff names the
  reason. Alternative: create a fifth producer before the cycles, which changes cycles, receptions and dashboard
  assertions; rejected for risk.

## Ordering and dependencies, slice 2

Numbering is not sequencing. T10 first: it creates the fixture, re-points the controls and proves the admission contract on
one page; every other page task needs the re-pointed controls (admitting a probe page earlier would fail slice 1
manifests). T15 (seeder) has no code dependency and may run in parallel. T16 needs every admitted page and the seeder (the
user reviews the states).

```
T10 ──► T11 product forms and dialog
   ├──► T12 cutoff
   ├──► T13 cycle summary
   └──► T14 movement history
T15 seeder (independent) ──► T16
T10..T14 ──► T16 acceptance
```

T11–T14 are independent of each other once T10 merges (different files; each adds its own manifest and spec). If two
of them change the same `.landing` block, the later one re-runs the earlier one's manifest as its probe.

## Proposed task slicing, slice 2 (names and allocation only; task text belongs to `tasks.md` after approval)

Last existing ID in `tasks.md` is T09 (`tasks.md:343`), so slice 2 continues at T10. Each task stays within the 400
authored-line review prompt (`skills/agent-workflow/references/planning.md:39`: a prompt, not a quota; estimates are
uncertain, medium confidence).

| ID  | Outcome (name)                                                                      | Requirements / scenarios                                                                  | Notes and dependency reason                                                                                                                      |
| --- | ----------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| T10 | Producer product list in the landing identity, with fixture and controls re-pointed | R14, R15 (title measurement), R16 and R17 for `Products/Index`; S13 (start), S14 (list), S16 (toggle, reactivate, delete busy and error), S17 (first states) | Tracer: producer fixture, `-shared` manifest, `landingPages` row, flipped specs, probes re-pointed (D7). First dependency of all page tasks. Est. 250–400 lines. |
| T11 | Producer product forms and classification dialog                                    | R14–R16 for `Create`, `Edit`; R18 (specs `Create`, `Edit`); S14 (forms), S16 (dialog)      | Needs T10's fixture and re-pointed dialog probe. Field pattern already measured (T05). Est. 200–350 lines.                                       |
| T12 | Producer default cutoff                                                             | R14–R16 for `Cutoff`; R18 (spec `Cutoff`); S15 (cutoff valid and invalid)                  | Needs T10 fixture. Smallest page. Est. 120–220 lines.                                                                                            |
| T13 | Producer cycle summary                                                              | R14–R16 for `CycleSummary`; R18 (spec `CycleSummary`); S15 (summary, availability toggle)  | Needs T10 fixture. Distinct states (sent, unsent, empty). Est. 180–300 lines.                                                                    |
| T14 | Producer stock movement history                                                     | R14, R16 for `Stock/Movements`; R17; R18 (spec, `layout-rules.spec.ts`); S16 (history)     | Needs T10's re-pointed history probe; `MovementHistory` has two unmigrated consumers, so the leakage probe is mandatory. Est. 120–250 lines.     |
| T15 | Demo seeder shows the Producer states                                               | R19 (demo data); supports S14–S16                                                          | `DemoSeeder.php` + `DemoSeederTest.php`; independent of the UI tasks, needed before T16. Est. 100–180 lines.                                      |
| T16 | Slice 2 acceptance record and visual approval request                               | R15 (area-wide, against slice 1), R17, R18, R19; S13, S17, S18                              | Needs T10–T15 merged: integrated-base run of all manifests, R→task→evidence→verdict map, asks the user for visual approval and logs it as Q&A; no code unless a correction is needed. |

Every R and S has an owner: R14–R16 per page task (T10–T14); R15 area-wide and S13 complete in T16; R17 in each page task
and T16; R18 in T11–T14 (T10 keeps `Index.spec.ts` green); R19 seeder in T15, approval in T16; S14 completes with T11,
S15 with T12/T13, S16 with T10/T11/T14, S17 with T10 and T16, S18 only in T16. Nothing is deferred. Each task is
tests-first, ships code, specs, manifest states and history entries together, and ends with focused Vitest,
`vp check --fix`, type check and candidate-bound CI; each handoff states "Demo seeder: see T15" or "no change" (`AGENTS.md`).

## Limits (slice 2)

Covers 005 slice 2, R14–R19/S13–S18, only. Slices 3–7, mail, error pages, the Dashboard links to these pages, first paint,
favicon and global promotion stay outside. Plan acceptance, the task breakdown, publication and implementation are separate
user decisions; the slice 2 spec acceptance gate stands first.
