# 001 — Weekly orders: Ordering

Part of [001 — Weekly orders](spec.md): glossary, time and roles are defined there.

Catalog, cycles and cutoffs, ordering, sending orders to producers and what producers and managers see of it.

## Requirements

### Catalog

- **R1** A producer creates, edits and deactivates their own weekly products: name, unit, unit price (≥ 0),
  variable flag, and whether it comes with containers. When it does, the producer sets how many containers
  go with one unit (> 0, in multiples of 0.001; e.g. 2 per kg for 0.500 kg jars). While a product has lines
  in a cycle that is not settled, its unit and variable flag cannot change; its name, price and containers
  can.
- **R1a** **Producer and classification.** Every product is created from exactly one producer's record,
  whether it is created by that producer (weekly products, R1) or by a manager (stock products, R25).
  Its producer can never change. Each product has a stock flag: marked means stock, unmarked means
  weekly. The classification applies across all cycles. The same article sold through both circuits
  needs two distinct product records, each with its own classification.
- **R1b** **First use.** A product has been used once it has appeared in an order, reception or basket,
  or has any stock movement. Removing lines or cancelling their order, basket or cycle never undoes
  first use; retained draft references count too (R19b).
  Only a manager can change the stock flag, and only before first use. After a permitted change,
  administration follows the new classification: the producer administers a weekly product (R1),
  the manager a stock product (R25). A producer cannot change the stock flag.
- **R1c** **Deletion and replacement.** Whoever administers a product under its current classification
  (R1, R25) can delete it only if it has never been used (R1b). Once used, it cannot be deleted;
  deactivation preserves its references and history. Other edits remain governed by R1 and R25a.
  A wrong producer is corrected by creating a new product
  from the right producer's record, then deactivating the old product or deleting it if unused.
- **R2** Acting as a producer, an account cannot see or edit another producer's products. Its own
  stock products are read-only (R25b). An account also linked to a household can consult the
  cooperative's stock catalogue as a member (R25c); a manager administers stock products (R25).
- **R3** Only weekly products that are active and available in the cycle (R3c), of producers
  participating in it, can be ordered. Stock products never appear in the weekly order selector,
  and attempts to add them to an order are rejected even if their producer participates.
- **R3a** Only a manager deactivates or reactivates a producer. A deactivated producer loses the producer
  role: the account keeps its other roles and can still log in, and an account with no other role cannot.
  Reactivating restores the role. A deactivated producer cannot be made to participate in a cycle, and in
  every cycle where its order is not sent it stops participating (R6c). This does not deactivate
  its stock products: the manager can still sell their remaining stock while the product is active
  (R20).
- **R3b** While a producer order is not sent, a line whose product is not orderable in its cycle (the
  product is deactivated, or unavailable in the cycle) stays in the order, marked *will not be delivered*.
  Members see the mark and can remove the line. The mark disappears if the product becomes orderable
  again before the order is sent; a line a member already removed does not come back. Marked lines are
  never sent to the producer (R12a), never received and never charged.
- **R3c** Each active weekly product is available in every cycle by default. While its producer order is not sent,
  the producer can mark a product unavailable in a cycle and available again; it cannot be newly ordered
  in that cycle while unavailable. This per-cycle availability does not apply to stock products.

### Cycles and cutoffs

- **R4** A manager opens a cycle by giving a pickup date after today, on the cooperative's pickup weekday
  (R4c). Only one cycle per pickup date, not counting cancelled ones (R4b). A cycle's pickup date never
  changes.
  There is no limit to how many cycles are open or how far ahead.
  A new cycle can be opened while earlier baskets are pending physical collection. A weekly base
  basket uses its own source order plus any stock/returns added to that basket; validation charges
  that basket (R22), while collection has no financial effect (R23b). Independent stock baskets
  need no cycle (R19).
- **R4a** When opening a cycle, the manager picks which active producers participate. Until the cycle is
  settled (R18), a manager can make more producers participate. A cycle is *open* for members while at
  least one participating producer in it is open (R8).
- **R4b** A manager can cancel a cycle while no producer order of the cycle has been sent. Its weekly
  orders leave current contents/totals and members see the cycle as cancelled; retain the cancelled
  orders/lines as history (R19b), with no weekly basket charge. Independent stock baskets and their
  posted movements remain unchanged even if they reference that cycle. Once an order is sent,
  cancelling is handled with the producer outside the app. A holiday on the pickup weekday is handled
  by cancelling the cycle.
- **R4c** The cooperative has one pickup weekday, set by a manager; until one is set there is none. A
  cycle cannot be opened without a weekday: opening a cycle when the cooperative has none asks the
  manager for it, and on confirming they choose whether it is permanent (it becomes the cooperative's
  weekday) or provisional (it applies only to this cycle; the cooperative stays without one and the
  next opening asks again). Changing it affects only cycles opened afterwards; cycles already open keep
  their pickup date.
