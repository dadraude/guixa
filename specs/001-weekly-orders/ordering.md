# 001 — Weekly orders: Ordering

Part of [001 — Weekly orders](spec.md): glossary, time and roles are defined there.

Catalog, cycles and cutoffs, ordering, sending orders to producers and what producers and managers see of it.

## Requirements

### Catalog

- **R1** A producer creates, edits and deactivates their own products: name, unit, unit price (≥ 0),
  variable flag, and whether it comes with containers. When it does, the producer sets how many containers
  go with one unit (> 0, in multiples of 0.001; e.g. 2 per kg for 0.500 kg jars). While a product has lines
  in a cycle that is not settled, its unit and variable flag cannot change; its name, price and containers
  can.
- **R2** A producer cannot see or edit another producer's products.
- **R3** Only products that are active and available in the cycle (R3c), of producers participating in
  it, can be ordered.
- **R3a** Only a manager deactivates or reactivates a producer. A deactivated producer loses the producer
  role: the account keeps its other roles and can still log in, and an account with no other role cannot.
  Reactivating restores the role. A deactivated producer cannot be made to participate in a cycle, and in
  every cycle where its order is not sent it stops participating (R6c).
- **R3b** While a producer order is not sent, a line whose product is not orderable in its cycle (the
  product is deactivated, or unavailable in the cycle) stays in the order, marked *will not be delivered*.
  Members see the mark and can remove the line. The mark disappears if the product becomes orderable
  again before the order is sent; a line a member already removed does not come back. Marked lines are
  never sent to the producer (R12a), never received and never charged.
- **R3c** Each active product is available in every cycle by default. While its producer order is not sent,
  the producer can mark a product unavailable in a cycle and available again; it cannot be newly ordered
  in that cycle while unavailable.

### Cycles and cutoffs

- **R4** A manager opens a cycle by giving a pickup date after today, on the cooperative's pickup weekday
  (R4c). Only one cycle per pickup date, not counting cancelled ones (R4b). A cycle's pickup date never
  changes.
  There is no limit to how many cycles are open or how far ahead.
  A new cycle can be opened while previous cycles are still pending pickup; each pickup belongs
  to its own cycle and charges only that cycle's order.
- **R4a** When opening a cycle, the manager picks which active producers participate. Until the cycle is
  settled (R18), a manager can make more producers participate. A cycle is *open* for members while at
  least one participating producer in it is open (R8).
- **R4b** A manager can cancel a cycle while no producer order of the cycle has been sent. Its orders are
  discarded (nothing was charged) and members see the cycle as cancelled. Once an order is sent,
  cancelling is handled with the producer outside the app. A holiday on the pickup weekday is handled by
  cancelling the cycle.
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
- **R9** Ordered quantity must be greater than zero. Setting it to zero removes the line.
  Non-variable products are ordered in whole units; variable products in multiples of 0.001, with no other
  minimum or step.
- **R10** A line keeps a snapshot of the product as it was when the line was last changed: name, unit,
  variable flag, unit price and containers per unit. Later changes to the product do not affect it.
- **R11** Ordering is allowed regardless of wallet balance.

### Sending and aggregation

- **R12a** **Sending.** Once a participating producer is closed, a manager sends its order. The producer
  is notified with the total quantity per product, without household names. Lines marked *will not be
  delivered* are left out. A producer order can be sent only once.
  A participating producer that closes with no line to send is sent, validated and distributed
  automatically; nothing is notified. A cycle with no participating producer never settles: a manager
  adds producers or cancels it (R4b).
- **R12** A producer sees the total quantity per product of a cycle only once its order is sent,
  without household names. Before that, the producer sees nothing for that cycle.
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

**S9 — Overlapping cycles** (R4, R22)
Given cycle A (pickup Saturday 7th) is settled and H has not picked up yet
When the manager opens cycle B (pickup Saturday 14th) and H orders in B
Then completing H's pickup for A charges only A's lines, and H's order in B stays editable.

**S37 — Cancel a cycle, fixed pickup weekday** (R4, R4b, R4c)
Given the pickup weekday is Saturday and cycle A (pickup Saturday 7th) has orders and no producer order sent
When the manager cancels cycle A
Then its orders are discarded and members see it as cancelled
And once one producer order is sent, cancelling is rejected
And opening a cycle with pickup on Friday 13th is rejected.

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
