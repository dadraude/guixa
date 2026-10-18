# 001 — Weekly orders · Tasks

Status: Draft · Spec: [spec.md](spec.md) · Plan: [plan.md](plan.md)

How to work a task:

- One task = one vertical slice: domain → use case → repository (in-memory + Eloquent) → HTTP → Vue page,
  whatever the task needs. Each task ends in one commit.
- Test first: write the listed scenarios as failing Application tests, then make them pass.
- **Done when**: its scenarios and rules pass at the level stated, architecture tests (T02) stay green,
  and the whole suite passes.
- A task depends on the tasks listed in *After*; anything else can go in any order.

## Phase 0 — Foundation

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T01 | Bootstrap: Laravel 12 Vue starter kit, Postgres (`guixa`, `guixa_test`), `Guixa\` → `src/`, public registration off, Pest + Vitest running | Constitution | — |
| T02 | Architecture tests A1–A8 in `tests/Architecture` (loop over contexts) and ESLint rules for A9 | A1–A9 | T01 |
| T03 | `Shared`: `Money` (half-up `multiply`), `Quantity` (thousandths), `Clock` with the cooperative's timezone (`SystemClock` from `COOP_TIMEZONE`, `FrozenClock`), `TransactionRunner` | R22 rounding | T01 |
| T04 | `Actor` from the authenticated user, `role:<role>` middleware, domain error → 422 handler, shared Inertia layout per role | Roles | T01 |
| T05 | Test support: in-memory repository base and contract-test pattern shared with Eloquent repositories | Plan · Testing | T03 |

**Done when** T01: app boots on Postgres and the starter tests pass. T02: a deliberate violation in a
scratch class makes each rule fail, then is removed. T03: unit tests for rounding (e.g. 1.120 × €20.00 = €22.40),
negative quantity rejected, string ↔ thousandths mapping.

## Phase 1 — Membership

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T06 | `CreateHousehold`, `AddMember` + invitation email, `AcceptInvitation`, login refused until accepted | R33, R34, S25 | T04, T05 |
| T07 | `RemoveMember` (deactivate, history kept), `InviteProducerAccount`, households admin pages | R35, R36, D4 | T06, T08 |

## Phase 2 — Catalog

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T08 | `Producer` + `CutoffRule`, `CreateProducer`, `SetDefaultCutoff`, producers admin page and producer cutoff page | R5, R36 | T04, T05 |
| T09 | Product CRUD by its producer, `ListProducerProducts`, producer products pages | R1, R2 | T08 |
| T10 | `ListOrderableCatalog`, `GetOrderableProduct`, `ListCutoffRules` (internal queries) | R3 | T09 |

**Done when** T08: `CutoffRule` unit tests in two timezones (e.g. `Europe/Madrid`, `America/Mexico_City`),
including the weeks when daylight saving time changes.

## Phase 3 — Cycles and ordering

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T11 | `Cycle`, `OpenCycle` (cutoffs from rules), cycles admin page | R4, R5, S6 (first half) | T10 |
| T12 | `OverrideCutoff`, producer without cutoff is closed | R6, S6, D2, D3 | T11 |
| T13 | `Order` ordering part, `SetOrderLine`, `GetHouseholdOrder`, `Orders/Edit` page with running total | R7–R11, S1–S5 | T11, T06 |
| T14 | `ListOpenCycles`, `Orders/Index` page | R4 | T13 |
| T15 | `GetProducerCycleSummary`, `Producer/CycleSummary` page | R12, S7, S8 | T13 |
| T16 | `GetCycleSummary` (totals + per household), `Manage/Cycles/Show` page | R13 | T13 |

**Done when** T13: two concurrent `SetOrderLine` calls on the same order (integration test) leave both lines.

## Phase 4 — Reception and distribution

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T17 | `ProportionalAllocator` domain service | R15, S10, S11, S12 (allocation math), S31 (math) | T03 |
| T18 | `ReceivedTotal`, `GetReception`, `SetReceivedTotal(overwriteManual?)`, `SetAllocation`, `Manage/Reception` page with the overwrite/cancel warning | R14–R17, S10–S13, S31 | T16, T17 |
| T19 | `MarkCycleDistributed`, `ReopenDistribution`, receptions read-only when distributed | R18, S28, S29, S30 (reopen refused once T25 exists: test added there) | T18 |

**Done when** T17: property test — for random claims and targets, Σ allocations == target, every allocation
is a multiple of the step, and no household gets more than one step above its exact share.

## Phase 5 — Stock

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T20 | `StockProduct` CRUD, `Manage/Stock` pages | R25 | T04, T05 |
| T21 | `RecordStockEntry`, `RecordStockAdjustment`, `ListStock`, `GetStockOffer`, `SellStock` (append-only movements, never below 0, row lock) | R26–R28, S23 | T20 |

## Phase 6 — Wallet

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T22 | `Movement`, `RecordTopUp`, `RecordCorrection`, `ChargeForPickup`, `GetBalance`, `GetWalletStatement`, `Wallet/Statement` page and manager top-up/correction forms | R29–R32, S24 | T06 |

**Done when** T22: the partial unique index rejects a second charge for the same household and cycle.

## Phase 7 — Pickup

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T23 | `GetPickup`, `AdjustDeliveredQuantity`, pickups only when distributed, stock-only pickup creates an empty order, `Manage/Pickups/Index` and `Manage/Pickups/Show` | R19, R21, S27 | T19, T22 |
| T24 | `AddStockToPickup`, `RemoveStockFromPickup` | R20, S17 | T23, T21 |
| T25 | `ConfirmPickup(topUp?)`: freeze, sell stock, top-up, charge, all in one transaction; S30 test for reopen refused | R22–R24, R19 (pending), S9, S14–S16, S18–S22, S26, S30 | T24 |

**Done when** T25: integration tests show that a failure while selling stock rolls back the charge and
the top-up, and that two concurrent confirmations charge once.

## Closure

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T26 | Traceability check: every `R#` and `S#` is named in at least one test (`->group('S14')` or test name); fill the gaps; set feature 001 to *Done* in `specs/README.md` | R1–R36, S1–S31 | T07, T12, T14, T15, T25 |

## Coverage

| Scenario | Task | Scenario | Task | Scenario | Task |
| --- | --- | --- | --- | --- | --- |
| S1–S5 | T13 | S13 | T18 | S23 | T21 |
| S6 | T11, T12 | S14–S16 | T25 | S24 | T22 |
| S7, S8 | T15 | S17 | T24 | S25 | T06 |
| S9 | T25 | S18–S22 | T25 | S26 | T25 |
| S10–S12 | T17, T18 | | | S27 | T23 |
| | | | | S28, S29 | T19 |
| | | | | S30 | T19, T25 |
| | | | | S31 | T17, T18 |
