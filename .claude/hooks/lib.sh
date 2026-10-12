#!/usr/bin/env bash
# Shared helpers for the Claude Code hooks in this directory.

# Prints the reason on stderr and exits 2: Claude Code blocks the tool call and
# feeds stderr back to the model.
block() {
    echo "BLOCKED by Guixa safety hook: $1" >&2
    exit 2
}

# Splits a shell command line into one simple command per line. Quotes are
# kept out on purpose: nested forms such as `bash -c "git push ..."` must be
# inspected too.
split_segments() {
    printf '%s\n' "$1" | sed -E 's/(&&|\|\||;|\||\(|\)|`)/\n/g'
}

# Strips quotes and brace characters from a token.
clean_token() {
    local token=${1//\"/}
    token=${token//\'/}
    printf '%s' "$token"
}
