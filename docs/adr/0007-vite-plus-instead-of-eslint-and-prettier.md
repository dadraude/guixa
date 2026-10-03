# 0007 — Vite+ instead of ESLint and Prettier

- Status: accepted
- Date: 2026-10-03

The Laravel Vue starter kit now ships **Vite+** (`vp check`), which runs Oxlint and Oxfmt with
type-aware linting, instead of ESLint and Prettier. We keep the kit's tooling rather than
replace it: less code and configuration, and one tool for linting, formatting and type
checks. The kit's code is ours from day one (ADR 0006), so there is no upstream to stay
aligned with, but also no reason to swap a working setup.

## Considered options

- **Re-adding ESLint and Prettier:** the most familiar tools with the largest plugin
  ecosystem, but two more dev dependencies and their configuration to maintain, and slower
  than the kit's tooling.

## Consequences

- CI runs `npm run check` (Vite+ lint and format check) next to `npm run types:check`.
- Markdown docs are excluded from formatting, so specs and ADRs are not rewrapped.
- ESLint and Prettier plugins are not available; if we ever need a rule only they provide,
  this decision should be revisited.
