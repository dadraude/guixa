# 002 — Actionable dashboard · Plan

Status: Accepted (2026-10-27). Implements [spec.md](spec.md).

## Design boundaries

The dashboard is a read-only composition of existing Ordering, Membership, Catalog and Wallet facts. It adds no database table, telemetry event, scheduled job, write command, background polling, route or chart dependency. Existing task screens perform all mutations. The accepted reference-style shell and palette are reused.

Business state and role scope remain in Application queries in their owning contexts. `DashboardController` calls those queries and maps their DTOs into Inertia props; it does not inspect domain entities or infer status. A small `Dashboard/Application/PrioritizeAttention` use case merges and stably sorts already-authorized attention DTOs by explicit priority keys; it does not grant access or decide whether a domain task is actionable. The HTTP mapper only turns destination parameters into existing route URLs. New DTOs are transformed to TypeScript by the existing generator, not duplicated as handwritten page types.

## Read models

| Query / owner | Inputs and source | Output and rule |
| --- | --- | --- |
| `ListManagerDashboardWork` / Ordering Application | Manager `Actor`, `CycleRepository`, `OrderRepository`, cooperative `Clock` | Earliest non-cancelled, not-closed focus cycle; grouped actionable producer work and pending pickups across cycles; participant and pickup state counts for the focus cycle. `Cycle::isReadyToSend`, `isSent`, `isValidated`, `isDistributed`, `isSettled` and each `Order::pickup()` are authoritative. |
| `ListMemberDashboardContext` / Ordering Application | Member `Actor`, cycles and the household's order/pickup data | Open cycle opportunities with applicable participating-producer cutoffs, the next pending pickup, and stable IDs for existing order/pickup destinations. It does not invent a submitted/unfinished order status. |
| `ListProducerDashboardContext` / Ordering Application | Producer `Actor`, participating cycles, sent producer-order totals and cycle availability | Own future cutoffs, sent orders to deliver and product-availability context. It exposes no household names or other producer data. |
| `ListClosedCycleParticipation` / Ordering Application | Manager `Actor`, last four closed non-cancelled cycles, their orders | Pickup date and distinct household count for orders with at least one `OrderLine::isOrdered()`. Allocation-only lines and later delivered quantities do not change the count. Return a complete four-cycle series or an insufficient-history result. |
| Existing Membership/Wallet reads | Manager `ListAccounts`; member `ReadHouseholdBalance` | Supporting pending-invitation count and household wallet balance. Counting pending invitations is presentation aggregation over an authorized account list; it is never an automatic urgency rule. |

Each new query has a focused Application test through its public `__invoke`. The new manager query reads each relevant cycle's orders once and classifies a participating producer into exactly one current state. An unsent producer with no ordered line is left out of the volunteer attention queue because the existing empty-order scheduler handles it; after cutoff it occupies the explicit *awaiting automatic send* status until the scheduler advances it. A reopened producer's current state is read afresh while settled pickups remain visible. Pickup denominators exclude `PickupStatus::None` and include pending, completed, not collected and nothing to collect. A zero denominator produces an explanatory state rather than a percentage.

The history read is bounded to four closed cycles after sorting by pickup date, then displayed chronologically. At this cooperative's scale, existing repositories can supply the data; keep the query bounded and avoid per-producer or per-household round trips. No telemetry table is needed.

## Attention composition and time

Each role query returns attention candidates with a stable kind, role, cycle ID, optional producer/household ID, count, optional actual cutoff instant, optional scheduled pickup date and destination parameters. Only work whose action is governed by the cutoff carries that instant; a sent producer order and a pickup carry their scheduled date as context, not an expired-cutoff deadline. `PrioritizeAttention` removes identical work keys, places expired-cutoff items first, then items with a real future deadline, then undated actionable work. Ties use pickup date, role and stable IDs so order does not jump between reloads. Pickup dates remain schedule labels and do not become overdue thresholds. All cutoff comparisons use the cooperative's `Clock` and timezone; display dates use existing `ca-ES` helpers.

