#!/usr/bin/env bash
# Shared helpers for the Claude Code hooks in this directory.

# Prints the reason on stderr and exits 2: Claude Code blocks the tool call and
# feeds stderr back to the model.
block() {
    echo "BLOCKED by Guixa safety hook: $1" >&2
    exit 2
}

# Joins backslash-newline continuations: the shell reads `git push origin \<nl>main`
# as one command, so the guards must too.
join_continuations() {
    local text=$1 backslash_newline=$'\\\n'
    printf '%s' "${text//"$backslash_newline"/ }"
}

# Splits a shell command line into one simple command per line. Quotes are
# kept out on purpose: nested forms such as `bash -c "git push ..."` must be
# inspected too.
split_segments() {
    { join_continuations "$1"; echo; } | sed -E 's/(&&|\|\||;|\||\(|\)|`)/\n/g'
}

# Strips quotes and brace characters from a token.
clean_token() {
    local token=${1//\"/}
    token=${token//\'/}
    printf '%s' "$token"
}

# Prints the physical absolute path of directory $2 resolved against $1 (a
# relative $2 is relative to $1). Fails when it does not exist or uses shell
# expansions we cannot evaluate.
resolve_dir() {
    local base=$1 rel=${2:-}
    case "$rel" in
        '' | '~') rel=$HOME ;;
        '~/'*) rel=$HOME/${rel#\~/} ;;
    esac
    [[ $rel = /* ]] || rel="$base/$rel"
    [ -d "$rel" ] || return 1
    (cd "$rel" 2>/dev/null && pwd -P)
}

# Prints the nearest existing ancestor directory (physical path) of a path,
# so git can be run from it even when the path itself does not exist yet.
nearest_existing_dir() {
    local dir
    dir=$(dirname "$1")
    while [ ! -d "$dir" ]; do dir=$(dirname "$dir"); done
    (cd "$dir" 2>/dev/null && pwd -P)
}
