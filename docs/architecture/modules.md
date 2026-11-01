# Current module contracts

Guixa is a modular monolith: one application and PostgreSQL database, with module-owned business
rules and persistence. [`architecture/modules.php`](../../architecture/modules.php) is the executable
registry of the **current implementation**. Architecture tests consume it directly; it is not runtime
configuration. The [constitution](../../specs/constitution.md) remains the source of architecture rules.

This registry records the current implementation. T55 adds independent stock baskets and their
validation/collection while Ordering remains the weekly pickup writer until T59. Manifest changes
are checked by architecture tests.

## Ownership and allowed dependencies

Dependencies name the targets a module may call through declared exports. They do not grant access
to a target's entire Application namespace. Shared is a kernel; the other six entries are business
modules. `Platform` below names framework persistence ownership, not a `src/Platform` module.

| Module     | Owns today                                                                                                    | Allowed dependencies                       |
| ---------- | ------------------------------------------------------------------------------------------------------------- | ------------------------------------------ |
| Catalog    | Producers; all product identity, metadata, classification and administration                                  | Membership, Ordering, Sales, Stock, Shared |
| Membership | Accounts, households, roles, invitations and household lifecycle                                              | Catalog, Ordering, Wallet, Shared          |
| Ordering   | Weekly demand, cycles, cutoffs, producer orders, reception, allocation, and the current pickup/sale lifecycle | Catalog, Membership, Stock, Wallet, Shared |
| Sales      | Stock offers and independent basket drafts, validation, history and collection                                | Catalog, Membership, Stock, Wallet, Shared |
| Stock      | Return items, append-only stock movements and availability                                                    | Catalog, Shared                            |
| Wallet     | Append-only household money movements, balances, top-ups and corrections                                      | Shared                                     |
| Shared     | Money, Quantity, Clock, Actor and transaction contracts                                                       | None                                       |

Reciprocal dependencies are intentional. Catalog asks Ordering and Sales about retained product use
and changes producer participation; Ordering asks Catalog for eligible products. Read queries must remain narrow
and independently resolvable, without eagerly constructing a facade of all commands. A reciprocal
module relationship does not authorize recursive command execution or constructor dependency cycles.

Catalog owns stock product metadata too. Stock's immutable `StockProduct` is a translated snapshot at
its read/lock interface, not another authoritative product record. Snapshot duplication is intentional.
Ordering currently owns the cooperative pickup-weekday setting; that does not make it the owner of
all future cooperative settings.

## Integration exports

These exports are for calls **between modules**. Symbols in this table live in the owner's
`Application` namespace. The manifest also lists the DTOs that cross these interfaces. Everything
else in a module is private to that module for integration purposes, even when PHP declares it public.

| Owner → permitted caller | Exported operations                                                                                                    | Exported DTOs                                |
| ------------------------ | ---------------------------------------------------------------------------------------------------------------------- | -------------------------------------------- |
| Catalog → Membership     | GetProducer                                                                                                            | ProducerData                                 |
| Catalog → Ordering       | GetProducerCutoffRule, GetOrderableProduct, ListOrderableProducts, ListProducerProductsForReception, LockWeeklyProduct | ProducerCutoffRuleData, OrderableProductData |
| Catalog → Stock          | GetStockProduct, ListStockProducts, LockStockProduct                                                                   | StockProductData                             |
| Catalog → Sales          | ListStockOfferProducts, LockStockProduct                                                                               | StockOfferProductData, StockProductData      |
| Membership → Catalog     | RestoreProducerRole, SuspendProducerRole                                                                               | —                                            |
| Membership → Ordering    | GetHouseholds, GetProducerAccountEmail                                                                                 | HouseholdData                                |
| Membership → Sales       | ReadActiveHouseholds                                                                                                   | ActiveHouseholdData                          |
| Ordering → Catalog       | CheckProductUsage, HasUnsettledLines, StopParticipatingWhereNotSent                                                    | —                                            |
| Ordering → Membership    | ReadPendingPickups                                                                                                     | PendingPickupData                            |
| Sales → Catalog          | HasProductHistory                                                                                                      | —                                            |
| Stock → Catalog          | CheckProductUsage, GetAvailableQuantities                                                                              | —                                            |
| Stock → Sales            | GetAvailableQuantities, SellBasketStock                                                                                | —                                            |
| Stock → Ordering         | GetReturnItem, GetStockOffer, LockStockOffer, SellStock                                                                | ReturnItemData, StockLevelData               |
| Wallet → Membership      | ReadHouseholdBalance                                                                                                   | —                                            |
| Wallet → Ordering        | ChargeForPickup, RecordCorrection, RecordTopUp                                                                         | —                                            |
| Wallet → Sales           | ChargeForBasket, GetBalance                                                                                            | —                                            |

