# Dashboard discovery — 2026-10-27

**Status:** Interview complete; assembled experience confirmed by the user on 2026-10-27. This records evidence and settled decisions behind the proposed [dashboard spec](../../specs/002-actionable-dashboard/spec.md). It is not an accepted requirement.

## Request

> I want to improve the dashboard with useful information, charts and direct access actions.

The request follows the accepted reference-style refresh. That work changes presentation; this dashboard request may add information, queries and actions, so it needs its own product decisions and acceptance scenarios before implementation.

## Current behavior and constraints

- `DashboardController` detects manager and member roles; `Dashboard.vue` also detects producer roles. A single account may hold several roles, and the page currently displays each applicable section.
- Members see next pickup date, wallet balance, number of open cycles and a pending-pickups date list. Their direct links are Orders and Wallet.
- Managers receive counts of cycles, active cycles, households, active households, accounts, pending invitations, producers and active producers. The page shows only total cycles, households and accounts. It links to seven manager index/settings pages.
- Producers see links to Products and Default cutoff, but no producer-specific summary on the dashboard.
- The controller obtains manager counts through existing list use cases. It does not provide a historical series or a prioritized work queue. The current dashboard frontend and HTTP tests cover rendering and access, not actionable information or trends.
- Existing reads can supply more context, but are scoped to task pages: `ListCycles` exposes cycle dates and participant cutoff/open states; `GetCycleSummary` exposes producer/product/household order totals for one cycle; `GetProducerCycleSummary` exposes a producer's own offer or sent quantities; `ListPickups` exposes a settled cycle's household pickup statuses. A dashboard task queue or trend would need an explicit read model and authorization contract rather than using display-only arithmetic on partial props.
- The [UX discovery](2026-10-24-guixa-discovery.md) describes a small cooperative and volunteer managers who may do an infrequent task once a month. It warns that small numbers make metrics noisy and suggests reading trends across four cycles. Its proposed `order_edited` and `reminder_sent` telemetry is absent from the current source; these events cannot be treated as available dashboard data.
- The reference-style rollout is now merged across the app. Its Dashboard change is presentational (`dashboard-summary` styling); the content and props described here remain. This discovery concerns dashboard **content and behavior**, so it needs its own spec rather than being treated as a follow-up CSS change.

## Decisions

1. **Audience (answered 2026-10-27):** Yes, make the dashboard role-aware for managers, members and producers. A mixed-role account sees a priority-ordered combined view. This settles audience scope, not the order or content of those sections.
2. **Purpose (answered 2026-10-27):** Yes, the dashboard first answers “What needs my attention now?” Charts are included only when they help that decision; detailed reporting belongs on the relevant task pages. This does not yet choose the tasks or charts.
3. **Manager priority (answered 2026-10-27):** Yes, put the next cycle's unfinished work—cutoffs, sending producer orders, reception, distribution and pickups—ahead of cooperative totals. This does not yet define the exact readiness states or ordering among simultaneous tasks.
4. **Member priority (answered 2026-10-27):** Yes, show the member's next open order or pickup first, with wallet balance as supporting context. This does not imply an order submission state; ordering currently saves as members edit.
5. **Producer priority (answered 2026-10-27):** Yes, show the next cutoff and any sent order to deliver first, with product availability as supporting context. A producer sees only its own order information and never household names.
6. **Direct access (answered 2026-10-27):** Yes, prominent dashboard actions are deep links to the relevant task screen. The dashboard does not perform state-changing mutations; the destination provides full context before the user acts.
7. **Focus cycle (answered 2026-10-27):** Yes, the manager view leads with the earliest non-cancelled cycle that still has unfinished work, including overdue work. The exact task-state query will be specified from the domain model.
8. **Participation trend (answered 2026-10-27):** Yes, include a secondary chart of the number of households that ordered in each of the last four completed cycles, once four comparable cycles exist. Decisions 12, 13 and 22 define the population, presentation and absence of a low-participation signal.
9. **Multiple roles (answered 2026-10-27):** Yes, use one combined “Needs attention” list with role labels and no duplicate cards, followed by role-specific context. Decision 15 defines its broad priority order.
10. **Sparse data (answered 2026-10-27):** Yes, explain when there is no work or insufficient history, and omit a chart that cannot support a useful comparison. Do not render an empty chart frame or imply missing data is zero.
11. **Manager action states (answered 2026-10-27):** Yes, the main list contains steps a manager can act on now: unsent producer orders after cutoff, reception needing validation or distribution, then pending pickups. Future cutoffs are context, not unfinished tasks.
12. **Trend population (answered 2026-10-27):** “Completed cycle” means the spec's **closed** state. Count a household when its order contains at least one positive ordered line, even if delivery later changed. Allocation-only lines do not make a household an ordering participant.
13. **Trend presentation (answered 2026-10-27):** Use four labeled bars with exact counts and a readable data list; each cycle links to its summary.
14. **Participation target (answered 2026-10-27; superseded by decision 22):** The earlier answer proposed showing the UX discovery's target of 10 ordering households. Decision 22 removes that target from the dashboard.
15. **Combined priority (answered 2026-10-27):** In the mixed-role attention list, overdue items precede nearest deadlines; actionable items without a deadline follow.
16. **Freshness (answered 2026-10-27):** Values update on page load or navigation. There is no background polling.
17. **Task aggregation (answered 2026-10-27):** Yes, group work by cycle and stage, with exact counts and links, rather than listing every household pickup on the dashboard.
18. **Current-cycle progress (answered 2026-10-27):** Yes, show separate labeled status bars for producer orders and, after settlement, pickups. Each has exact counts and links to the work.
19. **Member wording (answered 2026-10-27):** Yes, an open order is an opportunity to order until its cutoff, even when it already contains items. It is not an incomplete submission task.
20. **Other cycles (answered 2026-10-27):** Yes, urgent work in later cycles remains in the combined attention list while the earliest unfinished cycle leads the manager summary.
21. **Secondary counts (answered 2026-10-27):** Yes, supporting cards favor actionable exceptions, such as pending invitations, over all-time totals of accounts or cycles.
22. **Low participation (clarified 2026-10-27):** Keep the four-cycle household-count chart, but show counts only. Do not show a target line, low-participation wording, or an alert. This supersedes decision 14's target presentation.
23. **Deadline language (answered 2026-10-27):** Yes, “overdue” applies only when a real cutoff has passed. Pickup dates remain scheduled dates; reception and collection do not acquire invented deadlines.
24. **Attention versus context (answered 2026-10-27):** Yes, open-order opportunities and future producer cutoffs stay in role-specific context. Sent producer orders to deliver and pending pickups appear in Needs attention.

