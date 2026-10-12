#!/usr/bin/env bash
# PreToolUse (Bash) hook: blocks every way of pushing to main and of forcing a push.
# Blocked: `git push` while on main, any refspec whose destination is main
# (`origin main`, `HEAD:main`, `HEAD:refs/heads/main`, `HEAD:heads/main`, `+main`, `:main`),
# pattern refspecs (`*`), `--force`, `-f` (also inside combined flags), `--force-with-lease`,
# `--force-if-includes`, `--all` and `--mirror` (also their unambiguous abbreviations).
# The current branch follows `cd <dir>` segments and `git -C <dir>`; when the directory
# cannot be resolved, a push without a refspec is blocked.
# Allowed: `git push -u origin improvement/dra-NN-...` and everything that is not a push.
# Segments starting with gh, echo or printf are ignored (they only quote the text).
# Known gap, guarded only by the deny rules in settings.json: `git -c alias.p=push p origin main`
# and an inline `remote.<name>.push` config.
# Tests: .claude/hooks/tests/run.sh
set -u
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

payload=$(cat)
command=$(jq -r '.tool_input.command // empty' <<<"$payload")
cwd=$(jq -r '.cwd // empty' <<<"$payload")
[ -n "$command" ] || exit 0
[ -n "$cwd" ] || cwd=$PWD

# Directory the next segment runs in, updated by `cd` segments.
cur=$cwd
cur_known=1

# Sets matched_option when the long option is (an abbreviation of) a dangerous one.
matched_option=""
dangerous_long_option() {
    local name=${1%%=*} entry opt min
    for entry in "--force:5" "--force-with-lease:5" "--force-if-includes:5" "--all:4" "--mirror:4"; do
        opt=${entry%:*}
        min=${entry#*:}
        if [ "${#name}" -ge "$min" ] && [[ $opt == "$name"* ]]; then
            matched_option=$opt
            return 0
        fi
    done
    return 1
}

check_segment() {
    local -a tokens=()
    local raw
    for raw in $1; do
        tokens+=("$(clean_token "$raw")")
    done
    [ "${#tokens[@]}" -gt 0 ] || return 0

    case "$(basename "${tokens[0]}")" in
        gh | echo | printf) return 0 ;;
        cd)
            if cur=$(resolve_dir "$cur" "${tokens[1]:-}"); then
                cur_known=1
            else
                cur=$cwd
                cur_known=0
            fi
            return 0
            ;;
    esac

    local i=0 count=${#tokens[@]} dir=$cur known=$cur_known
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
                if dir=$(resolve_dir "$dir" "${tokens[$((i + 1))]:-}"); then
                    known=1
                else
                    dir=$cur
                    known=0
                fi
                i=$((i + 2))
                ;;
            -c | --git-dir | --work-tree | --namespace) i=$((i + 2)) ;;
            -*) i=$((i + 1)) ;;
            *) break ;;
        esac
    done
    [ "${tokens[$i]:-}" = push ] || return 0

    local current=""
    [ "$known" -eq 1 ] && current=$(git -C "$dir" branch --show-current 2>/dev/null || true)

    local -a positional=()
    local token
    for token in "${tokens[@]:$((i + 1))}"; do
        case "$token" in
            --*)
                if dangerous_long_option "$token"; then
                    case "$matched_option" in
                        --all | --mirror) block "'git push $token' would also push main. Push a single branch instead." ;;
                        *) block "forcing a push is not allowed ($token). Push with a plain 'git push -u origin <branch>'." ;;
                    esac
                fi
                ;;
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
        [ "$known" -eq 1 ] || block "cannot tell which branch 'git push' would push (directory not resolvable). Give an explicit 'git push -u origin <branch>'."
        [ "$current" = main ] && block "'git push' while on main is not allowed. Work on a ticket branch."
        return 0
    fi

    local refspec dest
    for refspec in "${refspecs[@]}"; do
        if [[ $refspec = +* ]]; then
            block "forced refspec '$refspec' is not allowed (leading +)."
        fi
        if [[ $refspec = *'*'* ]]; then
            block "pattern refspec '$refspec' could push every branch, main included."
        fi
        dest=${refspec#*:}
        if [ "$dest" = HEAD ]; then
            [ "$known" -eq 1 ] || block "cannot tell which branch HEAD is (directory not resolvable) for '$refspec'."
            dest=$current
        fi
        dest=${dest#refs/heads/}
        dest=${dest#heads/}
        if [ "$dest" = main ]; then
            block "pushing to main is not allowed ('$refspec'). Push the ticket branch and open a PR."
        fi
    done
}

while IFS= read -r segment; do
    check_segment "$segment"
done < <(split_segments "$command")
exit 0
