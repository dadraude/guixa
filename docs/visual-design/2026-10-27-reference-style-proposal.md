# Reference style for Guixa — proposal

**Status:** Approved for a staged rollout, 2026-10-27. T47 was merged. After reviewing it, the user requested the [all-page continuation](2026-10-27-reference-style-rollout.md), including the public Welcome page and a simplified breadcrumb/header hierarchy. This is a presentation change to the accepted visual identity in `specs/001-weekly-orders/plan.md`. It does not change the weekly-orders requirements or scenarios. T45 is merged; separate T44/T46 layout work must be reconciled if it lands before the continuation merges.

## Intent

Use the four screenshots supplied on 2026-10-27 as references for the **shape and hierarchy** of the signed-in application: a clear navigation rail, a warm canvas, large page titles, calm white panels, compact data summaries, and readable status treatments. Keep Guixa's identity and actual workflows. The screenshots' Common Ground name, record counts, sample orders, golden button, and page names are example content, not Guixa requirements.

Guixa already has the needed semantic palette in `public/theme-colors.css`. Use that palette rather than importing the reference's yellow and green values. Keep colors behind semantic tokens, with light and dark pairs.

The [concept preview](reference-style-preview.html) shows the proposed shell, panels, form and dialog in [light](reference-light.png) and [dark](reference-dark.png) modes. Its sample counts and controls are illustrative. It is a review artifact, not running application UI. The preview was measured at 375 and 1280 px in both modes without document-level horizontal overflow; it does not replace the app's visual test matrix.

| Reference role         | Guixa token and current value                               | Intended use                                                                |
| ---------------------- | ----------------------------------------------------------- | --------------------------------------------------------------------------- |
| Warm page canvas       | `--palette-canvas: #f8f7f4`                                 | Signed-in page background in light mode                                     |
| White panel            | `--palette-surface: #ffffff`                                | Cards, lists, tables, forms, menus                                          |
| Strong text            | `--palette-ink: #202b26`                                    | Headings and primary data                                                   |
| Brand green            | `--palette-brand: #246b57`                                  | Primary action, focus, and proposed desktop navigation surface              |
| Soft green             | `--palette-soft: #e8efeb`                                   | Selected state and small icon wells, when contrast is verified              |
| Supporting text/border | `--palette-muted-ink: #58665e`; `--palette-border: #d6ded8` | Metadata and quiet dividers                                                 |
| Dark mode              | Existing `--palette-dark-*` tokens                          | Neutral stepped surfaces and mint brand; retain the accepted dark treatment |

Current token pairs already give useful starting contrast: white on brand green **6.33:1**, ink on canvas **13.66:1**, muted ink on white **6.04:1**, and dark brand on dark sidebar **9.74:1**. These calculations cover only those exact pairs; every final interactive and status state still needs rendered checks. The reference's yellow is omitted. Destructive, warning, and success meanings continue to use their own semantic colors and visible text.

## Translation to Guixa screens

| Screenshot pattern                                 | Guixa equivalent                                                                | Adaptation                                                                                                                                          |
| -------------------------------------------------- | ------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| Overview with four metrics and two activity panels | `Dashboard.vue`                                                                 | Show only existing, meaningful role-specific data and actions; do not create placeholder counts or implied live activity.                           |
| Members directory                                  | `Manage/Households/Index.vue` and `Manage/Accounts/Index.vue`                   | Preserve the distinction between households and login accounts. Use a calm list or responsive table without hiding creation and invitation actions. |
| Orders directory                                   | Member `Orders/Index.vue` and manager `Manage/Cycles/Index.vue`                 | Preserve cycle and order states, deadlines, totals, and the current role-specific actions.                                                          |
| Providers directory                                | `Manage/Producers/Index.vue`                                                    | Keep cutoff, account invitation, activation, and producer information visible.                                                                      |
| Shared navigation                                  | `AppSidebarHeader.vue`, `AppSidebar.vue`, `NavMain.vue`, `AppSidebarLayout.vue` | Use the current role-aware destinations, appearance control, account menu, mobile drawer, and skip link.                                            |

The same visual language should ultimately cover stock, return items, reception, pickups, wallet, producer pages, account settings, and authentication. The guest `Welcome` page is outside this proposal because it is a separate public surface.

## Presentation rules

1. **Navigation:** At desktop widths, propose a persistent brand-green left rail with Guixa identity at the top, clear selected and expanded states, and grouped destinations. Keep the current compact/mobile drawer pattern and role-aware navigation. The appearance and account controls must remain findable at every width. Do not add a new destination or rename a domain concept from the screenshots.
2. **Hierarchy:** Use one clear page title and one useful description where the page already supports it. On the pilot routes, put the breadcrumb in the desktop top bar instead of repeating the current page title there; on narrow screens, place it immediately below the compact header. Keep the page H1 as the content title. Small category labels may be used only with translated, meaningful wording. Reuse the current Nunito/body and heading tokens unless an accepted amendment changes typography. Keep amounts and quantities tabular.
3. **Surfaces:** Use the existing page, panel, form, list-row, and action-row patterns from the accepted layout rules. Panels use a visible border or subtle shadow, consistent corner radius, and shared spacing. Forms, list actions, empty states, and dialogs remain distinct at a glance.
4. **Data:** Desktop lists may use aligned columns where they improve scanning. On phones, reflow into labeled rows or cards with all essential values and actions visible; no page-level horizontal scroll. A status badge always includes text. Keep totals and dates readable at 200% text.
5. **Interaction:** Preserve current routes, permissions, Inertia props, validation, save/submit behavior, and domain states. Use the current shadcn-vue primitives and shared components before adding any new component. Every target remains at least 44 × 44 px, with keyboard focus and reduced-motion support.
6. **Copy:** New visible text goes through `lang/`; Catalan remains the default. The reference's English copy is not copied into the app.

