# 001 — Weekly orders: Baskets, validation and collection

Part of [001 — Weekly orders](spec.md): glossary, time and roles are defined there.

Weekly base and independent stock baskets, actual contents, validation, global Wallet effects and physical collection.

## Requirements

- **R19** **Baskets and their source.** Once the cycle first settles, each household order with eligible allocations produces one base
  basket draft, initially containing its allocated quantities and original ordered product snapshots.
  Creating the draft does not charge money. Creation/synchronization is idempotent by source order.
  A late arrival (R18) for a household whose weekly baskets in the cycle are all validated creates one
  late weekly basket draft from the same source order with only the late lines; a household has at
  most one unvalidated weekly draft per cycle, and later late arrivals add to it.
  Finance or Manager can create additional stock baskets for any active household, including one
  without a weekly order, at any time and without requiring an open or settled cycle.
  While a basket is not validated, additions go to it; once validated, anything added creates a new
  independent basket with no cycle attribution, which does not reopen the cycle, block closure,
  duplicate the weekly contents or backdate movements.
- **R19a** **Draft contents and access.** Only Finance or Manager creates/edits stock basket drafts, adjusts actual weekly quantities and
  adds/removes stock and return lines. Preserve ordered quantities separately; actual quantities obey
  the source product's whole-unit/0.001 step and may be zero for weekly lines. Members can consult
  their own baskets and the read-only stock catalogue but cannot edit or validate contents.
  Finance basket access does not grant catalogue, producer/cycle/reception or general inventory
  administration. A Manager still holds those existing permissions where applicable.
- **R19b** **Retained history.** Removed order/basket lines and cancelled orders/baskets are retained as history. Exclude them from
  current contents, operational totals and proportional claims, but preserve product references for
  R1b/R1c. Re-adding a removed line creates a new line/creation moment. Cancelled cycles continue to
  show no active orders or totals. A stock-only draft may be cancelled without a charge or stock sale.
- **R20** **Stock basket lines.** Finance/Manager manually add active stock products, never weekly products. The producer's active
  state, participation, cutoff and per-cycle availability do not restrict a stock sale. Each line keeps
  the product/producer identity and its name, unit, variable, unit-price and containers snapshot when
  first added. Adding again increases the active line using the first price/container snapshot.
  Positive quantities obey the existing whole-unit/0.001 steps, and combined quantity is checked against
  stock at addition and validation. Draft stock is not reserved. Existing lines survive product
  deactivation and may be removed, but the inactive product cannot be newly added or increased.
- **R20a** **Return lines.** Preserve return items as basket lines: positive whole quantity, fixed amount snapshotted on addition,
  credited in the basket total. Finance/Manager can operate them as part of the basket; Manager alone
  administers the return-item catalogue. A return-only basket may yield a wallet credit.
- **R21** **Amounts and wallet.** Show the actual basket total, current global household balance and projected/resulting balance.
  Receipts are recorded independently of validation after Finance/Manager confirm money received.
  There is no basket paid/part-paid status, and a negative wallet balance remains permitted.
- **R22** **Explicit validation.** Finance or Manager explicitly validates the basket. For a weekly base the source must be settled
  and synchronized to the current allocation. Validate exactly once, with stock availability checked
  again. In one transaction, confirm the immutable basket contents/total, record stock sales and append
  one Wallet charge or credit. Failure records none of these. Calculate total as weekly actuals plus
  stock minus returns, each monetary line rounded half up to cents; negative means credit, zero means
  no money movement. Collection never generates another charge or stock sale.
- **R23** **Immutable originals and corrections.** Validated contents, original charge and validation time are never overwritten. Finance/Manager may
  append correcting quantity/amount/stock movements with a mandatory reason and original source link;
  preserve the original basket and ledger records. Correct actual money/goods errors without silently
  returning stock that was not returned. Cross-context corrections are atomic and cannot make stock
  negative. Reception redistribution never adjusts validated baskets (R18): staff correct each
  affected validated basket individually with these corrections. Ordinary sale/receipt corrections may reference
  a closed cycle without reopening weekly contents. A mistaken collection mark may be corrected by
  staff with a reason and retained original history, without financial effects.