- **R5** Each producer has a default cutoff, expressed relative to the pickup date: 1 to 6 days before,
  at a time (e.g. "3 days before, at 20:00"). When a producer starts participating, its cutoff in the cycle
  is computed from it once and never recomputed: changing the default affects only the cycles
  where the producer starts participating afterwards. If that moment has already passed, the producer
  starts with no cutoff.
- **R6** While a producer order is not sent, a manager can, in that cycle:
  - set or move its cutoff to any future moment from 6 days to 1 day before the pickup date
    (on the pickup date itself is never allowed), even if the current cutoff has already passed;
  - close it now: its cutoff becomes the current moment;
  - clear its cutoff: the producer stays open until a manager closes it.
- **R6a** A producer with a cutoff is open until that moment; one with no cutoff is open until 00:00 of the
  pickup date. No producer is open on the pickup date or after it.
- **R6b** Once a producer order is sent, its cutoff can no longer change.
- **R6c** A manager can make a producer stop participating in a cycle while its order is not sent, and make
  it participate again under the same condition. While it does not participate, its lines are marked
  *will not be delivered* like in R3b, and its order cannot be sent.

### Ordering

- **R7** Any member of a household can add, change or remove lines in the household's order for an open cycle.
  All members see and edit the same order. A member without a household cannot order.
- **R8** A line can only be added, changed or removed while its producer is open in that cycle (R6a).
  After the cutoff, lines of that producer are read-only; lines of producers still open stay editable.
  The only exception is removing a line marked *will not be delivered* (R3b, R6c).
- **R9** Ordered quantity must be greater than zero. Setting it to zero removes the line from current
  contents but retains its history (R19b).
  Non-variable products are ordered in whole units; variable products in multiples of 0.001, with no other
  minimum or step.
- **R10** A line keeps a snapshot of the product as it was when the line was last changed: name, unit,
  variable flag, unit price and containers per unit. Later changes to the product do not affect it.
- **R11** Ordering is allowed regardless of wallet balance.

### Sending and aggregation

- **R12a** **Sending.** Once a participating producer is closed, a manager sends its order. The producer
  is notified with the total quantity per product, without household names. Lines marked *will not be
  delivered* are left out. A producer order can be sent only once, and not when the producer has no
  account to notify.
  A participating producer that closes with no line to send is sent, validated and distributed
  automatically; nothing is notified. A cycle with no participating producer never settles: a manager
  adds producers or cancels it (R4b).
- **R12** A producer sees the total quantity per product of a cycle only once its order is sent,
  without household names. Before that, the producer sees nothing for that cycle.
- **R13** A manager sees, per cycle, totals per producer and product and the breakdown per household.
  A cancelled cycle still opens and is shown as cancelled, with no totals (its orders are retained as history, R4b).

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

**S5 — Product snapshot** (R1, R10)
Given H ordered 1 kg of beef at €20.00/kg, named "Beef"
When the producer raises the price to €22.00/kg and renames it "Beef steak"
Then H's line still shows "Beef" at €20.00/kg
And the producer cannot change the beef's unit or variable flag until the cycle is settled.

**S32 — Deactivated product** (R3b, R12a)
Given H ordered 2 jars of jam and the jam producer is still open
When the producer deactivates the jam
Then H's line is marked *will not be delivered*, H can remove it, and if kept it is not in the sent order.

**S48 — Unavailable in one cycle** (R3b, R3c)
Given H ordered 2 jars of jam in cycles A and B
When the producer marks the jam unavailable in cycle A only
Then H's line in A is marked *will not be delivered* and the one in B is untouched
And if the producer marks the jam available in A again before the order is sent, the mark disappears.

**S33 — Member without household** (R7, R35)
Given Anna's account was unlinked from H
When Anna tries to order in an open cycle
Then it is rejected; she can still log in.

**S49 — Quantity rules** (R9)
Given bread is a non-variable product and apples a variable one
When a member adds 0.5 loaf, then 0.250 kg of apples, then 0.0005 kg of apples
Then the 0.5 loaf and the 0.0005 kg are rejected and the 0.250 kg line is added
And setting the apples to 0 removes the line.

### Cycles and visibility

**S6 — Cutoff override** (R5, R6)
Given the baker's default cutoff is 3 days before pickup at 20:00
When the manager opens a cycle with pickup on Saturday 7th with the baker participating
Then the baker's cutoff is Wednesday 4th at 20:00
And when the manager moves it to Thursday 5th at 12:00, members can order bread until then
And moving it to Saturday 7th at 08:00, or to any moment 7 or more days before, is rejected.

**S34 — Reopening a passed cutoff** (R6, R6b)
Given the baker's cutoff on Wednesday 4th at 20:00 has passed and its order is not sent
When on Thursday 5th at 09:00 the manager moves it to Thursday 5th at 12:00
Then members can order bread again until 12:00
And once the baker's order is sent, moving its cutoff is rejected.

