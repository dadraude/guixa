---
status: accepted
---

# Calculations live in the domain; screens read through read repositories; Vue only displays

A field added deep in the `Basket` aggregate travels through every layer and every screen: DRA-228
threaded `effectiveQuantity` and `pendingCollection` through `Basket` → `BasketData` →
`BasketLineData` → `BasketRow`/`BasketSummaryRow` → `generated.d.ts` → four pages and their specs,
56 files in total. One write aggregate, mapped into one nested DTO tree, feeds every screen, and the
business calculations are scattered along the way: line amounts in `BasketLineData::from`, the
projected balance in `BasketRow::from`, the pending-collection rule in `BasketData::from`, and a
second copy of price × quantity with its rounding in `resources/js/lib/orderTotal.ts`.

We decided that each calculation has exactly one owner, that each screen reads a flat shape made for
it through a read repository, and that the frontend only displays data and triggers user actions.

## Rules

1. **One owner per calculation.** A value that depends only on an aggregate's own state is a method
   on that aggregate or its entities (`Basket::isPendingCollection()`, `BasketLine::effectiveAmount()`).
   A value that spans aggregates or contexts is a domain service (for example the projected balance,
   which needs the Wallet balance through the `WalletLedger` port). No other class repeats the formula.
2. **Writes go through the aggregate repository; reads go through read repositories.** Each context
   declares read ports in `Application` with one method per need (`summaries(filter)`,
   `linesOf(basketId)`, `adjustmentsOf(basketId)`), implemented in `Infrastructure`. They return flat
   DTOs with derived values already computed by the owners in rule 1, never a second formula in SQL.
3. **DTOs carry, they do not compute.** `*Data` and `*Row` classes only copy fields and format them
   for transport (dates, decimal strings). An architecture test enforces that they do not depend on
   `Money`, `Quantity` or domain services.
4. **Screens receive sibling props, not trees.** A page gets the slices it shows (`basket`, `lines`,
   `adjustments`, `events`) and passes each to the component that renders it, so a change to one
   slice touches its read method, its DTO and its component only.
5. **Vue displays and dispatches.** Pages and components render props and submit user actions. They
   contain no business arithmetic or rules; every amount, total, balance and state flag arrives from
   the server. Formatting for display (currency, dates, pluralisation) stays in the frontend.

## Considered options

- **Keep one aggregate DTO tree per context.** Rejected: it is the cause of the ripple.
- **Make Application `*Data` the Inertia props and drop `*Row`.** Saves files but keeps the nested
  tree and removes the explicit list of what reaches the browser; it does not address the problem.
- **Reads as raw queries in controllers.** Rejected: controllers would own data access and could not
  reuse the domain calculations.

## Consequences

- `specs/constitution.md` currently allows Vue to compute display-only values such as a running total
  while editing; that clause now forbids it, and `orderTotal.ts` is retired.
- A total that follows unsaved input is recalculated by the server. The page sends the draft
  quantities, debounced, to a read-only preview query and refreshes only that prop with an Inertia
  partial reload (`only: ['preview']`); nothing is persisted. The order page is the first case: it
  shows the saved total from the server and the preview while a box holds an unsaved quantity. The
  cost is one request per pause in typing and a short delay before the figure updates.
- Read repositories get contract tests against PostgreSQL; they need no in-memory fakes, since the
  Application tests that use them can run against the database.
- Adoption is incremental: a screen moves to read repositories the next time a feature touches it,
  starting with the basket pages.
