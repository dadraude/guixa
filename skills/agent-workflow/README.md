# One workflow, two runtimes

The process lives in [core](core/session.md) and runtime operations in [adapters](adapters/codex.md);
both are project-independent. Each project supplies `.agent-workflow/profile.md` (identities, tracker,
commands, conventions and the standing authorization) plus any cleanup scripts in `.agent-workflow/scripts/`.
Update each rule in one place. Permission for remote and implementation actions comes only from the
profile's standing authorization.

## Install in a project

1. Copy `skills/agent-workflow/` unchanged.
2. Copy [profile-template.md](profile-template.md) to `.agent-workflow/profile.md` and fill it in.
3. For Codex, add `.codex/agents/agent-workflow-<tier>.toml` for each tier in the profile's Model
   routing, setting `model` and `model_reasoning_effort`; workers spawned without one inherit the session's.
4. Add the thin entrypoints, each linking its adapter, the profile and its core process:

| Task                        | Codex (`.agents/skills/`)          | Claude Code (`.claude/skills/`) |
| --------------------------- | ---------------------------------- | ------------------------------- |
| Coordinate implementation   | `$<prefix>-implement-session`      | `/implement-session`            |
| Implement one admitted task | `$<prefix>-implement-task <issue>` | `/implement-task <issue>`       |
| Draft a ticket batch        | `$<prefix>-to-tickets`             | `/to-tickets`                   |

Codex names take the profile's prefix to avoid collisions with personal/global skills. Keep
explicit-only invocation in each entrypoint's metadata. No global installation or symlink is required. If a running client does not discover new skills,
reload its skills or start a fresh session; do not claim discovery from the registry alone.

## Guarantees

- One coordinator per feature across all runtimes; worktree isolation is not a distributed lock.
- Shared-file/contract reservations last through verified integration, including after PR opening.
- One retained implementer owns each task through corrections and CI; independent reviewers are fresh.
- Isolated task databases and focused rechecking after a base change remain required.
- Local checks cover the changed behavior; full build/lint/types/tests run in CI after publication.
- An opened PR is `pending-ci`, not validated. Cleanup waits until the PR is merged and contained in `main`.
- Grouping parents are not executable; hierarchy, prerequisites and contextual relations stay distinct.
- Missing tools, permissions, independent review or native relation support stop the affected action.
- Merging, product decisions and ticket publication or reorganization stay with the user.

To add a runtime, implement capability mappings in an adapter and a thin entrypoint. Do not fork the
core or claim that every AI chat service can run it: it needs repository access, required execution
and delegation capabilities, and authorized connectors. Keep explicit-only invocation in its metadata.

## Planning

Ticket drafting uses [planning evidence and handoffs](references/planning.md): current readiness
checks, bounded acceptance/contract excerpts, partial requirement allocation and an explicit dependency
frontier. Projects can reuse it while authoring canonical plans/tasks through their own document
approval flow. The source documents remain authoritative; draft approval and publication permission
stay separate. These checks adapt ideas from the supplied `plan-work` skill without adopting its
project-specific registries, mandatory database/UI loop or tracker tooling.

## Verification and context ownership

The implementer proves focused RED/GREEN; the coordinator reuses matching evidence rather than
repeating it. A fresh reviewer checks semantics and requirements, not another full suite. After
publication, CI owns broad validation. See [the task lifecycle](core/task.md) for pending,
failed, stale and successful results, bounded corrections and post-publication fixes.

Only the coordinator loads the whole adapter/profile/process. Follow [role-scoped context](core/handoff.md)
for workers: exact relevant briefs, source sections, resolved settings and candidate evidence, not the
whole conversation. Keep passing logs out of prompts; request bounded failure output when needed.
This reduces duplicated work/context, not the checks required for a validated delivery.

## Visual tasks

Use the [visual workflow](references/visual.md) for rendered UI changes: declare coverage before
implementation, require an explicit existing-component reuse plan, reuse the [shared browser harness](scripts/visual/run.mjs), and require the coverage
gate before independent review. Corrections check the same defect pattern across the admitted scope.
The project profile supplies matrix dimensions and acceptance thresholds; the shared core stays portable.

## Verify without side effects

```sh
PYTHONDONTWRITEBYTECODE=1 python3 -m unittest discover -s skills/agent-workflow/tests
```

Project-specific checks (such as a cleanup script) live in `.agent-workflow/tests/` and run the same way.

These structural checks validate shared references, entrypoints, the profile's required sections and
that the core stays project-independent, not live agent behavior.
Also dry-run the same scenarios for both adapters: overlapping vs disjoint tasks, PR-open reservation,
squash integration, scope expansion, clean rebase, exhausted review, unavailable capabilities, grouping
parents and partial ticket publication. Never run a live implementation or create a ticket as a smoke test.
Also exercise focused-only local verification, CI failure/fix, pending/skipped/stale CI, changed PR head
or base, merge-ref identity, missing publication/update permission, and each role's minimum handoff.
Instruction-only changes have no executable application behavior for RED/GREEN; structural tests
and independent scenario walkthroughs are the proportional checks, not the full CI suite locally.

The shared visual harness has dependency-free Node tests and real-browser regression fixtures:

```sh
node --test skills/agent-workflow/tests/visual/coverage.test.mjs
CHROME_BIN="/path/to/installed/chrome" node --test skills/agent-workflow/tests/visual/*.test.mjs
```

Browser fixtures run in temporary directories with an isolated browser profile; they do not use the
application or its database. A missing browser is a failed prerequisite, not a silently skipped check.
