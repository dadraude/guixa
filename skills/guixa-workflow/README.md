# One workflow, two runtimes

The process lives in [core](core/session.md), runtime operations in [adapters](adapters/codex.md), and
project identities/commands in [project.md](project.md). Update each rule in one place. Installing or
testing these files never authorizes Linear writes or implementation/publishing actions.

## Use

| Task | Codex | Claude Code |
| --- | --- | --- |
| Coordinate implementation | `$guixa-implement-session` | `/implement-session` |
| Implement one admitted task | `$guixa-implement-task DRA-NN` | `/implement-task DRA-NN` |
| Draft a ticket batch | `$guixa-to-tickets` | `/to-tickets` |

Codex entrypoints live in `.agents/skills/guixa-*`; Claude entrypoints stay in `.claude/skills/`.
Both link the same files. Codex names are prefixed to avoid collisions with personal/global skills.
No global installation or symlink is required. If a running client does not discover new skills,
reload its skills or start a fresh session; do not claim discovery from the registry alone.

## Guarantees

- One coordinator per feature across all runtimes; worktree isolation is not a distributed lock.
- Shared-file/contract reservations last through verified integration, including after PR opening.
- Fresh independent review, isolated task databases and rechecking after a base change remain required.
- Grouping parents are not executable; hierarchy, prerequisites and contextual relations stay distinct.
- Missing tools, permissions, independent review or native relation support stop the affected action.
- Existing ticket reorganization and remote actions still require explicit authorization.

To add a runtime, implement capability mappings in an adapter and a thin entrypoint. Do not fork the
core or claim that every AI chat service can run it: it needs repository access, required execution
and delegation capabilities, and authorized connectors. Keep explicit-only invocation in its metadata.

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