- **R23a** **What people see.** Members see their own ordered-versus-actual contents, draft/validated/collection status, adjustments,
  charges and global Wallet balance. Finance/Manager see the households needed to operate baskets and
  receipts. Keep a visual pending-collection indication after the planned date and after cycle closure:
  own baskets for members, all for staff, and a badge/link on the referenced closed cycle. Drafts are
  labelled in preparation rather than ready to collect. No new push/email reminder is implied.
- **R23b** **Collection.** Finance, Manager or a current member of the basket's own household may mark a validated basket
  collected. Record who/when; repeated marking preserves the first record and has no money/stock effect.
  A member cannot mark another household's basket, validate a draft or record a receipt. An already
  physically taken stock purchase reported later can be registered, validated and marked collected by
  staff, preserving the recording date and origin-cycle reference.
- **R24** **Delayed collection.** A validated basket stays pending until physically collected or explicitly resolved through justified
  correction. Passing the pickup date alone is not abandonment, cancellation, a refund or return of stock.
  Collection can be recorded on a later day and does not depend on whether the global Wallet is in debt.
- **R24a** **No contents and draft cancellation.** Preserve the existing nothing-to-collect case: no positive weekly allocation and no stock/return
  contents needs no charge or physical collection. If a later late arrival or permitted redistribution (R18) produces goods
  while the weekly cycle remains open, its base returns to draft and requires validation. A zero-price
  or net-zero basket with real goods/returns may still be validated. Retain cancelled stock drafts and
  removed lines as history. No automatic terminal not-collected/abandonment state is created by a date;
  replace the old charge-on-not-collected command with the separate validation/collection flow.
  A full justified contents reversal leaves no effective goods pending physical collection.
- **R24b** **Weekly closure.** A cycle closes once settled, every participating reception is distributed and every base and late weekly
  basket is validated or has nothing to collect. Physical collection is independent and remains actionable after
  closure. Supplementary/cycleless stock baskets never gate weekly closure.

## Acceptance scenarios

**S14 — Variable quantity before basket validation** (R19a, R22)
Given H's draft has 1 kg beef at €20/kg, its Wallet is €50, and another base remains draft so the cycle stays open
When Finance records actual 1.120 kg and validates H's basket
Then H is charged €22.40 with balance €27.60, while ordered 1 kg is retained
And reception flags the −0.120 kg actual difference without blocking distribution.

**S61 — Redistributing after an actual-quantity adjustment** (R18, R19a)
Given S14's actual difference and its still-open cycle
When the manager reopens the beef producer and distributes again without changing allocations
Then distribution succeeds and the −0.120 kg difference remains flagged; no charge is repeated.

**S15 — Allocation drives the initial basket charge** (R15, R19, R22)
Given A ordered 4 loaves at €3 and was allocated 3 in S10
When its automatically created basket is validated without further actual adjustments
Then A is charged €9.

**S16 — Adding stock** (R20, R22, R28)
Given H's weekly actuals total €20 and stock honey costs €6 with 5 jars available
When Finance adds 2 jars to H's draft and validates it
Then H is charged €32, 3 jars remain, and later collection has no financial effect.

**S17 — Not enough stock at addition** (R20)
Given 1 jar of honey is available
When Finance adds 2 jars to a draft basket
Then it is rejected and stock and Wallet are unchanged.

**S18 — Stock-only basket** (R19, R20, R22)
Given active household K has no weekly order and no cycle is required
When Finance creates a stock basket, adds 1 available bottle of oil at €12 and validates it
Then K is charged €12; it can be marked collected without another charge.

