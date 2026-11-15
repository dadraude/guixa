# Guixa agent rules

## Worktrees

Every change (code, specs, docs, tooling) happens in its own worktree, branched from `origin/main`. The
main checkout stays on `main` and is for reading only.

1. `git fetch origin`, then `git worktree add "<worktree-root>/<slug>" -b "<branch>" origin/main`. The
   worktree root, branch format and environment come from `.agent-workflow/profile.md`
   (`## Identities and paths`); without an issue, the slug is the branch's kebab-case description.
2. Run every command and edit every path inside that worktree.

Done when `git -C <worktree> status` shows the change and the main checkout's `git status` shows none.

## Workflow rules

Standing rules on how the workflow operates (statuses, authorizations, cleanup) go in
`.agent-workflow/profile.md` or `skills/agent-workflow/core/` through a worktree PR, not in agent memory.

## Demo seeder

When a change adds or alters data a user sees (roles, entities, states, new screens), check
`database/seeders/DemoSeeder.php` and update it so the demo shows the change. Extend
`tests/Feature/Database/DemoSeederTest.php` to cover what you add. If the seeder needs no change, say so
in the handoff.

## Versioned history

Record each new Request, Q&A, Change or Deviation in its own file under
`specs/NNN-feature/history/`, using the rules and commands in [specs/README.md](specs/README.md#history).
Existing `log.md` files are frozen archives: never append, rewrite, split or delete them.
Before changing a feature, consult both its legacy log and new entries with
`node .agent-workflow/scripts/history.mjs read NNN-feature`; omit the feature to read all history.
Create entries with the helper's `add` command, commit them with the change, and pass their exact paths
in handoffs. Never maintain a shared index or edit an existing entry; corrections get a new entry
referencing the original. History in other branches is visible only after those changes are integrated.

## Measure before screenshot

Opening an image costs far more tokens than reading numbers. To check a style fix, measure it as text:

1. Read the state with `getComputedStyle` and `getBoundingClientRect` on the changed elements (browser
   JS tool), or `measureLayout`/`measureFocus` from `skills/agent-workflow/scripts/visual/measure.mjs`.
   Compare against the expected values (height, spacing, colour, overflow).
2. Use `read_page`/`find` for structure and text, never a screenshot.
3. Use the visual runner (`skills/agent-workflow/references/visual.md`) in its default measurement mode.
   Routine review generates and opens no screenshots. Generate a small preview only for a new design
   direction, an unexplained failure, or an explicit request.
4. When an image is still needed, `zoom` on the affected region or capture at `scale` 0.3–0.5, once,
   as the final confirmation rather than on every iteration.

The review gates use DOM, measurement, interaction and source evidence. Explicit visual-design approval
remains required where specified; optional previews do not replace behavior or accessibility checks.
