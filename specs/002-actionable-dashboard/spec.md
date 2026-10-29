# 002 — Actionable dashboard

**Status: Retired (2026-10-29).** R1–R10 and S1–S13 below are historical product decisions, not executable requirements or authorization for dashboard-content implementation. T01–T04 are canceled. Preserve the original [request and decision log](log.md) and [discovery record](../../docs/ux-discovery/2026-10-27-dashboard-discovery.md). A new dashboard spec may be considered only after feature 001 is complete and the user makes a new decision. This retirement does not undo the independently shipped T05/R11 login-to-dashboard behavior or feature 001 T61's basic dashboard summary responsibility. The historical spec was accepted on 2026-10-27 and aligned with feature 001 Sales on 2026-10-28.

The signed-in dashboard should answer what each person needs to do now. A manager sees cycle work and pending baskets, a member sees their ordering and basket-collection context, and a producer sees delivery and cutoff context. Finance sees only its permitted basket context and destinations. People with several roles see one ordered attention list without repeated work. Charts clarify the current cycle and show recent household ordering counts; they do not invent an alarm about low participation.

## Language

Existing terms such as *cycle*, *producer order*, *cutoff*, *settled*, *closed*, *order line*, *basket*, *validation*, *collection* and *Finance* retain their meanings in [001 — Weekly orders](../001-weekly-orders/spec.md#glossary). The accepted 001 amendment separates Sales validation from physical collection: closure may precede collection, and stock baskets may have no cycle. This alignment carries those rules into dashboard reads; it adds no new write action or product workflow.

| Term | Meaning |
| --- | --- |
| Attention item | A visible group of work that the signed-in person can act on now, with its role, cycle or scheduled date, exact count where relevant, and a link to the existing task screen. It is a dashboard presentation, not a new domain entity. |
| Ordering household | A household whose order in a cycle contains at least one positive ordered line. Allocation-only lines do not count. A later delivery change does not undo the fact that the household ordered. |
| Focus cycle | The earliest non-cancelled, not-closed cycle by pickup date. It leads the manager's current-work summary; attention items from other cycles remain visible. |

## Requirements

### R1 — Role-aware overview

The dashboard shows only information the signed-in account may see as a manager, Finance, member of its household, or producer of its producer. Finance access grants only the basket/receipt destinations and collection information already authorized in 001; it does not grant manager cycle, reception, account, inventory or closed-cycle participation history access. Manager retains Finance capabilities. A person with several roles sees one combined attention list with role labels and no duplicate item for the same work. Role-specific context follows it. No role or relevant work produces an explanatory state rather than fabricated data.

### R2 — Manager work

The focus cycle leads the manager view. Manager attention items group actionable work by stage and optional source cycle: participating producer orders ready to send after cutoff; sent receptions needing validation; validated receptions awaiting distribution; and validated baskets pending physical collection. Each item shows an exact count and links to the relevant cycle, reception, or Sales basket screen. Pending collection is independent of the unfinished focus cycle: closed-cycle, attributed supplementary and cycleless baskets remain visible through the authorized Sales summary. Drafts are labelled in preparation, never ready to collect; passing a scheduled date does not abandon or cancel a basket. Future cutoffs are context, not attention items. An empty producer order handled automatically is not volunteer work. Actionable work in later cycles remains in the combined list.

The manager also sees supporting exception counts, such as pending invitations, below current work. A pending invitation is not automatically an urgent attention item. All-time totals of accounts or cycles do not lead the page.

### R3 — Member context

A member sees an open ordering opportunity with its applicable producer cutoffs, or validated baskets pending collection, before wallet balance. An order already containing items is not described as awaiting submission: ordering saves during editing. An open opportunity remains in role context; a validated pending basket appears in the attention list, including after cycle closure or without a cycle. Links lead to the existing order, Sales basket or wallet screen within the member's current household access. Basket preparation, validation and collection remain distinct; a moved or unlinked member gains no access to the former household's baskets.

### R4 — Producer context

A producer sees its next cutoff and product-availability context, plus any sent order it needs to deliver. A future cutoff is context; a sent order to deliver is an attention item with a link to its cycle summary. Producer information never reveals household names or another producer's order.

### R5 — Priority and time language

Items past an actual cutoff are labeled overdue and appear first. Other actionable items with an explicit deadline follow by deadline; actionable items without one follow. Pickup dates are shown as scheduled dates, not invented deadlines for reception or collection. The dashboard uses the cooperative's local timezone. A stable order is used for ties. Neither an open ordering opportunity nor a future cutoff is mislabeled as unfinished work.

### R6 — Direct access

Prominent actions navigate to the relevant existing screen, where the person has full context before changing anything. The dashboard itself does not send producer orders, validate or distribute receptions, validate baskets, mark collection, record receipts, edit orders, change availability, or perform other state-changing actions.

### R7 — Current-cycle progress

The manager sees a labeled producer-order status bar for the focus cycle. Its whole is the participating producers; each producer is counted once in its current state: still open for ordering, non-empty and ready for manual send, empty and awaiting automatic send, sent awaiting validation, validated awaiting distribution, or distributed. Once the cycle is settled, a separate Sales status view counts its existing weekly base baskets once each: in preparation, validated pending collection, collected, or with no physical collection due under 001's nothing-to-collect/correction rules. Do not restore the retired date-triggered not-collected state. Attributed supplements and cycleless stock baskets remain in the pending collection list; they do not enlarge the weekly-base denominator or gate weekly closure. Both views show exact counts and a readable status list; color is supplementary. Reopening a producer order updates its current state without hiding base-basket progress that already exists; closed cycles cannot reopen. A missing denominator has an explanatory state, not a zero-percent bar.

### R8 — Four-cycle history

For managers, a secondary chart shows the number of ordering households in each of the four most recent **closed** cycles, ordered by pickup date. It uses four labeled bars, exact values, a readable data list, and a link from each cycle to its summary. The chart shows counts only: no target line, low-participation label, or alert. Cancelled and not-closed cycles are excluded. Fewer than four closed cycles produces an insufficient-history explanation instead of a partial or empty chart.

### R9 — Freshness, empty states and access

Values reflect current persisted state whenever the dashboard is loaded or navigated to; the page does not poll in the background. If no cycle, producer work, household, basket, or attention item exists for a role, the page explains the state and keeps relevant permitted destinations available. A user cannot infer another household's or producer's information from counts, labels, links, or chart details.

### R10 — Presentation and accessibility

The dashboard follows Guixa's accepted visual system and is usable at 375 px, in light and dark appearance and at 200% text. Attention items and chart data remain available by keyboard and as visible text; neither status nor chart meaning depends on color or hover. Interactive targets remain at least 44 × 44 px. New visible copy is translatable and Catalan is the default.

### R11 — Login destination

After successful password, two-factor or passkey authentication, the account goes directly to the dashboard, even when the session contains a previously requested destination. Authentication and email-verification requirements still apply.

## Acceptance scenarios

**S1 — Combined roles (R1, R5).** Given an account with combined permitted manager/Finance, member and producer access and actionable work in each area, when it opens the dashboard, then it sees one attention list with the correct role labels, each work group once, and the agreed priority order; it also sees each permitted role's context. A basket visible through several roles appears once, and Finance-only access exposes no manager cycle/reception/history data.

**S2 — Focus and later work (R2).** Given two non-cancelled unfinished cycles with different pickup dates and actionable work in both, when a manager opens the dashboard, then the earlier cycle leads the current-work summary and both cycles' actionable groups remain in Needs attention. A cancelled or closed cycle cannot become the focus cycle.

**S3 — Sending and future cutoff (R2, R5).** Given one participating producer whose cutoff passed and whose non-empty order is unsent, one whose cutoff is in the future, and one empty order handled automatically, when the manager opens the dashboard, then only the non-empty order ready to send is a manager attention item; the future cutoff appears as context and only the passed cutoff is called overdue.

**S4 — Reception states (R2, R7).** Given a focus cycle with sent, validated and distributed producer orders, when the manager opens the dashboard, then the attention groups and producer-order status bar reflect each producer's current stage exactly once and link to the relevant work. Reopening a distributed producer order changes its displayed stage on the next load.

**S5 — Basket progress and persistent collection (R2, R7).** Given a settled cycle with base baskets in preparation, validated pending collection, collected and nothing to collect, when the manager opens the dashboard, then its separate Sales status view shows exact base-basket counts and pending items link to basket details. A permitted producer-order reopen does not hide that progress. Given validated pending baskets from a closed cycle, an attributed supplement and a cycleless purchase, they remain in the authorized pending list; supplements/cycleless baskets do not enter the weekly-base denominator, and a date alone creates no not-collected state. Collection or a justified full contents reversal removes the effective pending item on the next read.

**S6 — No progress denominator (R7, R9).** Given a cycle with no participating producers or a settled cycle with no existing base baskets, when the manager opens the dashboard, then the corresponding progress view explains the absence of data without showing a zero-percent chart.

**S7 — Member order opportunity (R3).** Given a household with an open cycle and an order already containing items, when its member opens the dashboard, then the opportunity and applicable cutoffs are shown without an unfinished-submission message, and the order link opens only that household's order.

**S8 — Member basket collection and balance (R3, R5).** Given a current member with no ordering opportunity, a validated pending basket and a wallet balance, when the member opens the dashboard, then their basket appears in Needs attention with validation time and its optional scheduled/origin date, while balance remains supporting context. The same holds after cycle closure or for a cycleless basket. A draft is preparation, a date alone is not overdue, and debt does not hide collection. A moved/unlinked member cannot see the former household's basket.

**S9 — Producer delivery and privacy (R4).** Given a producer with a future cutoff and a sent order to deliver, when that producer opens the dashboard, then the future cutoff is context, the sent order is an attention item linked to its own summary, and no household name or another producer's quantities appear.

**S10 — Four closed cycles (R8).** Given at least four closed cycles containing positive ordered lines, zero-line orders, allocation-only lines and later delivery changes, when a manager opens the dashboard, then the last four closed cycles appear with each ordering household counted once per cycle, exact values and summary links. No target, low-participation wording or alert appears.

**S11 — Sparse history (R8, R9).** Given fewer than four closed cycles, when a manager opens the dashboard, then the history chart is absent and the page explains that more completed cycles are needed; missing data is not shown as zero.

**S12 — No actionable work (R1, R9).** Given an account with no actionable work, when it opens the dashboard, then it sees a clear caught-up state and its permitted role destinations; absent household or inactive producer access does not create fabricated context.

**S13 — Navigation only (R6, R9).** Given actionable work, when the dashboard is read, then each attention item supplies the existing authorized task destination and no command changes state. A subsequent dashboard read reflects current persisted state.

**S14 — Login destination (R11).** Given an account allowed to log in, with or without a previously requested destination, when it completes password, two-factor or passkey authentication, then its destination is the dashboard. An unsuccessful login does not grant access.

## Presentation checks

R10 is verified with the shared visual runner and focused interaction checks in addition to the Application scenarios above. Populated and empty states at 375 px, 1024 px with 200% text, and 1280 px in light and dark appearance must keep labels, counts, links and chart equivalents readable without horizontal page overflow, hover-only information, color-only meaning or undersized targets. A frontend check verifies that the page does not poll while idle.

## Out of scope

The dashboard does not add notification delivery, a reminder system, telemetry collection, a new analytics platform, forecasting, member order submission, or state-changing shortcuts. It does not turn the UX discovery's participation target into a dashboard warning. Existing permissions and the accepted 001 Ordering, reception, Sales validation/collection and Wallet rules remain authoritative. The dashboard introduces no payment allocation, abandonment state, new Finance power or direct write command.

## Open questions

None for product behavior. Implementation details and gap review are recorded in [plan.md](plan.md).
