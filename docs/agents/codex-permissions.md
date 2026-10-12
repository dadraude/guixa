# Review the Codex development permission proposal

**Status: proposal, inactive.** DRA-135's first Codex policy is
[`scripts/policies/codex-dev.rules`](../../scripts/policies/codex-dev.rules). It grants a small set
of read prefixes if a command requests execution outside the sandbox. Owner approval is required
before activation. It does not complete Codex permission parity or authorize unattended delivery.
No active `.codex/` rule, global configuration, Claude setting or service permission was changed.

## Proposed owner decision

Review the rule file first, then approve or amend these boundaries:

| Activity | Proposed boundary |
| --- | --- |
| Repository status/root | Allow trusted `git --no-optional-locks status` and `git --no-optional-locks rev-parse --show-toplevel` prefixes outside the sandbox. |
| GitHub evidence | Allow only `gh pr view/list/checks` and `gh run view/list` prefixes through the reviewed account; they read PR/CI evidence and may write local CLI caches. |
| Application development | Run tests, linters and dependency tools inside the workspace sandbox with an isolated test database. No outside-sandbox PHP, Python, shell, Composer or npm allow prefix. |
| Generic service API/SQL | Prompt for direct `gh api` and `psql`; no unrestricted API or database allow. |
| Delivery/production | Forbid direct outside-sandbox Git push, PR merge and Supabase prefixes in this proposal. This includes normal feature pushes; delivery needs a separate reviewed gate. |
| Unmatched commands | No permission granted by this proposal. Existing sandbox, approval policy and other loaded rules determine what happens. |

A prefix matches additional trailing arguments. Broad `git diff` or `git log` allows are deliberately
absent because `--output` can write a file; an `rg` allow can admit `--pre` executables. Review
additional flags and any wrapper before expanding this list. Only the documented Git invocations
and basename `gh` are covered; pin/audit the actual executables, PATH, Git config, aliases and hooks
in the provider profile. Status disables optional index writes, but trusted Git configuration remains
necessary. The examples are not approval to read arbitrary private repositories or accounts.

