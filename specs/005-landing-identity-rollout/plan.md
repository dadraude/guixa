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

# Slice 3-7 — Rest of the application · Plan

**Status: Draft, awaiting acceptance.** Covers the [spec](spec.md) section "Slice 3-7" only (R20-R30, S19-S30; OQ4-OQ8
answered 2026-11-16) and inherits slices 1 and 2, whose plan parts above stay as written. It authorizes no tasks, tickets or
implementation; the spec acceptance gate stands first. Paths are relative to the repository root; the pages directory is
`resources/js/pages/`. Everything marked *unverified* was not run or measured while drafting: no test, build or browser check
was executed for this section (code reading only, worktree at `origin/main` c43a1f43).

## Readiness for this plan (slice 3-7)

| Concern                | State                       | Evidence and limit                                                                                                                                                                                                                                                |
| ---------------------- | --------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Behavior               | Ready                       | R20-R30 and S19-S30 are specific; OQ4-OQ8 answered (`spec.md:419-423`). The spec is Draft until the user accepts it (`spec.md:278`); planning proceeds on that draft and stops at acceptance.                                                                   |
| Decisions              | Ready, with 8 plan choices  | No open question. D12-D19 below are design choices the plan proposes; none changes a requirement. Corrections 1-4 need a Change entry before tasks.                                                                                                              |
| Design                 | Ready, with 2 new seams     | Admission, tokens and overlay carriers exist (slices 1 and 2). New work: the select popup (no `usePilotVariantClass` today), the auth shell (no variant plumbing today), the page-less first-paint and error page, the global promotion. All designed below.   |
| External prerequisites | Ready                       | None: no service, package, credential or migration. Slices 1 and 2 integrated and approved.                                                                                                                                                                       |
| Paths                  | Ready                       | The 36 pages/overlays, controllers' routes and shared components verified below; new files marked *(planned)*. OQ8 requires a re-verification at each checkpoint start (D19).                                                                                     |
| Verification           | Ready, planned              | Visual runner (`skills/agent-workflow/scripts/visual/run.mjs`), Vitest and Pest exist; fixtures `catalogue-landing-visual.ts` and `producer-landing-visual.ts` are the model. Nothing run for this draft.                                                        |
| Seeder                 | Not ready                   | `database/seeders/DemoSeeder.php` lacks four R22 states (deactivated household, deactivated member account, role with no holder, account with two-factor/passkey); settled-vs-unpaid balance unverified. Closed by seeder tasks, not by a decision (see *Seeder decision, slice 3-7*). |
| Page-spec coverage     | Not ready                   | 22 pages/overlays have no Vitest spec today (R26; list in *Test plan*). Closed inside the page tasks, tests first.                                                                                                                                                |
| Tracking               | Not ready                   | `tasks.md` has no slice 3-7 tasks; the 5-checkpoint gate (R20) needs one acceptance task per checkpoint. Closed by the task breakdown after plan acceptance.                                                                                                      |

### Corrections (for the coordinator to log as a Change; this section does not edit `spec.md`)

1. **Overlay count.** `spec.md:297` says "4 overlay components"; the C1 and C2 rows name five. Four are page-directory
   components (`pages/Manage/Cycles/CycleCreateDialog.vue`, `CycleManageDialog.vue`, `pages/Manage/Households/SettlementDetails.vue`,
   `pages/Manage/Stock/StockEditDialog.vue`); `ReceivedTotalForm` is `components/reception/ReceivedTotalForm.vue`. Proposed: "5 overlay components".
2. **`StockEditDialog` is already under a landing page.** Its only consumer is `Manage/Stock/Index`, admitted in slice 1.
   It is not an admission: C2 only measures it (S22) and adds the missing spec. Proposed wording: "overlay already rendered by a landing page".
3. **`WelcomePage` is not in Scope.** `pages/Welcome.vue` is a thin wrapper (`Welcome.vue:3,9`); the visible page is
   `components/WelcomePage.vue` (+ `WelcomePage.spec.ts`, `fixtures/welcome-visual.ts`). Proposed: C5 covers `Welcome` through
   `WelcomePage` (D18), as the landing source of truth, not a restyle.
4. **Overlays not named by path.** `components/DeleteUser.vue` (delete-account dialog, used by `settings/Profile.vue`),
   `components/TwoFactorSetupModal.vue`, `components/ManagePasskeys.vue`, `PasskeyRegister.vue` (C4 row says "dialogs") and
   `components/roles/RoleHolderRemove.vue` (used by `RoleGroup.vue`, C2 row says "holder-removal dialog") are the overlays; assigned to C4 and C2.
5. **OQ8 inventory scope.** `resources/views/**` also holds `mail/invitation.blade.php` and `mail/producer-order.blade.php`;
   mail is a non-goal (`spec.md:383`), so the inventory excludes `resources/views/mail/**`. The inventory covers pages, `app.blade.php`,
   `errors/minimal.blade.php` and the overlay components.
6. **`pilotPages` gaps.** `Manage/Roles/Index`, `Member/Baskets/Index` and `Member/Baskets/Show` are in neither set
   (`lib/pilotTheme.ts:1-53` verified by name), so they are not on the older green look but on the global look. Their admission
   adds the name to both sets (contract point 2); "previous look" in R21 means the global look for these three.
7. **Paths and counts.** Spec Scope counts 56 page files, 20 landing, 36 here; re-counted at each checkpoint start (D19).

## Architecture overview (slice 3-7, source-backed)

**What already works.** Admission is one exact-name entry in `landingPages` (`lib/pilotTheme.ts:55-76`, 20 names); a page
must also be in `pilotPages` (`:1-53`). `AppLayout.vue:12-13` derives `pilotTheme` (older green) and `pilotVariant` (`landing`)
from the Inertia page name and passes them to `AppSidebarLayout.vue`, which provides them (`:33-34`) and adds the classes
`pilot-theme ... landing` to the shell (`:60`). Portalled content takes the class through `usePilotVariantClass`
(`composables/usePilotTheme.ts`) in `ui/dialog/{DialogContent,DialogOverlay}`, `ui/dropdown-menu/{DropdownMenuContent,DropdownMenuSubContent}`,
`ui/sheet/{SheetContent,SheetOverlay}`, `ui/tooltip/TooltipContent`, `ui/sonner/Sonner`. Landing CSS sits only under `.landing` /
`.dark .landing` (`resources/css/app.css:2124-2237` and the sub-blocks of slices 1 and 2); tokens mirror `components/WelcomePage.vue`.

