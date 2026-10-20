# 001 — Weekly orders: Pickup

Part of [001 — Weekly orders](spec.md): glossary, time and roles are defined there.

A household collects its allocation and stock products and is charged; optional top-up on the spot.

## Requirements

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

## Acceptance scenarios

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

**S26 — Late pickup** (R19)
Given H was allocated 2 loaves in cycle A (pickup Saturday 7th) and did not come
When on Wednesday 11th, with cycle B already open, the manager confirms H's pickup for cycle A
Then H is charged for the 2 loaves of cycle A, and until then H had not been charged for them.
