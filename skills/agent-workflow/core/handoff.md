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

Reuse an implementer's identity for fixes with only the new findings, current diff and changed
configuration. A replacement gets the full role-scoped handoff. Every independent review is fresh.
Report command/status, candidate identity and failures; successful logs stay out of handoffs. Read
bounded, sanitized failure excerpts only when diagnosing a failure; retain log/run references.
