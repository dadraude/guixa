# 001 — Weekly orders

Status: Accepted (2026-10-19) — includes the amendment for producer reception, stock and top-up at pickup.
Reopened (2026-10-19) for [OQ1–OQ18](#open-questions): the requirements they block are not final.

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
  all the households that ordered it, proportionally to their ordered quantity.
  The allocations always add up exactly to the received total: nothing is left over and nothing is invented.
  - Each share is rounded down to the product's step: 1 unit for non-variable products, 0.001 (1 g)
    for variable ones.
  - The steps left over after rounding down (whole units, or grams) go one by one to the households
    with the largest remainders; on a tie, to the household that first added that product to its order
    in this cycle.
  - Example: 1.000 kg among three households that ordered 1 kg each → 0.334 + 0.333 + 0.333 = 1.000 kg.
  - If any allocation of that product was set by hand (way B), the manager is warned that those manual
    allocations will be lost, and either confirms (everything is re-allocated from the ordered quantities)
    or cancels (nothing changes, not even the received total).
- **R16** **Way B — edit allocations.** A manager sets any household's allocation directly (≥ 0).
  This does not change the received total.
- **R17** For each product, the reception shows the difference between the received total and the sum of
  allocations. A product is *balanced* when the difference is 0. Unbalanced products are flagged
  but never block closing the distribution nor a pickup.
- **R18** **Closing the distribution.** Once every producer's cutoff in the cycle has passed, a manager
  marks the cycle as *distributed*. From then on receptions are read-only. A manager can reopen the
  distribution only while no pickup of that cycle has been confirmed; after the first confirmed pickup
  it stays closed for good.

### Pickup (once per household and cycle)

- **R19** Pickups of a cycle can only be opened, edited and confirmed while the cycle is distributed (R18).
  A manager opens a household's pickup for a cycle, whether or not the household has an order.
  Each order line shows its allocation as the delivered quantity, which the manager can still adjust (≥ 0),
  e.g. the real weight of a package or a damaged item. The adjustment updates the allocation, so it
  is reflected in the reception balance (R17).
  A household's allocation stays pending until its pickup is confirmed, with no time limit: it is not
  charged, released or moved to stock in the meantime, and it can be picked up after the pickup date,
  even once later cycles are open.
- **R20** The manager can add stock products to the pickup, each with a quantity. A stock line takes the
  stock product's current price. The quantity cannot exceed the available stock.
- **R21** The pickup shows the household's current balance, the total to charge and the resulting balance.
  The manager can include a top-up in the same pickup (amount > 0, optional note), e.g. when the household
  pays in cash or shows a transfer.
- **R22** Confirming the pickup, all at once or nothing:
  charges the wallet with Σ(delivered × unit price) of order lines + Σ(quantity × price) of stock lines,
  each line rounded to the nearest cent (half up), so the lines shown add up to the charge;
  decreases stock by the stock lines; records the top-up, if any; and freezes the pickup.
- **R23** A pickup can be confirmed only once. A confirmed pickup and its order cannot be edited.
- **R24** A pickup with nothing to charge and no top-up cannot be confirmed; nothing is recorded.

### Stock

- **R25** A manager creates, edits and deactivates stock products: name, unit, price, variable flag.
- **R26** A manager records stock entries (quantity > 0, optional note, e.g. "bought from X").
- **R27** A manager records stock adjustments (positive or negative, mandatory note, e.g. "broken jar",
  "inventory count"). Available stock can never go below 0.
- **R28** Available stock = Σ entries + Σ adjustments − Σ quantities sold at confirmed pickups.
  Stock movements are never edited or deleted.

### Wallet

- **R29** A manager records top-ups, at a pickup (R21) or on their own: positive amount, date,
  optional note (e.g. "transfer", "cash").
- **R30** Balance = Σ top-ups − Σ charges ± corrections. It may be negative, with no limit.
- **R31** Members see their household balance and every movement (top-ups and charges, with cycle).
- **R32** Movements are never edited or deleted. Mistakes are fixed with a correcting movement
  recorded by a manager, with a mandatory note.

### Households and accounts

- **R33** Only a manager creates households and adds or removes their members. There is no self sign-up.
- **R34** Adding a member sends them an email invitation to set their password. Until they accept it,
  they cannot log in.
- **R35** A person belongs to at most one household. Removing a member keeps the household's orders
  and wallet movements intact.
- **R36** Producer accounts are also created only by a manager, with the same invitation flow.

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

**S12 — Weight split with leftover grams** (R15, R17)
Given A, B and C ordered 1 kg of cheese each, in that order
When the manager sets the received total to 1 kg
Then the allocations are A 0.334 kg, B 0.333 kg, C 0.333 kg, and the product is balanced.

**S13 — Manual allocation** (R16, R17)
Given the allocations of S10 (A 3, B 5, C 7, received 15)
When the manager sets C's allocation to 6
Then the received total stays 15 and the product is flagged as unbalanced by +1.

### Pickup

**S14 — Variable weight at pickup** (R19, R22)
Given H was allocated 1 kg of beef at €20.00/kg and its wallet is at €50.00
When the manager records 1.120 kg at H's pickup and confirms
Then H is charged €22.40, the balance is €27.60
And the beef reception now shows an unbalanced difference of −0.120 kg.

**S15 — Allocation drives the charge** (R15, R22)
Given A ordered 4 loaves at €3.00 and was allocated 3 in S10
When A's pickup is confirmed without changes
Then A is charged €9.00.

**S16 — Adding stock** (R20, R22, R28)
Given H's order lines total €20.00, honey is a stock product at €6.00 with 5 jars available
When the manager adds 2 jars of honey and confirms
Then H is charged €32.00 and 3 jars of honey remain.

**S17 — Not enough stock** (R20)
Given 1 jar of honey is available
When the manager adds 2 jars to a pickup
Then it is rejected and stock is unchanged.

**S18 — Stock-only pickup** (R19, R20)
Given household K has no order in the cycle
When the manager opens K's pickup, adds 1 bottle of oil at €12.00 and confirms
Then K is charged €12.00.

**S19 — Top-up at pickup** (R21, R22, R29)
Given H's balance is €5.00 and its pickup totals €30.00
When the manager adds a €40.00 cash top-up and confirms
Then H's balance is €15.00 and H sees two movements: a €40.00 top-up and a €30.00 charge.

**S20 — Nothing to confirm** (R24)
Given household K has no order, no stock lines and no top-up
When the manager tries to confirm K's pickup
Then it is rejected and nothing is recorded.

**S21 — Negative balance** (R11, R30)
Given H's wallet is at €5.00
When H's pickup of €30.00 is confirmed with no top-up
Then the balance is −€25.00.

**S22 — Double confirmation** (R23)
Given H's pickup was confirmed
When the manager tries to confirm it again
Then it is rejected and neither the wallet nor the stock change.

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
Then she can log in and sees H's order and wallet; before accepting, login is rejected.

**S26 — Late pickup** (R19)
Given H was allocated 2 loaves in cycle A (pickup Saturday 7th) and did not come
When on Wednesday 11th, with cycle B already open, the manager confirms H's pickup for cycle A
Then H is charged for the 2 loaves of cycle A, and until then H had not been charged for them.

### Distribution

**S27 — No pickup before distribution** (R18, R19)
Given every cutoff of cycle A has passed but the cycle is not distributed
When the manager tries to open H's pickup
Then it is rejected; after marking cycle A as distributed, it can be opened.

**S28 — Distribution needs every cutoff** (R18)
Given the vegetable grower's cutoff in cycle A has not passed yet
When the manager tries to mark cycle A as distributed
Then it is rejected.

**S29 — Reopen before the first pickup** (R18)
Given cycle A is distributed and no pickup has been confirmed
When the manager reopens the distribution and changes the bread's received total
Then the allocations are recalculated; once distributed again, pickups can start.

**S30 — No reopening after a pickup** (R18)
Given cycle A is distributed and H's pickup has been confirmed
When the manager tries to reopen the distribution
Then it is rejected and receptions stay read-only.

**S31 — Way A over manual allocations** (R15, R16)
Given the allocations of S13 (C set by hand to 6)
When the manager sets the received total to 14
Then they are warned that C's manual allocation will be lost;
if they cancel, the received total stays 15 and C stays 6;
if they confirm, the allocations become A 3, B 4, C 7 (2.8 + 4.2 + 7 → 2 + 4 + 7 = 13, the leftover unit
goes to A, largest remainder 0.8).

## Out of scope (for now)

Online payments, membership fees, work shifts, selling stock outside a pickup, notifications,
minimum order per producer, invoicing and payments to producers.

## Open questions

Raised in the spec review of 2026-10-19. Each question lists the requirements and scenarios it blocks;
everything else stays accepted. Options are listed with their trade-offs; the proposal is only a starting
point for the decision. Severity: **high** breaks an invariant the rest of the spec relies on,
**medium** is a missing business decision, **low** is a precision.

### OQ1 — Changing a cutoff that has already passed (high)

Blocks: R6, R8, R12, R14, R15, R18 · S6, S28.

R6 only limits an override to "never past the pickup date". Nothing stops a manager from:

- moving a cutoff that has **already passed** to a later moment, which reopens ordering for that producer
  after the producer has seen its totals (R12), after its reception has been opened or edited (R14–R16),
  or even after the cycle is distributed (R18) — breaking R18's precondition "every cutoff has passed";
- moving a future cutoff to a moment **already in the past**, closing the producer at once while members
  may be editing.

If ordering reopens after the reception was edited, a changed line resets its allocation to the ordered
quantity, the received total no longer matches the new ordered total, and manual allocations are lost
without the R15 warning.

Questions:

1. Can a cutoff that has already passed be moved?
2. Can a cutoff be moved to a moment that is already past (or to "now")?
3. Is a producer with no cutoff in the cycle (a producer created after the cycle opened, plan D2) treated as
   "already passed" for these rules, or as "not set"?

Options:

- **(a)** Only a cutoff that has not passed can be moved, and only to a moment after now and before the
  pickup date. A producer without a cutoff can be given one under the same rule. — Simple, keeps R12/R14/R18
  safe; a manager cannot reopen ordering for a forgotten line (see OQ10).
- **(b)** A passed cutoff can be moved while that producer's reception has not been touched and the cycle
  is not distributed. — More flexible; needs a definition of "touched" and has to tell the producer that
  totals may still change.
- **(c)** No extra limit (current text). — Requires defining what happens to received totals and manual
  allocations when ordered quantities change after reception.

Proposal: (a).

### OQ2 — Can a default cutoff fall on the pickup date? (high)

Blocks: R5, R6 · S6. Affects the plan (`CutoffRule` 0–6 days, D3).

R5 does not limit the default rule. The plan accepts "0 days before" (e.g. pickup date at 20:00), while D3
forbids an override at or after 00:00 of the pickup date. A cycle could therefore open with a default cutoff
that the manager could never set by hand, and that may fall after the households have already collected.
The spec has no pickup time, so "before the pickup" can only mean "before the pickup date".

Questions:

1. Can a default cutoff fall on the pickup date itself?
2. Is there a maximum number of days before? With 7 or more days, the cutoff of a weekly cycle falls before
   the previous cycle's pickup.

Options:

- **(a)** Default and override follow the same rule: strictly before the pickup date (≥ 1 day before),
  at most N days before (N to be decided, e.g. 6). — Consistent, nothing new to model.
- **(b)** Cutoffs may fall on the pickup date, and cycles get a pickup start time; both default and override
  must be before it. — Allows same-day producers; adds a pickup time to cycles.

Proposal: (a), unless some producer really delivers orders taken on the pickup day.

### OQ3 — Pickups that can never be closed, and the end of a cycle (high)

Blocks: R4, R18, R19, R22, R23, R24 · S20, S26.

- A household whose order lines all ended with an allocation of 0 (the producer did not deliver), with no
  stock line and no top-up, has nothing to charge: R24 forbids confirming it, so its pickup stays pending
  forever.
- R19 keeps an allocation pending "with no time limit". A household that never comes keeps a pending pickup
  forever; it is never charged, while the goods were received from the producer and were perishable.
- Cycles have no final state, so pending pickups and their cycles accumulate in every list and never leave
  "pending".

Questions:

1. How is a pickup with nothing to charge closed (e.g. "nothing to collect")? Does it record anything, and
   does it count as a confirmed pickup for R18?
2. What happens to a household that does not come? Charged anyway, not charged and the goods written off,
   or pending forever (current text)? Who decides and when?
3. Does a cycle have a final state ("closed")? What does it require (every pickup confirmed or closed?),
   and what becomes impossible afterwards?

Options for a household that does not come:

- **(a)** The manager marks the pickup as *not collected*: the household is charged its allocation, nothing
  else changes. — The cooperative recovers what it paid; a household may be charged for goods it never had.
- **(b)** The manager marks it as *not collected* without charging; the goods are lost (or moved to stock,
  see OQ5). — Fair to the household; the cooperative absorbs the cost.
- **(c)** Pending forever (current text), relying on late pickup (S26). — No new state; lists grow forever.

Proposal: a *close without collecting* action that records whether the household is charged, plus a
*closed* cycle state once every pickup is confirmed or closed.

### OQ4 — One person with several roles (high)

Blocks: Roles, R33, R34, R35, R36, R31 · S25. Affects the plan (`Actor` with a single role, one role per account).

- The roles are listed as exclusive. In a consumer cooperative a manager is usually also a member of a
  household, and a producer can also be a member. With one role per account and unique emails, that person
  needs two emails.
- The glossary says a member belongs to **exactly one** household; R35 says **at most one**.
- R36 does not say whether a producer can have more than one account (two people of the same farm).
- Nothing says who creates manager accounts, nor how the first manager is created.

Questions:

1. Can one person (one login) be a member of a household and also a manager and/or a producer?
2. Exactly one household or at most one? If "at most one", what can a member without a household do?
3. How many accounts can a producer have?
4. Who creates managers, and how is the first one created?

Options for 1:

- **(a)** One account with a set of roles (e.g. member of H + manager). — Matches reality; every screen and
  authorization check must handle combined roles.
- **(b)** One role per account (current plan); a person with two roles uses two accounts with two emails.
  — Simplest; awkward for the people who run the cooperative.

Proposal: (a), decided now because changing it later rewrites accounts and authorization.

### OQ5 — Surplus and goods nobody ordered (medium)

Blocks: R14, R16, R17, R19, R20, R28 · S11, S13.

- R16 says "any household's allocation", but R14 only lists the households that ordered the product. Can a
  household that did not order a product receive an allocation of it? If so, at which price, since there is
  no order line with a price snapshot (R10)?
- When received > Σ allocations (way B, S13), R17 only flags it. Physically there are goods left: do they go
  to stock, are they lost, are they given away?
- If a producer delivers a product nobody ordered, it does not appear in any reception.
- At pickup (R19, R20), only order lines and stock products exist; a catalog product cannot be added.

Questions:

1. Can an allocation be given to a household that did not order the product? At which price?
2. What happens to an unallocated difference at closing (R18)?
3. Can unordered goods be recorded in a reception?
4. Can unallocated or unordered goods become stock? With which price?

Options:

- **(a)** Allocations only for households that ordered; any surplus is lost or handled outside the app.
  — No new rules; the stock and the producer's bill drift from reality.
- **(b)** Surplus can be moved to a stock product with a stock entry (R26) noted with the cycle. — Reuses
  stock; needs a link between a catalog product and a stock product, and a price.
- **(c)** Way B also allows households that did not order, at the product's current price. — Flexible;
  breaks "price at the time of ordering" for those lines.

### OQ6 — Fixing a pickup confirmed by mistake (medium)

Blocks: R18, R23, R27, R28, R31, R32 · S22.

R23 freezes a confirmed pickup. A mistake (wrong weight, wrong stock line, wrong household) can only be fixed
in pieces: a wallet correction (R32) and a stock adjustment (R27). The pickup itself, its delivered
quantities and the reception balance stay wrong forever, and the confirmed pickup keeps the distribution
locked (R18).

Questions:

1. Is fixing in pieces (wallet correction + stock adjustment) acceptable, with the pickup record left as it
   was?
2. Or is there an action to cancel a confirmed pickup, reversing its charge, its stock sale and its top-up
   with new movements, and reopening it for editing? Who can do it, and until when?
3. Does a correction link to a cycle (R31 shows movements "with cycle")?

Options:

- **(a)** Fix in pieces; corrections can optionally reference a cycle. — No new state; the history of what
  was delivered is not fixed.
- **(b)** *Cancel pickup*: append reversing movements (never edit, R28, R32) and unfreeze the pickup.
  — Correct history; must define its effect on R18 (does cancelling the last confirmed pickup allow
  reopening the distribution?).

### OQ7 — Cycle lifecycle (medium)

Blocks: R4, R5, R6, R7 · S6, S9.

1. "Open cycle" (R7) is not defined. Is a cycle open while at least one cutoff is in the future, while it is
   not distributed, or something else? What does a member see for a cycle where every cutoff has passed?
2. Can a cycle be opened with a pickup date in the past, or with cutoffs already passed?
3. Can a cycle be cancelled (e.g. holidays)? With orders already placed? What do members see?
4. Can the pickup date of a cycle be changed (e.g. a holiday moves pickup to Friday)? Are cutoffs recomputed,
   and are manual overrides kept? The cycle is identified by its pickup date (glossary).
5. Is there a limit to how far ahead or how many cycles can be open at once?

### OQ8 — Deactivating producers and products, and editing products with orders (medium)

Blocks: R1, R3, R5, R9, R10, R15 · S5.

1. R3 relies on "active producers", but no requirement says who deactivates a producer or reactivates it.
   Does a deactivated producer still log in?
2. A product (or its producer) is deactivated while an open cycle has lines for it, before its cutoff.
   Are those lines kept, removed, or frozen? Can members still change or remove them? Is it delivered?
3. Does opening a cycle compute cutoffs for inactive producers? What happens when a producer is reactivated
   during a cycle (closed for that cycle, like plan D2)?
4. R10 only keeps the price. If a producer edits the name, unit or variable flag of a product with lines
   in an open cycle, do the lines keep the old values? Changing a product from variable to non-variable
   would leave fractional lines that R9 forbids, and changes the rounding step of R15.
5. Can a product or stock product have a price of 0 (free)? R1 and R25 do not say.

### OQ9 — Distribution, unchecked receptions and late deliveries (medium)

Blocks: R14, R17, R18, R19 · S27, S29, S30.

1. R18 only requires every cutoff to have passed. Can a cycle be distributed while a producer's reception
   was never opened or checked? Today an unchecked reception silently means "received = ordered".
2. A producer delivers late, or does not deliver, after the first pickup has been confirmed. Receptions stay
   read-only for good (R18), so the shortage can only be fixed household by household at pickup, without
   the proportional split of R15. Is that accepted, or can one producer's reception be reopened while none
   of its lines has been picked up?
3. When the distribution is reopened (R18), pickups that were opened but not confirmed may already have
   delivered-quantity adjustments (which count as manual allocations), stock lines and a top-up being
   entered. Are they kept or discarded? A later way A on that product warns about and drops those
   adjustments.

### OQ10 — Can a manager edit a household's order? (medium)

Blocks: R7, R8 · S1–S3.

R7 gives the order to the household's members; the manager has "everything else". Can a manager add, change
or remove a household's order lines on its behalf (e.g. a member without internet calls in)? Before the
cutoff only, or also after it (a forgotten line, before reception)? If after, how does it interact with the
producer's totals (R12) and the reception (R14, OQ1)?

### OQ11 — Members, households and leaving the cooperative (medium)

Blocks: R29–R36 · S25.

1. Moving a person to another household: removing them deactivates the account (plan D4) and the email is
   unique, so they cannot be added again. Is the same account reactivated in the new household?
2. Can a removed member be added back to the same household?
3. Can a household be renamed, deactivated or leave the cooperative? What happens to its balance (refund,
   debt): which movement records it? What happens to its pending pickups and its orders in open cycles?
4. A household whose last member is removed: does it stay active?
5. Does an invitation expire? Can a manager resend it, or change the email before it is accepted?
6. Can a producer account be removed, with the same rules as a member?

### OQ12 — Stock lines and stock prices (medium)

Blocks: R20, R22, R25, R28 · S16, S17.

1. R20: "a stock line takes the stock product's current price". Current when the line is added or when the
   pickup is confirmed? A price change in between gives different charges.
2. Must non-variable stock products be sold in whole units, as R9 requires for orders?
3. A stock product is deactivated while a pending pickup has a line for it: can that pickup still be
   confirmed with it?

### OQ13 — Way A tie rule (low)

Blocks: R15 · S10, S31.

1. "The household that first added that product to its order in this cycle": if a household removes the line
   and adds it again, does it keep its original moment or get a new one? Changing the quantity keeps it?
2. If two households added it at the same instant, which one wins? The rule needs a final deterministic
   criterion so the result is reproducible.

### OQ14 — Ordering step of variable products (low)

Blocks: R9.

R9 only requires whole units for non-variable products. A variable product can be ordered in any multiple
of 0.001 (e.g. 0.001 kg). Is there a minimum or a step per product (e.g. 0.250 kg)? A maximum?

### OQ15 — Zero charge with a top-up (low)

Blocks: R22, R24, R31 · S19.

R24 allows confirming a pickup whose total is 0 when it has a top-up. Is a charge of €0.00 recorded, and does
the member see it as a movement? Is the top-up made at a pickup linked to that cycle in the statement (R31)?

### OQ16 — Date of wallet movements (low)

Blocks: R29, R31, R32 · S24.

R29 says a top-up has a "date". Is it the moment it is recorded, or a date the manager enters (e.g. the day
of the bank transfer)? Can it be in the past or the future? Which date orders the statement and defines the
balance on a given day? The same for corrections.

### OQ17 — What a member sees of their pickup (low)

Blocks: R7, R31.

Before and after the pickup, does a member see the allocation and the delivered quantity of each line, the
stock lines and the price of each line, or only the order they placed and the total charge (R31)?

### OQ18 — Local times that do not exist or repeat (low)

Blocks: R5, Time.

On daylight-saving days a local time may not exist (e.g. 02:30 when clocks go forward) or happen twice (when
they go back). Which instant is a cutoff at such a time? Allowing only cutoff times outside 02:00–03:00 is
also an answer.
