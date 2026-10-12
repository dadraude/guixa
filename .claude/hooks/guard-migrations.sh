#!/usr/bin/env bash
# PreToolUse (Edit|Write|Bash) hook: blocks changing a migration that already exists on main.
# "Already on main" = the file exists at the merge-base with origin/main, so a migration added
# on the ticket's own branch can still be fixed in a correction pass. Without origin/main (or a
# merge-base) it falls back to "tracked in the index". A new migration (e.g. just created by
# `php artisan make:migration`) passes.
# For Bash it blocks write-style commands (sed -i, sd, rm, mv, cp, tee, redirects, find -delete,
# git rm...) whose arguments touch such a file under database/migrations/, wherever the path
# points (another repo or worktree included); `cd <dir>` segments are followed. Reading commands
# such as bat or rg pass. For cp and install only the last argument (the destination) counts.
# Tests: .claude/hooks/tests/run.sh
set -u -f # -f: never expand globs in the inspected command
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

payload=$(cat)
tool=$(jq -r '.tool_name // empty' <<<"$payload")
cwd=$(jq -r '.cwd // empty' <<<"$payload")
[ -n "$cwd" ] || cwd=$PWD

reason() {
    block "'$1' is a migration already on main; never change it. Create a new migration with 'php artisan make:migration' instead."
}

