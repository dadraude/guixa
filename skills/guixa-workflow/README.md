# One workflow, two runtimes

The process lives in [core](core/session.md), runtime operations in [adapters](adapters/codex.md), and
project identities/commands in [project.md](project.md). Update each rule in one place. Permission for
remote and implementation actions comes only from the profile's [standing authorization](project.md#standing-authorization).

## Use

| Task                        | Codex                          | Claude Code              |
| --------------------------- | ------------------------------ | ------------------------ |
| Coordinate implementation   | `$guixa-implement-session`     | `/implement-session`     |
| Implement one admitted task | `$guixa-implement-task DRA-NN` | `/implement-task DRA-NN` |
| Draft a ticket batch        | `$guixa-to-tickets`            | `/to-tickets`            |

Codex entrypoints live in `.agents/skills/guixa-*`; Claude entrypoints stay in `.claude/skills/`.
Both link the same files. Codex names are prefixed to avoid collisions with personal/global skills.
No global installation or symlink is required. If a running client does not discover new skills,
reload its skills or start a fresh session; do not claim discovery from the registry alone.

## Guarantees

- One coordinator per feature across all runtimes; worktree isolation is not a distributed lock.
- Shared-file/contract reservations last through verified integration, including after PR opening.
- Fresh independent review, isolated task databases and focused rechecking after a base change remain required.
- Local checks cover the changed behavior; full build/lint/types/tests run in CI after publication.
- An opened PR is `pending-ci`, not validated. Cleanup waits until the PR is merged and contained in `main`.
- Grouping parents are not executable; hierarchy, prerequisites and contextual relations stay distinct.
- Missing tools, permissions, independent review or native relation support stop the affected action.
- Merging, product decisions and ticket publication or reorganization stay with the user.

To add a runtime, implement capability mappings in an adapter and a thin entrypoint. Do not fork the
core or claim that every AI chat service can run it: it needs repository access, required execution
and delegation capabilities, and authorized connectors. Keep explicit-only invocation in its metadata.

## Verification and context ownership

The implementer proves focused RED/GREEN; the coordinator reuses matching evidence rather than
repeating it. A fresh reviewer checks semantics and requirements, not another full suite. After
publication, CI owns broad validation. See [the task lifecycle](core/task.md) for pending,
failed, stale and successful results, bounded corrections and post-publication fixes.

Only the coordinator loads the whole adapter/profile/process. Follow [role-scoped context](core/handoff.md)
for workers: exact relevant briefs, source sections, resolved settings and candidate evidence, not the
whole conversation. Keep passing logs out of prompts; request bounded failure output when needed.
This reduces duplicated work/context, not the checks required for a validated delivery.

## Verify without side effects

```sh
PYTHONDONTWRITEBYTECODE=1 python3 -m unittest discover -s skills/guixa-workflow/tests
```

For an isolated copy not registered with Herd, resolve `herd which-php` in the primary checkout first
and pass that absolute interpreter path as `GUIXA_WORKFLOW_PHP` to the test command in the copy.
The helper test uses only an invalid ticket (rejected before database access); it skips when no PHP
runtime is available. An explicitly supplied invalid interpreter is a failure, not a skip.

These structural checks validate shared references, entrypoints and separation, not live agent behavior.
Also dry-run the same scenarios for both adapters: overlapping vs disjoint tasks, PR-open reservation,
squash integration, scope expansion, clean rebase, exhausted review, unavailable capabilities, grouping
parents and partial ticket publication. Never run a live implementation or create a ticket as a smoke test.
Also exercise focused-only local verification, CI failure/fix, pending/skipped/stale CI, changed PR head
or base, merge-ref identity, missing publication/update permission, and each role's minimum handoff.
Instruction-only changes have no executable application behavior for RED/GREEN; structural tests
and independent scenario walkthroughs are the proportional checks, not `composer ci:check` locally.
