# 001 — Weekly orders: log

Append-only. See `specs/README.md` for the entry kinds.

## 2026-10-20 · Request

The original request was not kept: it was given in a conversation before this log existed, and no copy
survives. The spec's opening summary (`spec.md`) is the closest record of what was asked; the spec as
written starts at commit `8d84116` (2026-10-19).

## 2026-10-20 · Change

Out of scope (`spec.md`): aligned with the roadmap in `specs/README.md`. Online payments removed,
membership fees became a cooperative fee on product prices, tracking returned containers as stock became
returning containers to their producers, producer confirmation lost its "(v2)" tag. No `R#` or `S#` changed.
Why: each row was reviewed one by one when the roadmap was written (PR #137). Online payments are not
planned because top-ups stay manual (R29). Merged in PR #138.

## 2026-10-20 · Change

`plan.md`: settlement after a producer is distributed became one Application service,
`CycleSettlement::afterDistributed`, shared by `MarkProducerDistributed` and `SendEmptyProducerOrders` (D10).
`CompletePickup` sells its stock lines in stock product id order. No `R#` or `S#` changed.
Why: plan review. Settlement was written only inside `MarkProducerDistributed`, so a cycle settled by the
scheduler would skip dropping lines and closing; stock locks taken in line order let two pickups deadlock.

## 2026-10-20 · Change

`tasks.md`: T18's `SendEmptyProducerOrders` only sends; validating, distributing and settling empty producer
orders moved to T23 with `CycleSettlement`. T30 adds closing and reopening to `CycleSettlement`. T29's
*Done when* adds a test that two pickups selling the same stock products in opposite order both complete.
Why: follows the `plan.md` change above; `CycleSettlement` does not exist before T23.

## 2026-10-21 · Q&A

Q: "How do you want to settle #1? Should a pickup adjustment also block re-distributing, or should that
balance check leave it out?" (R19 says a pickup adjustment "blocks nothing", R18 requires every product
balanced to distribute, so a reopened producer with an adjusted pickup could not be distributed again.)
A: "leave pickup adjustments out of the balance check"

## 2026-10-21 · Change

R18 (`reception.md`): the balance required to distribute does not count adjustments made at pickup.
R19 (`pickup.md`): states that redistributing after a reopen ignores the adjustment. New S61 (`pickup.md`).
Why: the Q&A above, found while reviewing `plan.md`.

## 2026-10-21 · Change

`plan.md`, after the R18/R19 change and a plan review:
- Order lines keep `delivered` apart from `allocated`; the reception's `balancedForDistribution` ignores pickup
  adjustments (D16, S61).
- New `Households` port from Ordering to Membership (`GetHouseholds`): household name and active status.
- First manager: `guixa:create-manager` command and `CreateFirstManager` (D17).
- Lines marked `notDelivered` or `dropped` count in no total, claim, reception or container count.
- Authorization failures throw `Shared\Domain\Error\NotAllowed`, mapped to 403.
- Orders are created with `INSERT … ON CONFLICT` and then locked.
- `OpenPickup` and `SetAllocation` check for an active household (`HouseholdInactive`); `OpenPickup` also
  checks that the cycle is settled and not closed.
- The note that an inactive household is reactivated moved from `UnlinkMember` to `AddMember` and
  `MoveMember`.
- `TransactionRunner::afterCommit`: emails go out after commit.
Why: Ordering had no way to read households (R13, R16, R19); nothing created the first manager (R36a);
`total()` and claims did not exclude dropped lines (R3b, R18); a 422 for a permission failure; two first lines
at once broke the unique order constraint; a closed cycle could get a new pickup (R24b); the reactivation note
was on a use case with no target household; an email could go out for a rolled-back write.

## 2026-10-21 · Change

`tasks.md`, following the `plan.md` change above: new T34 (first manager, after T06). T03 adds
`TransactionRunner::afterCommit`; T04 maps `NotAllowed` to 403. T13's *Done when* races two first lines of a
household with no order. T20 adds `GetHouseholds` and the `Households` port, so T22 now also runs after T20;
T22 checks for an active household and leaves not-delivered lines out of claims. T27: `OpenPickup` checks and
`delivered`. T29 and T31 use `delivered()` and skip dropped lines. T30 covers S61. T32 adds reactivation on
`AddMember` and `MoveMember`. T33 covers S1–S61 and runs after T34.
Why: the plan review above.

## 2026-10-21 · Change

`plan.md` and `tasks.md`: status Draft → Accepted. `specs/README.md`: feature 001 in progress, task status in
Linear (Coop, DRA-150–DRA-183).
Why: the user approved `tasks.md` after PR #141 (plan review) was merged; coding can start with T01.

## 2026-10-21 · Deviation

T01: the Laravel 13 Vue starter kit ships Vite+ (`vp`): Oxlint and Oxfmt instead of ESLint and Prettier,
with Vitest bundled. `constitution.md` (Stack, A9), `plan.md` (Bootstrap, Testing) and `tasks.md` (T01, T02)
now name Oxlint and Oxfmt; A9 is enforced with `no-restricted-imports` and `no-restricted-globals` (for
`fetch`), since Oxlint has no `no-restricted-syntax`. Oxfmt ignores `specs/**`, so it never rewrites
`log.md`.
Why: the user chose to keep the starter kit's toolchain as it ships rather than swap it back to ESLint and
Prettier (asked during T01).

## 2026-10-21 · Deviation

T03: `plan.md` (Shared) now states details it left open: `Clock` lives in `Shared\Domain` (A1 lets Domain use
only Shared Domain, and `LocalTime` consumers need it there); `FrozenClock` lives in `tests/Support`;
`Money::multiply` rounds a negative amount by magnitude (the spec only says "half up"), so a credit mirrors
the charge; `Quantity` is built with `fromString` / `ofThousandths` and throws `InvalidQuantity`; `LocalTime`
needs its own first-occurrence step because PHP picks the second occurrence of a repeated local time.
Why: implementing T03 test-first showed these gaps and one wrong assumption (PHP's native resolution).