Rules evaluate command prefixes and choose the strongest matching decision. Project rules load from
a trusted project `.codex/rules/` layer. This proposal lives elsewhere and is evaluated explicitly;
it is not loaded by normal Codex runs. See the official
[Codex rules documentation](https://learn.chatgpt.com/docs/agent-configuration/rules).

## Provider profile to review before activation

Use an explicitly selected Codex executable/profile with `workspace-write`, restricted network and
no bypass flags. Keep normal development inside the worktree; any additional writable Git common
directory or isolated database access must be reviewed explicitly. Review merged migrations, CI,
linter settings and protected-file enforcement as separate controls. These prefix rules do not
reproduce the project's Claude hooks or protect every write inside the workspace.

The current Codex batch adapter fixes `approval_policy="never"` and shell network access off.
A command needing denied network, an additional writable root or approval remains blocked; this
proposal does not change those settings. `prompt` rules are not an unattended delivery mechanism.
`auto_review`/`--approve-for-me` can review eligible escalations, but enabling it would be a separate
owner decision. This proposal does not activate it. See the official
[Codex approval and sandbox guidance](https://learn.chatgpt.com/docs/agent-approvals-security).

Before activation, record the exact selected provider/model roles, CLI version, executable paths,
worktree/Git paths, account/workspace/project IDs, local database names and network policy. For the
current Codex setup use the owner's chosen `gpt-6.1-sol` implementation/orchestration and
`gpt-6-astra` reviewers. Providers remain replaceable executable adapters; these rules are not a
permission implementation for another provider.

### MCP and service scope

Command rules cannot constrain MCP tools, connector requests, arbitrary network destinations or
all command spellings. Review a separate curated MCP/provider profile with these proposed scopes:

| Service/tool family | Proposed scope |
| --- | --- |
| Linear reads | Exact tools such as `list_teams`, `get_issue`, `get_project`, `list_issues` and `list_projects`; Draude workspace and selected project only, bounded queries. Verify the actual CLI server's tool identities. |
| Linear writes | Separate scoped gate for ticket states, deduplicated follow-up/triage issues and relations in the parent's project. No blanket write-tool grant; the interactive coordinator alone posts the session status. |
| GitHub reads | Selected repository PR/head/base/current-head CI evidence; no generic mutation API allow. |
| GitHub delivery | Separately authorize only validated feature-branch push and PR creation after checks/reviews; no main push, force-push or automatic merge. This inactive proposal supplies no such grant. |
| Database/services | Local isolated `testing_draNN` database and required suffixes only. No production database, linked/remote Supabase or deployment grant. |

Tool names above are review targets, not a loaded MCP allowlist. Enforce tool identity and argument
scope through the actual provider's available controls or a scoped adapter, then test them. If those
controls cannot enforce project/database boundaries, retain an approval gate rather than describing
them as configured. Authentication alone establishes access; it does not establish permission scope.

`git -C <path> push`, different executable paths, wrapper scripts, Git aliases and service APIs can
escape the shown direct-prefix matches. The validation below deliberately records unmatched variants.
Unmatched means no grant from this file, not a universal denial. Production restrictions must also
be enforced at the provider/tool/network/credential boundary and tested inside the sandbox and via MCP.
Never use these direct-prefix denials as proof that production operations are impossible.

## Read-only policy validation

Codex `0.162.0-alpha.17.2` accepted every inline `match`/`not_match` example when loading the file.
The actual CLI evaluator checked 29 representative argv arrays: 8 allowed read prefixes,
10 forbidden direct write prefixes, 2 prompted API/SQL prefixes and 9 unmatched cases.
No checked command was executed, no AI call was made and no service was contacted.
The local JSON receipt is mode 0600 at `/private/tmp/dra135-policy-checks-36ctc7e7.json`; it is
session evidence, not a committed or durable acceptance receipt.

Reproduce a single decision with the installed CLI, substituting its absolute path:

```sh
/absolute/path/to/codex execpolicy check --rules scripts/policies/codex-dev.rules git --no-optional-locks status --short
/absolute/path/to/codex execpolicy check --rules scripts/policies/codex-dev.rules git push origin main
/absolute/path/to/codex execpolicy check --rules scripts/policies/codex-dev.rules gh pr merge 1 --merge
```

These calls parse and evaluate argv; they do not execute `status`, `push` or `merge`.
The first matched `allow`; the other two matched `forbidden`. Additional checks covered feature and
force pushes, Supabase remote writes, generic POST, SQL, interpreters, log/diff output flags and
alternative argument layouts. No-match results can omit the `decision` field entirely.

## Activation and acceptance gate

After the owner approves the exact proposal and scoped provider profile, activate only the reviewed
rules at the trusted project layer, preserve existing stricter rules and verify the effective policy
with the same CLI build. Do not copy it to global settings or activate it while awaiting approval.
Retain the approved rules/profile identity and acceptance evidence for rollback and review.

Then run one supervised real `/ship-ticket` through the selected adapter with the chosen model
roles, fresh implementer, two independent read-only reviewers, bounded corrections, isolated checks,
tracker reconciliation, feature-branch delivery and current-head CI. Capture actual prompts/denials
and their causes, plus negative enforcement tests for main/force pushes, PR merge, production SQL,
remote Supabase, protected files, weakened checks, wrappers and equivalent MCP writes. Use disposable
fixtures and non-production targets; do not test denials by attempting production mutations.

The existing basic Codex smoke and successful Draude tracker read demonstrate connectivity only.
No complete real ticket, scoped MCP enforcement, production negative test or activated permission
profile has been established by this proposal. DRA-135 remains open until owner review and that live
acceptance pass. Rollback for this inactive unit simply removes these two proposal files; activation
would need its own recorded rollback for the approved project configuration.
