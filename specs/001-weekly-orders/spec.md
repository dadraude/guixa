# 001 — Weekly orders

Status: Accepted (2026-10-19) — includes the amendment for producer reception, stock and top-up at pickup.
Revised (2026-10-20): OQ1–OQ18 resolved and the gap review of the revision applied (fixed pickup weekday,
late deliveries, per-cycle product availability, account deactivation, validated receptions). OQ19 resolved
(2026-10-20): containers per unit on products and stock products, counted per household and cycle (R1, R10, R18a,
R20, R25).

Households order products from local producers every week and collect them on a fixed
pickup date. After each producer closes, a manager sends it the order. When the producer delivers,
a manager checks what arrived and allocates it to households. Once every producer is distributed,
each household picks up its allocation plus any stock products, everything is charged to its prepaid
wallet, and it can top up the wallet on the spot.

## Glossary

| Term | Català | Meaning |
| --- | --- | --- |
| Household | Unitat de convivència | Ordering and paying unit. Has members; inactive once it leaves or loses its last member. |
| Member | Soci/sòcia | Person who belongs to at most one household; only with a household can they order. |
| Manager | Gestor/a | Runs the cooperative: cycles, reception, pickup, stock, wallets, accounts. |
| Producer | Productor/a | Supplies products and manages its own catalog. Has exactly one account. |
| Account | Compte | One login (unique email) with the manager and producer roles it holds, and a household link that makes it a member. |
| Product | Producte | Sold by a producer, with a unit (kg, unit, bunch…) and a unit price (≥ 0). |
| Variable product | Producte de pes variable | Quantity can be fractional (weight); delivered quantity may differ from ordered. |
| Cycle | Cicle | One week of ordering, identified by its pickup date. *Closed* once every pickup is done (R24b). |
| Participating producer | Productor/a actiu al cicle | A producer a manager has made active in a cycle. Only they can be ordered from in it. |
| Cutoff | Tancament | Moment after which a producer's products can no longer be ordered in a cycle. Optional. |
| Producer order | Comanda al productor | Everything households ordered from one producer in one cycle. |
| Sent | Enviada | A producer order a manager has sent to the producer. It can no longer change. |
| Order | Comanda | A household's order in one cycle. One per household per cycle. |
| Order line | Línia de comanda | Product + ordered quantity + snapshot of the product (name, unit, variable flag, price). |
| Reception | Recepció | Check of one producer's delivery in one cycle: received vs ordered, and allocation to households. |
| Allocation | Assignació | Quantity of a product a household will take in a cycle. Starts equal to the ordered quantity. |
| Validated | Validada | A reception whose received totals a manager has checked against what arrived. |
| Distributed | Distribuïda | A producer order whose reception is finished and balanced. |
| Settled | Liquidat | A cycle whose every participating producer is distributed. Pickups start. |
| Stock product | Producte d'estoc | Product kept at the cooperative's premises, with tracked quantity, sold at pickup. |
| Container | Envàs | Reusable packaging a product comes in (e.g. a glass jar, a bottle). Its cost is part of the product's price. |
| Return item | Retorn | A container the cooperative takes back at pickup, credited at a fixed amount. |
| Pickup | Recollida | A household collects its allocation and stock products, and is charged. |
| Wallet | Moneder | Household balance: top-ups plus credits minus charges ± corrections. May be negative. |
| Top-up | Recàrrega | Money added to a wallet, recorded by a manager. |

## Time

Every date and time in this spec (pickup dates, cutoffs, "Wednesday 20:00") is local time in the
cooperative's timezone. On daylight-saving days, a local time that does not exist moves forward by the
gap (02:30 becomes 03:30), and a local time that happens twice means its first occurrence.

Every recorded movement (wallet, stock) is dated at the moment it is recorded; that moment orders history
and defines a balance on a given day.

## Roles

An account holds the manager and producer roles it was given, and is a member of the household it is linked to.
Member is not a role someone is given: an account is a member of household H exactly when it is linked to H (R35).
An account with no household and no other role cannot log in until it is linked.

- **Member** (linked to a household): orders for their household, sees their household's orders, pickups, balance and movements.
- **Producer**: manages their own products and default cutoff, sees the sent totals of their products.
- **Manager**: everything else — cycles, participation, cutoffs, sending, reception, pickup, stock,
  top-ups, households and accounts. A manager never edits a household's order lines.

## Areas

Requirements (`R#`) and acceptance scenarios (`S#`) live in one file per area; numbers are global.

| File | Requirements |
| --- | --- |
| [ordering.md](ordering.md) | R1–R13 |
| [reception.md](reception.md) | R14–R18a |
| [pickup.md](pickup.md) | R19–R24 |
| [stock-wallet.md](stock-wallet.md) | R25–R36 |

## Out of scope (for now)

A cooperative fee on product prices, work shifts, selling stock outside a pickup, notifications other than
sending an order to a producer (R12a), producer confirmation of an order, minimum order per producer,
invoicing and payments to producers, returning containers to their producers. Each is a future slice in
the [roadmap](../README.md#roadmap).