Shared explicitly exports `Application\Actor`, `ActorResolver`, `TransactionRunner`, and
`Domain\Money`, `Quantity`, `Clock`, `LocalTime`, `Role`, plus `InvalidLocalTime` and `InvalidQuantity`
in `Domain\Error`. Business modules may use these according to A1/A2. Application authorization uses Laravel gates
and policies against the explicit `Actor`; denials throw Laravel's `AuthorizationException`.
The A2 exception permits the Gate facade only; domain business rules remain framework-free. Shared's
Infrastructure is private and is bound at application bootstrap.

A caller declares its required port in its own Domain, or Application when it needs Actor. Its
Infrastructure adapter consumes the owner's exported Application operation and translates the DTO.
Foreign Domain and Infrastructure imports remain forbidden by A4. Internal coordination helpers such
as Ordering's `CycleSettlement` and `PickupLocker` are not integration exports.

HTTP controllers, console entry points and bootstrap wiring are the application host, not extra
business modules. This registry does not redefine their entry-point interface: A5/A6/A10 still
apply. In particular, dependency binding may name module ports and implementations without exporting
those implementations to another module. Tests, factories and seeders are outside runtime access
scanning; cross-module integration exports do not grant a controller permission to skip authorization.

## Table ownership

Each table has one owner in the manifest. A foreign key to another module's table does not grant
permission to query or update that table. Production module queries and model mappings belong in the
owning module's Infrastructure. Cross-module access goes through the declared interface.

| Owner      | Tables                                                                                                                                                                         |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Catalog    | producers, products                                                                                                                                                            |
| Membership | users, account_roles, households, invitations                                                                                                                                  |
| Ordering   | cooperative_settings, cycles, cycle_producers, orders, order_lines, product_unavailabilities, received_totals, order_stock_lines, order_return_lines, order_pickup_adjustments |
| Sales      | baskets, basket_lines, basket_collection_events                                                                                                                                |
| Stock      | return_items, stock_movements                                                                                                                                                  |
| Wallet     | wallet_movements                                                                                                                                                               |
| Platform   | password_reset_tokens, sessions, cache, cache_locks, jobs, job_batches, failed_jobs, passkeys                                                                                  |
| Shared     | None                                                                                                                                                                           |

Platform tables are managed through Laravel/Fortify infrastructure. Naming them here does not give
business modules direct access. Historical migrations may span modules: tests reconcile their
`Schema::create` declarations with the complete ownership map without executing migrations.

Existing exceptions are intentionally narrow:

- `Shared/Infrastructure/DatabaseActorResolver.php` may **read** Membership's `users` and
  `account_roles`, for A10 entry-point resolution. The exception does not allow writes, another table,
  another path, or returning a deferred query builder. Moving this adapter is a separate change.
- `app/Models/User.php` maps Membership's `users` for framework authentication, retaining A6's existing
  exception. Authentication-related model use by Fortify remains governed by the host integration;
  the checker does not attempt runtime tracking of every model instance.
- Ordering's private `EloquentOrderRepository::rowsOf($table, ...)` is the sole declared dynamic-table
  helper. Every direct method or static call must supply a literal table name owned by Ordering.
  The table parameter may only be passed by value to `DB::table` or the existing `in_array` filter;
  reassignment, reference aliases and other uses fail the check. An unresolved argument, foreign
  table, public helper, or undeclared dynamic access also fails.
