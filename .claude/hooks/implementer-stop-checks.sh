#!/usr/bin/env bash
# SubagentStop hook, only for the ticket-implementer agent (never a Stop hook on the main session).
# Runs the linters and the test suite in the agent's worktree and blocks the stop when they fail,
# so the implementer fixes them before reporting.
#
# - Skipped when no tracked file differs from the merge-base with origin/main (nothing to check).
# - Skipped when stop_hook_active is true: Claude Code sets it after one block, so it never loops.
# - DB_DATABASE is inherited from the environment, else derived from the branch/worktree name
#   (dra-134 -> testing_dra134). It never falls back to `guixa` or `testing`: when it cannot
#   be derived the hook skips and says so.
# - GUIXA_STOP_CHECKS overrides the checks (one shell command per line); used by the tests.
# Tests: .claude/hooks/tests/run.sh
set -u

payload=$(cat)
[ "$(jq -r '.agent_type // empty' <<<"$payload")" = ticket-implementer ] || exit 0
[ "$(jq -r '.stop_hook_active // false' <<<"$payload")" = true ] && exit 0

cwd=$(jq -r '.cwd // empty' <<<"$payload")
[ -n "$cwd" ] || cwd=$PWD
cd "$cwd" 2>/dev/null || exit 0
git rev-parse --is-inside-work-tree >/dev/null 2>&1 || exit 0

base=$(git merge-base HEAD origin/main 2>/dev/null || git merge-base HEAD main 2>/dev/null || true)
if [ -n "$base" ]; then
    changed=$(git diff --name-only "$base")
else
    changed=$(git diff --name-only HEAD 2>/dev/null)
fi
[ -n "$changed" ] || exit 0

db=${DB_DATABASE:-}
if [ -z "$db" ] || [ "$db" = guixa ] || [ "$db" = testing ]; then
    ticket=$(git branch --show-current | sed -nE 's#.*(dra-[0-9]+).*#\1#p')
    [ -n "$ticket" ] || ticket=$(basename "$PWD" | sed -nE 's#.*(dra-[0-9]+).*#\1#p')
    if [ -z "$ticket" ]; then
        echo "implementer-stop-checks: cannot derive DB_DATABASE (no dra-NN in branch or directory); checks skipped." >&2
        exit 0
    fi
    db="testing_${ticket//-/}"
fi
export DB_DATABASE=$db

default_checks=$(cat <<'CHECKS'
vendor/bin/pint --parallel --test
npm run check
vendor/bin/phpstan analyse --memory-limit=1G
php artisan test --parallel
CHECKS
)
checks=${GUIXA_STOP_CHECKS:-$default_checks}

while IFS= read -r check; do
    [ -n "$check" ] || continue
    if ! output=$(bash -c "$check" 2>&1); then
        reason="Check failed in $PWD (DB_DATABASE=$DB_DATABASE): $check
$(tail -n 40 <<<"$output")
Fix it and finish again."
        jq -n --arg reason "$reason" '{decision: "block", reason: $reason}'
        exit 0
    fi
done <<<"$checks"
exit 0
