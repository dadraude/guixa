# 001 — Weekly orders

Status: Accepted (2026-10-19) — includes the amendment for producer reception, stock and top-up at pickup.
Reopened (2026-10-19) for [OQ1–OQ18](open-questions.md): the requirements they block are not final.

Households order products from local producers every week and collect them on a fixed
pickup date. When each producer delivers, a manager checks what arrived against what was ordered
and allocates it to households. At pickup, each household takes its allocation plus any stock
products, everything is charged to its prepaid wallet, and it can top up the wallet on the spot.

## Glossary

| Term | Català | Meaning |
| --- | --- | --- |
| Household | Unitat de convivència | Ordering and paying unit. Has one or more members. |
| Member | Soci/sòcia | Person who belongs to exactly one household. |
| Manager | Gestor/a | Runs the cooperative: cycles, reception, pickup, stock, wallets. |
| Producer | Productor/a | Supplies products and manages its own catalog. |
| Product | Producte | Sold by a producer, with a unit (kg, unit, bunch…) and a unit price. |
| Variable product | Producte de pes variable | Quantity can be fractional (weight); delivered quantity may differ from ordered. |
| Cycle | Cicle | One week of ordering, identified by its fixed pickup date. |
| Cutoff | Tancament | Moment after which a producer's products can no longer be ordered in a cycle. |
| Order | Comanda | A household's order in one cycle. One per household per cycle. |
| Order line | Línia de comanda | Product + ordered quantity + unit price at the time of ordering. |
| Reception | Recepció | Check of one producer's delivery in one cycle: received vs ordered, and allocation to households. |
| Allocation | Assignació | Quantity of a product a household will take in a cycle. Starts equal to the ordered quantity. |
| Stock product | Producte d'estoc | Product kept at the cooperative's premises, with tracked quantity, sold at pickup. |
| Pickup | Recollida | A household collects its allocation and stock products, and is charged. |
| Wallet | Moneder | Household balance: top-ups minus charges. May be negative. |
| Top-up | Recàrrega | Money added to a wallet, recorded by a manager. |

## Time

Every date and time in this spec (pickup dates, cutoffs, "Wednesday 20:00") is local time in the
cooperative's timezone.

## Roles

- **Member**: orders for their household, sees their household's orders, balance and movements.
- **Producer**: manages their own products and default cutoff, sees aggregated quantities of their products.
- **Manager**: everything else — cycles, cutoff overrides, reception, pickup, stock, top-ups, households and members.

## Areas

Requirements (`R#`) and acceptance scenarios (`S#`) live in one file per area; numbers are global.

| File | Requirements | Scenarios |
| --- | --- | --- |
| [ordering.md](ordering.md) | R1–R13 | S1–S9 |
| [reception.md](reception.md) | R14–R18 | S10–S13, S27–S31 |
| [pickup.md](pickup.md) | R19–R24 | S14–S22, S26 |
| [stock-wallet.md](stock-wallet.md) | R25–R36 | S23–S25 |
| [open-questions.md](open-questions.md) | — | — |

## Out of scope (for now)

Online payments, membership fees, work shifts, selling stock outside a pickup, notifications,
minimum order per producer, invoicing and payments to producers.
