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

## Slice 2 (Producer role complete)

**Published 2026-11-15 after the user approved the proposed batch.** Same workspace, team and project. All eight issues are Backlog, unassigned, with no labels, cycle, milestone, due date or priority. Publication does not authorize implementation. Bodies link to the spec, plan and tasks at `origin/main` commit `e55d5877`.

Parent: [DRA-280 — Landing identity: Producer role complete (slice 2)](https://linear.app/draude/issue/DRA-280). Related context only, never a blocker: DRA-270 (slice 1).

| Task | Published issue                                    | Blocked by         |
| ---- | -------------------------------------------------- | ------------------ |
| T10  | [DRA-281](https://linear.app/draude/issue/DRA-281) | none               |
| T11  | [DRA-283](https://linear.app/draude/issue/DRA-283) | DRA-281            |
| T12  | [DRA-284](https://linear.app/draude/issue/DRA-284) | DRA-281            |
| T13  | [DRA-285](https://linear.app/draude/issue/DRA-285) | DRA-281            |
| T14  | [DRA-286](https://linear.app/draude/issue/DRA-286) | DRA-281            |
| T15  | [DRA-282](https://linear.app/draude/issue/DRA-282) | none               |
| T16  | [DRA-287](https://linear.app/draude/issue/DRA-287) | DRA-281 to DRA-286 |

Every child has DRA-280 as its native parent. The dependency frontier is T10 (DRA-281) and T15 (DRA-282). No task is admitted or marked ready-for-agent.

### Verification

A duplicate search in DRA/Coop ("Producer", full pagination) found no slice 2 issues before creation. The first create of the parent failed with a connection reset; a search of issues created in the last hour found nothing, so it was retried once. Read-back of the children list (titles, parent, status, labels) and of the relations on DRA-280 and DRA-287 matched the approved graph. No pending operation.
