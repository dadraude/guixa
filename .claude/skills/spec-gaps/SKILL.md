---
name: spec-gaps
description: Hunt the gaps in a Guixa feature spec (`specs/NNN-*/spec.md`). Use when writing or editing a spec's requirements, or when reviewing a spec before accepting it.
---

A **gap** is a case the spec does not decide: a state with no way out, a rule that contradicts another,
an actor nobody accounted for. The gaps below are the families that reopened spec 001 (OQ1–OQ18);
each family names the question that would have caught them.

## Branches

- **Writing** (drafting or changing `R#`): for every requirement you write, walk the families. Answer each
  one that applies in the spec itself, as a requirement or a scenario. A gap you cannot answer becomes an
  open question. Done when every family that touches the new requirements is answered or recorded.
- **Reviewing** (a spec that claims to be complete): walk every family against every `R#`, scenario and
  glossary term. Done when each family has been checked against the whole spec, and every gap found is an
  open question.

Either way, finish by giving the user a coverage table: one row per family, with `answered (R#)`,
`OQ#` or `n/a (reason)`. The table goes in the reply, never in the spec. Then stop: editing the spec does
not authorize touching `plan.md` or `tasks.md` until the user gives the OK.

## Gap families

**G1 — Deadlines.** For every moment that changes what is allowed (a cutoff, a date, a confirmation):
what can still change once it passes, and who sees the effect? Can the moment be moved after it passed,
or moved into the past? What about an entity that has no such moment (created later)? Check that no
action can undo the precondition another requirement relies on. *(OQ1)*

**G2 — Defaults vs overrides.** A default rule obeys the same bounds as a manual override, and both
have a minimum and a maximum. *(OQ2)*

**G3 — Terminal states.** Every status has a final state reachable from every other state, including
"there is nothing to do" and "the actor never shows up". Nothing stays pending forever, and finished
things leave the working lists. *(OQ3)*

**G4 — Actors.** Can one person hold several roles with one login? Do cardinalities agree everywhere
(glossary "exactly one" vs requirement "at most one")? Who creates each kind of account, and the first
one? Can one actor act on another's behalf (a manager editing a household's order)? *(OQ4, OQ10)*

**G5 — Frozen records.** Every record that freezes has a way to fix a mistake in it, stated as new
appended records or as a reopen, with who can do it, until when, and its effect on whatever the
frozen record was locking. *(OQ6)*

**G6 — Deactivating and editing referenced things.** For every entity that can be deactivated, removed or
edited: who does it, can it come back, and what happens to the in-flight records that reference it
(open orders, pending pickups)? *(OQ8, OQ12.3)*

**G7 — Snapshots.** For every value copied at a moment (a price at ordering), list the other attributes
of the source that the copy depends on (unit, variable flag, name) and decide whether they are copied too.
Every "current" value says current *when*. *(OQ8.4, OQ12.1)*

**G8 — Conservation.** When quantities are split (ordered → received → allocated → delivered), every
difference has a destination: stock, loss, a charge. Goods outside the plan (delivered but never ordered,
given to someone who did not order) have a rule and a price. *(OQ5)*

**G9 — Determinism.** Every ordering or tie rule ends in a criterion that cannot tie, and says what editing,
removing and re-adding does to the ordering key. *(OQ13)*

**G10 — Boundaries.** Zero, empty, minimum, step and maximum for every amount and quantity: a price of
0, a charge of 0, an order of 0.001, a household with no members. *(OQ14, OQ15)*

**G11 — Time.** Every recorded date says whether it is the moment of recording or a date someone enters,
whether it may be past or future, and which one orders history. Local times that do not exist or repeat
on daylight-saving days resolve to one instant. *(OQ16, OQ18)*

**G12 — Visibility.** For each actor and each state, what they see. *(OQ17)*

**G13 — Aggregate lifecycle.** Every state word a requirement uses ("open", "touched", "active") is
defined. The aggregate can be created in the past or not, cancelled, rescheduled, and has a limit on how
many exist at once. *(OQ7, OQ9)*

**G14 — Membership lifecycle.** Join, move, leave and rejoin, for people and for groups: what happens to
the account, the unique email, the balance and the in-flight records. Invitations expire or not. *(OQ11)*

## Open question format

Append under `## Open questions` in the spec, numbered after the last `OQ#`, and mark the spec status as
reopened for them (see spec 001 for a worked example):

```markdown
### OQn — <the gap as a short title> (high | medium | low)

Blocks: R#, R# · S#, S#.

<What the spec says today and the concrete case it does not decide.>

Questions:

1. <One decision per question.>

Options:

- **(a)** <Option.> — <Its trade-off.>

Proposal: (a).
```

Severity: **high** breaks an invariant the rest of the spec relies on, **medium** is a missing business
decision, **low** is a precision. Options and a proposal only when there is a real choice; the user makes
the decision.
