# Stacked task PRs

Keep implementing in dependency order while earlier PRs await the user's merge. A stack is a sequence
of task branches: each successor starts from its predecessor's verified HEAD and its PR targets that
predecessor's branch. Each PR holds one task. The bottom PR targets the configured base.

## Admission

After filling compatible independent work, consider candidates the selector holds only for an unmerged
predecessor (`blocked-dependency` or `waiting-integration` on that edge) or its reserved paths. Stacking is
a standing option within the profile's authorization; keep the selector's ranking, the pipeline limits,
ownership, holds and correction budgets. Every other exclusion stays binding.

Check holds, correction stops and predecessor ownership first; a held or stopped predecessor rules out
the stack. Admit a successor only when all of these hold:

- Every start prerequisite is integrated, or supplied by an open, independently reviewed, CI-passed
  predecessor PR in the same chain.
- Each predecessor's remote HEAD matches its passing review and CI evidence, with no unpublished
  changes and no active correction: run the [readiness helper](../scripts/predecessor-readiness.mjs)
  (`--evidence FILE`) on evidence read from its owner record and live PR state, on every new session too.
- Overlapping paths belong only to that predecessor chain; they stay reserved against every task
  outside it. Shared hotspots serialize in plan order.
- The environment is verified for the effective base, including predecessor changes to dependencies,
  test configuration or CI.

Record the stack decision next to the selector result, citing each overridden reason and the evidence
above with its observation time. The helper is advisory and collects nothing: recheck live status and
ownership immediately before claiming. Stacking changes neither tracker blockers nor completion status.

## Implement, review and publish

Record in the task handoff and owner record: the configured base and SHA, the effective base branch and
fixed SHA, predecessor tasks/PRs and verified HEADs. Implementer, reviewers and check runners use the same
effective base, so review covers only the successor's diff while checks exercise the combined tree.
Before publication recheck the whole chain. The PR body lists predecessor PRs, the merge order and the
final target. A green stacked PR is ready for ordered integration, not proof its dependencies merged.

If a predecessor changes, pause its successors' publication and invalidate their HEAD/base evidence.
The same owners update unpublished branches by rebase and published branches by merging the updated
predecessor in a new commit, then repeat focused verification, required review and CI.

## Merge order and cleanup

Report the stack bottom to top for the user to merge in order. After a predecessor merges and its merge
commit is in the fresh base, retarget its direct successor to the configured base, inspect the resulting
diff (squash merges change it), incorporate the base and repeat the required checks and review before
reporting it ready. Keep predecessor worktrees until their successors no longer need them. A closed,
failed, held or unverifiable predecessor keeps its successors waiting; continue independent work.
