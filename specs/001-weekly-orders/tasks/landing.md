# 001 — Public landing task details (2026-11-11)

Part of the [canonical registry](../tasks.md); dependencies live there. The page's content and design
are in the accepted [public Welcome landing direction](../plan.md#2026-11-11-cooperative-landing-direction).

## T83

**Implement the approved light cooperative landing.** This task changes the public presentation only;
it does not change the server-side ordering decision or authenticated access destination.

The component plan keeps `WelcomePage.vue` as the composition surface and splits the distinct page
regions into `LandingHeader.vue`, `LandingHero.vue`, `LandingStory.vue` and `LandingContact.vue`.
Continue to use the shared `Button`, Lucide icons, `trans()` and the existing `/` route. Keep page colors
scoped to the landing. The wordmark is a typographic approximation because there is no source vector.

### Acceptance

1. The live Welcome page and visual fixture render the approved Catalan hero, cooperative values, order
   steps, contact details and footer. Navigation targets the cooperative, process and contact sections;
   the mail link is `mailto:laguixamanresa@gmail.com`.
2. The member-access link stays in the header and continues to use the server-provided login or dashboard
   URL. The `OrderController` route and member-order presentation do not change.
3. At 375 and 1280 px, light and dark, 100% text, the page has its one-column mobile or spacious desktop
   layout. At 1024 px with 200% text, content reflows without horizontal overflow or clipped text.
4. Navigation, CTAs and contact links are keyboard reachable with visible focus. Interactive targets are
   at least 44 px tall. Text contrast is at least 4.5:1 in both themes. Reduced motion and fallback fonts
   introduce no broken layout; this page adds no animation.
5. Focused Vue assertions cover approved sections, in-page links, email and both member-access URLs.
   Existing guest, member and non-member route tests still pass.
6. Run formatting, lint, types, frontend tests and the selected local CI jobs through the guarded task
   environment. Routine visual validation uses the declared measurement fixture; a screenshot is not
   required.

### Demo data

No `DemoSeeder` or `DemoSeederTest` change is needed: this is static public copy and adds no user-visible
record, role, state or application screen.
