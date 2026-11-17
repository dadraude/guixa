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

## Slices 3-7, checkpoints C2 to C5

**Published 2026-11-17 after the user asked to publish all remaining tickets**, from the accepted breakdown. Same workspace, team and project; bodies link to the spec, plan and tasks at `origin/main` commit `e40826b9`. All 26 issues are Backlog, unassigned, with no labels, cycle, milestone, due date or priority. Publication does not authorize implementation, and a checkpoint still opens only after the previous checkpoint's approval entry is logged (R20); the tracer of each checkpoint is blocked by the previous closure task as a tracker edge, but "Done" in Linear does not replace the approval entry.

Parents (one per checkpoint, no task ID, no label), each related to the previous checkpoint's parent as context only: [DRA-296 C2 Manager administration](https://linear.app/draude/issue/DRA-296), [DRA-297 C3 Wallet and member](https://linear.app/draude/issue/DRA-297), [DRA-298 C4 Sign-in and settings](https://linear.app/draude/issue/DRA-298), [DRA-299 C5 Dashboard, errors and global promotion](https://linear.app/draude/issue/DRA-299). DRA-296 relates to DRA-288.

| Task | Published issue                                    | Blocked by                          |
| ---- | -------------------------------------------------- | ----------------------------------- |
| T24  | [DRA-300](https://linear.app/draude/issue/DRA-300) | DRA-295 (T23)                       |
| T25  | [DRA-302](https://linear.app/draude/issue/DRA-302) | DRA-300                             |
| T26  | [DRA-303](https://linear.app/draude/issue/DRA-303) | DRA-300                             |
| T27  | [DRA-304](https://linear.app/draude/issue/DRA-304) | DRA-300                             |
| T28  | [DRA-305](https://linear.app/draude/issue/DRA-305) | DRA-300                             |
| T29  | [DRA-301](https://linear.app/draude/issue/DRA-301) | none                                |
| T30  | [DRA-306](https://linear.app/draude/issue/DRA-306) | DRA-300 to DRA-305                  |
| T31  | [DRA-307](https://linear.app/draude/issue/DRA-307) | DRA-306 (T30)                       |
| T32  | [DRA-308](https://linear.app/draude/issue/DRA-308) | DRA-307                             |
| T33  | [DRA-309](https://linear.app/draude/issue/DRA-309) | DRA-307, DRA-308                    |
| T34  | [DRA-311](https://linear.app/draude/issue/DRA-311) | DRA-309 (T33)                       |
| T35  | [DRA-312](https://linear.app/draude/issue/DRA-312) | DRA-311                             |
| T36  | [DRA-313](https://linear.app/draude/issue/DRA-313) | DRA-311                             |
| T37  | [DRA-314](https://linear.app/draude/issue/DRA-314) | DRA-311                             |
| T38  | [DRA-310](https://linear.app/draude/issue/DRA-310) | none                                |
| T39  | [DRA-315](https://linear.app/draude/issue/DRA-315) | DRA-310 to DRA-314                  |
| T40  | [DRA-316](https://linear.app/draude/issue/DRA-316) | DRA-315 (T39)                       |
| T41  | [DRA-317](https://linear.app/draude/issue/DRA-317) | DRA-315                             |
| T42  | [DRA-318](https://linear.app/draude/issue/DRA-318) | DRA-315                             |
| T43  | [DRA-319](https://linear.app/draude/issue/DRA-319) | DRA-315                             |
| T44  | [DRA-320](https://linear.app/draude/issue/DRA-320) | DRA-316 to DRA-319                  |
| T45  | [DRA-321](https://linear.app/draude/issue/DRA-321) | DRA-320                             |

Every child has its checkpoint parent as native parent. No task is admitted or marked ready-for-agent.

### Verification

The duplicate search from the C1 publication (no slices 3-7 issues) covered this batch. Parents were created first, then the children in dependency order, serially per level. One mistake was corrected during publication: a `relatedTo` for the C2 parent was first set on T31 (DRA-307) instead of on the C3 parent; it was removed and set on DRA-297. Read-back of the children lists and of the relations on DRA-296, DRA-299 and DRA-307 matched the approved graph. No pending operation.
