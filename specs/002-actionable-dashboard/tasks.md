# 002 — Actionable dashboard · Tasks

**Status: Retired (2026-10-29).** T01–T04 and DRA-201–DRA-204 are **canceled and non-executable**. The descriptions and dependency chain below are historical; do not dispatch or resume them. A new dashboard spec requires completion of feature 001 and a new user decision. The independently shipped T05/R11 login-to-dashboard behavior and feature 001 T61 basic dashboard summary remain in force. Preserve the [request and decision log](log.md) and [discovery record](../../docs/ux-discovery/2026-10-27-dashboard-discovery.md). This task plan was accepted on 2026-10-27 and aligned with feature 001 Sales on 2026-10-28.

## Historical Linear tracking (2026-10-27; canceled 2026-10-29)

Workspace `draude`, team `DRA`, project [Coop](https://linear.app/draude/project/coop-f80f57e1496d), milestone **Actionable dashboard**. Four root issues form a tracer sequence through native blocked-by relations; no parent issue was added. The original publication had no contextual links; 001 T61/DRA-216 now relates to T02/DRA-202 and T03/DRA-203 for Sales-source compatibility, without blocking either. The T01–T04 IDs are scoped to spec 002 and their issue titles include that spec to distinguish them from IDs in spec 001.

| Task | Linear issue | Native blocked by |
| --- | --- | --- |
| T01 | [DRA-201](https://linear.app/draude/issue/DRA-201/t01-dashboard-manager-focus-and-first-actions-spec-002) | — |
| T02 | [DRA-202](https://linear.app/draude/issue/DRA-202/t02-dashboard-manager-progress-and-pickups-spec-002) | DRA-201 |
| T03 | [DRA-203](https://linear.app/draude/issue/DRA-203/t03-dashboard-role-aware-attention-spec-002) | DRA-202 |
| T04 | [DRA-204](https://linear.app/draude/issue/DRA-204/t04-dashboard-four-cycle-history-spec-002) | DRA-203 |

At publication, all four issues were Backlog, unassigned and without `ready-for-agent`. They were canceled on 2026-10-29, with retirement notices and existing relations preserved. The abandoned T01 implementation draft remains available for historical recovery in its separate worktree; it is not an active task.

The table and acceptance checks below preserve the original proposed slices for reference only. They confer no implementation authorization.

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T01 | Manager focus and first action list: add the authorized manager read for the earliest unfinished cycle and grouped non-empty orders ready to send; surface future cutoffs as context and link each action to its existing task page. Add a clear caught-up state and no-mutating-request check. Build the first typed, translated attention component and test it at phone/desktop sizes. | R1, R2, R5, R6, R9, R10; S2, S3, S12, S13 | — |
| T02 | Extend the manager read and dashboard with reception and distribution across unfinished cycles plus authorized Sales pending baskets across open/closed/no cycle, with separate labeled producer-order and weekly-base basket progress for the focus cycle. Classify scheduler-owned empty orders, permitted reopening, preparation versus validated collection and missing denominators explicitly. Pending supplements/cycleless baskets stay outside weekly-base counts; omit retired not-collected states. Keep later-cycle work visible. | R2, R7, R9, R10; S2, S4, S5, S6 | T01 |
| T03 | Add authorized member, Finance and producer reads within existing 001 capabilities, their role context, and a single deterministic combined attention list with role labels and deduplication. Link to each role's existing order, Sales basket, wallet, cutoff, product and producer-summary screens. Preserve closed-cycle/cycleless pending baskets and current-household access; Finance-only access exposes no manager cycle/history data. Verify absent household/inactive producer states and privacy. | R1, R3, R4, R5, R6, R9, R10; S1, S7, S8, S9, S12, S13 | T02 |
| T04 | Add the bounded closed-cycle participation read and manager four-cycle bar chart with exact values, visible list and cycle-summary links. Omit the chart with fewer than four closed cycles. Verify no target or low-participation signal and no polling. | R8–R10; S10, S11, S13 | T03 |

## Historical acceptance checks (not executable)

- **T01:** Application and HTTP tests prove the focus cycle, cancellation/closure exclusion, non-empty manual-send grouping, actual-cutoff language, future-cutoff context, authorized destination and read-only behavior. The first rendered manager slice uses the current shell and passes the shared visual runner at 375 and 1280 px in light/dark.
- **T02:** Application tests cover one current state per participating producer, scheduler-owned empty orders, reopened orders, weekly-base preparation/validation/collection/no-collection-due counts, closed-cycle/cycleless/supplement pending persistence, effective full reversal, settled-base visibility and later-cycle work. No date creates abandonment; no debt hides pending collection. Both status views expose exact counts in text, including the no-denominator explanation; no color-only meaning or zero-percent empty bar remains. Render the relevant manager states, including 200% text.
- **T03:** Application tests prove current-household and producer scoping, Finance-only limits, basket-UUID mixed-role deduplication and priority, scheduled-date language, and the auto-saved order wording. HTTP and Vue checks cover role props and deep links. Visual evidence includes member-only, Finance-only, producer-only, mixed-role, empty and long-content states; keyboard flow and 44 px targets pass.
- **T04:** Application tests count distinct ordering households in exactly the four latest closed, non-cancelled cycles, including zero-line, allocation-only and later-delivery cases. Vue checks confirm every bar's date/count/list/link, the insufficient-history explanation, and absence of target or low-participation text. The final role/viewport visual manifest and focused checks pass; page idle time causes no background request.

The original verification plan required generated TypeScript DTOs, focused PHP/Vue tests, typecheck, format and architecture checks, measured layout and a shared visual report. It is retained solely as historical context.

## Shipped independent login destination

- **T05 (R11, S14):** Shipped independently of T01–T04. Successful password, two-factor and passkey logins go directly to the dashboard, clearing a previously requested destination; existing authentication and JSON response contracts remain applicable. Retirement of the dashboard-content tasks does not reverse this behavior.

## Historical compatibility publication

At publication, T02/DRA-202 and T03/DRA-203 descriptions were synchronized with the Sales read
contract above. Their native prerequisites and two contextual T61 links were retained when the
issues were canceled. The original T01 manager-sending and T04 counts-only scopes remain available
only as historical context. The amended scope's source links pointed to `docs/product-producer-stock`
until the documentation PR merged.