**First missing behaviors (the reason for the task order).**

| Gap | Source | Consequence |
| --- | --- | --- |
| `ui/select` popup has no variant class | `components/ui/select/SelectContent.vue` wraps `SelectPortal` (`:32`); no `usePilotVariantClass` in `ui/select/*` | the popup would render in the global look on a landing page; R24 owned by C1. `FormSelect.vue` wraps `ui/select` (`:2-10`), so one change reaches its 10 consumers (below) |
| Auth shell has no variant | `layouts/AuthLayout.vue:12` passes only `pilotTheme`; `layouts/auth/AuthSimpleLayout.vue:17` provides only the theme; no `landing` class on `.material-auth` | auth pages (C4) can only reach the older green today; C4's first task adds the variant plumbing |
| `settings/*` use `AppLayout` + `SettingsLayout` | `app.ts:13-22`; `layouts/settings/Layout.vue:27` (`pilot-settings`) | settings pages are admitted like app pages; the settings layout is a shared component (R23) |
| Error page is outside Vite | `resources/views/errors/minimal.blade.php` (inline Tailwind v1 CSS, white page, grey text, no `app.css`) | cannot use `.landing`; needs its own palette literals mirrored from the landing tokens (D16) |
| First paint | `resources/views/app.blade.php:19-26` (`html` background from `--background`, fallbacks `#fff`/`#1b1e1c`); `public/theme-colors.css` sets the global green palette on `:root` | until C5 the background before hydration is the global palette (OQ7); C5 changes `:root` |
| Tab icons | `app.blade.php:30` links only `/favicon.svg`; `public/{favicon.svg,favicon.ico,apple-touch-icon.png}` exist | OQ4: recolour the three files and link all three |

**Pages by checkpoint (verified against `main`).** All 36 exist. "Gate" is the current state in `lib/pilotTheme.ts`.

| Checkpoint | Pages and overlays | Gate today | Specs today | PHP coverage (verified) |
| --- | --- | --- | --- | --- |
| C1 | `Finance/Baskets/{Index,Create,Show}`, `Finance/Stock/{Index,Movements}`, `Receptions/{Index,Show}`; `ReceivedTotalForm` | all in `pilotPages` | `Baskets/Index`, `Baskets/Show`, `Stock/Index`, `Receptions/Show`, `ReceivedTotalForm`; **none** for `Baskets/Create`, `Stock/Movements`, `Receptions/Index` | `tests/Feature/Sales/BasketPagesTest.php:24-29` and `Finance/FinanceBasketAccessTest.php:49` (Create); `Ordering/ReceptionPageTest.php:254-255,304` (Index, OK + component); `Stock/StockMovementHistoryPagesTest.php` |
| C2 | `Manage/{Accounts/Index, Households/{Index,Show}, Producers/Index, Cycles/{Index,Show}, Roles/Index, Settings, Stock/Movements}`; `CycleCreateDialog`, `CycleManageDialog`, `SettlementDetails`, `StockEditDialog` (+ `RoleHolderRemove`) | all in `pilotPages` except `Manage/Roles/Index` (Correction 6) | `Accounts/Index`, `Cycles/{Index,Show}`, `CycleManageDialog`, `Stock/Movements`, `RoleHolderRemove`; **none** for `Households/{Index,Show}`, `Producers/Index`, `Roles/Index`, `Settings`, `CycleCreateDialog`, `SettlementDetails`, `StockEditDialog` | `Manage/RolesPageTest`, `Membership/{HouseholdPagesTest,AccountPagesTest}`, `Ordering/CyclesPagesTest` |
| C3 | `Wallet/Statement`, `Stock/Index`, `Member/Baskets/{Index,Show}` | `Wallet/Statement`, `Stock/Index` in `pilotPages`; `Member/*` in neither | `Statement`, `Member/Baskets/{Index,Show}`; **none** for `Stock/Index` | `Wallet/WalletPagesTest`, `Stock/StockDiscoveryTest`, `Sales/MemberBasketReadTest` |
| C4 | `auth/{Login,ForgotPassword,ResetPassword,VerifyEmail,ConfirmPassword,TwoFactorChallenge,AcceptInvitation}`, `settings/{Profile,Security,Appearance}`; `DeleteUser`, `TwoFactorSetupModal`, `ManagePasskeys`, `PasskeyRegister` | all in `pilotPages` | `PasskeyRegister`, `PasskeyVerify` only; **none** for the 10 pages | `Auth/*`, `Settings/{SecurityTest,ProfileUpdateTest}`; `Membership/InvitationFlowTest` exercises `invitations/{token}` (routes `web.php:111-112`) but no test asserts the `auth/AcceptInvitation` component name (*confirm in the C4 task*) |
| C5 | `Dashboard`, `Welcome` (+ `WelcomePage`), `errors/minimal.blade.php`, `app.blade.php`, tab icons, global theme, orphans | `Dashboard` in `pilotPages`; `Welcome` in neither | `Dashboard.spec.ts`, `WelcomePage.spec.ts`; **none** for `pages/Welcome.vue` | `DashboardTest` |

**Select users (R24, `FormSelect` or `ui/select`).** C1: `Finance/Baskets/Create`, `Receptions/Show`, `pickup/StockLines`, `pickup/ReturnLines`,
`reception/AddAllocationForm`. C2: `Manage/Accounts/Index`, `Manage/Households/Show`, `Manage/Settings`, `Manage/Stock/Movements`, `CycleCreateDialog`.
C3: `Wallet/Statement`. Fixtures only: `fixtures/{SelectVisual,ManagerFieldsVisual}.vue`, `select-visual.ts`, `selects.visual.json` (existing manifest: the
old-theme popup baseline, kept as the "current look" control, D13). `FormSelect.spec.ts` exists; `ui/select` has no spec.

