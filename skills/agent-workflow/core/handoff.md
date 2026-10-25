# Role-scoped context

Coordinator only: load the selected adapter, project profile and requested process once. Resolve
configuration from their authoritative sources; send each worker only the row below, not the wrapper,
whole profile, adapter, scheduling process or conversation transcript. Workers never delegate.

Every handoff includes task/role, explicit cwd, fixed HEAD/base when applicable, allowed operations,
authorized remote destination/session (only for remote roles), relevant evidence and stop conditions.
Include exact source paths and section headings for supplied configuration and requirements so missing
or contradictory evidence can be checked without loading unrelated documents. Host permissions still
apply; missing required inputs stop the affected action rather than inviting guesses.

| Role                    | Load / receive                                                                                                                                                                                                                      |
| ----------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Discovery               | Selected task rows, tracker identity/status/mapping settings, authorized read scope; relevant source only to establish prerequisites and edit surfaces.                                                                             |
| Implementer             | [Implementer brief](implementer.md), linked acceptance/requirements/scenarios/design and applicable standards; resolved environment, isolation/setup/focused commands, commit convention; scheduling evidence and allowed surfaces. |
| Reviewer                | [Reviewer brief](reviewer.md), the same requirements/standards and allowed surfaces, fixed diff, predecessor/ownership evidence, deviation entries and focused results; no setup, tracker or publication instructions.              |
| Local check runner      | Exact focused commands, environment/isolation constraints, expected HEAD/base and result format; no implementation or review process.                                                                                               |
| Preparation             | Task pipeline's preparation section, root/worktree/base, allowed surfaces, generator commands, prior evidence and conflict rules.                                                                                                   |
| Publisher / CI observer | Task pipeline's publication/CI sections, exact candidate and PR identity, verified authorization, expected CI checks and status/report contract; no implementation history or credentials in prompts.                               |

For code-writing roles, include the profile's Project conventions and only relevant project standards.
The implementer also receives the full isolation constraints from Commands; resolved values cannot
weaken those rules. A reviewer needing an extra probe requests its environment/authorization from the
coordinator rather than inheriting write or remote permissions. Task-specific test prerequisites are
passed only to roles that execute those tests.

Follow [implementation ownership](#implementation-ownership) for every implementation continuation.
Every independent review is fresh; this freshness rule never applies to the implementer.
Report command/status, candidate identity and failures; successful logs stay out of handoffs. Read
bounded, sanitized failure excerpts only when diagnosing a failure; retain log/run references.

For UI work, include the [visual contract](../references/visual.md) only for the implementer, reviewer
and any visual check runner. Pass the declared matrix/inventory, manifest, current report/gallery and
acceptance-specific results. Fix handoffs carry the accumulated finding ledger and pattern sweep, not
just the most recent defect. Keep the actual total review count through publication and CI corrections.

## Implementation ownership

Assign one implementer per admitted task. Preserve its runtime/host and worker ID in the profile's
ignored, task-local implementation owner record immediately after first dispatch. Retain the record
and a durable handoff through review, publication, CI corrections and integration follow-ups. Record:

```text
task, worktree, branch, runtime/host, implementer_id, current_head, fixed_base,
handoff_path, component_plan_path (for UI), replacement_history
```

For every correction or continuation, read that record and resume/message the same implementer using
the adapter's supported mechanism. Idle or completed worker turns, failed checks, review findings,
publication, session resumption and context compaction are not reasons to spawn a new implementer.
Do not replace it to get a different design, reset its context or work around correction budgets.
Only the assigned implementer changes task implementation; reviewers remain read-only, and the
preparation role retains only its existing integration duties.

If the recorded worker cannot be resumed, verify the runtime limitation first and ensure the previous
worker cannot continue writing. Only genuine unavailability permits a replacement. Record the reason,
old/new identities and full handoff before the replacement writes. Carry over the component decisions,
actual diff, scope, HEAD/base, evidence, findings and all counters. If identity or write ownership cannot
be established safely, stop the affected task. Never silently create a replacement after a timeout.
The owner record is recovery evidence, not a cross-process lock or permission to bypass admission.
