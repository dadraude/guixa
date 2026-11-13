# 005 — Linear publication

**Published 2026-11-13 after the user approved the exact batch in [ticket-drafts.md](ticket-drafts.md).** Workspace `draude`, team `DRA`, project `Coop`. All ten issues are Backlog, unassigned, with no labels, cycle, milestone, due date or priority. Publication does not authorize implementation.

Parent: [DRA-270 — Landing identity: Catalogue role complete (slice 1)](https://linear.app/draude/issue/DRA-270). Related context only, never blockers: DRA-264, DRA-265 and DRA-269 (001 T83, T84, T85).

| Task | Published issue | Blocked by |
| --- | --- | --- |
| T01 | [DRA-271](https://linear.app/draude/issue/DRA-271) | none |
| T02 | [DRA-272](https://linear.app/draude/issue/DRA-272) | none |
| T03 | [DRA-273](https://linear.app/draude/issue/DRA-273) | DRA-271 |
| T04 | [DRA-274](https://linear.app/draude/issue/DRA-274) | DRA-271 |
| T05 | [DRA-277](https://linear.app/draude/issue/DRA-277) | DRA-271, DRA-273 |
| T06 | [DRA-278](https://linear.app/draude/issue/DRA-278) | DRA-271, DRA-273 |
| T07 | [DRA-275](https://linear.app/draude/issue/DRA-275) | DRA-271 |
| T08 | [DRA-276](https://linear.app/draude/issue/DRA-276) | DRA-271 |
| T09 | [DRA-279](https://linear.app/draude/issue/DRA-279) | DRA-272 to DRA-278 |

Every child has DRA-270 as its native parent and no contextual links. The parent has no blocking edge. The dependency frontier is T01 (DRA-271) and T02 (DRA-272). No task is admitted or marked ready-for-agent.

## Verification

A duplicate search in DRA/Coop ("Catalogue landing", full pagination) found no matching 005 issues before creation. The coordinator created the parent and then the children serially, using native `parentId`, `blockedBy` and `relatedTo` fields, and no body contains a tracker identifier. Read-back of titles, parent, status, labels and every relation matched the approved draft; the parent relates only to DRA-264, DRA-265 and DRA-269. No self-link, cycle or stray auto-relation was found. No pending operation.
