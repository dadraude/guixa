#!/usr/bin/env bash
# Tests for the Claude Code hooks in .claude/hooks. Run: bash .claude/hooks/tests/run.sh
# Each case feeds a JSON payload to a hook and checks its exit code (2 = blocked on PreToolUse)
# or its JSON decision (SubagentStop). Everything happens in throwaway git repos under a temp
# directory; no real push, no database, no linters of this repo run.
set -u
hooks=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT
pass=0
fail=0

git_quiet() { git -C "$1" "${@:2}" >/dev/null 2>&1; }

# Repo on main with one tracked migration, plus a feature-branch clone-like repo.
make_repo() {
    local dir=$1 branch=$2
    git init -q -b main "$dir"
    git_quiet "$dir" config user.email t@t.t
    git_quiet "$dir" config user.name t
    mkdir -p "$dir/database/migrations"
    echo '<?php' >"$dir/database/migrations/2026_01_01_000000_create_x.php"
    git_quiet "$dir" add -A
    git_quiet "$dir" commit -m init
    [ "$branch" = main ] || git_quiet "$dir" checkout -b "$branch"
}
make_repo "$tmp/on-main" main
make_repo "$tmp/on-feature" improvement/dra-134-demo
echo '<?php' >"$tmp/on-main/database/migrations/2026_02_02_000000_new.php" # untracked = new

expect() { # name expected-exit hook payload
    local name=$1 want=$2 script=$3 payload=$4 got
    printf '%s' "$payload" | bash "$hooks/$script" >/dev/null 2>&1
    got=$?
    if [ "$got" -eq "$want" ]; then
        pass=$((pass + 1))
    else
        fail=$((fail + 1))
        echo "FAIL: $name (expected $want, got $got)"
    fi
}

bash_payload() { jq -nc --arg c "$1" --arg d "$2" '{tool_name:"Bash",cwd:$d,tool_input:{command:$c}}'; }
file_payload() { jq -nc --arg t "$1" --arg f "$2" --arg d "$3" '{tool_name:$t,cwd:$d,tool_input:{file_path:$f}}'; }

# --- guard-git-push.sh ---
push() { expect "push: $1" "$2" guard-git-push.sh "$(bash_payload "$1" "${3:-$tmp/on-feature}")"; }
push 'git push' 2 "$tmp/on-main"
push 'git push origin' 2 "$tmp/on-main"
push 'git push origin HEAD' 2 "$tmp/on-main"
push 'git push origin main' 2
push 'git push origin HEAD:main' 2
push 'git push origin HEAD:refs/heads/main' 2
push 'git push origin +main' 2
push 'git push origin +improvement/x' 2
push 'git push origin feature:main' 2
push 'git push origin :main' 2
push 'git push --force origin improvement/x' 2
push 'git push -f origin improvement/x' 2
push 'git push -fu origin improvement/x' 2
push 'git push --force-with-lease origin improvement/x' 2
push 'git push --force-with-lease=improvement/x origin improvement/x' 2
push 'git push --all origin' 2
push 'git -C /somewhere push origin main' 2
push 'git add . && git commit -m x && git push origin main' 2
push 'bash -c "git push origin main"' 2
push 'git push -u origin improvement/dra-134-demo' 0
push 'git push origin improvement/dra-134-demo' 0
push 'git push' 0
push 'git push origin HEAD' 0
push 'git push origin HEAD:improvement/dra-134-demo' 0
push 'git worktree add ../w/dra-9 -b improvement/dra-9-x origin/main' 0
push 'git fetch origin main' 0
push 'git pull origin main' 0
push 'git merge origin/main' 0
push 'git commit -m "mention git push origin main in the message"' 0
push 'gh pr create --base main --title x' 0
push 'ls' 0

# --- guard-migrations.sh ---
mig="database/migrations/2026_01_01_000000_create_x.php"
new="database/migrations/2026_02_02_000000_new.php"
mp() { expect "migrations: $1" "$2" guard-migrations.sh "$3"; }
for tool in Edit Write MultiEdit; do
    mp "$tool existing (absolute)" 2 "$(file_payload $tool "$tmp/on-main/$mig" "$tmp/on-main")"
    mp "$tool existing (relative)" 2 "$(file_payload $tool "$mig" "$tmp/on-main")"
    mp "$tool new untracked" 0 "$(file_payload $tool "$tmp/on-main/$new" "$tmp/on-main")"
    mp "$tool not yet created file" 0 "$(file_payload $tool "$tmp/on-main/database/migrations/2099_01_01_000000_future.php" "$tmp/on-main")"
    mp "$tool other file" 0 "$(file_payload $tool "$tmp/on-main/app/Foo.php" "$tmp/on-main")"