**S35 — Close now and no cutoff** (R6, R6a)
Given the cheesemaker participates with no cutoff
When members order cheese on any day before Saturday 7th and then the manager closes it now
Then the cheesemaker is closed from that moment
And had the manager not closed it, it would close on Saturday 7th at 00:00.

**S7 — Producer privacy** (R2, R12)
Given the baker's order has been sent
When the baker opens the cycle summary
Then they see "40 loaves" and no household names, and no products from other producers.

**S8 — No aggregate before sending** (R12)
Given the baker's cutoff has passed but its order is not sent
When the baker opens the cycle summary
Then they see no quantities for that cycle.

**S36 — Sending needs a closed participating producer** (R6c, R12a)
Given the vegetable grower is still open, and the baker is closed but no longer participates
When the manager tries to send either order
Then both are rejected.

**S9 — Overlapping cycles** (R4, R19, R22, R23b)
Given cycle A is settled with H's basket still draft, and cycle B is open with H's weekly order editable
When Finance validates H's basket for A and a member later marks it collected
Then the charge uses only A's basket contents, collection records no extra charge, and B stays editable.

**S37 — Cancel a cycle, fixed pickup weekday** (R4, R4b, R4c, R19b)
Given the pickup weekday is Saturday and cycle A has weekly orders but no producer order sent
When the manager cancels cycle A
Then its orders/lines are absent from current contents and totals, retained as history, and members see cancellation
And independent stock baskets/charges remain unchanged even if attributed to A
And once a producer order is sent, cancellation is rejected, as is opening a cycle on Friday.

**S38 — Daylight saving** (Time, R5)
Given the baker's default cutoff is "1 day before, at 02:30"
When a cycle has pickup on the day after clocks go forward at 02:00
Then the baker's cutoff is 03:30 on the day clocks change.

**S50 — Deactivating a producer** (R3a, R6c)
Given Jordi is a producer with no other role and participates in cycle A (order not sent) and cycle B
(order sent)
When the manager deactivates Jordi
Then Jordi cannot log in and cannot be made to participate in a new cycle
And Jordi stops participating in A, whose lines are marked *will not be delivered*, while B is unchanged
And reactivating Jordi restores the producer role.

**S51 — Participation per cycle** (R4a, R7)
Given the manager opens a cycle with only the baker participating
When a member tries to add vegetables
Then it is rejected because the grower does not participate
And once the manager makes the grower participate, the member can order vegetables
And the cycle is open for members until both the baker and the grower are closed.

**S52 — Manager totals** (R13)
Given households A and B ordered 2 and 3 loaves from the baker
When the manager opens the cycle
Then they see 5 loaves for the baker, with A 2 and B 3.

**S62 — Totals of a cancelled cycle** (R4b, R13)
Given cycle A had orders and the manager cancelled it
When the manager opens cycle A
Then they see it as cancelled, with no totals and no households.

### Product identity and classification

**S65 — A product stays with its producer** (R1a, R1c, R25)
Given the manager creates stock honey from the beekeeper's record
When the manager tries to create a product without a producer or move that honey to another producer
Then both are rejected, even if the honey has never been used
And correcting the producer requires a new product from the right producer's record.

**S66 — Separate weekly and stock records** (R1a, R3, R25c)
Given the oil producer participates in an open cycle and has two active oil products,
one weekly and one stock, with the same name and price
When a member orders the weekly oil and tries to order the stock oil
Then only the weekly oil is added; the stock oil is absent from the weekly selector and rejected if submitted
And the stock oil remains visible in the informational stock catalogue.

**S67 — Only the manager changes an unused classification** (R1, R1b, R25, R25b)
Given the beekeeper has an unused weekly honey product
When the beekeeper tries to mark it as stock
Then it is rejected
And when the manager marks it as stock, the manager administers it and the beekeeper can only consult it
And the manager can switch it back while it is still unused, restoring producer administration.

**S68 — First use is not undone by removing lines** (R1b, R1c)
Given weekly bread once had an order line that was removed, stock honey has an entry and adjustment
bringing its available quantity back to zero, and stock oil once had a basket line that was removed
When the manager tries to change any of their classifications and the responsible producer or manager
tries to delete each product
Then every attempt is rejected; each product can instead be deactivated.

**S69 — Reception is a first use** (R1b, R1c, R14)
Given rye bread has been added to a reception but has no household allocations yet
When the manager tries to mark it as stock or the producer tries to delete it
Then both are rejected because the product has already been used in reception.

**S70 — Delete only unused products** (R1, R1b, R1c, R25)
Given the beekeeper has an unused weekly product and the manager has created an unused stock product
from the beekeeper's record
When the beekeeper deletes the weekly product and the manager deletes the stock product
Then both are deleted.
