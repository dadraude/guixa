# 002 — Actionable dashboard · Tasks

Status: Accepted (2026-10-27). [Spec](spec.md) and [plan](plan.md) accepted.

## Linear tracking (2026-10-27)

Workspace `draude`, team `DRA`, project [Coop](https://linear.app/draude/project/coop-f80f57e1496d), milestone **Actionable dashboard**. Four root issues form a tracer sequence through native blocked-by relations; no parent issue or contextual relations were added. The T01–T04 IDs are scoped to spec 002 and their issue titles include that spec to distinguish them from IDs in spec 001.

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
| T02 | Extend the manager read and dashboard with reception, distribution and pickup groups across unfinished cycles, plus separate labeled producer-order and pickup status bars for the focus cycle. Classify empty orders awaiting the scheduler, reopening and missing denominators explicitly. Keep later-cycle work visible. | R2, R7, R9, R10; S2, S4, S5, S6 | T01 |
| T03 | Add authorized member and producer reads, their role context, and a single deterministic combined attention list with role labels and deduplication. Link to each role's existing order, pickup, wallet, cutoff, product and producer-summary screens. Verify absent household/inactive producer states and privacy. | R1, R3, R4, R5, R6, R9, R10; S1, S7, S8, S9, S12, S13 | T02 |
| T04 | Add the bounded closed-cycle participation read and manager four-cycle bar chart with exact values, visible list and cycle-summary links. Omit the chart with fewer than four closed cycles. Verify no target or low-participation signal and no polling. | R8–R10; S10, S11, S13 | T03 |

## Done when, by slice

- **T01:** Application and HTTP tests prove the focus cycle, cancellation/closure exclusion, non-empty manual-send grouping, actual-cutoff language, future-cutoff context, authorized destination and read-only behavior. The first rendered manager slice uses the current shell and passes the shared visual runner at 375 and 1280 px in light/dark.
- **T02:** Application tests cover one current state per participating producer, scheduler-owned empty orders, reopened orders, each pickup status, settled-cycle visibility and later-cycle work. Both status views expose exact counts in text, including the no-denominator explanation; no color-only meaning or zero-percent empty bar remains. Render the relevant manager states, including 200% text.
- **T03:** Application tests prove household and producer scoping, mixed-role deduplication and priority, scheduled-date language, and the auto-saved order wording. HTTP and Vue checks cover role props and deep links. Visual evidence includes member-only, producer-only, mixed-role, empty and long-content states; keyboard flow and 44 px targets pass.
- **T04:** Application tests count distinct ordering households in exactly the four latest closed, non-cancelled cycles, including zero-line, allocation-only and later-delivery cases. Vue checks confirm every bar's date/count/list/link, the insufficient-history explanation, and absence of target or low-participation text. The final role/viewport visual manifest and focused checks pass; page idle time causes no background request.

For each slice, generate the TypeScript DTOs, run focused PHP/Vue tests, typecheck, format and architecture checks, then inspect measured layout and the shared visual report. The full suite and CI remain the pull-request gate. These tasks do not authorize publishing Linear tickets or deploying a change.
