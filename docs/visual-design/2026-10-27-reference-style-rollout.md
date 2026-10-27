# Reference style across Guixa

**Status:** In progress, 2026-10-27. After viewing the merged T47 pilot, the user asked to remove the repeated page title, put breadcrumbs at the top, and apply the same visual language to every page. This extends the original pilot to all 37 route views, including Welcome. The change remains presentational; routes, permissions, props, validation, and domain behavior stay as they are.

## Route inventory

The route component inventory excludes dialog and detail components that render within these routes; those inherit their parent page's styling and remain covered by their parent route or the T47 overlay cases.

| Area                 | Route components                                                                                                                                                                                                                                                                                                         |
| -------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Dashboard            | `Dashboard.vue`                                                                                                                                                                                                                                                                                                          |
| Manager (17)         | `Manage/Accounts/Index.vue`; `Manage/Cycles/{Index,Show}.vue`; `Manage/Households/{Index,Show}.vue`; `Manage/Pickups/{Index,Show}.vue`; `Manage/Producers/Index.vue`; `Manage/Reception/Show.vue`; `Manage/ReturnItems/{Create,Edit,Index}.vue`; `Manage/Settings.vue`; `Manage/Stock/{Create,Edit,Index,Movements}.vue` |
| Member (3)           | `Orders/{Index,Edit}.vue`; `Wallet/Statement.vue`                                                                                                                                                                                                                                                                        |
| Producer (5)         | `Producer/Cutoff.vue`; `Producer/CycleSummary.vue`; `Producer/Products/{Create,Edit,Index}.vue`                                                                                                                                                                                                                          |
| Account settings (3) | `settings/{Appearance,Profile,Security}.vue`                                                                                                                                                                                                                                                                             |
| Authentication (7)   | `auth/{AcceptInvitation,ConfirmPassword,ForgotPassword,Login,ResetPassword,TwoFactorChallenge,VerifyEmail}.vue`                                                                                                                                                                                                          |
| Public               | `Welcome.vue`                                                                                                                                                                                                                                                                                                            |

## Component plan

- Reuse `AppLayout.vue`, `AppSidebarLayout.vue`, `AppSidebarHeader.vue`, `AppSidebar.vue`, `Breadcrumbs.vue`, and `NavMain.vue`. The signed-in shell gets the reference navigation on every route. The desktop top bar contains the breadcrumb and no duplicate section title; the mobile breadcrumb sits directly below the compact bar. The route H1 remains the content title.
- Reuse the palette in `public/theme-colors.css` and shared `.pilot-*` layout/form/list/status classes in `resources/css/app.css`. Extend those styles to all signed-in surfaces and preserve each page's existing width constraint.
- Extend `AuthSimpleLayout.vue` and `WelcomePage.vue` with the same brand mark, surface border, radius, and heading rhythm. Their separate navigation and actions remain intact.
- Move `Manage/Pickups/{Index,Show}.vue` and `Manage/Reception/Show.vue` onto the established manager page container because they still used the older bare `flex` layout. Other route markup reuses the existing shared styles.

## Verification plan

`resources/js/fixtures/reference-all-pages.visual.json` declares 164 cases: every route at 375 and 1280 px, light and dark, plus 1024 px/200% text in eight distinct layout patterns. The T47 manifest remains the focused 19-case overlay and long-content regression. Use the shared visual runner for geometry, focus, text, appearance and generated captures; measure breadcrumb placement, target size, contrast and reduced motion separately. Run focused frontend tests, type and format/lint checks, and a production build. No server-side behavior is changed.

The fixture supplies deterministic sample props to the real route components. Its sample records are review data, not application content. In-flight T44/T46 layout work must be reconciled if it lands before this rollout merges.
