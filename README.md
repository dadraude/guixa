# Guixa

Management app for a food consumer cooperative. One installation serves one cooperative.

Stack: Laravel 13 (PHP 8.5), Inertia 3 + Vue 3, PostgreSQL. See [`specs/constitution.md`](specs/constitution.md)
for principles and architecture, and [`specs/README.md`](specs/README.md) for how features are specified.

## Requirements

- PHP 8.5 and Composer
- Node.js and pnpm
- PostgreSQL
- [Mailpit](https://mailpit.axllent.org/) (SMTP on `127.0.0.1:1025`) to read invitation emails

## Getting started

1. Create the database. The defaults in `.env.example` expect a database `guixa` on `127.0.0.1:5432`,
   user `postgres`, no password; edit `.env` if yours differs.

    ```sh
    createdb guixa
    ```

2. Install dependencies, create `.env`, generate the app key, run migrations and build the frontend:

    ```sh
    composer setup
    ```

3. Start the server, queue worker, scheduler, logs and Vite together:

    ```sh
    composer dev
    ```

    The app runs at <http://localhost:8000>.

4. Create the first manager. This only works while no manager exists:

    ```sh
    php artisan guixa:create-manager "Your Name" you@example.com
    ```

    Open the invitation email in Mailpit (<http://localhost:8025>) and follow the link to set up the account.
    From there, the manager invites the rest of the members from the app.

## Tests and checks

```sh
php artisan test   # Pest, against the guixa_test database (see phpunit.xml)
pnpm test          # Vitest
pnpm check         # Oxlint + Oxfmt
vendor/bin/pint --test
vendor/bin/phpstan analyse
```