done
bm() { mp "bash: $1" "$2" "$(bash_payload "$1" "$tmp/on-main")"; }
bm "sed -i $mig" 2
bm "sed -i '' s/a/b/ $mig" 2
bm "sd a b $mig" 2
bm "rm $mig" 2
bm "rm database/migrations/*" 2
bm "mv $mig /tmp/x.php" 2
bm "echo x > $mig" 2
bm "echo x >> $mig" 2
bm "echo x >$mig" 2
bm "tee $mig" 2
bm "git checkout -- $mig" 2
bm "cd . && sd a b $mig" 2
bm "sd a b $new" 0
bm "echo x > $new" 0
bm "bat $mig" 0
bm "rg create database/migrations" 0
bm "php artisan make:migration create_y_table" 0
bm "php artisan migrate:fresh 2>&1 > /dev/null" 0
bm "sed s/a/b/ $mig" 0

# --- implementer-stop-checks.sh ---
stop_payload() { jq -nc --arg t "$1" --arg d "$2" --argjson a "${3:-false}" '{hook_event_name:"SubagentStop",agent_type:$t,cwd:$d,stop_hook_active:$a}'; }
decision() { printf '%s' "$2" | env -u DB_DATABASE GUIXA_STOP_CHECKS="$1" bash "$hooks/implementer-stop-checks.sh" 2>/dev/null | jq -r '.decision // "allow"' | rg . || echo allow; }
check() { # name expected checks payload
    local got
    got=$(decision "$3" "$4")
    if [ "$got" = "$2" ]; then pass=$((pass + 1)); else fail=$((fail + 1)); echo "FAIL: stop: $1 (expected $2, got $got)"; fi
}

# Feature branch with a change against main.
git_quiet "$tmp/on-feature" update-ref refs/remotes/origin/main main
echo '<?php // change' >"$tmp/on-feature/app.php"
git_quiet "$tmp/on-feature" add -A
git_quiet "$tmp/on-feature" commit -m change
changed=$(stop_payload ticket-implementer "$tmp/on-feature")

check "green checks pass" allow 'true' "$changed"
check "red checks block" block 'true
false' "$changed"
check "lint red blocks" block 'echo lint broken; exit 1' "$changed"
check "stop_hook_active never loops" allow 'false' "$(stop_payload ticket-implementer "$tmp/on-feature" true)"
check "other agents are ignored" allow 'false' "$(stop_payload ticket-reviewer "$tmp/on-feature")"
check "nothing changed is skipped" allow 'false' "$(stop_payload ticket-implementer "$tmp/on-main")"
git_quiet "$tmp/on-main" checkout -b improvement/dra-7-dirty
echo "<?php // dirty" >"$tmp/on-main/database/migrations/2026_01_01_000000_create_x.php"
check "uncommitted tracked change runs checks" block false "$(stop_payload ticket-implementer "$tmp/on-main")"
git_quiet "$tmp/on-main" checkout -- .

# DB_DATABASE: derived from the branch, inherited when set, never guixa/testing, skipped when unknown.
probe='echo "$DB_DATABASE" >"'$tmp'/db"'
printf '%s' "$changed" | env -u DB_DATABASE GUIXA_STOP_CHECKS="$probe" bash "$hooks/implementer-stop-checks.sh" >/dev/null 2>&1
[ "$(cat "$tmp/db")" = testing_dra134 ] && pass=$((pass + 1)) || { fail=$((fail + 1)); echo "FAIL: DB derived from branch"; }
printf '%s' "$changed" | DB_DATABASE=testing_dra999 GUIXA_STOP_CHECKS="$probe" bash "$hooks/implementer-stop-checks.sh" >/dev/null 2>&1
[ "$(cat "$tmp/db")" = testing_dra999 ] && pass=$((pass + 1)) || { fail=$((fail + 1)); echo "FAIL: DB inherited"; }
printf '%s' "$changed" | DB_DATABASE=guixa GUIXA_STOP_CHECKS="$probe" bash "$hooks/implementer-stop-checks.sh" >/dev/null 2>&1
[ "$(cat "$tmp/db")" = testing_dra134 ] && pass=$((pass + 1)) || { fail=$((fail + 1)); echo "FAIL: DB guixa replaced"; }
git_quiet "$tmp/on-feature" checkout -q -b scratch
rm -f "$tmp/db"
printf '%s' "$changed" | env -u DB_DATABASE GUIXA_STOP_CHECKS="$probe" bash "$hooks/implementer-stop-checks.sh" >/dev/null 2>&1
[ ! -e "$tmp/db" ] && pass=$((pass + 1)) || { fail=$((fail + 1)); echo "FAIL: no DB derivable must skip"; }

echo "passed: $pass, failed: $fail"
[ "$fail" -eq 0 ]
