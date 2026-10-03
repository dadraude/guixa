# Guixa

The app of a food cooperative: households order weekly from producers, the cooperative packs
their boxes and keeps each household's balance. It replaces the cooperative's old in-house
software.

## Documentation

- [Vision](docs/vision.md): what the cooperative is, who uses the app, what is in and out of
  scope.
- [Glossary](CONTEXT.md): domain terms, with the Catalan word used in the app. Use these
  terms everywhere (code, specs, tickets).
- [Principles](docs/principles.md): technical decisions and rules for the whole project.
- [Architecture decisions](docs/adr/): ADRs, one per significant decision.
- [Specs](specs/): one folder per feature (`NNN-name/spec.md`).
- [Status](STATUS.md): what has been done and the next step.

## How we work

Spec-driven development in short sessions, with Matt Pocock's skills: grill each feature,
write and approve its spec, split it into tickets in Linear, then implement test-first. See
[CLAUDE.md](CLAUDE.md) and [ADR 0001](docs/adr/0001-sdd-workflow-with-mattpocock-skills.md).

## Run locally

You need Docker, PHP 8.5 with Composer, and Node 22. [Laravel Sail](https://laravel.com/docs/sail)
runs the app with PostgreSQL, Redis and Mailpit.

```sh
composer install
npm install
cp .env.example .env
vendor/bin/sail up -d
vendor/bin/sail artisan key:generate
vendor/bin/sail artisan migrate
vendor/bin/sail npm run dev
```

The app is at <http://localhost> and the emails it sends are at <http://localhost:8025>.

Checks (the same ones CI runs; it blocks merging unless all pass):

```sh
vendor/bin/sail pest                          # tests, against PostgreSQL
vendor/bin/sail bin pint --test               # PHP style
vendor/bin/sail bin phpstan analyse           # Larastan
vendor/bin/sail npm run check                 # Oxlint and Oxfmt (Vite+)
vendor/bin/sail npm run types:check           # vue-tsc
```

User-facing text lives in `lang/ca.json` and `lang/ca/*.php`. In Vue, wrap strings with
`t('English text')` from `@/lib/i18n`; in PHP, with `__('English text')`. A test fails when a
string has no Catalan translation.
