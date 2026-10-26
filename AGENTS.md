# Guixa agent rules

## Worktrees

Every change (code, specs, docs, tooling) happens in its own worktree, branched from `origin/main`. The
main checkout stays on `main` and is for reading only.

1. `git fetch origin`, then `git worktree add "<worktree-root>/<slug>" -b "<branch>" origin/main`. The
   worktree root, branch format and environment come from `.agent-workflow/profile.md`
   (`## Identities and paths`); without an issue, the slug is the branch's kebab-case description.
2. Run every command and edit every path inside that worktree.

Done when `git -C <worktree> status` shows the change and the main checkout's `git status` shows none.

## Measure before screenshot

Opening an image costs far more tokens than reading numbers. To check a style fix, measure it as text:

1. Read the state with `getComputedStyle` and `getBoundingClientRect` on the changed elements (browser
   JS tool), or `measureLayout`/`measureFocus` from `skills/agent-workflow/scripts/visual/measure.mjs`.
   Compare against the expected values (height, spacing, colour, overflow).
2. Use `read_page`/`find` for structure and text, never a screenshot.
3. Let the visual runner (`skills/agent-workflow/references/visual.md`) judge captures by its report;
   open a PNG only for a failed case or a required human inspection.
4. When an image is still needed, `zoom` on the affected region or capture at `scale` 0.3–0.5, once,
   as the final confirmation rather than on every iteration.

This does not lift the visual-review gates: required inspections still happen.
