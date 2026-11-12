# 004 — Dashboard: attention and opportunities

**Status: First-slice specification accepted (Q22, 2026-11-11).** Q14 selected this delivery boundary. Q1–Q21 in
[history](history/) establish the product direction; Q22 accepts the exact requirements and scenarios below.
Feature 002 remains retired. The independently shipped login destination (002 T05/R11) and interim
dashboard summary (001 T61) retain their ownership; this slice replaces the interim page content when accepted
and delivered. Feature 001 completion and 003 delivery readiness must be confirmed before implementation.

The signed-in dashboard puts permitted pending work first, then opportunities. It helps household members,
producers, and Manager, Finance, Catalogue and Receiver staff reach their existing task screens. It performs
no workflow command itself.

## Language

Terms such as household, member, producer order, cutoff, reception, validated, distributed, basket,
collection, cycle and wallet retain their meanings in [001](../001-weekly-orders/spec.md#glossary); role
powers come from [003](../003-role-management/spec.md). The terms below describe this page only and create no
domain entity.

| Term | Meaning |
| --- | --- |
| Pending work | An existing, not-yet-completed workflow action that this account is currently permitted to perform or help complete. |
| Opportunity | A permitted task destination available now without evidence that anything is unfinished. It is not an obligation. |
| Attention group | A compact row for one action kind in one cycle or date context, showing a count of distinct underlying work records and linking to their authorized work list or detail. |
| Scheduled date | A cycle's planned pickup date or a basket's existing optional cycle/pickup context, shown for orientation and ordering, not treated as a deadline. |
| Last updated | The time the dashboard's data was successfully read, in the cooperative's local timezone. |

## Requirements

### Access and page structure

- **R1 — Permission-scoped overview.** The page shows only data and destinations available to the signed-in
  account under its current household link, producer association and staff roles. Shared staff work is
  relevant without individual assignment. A member sees only its current household's items; a producer sees
  only its own producer's information. Manager includes Finance, Catalogue and reception powers; Finance,
  Catalogue and Receiver have only their accepted 001/003 powers. Accounts with several roles see one combined
  page. Direct destination links are still checked by the destination's own authorization rules.
- **R2 — One combined attention list.** Pending work leads the page. Similar work is grouped by action kind
  and cycle or scheduled-date context, with a distinct-record count, identifying label and permitted work
  destination. If several roles permit the same action on the same record, count it once and show one group,
  not one row per role. Distinct independently available actions on the same record may appear in different
  groups; the group wording names the action so the difference is clear. For an account that is both a
  household member and staff, collecting the same basket is one action/record, counted once in that context. A
  group never sums a staff-wide count and its member subset.
- **R3 — Navigation only.** Each pending group and opportunity links to the existing task list or detail
  screen where the person can inspect current state before acting. The dashboard never sends orders, changes
  quantities or availability, validates or distributes receptions or baskets, records collection or receipts,
  or executes any other state-changing command. If a link has gone stale, the destination shows its current
  state and refuses actions no longer permitted; dashboard text is not an authorization grant.

### Pending work

- **R4 — Member collection.** For a current member of a household, each of that household's validated baskets
  still pending physical collection is pending work, including a late basket, a stock basket with no cycle,
  and one whose cycle is closed. A draft is preparation, not ready to collect. A basket already collected or
  effectively left with nothing to collect after justified correction is absent. The member's destination is
  its own basket or pending basket list under 001 R23a/R23b/R24/R24a.
- **R5 — Reception review and allocation.** For every sent producer order whose reception is not distributed,
  Receiver, Finance and Manager see only the work their roles permit, including when the reception has not yet
  been opened. The work is identified by producer and cycle; dashboard reading does not open a reception. An
  unvalidated reception appears once as “check delivery and allocations”; for Finance/Manager its destination
  also supports validation after checking. Default received totals or balanced initial allocations do not
  prove physical checking. If allocations do not sum to received totals and therefore block distribution under
  001 R18, the group explicitly identifies that difference as work to resolve; do not confuse a difference
  between received totals and basket actuals under 001 R17 with the distribution gate. Receiver may open, set
  received totals and allocate but cannot validate, distribute or reopen. A validated, balanced reception
  waiting only for distribution is not Receiver pending work. A distributed reception is not pending unless a
  permitted reopen changes its state.
- **R6 — Reception distribution.** Finance and Manager see a validated reception whose allocations permit
  distribution as “mark distributed.” A validated reception with a blocking allocation difference is instead
  “resolve allocations,” until balanced; it is not described as ready to distribute. An unvalidated reception
  has the single review group in R5, not a separate validation group. A received-total change that clears
  validation updates the next dashboard read accordingly. The dashboard adds no receipt-confirmed state.
- **R7 — Basket preparation and validation.** Finance and Manager see existing, non-cancelled basket drafts
  with at least one active line, the condition under which 001 R19a/R22 accepts validation. Every such draft
  is validation work, named as validation. Preparation remains a defined action name, but no draft reaches it
  today: a draft is either validatable or has no active line. Validated, cancelled, nothing-to-collect and
  drafts with no active line are excluded from draft work. A zero-price or net-zero basket with effective
  goods or returns still requires validation; amount alone cannot remove it.
- **R8 — Staff collection.** Finance and Manager see every validated basket still pending collection that they
  can record, including closed-cycle, late and cycleless baskets (a supplemental basket is independent, has
  no cycle and takes the no-cycle context) and those of inactive households. Collection remains independent of wallet balance. An effective full contents reversal
  or a corrected collection mark changes eligibility under 001 R23/R24 on the next read. A member/Finance or
  member/Manager account does not get a second copy of its own basket collection action.
- **R9 — Manual sending.** Manager sees a participating producer's non-empty, unsent order only when the
  existing 001 R12a manual-send rules permit sending: the producer has closed for that cycle and has an
  account to notify. A still-open order, an already-sent order, a nonparticipating producer, or an empty order
  owned by the scheduler is absent. The dashboard does not present a cutoff passed before sending as an
  overdue send deadline; the cutoff governs ordering and send eligibility.
- **R10 — Automatic closure.** Cycle closure follows 001 R24b when settlement, distribution and weekly
  base/late basket conditions hold. It is never a dashboard task or manual action. The pending distribution
  and basket-validation groups remain visible while they are eligible and can lead to closure.

### Opportunities and ordering

- **R11 — Opportunities.** A member with an open ordering window sees the existing household order destination
  as an opportunity, with the cutoffs stored for the producers open in that window (a producer without a stored
  cutoff is not listed, so no deadline is inferred); a saved order is not labelled awaiting submission. A
  producer sees its own sent-order summary as a review opportunity, not a persistent delivery obligation, only
  for its own sent, non-cancelled cycles whose pickup date is today or later in the cooperative calendar.
  Catalogue sees its permitted product/availability maintenance destination as an opportunity; an inactive or
  cycle-unavailable product alone does not make pending work. These do not appear after the account loses the
  relevant household/producer/role access. Existing permitted role-area shortcuts remain available, including
  Catalogue and Receiver shortcuts from 003 R15; generic shortcuts do not manufacture additional pending tasks
  or deadline claims.
- **R12 — Priority and time language.** Pending work with an actual existing deadline comes first, then other
  pending work, then opportunities. Use actual existing rules to call an item overdue. A scheduled pickup date
  alone never makes reception, basket preparation or collection overdue; an ordering cutoff is an
  opportunity's end, not a deadline for an already-eligible manual send. All dates and times follow 001's
  cooperative-local timezone and daylight-saving rule.
- **R13 — Pending order.** Among pending groups without actual deadlines, earlier scheduled dates precede
  later ones; undated groups follow dated groups. For the same scheduled date, order action kinds as sending →
  reception review/resolution/validation/distribution → basket preparation/validation → collection. Within a
  stage use a stable human-readable context order, then the unique action-kind and cycle/context identifiers
  as final tie-breaks. Editing a display name does not change those identifiers. Group counts reflect distinct
  currently eligible records, not historical removed records. Opportunity order is stable and separate from
  pending priority.
- **R14 — Cross-cycle and empty state.** Include all permitted pending work across overlapping cycles and
  independent baskets. A scheduled date passing, a cycle closing, or a household becoming inactive does not
  hide an item that an authorized staff actor can still resolve. Label each cycle or no-cycle context clearly.
  When the account has no pending work, say it is caught up and keep its permitted opportunities and area
  shortcuts available. When it has no role-specific context, explain the absence rather than displaying a
  fabricated zero or trend. Cancelled weekly work and retained cancelled drafts do not become live work.
  An independent stock basket has no cycle attribution, so it shows in the explicit no-cycle context and
  remains eligible under its own state and permissions, as required by 001 R4b; no cycle's state can hide it.

### Freshness and presentation

- **R15 — Read freshness.** Read current persisted data when the dashboard first opens, when the person
  returns to it, and when they use an explicit refresh action. Show the time of the last successful read.
  While it stays open, the list does not refresh in the background. An in-progress or failed read must not
  turn an unknown result into “caught up”; keep an accessible loading or error message and a way to retry. A
  stale dashboard item may remain until refresh, but its destination always applies current state and
  permissions.
- **R16 — Accessible presentation.** New visible copy is translatable with Catalan as default. The combined
  list, opportunities, counts, dates, and refresh state remain understandable at 375 px, at 200% text, in
  light and dark appearance, and by keyboard. Do not use color or hover alone for meaning. Interactive targets
  are at least 44 × 44 px. A count and group action remain visible as text; no chart is required in this
  slice.

## Acceptance scenarios

**S1 — Combined permissions (R1–R3, R8).** Given an account is a member of H and has Finance and Receiver, and
H's basket is among staff-wide pending collections, when it opens the dashboard, then collection of that
basket is counted once, other households' pending baskets are visible only through Finance, and the account
sees reception work it can perform without duplicated role rows. Each link opens an authorized current work
screen.

**S2 — Current membership and producer privacy (R1, R11, R14).** Given a member moves from H to J and a
producer account is associated with P, when each opens the dashboard, then the member sees J's permitted order
and collection context but not H's, while the producer sees only P's sent-order summaries for pickups today or later and no household
names or Q's order.

**S3 — Member opportunity and collection (R4, R11, R12).** Given a member's household has an open ordering
window, a saved order and a validated basket awaiting collection, when the member opens the dashboard, then
collection is pending work before ordering opportunity; the order is not called unsubmitted; relevant cutoffs
are shown without making the collection overdue solely from its pickup date.

**S4 — Closed, late and cycleless collection (R4, R8, R14).** Given a closed-cycle basket, a late basket and a
cycleless independent stock basket remain effectively pending collection, when a current household member and
Finance open their dashboards, then each sees only the baskets its permissions allow; all remain actionable
despite date or cycle closure. Cancelled weekly orders do not become work, and a cycleless basket has an
explicit no-cycle context.

**S5 — Reception defaults and permissions (R5, R6).** Given a sent producer order whose reception has not yet
been opened, and another sent reception whose received totals and allocations still equal the ordered defaults
and no one has validated it, when Receiver and Finance open the dashboard, then each is represented as
check-delivery work by producer and cycle rather than calling the delivery checked, and reading the dashboard
does not open either reception; Receiver gets the permitted reception link but no validate/distribute claim;
Finance gets the validation destination.

**S6 — Blocking allocation and distributed state (R5, R6).** Given a reception with allocations that do not
total received quantity, when permitted staff open the dashboard, then its reception group identifies the
blocking allocation difference and it is not “ready to distribute”; an unvalidated reception has one review
group, not another validation group. Once Finance validates a balanced reception, it appears as ready for
Finance/Manager to distribute, not for Receiver; after distribution it leaves pending work. A variance against
basket actuals alone does not create this distribution blocker.

**S7 — Baskets and closure (R7, R10).** Given a settled cycle with a draft basket containing effective goods,
another draft with no contents, and a validated basket pending physical collection, when Finance opens the
dashboard, then it sees only the draft as validation work and the collection work, and the empty draft is
absent; zero money due does not by itself remove a goods basket; no “close cycle” action appears. When the
final weekly basket is validated or needs no collection, closure follows 001 R24b without a dashboard command.

**S8 — Eligible send and scheduler exclusion (R9, R12).** Given one non-empty participating order closed for
ordering with a producer account, one still open, one empty and one already sent, when Manager opens the
dashboard, then only the first is pending manual-send work; it links to the existing send screen, and a passed
cutoff is not labelled an overdue send deadline.

**S9 — Multiple cycles and order (R12–R14).** Given pending work in two cycles plus an older validated basket
awaiting collection from a closed cycle, and none has an actual deadline, when the dashboard opens, then all
remain visible; the older scheduled date sorts first, the later cycle follows by date, and same-date groups
follow sending, reception, baskets, collection. Undated cycleless collection follows dated work.

**S10 — Opportunities without false tasks (R11, R14).** Given a Catalogue account sees an intentionally
inactive product and a producer sees an existing sent summary, with no pending tasks either can do, when each
opens the dashboard, then both see a caught-up message plus their permitted maintenance/review opportunity and
role shortcut; neither inactive product nor summary is labelled unfinished work.

**S11 — Refresh and stale work (R3, R15).** Given another volunteer distributes a reception while this
dashboard stays open, then the old item remains until explicit refresh or return to the page; its link opens
the current distributed state without a stale command. After refresh, the item disappears and the last-updated
time advances. If refresh fails, the page does not claim that there is no pending work.

**S12 — Role and household changes (R1, R14, R15).** Given a Receiver role is removed or a member is unlinked
while their dashboard stays open, when they return or refresh, then inaccessible items and destinations
disappear; a previously displayed link cannot bypass current authorization. Staff obligations of an inactive
household remain visible to Finance/Manager under 001 R35a.

**S13 — Presentation (R16).** Given populated and caught-up dashboards, when checked at 375 px, at 200% text,
in light and dark appearance and by keyboard, then names, counts, dates, opportunity labels, link targets and
refresh status remain readable without horizontal page overflow, color-only or hover-only meaning, or targets
smaller than 44 × 44 px.

## Outside this slice

Insights accepted in discovery are later delivery slices: household spending/order changes, Finance
wallet/receipt context, producer demand history, Manager cycle progress, Receiver reception progress and
Catalogue availability summaries. Their reporting periods, denominators, charts and sparse-history treatment
will be specified when selected. This slice adds no reminder/notification delivery, new workflow state, manual
cycle closure, new payment status, producer invoice, online payment, work-shift assignment, low-variety alarm,
analytics platform, or direct write action. The existing login destination and role-area navigation continue
under 002 T05/R11 and 003 R15.

## Open questions

No unresolved product decision remains for this slice. Q22 accepts R1–R16 and S1–S13, including the
opportunity set and action-group identity in R2/R11, following the recorded independent whole-spec review.