**S41 — Returning jars** (R20a, R22)
Given H's goods total €10 and a snapshotted glass-jar return credits €0.50 each
When Finance adds 4 returns and validates the basket
Then H is charged €8.

**S45 — Stock sold by another basket** (R20, R22, R28)
Given 5 jars are available and H and K each have 3 jars on draft baskets
When H's basket is validated and Finance then validates K's
Then K's validation fails with only 2 jars available; its draft and Wallet remain unchanged.

**S19 — Independent receipt around basket validation** (R21, R22, R29)
Given H's balance is €5 and its basket total is €30
When Finance records a confirmed €40 cash receipt and validates the basket
Then the global balance is €15 with separate €40 receipt and €30 basket charge
And the receipt is not allocated to a basket or repeated by collection.

**S20 — Nothing to collect** (R24a, R24b)
Given K's only weekly line, 1 kg cherries, was allocated 0 and it has no stock or return contents
When the cycle settles
Then its base has nothing to collect, with no charge or collection obligation, and does not block closure.

**S42 — Missing the planned day is not abandonment** (R24, R24b)
Given H's 2-loaf basket at €3 each was validated and charged €6
When the planned pickup day passes without a collection mark
Then the basket stays pending collection and the original charge remains
And there is no automatic refund, stock return or terminal abandonment; it can be collected later.

**S21 — Negative balance** (R11, R22, R30)
Given H's Wallet balance is €5
When its €30 basket is validated without a receipt
Then the balance is −€25 and collection remains available.

**S22 — Double validation** (R22, R23)
Given H's basket is already validated
When Finance tries to validate it again
Then it is rejected and neither Wallet nor stock changes.

**S26 — Late physical collection** (R23b, R24)
Given H's basket for Saturday 7th is validated and charged but pending collection
When on Wednesday 11th, with the next cycle already open, a member marks it collected
Then the collection actor/time are recorded and no charge or stock sale is repeated.

**S53 — Rounding per line** (R22)
Given H's basket actuals are 0.125 kg of two products, each at €1/kg
When Finance validates it
Then each line is €0.13 half up and the charge is €0.26, matching the displayed sum.

**S54 — Closed cycle with pending collection** (R23a, R24b)
Given cycle A is settled and distributed, H's base is validated, K's is the last non-empty draft,
and L's base has nothing to collect
When Finance validates K's base
Then A closes even while H or K is pending collection and keeps the pending-collection indicator
And reception reopening is rejected, while justified Wallet corrections may reference A as new movements.

**S55 — Stock line survives product deactivation** (R20)
Given H's draft already contains 1 jar of stock honey at €6 and the manager deactivates the product
When Finance validates that existing line
Then H is charged €6, while any new line or increase for inactive honey is rejected.

**S75 — Remaining stock from an inactive producer** (R3a, R19, R20, R22, R25c)
Given the beekeeper is inactive, their stock honey stays active with 5 jars at €6, and H is active
When Finance creates a basket for H, adds 2 jars chosen by the member and validates it
Then H is charged €12 and 3 remain, without producer participation or a settled cycle
And that active honey remains visible in the informational stock catalogue.

**S76 — Weekly products cannot be stocked or sold as stock** (R1a, R20, R26, R27)
Given the oil producer has distinct weekly and stock oil products
When the manager tries a stock entry/adjustment for weekly oil or adds it as a basket stock line
Then each attempt is rejected; the stock variant remains subject to the normal quantity/availability checks.

**S78 — Automatic draft, explicit Finance validation** (R18, R19, R19a, R22, R36c)
Given a household ordered 1 kg of a variable product at €20/kg and distribution assigns 1 kg
When distribution settles and Finance records 1.120 kg actual
Then a base draft exists with ordered 1 kg, actual 1.120 kg and no charge yet
And validating it charges €22.40 exactly once, retaining both quantities and the product snapshot.

