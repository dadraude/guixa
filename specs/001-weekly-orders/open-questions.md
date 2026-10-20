# 001 — Weekly orders: Open questions

Part of [001 — Weekly orders](spec.md): glossary, time and roles are defined there.

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
