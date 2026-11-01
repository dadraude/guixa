# Constitution

Guixa is a management app for a food consumer cooperative.

One installation serves exactly one cooperative (single-tenant); there is no tenant id anywhere.
Cooperative-wide settings are configuration (e.g. its timezone), unless a spec lets a manager change them
in the app (e.g. the pickup weekday): those live in a single-row settings table.

## Stack

- Laravel 13, PHP 8.5.
- Inertia 3 + Vue 3 (Composition API, `<script setup lang="ts">`), built with Vite. No Livewire, no separate SPA/API.
- UI: Tailwind 4 and shadcn-vue (on Reka UI), copied into `resources/js/components/ui` as the starter kit
  ships them. No other component library.
- PostgreSQL in every environment except fast unit tests.
- Pest for backend tests, Vitest for Vue components with client-side logic.
- PHP DTOs passed as Inertia props generate their TypeScript types (`spatie/laravel-typescript-transformer`);
  never hand-written duplicates.
- Code quality: Pint and Larastan (PHPStan) for PHP; Oxlint and Oxfmt for TypeScript and Vue, through Vite+
  (`vp check`) as the starter kit ships them.
- CI on GitHub Actions runs lint, static analysis, Pest (architecture tests included) against Postgres
  and Vitest on every pull request; a red build blocks the merge.
- Mail: the `log` driver in tests, Mailpit in development.

## Architecture

Hexagonal. The domain must not know Laravel exists.

```
src/
  <Context>/            # e.g. Ordering, Catalog, Wallet
    Domain/             # entities, value objects, domain errors, repository ports. Plain PHP.
    Application/        # use cases (one class per command/query), input/output DTOs.
    Infrastructure/     # Eloquent/DB repositories, mail, clock. Implements domain ports.
app/Http/               # controllers: thin adapters that call use cases and render Inertia pages.
resources/js/
  pages/                # one Vue page per Inertia route. Receives props, emits form submits.
  components/           # presentational components, no data fetching.
```

- Controllers only translate HTTP ↔ use case DTOs and call `Inertia::render`. No business rules in
  controllers, models or Vue.
- Vue may compute display-only values (e.g. a running total while editing), but the server is
  always the source of truth: the charged amount comes from the use case, never from the client.
- Eloquent models live in Infrastructure and never leak into Domain or Application.
- Time comes from a `Clock` port, so cutoffs are testable.

## Architecture rules

