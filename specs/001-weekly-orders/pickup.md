# 001 — Weekly orders: Pickup

Part of [001 — Weekly orders](spec.md): glossary, time and roles are defined there.

A household collects its allocation and stock products and is charged; optional top-up on the spot.

## Requirements

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
  snapshot of the stock product's price and containers per unit when the line is added. Non-variable stock products are sold in
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

## Acceptance scenarios

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
