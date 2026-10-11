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
- Status: what has been done and the next step lives in Linear (project Guixa status updates).

## How we work

Spec-driven development in short sessions, with Matt Pocock's skills: grill each feature,
write and approve its spec, split it into tickets in Linear, then implement test-first. See
[CLAUDE.md](CLAUDE.md) and [ADR 0001](docs/adr/0001-sdd-workflow-with-mattpocock-skills.md).

## Run locally

You need [Laravel Herd](https://herd.laravel.com) (PHP 8.5, Composer, Node 22) and
[DBngin](https://dbngin.com) running PostgreSQL 18 and Redis. Herd serves the app at `http://guixa.test`.

```sh
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run dev
```

The app is at <http://guixa.test> and the emails it sends show up in Herd's mail panel (if enabled).
Create the `guixa` and `testing` databases in DBngin's PostgreSQL first.

Checks (the same ones CI runs; it blocks merging unless all pass):

```sh
vendor/bin/pest --parallel               # tests, against PostgreSQL
vendor/bin/pint --test               # PHP style
vendor/bin/phpstan analyse           # Larastan
npm run check                 # Oxlint and Oxfmt (Vite+)
npm run types:check           # vue-tsc
```

Local full-suite runs use `--parallel` without `--processes` or any other explicit worker-count
limit; let the runner use its default parallelism. Filtered test runs stay serial.

Parallel runs need the base `testing` database to exist (a serial `vendor/bin/pest` creates it); the
workers create `testing_test_N` from it.

End-to-end tests (Playwright, `e2e/`) drive a real browser through the critical path: a member is
created with a household, the welcome email arrives, they sign in with the 6-digit code of a magic
link, set a password, sign out and sign in with the password. They need
[Mailpit](https://mailpit.axllent.org) (SMTP on 1025, web API on 8025, e.g.
`docker run -d -p 1025:1025 -p 8025:8025 axllent/mailpit`) and the local `testing` database. The run
wipes that database (`migrate:fresh`) and refuses any other name or a non-local host.

```sh
npm run build
npm run e2e
```

Set `MAILPIT_URL` if Mailpit's API is not at `http://127.0.0.1:8025`.

User-facing text lives in `lang/ca.json` and `lang/ca/*.php`. In Vue, wrap strings with
`t('English text')` from `@/lib/i18n`; in PHP, with `__('English text')`. A test fails when a
string has no Catalan translation.
