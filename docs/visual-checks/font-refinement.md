# Softer application typography

Observed 2026-10-27 in the `feature/softer-readable-font` worktree.

## Scope and reuse

The shared `pilot-theme` typography tokens in `resources/css/app.css` now use
the locally bundled Nunito variable face for body text and headings. The
existing `coop-manager-shell` and portal theme classes inherit those tokens.
JetBrains Mono remains the code face. No page component or new UI component
was needed. The guest `Welcome` page remains outside the theme boundary.

The affected patterns are the shared sidebar, page headings, lists, tables,
forms, authentication panels and settings. The DRA-192 fixture covers 14
manager views; DRA-193 covers authentication, settings, orders, dashboard and
producer views. Their existing populated-state fixtures were reused. DRA-197
continues to cover ordering and pickup-specific states. Font descriptors in
all three manifests now name Nunito; fallback states use the system sans stack.

## Rendered checks

`resources/js/fixtures/font-visual.mjs` checks the loaded font face, computed
body and heading families, and document horizontal overflow. Each populated
view was measured at 375 px and 1280 px in light and dark modes with 100%
text, and at 1024 px in both modes with 200% text. Results:

- DRA-192 manager fixture: 84 cases, 0 failures.
- DRA-193 authentication, settings and application fixture: 90 cases, 0 failures.
- Fallback font states: 2 DRA-192 and 6 DRA-193 cases, 0 failures.
- One 375 px manager stock capture was inspected after the measurements.

The visual manifest `plan` checks passed for DRA-192, DRA-193 and DRA-197.
`pnpm check`, `pnpm types:check` and `git diff --check` passed.

The older DRA-199 layout script uses spacing and element assumptions that no
longer match the current manager screens, so it cannot serve as a typography
gate. Its contrast script also reports 1:1 for every pair because the current
branch has no `--background` or `--foreground` declarations. The font change
does not alter colors; contrast was not revalidated here. These focused checks
do not establish persistence or authorization.
