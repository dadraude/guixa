# 001 — Weekly orders: Reception and distribution

Part of [001 — Weekly orders](spec.md): glossary, time and roles are defined there.

Checking each producer's delivery against the orders, allocating it to households and closing the distribution.

## Requirements

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

## Acceptance scenarios

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