## Reuse plan

| Pattern                 | Existing source                                                                                                      | Intended change                                                                           |
| ----------------------- | -------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------- |
| Shell and navigation    | `resources/js/layouts/app/AppSidebarLayout.vue`, `resources/js/components/{AppSidebarHeader,AppSidebar,NavMain}.vue` | Extend existing structure and role-aware menu, then style through shared tokens.          |
| Palette and spacing     | `public/theme-colors.css`, `resources/css/app.css`                                                                   | Adjust shared semantic pairs and presentation classes; avoid page-local color values.     |
| Page heading and panels | `resources/js/components/Heading.vue`, `.pilot-layout-page`, `.pilot-layout-panel`                                   | Extend existing variants/patterns where needed.                                           |
| Actions, badges, forms  | `resources/js/components/ui/{button,badge,input,card}/`, `resources/js/components/StatusIndicator.vue`               | Reuse and extend compatible variants; no parallel button, badge, or input implementation. |
| Overlay                 | `resources/js/components/manager/NativeDialog.vue` and existing dialog/sheet primitives                              | Verify light/dark and mobile behavior with the revised surfaces.                          |

### T47 pilot inventory and coverage

The first production pilot is limited to manager `Manage/Cycles/Index` and `Manage/Producers/Index`, plus their opted-in shared shell. `Dashboard`, member ordering, settings and other manager routes remain in the later slices because their layout-rule work is separate or their patterns can reuse the reviewed pilot. The pilot does not alter any permission, route, form submission or data shape.

| View                    | Shared patterns                                                                           | States declared in `reference-pilot.visual.json`                       |
| ----------------------- | ----------------------------------------------------------------------------------------- | ---------------------------------------------------------------------- |
| Manager Cycles index    | Sidebar and mobile drawer, page heading, list, status, action row, native dialog          | Populated, empty, long content, open create dialog, open mobile drawer |
| Manager Producers index | Sidebar and mobile drawer, page heading, labeled form, list, status and invitation action | Populated, empty, long content                                         |

The 19 selected cases cover each view at 375 and 1280 px in light/dark at 100% text, plus both themes at 1024 px/200% where the sidebar and form/list reflow. The empty states use the narrowest width; the long cycle name probes desktop enlarged-text layout, while the long producer name probes phone enlarged-text wrapping. The dialog is probed at 375 px/200% in dark mode, its tightest contrast and space combination. The mobile drawer is probed at 375 px/200% in both themes. Native date and time inputs receive an explicit `:focus-within` outline; Chrome's native picker controls are verified separately because the shared keyboard probe does not report their `:focus-visible` state. The existing forms' invalid and processing outcomes do not change in T47; their focused Vue tests and the applicable existing manager visual checks remain regression evidence. If the new styling exposes a defect in either outcome, add its state to the pilot manifest before review. No separate loading state exists on these indexes.

## Delivery sequence

These slices are recorded as local tasks T47–T51 in the accepted plan. They are not published Linear tickets or amendments to T43–T46.

1. **Visual pilot (T47):** Apply the opted-in shared shell and panel style to manager Cycles and Producers. Render their list, form, dialog and drawer states in the declared matrix. Review the sidebar direction and Guixa's green primary action before wider rollout.
2. **Manager directories:** Apply the shared heading, panel, list/table, and status patterns to households, accounts, producers, cycles, stock, and return items. Keep every current form and action. Verify empty, populated, long-name, validation, and disabled states.
3. **Manager workflows:** Carry the system through cycle details, reception, pickups, stock movements, settings, and their dialogs. Check status meaning and below-the-fold actions.
4. **Member and producer workflows:** Apply the same system to orders, wallet, products, and cutoff screens, preserving the phone-first ordering and quantity-entry behavior.
5. **Account and authentication:** Apply the visual system to account settings and authentication after the signed-in patterns are stable. Keep the existing appearance preference and passkey/2FA flows.

Each slice is reviewable and mergeable on its own. Before publishing a Linear issue or starting T48–T51, reconcile the slice with any in-flight UI branches. A task owns its exact route inventory and a `component_plan` naming reused and extended components.

## Acceptance and evidence

- On every affected route, verify populated state at **375 and 1280 px**, light and dark, 100% text. Check distinct layout/form/table patterns at **1024 px and 200% text**. Add each applicable empty, error, disabled, long-content, domain, and overlay state at its riskiest width/theme.
- Use the shared visual manifest and runner described in `skills/agent-workflow/references/visual.md`; record selected cases before code. Check structure and measured geometry first, then inspect required representative captures. Include focus, touch target, contrast, reflow, and reduced-motion checks.
- Run existing tests for changed contracts and focused frontend checks. A visual-only change uses the constitution's test-first exception for parts without executable behavior, with structural and rendered verification recorded.
- Do not claim the screenshots' exact geometry or colors as acceptance. The outcome is Guixa's workflows made clearer in that visual language, with its own palette.
