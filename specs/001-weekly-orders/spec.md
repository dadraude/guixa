# 001 — Weekly orders

Status: Accepted (2026-10-19) — includes the amendment for producer reception, stock and top-up at pickup.
Revised (2026-10-20): OQ1–OQ18 resolved and the gap review of the revision applied (fixed pickup weekday,
late deliveries, per-cycle product availability, account deactivation, validated receptions). OQ19 resolved
(2026-10-20): containers per unit on products and stock products, counted per household and cycle (R1, R10, R18a,
R20, R25).
Revised and accepted (2026-10-28): every product belongs to a producer and is either weekly or stock;
Sales baskets separate actual purchases, validation and physical collection from weekly demand.
Finance operates baskets and manual receipts; retained history establishes first use. Requirements
R1–R36c and scenarios S1–S92 (S90 retired) include this amendment. Revised (2026-11-06): R28a and S93 add the stock movement history (T76). The amended plan and [task breakdown](tasks.md) are accepted (2026-10-28).

Households order products from local producers every week, with a planned pickup date. After each
producer closes, a manager sends it the order, checks the delivery and allocates goods to households.
Once distribution settles, each household order produces a basket draft. Finance or a manager checks
actual quantities and validates the basket, charging the household wallet and selling any stock in one
operation. Physical collection is recorded separately and can happen later, including after cycle closure.
Additional stock baskets can be created at any time, without a weekly order or cycle. Confirmed money received
is recorded manually as a separate credit to the household's global wallet.

## Glossary

| Term | Català | Meaning |
| --- | --- | --- |
| Household | Unitat de convivència | Ordering and paying unit. Has members; inactive once it leaves or loses its last member. |
| Member | Soci/sòcia | Person who belongs to at most one household; only with a household can they order. |
| Manager | Gestor/a | Runs the cooperative and retains all Finance and Catalogue powers, alongside cycle, reception, catalogue, inventory and account administration. |
| Finance | Econòmiques | Staff who prepare and validate baskets, record receipts, make justified corrections and run receptions ([003](../003-role-management/spec.md) R7). |
| Producer | Productor/a | Grows, gathers or makes the products it supplies. Manages its weekly products and can consult its stock products. Has at most one account. |
| Account | Compte | One login (unique email) with the manager, finance, catalogue, receiver and producer roles it holds, and a household link that makes it a member. |
| Product | Producte | Belongs to exactly one producer for its lifetime, with a unit (kg, unit, bunch…) and a unit price (≥ 0). Either a weekly product or a stock product. |
| Weekly product | Producte de comanda setmanal | Product offered through weekly ordering and received and allocated in a cycle. Not a stock product. |
| Variable product | Producte de pes variable | Quantity can be fractional (weight); delivered quantity may differ from ordered. |
| Cycle | Cicle | One week of ordering, identified by its planned pickup date. Closed once distribution is finished and all base and late weekly baskets are validated or have nothing to collect (R24b). |
| Participating producer | Productor/a actiu al cicle | A producer a manager has made active in a cycle. Only they can be ordered from in it. |
| Cutoff | Tancament | Moment after which a producer's products can no longer be ordered in a cycle. Optional. |
| Producer order | Comanda al productor | Everything households ordered from one producer in one cycle. |
| Sent | Enviada | A producer order a manager has sent to the producer. It can no longer change. |
| Order | Comanda | A household's order in one cycle. One per household per cycle. |
| Order line | Línia de comanda | Weekly product, ordered and allocated quantities, and its product snapshot. Removed lines remain as history. |
| Basket | Cistella | A household's actual purchase contents, either a base or late weekly basket from a weekly order, or an additional stock purchase with an optional cycle reference. |
| Validated basket | Cistella validada | Basket whose actual contents and total have been confirmed, with its charge and stock sale recorded. Original records remain immutable. |
| Reception | Recepció | Check of one producer's delivery in one cycle: received vs ordered, and allocation to households. |
| Allocation | Assignació | Quantity of a product a household will take in a cycle. Starts equal to the ordered quantity. |
| Validated reception | Recepció validada | A reception whose received totals Finance or a manager has checked against what arrived ([003](../003-role-management/spec.md) R7). Distinct from basket validation. |
| Distributed | Distribuïda | A producer order whose reception is finished and balanced. |
| Settled | Liquidat | A cycle whose every participating producer is distributed. Weekly base basket drafts are created. |
| Stock product | Producte d'estoc | Product marked for sale from tracked stock in baskets, during or outside weekly pickups. Excluded from weekly ordering and reception. |
| Container | Envàs | Reusable packaging a product comes in (e.g. a glass jar, a bottle). Its cost is part of the product's price. |
| Return item | Retorn | A container the cooperative takes back, credited at a fixed snapshotted amount in a basket. |
| Pickup | Recollida | Physical collection of a validated basket, recorded with who marked it and when. It makes no new charge or stock sale. |
| Wallet | Moneder | Household balance: top-ups plus credits minus charges ± corrections. May be negative. |
| Top-up / receipt | Recàrrega / cobrament | Confirmed money received and added to the household wallet by Finance or a manager. Not allocated to a particular basket. |

