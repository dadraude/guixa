# 001 — Task responsibility and coverage allocation

Status: **proposed reslicing (2026-10-31)** of the accepted 2026-10-28 allocation, linked from the [canonical registry](../tasks.md).
This allocates work; it does not assert that tests exist or pass. Historical owners refer to the
[baseline](baseline.md). A new owner must adapt the assertions to current semantics. T63 checks the
final contracted interfaces; T33 audits every requirement/scenario semantically after all delivery.
Existing presentation tasks T37–T51 retain their regression/visual scopes and approval gates.

## Requirements

All 65 current requirement IDs, ordered as declared in the area specifications. Entries before a
semicolon identify retained work; the amendment owner implements the stated changed boundary.

| Rule | Delivery responsibility |
| --- | --- |
| [R1](../ordering.md) | [T09](../tasks.md#t09)/[T15](../tasks.md#t15); [T53](../tasks.md#t53) (protected edits) |
| [R1a](../ordering.md) | [T52](../tasks.md#t52) |
| [R1b](../ordering.md) | [T53](../tasks.md#t53)/[T55](../tasks.md#t55) |
| [R1c](../ordering.md) | [T53](../tasks.md#t53)/[T55](../tasks.md#t55) |
| [R2](../ordering.md) | [T09](../tasks.md#t09); [T52](../tasks.md#t52)/[T54](../tasks.md#t54) |
| [R3](../ordering.md) | [T10](../tasks.md#t10)/[T15](../tasks.md#t15); [T52](../tasks.md#t52) |
| [R3a](../ordering.md) | [T16](../tasks.md#t16); [T52](../tasks.md#t52)/[T55](../tasks.md#t55) (stock independence), [T57](../tasks.md#t57) (regression) |
| [R3b](../ordering.md) | [T15](../tasks.md#t15)/[T18](../tasks.md#t18); [T53](../tasks.md#t53) (removal history), [T59](../tasks.md#t59) (fulfilment) |
| [R3c](../ordering.md) | [T15](../tasks.md#t15); [T52](../tasks.md#t52) (weekly only) |
| [R4](../ordering.md) | [T11](../tasks.md#t11); [T55](../tasks.md#t55)/[T59](../tasks.md#t59) (purchase/validation boundary) |
| [R4a](../ordering.md) | [T11](../tasks.md#t11)/[T14](../tasks.md#t14) |
| [R4b](../ordering.md) | [T17](../tasks.md#t17); [T53](../tasks.md#t53) (archive), [T60](../tasks.md#t60) (independent baskets) |
| [R4c](../ordering.md) | [T11](../tasks.md#t11) |
| [R5](../ordering.md) | [T08](../tasks.md#t08)/[T11](../tasks.md#t11) |
| [R6](../ordering.md) | [T12](../tasks.md#t12) |
| [R6a](../ordering.md) | [T12](../tasks.md#t12) |
| [R6b](../ordering.md) | [T18](../tasks.md#t18) |
| [R6c](../ordering.md) | [T16](../tasks.md#t16)/[T18](../tasks.md#t18) |
| [R7](../ordering.md) | [T13](../tasks.md#t13) |
| [R8](../ordering.md) | [T13](../tasks.md#t13) |
| [R9](../ordering.md) | [T13](../tasks.md#t13); [T53](../tasks.md#t53) (archive) |
| [R10](../ordering.md) | [T13](../tasks.md#t13)/[T22](../tasks.md#t22) |
| [R11](../ordering.md) | [T13](../tasks.md#t13); [T55](../tasks.md#t55)/[T59](../tasks.md#t59) (negative balance) |
| [R12a](../ordering.md) | [T18](../tasks.md#t18)/[T23](../tasks.md#t23); [T59](../tasks.md#t59) (settlement) |
| [R12](../ordering.md) | [T19](../tasks.md#t19) |
| [R13](../ordering.md) | [T20](../tasks.md#t20)/[T35](../tasks.md#t35); [T53](../tasks.md#t53) (cancelled history) |
| [R14](../reception.md) | [T22](../tasks.md#t22); [T52](../tasks.md#t52) (weekly only), [T53](../tasks.md#t53) (first use) |
| [R15](../reception.md) | [T21](../tasks.md#t21)/[T22](../tasks.md#t22); [T53](../tasks.md#t53) (active tie), [T59](../tasks.md#t59) (redistribution integration) |
| [R16](../reception.md) | [T22](../tasks.md#t22); [T52](../tasks.md#t52) (weekly only) |
| [R17](../reception.md) | [T22](../tasks.md#t22); [T59](../tasks.md#t59) (actual/allocation separation) |
| [R17a](../reception.md) | [T23](../tasks.md#t23); [T59](../tasks.md#t59) (weekly guards) |
| [R18](../reception.md) | [T23](../tasks.md#t23)/[T30](../tasks.md#t30); [T59](../tasks.md#t59) |
| [R18a](../reception.md) | [T31](../tasks.md#t31); [T60](../tasks.md#t60) |
| [R19](../pickup.md) | [T55](../tasks.md#t55)/[T59](../tasks.md#t59)/[T60](../tasks.md#t60) |
| [R19a](../pickup.md) | [T55](../tasks.md#t55) (Manager), [T56](../tasks.md#t56) (Finance), [T65](../tasks.md#t65) (member reads), [T59](../tasks.md#t59) (weekly), [T61](../tasks.md#t61) (self-collection) |
| [R19b](../pickup.md) | [T53](../tasks.md#t53)/[T55](../tasks.md#t55)/[T57](../tasks.md#t57) |
| [R20](../pickup.md) | [T55](../tasks.md#t55)/[T57](../tasks.md#t57) |
| [R20a](../pickup.md) | [T24](../tasks.md#t24); [T57](../tasks.md#t57) |
| [R21](../pickup.md) | [T55](../tasks.md#t55) (basket amounts), [T64](../tasks.md#t64) (receipts), [T57](../tasks.md#t57) (returns), [T65](../tasks.md#t65) (member read), [T59](../tasks.md#t59) (weekly) |
| [R22](../pickup.md) | [T55](../tasks.md#t55)/[T57](../tasks.md#t57)/[T59](../tasks.md#t59) |
| [R23](../pickup.md) | [T58](../tasks.md#t58) (amount), [T68](../tasks.md#t68) (goods/effective quantities), [T59](../tasks.md#t59) (weekly) |
| [R23a](../pickup.md) | [T65](../tasks.md#t65) (read), [T59](../tasks.md#t59) (weekly), [T61](../tasks.md#t61) (action), [T67](../tasks.md#t67) (indicators) |
| [R23b](../pickup.md) | [T55](../tasks.md#t55) (staff collection), [T66](../tasks.md#t66) (correction), [T61](../tasks.md#t61) (member action) |
| [R24](../pickup.md) | [T67](../tasks.md#t67) |
| [R24a](../pickup.md) | [T55](../tasks.md#t55)/[T57](../tasks.md#t57) (contents), [T68](../tasks.md#t68) (reversal), [T59](../tasks.md#t59) (weekly empty), [T65](../tasks.md#t65) (read) |
| [R24b](../pickup.md) | [T59](../tasks.md#t59) (closure), [T60](../tasks.md#t60) (supplements), [T67](../tasks.md#t67) (indicators) |
| [R25](../stock-wallet.md) | [T24](../tasks.md#t24); [T52](../tasks.md#t52)/[T53](../tasks.md#t53) |
| [R25a](../stock-wallet.md) | [T53](../tasks.md#t53)/[T55](../tasks.md#t55) |
| [R25b](../stock-wallet.md) | [T52](../tasks.md#t52)/[T54](../tasks.md#t54) |
| [R25c](../stock-wallet.md) | [T54](../tasks.md#t54) |
| [R26](../stock-wallet.md) | [T25](../tasks.md#t25); [T52](../tasks.md#t52) |
| [R27](../stock-wallet.md) | [T25](../tasks.md#t25); [T52](../tasks.md#t52)/[T68](../tasks.md#t68) |
| [R28](../stock-wallet.md) | [T25](../tasks.md#t25); [T54](../tasks.md#t54)/[T55](../tasks.md#t55)/[T68](../tasks.md#t68) |
| [R29](../stock-wallet.md) | [T26](../tasks.md#t26); [T64](../tasks.md#t64) |
| [R30](../stock-wallet.md) | [T26](../tasks.md#t26); [T55](../tasks.md#t55)/[T64](../tasks.md#t64) |
| [R31](../stock-wallet.md) | [T26](../tasks.md#t26); [T55](../tasks.md#t55) (source), [T56](../tasks.md#t56) (Finance read), [T64](../tasks.md#t64) (receipts), [T65](../tasks.md#t65) (member read), [T58](../tasks.md#t58)/[T68](../tasks.md#t68) (adjustments) |
| [R32](../stock-wallet.md) | [T26](../tasks.md#t26); [T64](../tasks.md#t64) (receipt), [T58](../tasks.md#t58) (amount), [T68](../tasks.md#t68) (goods) |
| [R33](../stock-wallet.md) | [T06](../tasks.md#t06) |
| [R34](../stock-wallet.md) | [T06](../tasks.md#t06) |
| [R35](../stock-wallet.md) | [T07](../tasks.md#t07); [T65](../tasks.md#t65) (read), [T61](../tasks.md#t61) (action), [T67](../tasks.md#t67) (pending), [T62](../tasks.md#t62) (lifecycle) |
| [R35a](../stock-wallet.md) | [T32](../tasks.md#t32); [T59](../tasks.md#t59) (adapter), [T62](../tasks.md#t62) (lifecycle) |
| [R36](../stock-wallet.md) | [T08](../tasks.md#t08)/[T09](../tasks.md#t09) |
| [R36a](../stock-wallet.md) | [T04](../tasks.md#t04)/[T07](../tasks.md#t07)/[T34](../tasks.md#t34); [T56](../tasks.md#t56) |
| [R36b](../stock-wallet.md) | [T07](../tasks.md#t07); [T56](../tasks.md#t56)/[T62](../tasks.md#t62) |
| [R36c](../stock-wallet.md) | [T56](../tasks.md#t56) (role/baskets), [T64](../tasks.md#t64) (receipts) |

## Scenarios

A split scenario completes only after all listed parts pass; no slice claims the other part merely
because it cites the same ID. A dash in the amendment column means retain the existing acceptance
behavior and verify its regression through the new final contracts, not that the scenario is optional.

| Scenario | Historical owner(s) | Amendment owner(s) |
| --- | --- | --- |
| [S1 — Order before cutoff](../ordering.md) | [T13](../tasks.md#t13) | — (retained) |
| [S2 — Order after cutoff](../ordering.md) | [T13](../tasks.md#t13) | — (retained) |
| [S3 — Mixed cutoffs](../ordering.md) | [T13](../tasks.md#t13) | — (retained) |
| [S4 — Shared household order](../ordering.md) | [T13](../tasks.md#t13) | — (retained) |
| [S5 — Product snapshot](../ordering.md) | [T13](../tasks.md#t13), [T15](../tasks.md#t15) | [T52](../tasks.md#t52)/[T53](../tasks.md#t53) (catalogue guards) |
| [S6 — Cutoff override](../ordering.md) | [T11](../tasks.md#t11), [T12](../tasks.md#t12) | — (retained) |
| [S7 — Producer privacy](../ordering.md) | [T19](../tasks.md#t19), [T40](../tasks.md#t40) | — (retained) |
| [S8 — No aggregate before sending](../ordering.md) | [T19](../tasks.md#t19), [T40](../tasks.md#t40) | — (retained) |
| [S9 — Overlapping cycles](../ordering.md) | [T29](../tasks.md#t29) | [T59](../tasks.md#t59) |
| [S10 — Proportional shortage](../reception.md) | [T21](../tasks.md#t21), [T22](../tasks.md#t22) | — (retained) |
| [S11 — Proportional surplus on a variable product](../reception.md) | [T21](../tasks.md#t21), [T22](../tasks.md#t22) | — (retained) |
| [S12 — Weight split with leftover grams](../reception.md) | [T21](../tasks.md#t21), [T22](../tasks.md#t22) | — (retained) |
| [S13 — Manual allocation](../reception.md) | [T22](../tasks.md#t22), [T23](../tasks.md#t23) | — (retained) |
| [S14 — Variable quantity before basket validation](../pickup.md) | [T29](../tasks.md#t29) | [T59](../tasks.md#t59) |
| [S15 — Allocation drives the initial basket charge](../pickup.md) | [T29](../tasks.md#t29) | [T59](../tasks.md#t59) |
| [S16 — Adding stock](../pickup.md) | [T29](../tasks.md#t29) | [T55](../tasks.md#t55) (stock), [T57](../tasks.md#t57) (regression), [T59](../tasks.md#t59) (weekly) |
| [S17 — Not enough stock at addition](../pickup.md) | [T28](../tasks.md#t28) | [T55](../tasks.md#t55) (implemented candidate), [T57](../tasks.md#t57) (regression) |
| [S18 — Stock-only basket](../pickup.md) | [T29](../tasks.md#t29) | [T55](../tasks.md#t55) |
| [S19 — Independent receipt around basket validation](../pickup.md) | [T29](../tasks.md#t29) | [T64](../tasks.md#t64) (receipt), [T59](../tasks.md#t59) (weekly validation integration) |
| [S20 — Nothing to collect](../pickup.md) | [T23](../tasks.md#t23) | [T59](../tasks.md#t59) |
| [S21 — Negative balance](../pickup.md) | [T29](../tasks.md#t29) | [T55](../tasks.md#t55) |
| [S22 — Double validation](../pickup.md) | [T29](../tasks.md#t29) | [T55](../tasks.md#t55) |
| [S23 — Entries and adjustments](../stock-wallet.md) | [T25](../tasks.md#t25) | [T52](../tasks.md#t52) (mode guard) |
| [S24 — Receipt and correction](../stock-wallet.md) | [T26](../tasks.md#t26), [T40](../tasks.md#t40) | [T64](../tasks.md#t64) |
| [S25 — Member invitation](../stock-wallet.md) | [T06](../tasks.md#t06) | — (retained) |
| [S26 — Late physical collection](../pickup.md) | [T29](../tasks.md#t29) | [T61](../tasks.md#t61) (action), [T59](../tasks.md#t59) (weekly integration), [T67](../tasks.md#t67) (visibility) |
| [S27 — No weekly base before settlement](../reception.md) | [T23](../tasks.md#t23), [T27](../tasks.md#t27) | [T59](../tasks.md#t59) |
| [S28 — Settlement ignores non-participating producers](../reception.md) | [T23](../tasks.md#t23) | [T59](../tasks.md#t59) |
| [S29 — Reopen before settlement](../reception.md) | [T23](../tasks.md#t23) | [T59](../tasks.md#t59) |
| [S30 — Late delivery after settlement](../reception.md) | [T30](../tasks.md#t30) | [T59](../tasks.md#t59) |
| [S31 — Way A over manual allocations](../reception.md) | [T21](../tasks.md#t21), [T22](../tasks.md#t22) | [T53](../tasks.md#t53) (active-line tie) |
| [S32 — Deactivated product](../ordering.md) | [T15](../tasks.md#t15) | [T52](../tasks.md#t52) (mode boundary) |
| [S33 — Member without household](../ordering.md) | [T13](../tasks.md#t13) | — (retained) |
| [S34 — Reopening a passed cutoff](../ordering.md) | [T12](../tasks.md#t12), [T18](../tasks.md#t18) | — (retained) |
| [S35 — Close now and no cutoff](../ordering.md) | [T12](../tasks.md#t12) | — (retained) |
| [S36 — Sending needs a closed participating producer](../ordering.md) | [T18](../tasks.md#t18) | — (retained) |
| [S37 — Cancel a cycle, fixed pickup weekday](../ordering.md) | [T11](../tasks.md#t11), [T17](../tasks.md#t17), [T18](../tasks.md#t18) | [T53](../tasks.md#t53) (retained cancellation) |
| [S38 — Daylight saving](../ordering.md) | [T11](../tasks.md#t11) | — (retained) |
| [S39 — Allocation to a household that did not order](../reception.md) | [T22](../tasks.md#t22) | [T59](../tasks.md#t59) |
| [S40 — Product nobody ordered](../reception.md) | [T22](../tasks.md#t22), [T23](../tasks.md#t23) | [T59](../tasks.md#t59) |
| [S41 — Returning jars](../pickup.md) | [T29](../tasks.md#t29) | [T57](../tasks.md#t57) |
| [S42 — Missing the planned day is not abandonment](../pickup.md) | [T30](../tasks.md#t30) | [T59](../tasks.md#t59) (closure), [T67](../tasks.md#t67) (pending) |
| [S43 — Moving a member](../stock-wallet.md) | [T07](../tasks.md#t07), [T32](../tasks.md#t32) | [T62](../tasks.md#t62) |
| [S44 — Several roles](../stock-wallet.md) | [T07](../tasks.md#t07) | — (retained) |
| [S45 — Stock sold by another basket](../pickup.md) | [T29](../tasks.md#t29) | [T55](../tasks.md#t55) |
| [S46 — No reopening once closed](../reception.md) | [T30](../tasks.md#t30) | [T59](../tasks.md#t59) |
| [S47 — Validate before distributing](../reception.md) | [T23](../tasks.md#t23) | [T59](../tasks.md#t59) |
| [S48 — Unavailable in one cycle](../ordering.md) | [T15](../tasks.md#t15) | [T52](../tasks.md#t52) (mode boundary) |
| [S49 — Quantity rules](../ordering.md) | [T13](../tasks.md#t13) | — (retained) |
| [S50 — Deactivating a producer](../ordering.md) | [T16](../tasks.md#t16), [T18](../tasks.md#t18) | [T52](../tasks.md#t52) (stock independence) |
| [S51 — Participation per cycle](../ordering.md) | [T13](../tasks.md#t13) | — (retained) |
| [S52 — Manager totals](../ordering.md) | [T20](../tasks.md#t20) | — (retained) |
| [S53 — Rounding per line](../pickup.md) | [T29](../tasks.md#t29) | [T55](../tasks.md#t55)/[T57](../tasks.md#t57) (stock/returns), [T59](../tasks.md#t59) (weekly) |
| [S54 — Closed cycle with pending collection](../pickup.md) | [T30](../tasks.md#t30) | [T59](../tasks.md#t59) (closure), [T67](../tasks.md#t67) (indicator) |
| [S55 — Stock line survives product deactivation](../pickup.md) | [T29](../tasks.md#t29) | [T55](../tasks.md#t55) (snapshot), [T57](../tasks.md#t57) (regression) |
| [S56 — Only managers create households](../stock-wallet.md) | [T06](../tasks.md#t06) | — (retained) |
| [S57 — Producer account](../stock-wallet.md) | [T09](../tasks.md#t09), [T40](../tasks.md#t40) | — (retained) |
| [S58 — Deactivated household retains basket obligations](../stock-wallet.md) | [T32](../tasks.md#t32) | [T62](../tasks.md#t62) |
| [S59 — Deactivating an account](../stock-wallet.md) | [T07](../tasks.md#t07) | [T62](../tasks.md#t62) (access regression) |
| [S60 — Counting containers](../reception.md) | [T31](../tasks.md#t31) | [T60](../tasks.md#t60) |
| [S61 — Redistributing after an actual-quantity adjustment](../pickup.md) | [T30](../tasks.md#t30) | [T59](../tasks.md#t59) |
| [S62 — Totals of a cancelled cycle](../ordering.md) | [T35](../tasks.md#t35), [T41](../tasks.md#t41) | [T53](../tasks.md#t53) (archive regression) |
| [S63 — A validated basket blocks redistribution of its product](../reception.md) | [T30](../tasks.md#t30) | [T59](../tasks.md#t59) |
| [S64 — Redistributing a product no validated basket contains](../reception.md) | [T30](../tasks.md#t30) | [T59](../tasks.md#t59) |
| [S65 — A product stays with its producer](../ordering.md) | — | [T52](../tasks.md#t52) |
| [S66 — Separate weekly and stock records](../ordering.md) | — | [T52](../tasks.md#t52) (separate modes), [T54](../tasks.md#t54) (visibility) |
| [S67 — Only the manager changes an unused classification](../ordering.md) | — | [T53](../tasks.md#t53) |
| [S68 — First use is not undone by removing lines](../ordering.md) | — | [T53](../tasks.md#t53)/[T55](../tasks.md#t55) (retained references) |
| [S69 — Reception is a first use](../ordering.md) | — | [T53](../tasks.md#t53) |
| [S70 — Delete only unused products](../ordering.md) | — | [T53](../tasks.md#t53) |
| [S71 — The manager administers stock products](../stock-wallet.md) | — | [T52](../tasks.md#t52) |
| [S72 — Stock units and variable flag are fixed after use](../stock-wallet.md) | — | [T53](../tasks.md#t53) (movements), [T55](../tasks.md#t55) (basket history) |
| [S73 — Members consult stock without reserving it](../stock-wallet.md) | — | [T54](../tasks.md#t54) |
| [S74 — A producer consults only their own stock](../stock-wallet.md) | — | [T54](../tasks.md#t54) |
| [S75 — Remaining stock from an inactive producer](../pickup.md) | — | [T55](../tasks.md#t55) (stock independence), [T57](../tasks.md#t57) (regression) |
| [S76 — Weekly products cannot be stocked or sold as stock](../pickup.md) | — | [T52](../tasks.md#t52) (inventory), [T55](../tasks.md#t55) (sale), [T57](../tasks.md#t57) (regression) |
| [S77 — Stock cannot enter through reception](../reception.md) | — | [T52](../tasks.md#t52) |
| [S78 — Automatic draft, explicit Finance validation](../pickup.md) | — | [T56](../tasks.md#t56) (Finance), [T59](../tasks.md#t59) (base) |
| [S79 — Independent stock purchase](../pickup.md) | — | [T55](../tasks.md#t55) (Manager), [T56](../tasks.md#t56) (Finance) |
| [S80 — Late supplementary basket](../pickup.md) | — | [T60](../tasks.md#t60) |
| [S81 — Insufficient stock rolls back validation](../pickup.md) | — | [T55](../tasks.md#t55) |
| [S82 — Member self-collection is household-scoped](../pickup.md) | — | [T61](../tasks.md#t61) |
| [S83 — Close weekly cycle with pending collection](../pickup.md) | — | [T59](../tasks.md#t59) (closure), [T67](../tasks.md#t67) (indicator) |
| [S84 — Finance is additive and centrally granted](../pickup.md) | — | [T56](../tasks.md#t56) (role/baskets), [T64](../tasks.md#t64) (receipts) |
| [S85 — Global manual receipt](../pickup.md) | — | [T64](../tasks.md#t64) (receipt/correction), [T58](../tasks.md#t58)/[T68](../tasks.md#t68) (regression) |
| [S86 — Corrections retain the validated original](../pickup.md) | — | [T68](../tasks.md#t68) |
| [S87 — Retained references establish use](../pickup.md) | — | [T53](../tasks.md#t53) (orders), [T55](../tasks.md#t55) (baskets) |
| [S88 — Returns, zero total and no contents](../pickup.md) | — | [T57](../tasks.md#t57) (returns/real contents), [T59](../tasks.md#t59) (weekly empty) |
| [S89 — Validation, not collection, freezes a basket](../pickup.md) | — | [T59](../tasks.md#t59) |
| S90 — retired | — | — |
| [S91 — Successive late arrivals share one open draft](../pickup.md) | — | [T59](../tasks.md#t59) |
| [S92 — Container balance accumulates per household](../reception.md) | — | [T60](../tasks.md#t60) |

## Required checks beyond scenario identifiers

| Invariant | Owning task(s) |
| --- | --- |
| First-reference/edit/delete race; retained-reference and active-reader contracts | T53/T55 |
| Atomic validation rollback, oversell prevention, ordered multi-product locks and replay | T55 |
| Finance/Manager capabilities and revocation; independent confirmed receipts | T56 / T64 respectively |
| Negative/net-zero/return totals and inactive-line snapshots | T57/T59 |
| Amount-only corrections; physical stock/money atomicity, originals and replay | T58 / T68; T59 weekly integration |
| Collection correction with retained history and no financial effects | T66 |
| Settlement/source-sync/validation lock, immutable financial freeze, effective redistribution | T59 |
| Current own-household reads, duplicate collection and late/closed indicators | T65 / T61 / T67; T62 lifecycle |
| Fresh final schema, no duplicate metadata/financial writers or retired live routes | T63 |
| Semantic R#/S# audit, T36 and visual integration, exact-candidate CI | T33 |

These checks are implementation obligations, not checks run while editing task documents.

T55 attribution above is based on reviewed candidate `a9c36d8`, still unmerged at this draft.
Successors verify its actual integrated result before relying on that evidence; no new completion claim
is made. Each delivery returns assertion-level evidence, including missing/partial cases, for T33.
