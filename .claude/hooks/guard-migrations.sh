#!/usr/bin/env bash
# PreToolUse (Edit|Write|Bash) hook: blocks changing a migration already tracked in git.
# A new migration (untracked, e.g. just created by `php artisan make:migration`) passes.
# For Bash it blocks write-style commands (sed -i, sd, rm, mv, cp, tee, redirects,
# git checkout/restore/rm...) whose arguments touch a tracked file under database/migrations/;
# reading commands such as bat or rg pass.
# Tests: .claude/hooks/tests/run.sh
set -u -f # -f: never expand globs in the inspected command
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

payload=$(cat)
tool=$(jq -r '.tool_name // empty' <<<"$payload")
cwd=$(jq -r '.cwd // empty' <<<"$payload")
[ -n "$cwd" ] || cwd=$PWD

reason() {
    block "'$1' is a migration already tracked in git; never change it. Create a new migration with 'php artisan make:migration' instead."
}

# Tracked files matching a path, directory or glob (git pathspec), from $cwd.
tracked_matches() {
    git -C "$cwd" ls-files -- "$1" 2>/dev/null
}

case "$tool" in
    Edit | Write | MultiEdit | NotebookEdit)
        path=$(jq -r '.tool_input.file_path // empty' <<<"$payload")
        [ -n "$path" ] || exit 0
        [[ $path = */database/migrations/* ]] || [[ $path = database/migrations/* ]] || exit 0
        [[ $path = /* ]] || path="$cwd/$path"
        # Resolve through the nearest existing ancestor: Write may target a new file.
        dir=$(dirname "$path")
        while [ ! -d "$dir" ]; do dir=$(dirname "$dir"); done
        if git -C "$dir" ls-files --error-unmatch -- "$path" >/dev/null 2>&1; then
            reason "$path"
        fi
        exit 0
        ;;
    Bash) ;;
    *) exit 0 ;;
esac

command=$(jq -r '.tool_input.command // empty' <<<"$payload")
[[ $command = *database/migrations* ]] || exit 0

writers=" sd rm mv cp tee truncate install dd perl sed awk ex vim vi nano patch "
while IFS= read -r segment; do
    [[ $segment = *database/migrations* ]] || continue
    # shellcheck disable=SC2086
    set -- $segment
    first=$(clean_token "${1:-}")
    second=$(clean_token "${2:-}")
    # any_token: every migration path in the segment is written; else only redirect targets are.
    any_token=0
    if [[ $writers = *" $first "* ]]; then
        # sed/perl/awk only write with in-place flags; the others always write.
        case "$first" in
            sed | perl | awk) [[ $segment =~ [[:space:]]-[a-zA-Z]*i ]] && any_token=1 ;;
            *) any_token=1 ;;
        esac
    fi
    if [ "$first" = git ]; then
        case "$second" in checkout | restore | rm | mv | reset | apply | stash) any_token=1 ;; esac
    fi

    previous=""
    for token in "$@"; do
        token=$(clean_token "$token")
        target=0
        [ "$any_token" -eq 1 ] && target=1
        if [[ $previous = '>' || $previous = '>>' ]]; then target=1; fi
        if [[ $token = '>'* && $token != '>' && $token != '>>' ]]; then
            target=1
            token=${token#>}
            token=${token#>}
        fi
        previous=$token
        [ "$target" -eq 1 ] || continue
        [[ $token = *database/migrations* ]] || continue
        if [ -n "$(tracked_matches "$token")" ]; then
            reason "$token"
        fi
    done
done < <(split_segments "$command")
exit 0
