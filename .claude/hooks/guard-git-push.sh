#!/usr/bin/env bash
# PreToolUse (Bash) hook: blocks every way of pushing to main and of forcing a push.
# Blocked: `git push` while on main, any refspec whose destination is main
# (`origin main`, `HEAD:main`, `HEAD:refs/heads/main`, `+main`, `:main`),
# `--force`, `-f` (also inside combined flags), `--force-with-lease`,
# `--force-if-includes`, `--all` and `--mirror`.
# Allowed: `git push -u origin improvement/dra-NN-...` and everything that is not a push.
# Tests: .claude/hooks/tests/run.sh
set -u
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

payload=$(cat)
command=$(jq -r '.tool_input.command // empty' <<<"$payload")
cwd=$(jq -r '.cwd // empty' <<<"$payload")
[ -n "$command" ] || exit 0
[ -n "$cwd" ] || cwd=$PWD

check_segment() {
    local -a tokens=()
    local raw
    for raw in $1; do
        tokens+=("$(clean_token "$raw")")
    done

    local i=0 count=${#tokens[@]} dir=$cwd
    while [ "$i" -lt "$count" ]; do
        [ "$(basename "${tokens[$i]}")" = git ] && break
        i=$((i + 1))
    done
    [ "$i" -lt "$count" ] || return 0

    # Skip git global options until the subcommand.
    i=$((i + 1))
    while [ "$i" -lt "$count" ]; do
        case "${tokens[$i]}" in
            -C)
                dir=${tokens[$((i + 1))]:-$dir}
                [[ $dir = /* ]] || dir="$cwd/$dir"
                i=$((i + 2))
                ;;
            -c | --git-dir | --work-tree | --namespace) i=$((i + 2)) ;;
            -*) i=$((i + 1)) ;;
            *) break ;;
        esac
    done
    [ "${tokens[$i]:-}" = push ] || return 0

    local current
    current=$(git -C "$dir" branch --show-current 2>/dev/null || true)

    local -a positional=()
    local token
    for token in "${tokens[@]:$((i + 1))}"; do
        case "$token" in
            --force | --force-with-lease | --force-with-lease=* | --force-if-includes)
                block "forcing a push is not allowed ($token). Push with a plain 'git push -u origin <branch>'." ;;
            --all | --mirror)
                block "'git push $token' would also push main. Push a single branch instead." ;;
            --*) ;;
            -*)
                if [[ $token =~ ^-[a-zA-Z]*f[a-zA-Z]*$ ]]; then
                    block "forcing a push is not allowed ($token). Push with a plain 'git push -u origin <branch>'."
                fi
                ;;
            *) positional+=("$token") ;;
        esac
    done

    # First positional argument is the remote; the rest are refspecs.
    local refspecs=("${positional[@]:1}")
    if [ "${#refspecs[@]}" -eq 0 ]; then
        [ "$current" = main ] && block "'git push' while on main is not allowed. Work on a ticket branch."
        return 0
    fi

    local refspec dest
    for refspec in "${refspecs[@]}"; do
        if [[ $refspec = +* ]]; then
            block "forced refspec '$refspec' is not allowed (leading +)."
        fi
        dest=${refspec#*:}
        [ "$dest" = HEAD ] && dest=$current
        dest=${dest#refs/heads/}
        if [ "$dest" = main ]; then
            block "pushing to main is not allowed ('$refspec'). Push the ticket branch and open a PR."
        fi
    done
}

while IFS= read -r segment; do
    check_segment "$segment"
done < <(split_segments "$command")
exit 0