**S79 — Independent stock purchase** (R19, R20, R22, R23b)
Given no weekly cycle is open and an active household has no weekly order
When Finance creates a stock basket, adds available oil, validates it and records collection
Then the sale and charge belong to that basket without a cycle, and collection records no second movement.

**S80 — Late supplementary basket** (R19, R22, R23b, R24b)
Given the weekly basket is validated and its cycle closed
When a member collects next day, reports extra honey taken, and Finance records a separate honey basket
with no cycle, validates it and marks it collected
Then the original basket/charge and cycle closure are unchanged, the new basket charges/sells honey once,
and its recording date is not the old planned pickup date.

**S81 — Insufficient stock rolls back validation** (R20, R22, R28)
Given two drafts each contain 3 jars while 5 are available and the first is validated
When Finance validates the second
Then validation fails, its basket stays draft and its Wallet is unchanged; available stock remains 2.

**S82 — Member self-collection is household-scoped** (R19a, R23b, R35)
Given Anna belongs to H, H has a validated pending basket and K another basket
When Anna marks H's basket collected and repeats the action
Then the original collection actor/time are retained and no charge/sale is repeated
And marking K's basket, validating a basket or editing contents is rejected.

**S83 — Close weekly cycle with pending collection** (R23a, R24, R24b)
Given distribution is finished and the last base basket is validated but not collected
When the weekly cycle closes
Then the pending warning/list and collection action remain visible to the authorized users
And a member can collect later without reopening the cycle or generating money/stock movements.

**S84 — Finance is additive and centrally granted** (R19a, R36a, R36c)
Given an active account without a household and a Manager
When Manager grants Finance
Then that account may log in and operate baskets/receipts, but not product CRUD, cycles or general inventory
And Manager retains Finance functions, a member-only account cannot grant Finance or validate,
and revoking Finance removes its added access under the existing login-role rules.

**S85 — Global manual receipt** (R21, R29–R32, R36c)
Given H has two basket charges totalling €30 and its global balance is −€30
When Finance confirms receipt of €50 by cash or bank transfer and records it
Then H's balance is €20 with one receipt; neither basket acquires a paid/part-paid status
And a receipt correction appends a justified movement without editing the receipt or basket charges.

**S86 — Corrections retain the validated original** (R23, R27, R32)
Given a validated stock basket and its immutable charge/sale
When Finance records a justified correction for goods actually returned and the corresponding amount
Then new linked stock/Wallet corrections are recorded together and original records remain unchanged
And a missing reason, stock-negative correction or replay cannot create an invalid/duplicate effect.

**S87 — Retained references establish use** (R1b, R1c, R19b)
Given a product appeared in a line later removed from an order or stock draft, or in a cancelled document
When the current view is read and Manager attempts to change its mode or its administrator deletes it
Then the inactive contents are absent from current totals but the retained history blocks both operations.

**S88 — Returns, zero total and no contents** (R20a, R22, R24a)
Given a return-only basket, a basket whose goods exactly match its return credit, and a source
with no positive goods, stock or return contents
When Finance processes their validation/no-contents state
Then the return-only basket credits the Wallet, the net-zero basket has no money movement but can be
collected, and the empty source needs no collection or charge and does not block cycle closure.

**S89 — Validation, not collection, freezes a basket** (R18, R23, R24b)
Given A's basket containing beef is validated but pending collection, B's is draft and the weekly cycle remains open
When a reopened reception changes beef's allocations
Then it is rejected exactly as for a collected basket, without rewriting A or reselling stock/returns
And a closed weekly cycle still rejects reception reopening.

**S91 — Successive late arrivals share one open draft** (R18, R19, R24b)
Given H's base is validated and its late weekly basket draft holds 1 kg cherries in an open cycle
When strawberries received as 0 arrive and the manager reopens their producer and allocates 1 kg to H
Then they are added to that late draft; H still has two weekly baskets and one charge per validation.

**S90 — retired (see history entry)**