**Orphans for C5 (re-verified).** `rg` over `resources` and `tests` finds no importer of `layouts/auth/AuthCardLayout.vue` or
`layouts/auth/AuthSplitLayout.vue`; `layouts/app/AppHeaderLayout.vue` is imported by nobody and itself imports `components/AppHeader.vue`
(`AppHeaderLayout.vue:3`), whose only importer is that layout. Delete the four together (R29). *Re-run the search in the task; a page added meanwhile may import them.*

**Shared-component leakage (R2, R23).** Landing styling lands only under `.landing`; no markup, class or prop change in a
shared component unless a probe shows unmigrated consumers' computed values unchanged. The unmigrated probe set shrinks per
checkpoint; the probe is always "a page of a later checkpoint", chosen from the table below, never a synthetic page.

| Checkpoint ending | Probe pages (still old-theme) for shared-component changes |
| --- | --- |
| C1 | `Manage/Accounts/Index` (select, `ActiveSwitch`), `Manage/Cycles/Index` (`NativeDialog`), `Manage/Stock/Movements` (`MovementHistory`), `Wallet/Statement` (select), `auth/Login` |
| C2 | `Wallet/Statement`, `Member/Baskets/Index`, `settings/Profile`, `auth/Login`, `Dashboard` |
| C3 | `settings/Profile`, `auth/Login`, `Dashboard` |
| C4 | `Dashboard`, `Welcome` (landing already; negative control for `.welcome-page`) |
| C5 | none: global promotion; the check is before/after on one page of each checkpoint (S29) |

## Contract for admitting a page (reuse)

The seven-point contract of slices 1 and 2 applies unchanged: exact name; also in `pilotPages` (Correction 6: add both for
`Manage/Roles/Index`, `Member/Baskets/{Index,Show}`); measured before merge; tests flipped in the same change
(`lib/pilotTheme.spec.ts`, `composables/usePilotTheme.spec.ts`, per-page specs that assert `pilotVariantFor`); controls re-pointed
if a probe page is admitted; shared-consumer probe from the table above; reversible (one name per entry, D8 precedent).
Specifics:

- Point 5: the existing probes in `catalogue-landing-visual.ts` and `producer-landing-visual.ts` (and their manifests) point to
  `Manage/Stock/Movements`, `Finance/Stock/Movements`, `Manage/Cycles/Index`, `Manage/Accounts/Index`. Admitting those pages
  removes the control: re-point **before** the admitting task, to the next-later page (table above), in the same PR (D14).
- Point 4 also covers `layout-rules.spec.ts`: it lists landing pages for the 8 px grid check (`:6-13`); each admitted page joins.
- Auth pages (C4) cannot be admitted by name alone: they need the shell variant first (D15).

## Per-requirement design

### R20 — Checkpoints, order and gate (S30)

