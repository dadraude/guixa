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

expect_msg() { # name expected-substring hook payload (must exit 2 and print the substring on stderr)
    local name=$1 want=$2 script=$3 payload=$4 err code
    err=$(printf '%s' "$payload" | bash "$hooks/$script" 2>&1 >/dev/null)
    code=$?
    if [ "$code" -eq 2 ] && [[ $err = *"$want"* ]]; then
        pass=$((pass + 1))
    else
        fail=$((fail + 1))
        echo "FAIL: $name (exit $code, stderr: $err)"
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

# F3: the branch follows `cd <dir>`; cwd is the main checkout while the work happens in a worktree.
push "cd $tmp/on-feature && git push" 0 "$tmp/on-main"
push "cd $tmp/on-feature && git push origin HEAD" 0 "$tmp/on-main"
push "cd $tmp/on-main && git push" 2 "$tmp/on-feature"
push "cd $tmp/on-main && git push origin HEAD" 2 "$tmp/on-feature"
push "cd $tmp/does-not-exist && git push" 2 "$tmp/on-feature"
push "cd $tmp/does-not-exist && git push -u origin improvement/x" 0 "$tmp/on-feature"
push "git -C $tmp/on-feature push" 0 "$tmp/on-main"
# F4: line continuation
push $'git push origin \\\nmain' 2
push $'git push \\\n--force origin improvement/x' 2
push $'git push -u origin \\\nimprovement/dra-134-demo' 0
# F5: abbreviated long options
push 'git push --forc origin improvement/x' 2
push 'git push --force-w origin improvement/x' 2
push 'git push --force-with origin improvement/x' 2
push 'git push --force-with-lease origin improvement/x' 2
push 'git push --force-i origin improvement/x' 2
push 'git push --force-if-includes origin improvement/x' 2
push 'git push --mirror origin' 2
push 'git push --mirr origin' 2
push 'git push --mi origin' 2
push 'git push --al origin' 2
# F6: non-canonical destinations and patterns
push 'git push origin HEAD:heads/main' 2
push 'git push origin improvement/x:heads/main' 2
push 'git push origin refs/heads/*:refs/heads/*' 2
push "git push origin 'refs/heads/*:refs/heads/*'" 2
push 'git push origin improvement/*' 2
# F7: quoted text in gh/echo/printf segments
push "gh pr create --body 'never run git push origin main'" 0
push 'echo "git push origin main"' 0
push "printf 'git push origin main'" 0
push 'sh -c "git push origin main"' 2
push 'env git push origin main' 2
push 'gh pr create --title x && git push origin main' 2
push 'git push -u origin maintenance/dra-9-cleanup' 0
# F11: a block explains itself on stderr
expect_msg "push: reason on stderr" 'pushing to main is not allowed' guard-git-push.sh "$(bash_payload 'git push origin main' "$tmp/on-feature")"
expect_msg "push: force reason on stderr" 'forcing a push is not allowed' guard-git-push.sh "$(bash_payload 'git push --force origin x' "$tmp/on-feature")"
expect_msg "push: mirror reason on stderr" 'also push main' guard-git-push.sh "$(bash_payload 'git push --mirror' "$tmp/on-feature")"

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
bm "cd . && sd a b $mig" 2
bm "sd a b $new" 0
bm "echo x > $new" 0
bm "bat $mig" 0
bm "rg create database/migrations" 0
bm "php artisan make:migration create_y_table" 0
bm "php artisan migrate:fresh 2>&1 > /dev/null" 0
bm "sed s/a/b/ $mig" 0

# F2: "tracked" means present at the merge-base with origin/main.
make_repo "$tmp/stacked" main
git_quiet "$tmp/stacked" update-ref refs/remotes/origin/main main
git_quiet "$tmp/stacked" checkout -b improvement/dra-8-own
echo '<?php' >"$tmp/stacked/database/migrations/2026_03_03_000000_own.php"
git_quiet "$tmp/stacked" add -A
git_quiet "$tmp/stacked" commit -m own
own="database/migrations/2026_03_03_000000_own.php"
sp() { mp "stacked: $1" "$2" "$3"; }
sp "Edit migration added on the branch" 0 "$(file_payload Edit "$tmp/stacked/$own" "$tmp/stacked")"
sp "Write migration added on the branch" 0 "$(file_payload Write "$own" "$tmp/stacked")"
sp "Edit migration that exists on main" 2 "$(file_payload Edit "$tmp/stacked/$mig" "$tmp/stacked")"
sp "bash sd on branch-only migration" 0 "$(bash_payload "sd a b $own" "$tmp/stacked")"
sp "bash sd on main migration" 2 "$(bash_payload "sd a b $mig" "$tmp/stacked")"
sp "bash rm main migration even if deleted from index" 2 "$(git_quiet "$tmp/stacked" rm -q --cached "$mig"; bash_payload "rm $mig" "$tmp/stacked")"
git_quiet "$tmp/stacked" reset -q -- "$mig"
# F2b: absolute paths into another repo, and cd segments
bm_other() { mp "other repo: $1" "$2" "$(bash_payload "$1" "$tmp/on-feature")"; }
bm_other "rm $tmp/on-main/$mig" 2
bm_other "sd a b $tmp/stacked/$mig" 2
bm_other "sd a b $tmp/stacked/$own" 0
bm_other "cd $tmp/on-main && rm $mig" 2
bm_other "cd $tmp/on-main/database/migrations && rm 2026_01_01_000000_create_x.php" 2
bm_other "cd $tmp/on-main && rm $new" 0
bm_other "rm $tmp/on-main/$new" 0
# F4: line continuation
bm $'rm \\\ndatabase/migrations/2026_01_01_000000_create_x.php' 2
bm $'rm \\\n'"$new" 0
# F8: find, wrappers, destinations, git checkout/restore
bm "find database/migrations -name '*.php' -delete" 2
bm "find database/migrations -exec rm {} ;" 2
bm "find database/migrations -exec sed -i s/a/b/ {} ;" 2
bm "find database/migrations -name '*.php'" 0
bm "find database/migrations -exec cat {} ;" 0
bm "xargs rm $mig" 2
bm "xargs -n1 rm $mig" 2
bm "env FOO=1 rm $mig" 2
bm "sudo rm $mig" 2
bm "env sd a b $mig" 2
bm "cp $mig database/migrations/2026_04_04_000000_copy.php" 0
bm "cp $mig $new" 0
bm "cp -r $mig database/migrations/2026_04_04_000000_copy.php" 0
bm "cp /tmp/x.php $mig" 2
bm "cp /tmp/x.php database/migrations/" 0
bm "install -m 644 /tmp/x.php $mig" 2
bm "mv $new database/migrations/2026_05_05_000000_renamed.php" 0
bm "git checkout -- $mig" 0
bm "git restore $mig" 0
bm "git rm $mig" 2
# F11: a block explains itself on stderr
expect_msg "migrations: reason on stderr" 'make:migration' guard-migrations.sh "$(bash_payload "rm $mig" "$tmp/on-main")"
expect_msg "migrations: Edit reason on stderr" 'already on main' guard-migrations.sh "$(file_payload Edit "$mig" "$tmp/on-main")"

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

# F1: payload cwd is the clean main checkout; the worktree comes from the agent transcript.
make_repo "$tmp/checkout" main
git_quiet "$tmp/checkout" update-ref refs/remotes/origin/main main
mkdir -p "$tmp/guixa-worktrees"
git_quiet "$tmp/checkout" worktree add "$tmp/guixa-worktrees/dra-55" -b improvement/dra-55-x
echo '<?php // change' >"$tmp/guixa-worktrees/dra-55/app.php"
git_quiet "$tmp/guixa-worktrees/dra-55" add -A
git_quiet "$tmp/guixa-worktrees/dra-55" commit -m change
jq -nc --arg p "Implement DRA-55 in the worktree $tmp/guixa-worktrees/dra-55 on its branch." '{type:"user",message:{role:"user",content:$p}}' >"$tmp/transcript.jsonl"
jq -nc '{type:"user",message:{role:"user",content:"no path here"}}' >"$tmp/transcript-none.jsonl"
jq -nc --arg p "Worktree ../guixa-worktrees/dra-55 please." '{message:{content:$p}}' >"$tmp/transcript-relative.jsonl"
via_transcript() { jq -c --arg t "$1" '. + {agent_transcript_path: $t}' <<<"$(stop_payload ticket-implementer "$tmp/checkout")"; }
check "worktree from transcript: red blocks" block 'false' "$(via_transcript "$tmp/transcript.jsonl")"
check "worktree from transcript: green passes" allow 'true' "$(via_transcript "$tmp/transcript.jsonl")"
check "relative worktree mention resolves next to the checkout" block 'false' "$(via_transcript "$tmp/transcript-relative.jsonl")"
check "transcript_path is the fallback key" block 'false' "$(jq -c '. + {transcript_path: "'"$tmp/transcript.jsonl"'"}' <<<"$(stop_payload ticket-implementer "$tmp/checkout")")"
check "no worktree anywhere: skipped, not blocked" allow 'false' "$(via_transcript "$tmp/transcript-none.jsonl")"
printf '%s' "$(via_transcript "$tmp/transcript.jsonl")" | env -u DB_DATABASE GUIXA_STOP_CHECKS="$probe" bash "$hooks/implementer-stop-checks.sh" >/dev/null 2>&1
[ "$(cat "$tmp/db")" = testing_dra55 ] && pass=$((pass + 1)) || { fail=$((fail + 1)); echo "FAIL: DB derived from transcript worktree"; }
err=$(printf '%s' "$(via_transcript "$tmp/transcript-none.jsonl")" | env -u DB_DATABASE GUIXA_STOP_CHECKS=false bash "$hooks/implementer-stop-checks.sh" 2>&1 >/dev/null)
[[ $err = *'checks skipped'* ]] && pass=$((pass + 1)) || { fail=$((fail + 1)); echo "FAIL: undeterminable worktree must say so on stderr ($err)"; }
reason=$(printf '%s' "$(via_transcript "$tmp/transcript.jsonl")" | env -u DB_DATABASE GUIXA_STOP_CHECKS='echo boom; exit 1' bash "$hooks/implementer-stop-checks.sh" 2>/dev/null | jq -r '.reason')
[[ $reason = *boom* && $reason = *'do not guess'* && $reason = *dra-55* ]] && pass=$((pass + 1)) || { fail=$((fail + 1)); echo "FAIL: block reason content ($reason)"; }

# Second correction pass: squash-merged parent migration, background &, xargs, write forms, `@`, DB_DATABASE.
make_repo "$tmp/sq" main
git_quiet "$tmp/sq" checkout -b improvement/dra-20-parent
echo '<?php' >"$tmp/sq/database/migrations/2026_06_06_000000_parent.php"
git_quiet "$tmp/sq" add -A
git_quiet "$tmp/sq" commit -m parent
git_quiet "$tmp/sq" checkout -b improvement/dra-21-stacked
echo '<?php' >"$tmp/sq/database/migrations/2026_07_07_000000_child.php"
git_quiet "$tmp/sq" add -A
git_quiet "$tmp/sq" commit -m child
git_quiet "$tmp/sq" update-ref refs/remotes/origin/main main
git_quiet "$tmp/sq" checkout -q main
git_quiet "$tmp/sq" checkout improvement/dra-20-parent -- database/migrations/2026_06_06_000000_parent.php
git_quiet "$tmp/sq" commit -m "squash of parent"
git_quiet "$tmp/sq" update-ref refs/remotes/origin/main main
git_quiet "$tmp/sq" checkout -q improvement/dra-21-stacked
parent="database/migrations/2026_06_06_000000_parent.php"
child="database/migrations/2026_07_07_000000_child.php"
sp "Edit parent migration squashed into origin/main" 2 "$(file_payload Edit "$parent" "$tmp/sq")"
sp2() { mp "squash: $1" "$2" "$(bash_payload "$1" "$tmp/sq")"; }
sp2 "sd a b $parent" 2
sp2 "rm database/migrations/*parent*" 2
sp2 "sd a b $child" 0
mp "squash: Edit branch-only migration" 0 "$(file_payload Edit "$child" "$tmp/sq")"

# A lone & runs the next command in the background; it must be inspected too.
push 'git status & git push origin main' 2
push 'git fetch origin & git push' 2 "$tmp/checkout"
push 'git push -u origin improvement/dra-134-demo 2>&1 | tail' 0
push 'git push origin improvement/dra-134-demo &> /dev/null' 0
push 'git fetch origin >& /dev/null' 0
bm "sleep 0 & rm $mig" 2
bm "php artisan migrate 2>&1 | tail" 0
# xargs reads its paths from an earlier pipeline segment.
bm "rg -l a database/migrations | xargs sed -i '' s/a/b/" 2
bm "fd . database/migrations | xargs rm" 2
bm "rg -l foo database/migrations | xargs bat" 0
# Other write forms.
bm "sed --in-place s/a/b/ $mig" 2
bm "echo x 1> $mig" 2
bm "echo x 2> $mig" 2
bm "echo x 1>> $mig" 2
bm "echo x 2>$mig" 2
bm "echo x &> $mig" 2
bm "echo x &>>$mig" 2
bm "echo x > /dev/null 2>&1" 0
# `@` is an alias of HEAD.
push 'git push origin @' 2 "$tmp/checkout"
push 'git push origin @' 0
push 'git push origin @:main' 2
# An inherited DB_DATABASE of another ticket never wins over the transcript worktree.
printf '%s' "$(via_transcript "$tmp/transcript.jsonl")" | DB_DATABASE=testing_dra133 GUIXA_STOP_CHECKS="$probe" bash "$hooks/implementer-stop-checks.sh" >/dev/null 2>&1
[ "$(cat "$tmp/db")" = testing_dra55 ] && pass=$((pass + 1)) || { fail=$((fail + 1)); echo "FAIL: foreign DB_DATABASE must not win over the worktree ($(cat "$tmp/db"))"; }
printf '%s' "$(via_transcript "$tmp/transcript.jsonl")" | DB_DATABASE=testing_dra55_2 GUIXA_STOP_CHECKS="$probe" bash "$hooks/implementer-stop-checks.sh" >/dev/null 2>&1
[ "$(cat "$tmp/db")" = testing_dra55_2 ] && pass=$((pass + 1)) || { fail=$((fail + 1)); echo "FAIL: suffixed DB of the same ticket is kept ($(cat "$tmp/db"))"; }

echo "passed: $pass, failed: $fail"
[ "$fail" -eq 0 ]
