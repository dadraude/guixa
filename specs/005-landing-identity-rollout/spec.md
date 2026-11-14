# 005 — Landing identity rollout: Catalogue role complete

**Status: Accepted (2026-11-13, merge of PR #416); no open questions.** Specification of delivery slice 1 only. The
[discovery](discovery.md) keeps slices 2-7 as a roadmap; they get no requirements here. Slice selection
and boundary are recorded in [history](history/) (Q&A 2026-11-13: slice 1 selected; T85 visual approval
granted; Manage stock and return-item pages moved into slice 1). Accepting this spec authorizes no plan,
tasks, tickets or implementation.

A person with the Catalogue role works through every page of its area in the same blue/cream/Nunito
identity as the public landing and the already migrated stock and orders screens, in light and dark
appearance. Nothing a person can do changes: this slice is a change of presentation only.

## Language

Terms from [001](../001-weekly-orders/spec.md#glossary) and [003](../003-role-management/spec.md#language)
keep their meaning. 001 items are cited as "001 R28a" (001 requirements live in its area files, for example R28a in `stock-wallet.md`); 003 items as "003 R4". The terms below describe this
feature only and create no domain entity.

| Term | Meaning |
| --- | --- |
| Landing identity | The visual language of the public landing (`resources/js/components/WelcomePage.vue`): blue and cream paper in light appearance, the dark counterpart in dark appearance, Nunito type, warm borders and focus. Values are fixed by the accepted 001 plan, section "Llenguatge visual de la portada a tota l'aplicació"; the landing is the source of truth if the two differ. |
| Landing page set | The pages that show the landing identity, one entry per page by exact name. Today: the public landing, `Manage/Stock/Index`, `Orders/Index`, `Orders/Edit`. Membership is explicit and per page; no role, prefix or area is migrated by implication. The older green pilot list does not mean migrated. |
| Catalogue pages | The pages listed under Scope. Every page a Catalogue account reaches in its area. |
| Shared invariants | The measurable conditions of R5 to R9 that every migrated page meets. |
| Visual approval | The explicit user decision that a slice's look is accepted (R12). Measurements and passing tests are evidence for it and never replace it. |

## Roles

- **Catalogue** (003 R3-R5, R14): the audience of this slice. Reaches the pages below through its *Catàleg* area.
- **Manager** (003 "Roles"): holds every Catalogue power and reaches the same pages. Theming is per page,
  not per role, so Manager sees the landing identity on these pages too. Manager's other pages stay as they
  are until their slice.
- **Producer's account holding Catalogue** (003 R3, S5): sees these pages through the Catalogue role only;
  its Producer area is not in this slice.
- **Everyone else** (members, producers, Finance, Receiver): cannot reach these pages (003 R14) and sees no
  change on any page.

## Scope

Catalogue pages, from routes under `role:manager,catalogue` (`routes/web.php`, prefix `catalogue`,
controllers in `app/Http/Controllers/Catalogue`). Re-verified against `main` at 876672a4.

| Page | Reached for |
| --- | --- |
| `Catalogue/Producers/Index` | producer list (003 R5) |
| `Catalogue/Producers/Products` | a producer's weekly and stock products (003 R3, R4) |
| `Catalogue/Producers/Availability` | marking a product unavailable or available in a cycle (003 R3, 001 R3c) |
| `Catalogue/Producers/WeeklyProduct/Create`, `Edit` | weekly product forms (001 R1, R1c) |
| `Catalogue/Stock/Movements` | read-only quantity and movement history (003 R4, 001 R28a) |
| `Manage/Stock/Create`, `Edit` | stock product forms, served to Catalogue by Catalogue's `StockProductController` (001 R25, R25a) |
| `Manage/ReturnItems/Index`, `Create`, `Edit` | return-item catalogue (003 R5, 001 R20a; 003 R14 lists only producers, weekly and stock products) |

Out of this list, though a Catalogue account passes through them: the shell (sidebar, header, breadcrumbs,
account and appearance menus, mobile sheet) and overlays opened from these pages. They are in scope
wherever they appear on a Catalogue page, so that a page is never half-themed. `Manage/Stock/Index` and
`Manage/Stock/Movements` are not Catalogue pages (a Catalogue account is refused `/manage/stock`, 003 R4).

## Requirements

### Identity

- **R1 — Every Catalogue page in the landing page set.** Each page in Scope shows the landing identity
  (surfaces, ink, primary action, links, focus, borders, type) in light and dark appearance, including the
  shell and any overlay it opens: native dialog and toast. Dropdown menus, sheets and tooltips that the shell
  shows on these pages inherit the same look. No page in Scope keeps the previous green look.
- **R2 — Page-scoped, no leakage.** The identity appears only on migrated pages. A page not yet migrated
  keeps its current look, including when reached from a Catalogue page and when returning; "current look" is checked by the page's computed colours and fonts matching its pre-slice values, not by eye; a migrated page
  keeps the identity after Inertia navigation, with the sidebar, account menu or mobile sheet open. The
  public landing and the earlier migrated pages look as before.
- **R3 — One identity within the area.** A Catalogue account moving between the pages in Scope sees no
  change of palette or type. Where a Catalogue page links to a page outside Scope (for example a Manager-only
  page) the change of look is expected until that page's slice.
- **R4 — Operational hierarchy, not landing composition.** Titles, subtitles, figures and labels stay
  compact and scannable. The landing's hero composition, basket illustration and texture do not appear on
  tables, forms, errors, destructive confirmations or any operating surface. Empty states may use only what
  the accepted 001 plan allows for them.

### Measurable conditions (every page in Scope)

- **R5 — Contrast.** At least 4.5:1 for normal text and 3:1 for large text, essential borders (fields,
  controls, dividers that carry meaning) and focus indicators, in both appearances. A field's
  boundary is measured at 3:1 or more; the decorative landing border (1.28:1 light, 2.31:1 dark) alone does not
  qualify. Primary action text on primary action background meets the ratio in each appearance.
- **R6 — Touch and keyboard.** Interactive targets are at least 44 x 44 px. Focus is visible on every
  control. Tab order follows reading order. Escape closes an overlay and focus returns to the control that
  opened it. Nothing depends on hover (constitution, UI).
- **R7 — Layout.** No clipping and no horizontal page scroll at 375 px and 1280 px, in light and dark; at
  1024 px with 200% text for each composition on these pages: list or table (`Producers/Index`, `Producers/Products`,
  `ReturnItems/Index`), form (weekly product, stock and return-item create and edit), movement history,
  availability and the dialog of `ProductAdministrationActions`. Quantities, amounts and dates keep their alignment and tabular figures.
  The same information is available at every width.
- **R8 — States.** Each page is legible and unclipped in its full, empty, long-content, invalid and
  disabled states, as the page has them: long product, producer or return-item names; no producers, products
  or movements; a validation error on every form field that can show one; a busy or disabled action; an
  inactive product, producer or return item marked as inactive.
- **R9 — Semantics kept.** Danger, success and warning keep their meaning and colour role; ochre (the landing accent) is not
  used for warning. State is conveyed by text or icon as well as colour (active and inactive, available and
  unavailable, errors).

### Behaviour preserved

- **R10 — Nothing but presentation changes.** Routes, URLs, Inertia props, validation and error messages,
  translated copy, domain data, calculations and authorization are unchanged. Catalogue and Manager have the
  same pages, actions and refusals as before: 003 R3-R5 and R14 (area shows only what the roles allow; a
  direct link to anything else is refused), 003 R16 (translatable copy, Catalan default, 375 px, light and
  dark, 200% text, keyboard, 44 px), 001 R28a (read-only quantities and movement history for Catalogue; no
  entry, adjustment or count). Strictly visual corrections that the work uncovers (for example an overflowing
  label) are allowed and recorded as a Deviation.
- **R11 — Existing tests stay green.** The existing PHP and Vitest tests of this family pass unchanged in
  meaning, including the Catalogue area feature tests, the role permission matrix and the page specs of
  `Catalogue/Producers/{Index,Products,Availability}` and `Manage/Stock/Edit`. A test may be edited only where
  it asserts a changed look, never to relax a behavioural assertion. Pages that have no page spec today
  (`WeeklyProduct/Create`, `WeeklyProduct/Edit`, `Catalogue/Stock/Movements`, `Manage/Stock/Create`,
  `Manage/ReturnItems/{Index,Create,Edit}`) get regression coverage for what S3-S6 observe before they are
  declared done.

### Evidence and approval

- **R12 — Evidence by measurement, approval by the user.** For each page in Scope the slice's acceptance
  record shows R5 to R9 as measured text (computed style, bounding boxes, contrast ratios, overflow, focus
  and tab-order checks), not as screenshots. Images are produced only for an unexplained failure, a new
  design direction or on request, and then once, cropped or scaled down. The slice is complete only when the
  user records visual approval of the Catalogue pages in light and dark; until then the next slice cannot
  start.
- **R13 — Demo data shows the states.** The demo seeder shows the states R8 needs on Catalogue pages
  (a deactivated producer, an inactive product, an unavailable product in a cycle, an inactive return item).
  If it already does, the handoff says so; if not, it is extended with its test (project rule "Demo seeder").

## Acceptance scenarios

Scenarios use the pages in Scope. "Measured" means by the evidence kinds in R12.

**S1 — Catalogue works one area in one identity (R1, R3, R4).** Given a Catalogue account, when it opens the
producer list, a producer's products, that producer's availability, a weekly product form, a stock product
form, a stock movement history and the return-item list in turn, in light and in dark, then every page shows
the landing identity in shell and content, the primary action is the landing blue (light) or its dark
counterpart, and no page shows the previous green or a hero, basket illustration or texture.

**S2 — Manager sees the same pages the same way (R1, R10).** Given an account holding Manager, when it opens
the pages in Scope, then they show the landing identity and offer the same actions as before; its other
pages (accounts, cycles, settings) still show their current look.

**S3 — Forms: fields, errors, disabled (R5, R6, R8, R10).** Given the weekly product create form, the weekly
product edit form, the stock create and edit forms and the return-item create and edit forms, when each is
opened empty, submitted invalid and submitted with a server error, then every field has a persistent label,
a border measured at 3:1 or more, an error message shown directly after its field and visible without clipping,
the busy or disabled state of the submit control is distinguishable by more than colour, and the submitted
values, messages and redirects are the same as before this slice. Opening a form by keyboard only, every
control is reachable in reading order and focus is visible.

**S4 — Lists and tables with long and empty content (R7, R8, R9).** Given the producer list, a producer's
product list and the return-item list, each once with no rows, once with several, once with a very long name,
and once with an inactive or deactivated row, when opened at 375 and 1280 px (1024 px at 200% text) in light
and dark, then there is no horizontal page scroll or clipped text, quantities and prices stay aligned,
inactive and deactivated rows are marked by text, and the empty state explains itself in both appearances.

**S5 — Availability, previously unthemed (R1, R2, R8, R9).** Given producer P's order is not sent and a
product is available in cycle C, when a Catalogue account opens P's availability page and marks the product
unavailable, then the page shows the landing identity before and after, the available and unavailable states
are distinguishable by text as well as colour, the request and result are the same as before, and the
previous page and the next page keep their own look (R2).

**S6 — Read-only movement history (R1, R10).** Given a stock product with movements, when a Catalogue
account opens its movement history, then it reads the same entries as before in the landing identity, with
no entry, adjustment or count action (001 R28a), and a direct link to a quantity-changing action is still
refused.

**S7 — Overlays keep behaviour (R1, R6).** Given a Catalogue page that opens a dialog (today the one in `ProductAdministrationActions`, used by `Products`, `WeeklyProduct/Edit` and
`Manage/Stock/Edit`; the plan verifies any others) or shows a toast after saving, when the dialog is opened with the
keyboard, then its surface, text, buttons and focus ring use the landing identity, Escape closes it with no
request sent, and focus returns to the control that opened it. A toast shown after an action is readable in
both appearances.

**S8 — No leakage across navigation (R2).** Given a Catalogue account with the mobile menu or account menu
open, when it navigates from a migrated Catalogue page to a page outside the landing page set and back,
then the outside page keeps its current look, the Catalogue page recovers the landing identity, and the
appearance preference (light, dark, system) is preserved throughout.

**S9 — Access unchanged (R10).** Given a Receiver-only account and a producer account without Catalogue,
when each opens any page in Scope by URL, then access is refused as before. Given a Catalogue account, when
it opens `/manage/stock` or a cycle's settings by URL, then it is refused as before (003 R4 and R14; 003 S11 covers the Receiver-only account).

**S10 — Existing behaviour and tests (R10, R11).** Given the existing feature and page tests of the area,
when the slice is complete, then they pass. Given the pages that had no page spec, when the slice is
complete, then each has at least one test that renders it with representative props and checks the title,
one primary action and its error display.

**S11 — Contrast, targets and 200% text (R5, R6, R7).** Given each page in Scope, when measured at 375 and
1280 px in light and dark and at 1024 px with 200% text per composition, then all text, border and focus
pairs meet R5, all interactive targets are 44 x 44 px or larger, and no page has horizontal overflow.

**S12 — Visual approval gate (R12).** Given the measurement record for all pages in Scope, when the user has
not recorded visual approval, then the slice is not complete and no later slice is specified or built. When
the user approves, the approval is logged in history with the evidence it covered.

## Non-goals

- Any page outside Scope: producer, finance, reception, other Manager, member, access and settings pages,
  dashboard, `Manage/Stock/Index` and `Manage/Stock/Movements`, `Manage/Roles/Index`.
- Routes, roles, permissions, props, validation, copy, data, calculations (R10). No new page, component,
  field or action where an existing pattern serves.
- A select control: no page in Scope uses one (verified 2026-11-13). The first slice that does owns theming
  `ui/select`.
- Promoting landing values to the global theme, removing the older green opt-in, deleting orphan layouts,
  error pages, mail templates, first-paint background and favicon: slice 7 and the 001 plan inventory.
- Mail templates stay on the Laravel defaults (decided 2026-11-13).
- New copy or translations beyond what a visual correction needs.

## Traceability

| Obligation kept | Source | Here |
| --- | --- | --- |
| Catalogue weekly products, availability while unsent | 003 R3, 001 R1, R1c, R3c | R10, S4, S5 |
| Catalogue stock administration, read-only history | 003 R4, 001 R25, R25a, R28a | R10, S3, S6 |
| Return-item catalogue; producer list without producer administration | 003 R5, 001 R20a | R10, S3, S4 |
| Area shows only what roles allow; refused links | 003 R14 | R10, S9 |
| Guixa visual system, 375 px, light/dark, 200% text, keyboard, 44 px | 003 R16, constitution UI | R5-R8 |
| Landing visual direction, invariants, containment | 001 plan "Llenguatge visual de la portada a tota l'aplicació", T83-T85 | Language, R1-R9, R12 |
| Slice boundary and selection | [discovery](discovery.md), Q&A 2026-11-13 | Scope |
| Visual approval before moving on | discovery decision 3; T84/T85 precedent | R12, S12 |

## Open questions

None open. Answered 2026-11-13 (see history):

- **OQ1 — Mixed look inside a task.** Acceptable until each page's own slice; R3 stands.
- **OQ2 — Who approves and how is it recorded.** One approval in one sitting covers Catalogue and Manager use
  of these pages, in light and dark; it is logged as a Q&A with the evidence it covered (R12, S12).
