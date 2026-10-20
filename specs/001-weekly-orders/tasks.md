# 001 — Weekly orders · Tasks

Status: Draft · Spec: [spec.md](spec.md) + areas · Plan: [plan.md](plan.md)

How to work a task:

- One task = one vertical slice: domain → use case → repository (in-memory + Eloquent) → HTTP → Vue page,
  whatever the task needs. Each task ends in one commit.
- Test first: write the listed scenarios as failing Application tests, then make them pass.
- **Done when**: its scenarios and rules pass at the level stated, architecture tests (T02) stay green,
  and the whole suite passes.
- A task depends on the tasks listed in *After*; anything else can go in any order.
- A scenario split across tasks lists its part, e.g. "S5 (snapshot)"; the last task finishes it.

## Phase 0 — Foundation

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T01 | Bootstrap: Laravel 12 Vue starter kit, Postgres (`guixa`, `guixa_test`), `Guixa\` → `src/`, public registration off, scheduler, Pest + Vitest running | Constitution | — |
| T02 | Architecture tests A1–A8 in `tests/Architecture` (loop over contexts) and ESLint rules for A9 | A1–A9 | T01 |
| T03 | `Shared`: `Money` (half-up `multiply`), `Quantity` (thousandths, `ceilToWhole`), `Clock` with the cooperative's timezone (`SystemClock` from `COOP_TIMEZONE`, `FrozenClock`), `LocalTime`, `TransactionRunner` | R22 rounding, Time | T01 |
| T04 | `Actor` with several roles from the authenticated account, `role:<role>` middleware, domain error → 422 handler, shared Inertia layout with a switch between role areas | Roles, R36a | T01 |
| T05 | Test support: in-memory repository base and contract-test pattern shared with Eloquent repositories | Plan · Testing | T03 |

**Done when** T01: app boots on Postgres and the starter tests pass. T02: a deliberate violation in a
scratch class makes each rule fail, then is removed. T03: unit tests for rounding (e.g. 1.120 × €20.00 = €22.40),
negative quantity rejected, string ↔ thousandths mapping, and `LocalTime` on both daylight-saving days.

## Phase 1 — Membership

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T06 | `CreateHousehold`, `RenameHousehold`, `AddMember` + invitation email, `AcceptInvitation`, `ResendInvitation`, `ChangeInvitationEmail`; login refused until accepted; 7-day expiry; households admin pages | R33, R34, S25, S56 | T04, T05 |
| T07 | `MoveMember`, `UnlinkMember`, `GrantManagerRole`, `RemoveManagerRole`, `DeactivateAccount`, `ReactivateAccount`, accounts admin pages | R35, R36a, R36b, S43 (move), S44, S59 | T06 |

**Done when** T07: two concurrent `RemoveManagerRole` calls on the last two managers (integration test)
leave one manager.

## Phase 2 — Catalog

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T08 | `Producer` + `CutoffRule` (1–6 days), `CreateProducer`, `InviteProducerAccount`, `SetDefaultCutoff`, producers admin page and producer cutoff page | R5, R36 | T06 |
| T09 | Product CRUD by its producer (price ≥ 0, containers per unit), `ListProducerProducts`, producer products pages | R1 (fields), R2, S57 | T08 |
| T10 | `GetOrderableProduct`, `ListOrderableProducts`, `GetProducerCutoffRule` (internal queries) | R3 (catalog part) | T09 |

**Done when** T08: `CutoffRule` unit tests in two timezones (e.g. `Europe/Madrid`, `America/Mexico_City`),
including the weeks when daylight saving time changes (S38).

## Phase 3 — Cycles and ordering

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T11 | `SetPickupWeekday`, `Cycle`, `OpenCycle` with participants (cutoffs from rules, none if passed), `AddParticipant`, cycles admin page and settings page | R4, R4a (adding), R4c, R5, S6 (opening), S37 (weekday), S38 | T10 |
| T12 | `SetCutoff`, `CloseProducerNow`, `ClearCutoff`; open until the cutoff or the pickup date at 00:00 | R6, R6a, S6, S34 (move), S35 | T11 |
| T13 | `Order` ordering part, `SetOrderLine` (snapshot with containers, `addedBy`), `GetHouseholdOrder`, `Orders/Edit` page with running total | R7–R11, S1–S4, S5 (snapshot), S33, S49, S51 | T12, T07 |
| T14 | `ListMemberCycles`, `Orders/Index` page: open cycles, pending pickups, cancelled and closed cycles | R4a (open for members), R23a (before settlement) | T13 |
| T15 | `SetProductAvailability`, *will not be delivered* derived on read, `RemoveMarkedLine`, product deactivation; unit and variable flag locked while the product has unsettled lines (`HasUnsettledLines`) | R1 (lock), R3, R3b, R3c, S5 (lock), S32, S48 | T13 |
| T16 | `StopParticipating`, `ParticipateAgain`, `DeactivateProducer`, `ReactivateProducer` (producer role, participation where not sent) | R3a, R6c, S50 | T15 |
| T17 | `CancelCycle` (orders deleted, cycle shown as cancelled) | R4b, S37 | T13 |
| T18 | `SendProducerOrder` + producer email, cutoff frozen once sent, marked lines stored as not delivered, `SendEmptyProducerOrders` scheduled command | R6b, R12a, S34 (frozen), S36 | T16 |
| T19 | `GetProducerCycleSummary`, `Producer/CycleSummary` page with availability per product | R12, S7, S8 | T18 |
| T20 | `GetCycleSummary` (totals per producer and product, per household), `Manage/Cycles/Show` page | R13, S52 | T13 |

**Done when** T13: two concurrent `SetOrderLine` calls on the same order (integration test) leave both lines.
T18: a `SetOrderLine` racing a `SendProducerOrder` either lands before the send or is rejected.

## Phase 4 — Reception and distribution

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T21 | `ProportionalAllocator` domain service (tie by creation, then line id) | R15, S10–S12 (math), S31 (math) | T03 |
| T22 | `ReceivedTotal`, `GetReception`, `AddUnorderedProduct`, `SetReceivedTotal(overwriteManual?)`, `SetAllocation` (allocation-only lines), `Manage/Reception` page with the overwrite/cancel warning | R14–R17, S10–S12, S13 (balance), S31, S39, S40 (allocation) | T18, T21 |
| T23 | `ValidateReception`, `MarkProducerDistributed`, `ReopenProducerOrder` before settlement, settlement (drop lines, pending or nothing-to-collect pickups); receptions read-only when distributed | R17a, R18 (before settlement), R24, S13, S20, S27 (settlement), S28, S29, S40, S47 | T22 |

**Done when** T21: property test — for random claims and targets, Σ allocations == target, every allocation
is a multiple of the step, and no household gets more than one step above its exact share.

## Phase 5 — Stock

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T24 | `StockProduct` CRUD (price ≥ 0, containers per unit), `ReturnItem` CRUD, `Manage/Stock` and `Manage/ReturnItems` pages | R25 | T04, T05 |
| T25 | `RecordStockEntry`, `RecordStockAdjustment` (optional cycle), `ListStock`, `GetStockOffer`, `GetReturnItem`, `SellStock` (append-only movements, never below 0, row lock) | R26–R28, S23 | T24 |

## Phase 6 — Wallet

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T26 | `Movement` (top-up, charge, credit, correction), `RecordTopUp`, `RecordCorrection`, `ChargeForPickup`, `GetBalance`, `GetWalletStatement`, `Wallet/Statement` page and manager top-up/correction forms | R29–R32, S24 | T06 |

**Done when** T26: the partial unique index rejects a second charge or credit for the same household and cycle.

## Phase 7 — Pickup

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T27 | `GetPickup`, `OpenPickup` (stock-only pickup creates an empty order), `AdjustDeliveredQuantity`, pickups only once settled, `Manage/Pickups/Index` and `Manage/Pickups/Show`; member sees the pickup once settled | R19, R21, R23a, S27 | T23, T26 |
| T28 | `AddStockToPickup`, `RemoveStockFromPickup`, `AddReturnToPickup`, `RemoveReturnFromPickup` | R20, R20a, S17 | T27, T25 |
| T29 | `CompletePickup(topUp?)`: freeze, sell stock, top-up, charge or credit, all in one transaction | R21–R23, S9, S14–S16, S18, S19, S21, S22, S26, S41, S45, S53, S55 | T28 |
| T30 | `MarkPickupNotCollected`, closing the cycle, `ReopenProducerOrder` after settlement (done pickups kept, nothing-to-collect pickups reopened), no reopen once closed | R18 (after settlement), R24a, R24b, S30, S42, S46, S54 | T29 |
| T31 | `GetContainerCount` on `Manage/Cycles/Show` | R18a, S60 | T29, T20 |

**Done when** T29: integration tests show that a failure while selling stock rolls back the charge and
the top-up, and that two concurrent completions charge once.

## Phase 8 — Households

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T32 | `DeactivateHousehold` (warning first), `ReactivateHousehold`, automatic deactivation when the last member leaves, `ListHouseholdsToSettle` (balances from Wallet, pending pickups from Ordering) | R35a, S43, S58 | T07, T30 |

## Closure

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T33 | Traceability check: every `R#` and `S#` is named in at least one test (`->group('S14')` or test name); fill the gaps; set feature 001 to *Done* in `specs/README.md` | R1–R36b, S1–S60 | T14, T17, T19, T31, T32 |

