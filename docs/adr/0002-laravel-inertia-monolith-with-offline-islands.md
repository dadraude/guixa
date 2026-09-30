# 0002 — Laravel + Inertia monolith with offline islands

- Status: accepted
- Date: 2026-09-30

Guixa is a Laravel + Inertia + Vue 3 (TypeScript) monolith. Laravel brings queues, scheduler,
mail, auth, migrations and policies out of the box, and a single codebase is the easiest to
maintain and deploy for one developer with agents. Packing boxes and recording payments must
work offline, so only those screens are built as Vue "islands" with IndexedDB, an outbox and a
sync endpoint, cached by a service worker; the rest of the app is online-only.

## Considered options

- **Laravel API + separate Vue SPA:** offline comes naturally, but it means two apps, API
  auth (Sanctum/CORS) and duplicated permission logic, to benefit only two screens.
- **TypeScript full-stack (Next.js, React Router, SvelteKit):** one language, but more pieces
  to assemble (queue, scheduler, mail) on a self-hosted server.
- **Sync engine (PowerSync, ElectricSQL):** stronger offline, but extra services and cost for
  a small offline scope with rare conflicts.

## Consequences

- Inertia pages do not navigate offline; any new offline need must be built as another
  island with its own sync endpoint.
- Offline conflicts are resolved by "last change wins, with a warning", which relies on each
  box being packed by one person at a time.