## Decision tree status

The role, action, chart, progress, deadline, and empty-state direction is settled. No product decision remains open in this interview. The user confirmed the assembled experience below with “yes” on 2026-10-27.

The remaining work is specification detail from existing domain facts: exact query states and denominators, stable ordering of undated items, authorization, and no-work copy for each role. These do not require inventing new product behavior.

The UI/UX Pro Max guidance helped reject a funnel for these non-monotonic work states and favors labeled bars for the four discrete cycles. A labeled statistic is clearer when history is sparse. Each chart needs visible values and a text equivalent; color cannot carry the only meaning.

## Confirmed experience

This section assembles the answered decisions into one reviewable experience. The formal spec remains proposed until accepted.

### Shared structure

1. A combined **Needs attention** list shows role labels, exact counts, the relevant cycle or date, and a deep link to the task page. A mixed-role account sees one prioritized list with no duplicate card.
2. Items past a real cutoff are labeled overdue and lead the list. Any other actionable item with an explicit deadline follows by time; undated actionable items come next. Future cutoffs that only provide context stay outside this list. Pickup dates are shown as scheduled dates and do not create an overdue label.
3. A manager's **focus cycle** is the earliest non-cancelled, not-closed cycle. Its work summary leads the page. Actionable items from later cycles remain in the combined list.
4. Role-specific context follows the attention list. The page refreshes its values on navigation or reload, without polling. No-work and insufficient-history states have explanatory text.

### Manager

- Show producer orders ready to send after cutoff, reception needing validation, validated reception awaiting distribution, and pending pickups as grouped work. Each group links to the relevant cycle, reception, or pickups page. An unsent zero-line producer order that the existing scheduler handles automatically is not a volunteer task.
- Show a producer-order status bar whose denominator is the focus cycle's participating producers. Use mutually exclusive current states: open before cutoff, ready to send, sent awaiting validation, validated awaiting distribution, and distributed. Keep counts and labels visible next to the bar.
- Once a cycle is settled, show a separate pickup status bar whose denominator is existing pickups for that cycle, with pending, completed, not collected, and nothing to collect counted separately. A producer order reopened after settlement can move backward in the first bar while pickup progress remains visible.
- Put supporting exception counts, such as pending invitations, after operational work. Avoid all-time account/cycle counts as the lead content.
- Show four labeled bars for the most recent four **closed** cycles, counting households with at least one positive ordered line in each. Provide exact counts, a readable data list, and links to cycle summaries. Show no target, low-participation label, or alert. With fewer than four closed cycles, explain why the chart is absent.

### Member and producer

- A member sees an open ordering opportunity with the applicable producer cutoffs, or the next pickup, ahead of wallet balance. The page never describes an auto-saved order as awaiting submission. Orders and Wallet remain direct destinations.
- A producer sees its next cutoff and any sent order to deliver ahead of catalog context. The dashboard never exposes household names or another producer's order. Products, cutoff and the relevant cycle summary are direct destinations.
- Open-order opportunities and future producer cutoffs remain in role-specific context. Sent orders to deliver and pending pickups appear in Needs attention.

### Boundaries

- Dashboard controls navigate to existing task screens; they do not send, validate, distribute, complete a pickup, edit an order, or change availability in place.
- These new read models require the same role and household/producer authorization as their task pages. A count must be derived from current domain state rather than a client-side guess from incomplete props.
- Status bars are discrete state summaries, not a funnel or a percentage of one process. The visual encoding supplements visible labels and counts; it never carries the only meaning. Every chart has a text equivalent and remains usable at 375 px, 200% text, in light/dark modes and by keyboard.
- When no producers participate, no pickups exist, or fewer than four cycles are closed, explain the missing comparison rather than drawing a zero-percent or empty chart. Cancelled and closed cycles do not lead the live manager work summary; closed cycles are eligible for the historical count chart.
- A reopened producer order is reflected in its current producer-order state even if the cycle was already settled. Its existing pickup progress remains visible. An account without a household or active producer role sees no invented member or producer data.
- When no item needs action, show a clear caught-up state and keep the relevant role destinations available. Pending invitations can remain a supporting count without being labeled urgent solely because they are pending.
