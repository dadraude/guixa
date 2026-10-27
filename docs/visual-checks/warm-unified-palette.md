# Unified warm palette and action hierarchy

Observed 2026-10-27 in `feature/warm-unified-palette`.

## Scope and reuse

The operational interface uses one light/dark token map in `public/theme-colors.css`.
`resources/js/components/ui/button/index.ts` remains the shared button source:
filled olive for primary actions, pale sage with a visible edge for secondary,
card-surface outline for alternative actions, and muted red for destructive
actions. The existing `manager-button` styles use the same semantic tokens.
`resources/css/app.css` gives shared inputs and select triggers a bordered card
surface at every width. No new product component or runtime dependency was added.

The representative fixture uses the real Input, FormSelect and Button
components. Its manager and shared scopes cover the same token pairs; the
`Welcome` artwork retains its own hardcoded presentation.

## Coverage selection

`resources/js/fixtures/manager-fields.visual.json` declares 16 cases:
manager and shared populated states at 375 and 1280 px in light/dark mode,
375 px with 200% text, and 1024 px with 200% text in both modes. The manager
scope also covers invalid and disabled controls at the riskiest mobile width.
This covers the changed input, select and action variants, their focus order,
mobile wrapping and large-text reflow. The fixture uses local component and
style source, and mocks no network requests.

## Rendered checks

The visual runner reported no layout or keyboard-focus failures in all 16
cases. At 375 px, every sampled control was at least 44 px high and document
scroll width stayed at 375 px. The light and dark manager captures were
inspected after computed-style measurements.

Rendered WCAG contrast ratios, identical in manager and shared scopes:

| Pair                                   |   Light |    Dark |
| -------------------------------------- | ------: | ------: |
| Body text / canvas                     | 12.36:1 | 13.43:1 |
| Muted text / canvas                    |  5.94:1 |  8.99:1 |
| Primary button text / fill             |  6.74:1 |  8.35:1 |
| Secondary button text / fill           |  8.48:1 |  8.83:1 |
| Destructive button text / fill         |  6.33:1 |  6.00:1 |
| Input and secondary button edge / card |  3.64:1 |  4.61:1 |

The runner's final verifier, `pnpm check`, `pnpm types:check` and
`git diff --check` passed on the committed candidate. This fixture checks the
shared visual patterns; it does not exercise every application route or any
saved action.
