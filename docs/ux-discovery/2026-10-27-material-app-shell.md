# Material 3 structure for Guixa

## Official guidance

- Material 3 describes a shared layout scaffold made of bar, rail, and pane regions. Its canonical layouts adapt those regions to the available width. [Canonical layouts](https://m3.material.io/foundations/layout/canonical-examples/overview)
- The app bar identifies the current page and carries a small number of essential controls. [App bars](https://m3.material.io/components/app-bars/)
- Material's adaptive navigation guidance pairs compact windows with bottom navigation when there are few destinations, but recommends a drawer when there are many. Medium windows can use a rail or drawer; expanded windows can use persistent navigation. [Responsive navigation](https://developer.android.com/develop/ui/views/layout/build-responsive-navigation)
- Surface and navigation color roles should keep the same meaning across widths and light/dark themes. [Color roles](https://m3.material.io/styles/color/the-color-system)
- Headline, title, body, and label roles give pages a consistent text hierarchy. [Typography](https://m3.material.io/styles/typography/applying-type)

## Guixa decisions

Guixa can expose more than five destinations across member, manager, and producer roles. The application shell will therefore keep a modal navigation drawer on narrow windows and a persistent leading drawer on wide windows. This is a product-specific application of Material's guidance, not a fixed Material component prescription.

Every authenticated route will share the same app bar, navigation location, content region, responsive gutters, and active-section indicator. Page content may remain a form, list, detail view, or supporting pane. The current route determines the section label in the app bar; detail and edit routes keep their parent destination selected.

Authentication screens will use one centered surface and the same typography and spacing roles. The public welcome screen can use a separate landing composition while sharing those tokens. Existing Guixa colors and workflows remain the brand layer.

## Component map

- `useAppNavigation`: derives permitted destinations and the active section from Inertia page props and URL. It owns navigation data, not rendering.
- `AppSidebar`: renders the shared navigation drawer from those destinations.
- `AppSidebarHeader`: renders the app bar with the section label, navigation trigger, and contextual breadcrumbs.
- `AppSidebarLayout`: assembles the bar, drawer, and main content region once for all authenticated pages.
- `AuthSimpleLayout`: presents a common authentication frame for all sign-in and recovery pages.

## Verification

Check representative routes from every role at 375, 1024, and 1280 px in light and dark themes. Read dimensions and computed styles, confirm active navigation and page labels after navigation, test the drawer by keyboard, and confirm no horizontal clipping or undersized controls. Do not capture screenshots.

The local preview was measured at those widths across member, manager, producer, and account settings routes. The app bar remains 64 px tall; content gutters are 16 px on narrow screens and 24 px on wider screens. The mobile drawer opens at 288 px normally and is capped at 311 px with 200% root text in a 375 px viewport. At 1024 px with 200% root text, the persistent drawer is about 306 px and the content region about 717 px. The measured routes had no horizontal document overflow. The first keyboard Tab exposes the skip link with a 48 px tall focus target. Light and dark surfaces both follow the current appearance setting. No screenshots were taken.
