# UX Discovery Document: Guixa

**Date**: 2026-10-24
**Author**: draude + UX Superpowers
**Status**: Approved (2026-10-24)
**Scope**: The whole product (replacement for the cooperative's old ordering app)

Sources: answers given by the project owner during discovery (2026-10-24), the cooperative's numbers, and
`specs/001-weekly-orders`. No interviews or usage data yet; every claim is marked *stated*, *inferred* or
*assumption*.

## 1. Problem Brief

- **Problem**: Ordering through the cooperative's old web app is a hassle.
- **Affected users**: Households (primary), the volunteer managers, producers.
- **Current alternative**: An old web application that "does not fit the new use".
- **Why it fails** *(stated)*:
  1. Not usable from a phone.
  2. Built for one or two expert admins, while management is now shared by about 10 volunteers.
  3. Producers cannot keep their own catalog and prices; a manager does it for them.
  4. Inside the app, entering quantities is clunky and the order's state (saved? total? cutoff?) is unclear.
- **Scope**: New product replacing the old app. Feature 001 (Weekly orders) is its first slice.

### The cooperative in numbers *(stated)*

| | |
| --- | --- |
| Households | 20 in total, about 10 order each week |
| Producers | 10 in total, about 7 take part each week |
| Order size | About 20 lines, varies *(interpretation of "20, it depends")* |
| Managers | 10 volunteers, about 2 h a week each (about 20 person-hours per cycle) |
| Ordering time | Under 10 min per order in the old app |
| Pickup | Every household that ordered comes; about 10 min each to collect, check and pay |
| Stock | 6 stock products; several containers returned each week |
| Growth | No waiting list; possibly in the future |

What the numbers say *(inferred)*: volume per producer is tiny (1–2 households a week), half the households
help run the coop, and pickup already works well.

## 2. Personas

### Persona: The Weekly Planner — Primary

**Role & Context**: A member who orders for their household in a 20-household food coop; ordering is a weekly
chore next to meal planning.

**Behavioral Pattern**: Sits down once a week at a computer when the coop announces the cycle is open, goes through
the catalog and plans meals around it *(stated)*. Orders about 20 lines across several producers. Skips some weeks.

**Primary Goal**: Get the week's food ordered right, from the producers they like, with little effort.
**Emotional Goal**: In control — sure the order went in and what it costs.
**Social Goal**: A reliable member who supports local producers.

**Frustrations & Pain Points**:
- Entering quantities is clunky; there is no way to start from last week *(stated / assumption)*.
- Unclear whether the order is saved, what the total is and when it closes *(stated)*.
- Can only order from a computer *(stated as a reason the app no longer fits; current habit is computer)*.

**Current Tools & Workarounds**: The old web app on a computer; less digital members get help *(inferred)*.

**Context of Use**:
- **When**: Once a week, before the cutoff, triggered by a coop message *(stated)*.
- **Where**: At home.
- **Device/Platform**: Computer today; phone wanted *(assumption: mobile is hidden demand)*.
- **Constraints**: Mixed digital skills *(stated)* — design for the least confident member.

> "I don't mind planning the week's food. I mind fighting the website to do it."

**Evidence basis**: Behaviour and skill level validated by the project owner; pain points partly assumption.

### Persona: The Manager on Duty — Secondary

**Role & Context**: One of about 10 member-volunteers sharing the running of the coop, about 2 h a week each.

**Behavioral Pattern**: Does their share (sending producer orders, reception and allocation, pickups, wallets),
not the whole system.

**Primary Goal**: Finish their part of the cycle correctly in the time they gave.
**Emotional Goal**: Confident they didn't break anything, even though they don't do it often.
**Social Goal**: Pulling their weight without being the person everyone asks.

**Frustrations & Pain Points**:
- The old app assumes one or two experts *(stated)*.
- Infrequent tasks are easy to forget *(assumption)*.

**Context of Use**: Short, infrequent sessions; sometimes at the premises during reception or pickup, possibly on a
phone or tablet *(assumption)*.

> "Just tell me what's left to do this week and let me do it."

### Persona: The Small Producer — Tertiary

**Role & Context**: A local farm or maker for whom the coop is one small channel among several.

**Behavioral Pattern**: Takes part most weeks (7 of 10); gets a tiny order each time *(inferred from numbers)*.

**Primary Goal**: Keep catalog and prices current and receive a clear order on time.
**Emotional Goal**: The coop is low-effort, not one more admin chore.
**Social Goal**: A dependable supplier.

**Frustrations & Pain Points**: Cannot update their own catalog today *(stated)*; small volumes barely justify
the effort *(inferred)*.

**Context of Use**: Quick updates from a phone between jobs *(assumption)*.

> "If updating my prices takes more than a couple of minutes, I won't do it."

## 3. Jobs to Be Done

Scores are estimates from discovery answers, not measured. Opportunity = Importance + (Importance − Satisfaction).

### Primary job — Weekly Planner

**Statement**: When the coop announces this week's cycle is open, I want to put together my household's food from
what the producers offer, so I can plan our meals and support local producers without it taking over my evening.
**Importance**: 9 · **Current satisfaction**: 4 · **Opportunity**: 14

- **Functional**: Find what's available this week, choose quantities across producers, know the total.
- **Emotional**: In control — the order went in, and I know what it costs against the wallet.
- **Social**: Seen as a reliable member who supports the producers.

### Supporting jobs

| Job | Persona | Statement | Imp. | Sat. | Opp. |
| --- | --- | --- | --- | --- | --- |
| A. Don't miss the week | Weekly Planner | When the cutoff is near and I haven't ordered, I want a timely reminder, so I don't lose a week by accident. | 7 | 2 | 12 |
| B. Keep my catalog true | Small Producer | When my availability or prices change, I want to update my catalog myself in a couple of minutes, so households see what I really have. | 8 | 2 | 14 |
| C. Run my part of the cycle | Manager on Duty | When it's my turn, I want to see what's left to do this cycle and do it without knowing the system by heart, so the cycle runs whoever is on duty. | 8 | 3 | 13 |

### Why households skip a week *(stated)*

Forgetting or missing the cutoff, not needing anything that week, and an unappealing catalog. **Not** the effort of
ordering. The project owner chose to keep "ordering is a hassle" as the problem; the skip reasons are tracked as a
risk (section 7).

### Job map — primary job

| Stage | Action | Pain today | Opportunity |
| --- | --- | --- | --- |
| Define | Learns the cycle is open | Depends on a manual coop message | The app announces opening and cutoffs |
| Locate | Looks at this week's products | — | Show only this week's producers and available products |
| Prepare | Recalls what they usually get | Starts from scratch *(assumption)* | Start from last week's order |
| Execute | Enters about 20 lines | Clunky entry; computer only | Fast quantity entry, works on a phone |
| Confirm | Checks total against wallet | Saved state, total and cutoff unclear | Always-visible saved state, total, balance, cutoffs |
| Monitor | Edits until the cutoff | Per-producer cutoffs unclear | Each producer's cutoff shown on the order |
| Conclude | Knows what to pick up and when | — | Confirmation with the pickup date |

### Competing solutions

| Solution | Strengths | Weaknesses | Switching cost |
| --- | --- | --- | --- |
| The old app | Known, has the history | Old, no phone, no producer self-serve, needs expert admins | Medium (data, relearning) |
| Messaging a manager | No effort for the member | Shifts work onto volunteers; errors | Low |
| Not ordering this week (supermarket, market) | No planning at all | Doesn't support the coop | None — the real competitor |

## 4. User Journey Maps

### Current state — Weekly Planner, weekly order

| Phase | Action | Touchpoint | Feeling | Pain points | Duration |
| --- | --- | --- | --- | --- | --- |
| Awareness | Sees the cycle is open | Coop message (WhatsApp / email) | Neutral | Depends on a volunteer posting it; easy to miss | — |
| Getting in | Waits for computer time, opens the old app | Old web app, desktop | Neutral | No phone use | Hours to days of delay |
| Choose | Browses products | Old app list | Neutral | Not the main pain | 2–3 min |
| Enter | Enters about 20 quantities | Old app form | Frustrated | Clunky; no repeat of last week | 4–5 min |
| Confirm | Checks it saved, the total, the cutoff | Old app | Anxious | All three unclear | 1–2 min |
| Edge: missed | Remembers after the cutoff | Coop chat, or nothing | Frustrated | No reminder; the week is lost | — |
| Pickup | Collects, checks, pays | Premises | Satisfied | Works well | 10 min |
| Return | Waits for next week's message | Coop message | Neutral | The habit relies on someone else's post | — |

### Moments of truth

- **Make-or-break**: the coop message (missed → no order) and the cutoff (passed → the week is gone).
- **Peak pain**: entering about 20 quantities from scratch; not knowing whether the order is saved and what it costs.
- **Delight to preserve**: pickup in about 10 minutes.

### Desired state

| Phase | Action | Feeling | Design opportunity | Metric |
| --- | --- | --- | --- | --- |
| Awareness | Told by the app that the cycle is open | Neutral | Cycle-open message (roadmap: Notifications) | Households opening the cycle |
| Getting in | Opens the app wherever they are | Neutral | Same experience on phone and computer; stays signed in | Share of orders placed from a phone |
| Choose | Sees only this week's producers and products | Confident | Per-cycle catalog kept by producers (001: R1–R3c) | — |
| Enter | Starts from last week's order and adjusts | Relieved | "Repeat last order"; fast quantity steppers | Median active ordering time |
| Confirm | Always sees saved, total, balance, cutoffs | In control | Saved indicator; running total next to the wallet balance; cutoff per producer | Manager edits to orders; "did it go in?" questions |
| Edge: nearly missed | Gets a reminder before the cutoff | Relieved | Reminder to households without an order (job A) | Orders placed after a reminder |
| Pickup | Same as today | Satisfied | Don't add steps | Pickup time ≤ 10 min |

### Gap analysis

| Phase | Current | Desired | Gap | Priority | In 001? |
| --- | --- | --- | --- | --- | --- |
| Enter | Clunky, from scratch | Repeat and adjust | Quick entry + repeat last order | High | No |
| Confirm | Unclear state, total, cutoff | Always visible | Saved indicator, total vs balance, cutoffs | High | Partly (line-by-line save, running total) |
| Getting in | Desktop only | Phone and computer | Phone-ready UI for low-skill members | High | Not a requirement |
| Awareness / nearly missed | Manual coop message | App-sent opening and reminder | Notifications | Medium | No (roadmap) |
| Choose | Catalog kept by managers | Producers keep it current | Producer self-serve | Medium | Yes (R1–R3c) |
| Pickup | Works | Same | Don't regress | Guard | — |

## 5. Success Metrics & Telemetry

At 20 households one household moves a percentage by 5 points; read trends over several cycles, not single values.
Everything is measured from Guixa's own database — no third-party analytics.

### North Star — Hassle-free orders

- **Metric**: Share of household orders per cycle completed in ≤ 5 min of active editing and needing no manager
  edit or help.
- **Baseline**: Unknown (the old app can't measure it; orders take under 10 min). Established over Guixa's first 4
  cycles, plus one question to members about the old app.
- **Target**: ≥ 80%, sustained over 4 consecutive cycles, about 6 months after the switch.
- **Why**: Measures the chosen problem directly, per order, independent of whether a household needs food that week.

### Health metrics (guard rails)

| Metric | Guards against | Target | Alert |
| --- | --- | --- | --- |
| Pickup time per household | Breaking what works | ≤ 10 min | > 12 min two cycles in a row |
| Households ordering per cycle | Guixa pushing people away | ≥ 10 | < 8 two cycles in a row |
| Manager edits to household orders | Hassle moved onto volunteers | ≤ 1 per cycle | ≥ 3 in a cycle |
| "Did my order go in?" questions | Status pain returning | 0 | Any recurring question |

### Input metrics (leading indicators)

| Metric | Predicts | Measurement |
| --- | --- | --- |
| Orders started from "repeat last order" | Faster entry | Flag on the order |
| Orders placed or edited from a phone | Phone-ready is used | Device class (phone / desktop) on the edit, nothing finer |
| Orders placed after a reminder | Fewer missed weeks | Order time after the reminder (once notifications exist) |
| Products updated by their producer | Fresher catalog | Who last changed the product |

### Telemetry events (stored in PostgreSQL)

**`order_edited`**
- **Trigger**: A household's order is changed.
- **Properties**: `order_id`, `cycle_id`, `actor_role` (member / manager), `device_class` (phone / desktop),
  `started_from_repeat` (bool), `at`.
- **Purpose**: Active ordering time (first to last edit in a session; a 30-min gap splits sessions), device mix,
  how often managers step in.
- **Privacy**: Linked to a household the coop already knows; no third party; no consent banner. Kept 12 months,
  then aggregated.

**`reminder_sent`** (with Notifications)
- **Trigger**: The system sends a cycle-open message or a pre-cutoff reminder.
- **Properties**: `cycle_id`, `household_id`, `kind`, `at`.
- **Purpose**: Do reminders lead to orders?
- **Privacy**: Same as above.

**Pickup duration**: from the pickup's existing start and end moments (001).

### Qualitative feedback

| Signal | Method | Frequency | Owner |
| --- | --- | --- | --- |
| Ordering ease | One question after ordering ("How easy was this?" 1–5), at most once a month | Monthly | Managers' meeting |
| Usability | Watch 2–3 members order on a phone, including a less digital one | Before launch, then quarterly | Whoever builds Guixa |
| Issues | Coop assembly; a "something's wrong" link | Ongoing | Manager on duty |

### Decision framework

| Metric | Improving | Flat | Declining |
| --- | --- | --- | --- |
| Hassle-free orders | Move effort to Medium gaps (notifications) | Watch members order; find the slow step | Check entry and status UX first, then devices |
| Households per cycle | Plan for growth | Expected (driven by need and catalog); look at reminders and producers | Ask the households who stopped |
| Pickup time | — | Fine | Undo the change that slowed it |
| Manager edits | — | Fine | Find the step that makes members ask for help |

## 6. Design Principles

1. **Last week is the starting point**: An order should start from what the household usually gets, not from an
   empty list.
   - *Because*: About 20 lines each week; quantity entry is the peak pain.
   - *This means*: Repeat last order, fast steppers, sensible defaults.
   - *This means NOT*: Recommendations, carts across weeks, or a shop-style product browsing experience.

2. **Never leave them guessing**: The order's state is always visible — saved, total, wallet balance, cutoffs.
   - *Because*: "Unclear status" is a stated pain; the emotional goal is being in control.
   - *This means*: A visible saved state on every change; total and balance side by side; each producer's cutoff
     shown where you order from it.
   - *This means NOT*: A "Submit order" step that can be forgotten, or confirmation that only arrives by email.

3. **Designed for the least confident member, on the smallest screen**: If it works for a less digital member on a
   phone, it works for everyone.
   - *Because*: Mixed skills; the phone is a stated reason the old app no longer fits.
   - *This means*: Large touch targets, plain language, few screens, Catalan-first copy, tested on a phone.
   - *This means NOT*: Dense tables, hover-only actions, or desktop-first layouts that are "also responsive".

4. **Any volunteer can do it**: Every manager task can be done by someone who does it once a month.
   - *Because*: 10 volunteers share the work; the old app needed experts.
   - *This means*: Each cycle shows what's left to do and by whom; actions explain their consequence before they're
     irreversible.
   - *This means NOT*: Hidden admin screens, or knowledge that lives in one person's head.

5. **Small is a feature**: Built for 20 households and 10 producers, not for scale.
   - *Because*: No waiting list; 20 person-hours of volunteer time is the scarce resource.
   - *This means*: Simple choices, our own database for metrics, no extra infrastructure.
   - *This means NOT*: Multi-coop setups, analytics platforms, or optimizing for thousands of users.

## 7. Assumptions & Risks

### Validated (stated by the project owner)
- Members plan their order once a week on a computer, triggered by a coop message.
- Digital skills are mixed.
- The old app's pain inside ordering is quantity entry and unclear status; orders take under 10 min.
- Phones, shared management and producer self-serve are why the old app no longer fits.
- Pickup works well.

### Unvalidated
| Assumption | How to validate | Risk if wrong |
| --- | --- | --- |
| Members would order from a phone if it worked | Ask at the assembly; measure device class in the first cycles | Over-investing in mobile; desktop still matters |
| Most of an order repeats from week to week | Compare consecutive orders in the old app's data, if exportable | Repeat-last-order saves little |
| Managers often forget how to do infrequent tasks | Watch a manager do reception for the first time | Guidance UI that nobody needs |
| Producers will update their own catalog from a phone | Ask 2–3 producers | Catalog stays stale; managers keep doing it |
| "20" means about 20 lines per order | Confirm with the owner | Order entry is sized wrong |

### Known risks
| Risk | Likelihood | Impact | Mitigation |
| --- | --- | --- | --- |
| A smoother order form doesn't raise orders per cycle, because households skip for reasons other than effort | High | Medium | Track households per cycle separately from the North Star; prioritize Notifications next |
| Switching from the old app loses history or confuses members | Medium | High | Migrate only what's needed (households, balances, products); run one cycle in parallel or with help at pickup |
| Small numbers make metrics noisy | High | Low | Read 4-cycle trends; rely on observation as much as numbers |
| Pickup slows down because of new steps | Low | High | Guard rail ≤ 10 min; test a full pickup before launch |

## 8. Constraints & Boundaries

### Technical
- Laravel 13, Inertia 3 + Vue 3, PostgreSQL (see `specs/constitution.md`). One installation per cooperative.
- Web app, not native: phone support means a phone-first responsive UI.

### User
- Mixed digital skills; Catalan-speaking members (glossary keeps Catalan terms).
- Ordering at home; manager tasks sometimes at the premises.

### Business
- Run and built on volunteer time; no budget for tooling or analytics.

### Scope
- **In scope**: Ordering, reception and allocation, pickup, stock, wallet, producer self-serve catalog (001), plus the
  ordering-experience gaps above.
- **Out of scope**: Multi-coop, payments online, native apps, analytics platforms.
- **Future consideration**: Roadmap items in `specs/README.md`, with **Notifications** first in line given job A.

## 9. Research Summary

No interviews or usage data were provided. Inputs: the project owner's answers on scope, the old app, ordering
behaviour, skip reasons and pain points; the cooperative's numbers (section 1); and the 001 spec. The next research
step is to watch 2–3 members order and to ask producers about self-serve (section 7).

## 10. Recommended Next Steps

- [ ] Confirm what "20" means in order size, and whether the old app's orders can be exported (to test the
      repeat-order assumption).
- [ ] Spec change for 001 (through `spec-flow`): repeat last order, a visible saved state, and the wallet balance
      next to the running total. Or keep 001 as is and make these the next feature.
- [x] Add a phone-first UI rule to `specs/constitution.md` (principle 3), so every feature is checked on a phone.
      Done 2026-10-24 (constitution, UI section).
- [ ] Add the `order_edited` activity log to the plan so the North Star baseline starts with Guixa's first cycle.
- [ ] Before launch: watch 2–3 members, including a less digital one, place an order on a phone.
- [ ] Promote **Notifications** to the next roadmap feature (job A, the main skip reason).