`src/` is the `Guixa\` namespace. `Shared` holds cross-context value objects (`Money`, `Quantity`, `Clock`).
These rules are enforced by Pest architecture tests in `tests/Architecture/`, one test per rule,
iterating over every context. A rule change edits this list first.

- **A1 — Pure domain.** `Guixa\<Context>\Domain` uses only its own Domain, `Guixa\Shared\Domain`
  and the PHP standard library. No `Illuminate`, no Carbon, no other context.
- **A2 — Application depends on Domain, with Laravel authorization.** `Guixa\<Context>\Application` uses its own Domain,
  its own Application and `Guixa\Shared`. The sole framework exception is
  `Illuminate\Support\Facades\Gate` for authorization against the explicit entry-point actor.
  No other `Illuminate` dependency or Infrastructure is allowed. Gates and policies are registered
  outside Application; domain business invariants remain plain PHP under A1.
- **A3 — Dependencies point inwards.** Nothing in Domain or Application uses Infrastructure.
- **A4 — Context isolation.** A context never touches another context's Domain or Infrastructure.
  When it needs data from another context, its Domain declares a port and its Infrastructure
  implements it by calling the other context's Application (use cases / query DTOs).
- **A5 — Thin controllers.** `App\Http` uses only Application use cases and DTOs, plus domain
  errors (`Guixa\<Context>\Domain\Error`) to map them to HTTP responses. Never Domain entities,
  repositories or Infrastructure.
- **A6 — Eloquent stays in Infrastructure.** Classes extending `Illuminate\Database\Eloquent\Model`
  live only in `Guixa\<Context>\Infrastructure\Persistence` (the auth `App\Models\User` is the only exception).
- **A7 — Shape of building blocks.** Value objects and DTOs are `final readonly`. Use cases are `final`,
  named `<Verb><Noun>` (e.g. `ConfirmPickup`), expose a single public `__invoke`, and live in `Application`.
  Repository ports are interfaces in `Domain`, named `<Noun>Repository`.
  The test finds them by shape: value objects live in `Domain\ValueObject`; an `Application` class
  with `__invoke` is a use case and any other `Application` class is a DTO.
- **A8 — Hygiene.** Pest presets `php()` and `security()` pass. No `dd`, `dump`, `ray`, `var_dump` anywhere.
- **A9 — Frontend boundaries.** Vue files in `resources/js/components` never import from `pages`,
  never call `router`, `axios` or `fetch`, and never import `@inertiajs/*`. Enforced with Oxlint
  `no-restricted-imports` / `no-restricted-globals`.
  The starter-kit components that predate this rule are listed as exceptions in `vite.config.ts`.
- **A10 — Actors come from the entry point.** Outside `src/Shared`, nothing in `src/` or `app/` builds
  an `Actor`; it arrives from `ActorResolver` at the user entry point. A context that needs another
  context's data calls an internal query that takes no actor, instead of forging one.
- **A11 — Declared modules and exports.** `architecture/modules.php` records the current modules,
  their allowed dependencies and exact integration exports with permitted caller modules. Foreign
  references in `src/` must use these exports through Infrastructure; Shared's declared Domain and
  Application contracts remain available through inward dependencies. Application helpers are private
  unless explicitly exported. HTTP/bootstrap access continues to follow A5/A6/A10.
- **A12 — Table ownership.** Every table created by migrations has exactly one declared owner.
  Runtime PHP in `src/`, `app/` and `routes/` may access a table only from its owner's Infrastructure,
  or through a named, scoped exception in the manifest. Literal query-builder tables, joins and model
  mappings are checked statically. Dynamic table selection and raw SQL require explicit reviewed
  declarations; exceptions must not silently authorize additional tables or writes.

The [current module contract](../docs/architecture/modules.md) explains the manifest, existing
exceptions, check coverage and the transition to the separately planned Sales module.

## Principles

- Money is an integer number of cents (`Money` value object). Never floats.
- Quantities are decimals with 3 places (`Quantity` value object), so 1.125 kg is exact.
- Time: every local date and time (cutoffs, pickup dates, "today") is interpreted in the cooperative's
  timezone, a setting (`COOP_TIMEZONE`, e.g. `Europe/Madrid`), never hard-coded. Stored in UTC.
- Every acceptance scenario in a spec has a test at the Application layer (use case level).
  HTTP tests only cover authorization, wiring and Inertia props (`assertInertia`).
- Always TDD: write the failing test first and watch it fail for the right reason, then the minimum code
  to pass, then refactor while green. No production code without a failing test that demands it. The only
  exceptions are changes with no executable behavior (docs, config), and each one states why.
- Early returns, small classes with one responsibility, explicit names. Comments only for *why*.
- Code, tests and specs are written in English. Domain glossary keeps the Catalan term for reference.

## UI

Members order from phones as well as computers, and their digital skills are mixed
([UX discovery](../docs/ux-discovery/2026-10-24-guixa-discovery.md), principle 3). A page that works for the least
confident member on a phone works for everyone.

- **Phone first.** Every page is laid out for a 375 px wide screen first and widened from there. No horizontal
  scroll, and nothing reachable only on a wide screen.
- **Touch, not hover.** Touch targets are at least 44 × 44 px. No action or information depends on hover.
- **Plain language.** Labels say what happens in the member's words, not the domain's internals; one main action
  per screen.
- **Translatable.** Every text a person reads (pages, emails, validation and domain error messages) comes from a
  translation in `lang/`, never a literal in PHP or Vue. Catalan (`ca`) is the default and fallback locale, and the
  only one shipped for now; another language is added as more `lang/` files, without code changes.
- A task with a page is not done until that page has been checked at 375 px wide.
