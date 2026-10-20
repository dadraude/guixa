# 001 — Weekly orders: Ordering

Part of [001 — Weekly orders](spec.md): glossary, time and roles are defined there.

Catalog, cycles and cutoffs, ordering and what producers and managers see of it.

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

**S9 — Overlapping cycles** (R4, R22)
Given cycle A (pickup Saturday 7th) is distributed and H has not picked up yet
When the manager opens cycle B (pickup Saturday 14th) and H orders in B
Then confirming H's pickup for A charges only A's lines, and H's order in B stays editable.
