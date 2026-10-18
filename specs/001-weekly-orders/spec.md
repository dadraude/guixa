# 001 — Weekly orders

Status: Draft — amendment in review (2026-10-19): producer reception, stock and top-up at pickup.

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

## Roles

- **Member**: orders for their household, sees their household's orders, balance and movements.
- **Producer**: manages their own products and default cutoff, sees aggregated quantities of their products.
- **Manager**: everything else — cycles, cutoff overrides, reception, pickup, stock, top-ups, households and members.

## Requirements

### Catalog

- **R1** A producer creates, edits and deactivates their own products: name, unit, unit price, variable flag.
- **R2** A producer cannot see or edit another producer's products.
- **R3** Only active products of active producers can be ordered.

### Cycles and cutoffs

- **R4** A manager opens a cycle by giving its pickup date. Only one cycle per pickup date.
  A new cycle can be opened while previous cycles are still pending pickup; each pickup belongs
  to its own cycle and charges only that cycle's order.
- **R5** Each producer has a default cutoff, expressed relative to the pickup date
  (e.g. "3 days before, at 20:00"). Opening a cycle computes every producer's cutoff from it.
- **R6** A manager can override a producer's cutoff in a specific cycle. The override never moves
  past the pickup date.

### Ordering

- **R7** Any member of a household can add, change or remove lines in the household's order for an open cycle.
  All members see and edit the same order.
- **R8** A line can only be added, changed or removed before its producer's cutoff in that cycle.
  After the cutoff, lines of that producer are read-only; lines of producers still open stay editable.
- **R9** Ordered quantity must be greater than zero. Setting it to zero removes the line.
  Non-variable products are ordered in whole units.
- **R10** A line keeps the unit price the product had when the line was last changed.
  Later price changes do not affect it.
- **R11** Ordering is allowed regardless of wallet balance.

### Aggregation

- **R12** After a producer's cutoff, the producer sees the total quantity per product for that cycle,
  without household names. Before the cutoff, the producer sees nothing for that cycle.
- **R13** A manager sees, per cycle, totals per producer and product and the breakdown per household.

### Reception (once per producer and cycle)

- **R14** After a producer's cutoff, a manager opens that producer's reception for the cycle. For each
  product it shows the ordered total, the received total (initially equal to the ordered total) and the
  allocation of every household that ordered it (initially equal to its ordered quantity).
- **R15** **Way A — change the received total.** Setting a product's received total re-allocates it among
  the households that ordered it and have not picked up yet, proportionally to their ordered quantity.
  Non-variable products are allocated in whole units; variable products to 0.001. Units left over after
  rounding down go one by one to the largest remainders; on a tie, to the household that ordered first.
  *(tie rule: assumption)*
- **R16** **Way B — edit allocations.** A manager sets any household's allocation directly (≥ 0).
  This does not change the received total.
- **R17** For each product, the reception shows the difference between the received total and the sum of
  allocations. A product is *balanced* when the difference is 0. Unbalanced products are flagged
  but never block a pickup.

### Pickup (once per household and cycle)

- **R18** A manager opens a household's pickup for a cycle, whether or not the household has an order.
  Each order line shows its allocation as the delivered quantity, which the manager can still adjust (≥ 0),
  e.g. the real weight of a package or a damaged item. The adjustment updates the allocation, so it
  is reflected in the reception balance (R17).
- **R19** The manager can add stock products to the pickup, each with a quantity. A stock line takes the
  stock product's current price. The quantity cannot exceed the available stock.
- **R20** The pickup shows the household's current balance, the total to charge and the resulting balance.
  The manager can include a top-up in the same pickup (amount > 0, optional note), e.g. when the household
  pays in cash or shows a transfer.
- **R21** Confirming the pickup, all at once or nothing:
  charges the wallet with Σ(delivered × unit price) of order lines + Σ(quantity × price) of stock lines,
  each line rounded to the nearest cent (half up), so the lines shown add up to the charge;
  decreases stock by the stock lines; records the top-up, if any; and freezes the pickup.
- **R22** A pickup can be confirmed only once. A confirmed pickup and its order cannot be edited.
- **R23** A pickup with nothing to charge and no top-up cannot be confirmed; nothing is recorded.

### Stock

- **R24** A manager creates, edits and deactivates stock products: name, unit, price, variable flag.
- **R25** A manager records stock entries (quantity > 0, optional note, e.g. "bought from X").
- **R26** A manager records stock adjustments (positive or negative, mandatory note, e.g. "broken jar",
  "inventory count"). Available stock can never go below 0.
- **R27** Available stock = Σ entries + Σ adjustments − Σ quantities sold at confirmed pickups.
  Stock movements are never edited or deleted.

### Wallet

- **R28** A manager records top-ups, at a pickup (R20) or on their own: positive amount, date,
  optional note (e.g. "transfer", "cash").
- **R29** Balance = Σ top-ups − Σ charges ± corrections. It may be negative, with no limit.
- **R30** Members see their household balance and every movement (top-ups and charges, with cycle).
- **R31** Movements are never edited or deleted. Mistakes are fixed with a correcting movement
  recorded by a manager, with a mandatory note.

### Households and accounts

- **R32** Only a manager creates households and adds or removes their members. There is no self sign-up.
- **R33** Adding a member sends them an email invitation to set their password. Until they accept it,
  they cannot log in.
- **R34** A person belongs to at most one household. Removing a member keeps the household's orders
  and wallet movements intact.
- **R35** Producer accounts are also created only by a manager, with the same invitation flow.

## Acceptance scenarios

### Ordering

**S1 — Order before cutoff** (R7, R8)
Given a cycle with pickup on Saturday and the baker's cutoff on Wednesday 20:00
When a member of household H adds 2 loaves on Wednesday at 19:59
Then H's order has a line of 2 loaves.