One acceptance task per checkpoint (C1..C4 closure, C5 closure) records R5-R9 as measured text for its pages, then asks the user
for visual approval in light and dark and logs it as a Q&A naming the evidence. No task records the approval on the user's behalf.
Later checkpoints are blocked by that Q&A (OQ5: rework stays in the same checkpoint; OQ6: a fix to an approved page goes in the
current checkpoint's PR as a Deviation with R30 re-measurement). Task statuses show the block; no code mechanism.

### R21 — Every page of a checkpoint in the landing page set (S19, S21, S23-S25)

One `landingPages` entry per page, in the page's task (D8). Overlays inherit `.landing` from the shell (modal) or through
`usePilotVariantClass` (portalled); the cycle, settlement and reception dialogs use `NativeDialog`
(`components/manager/NativeDialog.vue`), already measured on slices 1 and 2. No change to `AppLayout.vue`, `AppSidebarLayout.vue`
or `usePilotTheme.ts` for app pages. For auth pages see R22/C4 design below.

### R22 — Measurable conditions per composition (S27)

Method of slices 1 and 2 per page, as measured text: contrast pairs, 44 px minimums, tabular figures on amounts/balances/quantities
(`pilot-amount`, `tabular-nums`; probe which cells carry them), text for states (`Deactivated`, `Sold out`, `Sent`, `Settled`...;
colour never the only signal), no overflow at 375 px and 1024 px/200 %. Composition-specific design:

| Checkpoint | Composition | Probe first (likely issue) |
| --- | --- | --- |
| C1 | basket list/form/detail with amounts | `Finance/Baskets/Create` select and amount fields; row layout in `components/baskets/*`, `financeBasketRow.ts` fixture; money columns right-aligned and tabular |
| C1 | finance stock and movement history | `MovementHistory` shared with `Manage/Stock/Movements` (still old-theme): leakage probe mandatory |
| C1 | reception list/detail, total dialog | `reception/AllocationRow.vue`, `pickup/*Lines` tables at 375 px; `ReceivedTotalForm` dialog by keyboard |
| C2 | account and household tables | `ActiveSwitch` and `.pilot-status-switch` already measured (slice 1); deactivated rows read as text |
| C2 | household detail with settlement | `SettlementDetails` (overlay); unpaid vs settled balance as text plus amount |
| C2 | producer table, settings form | tables at 375 px; `Manage/Settings` select and invalid/server-error states |
| C2 | cycle list/detail and dialogs | `CycleCreateDialog` (select + dialog), `CycleManageDialog`; busy/disabled actions |
| C2 | role groups, holder removal | `RoleGroup` empty group ("role with no holder"); destructive removal not styled primary (S22) |
| C3 | wallet statement | amounts to the cent, running balance, tabular; multi-role access (R25) |
| C3 | stock list, member baskets | sent/unsent basket as text; empty and long names |
| C4 | sign-in/recovery cards, invitation | `AuthSimpleLayout` card on the landing paper background; server-error and invalid forms |
| C4 | profile, security, appearance, dialogs | two-factor setup modal (QR, codes), passkey add/remove, delete-account dialog by keyboard |
| C5 | dashboard, transition, error page | dashboard cards keep 004 capability; error page is static HTML, measured by fetching the rendered view |

### R23 — Shared components change page-scoped until C5 (S26)

Every CSS change is under `.landing`. A component whose template must change (the select popup, the auth shell) gets a class
added only when the variant is active (empty string otherwise, as `usePilotVariantClass` does), so old-theme output is byte-identical.
The check for each task: computed colours and fonts of the probe page (table above) before and after on the integrated base.
The probe set shrinks to none at C5 (promotion); R30 re-measures one slice 1 and one slice 2 page after any shared-component change.

### R24 — The select takes the identity (S20)

**Design.** Add `usePilotVariantClass` to `ui/select/SelectContent.vue` (the popup root, inside `SelectPortal`), exactly as
`DialogContent.vue` and `DropdownMenuContent.vue` do (*read them in the task and copy the pattern*). The landing look is CSS
under `.landing` for the popup surface, items (highlight/selected), scroll buttons and the trigger focus ring; text and surface
pairs meet R5. Trigger styling inside a landing page already follows tokens (`--input`, `--ring`). `FormSelect.vue` needs no change
unless its `contentClass` overrides colours (*read first*). Keyboard selection, Escape and focus return are Reka UI behaviour
(unchanged); the task proves them with a keyboard case, not by assumption. **Owner: the first C1 task that reaches a select (D12).**
Evidence: popup open on a landing page (light/dark, keyboard and touch), the same on `Manage/Accounts/Index` (old-theme control) with
computed values equal to the baseline in `selects.visual.json`. Later checkpoints (C2, C3) add their pages to the existing
cases, they do not edit the select.

### R25 — Behaviour, access and money unchanged (S19, S21, S23-S25)

Presentation only; no PHP, route, prop, validation, translation or calculation edit. Guards: the Pest suites named above run
unchanged per task (their ordering names the assertions of money and access). Multi-role navigation (`Wallet/Statement` reached by
a member, a manager, finance): the variant derives from the page name, so by construction; one navigation case per checkpoint
measures it. For C4: no test change to sign-in/two-factor/passkey/destinations; the Pest `Auth/*` and `Settings/*` run unchanged; the
appearance preference (light, dark, system) is covered by an `Appearance` spec asserting the three options and the stored value are untouched.
Visual corrections found are recorded as a Deviation (R25 last sentence).

### R26 — Tests stay green; gaps get coverage (S27)

See *Test plan, slice 3-7*.

### R27 — Evidence, approval, demo data

See *Visual evidence plan* and *Seeder decision*. Each checkpoint's closure task builds the R12 map and asks for approval (S30).

### R28 — Dashboard, welcome, errors and tab icons (S28)

- **Dashboard.** `pages/Dashboard.vue` (already in `pilotPages`) enters `landingPages` with the same contract; `Dashboard.spec.ts` stays green. Capability stays 004 (no card added, removed or reordered).
- **Transition without flash.** Welcome renders no layout (`app.ts:13-14`) and uses its own CSS; the app shell paints `html` from
  `--background` (`app.blade.php:19-26`). After C5's promotion `--background` is the landing paper in light and dark, so both ends match; the fallbacks `#fff` / `#1b1e1c` in the inline
  style change to the landing values (`#faf1dc` / `#1c201f`) so a missing stylesheet does not flash either (D17). Measured: `getComputedStyle(html).backgroundColor`
  at first paint against the landing token, light and dark, on `/`, `/login` and `/dashboard`.
- **Error page.** `errors/minimal.blade.php` has no Vite assets; it is restyled with inline CSS using the landing palette literals (paper, ink, blue, border) and the
  font stack the app resolves, light and dark through `prefers-color-scheme`; no illustration. Copy, status codes and the `@yield` structure stay. Measured by rendering the view for 403, 404, 500 and reading computed styles (D16).
- **Tab icons.** Recolour `public/favicon.svg`, `public/favicon.ico`, `public/apple-touch-icon.png` to the landing palette (OQ4) and add the two missing `<link>` tags in `app.blade.php`. The `.svg` may carry a `prefers-color-scheme` variant for dark tabs; `.ico` and `.png` are static (the light-palette version). Evidence: file dimensions, dominant colours read from the files as text (no screenshot).

### R29 — Global promotion and removal of the older opt-in (S29)

Last task of C5 after every other checkpoint is approved. Design:

1. Move the landing values from `.landing` to the application theme: the `:root` palette in `public/theme-colors.css` and the token blocks in `resources/css/app.css`
   (`.landing` / `.dark .landing`, `:2124-2237`) become the global values; sidebar tokens included.
2. Remove `pilotPages` and `landingPages`, `pilotVariantFor`, `isPilotThemePage`; `AppLayout.vue`, `AuthLayout.vue`, `AppSidebarLayout.vue`, `AuthSimpleLayout.vue` and
   `composables/usePilotTheme.ts` keep the class on the shell only if a CSS rule still needs it (D15, D17); otherwise drop the props. Specs for the removed functions are deleted with them; the page specs lose their `pilotVariantFor` assertions.
3. Rename-or-keep: the `.pilot-*` classes and `.landing` rules stay as plain rules (renaming is out of scope; no refactor beyond the promotion).
4. Delete the four orphans (R29).
5. The way back is `git revert` of C5's promotion PR; the PR keeps the promotion in one commit-sized change so the revert is clean (D17).
6. "No old-theme page" inventory (OQ8): script over `resources/js/pages/**` and overlay components listed in Scope, plus `resources/views/{app,errors/minimal}.blade.php` and not `views/mail/**` (Correction 5), asserting each page resolves the landing identity; run again at each checkpoint start (D19).

Evidence: computed colours and fonts of one page per checkpoint before and after (S29), a navigation case with the mobile menu open (Inertia navigation, light and dark).

### R30 — Earlier scope kept (S29)

Slice 1 and 2 pages keep their evidence. Each checkpoint's closure re-runs the slice 1 and slice 2 manifests (`catalogue-landing-*`, `producer-landing-*`) on the integrated base after any shared-component change (select, auth shell, `ui/*`, `MovementHistory`), citing the result. The existing `reference-*`, `dra-*` manifests stay manual-only and unrun (precedent, slice 2 risks).

## Test plan, slice 3-7 (TDD per scenario)

One failing check per rule or scenario before the minimum change. Behaviour does not change, so the executable specs are Vitest
(admission, page regression, select), Pest suites already named (unchanged) and the visual runner (presentation). A test that only reads a CSS class is not evidence of look.

**Existing tests that stay green unchanged in meaning (R26/R11):** every spec listed under "Specs today" above; `lib/pilotTheme.spec.ts`,
`composables/usePilotTheme.spec.ts`, `layout-rules.spec.ts` (edited only as the contract says); `FormSelect.spec.ts`; `components/manager/NativeDialog.spec.ts`;
the Pest suites listed under "PHP coverage" above, run once per task whose page they cover; slice 1 and slice 2 Vitest specs.

| Scenario / rule | Failing check first | Where |
| --- | --- | --- |
| R21/S19, S21, S23, S24 admission (per page) | `pilotVariantFor('<page>') === 'landing'`; shell and portalled content get `landing` and lose it on leaving | `lib/pilotTheme.spec.ts`, `composables/usePilotTheme.spec.ts` |
| R24/S20 select | `SelectContent` adds the variant class when a variant is provided and none otherwise (SSR render with `providePilotVariant`) | `components/ui/select/SelectContent.spec.ts` *(planned)* |
| R24/S20 keyboard | Escape closes, focus returns, same option chosen, on a landing and an old-theme page | visual runner interaction case (not unit testable in SSR) |
| R26/S19 C1 specless | `Baskets/Create`: title, one primary action, household select, error shown through `InputError`; `Stock/Movements`: title, `MovementHistory`, entry/adjust controls only for the permitted role; `Receptions/Index`: title, rows, empty state | `pages/Finance/Baskets/Create.spec.ts`, `pages/Finance/Stock/Movements.spec.ts`, `pages/Receptions/Index.spec.ts` *(planned)* |
| R26/S21 C2 specless | `Households/Index`, `Households/Show` (settlement shown, deactivated row text, `SettlementDetails`), `Producers/Index`, `Roles/Index` (role with no holder text, remove control), `Settings` (form, errors), `CycleCreateDialog`, `SettlementDetails`, `StockEditDialog` | one `*.spec.ts` beside each *(planned)* |
| R26/S23 C3 specless | `Stock/Index`: title, list, empty state, no write control | `pages/Stock/Index.spec.ts` *(planned)* |
| R26/S24, S25 C4 specless | each of the 7 `auth/*` and 3 `settings/*` pages: title, one primary action, `InputError` after its field, busy state disables submit; `Appearance`: three options present and unchanged | `pages/auth/*.spec.ts`, `pages/settings/*.spec.ts` *(planned)* |
| R26/S28 C5 specless | `pages/Welcome.vue` renders `WelcomePage` (title, sign-in link); `Dashboard.spec.ts` stays | `pages/Welcome.spec.ts` *(planned)* |
| R15/S24 auth shell variant | `AuthSimpleLayout` carries `landing` only when the variant is provided; unchanged otherwise | `layouts/auth/AuthSimpleLayout.spec.ts` *(planned)* |
| R29/S29 inventory | script: no page resolves old-theme; mail excluded; orphans absent | `resources/js/layout-rules.spec.ts` or a new inventory spec *(planned, D19)* |
| R28/S28 error page | the view renders for 403/404/500 with the landing literals and no `<img>`/`<svg>` illustration | `tests/Feature/ErrorPagesTest.php` *(planned; confirm none exists)* |
| R25 guards | no new test; Pest suites above re-run; handoff states "no PHP behaviour changed" | existing |
| R27 seeder | new states asserted | `tests/Feature/Database/DemoSeederTest.php` |

Spec pattern to mirror: `pages/Producer/Products/Index.spec.ts` and `pages/Manage/Stock/Edit.spec.ts` (SSR `renderToString` of the real component with `installTranslations`).

## Visual evidence plan (measurement first, per AGENTS.md "Measure before screenshot")

Follows `skills/agent-workflow/references/visual.md`: DOM, `getComputedStyle`, `getBoundingClientRect` and `measure.mjs` first;
no screenshot unless an unexplained failure, a new direction or the user asks (then one, cropped or scale 0.3-0.5). Fixtures
*(planned)*, modelled on `catalogue-landing-visual.ts` and `producer-landing-visual.ts` (real `createInertiaApp`, real layouts, deterministic props), one per checkpoint so a
failed measurement blocks one checkpoint: `rest-c1-landing`, `rest-c2-landing`, `rest-c3-landing`, `rest-c4-landing` (auth shell and settings), `rest-c5-landing` (dashboard, transition, error page by static render). No shared-bootstrap
extraction (D9 stands; revisit at the fifth copy, which this is: see D13). Runs are bound to a base SHA in an ignored `.atl/<task>/visual` directory. Fixtures prove presentation only, never persistence or authorization.

**Dimensions per checkpoint (explicit `cases`; estimates uncertain, `run.mjs plan` decides):**

| Checkpoint | Baseline (pages × 375/1280 × light/dark) | 200 % text at 1024 (per composition × 2 appearances) | Risk states (375 light + 1280 dark) | Overlays and select | Leak, regression, system | Estimate |
| --- | --- | --- | --- | --- | --- | --- |
| C1 | 7 pages = 28 | 5 compositions = 10 | basket empty, long name, big amount, sent/unsent, busy submit, reception states, validation errors ≈ 24 | select popup (keyboard, touch), total dialog, account menu, sheet, toast ≈ 14 | old-theme select control, probe pages, slice 1/2 re-measure ≈ 14 | ≈ 90-120 |
| C2 | 9 pages = 36 | 8 compositions = 16 | deactivated account/household, unpaid/settled balance, role with no holder, long names, cycle states, errors ≈ 30 | 5 overlays by keyboard, select ≈ 16 | probes, slice 1/2 re-measure, multi-role ≈ 14 | ≈ 110-140 |
| C3 | 4 pages = 16 | 3 compositions = 6 | wallet empty/negative, long lines, sent/unsent basket, empty stock ≈ 12 | select on statement ≈ 4 | multi-role navigation (member, manager, finance), probes ≈ 10 | ≈ 45-60 |
| C4 | 10 pages = 40 | 6 compositions = 12 | invalid/server-error forms, busy, 2FA setup, passkey list, delete dialog ≈ 24 | dialogs by keyboard ≈ 10 | system appearance, reduced motion, auth→app navigation ≈ 12 | ≈ 95-120 |
| C5 | dashboard 4 + 3 error statuses × 2 appearances = 10 | 2 = 4 | per role dashboard ≈ 8 | none | first paint on `/`, `/login`, `/dashboard`, before/after promotion on one page per checkpoint (8), mobile menu navigation, icons ≈ 24 | ≈ 50-70 |

**Assertions (per page, as slices 1 and 2):** `.reference-style.landing` count 1 on app pages (auth shell: its `landing` carrier, D15); `.pilot-theme:not(.landing)` count 0 on migrated pages; `.welcome-page` count 0 off Welcome;
`#main-content h1:not(.sr-only)` size per composition (24 px under `pilot-t44-page`, else `pilot-title-compact`, D6 precedent); contrast on H1, muted, error, buttons, badges, field border, select popup item and highlight; 44 px
`minWidth/minHeight` on interactive elements; tabular figures on money cells; state texts; no `background-image` in `#main-content`; real-Tab `focus` list; no horizontal overflow; for portalled content, the carrier class on the popup root.

**First checkpoint per checkpoint (the tracer task):**

- C1: `Receptions/Show` at 375 px light still shows green on the select popup and primary action (record the failing value), then admit it and the select, and re-measure the landing blue (`rgb(40, 101, 133)`), with the old-theme control `Manage/Accounts/Index` unchanged.
- C4: `auth/Login` still green, then landing after the shell variant lands, with `settings/Profile` unchanged until its task.
- C5 promotion: record before values for one page per checkpoint, promote, re-measure equal (S29).

## Seeder decision, slice 3-7

Read: `database/seeders/DemoSeeder.php`, `tests/Feature/Database/DemoSeederTest.php`. Already seeded (from the brief and `DemoSeeder.php`): staff role holders, reception states, sold-out/retired stock, a long stock history, an unsent cycle with an unavailable
product, deactivated producer and product, an unlinked former member account, settlement drafts, opening balances (`:264-269`, top-ups through `RecordTopUp`). **Decision (per `AGENTS.md` "Demo seeder"): the seeder must change** for states that R22 needs and the demo does not show; the rest it already shows (no change).

| State needed (R22) | Pages | Seeded today | Proposal |
| --- | --- | --- | --- |
| Sent and unsent basket; reception states; sold-out stock; long history | C1, C3 | yes | none; the handoff says so (verify the member has both a sent and an unsent basket, *unverified*) |
| Unpaid and settled household balance | C2 `Households/Show`, C3 `Wallet/Statement` | opening balances only (*settled vs unpaid unverified*) | confirm by reading; if absent, add one settled and one unpaid household through existing use cases (D20 gives the placement) |
| Deactivated household | C2 `Households/*` | no | add through the existing deactivation use case (*find it in `src/Membership/Application`*) |
| Deactivated member account | C2 `Accounts/Index` | no (an *unlinked former* account exists, different state) | add through the existing account-deactivation use case |
| Role with no holder | C2 `Roles/Index` | no (all staff roles held) | one role left without a holder (e.g. a staff role that has none after seeding), asserted |
| Account with two-factor or passkey | C4 `settings/Security` | no | one account with two-factor enabled; a passkey record needs WebAuthn data, so seed only the two-factor secret and recovery codes through the Fortify action and measure passkeys from the fixture (D21) |
| Long names | all | partly | covered by the fixtures (deterministic props); not seeded |
| Server-error and invalid forms, busy | all | no | not seedable; covered by fixtures and Vitest |

Names are fixed so reruns stay idempotent (the seeder is guarded by the demo transaction; confirm with the existing idempotency test). New rows are created after the cycles, as in slice 2, so cycles, orders, receptions and dashboard assertions stay as they are. `DemoSeederTest.php`: update counts, add one test per seeded state, extend the role path lists with each new C1-C4 URL (run the whole file; unverified).

## Risks (slice 3-7)

| Risk | Evidence | Mitigation |
| --- | --- | --- |
| Select theming leaks to old-theme pages | `FormSelect.vue` has 10 consumers; `ui/select/SelectContent.vue` is portalled | class only when the variant is provided; control `Manage/Accounts/Index` before/after; keyboard case |
| Auth pages cannot reach `landing` without shell plumbing | `AuthLayout.vue:12`, `AuthSimpleLayout.vue:17` | C4 first task adds a variant prop and class carrier; `settings/*` use `AppLayout` and are unaffected |
| Probe pages are admitted and break earlier evidence | probes at `catalogue-landing-visual.ts`, `producer-landing-visual.ts` point to C1/C2 pages | D14: re-point first, in the admitting PR |
| Error page and `app.blade.php` are outside Vite/CSS tokens | `errors/minimal.blade.php` is standalone; first paint uses `--background` fallback | D16/D17; measure the rendered view; test with CSS unloaded for first paint |
| Global promotion changes pages that were not re-measured | `public/theme-colors.css` `:root` is the only source for old-theme pages | S29 before/after on one page per checkpoint plus the inventory; one reversible PR |
| `MovementHistory` and `NativeDialog` restyle reaches unmigrated pages | `Manage/Stock/Movements`, `Manage/Cycles/*` consumers | probes per checkpoint (table above); C2 consumes the last probe |
| 22 specless pages block definition of done | R26 | specs written first, inside the page task; no separate "coverage" task |
| Large C2 and C4 checkpoints are slow to approve | 9 pages + 5 overlays; 10 pages + 4 overlays | task slicing below; user approval is per checkpoint (R20), not per task |
| Fixture bootstrap duplicated a fifth time | `catalogue-landing-visual.ts` ~1650 lines | D13: one fixture per checkpoint now; extraction as a separate refactor task proposal, not in scope |
| Orphan list changes during the slice | OQ8 | D19: re-run `rg` at each checkpoint start and at C5 |
| Seeder additions change dashboard counts | `DemoSeederTest.php` (slice 2 note) | create after cycles; run the whole file |
| Behaviour regression on money/auth | R25 | presentation-only diff review; Pest suites unchanged and listed per task |
| Mail templates touched by the inventory | `views/mail/*.blade.php` | excluded (Correction 5); non-goal |

## Decisions the plan proposes (to confirm)

Numbering continues from slice 2 (D6-D11).

- **D12 — Select owner and shape.** Recommendation: the first C1 task that reaches a select adds `usePilotVariantClass` to
  `ui/select/SelectContent.vue` and the `.landing` select CSS (same pattern as `ui/dialog`); `FormSelect` unchanged. Alternative (a wrapper `LandingSelect`) rejected: a second component for one behaviour.
- **D13 — Fixtures.** Recommendation: one fixture per checkpoint (`rest-c1..c5-landing`), reuse the select baseline in `selects.visual.json` as the old-theme control; no bootstrap extraction now. Alternative: extract the shared bootstrap first as a prefactor task (a fifth copy is where D9 said to revisit).
- **D14 — Probe re-pointing.** Recommendation: the PR that admits a probe page re-points that probe in slice 1/2 fixtures and manifests to the next later page (table in *Architecture overview*), as in slice 2 D7. Alternative: a permanent synthetic control page (rejected, D4/D7).
- **D15 — Auth shell variant.** Recommendation: add `pilotVariant` to `AuthLayout.vue` and `AuthSimpleLayout.vue` (provide it, add the class to the `.material-auth` root and to portalled overlays via `usePilotVariantClass`), with `landing` CSS under `.landing`; no new layout. Alternative: a dedicated `AuthLandingLayout` (rejected: duplicates the shell).
- **D16 — Error page.** Recommendation: restyle `errors/minimal.blade.php` with inline CSS in landing palette literals, `prefers-color-scheme` for dark, no Vite assets; copy unchanged. Alternative: render the error as an Inertia page (rejected: new component, changes behaviour on failures).
- **D17 — Promotion shape.** Recommendation: one PR that (a) moves the tokens to `:root` and `theme-colors.css`, (b) removes both sets and the helper functions, (c) deletes the orphans; the `.landing` selectors and `.pilot-*` classes are kept as plain rules this slice (no renaming); fallback colours in `app.blade.php` move in the same PR. Alternative: orphans deleted in an earlier C5 task (acceptable, independent) and promotion split in two PRs; the revert story is then two reverts.
- **D18 — `Welcome` coverage.** Recommendation: C5 admits `Welcome` as already landing (no restyle), adds `pages/Welcome.spec.ts`, and measures the transition; `WelcomePage.vue` is edited only if the transition measurement fails (Deviation). Alternative: add `WelcomePage.vue` to Scope formally (a spec edit).
- **D19 — Inventory.** Recommendation: a script/spec listing `resources/js/pages/**` plus overlay components, run at each checkpoint start and fail on a page not in the checkpoint table nor in `landingPages`; mail excluded. Alternative: a manual `rg` list in each closure record (cheaper, drifts).
- **D20 — Seeder placement.** Recommendation: three seeder tasks, one per checkpoint with a gap (C2, C4, and C1 only if a read shows a missing state), each created after the cycles; no single big seeder task up front. Alternative: one seeder task in C1 (earlier demo for all checkpoints; but it ships states before their pages exist).
- **D21 — Passkey demo state.** Recommendation: seed two-factor only; passkey states are covered by the fixture and `PasskeyRegister.spec.ts`/`PasskeyVerify.spec.ts` (WebAuthn data is not realistic to seed). Alternative: seed a fake passkey record (rejected: wrong data in the demo).
- **D22 — Approval granularity.** Recommendation: closure task per checkpoint presents the per-page measurement as text and asks one approval for the whole checkpoint in light and dark (R20 text); a page the user rejects is reworked inside the checkpoint (OQ5).
- **D23 — `Member/Baskets/*` and `Manage/Roles/Index` admission.** Recommendation: add each name to both sets in its page task (Correction 6); no separate "bring to green first" step, since the intermediate look is the global look the page has today.

## Ordering and dependencies

Strict checkpoint order C1 → C2 → C3 → C4 → C5; each closure task needs every task of its checkpoint merged and the previous checkpoint's approval (R20).
Inside a checkpoint, the first task is the tracer (fixture, shared change, first admission); the other page tasks need only that task.
Seeder tasks are independent of UI tasks and are needed before the closure task of their checkpoint.

```
C1:  C1-tracer ──► C1-list, C1-baskets-form, C1-finance-stock, C1-receptions-list ──► C1-closure
     C1-seeder (if needed) ───────────────────────────────────────────────────────────┘
C2:  C2-tracer ──► C2-households, C2-producers-settings, C2-cycles, C2-stock-history ──► C2-closure
     C2-seeder ─────────────────────────────────────────────────────────────────────────┘
C3:  C3-wallet ──► C3-member-pages ──► C3-closure         (wallet first: multi-role, select reuse)
C4:  C4-tracer (auth shell + sign-in) ──► C4-auth-flows, C4-profile, C4-security ──► C4-closure
     C4-seeder ───────────────────────────────────────────────────────────────────────┘
C5:  C5-dashboard, C5-orphans (independent) ──► C5-promotion ──► C5-closure
     C5-errors-icons-paint, C5-welcome (independent) ──────────┘
```

Page tasks inside a checkpoint are independent of each other once the tracer merges (different files; each adds its fixture cases and spec). If two touch the same `.landing` block, the later one re-runs the earlier one's cases as its probe.
`C5-promotion` needs all pages admitted and approved (C1-C4 closed), the error page, paint and icons done, and the orphans removed or untouched.

## Proposed task slicing (names, allocation of R#/S# only; no task text, no Tnn IDs)

Names are proposals. IDs continue after T16 in `tasks.md` only after plan acceptance. Each task is one PR, tests first, within the 400-line review prompt
(`skills/agent-workflow/references/planning.md`: a prompt, not a quota; estimates are uncertain). Tasks marked * are the likeliest to exceed it and may split once measured.

| Name | Checkpoint | Requirements / scenarios | Dependency reason |
| --- | --- | --- | --- |
| `c1-reception-detail-select-total-dialog`* | C1 | R21, R22, R23, R24, R25, R26; S19 (reception), S20, S22 (total dialog), S26 | Tracer: fixture `rest-c1`, select theming (D12), `Receptions/Show` admission, `ReceivedTotalForm`. First dependency of the rest |
| `c1-receptions-list` | C1 | R21, R22, R26 (`Index` spec); S19 | Needs the C1 fixture |
| `c1-finance-baskets-list-detail` | C1 | R21, R22, R25, R26; S19 (baskets, amounts to the cent) | Needs the fixture; `Baskets/Index`, `Baskets/Show` |
| `c1-finance-new-basket-form` | C1 | R21, R22, R24 (select reuse), R26 (`Create` spec); S19, S20 | Needs the select from the tracer |
| `c1-finance-stock-and-history` | C1 | R21, R22, R23, R26 (`Movements` spec); S19, S26 | Needs the fixture; `MovementHistory` leakage probe |
| `c1-demo-seeder-states` (only if a read shows a gap) | C1 | R27; supports S19 | Independent |
| `c1-acceptance-and-visual-approval` | C1 | R20, R27, R30; S19, S20, S26, S27, S30 | All C1 tasks merged |
| `c2-roles-and-accounts`* | C2 | R21, R22, R24, R25, R26 (`Roles/Index` spec), R23; S21, S22 (holder removal), S26 | Tracer: fixture `rest-c2`, `Accounts/Index` (select) and `Roles/Index` with `RoleHolderRemove`; admits `Roles/Index` to both sets |
| `c2-households-and-settlement`* | C2 | R21, R22, R24, R25, R26 (two specs, `SettlementDetails`); S21, S22 | Needs the fixture; `Households/{Index,Show}` |
| `c2-producers-and-settings` | C2 | R21, R22, R24, R26 (two specs); S21 | Needs the fixture; `Producers/Index`, `Settings` |
| `c2-cycles-and-dialogs` | C2 | R21, R22, R24, R26 (`CycleCreateDialog` spec); S21, S22 | Needs the fixture; `Cycles/{Index,Show}`, create and manage dialogs |
| `c2-stock-movements-and-edit-dialog` | C2 | R21, R22, R23, R24, R26 (`StockEditDialog` spec); S21, S22 | Needs the fixture; last consumer of `MovementHistory` probe |
| `c2-demo-seeder-states` | C2 | R27; supports S21 | Independent: deactivated household/account, role with no holder, settled/unpaid |
| `c2-acceptance-and-visual-approval` | C2 | R20, R27, R30; S21, S22, S26, S27, S30 | All C2 tasks merged |
| `c3-wallet-statement-all-audiences` | C3 | R21, R22, R24, R25 (multi-role, cents); S23 | Tracer: fixture `rest-c3`; multi-role navigation |
| `c3-member-baskets-and-stock-list` | C3 | R21, R22, R26 (`Stock/Index` spec); S23 | Needs the fixture; admits `Member/Baskets/*` to both sets (D23) |
| `c3-acceptance-and-visual-approval` | C3 | R20, R27, R30; S23, S26, S27, S30 | All C3 tasks merged; seeder: handoff states "no change" if verified |
| `c4-auth-shell-and-sign-in-cards`* | C4 | R21, R22, R23, R26 (`Login`, `ForgotPassword`, `ResetPassword` specs); S24, S26 | Tracer: variant plumbing for `AuthLayout` (D15), fixture `rest-c4`, three pages |
| `c4-verification-confirmation-invitation` | C4 | R21, R22, R25, R26 (four specs); S24 | Needs the auth shell; `VerifyEmail`, `ConfirmPassword`, `TwoFactorChallenge`, `AcceptInvitation` |
| `c4-profile-appearance-delete-account` | C4 | R21, R22, R25, R26 (`Profile`, `Appearance` specs); S25 | Needs the fixture; `DeleteUser` dialog |
| `c4-security-two-factor-passkeys`* | C4 | R21, R22, R25, R26 (`Security` spec); S24 (two-factor), S25 | Needs the fixture; `TwoFactorSetupModal`, `ManagePasskeys`, `PasskeyRegister` |
| `c4-demo-seeder-two-factor` | C4 | R27; supports S25 | Independent (D21) |
| `c4-acceptance-and-visual-approval` | C4 | R20, R27, R30; S24, S25, S26, S27, S30 | All C4 tasks merged; S24 measured after hydration (OQ7) |
| `c5-dashboard-landing` | C5 | R21, R28, R22; S28 | Needs C4 approved; fixture `rest-c5` |
| `c5-error-page-first-paint-tab-icons` | C5 | R28; S28 | Independent of the dashboard; D16, D17, OQ4 |
| `c5-welcome-transition-coverage` | C5 | R26 (`Welcome` spec), R28; S24 (full), S28 | Independent; D18 |
| `c5-remove-orphan-layouts` | C5 | R29; S29 | Independent; re-verify with `rg` |
| `c5-global-theme-promotion` | C5 | R29, R30; S29 | All pages admitted and approved; error page, paint and icons done |
| `c5-closure-and-feature-acceptance` | C5 | R20, R27, R30; S27-S30 | All C5 tasks merged; user approval allows the feature acceptance review |

Allocation check: R20 and R30 in each closure task; R21-R22 in each page task; R23 in the tracer and shared-component tasks; R24 in `c1-reception-detail-select-total-dialog`
(owner) and the C2/C3 select pages (reuse); R25 per page task; R26 in the page task that adds the spec; R27 in the seeder and closure tasks;
R28 in the three C5 tasks; R29 in `c5-remove-orphan-layouts` and `c5-global-theme-promotion`. S19 in C1, S20 in the C1 tracer and `c1-finance-new-basket-form`, S21-S22 in C2, S23 in C3, S24-S25 in C4,
S26 in the tracer and closure of each checkpoint, S27 in each closure task, S28-S29 in C5, S30 in every closure task. Nothing is deferred.

## Limits (slice 3-7)

Covers 005 slice 3-7, R20-R30/S19-S30, only; slices 1 and 2 keep their plan parts. Mail templates, new dashboard capability (004), new pages, routes and copy stay outside. Estimates, line counts and measured
values marked *unverified* were not run. Plan acceptance, the Change entry for Corrections 1-7, the task breakdown, publication and implementation are separate user decisions; the spec acceptance gate stands first.
