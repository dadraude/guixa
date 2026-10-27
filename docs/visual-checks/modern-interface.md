# Modern palette refinement

Observed 2026-10-27 in `feature/modern-warm-interface`. This supersedes the
color choices in [the warm palette preview](warm-unified-palette.md) after the
user found that preview too retro. The action hierarchy remains the same.

## Design and component reuse

`public/theme-colors.css` now supplies near-white warm-neutral surfaces,
charcoal text and one deep green primary. Secondary fills and hover surfaces
have low chroma; destructive actions retain their distinct red. Dark mode uses
neutral stepped charcoal surfaces and a soft green primary. No page-specific
palette, new component, font or runtime package was added. The existing shared
Button, Input and FormSelect components and manager button classes continue to
use the semantic tokens.

## Rendered coverage

The retained `resources/js/fixtures/manager-fields.visual.json` covers 16
cases across manager and shared scopes: populated forms and action variants at
375 and 1280 px in both themes, 375 px with 200% text, 1024 px with 200% text
in both themes, plus invalid and disabled mobile manager controls. Chrome
reported no layout or keyboard-focus failures, and the 375 px document had no
horizontal overflow. The light and dark mobile captures were inspected after
measuring their computed styles.

Rendered WCAG contrast ratios were identical in both scopes:

| Pair                                   |   Light |    Dark |
| -------------------------------------- | ------: | ------: |
| Body text / canvas                     | 13.66:1 | 14.80:1 |
| Muted text / canvas                    |  5.64:1 |  9.80:1 |
| Primary button text / fill             |  6.33:1 |  8.77:1 |
| Secondary button text / fill           |  6.97:1 | 10.25:1 |
| Destructive button text / fill         |  5.85:1 |  6.60:1 |
| Input and secondary button edge / card |  3.66:1 |  5.86:1 |

`pnpm check`, `pnpm types:check`, `git diff --check` and the visual verifier
passed on the committed candidate. The fixture checks shared visual patterns;
it does not exercise every application route or saved action. The guest
`Welcome` artwork retains its separate presentation.
