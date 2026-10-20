# 001 — Weekly orders

Status: Accepted (2026-10-19) — includes the amendment for producer reception, stock and top-up at pickup.
Revised (2026-10-20): OQ1–OQ18 resolved and the gap review of the revision applied (fixed pickup weekday,
late deliveries, per-cycle product availability, account deactivation, validated receptions). Reopened for
[OQ19](#open-questions): the requirements it blocks are not final.

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
| Account | Compte | One login (unique email) with one or more roles: member, manager, producer. |
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
| Return item | Retorn | Something a household brings back at pickup (e.g. a glass jar), credited at a fixed amount. |
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

An account has one or more roles.

- **Member**: orders for their household, sees their household's orders, pickups, balance and movements.
- **Producer**: manages their own products and default cutoff, sees the sent totals of their products.
- **Manager**: everything else — cycles, participation, cutoffs, sending, reception, pickup, stock,
  top-ups, households and accounts. A manager never edits a household's order lines.

## Requirements

### Catalog

- **R1** A producer creates, edits and deactivates their own products: name, unit, unit price (≥ 0),
  variable flag. While a product has lines in a cycle that is not settled, its unit and variable flag
  cannot change; its name and price can.
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
- **R4c** The cooperative has one pickup weekday (e.g. Saturday), configured by a manager. Changing it
  affects only cycles opened afterwards; cycles already open keep their pickup date.
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
  variable flag and unit price. Later changes to the product do not affect it.
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

### Reception (once per producer and cycle)

- **R14** Once a producer order is sent, a manager opens that producer's reception for the cycle. For each
  product sent it shows the ordered total, the received total (initially equal to the ordered total) and
  the allocation of every household that ordered it (initially equal to its ordered quantity).
  A manager can also add any active product of that producer that nobody ordered, with its received total
  (initially 0) and no allocations.
- **R15** **Way A — change the received total.** Setting a product's received total re-allocates it among
  all the households that ordered it, proportionally to their ordered quantity.
  The allocations always add up exactly to the received total: nothing is left over and nothing is invented.
  - Each share is rounded down to the product's step: 1 unit for non-variable products, 0.001 (1 g)
    for variable ones.
  - The steps left over after rounding down (whole units, or grams) go one by one to the households
    with the largest remainders; on a tie, to the household whose current line for that product was
    created first (removing and adding it again gives a new moment; changing its quantity does not);
    on the same moment, to the line created first by the system's record order.
  - Example: 1.000 kg among three households that ordered 1 kg each → 0.334 + 0.333 + 0.333 = 1.000 kg.
  - If any allocation of that product was set by hand (way B), the manager is warned that those manual
    allocations will be lost, and either confirms (everything is re-allocated from the ordered quantities)
    or cancels (nothing changes, not even the received total).
  - A product nobody ordered cannot use way A.
- **R16** **Way B — edit allocations.** A manager sets any active household's allocation directly
  (≥ 0, in the product's step: whole units, or multiples of 0.001 for variable products), including a
  household that did not order the product. This does not change the received total.
  An allocation to a household without a line for that product takes a snapshot of the product (R10)
  when it is set.
- **R17** For each product, the reception shows the difference between the received total and the sum of
  allocations. A product is *balanced* when the difference is 0. Unbalanced products are flagged.
- **R17a** **Validating.** After checking what arrived, a manager marks a producer's reception as
  *validated*. Changing a received total afterwards clears the validation.
- **R18** **Distributing.** A manager marks a producer order as *distributed* only when its reception is
  validated and every product of it is balanced. Its reception is then read-only. A manager can reopen a distributed producer
  order until the cycle is closed (R24b), e.g. when goods arrive late after being received as 0.
  The cycle becomes *settled* the first time every participating producer is distributed; at that moment
  lines of producers that do not participate, and lines marked *will not be delivered*, are dropped: never
  received and never charged. Reopening a producer afterwards does not stop pickups. Its new
  allocations apply to pending pickups; completed and not collected pickups keep theirs (R23), and any
  difference is fixed with a wallet correction and a stock adjustment.

### Pickup (once per household and cycle)

- **R19** Once a cycle is settled, every household with an allocation has a pending pickup for that cycle.
  A manager can also open a pickup for any active household without one (e.g. to sell stock).
  Each order line shows its allocation as the delivered quantity, which the manager can still adjust
  (≥ 0, in the same step as R16),
  e.g. the real weight of a package or a damaged item. The adjustment updates the allocation, so it is
  reflected in the reception balance (R17), where it is flagged but blocks nothing.
  A pickup stays pending until it is completed or marked not collected: it is not charged in the meantime,
  and it can be picked up after the pickup date, even once later cycles are open.
- **R20** The manager can add stock products to the pickup, each with a quantity. A stock line takes a
  snapshot of the stock product's price when the line is added. Non-variable stock products are sold in
  whole units; variable ones in multiples of 0.001. The quantity cannot exceed the available stock,
  checked when the line is added and again when the pickup is completed (R22).
  A stock line stays even if its stock product is deactivated afterwards.
- **R20a** The manager can add return items to the pickup, each with a quantity in whole units. A return line
  credits quantity × the return item's amount, snapshotted when the line is added.
- **R21** The pickup shows the household's current balance, the total to charge and the resulting balance.
  The manager can record a top-up at the same time (R29); it is an independent wallet movement.
- **R22** Completing the pickup is rejected if any stock line exceeds the available stock at that moment
  (the manager adjusts the line). Otherwise it does all at once or nothing:
  charges the wallet with Σ(delivered × unit price) of order lines + Σ(quantity × price) of stock lines
  − Σ(quantity × amount) of return lines, each line rounded to the nearest cent (half up), so the lines
  shown add up to the charge; decreases stock by the stock lines; and freezes the pickup.
  A negative total is recorded as a credit; a total of 0 records no wallet movement.
- **R23** A pickup can be completed only once. A completed pickup and its order cannot be edited.
  Mistakes are fixed with a wallet correction (R32) and a stock adjustment (R27), which can reference
  the cycle; the pickup itself stays as it was.
- **R23a** **What members see.** Before the cycle is settled, a member sees the order their household
  placed, with the *will not be delivered* marks. Once settled, they also see the pickup: per order line
  the ordered quantity, the delivered quantity, the price and the amount; the stock and return lines; and
  the total. A cancelled cycle shows as cancelled, and a closed cycle stays visible and read-only.
- **R24** A pending pickup whose every allocation is 0 and that has no stock or return lines is closed
  automatically as *nothing to collect* when the cycle is settled; nothing is recorded. If a later
  distribution gives that household an allocation > 0, its pickup becomes pending again.
- **R24a** When a household does not come, a manager marks its pickup as *not collected*: the household is
  charged its allocations as in R22 (stock and return lines are dropped), and the pickup is frozen.
- **R24b** A cycle is *closed* once it is settled and every pickup of it is completed, not collected or
  closed as nothing to collect, and every participating producer is distributed. Nothing in a closed cycle
  can change: its orders, receptions and pickups are final, and later wallet corrections or stock
  adjustments only refer to it. It leaves the pending lists.

### Stock

- **R25** A manager creates, edits and deactivates stock products: name, unit, price (≥ 0), variable flag.
  A manager creates, edits and deactivates return items: name and amount (> 0).
- **R26** A manager records stock entries (quantity > 0, optional note, e.g. "bought from X").
- **R27** A manager records stock adjustments (positive or negative, mandatory note, e.g. "broken jar",
  "inventory count", optional cycle). Available stock can never go below 0.
- **R28** Available stock = Σ entries + Σ adjustments − Σ quantities sold at completed pickups.
  Stock movements are never edited or deleted.

### Wallet

- **R29** A manager records top-ups, at a pickup (R21) or on their own: positive amount, optional note
  (e.g. "transfer of the 3rd", "cash"). A top-up is dated when recorded and is not linked to a cycle.
- **R30** Balance = Σ top-ups + Σ credits − Σ charges ± corrections. It may be negative, with no limit.
- **R31** Members see their household balance and every movement: top-ups, charges and credits with their
  cycle, corrections with their cycle if any.
- **R32** Movements are never edited or deleted. Mistakes are fixed with a correcting movement
  recorded by a manager: a non-zero amount, a mandatory note and an optional cycle.

### Households and accounts

- **R33** Only a manager creates households, renames them, and links or unlinks members. There is no
  self sign-up.
- **R34** Adding a person sends them an email invitation to set their password. Until they accept it,
  they cannot log in. An invitation expires after 7 days. While it is not accepted, a manager can resend it
  (the previous link stops working) or change its email.
- **R35** A person belongs to at most one household.
  - Moving a member links the same account to the new household. The old household keeps its orders,
    pickups, balance and history; nothing moves with the person.
  - Unlinking a member keeps the account active, without a household; it can be linked again to any
    household, the same one included.
  - Records of what a person did (e.g. who added a line) keep naming them after a move.
- **R35a** A manager can deactivate a household at any time, even with a balance or pending pickups,
  after a warning listing what is left to settle. A household is deactivated automatically when its last
  member is unlinked or moved. On deactivation: its members are unlinked and nobody can order for it; its
  existing lines and pending pickups are kept, and a manager resolves them by hand (complete, or mark not
  collected). A deactivated household with a balance other than 0, or with pending pickups, stays in a
  *to settle* list for managers until both are resolved.
  A manager can reactivate a household at any time and link members to it (R35 allows linking to any
  household, an inactive one included, which reactivates it). While a household is deactivated its wallet
  still accepts top-ups and corrections. A household created without members is active.
- **R36** A manager creates producer accounts, with the same invitation flow. Each producer has exactly
  one account.
- **R36a** One account can hold several roles (e.g. member of H and manager). A manager grants or removes the
  manager role on any account, except removing it from the last manager. The first manager is created
  when the system is installed, outside the app.
- **R36b** A manager can deactivate and reactivate any account. A deactivated account cannot log in and is
  unlinked from its household (R35); its history is kept. The last active manager's account cannot be
  deactivated.

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

### Reception

**S10 — Proportional shortage** (R14, R15, R17)
Given households A, B and C ordered 4, 6 and 10 loaves (B's line created before C's), 20 in total
When the manager sets the received total to 15
Then the allocations are A 3, B 5, C 7 and the product is balanced.
(3 + 4.5 + 7.5 → 3 + 4 + 7 = 14; the leftover unit goes to B, tied with C but its line was created first.)

**S11 — Proportional surplus on a variable product** (R15)
Given A and B ordered 2 kg of apples each
When the manager sets the received total to 5 kg
Then A and B are allocated 2.500 kg each.

**S12 — Weight split with leftover grams** (R15, R17)
Given A, B and C ordered 1 kg of cheese each, in that order
When the manager sets the received total to 1 kg
Then the allocations are A 0.334 kg, B 0.333 kg, C 0.333 kg, and the product is balanced.

**S13 — Manual allocation** (R16, R17, R18)
Given the allocations of S10 (A 3, B 5, C 7, received 15)
When the manager sets C's allocation to 6
Then the received total stays 15, the product is flagged as unbalanced by +1,
and marking the baker as distributed is rejected until it is balanced.

**S47 — Validate before distributing** (R17a, R18)
Given the baker's reception is balanced but not validated
When the manager tries to mark the baker as distributed
Then it is rejected; after validating it, it can be distributed
And changing a received total clears the validation.

**S39 — Allocation to a household that did not order** (R16)
Given D did not order bread and bread costs €3.00
When the manager gives D an allocation of 1 loaf
Then D has 1 loaf at €3.00, even if the price changes afterwards.

**S40 — Product nobody ordered** (R14, R16, R18)
Given the baker delivered 3 rye loaves that nobody ordered
When the manager adds rye bread to the reception with received total 3 and allocates 2 to A and 1 to B
Then the product is balanced and A and B are charged for it at pickup.

### Distribution

**S27 — No pickup before settlement** (R18, R19)
Given the baker is distributed but the vegetable grower is not
When the manager tries to open H's pickup
Then it is rejected; once the vegetable grower is distributed, the cycle is settled and H's pickup is pending.

**S28 — Settlement ignores non-participating producers** (R6c, R18)
Given H has 2 jars of honey from a producer who stopped participating, and every other producer is distributed
When the cycle is settled
Then H's honey line is dropped and never charged.

**S29 — Reopen before settlement** (R18)
Given the baker is distributed and the cycle is not settled
When the manager reopens the baker and changes the bread's received total
Then the allocations are recalculated; once distributed again, the cycle can settle.

**S30 — Late delivery after settlement** (R18, R19, R24, R24b)
Given the cherries were received as 0, so K's pickup was closed as nothing to collect, and H has already
completed its pickup
When the cherries arrive and the manager reopens that producer, allocates 1 kg to K and distributes again
Then K's pickup becomes pending and can be completed with the cherries
And H's completed pickup keeps its allocations; any difference is a wallet correction.

**S46 — No reopening once closed** (R18, R24b)
Given cycle A is closed
When the manager tries to reopen the baker
Then it is rejected and receptions stay read-only.

**S31 — Way A over manual allocations** (R15, R16)
Given the allocations of S13 (C set by hand to 6)
When the manager sets the received total to 14
Then they are warned that C's manual allocation will be lost;
if they cancel, the received total stays 15 and C stays 6;
if they confirm, the allocations become A 3, B 4, C 7 (2.8 + 4.2 + 7 → 2 + 4 + 7 = 13, the leftover unit
goes to A, largest remainder 0.8).

### Pickup

**S14 — Variable weight at pickup** (R19, R22)
Given H was allocated 1 kg of beef at €20.00/kg and its wallet is at €50.00
When the manager records 1.120 kg at H's pickup and completes it
Then H is charged €22.40, the balance is €27.60
And the beef reception now shows an unbalanced difference of −0.120 kg, which blocks nothing.

**S15 — Allocation drives the charge** (R15, R22)
Given A ordered 4 loaves at €3.00 and was allocated 3 in S10
When A's pickup is completed without changes
Then A is charged €9.00.

**S16 — Adding stock** (R20, R22, R28)
Given H's order lines total €20.00, honey is a stock product at €6.00 with 5 jars available
When the manager adds 2 jars of honey and completes the pickup
Then H is charged €32.00 and 3 jars of honey remain.

**S17 — Not enough stock** (R20)
Given 1 jar of honey is available
When the manager adds 2 jars to a pickup
Then it is rejected and stock is unchanged.

**S18 — Stock-only pickup** (R19, R20)
Given household K has no order in the settled cycle
When the manager opens K's pickup, adds 1 bottle of oil at €12.00 and completes it
Then K is charged €12.00.

**S41 — Returning jars** (R20a, R22)
Given H's order lines total €10.00 and "glass jar" is a return item of €0.50
When the manager adds 4 returned jars and completes the pickup
Then H is charged €8.00.

**S45 — Stock taken by another pickup** (R20, R22, R28)
Given 5 jars of honey are available and the pickups of H and K are both pending
When the manager adds 3 jars to each, completes H's pickup, and then tries to complete K's
Then K's completion is rejected as only 2 jars remain, nothing is recorded for K, and stock stays at 2.

**S19 — Top-up at pickup** (R21, R22, R29)
Given H's balance is €5.00 and its pickup totals €30.00
When the manager records a €40.00 cash top-up and completes the pickup
Then H's balance is €15.00 and H sees two movements: a €40.00 top-up with no cycle and a €30.00 charge
for the cycle.

**S20 — Nothing to collect** (R24, R24b)
Given household K's only line, 1 kg of cherries, was allocated 0 because none arrived
When the cycle is settled
Then K's pickup is closed as nothing to collect and nothing is recorded.

**S42 — Not collected** (R24a, R24b)
Given H was allocated 2 loaves at €3.00 and never came
When the manager marks H's pickup as not collected
Then H is charged €6.00, and once every other pickup is done the cycle is closed.

**S21 — Negative balance** (R11, R30)
Given H's wallet is at €5.00
When H's pickup of €30.00 is completed with no top-up
Then the balance is −€25.00.

**S22 — Double completion** (R23)
Given H's pickup was completed
When the manager tries to complete it again
Then it is rejected and neither the wallet nor the stock change.

**S26 — Late pickup** (R19)
Given H was allocated 2 loaves in cycle A (pickup Saturday 7th) and did not come
When on Wednesday 11th, with cycle B already open, the manager completes H's pickup for cycle A
Then H is charged for the 2 loaves of cycle A, and until then H had not been charged for them.

**S53 — Rounding per line** (R22)
Given H's allocation is 0.125 kg of two products, each at €1.00/kg
When the manager completes H's pickup
Then each line costs €0.13 (half up) and H is charged €0.26, the sum of the lines shown.

**S54 — Closed cycle** (R24b)
Given cycle A is settled, H's pickup is completed, K's is marked not collected and L's was closed as
nothing to collect
When the last of them is done
Then cycle A is closed and leaves the pending lists
And reopening a producer of A is rejected, while a wallet correction referring to A is recorded as a new
movement.

**S55 — Stock line survives deactivation** (R20)
Given H's pending pickup has 1 jar of honey at €6.00 and the manager then deactivates honey
When the manager completes H's pickup
Then H is charged €6.00 for the jar.

### Stock

**S23 — Entries and adjustments** (R26, R27, R28)
Given honey has 0 jars
When the manager records an entry of 10 and an adjustment of −1 noted "broken jar"
Then 9 jars are available; an adjustment of −10 is rejected.

### Wallet and accounts

**S24 — Top-up and correction** (R29, R32)
Given H's balance is €0.00
When the manager records a €50.00 top-up and then a −€50.00 correction noted "duplicated"
Then H's balance is €0.00 and both movements are visible to H.

**S25 — Member invitation** (R33, R34)
Given the manager creates household H and adds anna@example.org as a member
When Anna opens the invitation link and sets a password
Then she can log in and sees H's order and wallet; before accepting, login is rejected
And a link older than 7 days, or replaced by a resent one, is rejected.

**S43 — Moving a member** (R35, R35a)
Given Anna and Biel are members of H, and H's balance is €12.00
When the manager moves Anna to household J
Then Anna logs in with the same account and sees J's order and wallet; H keeps its €12.00 with Biel
And when Biel is unlinked too, H is deactivated and listed as to settle.

**S44 — Several roles** (R36a)
Given Anna is a member of H
When a manager grants her the manager role
Then with one login she orders for H and manages cycles
And removing the manager role from the last manager is rejected.

**S56 — Only managers create households** (R33)
Given Anna is a member of H and is logged in
When she tries to create a household or add a member
Then it is rejected; a manager can do both.

**S57 — Producer account** (R36)
Given the manager creates a producer account for baker@example.org
When the baker opens the invitation and sets a password
Then they can log in and see only their own products
And creating a second account for the same producer is rejected.

**S58 — Deactivating a household** (R35a)
Given household H has a balance of −€5.00, a pending pickup and members Anna and Biel
When the manager deactivates H after the warning that lists the balance and the pending pickup
Then Anna and Biel are unlinked and nobody can order for H
And H's lines and pending pickup are kept and H is listed as to settle
And once the manager completes the pickup and records a top-up that brings the balance to €0.00, H leaves
the list.

**S59 — Deactivating an account** (R36b)
Given Anna is a member of H and Carla is the only manager
When the manager tries to deactivate Carla's account
Then it is rejected; deactivating Anna's account unlinks her from H, she cannot log in and her history is
kept.

## Out of scope (for now)

Online payments, membership fees, work shifts, selling stock outside a pickup, notifications other than
sending an order to a producer (R12a), producer confirmation of an order (v2), minimum order per producer,
invoicing and payments to producers, tracking returned containers as stock.

## Open questions

Raised while resolving OQ1–OQ18 on 2026-10-19. Each question lists the requirements and scenarios it
blocks; everything else stays accepted.

### OQ19 — Counting the containers distributed (low)

Blocks: R1, R20a.

Decided: a product's price already includes its container, and a return item credits a fixed amount
(R20a). A product can also be linked to the container that comes with it (e.g. honey → glass jar), only to
know how many containers were distributed; it adds nothing to the charge.

Questions:

1. How many containers does a delivered quantity count? One per unit is clear for non-variable products;
   for a variable product (2.500 kg of yoghurt) it is not.
2. Where is the count shown (per cycle, per household, overall), and to whom?
3. Is the link snapshotted in the order line, like the rest of the product (R10)?

Options for 1:

- **(a)** Only non-variable products can be linked; one container per unit delivered. — Simple; variable
  products in jars are not counted.
- **(b)** Each product sets how many containers go with one unit (e.g. 1 per 0.500 kg), rounded up.
  — Covers everything; one more field per product.

Proposal: (a).
