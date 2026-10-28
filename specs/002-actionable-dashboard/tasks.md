# 002 — Actionable dashboard · Tasks

Status: Accepted (2026-10-27); aligned with the accepted feature-001 Sales model on 2026-10-28. [Spec](spec.md) and [plan](plan.md) accepted.

## Linear tracking (2026-10-27)

Workspace `draude`, team `DRA`, project [Coop](https://linear.app/draude/project/coop-f80f57e1496d), milestone **Actionable dashboard**. Four root issues form a tracer sequence through native blocked-by relations; no parent issue was added. The original publication had no contextual links; 001 T61/DRA-216 now relates to T02/DRA-202 and T03/DRA-203 for Sales-source compatibility, without blocking either. The T01–T04 IDs are scoped to spec 002 and their issue titles include that spec to distinguish them from IDs in spec 001.

| Task | Linear issue | Native blocked by |
| --- | --- | --- |
| T01 | [DRA-201](https://linear.app/draude/issue/DRA-201/t01-dashboard-manager-focus-and-first-actions-spec-002) | — |
| T02 | [DRA-202](https://linear.app/draude/issue/DRA-202/t02-dashboard-manager-progress-and-pickups-spec-002) | DRA-201 |
| T03 | [DRA-203](https://linear.app/draude/issue/DRA-203/t03-dashboard-role-aware-attention-spec-002) | DRA-202 |
| T04 | [DRA-204](https://linear.app/draude/issue/DRA-204/t04-dashboard-four-cycle-history-spec-002) | DRA-203 |

All four issues are Backlog, unassigned and without `ready-for-agent`. Ticket creation does not authorize implementation. The T01 implementation draft remains stopped in its separate worktree.

Each task is a reviewable vertical slice through authorized Application reads, HTTP props, translated Vue presentation, and its focused tests. Keep the existing dashboard route and accepted palette. A task is done when its listed scenarios pass, relevant architecture and type checks stay green, and its changed interface passes the visual and accessibility checks in R10. Record any behavior change in the spec first and any implementation deviation in [log.md](log.md).

| ID | Task | Covers | After |
| --- | --- | --- | --- |
| T01 | Manager focus and first action list: add the authorized manager read for the earliest unfinished cycle and grouped non-empty orders ready to send; surface future cutoffs as context and link each action to its existing task page. Add a clear caught-up state and no-mutating-request check. Build the first typed, translated attention component and test it at phone/desktop sizes. | R1, R2, R5, R6, R9, R10; S2, S3, S12, S13 | — |
| T02 | Extend the manager read and dashboard with reception and distribution across unfinished cycles plus authorized Sales pending baskets across open/closed/no cycle, with separate labeled producer-order and weekly-base basket progress for the focus cycle. Classify scheduler-owned empty orders, permitted reopening, preparation versus validated collection and missing denominators explicitly. Pending supplements/cycleless baskets stay outside weekly-base counts; omit retired not-collected states. Keep later-cycle work visible. | R2, R7, R9, R10; S2, S4, S5, S6 | T01 |
| T03 | Add authorized member, Finance and producer reads within existing 001 capabilities, their role context, and a single deterministic combined attention list with role labels and deduplication. Link to each role's existing order, Sales basket, wallet, cutoff, product and producer-summary screens. Preserve closed-cycle/cycleless pending baskets and current-household access; Finance-only access exposes no manager cycle/history data. Verify absent household/inactive producer states and privacy. | R1, R3, R4, R5, R6, R9, R10; S1, S7, S8, S9, S12, S13 | T02 |
| T04 | Add the bounded closed-cycle participation read and manager four-cycle bar chart with exact values, visible list and cycle-summary links. Omit the chart with fewer than four closed cycles. Verify no target or low-participation signal and no polling. | R8–R10; S10, S11, S13 | T03 |

## Done when, by slice

- **T01:** Application and HTTP tests prove the focus cycle, cancellation/closure exclusion, non-empty manual-send grouping, actual-cutoff language, future-cutoff context, authorized destination and read-only behavior. The first rendered manager slice uses the current shell and passes the shared visual runner at 375 and 1280 px in light/dark.
- **T02:** Application tests cover one current state per participating producer, scheduler-owned empty orders, reopened orders, weekly-base preparation/validation/collection/no-collection-due counts, closed-cycle/cycleless/supplement pending persistence, effective full reversal, settled-base visibility and later-cycle work. No date creates abandonment; no debt hides pending collection. Both status views expose exact counts in text, including the no-denominator explanation; no color-only meaning or zero-percent empty bar remains. Render the relevant manager states, including 200% text.
- **T03:** Application tests prove current-household and producer scoping, Finance-only limits, basket-UUID mixed-role deduplication and priority, scheduled-date language, and the auto-saved order wording. HTTP and Vue checks cover role props and deep links. Visual evidence includes member-only, Finance-only, producer-only, mixed-role, empty and long-content states; keyboard flow and 44 px targets pass.
- **T04:** Application tests count distinct ordering households in exactly the four latest closed, non-cancelled cycles, including zero-line, allocation-only and later-delivery cases. Vue checks confirm every bar's date/count/list/link, the insufficient-history explanation, and absence of target or low-participation text. The final role/viewport visual manifest and focused checks pass; page idle time causes no background request.

For each slice, generate the TypeScript DTOs, run focused PHP/Vue tests, typecheck, format and architecture checks, then inspect measured layout and the shared visual report. The full suite and CI remain the pull-request gate. These tasks do not authorize publishing Linear tickets or deploying a change.

## Login destination follow-up

- **T05 (R11, S14):** Route successful password, two-factor and passkey logins directly to the dashboard, clearing a previously requested destination. Done when focused HTTP/response tests cover each login method, existing authentication checks pass, and JSON contracts are preserved. This independent navigation change has no dependency on T01–T04 and no visual changes.

## Compatibility publication

T02/DRA-202 and T03/DRA-203 descriptions are synchronized with the Sales read contract above.
Their native prerequisites remain T01 → T02 → T03 → T04; the two contextual T61 links remain.
No status, assignee, label, parent, milestone or implementation authorization is changed. T01's
manager sending tracer and T04's counts-only history keep their accepted behavior. Source links
for the amended scope target `docs/product-producer-stock` until the documentation PR merges.
