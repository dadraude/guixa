# 001 — Weekly orders: Reception and distribution

Part of [001 — Weekly orders](spec.md): glossary, time and roles are defined there.

Checking each producer's delivery against the orders, allocating it to households and closing the distribution.

## Requirements

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
- **R18a** **Containers distributed.** A manager sees, per cycle, how many containers each household takes
  and the cycle total. Each allocation (R10) and each stock line of the household's pickup (R20) counts its
  quantity × the containers per unit of its snapshot, rounded up to a whole number; a product without
  containers counts 0. The cycle total is the sum over households.

## Acceptance scenarios

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

**S60 — Counting containers** (R1, R10, R18a, R20, R25)
Given yoghurt is variable, comes with 2 containers per kg, and honey comes with 1 container per unit
And H is allocated 1.300 kg of yoghurt and 3 jars of honey, and K 0.500 kg of yoghurt
And K's pickup has a stock line of 2 jars of stock honey, with 1 container per unit
When the producer changes yoghurt to 4 containers per kg and the manager opens the cycle
Then they see H 6 containers (1.300 × 2 = 2.6 → 3, plus 3), K 3 (1 + 2), and 9 for the cycle.
