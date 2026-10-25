# Guixa agent rules

## Worktrees

Every change (code, specs, docs, tooling) happens in its own worktree, branched from `origin/main`. The
main checkout stays on `main` and is for reading only.

1. `git fetch origin`, then `git worktree add "<worktree-root>/<slug>" -b "<branch>" origin/main`. The
   worktree root, branch format and environment come from `.agent-workflow/profile.md`
   (`## Identities and paths`); without an issue, the slug is the branch's kebab-case description.
2. Run every command and edit every path inside that worktree.

Done when `git -C <worktree> status` shows the change and the main checkout's `git status` shows none.