# Absolute, physical form of a path whose tail may not exist yet.
absolute_path() {
    local path=$1 base=$2 existing rest
    [[ $path = /* ]] || path="$base/$path"
    path=${path%/}
    existing=$(nearest_existing_dir "$path") || return 1
    if [ -d "$path" ]; then
        (cd "$path" && pwd -P)
        return
    fi
    printf '%s/%s' "${existing%/}" "$(basename "$path")"
}

# Succeeds when $1 (absolute path or glob) matches a file that exists on main.
on_main() {
    local abs=$1 dir top base match rel
    dir=$(nearest_existing_dir "$abs") || return 1
    top=$(git -C "$dir" rev-parse --show-toplevel 2>/dev/null) || return 1
    base=$(git -C "$top" merge-base HEAD origin/main 2>/dev/null || true)
    if [ -z "$base" ]; then
        [ -n "$(git -C "$top" ls-files -- "$abs" 2>/dev/null)" ]
        return
    fi
    rel=${abs#"$top"/}
    # A literal path that exists at the merge-base (also covers files already removed from the index),
    # or at origin/main itself: a parent branch squashed into main shares no commit with a stacked branch,
    # so its merge-base is still the old one.
    local ref
    for ref in "$base" origin/main; do
        git -C "$top" cat-file -e "$ref:$rel" 2>/dev/null && return 0
        while IFS= read -r match; do
            git -C "$top" cat-file -e "$ref:$match" 2>/dev/null && return 0
        done < <(git -C "$top" ls-files -- "$abs" 2>/dev/null)
    done
    return 1
}

check_path() { # path base
    local abs
    abs=$(absolute_path "$1" "$2") || return 0
    [[ $abs = *database/migrations* ]] || return 0
    if on_main "$abs"; then
        reason "$1"
    fi
}

case "$tool" in
    Edit | Write | MultiEdit | NotebookEdit)
        path=$(jq -r '.tool_input.file_path // empty' <<<"$payload")
        [ -n "$path" ] || exit 0
        check_path "$path" "$cwd"
        exit 0
        ;;
    Bash) ;;
    *) exit 0 ;;
esac

command=$(jq -r '.tool_input.command // empty' <<<"$payload")
command=$(join_continuations "$command")
[[ $command = *migrations* ]] || exit 0

redirect_only='^([0-9]*>>?|&>>?)$'
redirect_attached='^([0-9]*>>?|&>>?)(.+)$'
writers=" sd rm mv cp tee truncate install dd perl sed awk ex vim vi nano patch "
wrappers=" xargs env sudo nohup time command "
cur=$cwd
while IFS= read -r segment; do
    # shellcheck disable=SC2086
    set -- $segment
    [ "$#" -gt 0 ] || continue
    args=()
    for arg in "$@"; do args+=("$(clean_token "$arg")"); done
    set -- "${args[@]}"

    if [ "$1" = cd ]; then
        cur=$(resolve_dir "$cur" "${2:-}") || cur=$cwd
        continue
    fi

    # Skip wrappers (xargs, env, sudo...), their flags and VAR=value assignments.
    via_xargs=0
    while [ "$#" -gt 0 ]; do
        if [[ $wrappers = *" $1 "* ]]; then
            [ "$1" = xargs ] && via_xargs=1
            shift
            while [ "$#" -gt 0 ] && [[ $1 = -* || $1 = *=* || $1 = '{}' ]]; do shift; done
        elif [[ $1 = *=* && $1 != -* ]]; then
            shift
        else
            break
        fi
    done
    [ "$#" -gt 0 ] || continue
    first=$1
    second=${2:-}

    # mode: none | all (every path argument is written) | last (only the destination) | redirect
    mode=none
    if [[ $writers = *" $first "* ]]; then
        case "$first" in
            # sed/perl/awk only write with in-place flags.
            sed | perl | awk) [[ $* =~ (^|[[:space:]])(-[a-zA-Z]*i|--in-place) ]] && mode=all ;;
            cp | install) mode=last ;;
            *) mode=all ;;
        esac
    elif [ "$first" = find ]; then
        # -delete, or -exec/-execdir/-ok running a writer on the found files.
        for ((n = 2; n <= $#; n++)); do
            word=${!n}
            case "$word" in
                -delete) mode=all ;;
                -exec | -execdir | -ok | -okdir)
                    m=$((n + 1))
                    [[ $writers = *" ${!m:-} "* ]] && mode=all
                    ;;
            esac
        done
    elif [ "$first" = git ]; then
        case "$second" in rm | mv | reset | apply | stash) mode=all ;; esac
    fi

    # xargs gets its paths from an earlier pipeline segment (`fd . database/migrations | xargs rm`), which
    # this per-segment check cannot see: with a writer behind xargs, any mention of the directory blocks.
    if [ "$via_xargs" -eq 1 ] && [ "$mode" != none ] && [[ $command = *database/migrations* ]]; then
        reason "$segment"
    fi

    # Destination of cp/install/mv: last non-flag argument. When it is an existing directory the
    # written files are the sources' basenames inside it (copying into the folder is how a new migration appears).
    last=""
    if [ "$mode" = last ] || [ "$first" = mv ]; then
        for token in "$@"; do [[ $token = -* ]] || last=$token; done
        if [ -n "$last" ] && [ -d "$(absolute_path "$last" "$cur")" ]; then
            for token in "$@"; do
                [[ $token = -* || $token = "$first" || $token = "$last" ]] || check_path "$last/$(basename "$token")" "$cur"
            done
            [ "$mode" = last ] && continue
            mode=sources
        fi
    fi

    previous=""
    for token in "$@"; do
        target=0
        [ "$mode" = all ] && target=1
        [ "$mode" = sources ] && [ "$token" != "$last" ] && target=1
        [ "$mode" = last ] && [ "$token" = "$last" ] && target=1
        # Redirections, with an optional file descriptor: >f >>f 1>f 2>>f &>f, attached or separate.
        if [[ $previous =~ $redirect_only ]]; then target=1; fi
        if [[ $token =~ $redirect_attached ]]; then
            token=${BASH_REMATCH[2]}
            # `2>&1` duplicates a descriptor, it writes no file.
            [[ $token = '&'* ]] || target=1
        fi
        previous=$token
        [ "$target" -eq 1 ] || continue
        [[ $token = -* ]] && continue
        check_path "$token" "$cur"
    done
done < <(split_segments "$command")
exit 0
