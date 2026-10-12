#!/usr/bin/env bash
# SubagentStop hook, only for the ticket-implementer agent (never a Stop hook on the main session).
# Runs the linters and the test suite in the agent's worktree and blocks the stop when they fail,
# so the implementer fixes them before reporting.
#
# - The worktree is found in the agent transcript (agent_transcript_path, else transcript_path):
#   the first /guixa-worktrees/dra-NN path of the prompt; else the payload cwd. When neither
#   identifies a ticket worktree the hook says so on stderr (and as a systemMessage) and skips.
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

# The payload cwd is the main checkout (the session lives there); the implementer works in a
# worktree named in its prompt, which /ship-ticket always fills with ../guixa-worktrees/dra-NN.
transcript=$(jq -r '.agent_transcript_path // .transcript_path // empty' <<<"$payload")
worktree=""
if [ -n "$transcript" ] && [ -r "$transcript" ]; then
    worktree=$(rg -o -m1 '/[^[:space:]"'"'"'\\]*guixa-worktrees/dra-[0-9]+' "$transcript" 2>/dev/null | head -n 1)
fi
# A relative mention (../guixa-worktrees/dra-NN) matches as /guixa-worktrees/dra-NN: resolve it next to the checkout.
if [ -n "$worktree" ] && [ ! -d "$worktree" ] && [ -d "$cwd/..$worktree" ]; then
    worktree="$cwd/..$worktree"
fi
if [ -n "$worktree" ] && [ -d "$worktree" ]; then
    cwd=$worktree
else
    worktree=""
fi

skip() {
    echo "implementer-stop-checks: $1; checks skipped." >&2
    jq -n --arg m "implementer-stop-checks: $1; checks skipped." '{systemMessage: $m}'
    exit 0
}

cd "$cwd" 2>/dev/null || skip "cannot enter $cwd"
git rev-parse --is-inside-work-tree >/dev/null 2>&1 || skip "$cwd is not a git work tree"
if [ -z "$worktree" ]; then
    branch=$(git branch --show-current)
    { [ -z "$branch" ] || [ "$branch" = main ]; } && skip "no guixa-worktrees/dra-NN path in the agent transcript and $cwd is on '${branch:-detached HEAD}'"
fi

base=$(git merge-base HEAD origin/main 2>/dev/null || git merge-base HEAD main 2>/dev/null || true)
if [ -n "$base" ]; then
    changed=$(git diff --name-only "$base")
else
    changed=$(git diff --name-only HEAD 2>/dev/null)
fi
[ -n "$changed" ] || exit 0

db=${DB_DATABASE:-}
if [ -z "$db" ] || [ "$db" = guixa ] || [ "$db" = testing ]; then
    ticket=$(basename "$PWD" | sed -nE 's#.*(dra-[0-9]+).*#\1#p')
    [ -n "$ticket" ] || ticket=$(git branch --show-current | sed -nE 's#.*(dra-[0-9]+).*#\1#p')
    [ -n "$ticket" ] || skip "cannot derive DB_DATABASE (no dra-NN in the directory or branch)"
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
Fix it and finish again. If you stopped on an undecided edge case or could not commit, finish again with your questions; do not guess."
        jq -n --arg reason "$reason" '{decision: "block", reason: $reason}'
        exit 0
    fi
done <<<"$checks"
exit 0