## Coverage

| Scenarios | Task | Scenarios | Task | Scenarios | Task |
| --- | --- | --- | --- | --- | --- |
| S1–S4 | T13 | S23 | T25 | S43 | T07, T32 |
| S5 | T13, T15 | S24 | T26 | S44 | T07 |
| S6 | T11, T12 | S25 | T06 | S45 | T29 |
| S7, S8 | T19 | S26 | T29 | S46 | T30 |
| S9 | T29 | S27 | T23, T27 | S47 | T23 |
| S10–S12 | T21, T22 | S28, S29 | T23 | S48 | T15 |
| S13 | T22, T23 | S30 | T30 | S49 | T13 |
| S14–S16 | T29 | S31 | T21, T22 | S50 | T16 |
| S17 | T28 | S32 | T15 | S51 | T13 |
| S18, S19 | T29 | S33 | T13 | S52 | T20 |
| S20 | T23 | S34 | T12, T18 | S53 | T29 |
| S21, S22 | T29 | S35 | T12 | S54 | T30 |
| | | S36 | T18 | S55 | T29 |
| | | S37 | T11, T17 | S56 | T06 |
| | | S38 | T11 | S57 | T09 |
| | | S39 | T22 | S58 | T32 |
| | | S40 | T22, T23 | S59 | T07 |
| | | S41, S42 | T29, T30 | S60 | T31 |