**S2 — Order after cutoff** (R8)
Given the same cycle
When a member of H adds 2 loaves on Wednesday at 20:00
Then it is rejected because the baker is closed for this cycle.

**S3 — Mixed cutoffs** (R8)
Given the baker closed on Wednesday and the vegetable grower closes on Thursday
When on Thursday morning a member changes the vegetables and tries to change the bread
Then the vegetables are updated and the bread change is rejected.

**S4 — Shared household order** (R7)
Given Anna and Biel are members of household H
When Anna adds 1 kg of apples and Biel adds 2 loaves
Then both see one order for H with both lines.

**S5 — Price snapshot** (R10)
Given H ordered 1 kg of beef at €20.00/kg
When the producer raises the price to €22.00/kg
Then H's line still costs €20.00/kg.

### Cycles and visibility

**S6 — Cutoff override** (R5, R6)
Given the baker's default cutoff is 3 days before pickup at 20:00
When the manager opens a cycle with pickup on Saturday 7th
Then the baker's cutoff is Wednesday 4th at 20:00
And when the manager overrides it to Thursday 5th at 12:00, members can order bread until then.

**S7 — Producer privacy** (R2, R12)
Given the baker's cutoff has passed
When the baker opens the cycle summary
Then they see "40 loaves" and no household names, and no products from other producers.

**S8 — No aggregate before cutoff** (R12)
Given the baker's cutoff is Wednesday 20:00 and households have already ordered bread
When the baker opens the cycle summary on Wednesday at 19:59
Then they see that the cycle is still open and no quantities.

**S9 — Overlapping cycles** (R4, R21)
Given cycle A (pickup Saturday 7th) is closed for every producer and not yet picked up
When the manager opens cycle B (pickup Saturday 14th) and H orders in B
Then confirming H's pickup for A charges only A's lines, and H's order in B stays editable.

### Reception

**S10 — Proportional shortage** (R14, R15, R17)
Given households A, B and C ordered 4, 6 and 10 loaves (B before C), 20 in total
When the manager sets the received total to 15
Then the allocations are A 3, B 5, C 7 and the product is balanced.
(3 + 4.5 + 7.5 → 3 + 4 + 7 = 14; the leftover unit goes to B, tied with C but ordered first.)

**S11 — Proportional surplus on a variable product** (R15)
Given A and B ordered 2 kg of apples each
When the manager sets the received total to 5 kg
Then A and B are allocated 2.500 kg each.

**S12 — Manual allocation** (R16, R17)
Given the allocations of S10 (A 3, B 5, C 7, received 15)
When the manager sets C's allocation to 6
Then the received total stays 15 and the product is flagged as unbalanced by +1.

### Pickup

**S13 — Variable weight at pickup** (R18, R21)
Given H was allocated 1 kg of beef at €20.00/kg and its wallet is at €50.00
When the manager records 1.120 kg at H's pickup and confirms
Then H is charged €22.40, the balance is €27.60
And the beef reception now shows an unbalanced difference of −0.120 kg.

**S14 — Allocation drives the charge** (R15, R21)
Given A ordered 4 loaves at €3.00 and was allocated 3 in S10
When A's pickup is confirmed without changes
Then A is charged €9.00.

**S15 — Adding stock** (R19, R21, R27)
Given H's order lines total €20.00, honey is a stock product at €6.00 with 5 jars available
When the manager adds 2 jars of honey and confirms
Then H is charged €32.00 and 3 jars of honey remain.

**S16 — Not enough stock** (R19)
Given 1 jar of honey is available
When the manager adds 2 jars to a pickup
Then it is rejected and stock is unchanged.

**S17 — Stock-only pickup** (R18, R19)
Given household K has no order in the cycle
When the manager opens K's pickup, adds 1 bottle of oil at €12.00 and confirms
Then K is charged €12.00.

**S18 — Top-up at pickup** (R20, R21, R28)
Given H's balance is €5.00 and its pickup totals €30.00
When the manager adds a €40.00 cash top-up and confirms
Then H's balance is €15.00 and H sees two movements: a €40.00 top-up and a €30.00 charge.

**S19 — Nothing to confirm** (R23)
Given household K has no order, no stock lines and no top-up
When the manager tries to confirm K's pickup
Then it is rejected and nothing is recorded.

**S20 — Negative balance** (R11, R29)
Given H's wallet is at €5.00
When H's pickup of €30.00 is confirmed with no top-up
Then the balance is −€25.00.

**S21 — Double confirmation** (R22)
Given H's pickup was confirmed
When the manager tries to confirm it again
Then it is rejected and neither the wallet nor the stock change.

### Stock

**S22 — Entries and adjustments** (R25, R26, R27)
Given honey has 0 jars
When the manager records an entry of 10 and an adjustment of −1 noted "broken jar"
Then 9 jars are available; an adjustment of −10 is rejected.

### Wallet and accounts

**S23 — Top-up and correction** (R28, R31)
Given H's balance is €0.00
When the manager records a €50.00 top-up and then a −€50.00 correction noted "duplicated"
Then H's balance is €0.00 and both movements are visible to H.

**S24 — Member invitation** (R32, R33)
Given the manager creates household H and adds anna@example.org as a member
When Anna opens the invitation link and sets a password
Then she can log in and sees H's order and wallet; before accepting, login is rejected.

## Out of scope (for now)

Online payments, membership fees, work shifts, selling stock outside a pickup, notifications,
minimum order per producer, invoicing and payments to producers.

## Open questions

- **Q1** What happens to the allocation of a household that never picks up in a cycle? (e.g. it stays
  pending, is charged anyway, or is released so the manager can re-allocate it)
