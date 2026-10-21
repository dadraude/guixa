# Implementer brief

You implement one task of a Guixa feature, test-first, in its own worktree, and end with one commit.
The main session gave you `NN`, `Tnn`, the branch, the worktree (already created) and the test database.

Every Bash call starts in the main checkout with a fresh environment, so every command you run starts
with the **worktree prefix**:

```sh
cd /Users/eduard/code/practice/guixa-worktrees/dra-NN && PATH=/Users/eduard/code/practice/guixa-worktrees/.bin:$PATH <command>
```

The `.bin` shim gives PHP 8.5 (Herd's isolation covers only the main checkout). Read and Edit take
absolute paths under the worktree.

## 1. Set up the worktree

With the worktree prefix:

```sh
cp /Users/eduard/code/practice/guixa/.env .env
sd '^DB_DATABASE=.*' 'DB_DATABASE=guixa_test_draNN' .env
composer install && npm ci && npm run build
```

Every artisan command in the worktree (tests, `migrate`, `tinker`) uses `guixa_test_draNN`, so the
user's `guixa` database stays untouched.

## 2. Read the task

Find `Tnn` in `specs/*/tasks.md`: its row, its phase's **Done when**, and "How to work a task" at the
top. Then read every `R#` and scenario in its *Covers* column (in `spec.md` and the area files), the
`plan.md` sections it touches, and `specs/constitution.md`.

Done when you can name, for each linked `R#` and scenario, the test that will prove it.

## 3. Build it test-first

Use the `tdd` skill: one failing Application test per scenario or rule, through the public use case,
then the code that makes it pass, slice by slice. Every test command carries the task's database,
because `phpunit.xml` would otherwise set `guixa_test`:

```sh
DB_DATABASE=guixa_test_draNN php artisan test --filter=<test>
```

Run filtered tests serially; the first run creates `guixa_test_draNN`.

Code style: early returns, explicit names, short functions, comments only for the *why*.

## 4. Close the task

1. With the worktree prefix, run `composer lint:check`, `composer types:check` and, prefixed with
   `DB_DATABASE=guixa_test_draNN`, `php artisan test --filter=<each test file you wrote>` until all are
   green. The main session's gate runs the full suite afterwards.
2. Run step 3 of the `spec-flow` skill: compare the code with `plan.md` and every linked `R#` and
   scenario; write each design mismatch back to the document it contradicts and log it as a
   **Deviation** in the feature's `log.md`.
3. Commit everything as one commit, one line, no body and no attribution:
   `<type>(<scope>): <description> (DRA-NN)`, `<type>` matching the branch.

Done when the commit holds the code, its tests and any document changes, and step 4.1 is green.

## Stop instead of guessing

Stop and report `stopped` when the work needs a decision only the user can make: the spec is silent or
contradicts itself about behaviour a reader of `spec.md` would notice, or a blocker is missing. Also stop
when step 4.1 stays red after you have diagnosed and fixed what you can. Leave the work uncommitted and
describe exactly what blocks you.

## Fixes

When the main session sends findings, gate failures or a rebase conflict, fix each one (for a conflict:
`git rebase origin/main` and resolve it), fold the fix into the task commit with
`git commit --amend --no-edit` (the branch is not pushed yet), rerun steps 4.1 and 4.2, and report again.

## Report

```
status: committed | stopped
commit: <sha>
covered: <each R# and scenario → its test>
deviations: <each Deviation logged, or none>
checks: green | red (<what fails>)
fixes: <each finding or failure → what you changed>   (fix rounds only)
blocker: <the question or failure, verbatim>          (stopped only)
```
