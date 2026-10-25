#!/usr/bin/env bash
# Blocks file edits in the main checkout: every change belongs in a worktree (see AGENTS.md).
set -euo pipefail

file_path=$(jq -r '.tool_input.file_path // .tool_input.notebook_path // empty')
[[ -z "$file_path" ]] && exit 0

# A new file's directory may not exist yet; check the nearest existing ancestor.
dir=$(dirname "$file_path")
while [[ ! -d "$dir" ]]; do dir=$(dirname "$dir"); done

toplevel=$(git -C "$dir" rev-parse --show-toplevel 2>/dev/null) || exit 0
common_dir=$(git -C "$dir" rev-parse --path-format=absolute --git-common-dir)
main_checkout=$(dirname "$common_dir")
[[ "$toplevel" != "$main_checkout" ]] && exit 0

echo "Blocked: $file_path is in the main checkout. Create a worktree first (AGENTS.md § Worktrees)." >&2
exit 2