- Membership's exact `select pg_advisory_xact_lock(191983, 1)` SQL is allowed in its household
  repository. It locks a Membership operation without accessing a table. Other raw SQL needs review.

## Transactions and authorization

`CompletePickup` currently owns the transaction coordinating the pickup, stock movements, an optional
top-up, the wallet charge and cycle closure. Stock products are processed in ascending id order.
Reception redistribution similarly coordinates order adjustments and wallet corrections atomically.
Retain the rollback and concurrency tests when changing either workflow. Modules share the same local
`TransactionRunner`; mail is scheduled after commit.

Actor-based operations retain their own authorization checks. Internal read queries such as
`ReadPendingPickups` and `ReadHouseholdBalance` accept no Actor because their caller already authorizes
the user operation. An export is permission to depend on a symbol, not user authorization.

## Enforcement and maintenance

`tests/Architecture/ModuleManifestTest.php` checks the registry against current module directories,
existing exported symbols, permitted callers, migration-created tables, and scoped exceptions. It
applies the checker to all PHP files under `src/`, `app/` and `routes/`. Existing architecture tests
continue to enforce A1–A8 and A10; Oxlint enforces A9. No database, web server or frontend build is
needed for these PHP checks:

```sh
php artisan test tests/Architecture
```

Use the configured PHP 8.5 runtime and isolated task environment required by the execution profile.
The checker uses the project's PHP parser to resolve aliases, grouped imports, native type names and
fully qualified Guixa class strings. It checks literal query-builder tables, joins, table overrides,
explicit/inferred direct Eloquent model mappings, model references from modules, and business model
references from the application host. Ordinary and nullsafe method calls are checked. Recognized SQL
execution methods, raw table sources and SQL-bearing raw expressions require a declaration; ordinary
literal raw expressions without SQL table operations remain permitted. Direct local assignments from
`DB::connection()` are recognized conservatively by variable name within the file, even when SQL is
variable. Read-only exceptions must reach a recognized terminal read through a continuous chain of
simple literal-column `where`/`orWhere`/null filters and `orderBy` calls. Callbacks, unrecognized
intermediate operations, deferred builders and unrelated enclosing reads do not qualify.

This is static enforcement, not runtime information-flow analysis. It does not infer arbitrary dynamic
class construction or method names, analyze vendor internals, follow query builders or connection
aliases across arbitrary assignments/methods, or parse PHPDoc-only references without imports. Host
references to the framework authentication model retain the integration exception described above.
Raw-expression checks use SQL keywords, not a complete SQL parser. Existing layer rules and code
review remain necessary.
Unsupported database access patterns must be made statically checkable or receive a narrowly reviewed
extension to the checker; do not add a whole-file or whole-module bypass. New table creation should use
`Schema::create`, so migration ownership stays discoverable.

When changing a module contract:

1. Update the constitution first if an architecture rule changes.
2. Update the manifest deliberately: ownership, exact export, allowed callers and dependency edge.
3. Update this document's ownership/export tables and any affected feature plan. Follow the spec flow
   for product changes; a manifest edit does not authorize new behavior.
4. Add a rejection example if the checker needs a new supported syntax or exception shape.
5. Run both the checker examples and the complete architecture suite. Existing behavior tests remain
   required when changing runtime behavior.

## Planned Sales transition

The [accepted plan](../../specs/001-weekly-orders/plan.md#bounded-contexts) already separates Ordering's
weekly demand from Sales baskets, validation and collection. The current registry includes independent
stock baskets, but no Finance role grant, weekly basket writer, return lines or corrections.

Each delivered slice updates the registry and its tests alongside the code. When Sales becomes the
weekly sale owner at T59, remove the superseded Ordering writer relationships at that cutover. Finance
is a permission area; Wallet
remains the money ledger. This baseline does not complete or reorder those tasks.
