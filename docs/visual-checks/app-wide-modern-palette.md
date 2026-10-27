# App-wide modern palette

Observed 2026-10-27 in `feature/app-wide-modern-palette`. This extends the
[modern palette refinement](modern-interface.md) to the remaining guest entry
screen and shared browser chrome.

## Component plan

- `resources/js/pages/Welcome.vue` remains the route adapter for the guest and
  signed-in destination. `resources/js/components/WelcomePage.vue` holds its
  single presentation, shared by the live page and visual fixture. The prior
  Laravel starter composition had no Guixa component to reuse.
- The landing action reuses `resources/js/components/ui/button/Button.vue`.
  The page, fields, and action styles reuse semantic tokens from
  `public/theme-colors.css`; no second palette or button variant was added.
- Shared validation errors, Inertia progress, the document background and the
  browser favicon now use the same identity and color meanings.

## Selected coverage

`resources/js/fixtures/welcome.visual.json` renders the real WelcomePage:

- Guest: 375 and 1280 px, light and dark, 100% and 200% root text; 1024 px,
  light and dark, 200% root text. This covers the mobile single-column layout,
  desktop two-column layout, theme contrast and enlarged-text reflow.
- Signed in: 375 and 1280 px, light and dark, 100% root text. This checks the
  alternative dashboard action on both layouts.
- Every case tabs to the main action. The fixture uses local translation data
  and deterministic links; it does not prove authentication or navigation.

`resources/js/fixtures/manager-fields.visual.json` checks 16 existing manager
and shared-control cases, including normal, invalid and disabled form states,
all action variants, both themes and enlarged text. These fixtures check the
shared patterns used throughout the application; they do not render every
route or prove saved actions.

The first Welcome run found text escaping its column at 1024 px with 200% text.
Moving the two-column breakpoint to 1280 px fixed it. The expanded matrix
then found a narrow list overflow at 375 px with 200% text; wrapping and
spacing adjustments fixed it. Final Chrome reports have no overflow or focus
failure. I inspected the mobile light, desktop dark and 200% mobile captures.

## Verification

- `pnpm run check`
- `pnpm run types:check`
- `pnpm run build`
- `node skills/agent-workflow/scripts/visual/run.mjs verify` for both manifests
- `git diff --check`

The visual fixtures ran from `pnpm run dev --host 127.0.0.1 --port 5182` using
Chrome. Reports and galleries are in `/tmp/guixa-welcome-visual` and
`/tmp/guixa-manager-visual`.
