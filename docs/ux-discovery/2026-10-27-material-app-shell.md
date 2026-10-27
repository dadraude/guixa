# Material 3 structure for Guixa

## Official guidance

- Material 3 describes a shared layout scaffold made of bar, rail, and pane regions. Its canonical layouts adapt those regions to the available width. [Canonical layouts](https://m3.material.io/foundations/layout/canonical-examples/overview)
- The app bar identifies the current page and carries a small number of essential controls. [App bars](https://m3.material.io/components/app-bars/)
- Material's adaptive navigation guidance pairs compact windows with bottom navigation when there are few destinations, but recommends a drawer when there are many. Medium windows can use a rail or drawer; expanded windows can use persistent navigation. [Responsive navigation](https://developer.android.com/develop/ui/views/layout/build-responsive-navigation)
- Material's navigation drawer example organizes destinations into sections separated by dividers. [Navigation drawer](https://developer.android.com/develop/ui/compose/components/drawer)
- Surface and navigation color roles should keep the same meaning across widths and light/dark themes. [Color roles](https://m3.material.io/styles/color/the-color-system)
- Headline, title, body, and label roles give pages a consistent text hierarchy. [Typography](https://m3.material.io/styles/typography/applying-type)
- Material's filled cards use a surface tone to separate a coherent content block from its background; elevation can add emphasis without an enclosing stroke. [Cards](https://developer.android.com/develop/ui/compose/components/card)

## Guixa decisions

Guixa can expose more than five destinations across member, manager, and producer roles. The application shell will therefore keep a modal navigation drawer on narrow windows and a persistent leading drawer on wide windows. This is a product-specific application of Material's guidance, not a fixed Material component prescription.

Every authenticated route will share the same app bar, navigation location, content region, responsive gutters, and active-section indicator. Page content may remain a form, list, detail view, or supporting pane. The current route determines the section label in the app bar; detail and edit routes keep their parent destination selected.

The drawer groups its destinations under Overview, Orders and wallet, Cooperative, Catalog and stock, Administration, and Production. Each group contains direct links to its subsections, and groups appear only for permitted roles. A user with several roles sees all relevant groups once. The group headings and dividers disappear when the desktop drawer is reduced to icons, while the destination links remain available.

Authentication screens will use one centered surface and the same typography and spacing roles. The public welcome screen keeps its separate landing composition. Guixa's identity and workflows remain in place while the application palette follows the new admin direction.

## Admin layout and surface direction

The user supplied [Flowbite's settings page](https://flowbite-admin-dashboard.vercel.app/settings/) and a list-page reference for the top toolbar, navigation below it, card layout, filled fields, table hierarchy, and labeled status dots. Guixa applies those layout ideas to its own routes and actions. The app does not add a search field without an application-wide search feature.

The toolbar spans the window. The responsive navigation sits below it, and breadcrumbs sit at the start of the content area. Settings use two columns when space allows, with account navigation to the left and form panels to the right. Light and dark palettes use cool background, card, and inset-surface tones. Static cards, list containers, badges, and secondary buttons use filled surfaces without enclosing borders; subtle dividers distinguish rows. Filled fields keep a bottom indicator, and keyboard focus remains visible. Status indicators pair a colored dot with text so meaning never depends on color alone.

## Component map

- `useAppNavigation`: derives permitted destinations and the active section from Inertia page props and URL. It owns navigation data, not rendering.
- `AppSidebar`: renders the shared navigation drawer from those destinations.
- `AppSidebarHeader`: renders the app bar with the section label, navigation trigger, and account menu.
- `AppSidebarLayout`: assembles the bar, drawer, and main content region once for all authenticated pages.
- `AuthSimpleLayout`: presents a common authentication frame for all sign-in and recovery pages.

## Verification

Check representative routes from every role at 375, 1024, and 1280 px in light and dark themes. Read dimensions and computed styles, confirm active navigation and page labels after navigation, test the drawer by keyboard, and confirm no horizontal clipping or undersized controls. Do not capture screenshots.

The local preview was measured at those widths across member, manager, producer, and account settings routes. The app bar remains 64 px tall; content gutters are 16 px on narrow screens and 24 px on wider screens. The mobile drawer opens at 288 px normally and is capped at 311 px with 200% root text in a 375 px viewport. At 1024 px with 200% root text, the persistent drawer is about 306 px and the content region about 717 px. The measured routes had no horizontal document overflow. The first keyboard Tab exposes the skip link with a 48 px tall focus target. Light and dark surfaces both follow the current appearance setting. No screenshots were taken.

The sectioned drawer was also checked with all three roles active: six translated headings and 12 distinct destinations appear on both desktop and mobile. In the collapsed desktop drawer, the headings are hidden and all 12 destination icons remain available. Enter opens the mobile drawer from its trigger.

The sidebar separators now fit inside their horizontal margins, and the starter-kit repository and documentation footer links have been removed. At 375, 1024, and 1280 px, with 100% and 200% text, the sidebar content's scroll width equals its client width. The scrollbar is hidden, but the content still scrolls vertically to the last destination; the page itself has no horizontal overflow.

For the filled-surface revision, the household member section, its nested list, and its secondary button all measured 0 px enclosing borders in light and dark appearance; each has a distinct surface tone. At 1280 px, profile settings measured a 251 px navigation card beside a 670 px form panel. At 375 px, they stacked at 343 px wide without overflow. The stock table measured 0 px outer border, a distinct header tone, and labeled active/inactive status dots. The app bar spans the viewport and the desktop drawer begins immediately below it. These checks read DOM geometry and computed styles without capturing screenshots.