The controller requests only the role data the `Actor` can access. Every use case still asserts its role/household/producer scope. The manager's focus panel may show one cycle while the combined attention list contains work from later cycles. A manager-only invitation count is supporting context; no pending invitation is inserted into the attention list without a separate rule.

## Login destination

For R11/S14, bind the password, two-factor and passkey login response contracts to application adapters that discard `url.intended` after successful authentication and delegate to the installed package responses. Keep the configured dashboard destination and each response’s existing JSON contract. Test the real password and two-factor endpoints and the resolved passkey response in HTML and JSON. This navigation change has no Application use case or visual change.

## HTTP and UI

Keep the existing `dashboard` route and `DashboardController`. Replace its ad hoc aggregate props with generated, typed role sections and attention/status/history DTO rows. Use code values for task and status labels, translated in Vue through `lang/`. Preserve existing direct destinations for Orders, Wallet, Products, Cutoff, Cycles, Households, Producers, Stock, Return items, Accounts and Settings.

`resources/js/pages/Dashboard.vue` remains a composition surface. Reuse `Heading.vue`, `StatusIndicator.vue`, the shared `.pilot-layout-*` patterns and the shadcn-vue button/card primitives. Inspect those variants before editing. New reusable presentational pieces are justified for the two repeated status bars and the four-cycle bar/list combination; they belong under `resources/js/components/dashboard/`, take typed props, and do not import Inertia or fetch data. The page owns Inertia `Link` instances to existing routes. Keep content order: attention, role context and focus-cycle progress, supporting exceptions, then four-cycle history. On a phone these stack; no horizontal page scroll.

Use semantic palette tokens from `public/theme-colors.css`. A status bar shows state names and counts adjacent to its segments; the four-cycle bars show each pickup date and exact count plus a visible list. Bars are simple CSS/SVG presentation, so no chart package is required. Buttons and links expose focus, hover and active states; reduced motion is respected. A small data set never produces an empty frame or misleading 0%.

## Verification

- Test-first Application scenarios S1–S13: role scope, focus cycle and later work, zero-line auto-send exclusion, reopen behavior, pickup counts, member wording data, producer privacy, four-cycle counting, sparse/no-work states and deterministic priority. HTTP tests cover route authorization and mapped Inertia props. Vitest checks translated role sections, navigation links, chart text equivalents and absence of polling.
- Before UI changes, record a route/role/state inventory and a `component_plan` with exact reused paths. Prepare the shared visual manifest for manager-only, member-only, producer-only and combined-role views. Include populated state for each at 375/1280 px in light/dark, distinct patterns at 1024 px and 200% text, and the riskiest empty, long-content, reopened, insufficient-history and keyboard-focus states.
- Use the shared visual runner and text measurements before inspecting captures. Check contrast, 44 px targets, no clipping, tab order, chart labels and dark mode. Keep generated evidence outside tracked source. Verify request count stays stable across page idle time and that no state-changing request originates from the dashboard.
- Run focused Vue/PHP checks, type generation/check, formatting, and the existing contract tests affected by the read queries. CI provides the full suite on the PR.

## Manual gap review

The repository references `.claude/skills/spec-gaps/SKILL.md`, but that file is not present in this checkout. The following gap families were checked against [spec.md](spec.md) and the [discovery record](../../docs/ux-discovery/2026-10-27-dashboard-discovery.md): roles and mixed roles (R1, S1), permissions/privacy (R1/R4/R9, S7/S9), lifecycle and reopening (R2/R7, S2–S5), zero and sparse data (R7–R9, S6/S11/S12), time and ordering (R5, S3/S8), direct actions and side effects (R6, S13), historical counting and cancellation (R8, S10), translation/accessibility/responsive appearance (R10 and presentation checks), and data freshness/performance (R9, bounded queries above). No product question remains open.
