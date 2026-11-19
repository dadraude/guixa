# Implementation retrospective

Find what would let the next run find information faster, avoid repeated work and catch errors earlier.
Product-code review stays with independent review.

Run it only for a task that stopped or whose CI failed; a clean `ci-passed` task records
`retrospective: none`. Keep it a short pass over existing artifacts, not another implementation or review.

## Delegate

Dispatch a fresh Retrospective worker, separate from the task's implementer and reviewers. Supply this
brief, the task and PR identities, fixed HEAD/base and bounded artifact paths (owner record, findings
files, focused-check and CI results, relevant worker reports). It reads them and returns a digest: the
outcome, evidence gaps and at most three ranked proposals. It edits nothing, launches nothing and reruns
no validation. The coordinator saves the digest as `retrospective.md` next to the task handoff and links
it from the session report. Without delegation, record the retrospective as pending; do not self-analyze.

## Inspect

Account for what was delivered and what remains; correction attempts and review rounds; the largest
avoidable delay or repeated work (late contract discovery, broad context, fragile fixtures, redundant
checks, scope conflicts, stale evidence); defects first caught in review or CI and the earlier, focused
check that could have caught them; and what worked. Cite the artifact behind each conclusion and mark
hypotheses as such. A clean run can end with no proposal.

| Session evidence                                  | Candidate improvement                                                |
| ------------------------------------------------- | -------------------------------------------------------------------- |
| Repeated searches or a hidden dependency          | A pointer from an instruction the role already reads to the source   |
| A mistake a tool could catch                      | Repair an unwired check before proposing a new one; then a new check |
| A reviewer missed a fixed, mechanical rule        | A deterministic check, not a prose reminder                          |
| A handoff or tool call cost more than it returned | A narrower query, output or context pack                             |
| Instructions repeat defaults or no longer matter  | Prune or clarify the authoritative instruction                       |

## Propose

Each proposal names its category, the observed problem and evidence, the smallest change, the affected
instruction or tool, and the signal the next applicable task will show. A proposal must amend or replace
an existing instruction, never append one; if nothing can be amended or removed, it stays `deferred`.

A session never edits the workflow it is running. Proposals are applied in one batch per week, in a
session and PR of their own, and only when the same problem appears in at least two retrospectives or a
single occurrence stopped a task, failed CI or cost more than one session of rework. Every other proposal
stays `deferred` with the count of retrospectives that show it. Permission changes, weaker validation or
review and altered correction limits need the user's decision.
