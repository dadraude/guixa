# Guixa

Management app for a food consumer cooperative. One installation serves one cooperative.

Stack: Laravel 13 (PHP 8.5), Inertia 3 + Vue 3, PostgreSQL. See [`specs/constitution.md`](specs/constitution.md)
for principles and architecture, [current module contracts](docs/architecture/modules.md) for enforced
module interfaces and table ownership, and [`specs/README.md`](specs/README.md) for how features are specified.

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

## Demo data

To start the app with demo data, run this in `local` or `testing`:

```sh
php artisan migrate --seed
composer dev
```

Open <http://localhost:8000> and sign in as `gestora@demo.guixa.test` with password `password`.
`DatabaseSeeder` calls `DemoSeeder`; its accounts are activated and verified.

| Role              | Account                                                                                          |
| ----------------- | ------------------------------------------------------------------------------------------------ |
| Manager           | `gestora@demo.guixa.test`                                                                        |
| Household members | `anna@demo.guixa.test`, `biel@demo.guixa.test`, `carla@demo.guixa.test`, `david@demo.guixa.test` |
| Producers         | `hort@demo.guixa.test`, `forn@demo.guixa.test`, `formatgeria@demo.guixa.test`                    |
| Former member     | `eva@demo.guixa.test` (no household or role; shows the dashboard's no-context state)             |

The demo creates four households, three producers, six weekly products, two stock products,
opening wallet balances and stock entries. Each household has an order in three upcoming cycles:

- The earliest cycle is ready for pickup, with one completed pickup and three pending.
- The next cycle has sent producer orders and a partially prepared reception.
- The last cycle is open for members to edit their orders.

Dates follow the cooperative's timezone and configured pickup weekday (Thursday when none is set),
skipping dates already occupied by live cycles. Demo setup sends no invitation or order emails.
Creation is transactional, and rerunning the seeder leaves the existing demo and any manual edits
intact. Existing application data is preserved. To start the demo over, use a separate empty database.

## Tests and checks

Use the [testing and CI reproduction guide](docs/testing.md) for isolated task setup, focused
selectors, and the current CI checks. Application tests require PostgreSQL and Redis; run them
through the guarded task environment described there rather than using the shared local database.