## Time

Every date and time in this spec (pickup dates, cutoffs, "Wednesday 20:00") is local time in the
cooperative's timezone. On daylight-saving days, a local time that does not exist moves forward by the
gap (02:30 becomes 03:30), and a local time that happens twice means its first occurrence.

Every recorded movement (wallet, stock) is dated at the moment it is recorded; that moment orders history
and defines a balance on a given day. Basket creation, validation, collection marks and corrections also
record their application-action instant and actor. A late notice can explain an earlier handover in a
note; a late notice never backdates a charge, movement or collection mark.

## Roles

An account holds the manager, finance and producer roles it was given, and is a member of the household it is linked to.
Member is not a role someone is given: an account is a member of household H exactly when it is linked to H (R35).
An account with no household and no other role cannot log in until it is linked.

- **Member** (linked to a household): orders for their household; sees its baskets, balance and movements;
  consults the stock catalogue; and can mark its validated baskets collected. Cannot create/edit or validate
  basket contents, record receipts or act on another household's baskets.
- **Producer**: manages their own weekly products and default cutoff, sees their sent weekly totals, and
  consults their own stock products, prices and available quantities without modifying them.
- **Finance**: prepares basket drafts, adjusts actual quantities, adds stock/returns, validates baskets,
  records confirmed receipts and justified corrections, and records collection. This grants no catalogue,
  cycle/reception, account or general inventory administration.
- **Manager**: retains all Finance powers and the existing cycle, reception, stock/return catalogue,
  inventory, product-classification, household and account powers. A manager never edits a household's
  ordered quantities. Only a manager grants or revokes Finance.

[003 — Role management](../003-role-management/spec.md) adds the Catalogue and Receiver roles, lets Finance
run receptions and lets Catalogue and the manager edit any producer's weekly products. Where it differs
from R1, R1b, R1c, R2, R3c, R14–R18, R20a, R25, R25b, R28a, R36a, R36b or R36c, 003 applies.

## Areas

Requirements (`R#`) and acceptance scenarios (`S#`) live in one file per area; numbers are global.

| File | Requirements |
| --- | --- |
| [ordering.md](ordering.md) | R1–R13 |
| [reception.md](reception.md) | R14–R18a |
| [pickup.md](pickup.md) — baskets, validation and collection | R19–R24b |
| [stock-wallet.md](stock-wallet.md) | R25–R36c |

[diagrams.md](diagrams.md) draws the amended contexts, model relations and weekly/independent sales flow.
The requirements in these areas define behaviour; [plan.md](plan.md) records the accepted design.

## Dashboard (interim)

This describes what the dashboard shows today, not a requirement of 001; a new dashboard spec will
replace it. Every account sees the Dashboard page; the cards depend on its roles:

- **Member with a household:** the next pickup date (first non-cancelled cycle of the household, or
  none), the household's wallet balance, the number of open cycles, and the household's validated
  baskets pending collection (R23a, R24) with a link to all of them (shown only when there are some).
- **Manager or Finance:** the number of baskets pending collection, linking to the pending list (shown only when there are some).
- **Manager:** a cooperative summary with the totals of cycles, households and accounts.
- **Member, producer and manager:** shortcuts to the areas the account can use (orders and wallet, production,
  administration).

## Out of scope (for now)

A cooperative fee on product prices, work shifts, notifications other than sending an order to a
producer (R12a), producer confirmation of an order, minimum order per producer, invoicing and payments
to producers, returning containers to their producers, configurable commissions and online payment
processing. Each relevant future feature is tracked in
the [roadmap](../README.md#roadmap).
