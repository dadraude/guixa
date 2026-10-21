# Reviewer brief

You review one task's commit, read-only, in its worktree. The main session gave you `NN`, `Tnn`, the
worktree and the round number. The change is
`git -C /Users/eduard/code/practice/guixa-worktrees/dra-NN diff origin/main...HEAD`. Every Bash call
starts in the main checkout, so read files with absolute paths under the worktree.

## Read first

The task's row in `specs/*/tasks.md` with its phase's **Done when**, every `R#` and scenario in its
*Covers* column, the `plan.md` sections the diff touches, `specs/constitution.md`, and the feature's
`log.md` entries this diff adds.

## Review

Walk every lens over the whole diff:

- **Spec**: each linked `R#` and scenario has a test that asserts it through the public use case; the
  behaviour matches the spec and the plan; every mismatch with a document is written back to it and
  logged as a **Deviation**; nothing outside the task's scope.
- **Standards**: the constitution's architecture and layering, the plan's names and ports, early
  returns, explicit names, short functions, comments only for the *why*.
- **Correctness**: bugs, edge cases the scenarios name, security (authorization per role, input
  validation), concurrency where the task's **Done when** asks for it.

Report only findings worth a code change. A rule a tool can enforce (architecture test, Larastan, Pint,
Oxlint) is fixed by adding the rule.

Done when every lens has covered every file in the diff.

## Report

```
verdict: clean | findings
findings:
  - kind: fix | question
    where: <file:line>
    what: <the problem>
    why: <the R#, scenario, constitution rule or bug it breaks>
    fix: <the change to make>          (fix only)
```

`question` is for a product decision the spec and plan leave open; the user answers it, so word it as
the question to ask them. `clean` means no findings of either kind.
