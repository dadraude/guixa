# Codex batch adapter

`scripts/adapters/codex_adapter.py` is the first concrete AI transport for the
[provider-neutral batch protocol](batch-execution.md). Other providers remain executable adapters;
the runner has no Codex dependency or implicit provider/model fallback.

## Explicit configuration

Select the absolute Python and Codex executables, an existing Codex profile (or explicitly select
existing user configuration), and the sandbox. Example manifest fields, replacing paths/project
with reviewed local configuration:

```json
{
  "adapter": [
    "/absolute/path/to/python3",
    "/absolute/path/to/guixa/scripts/adapters/codex_adapter.py",
    "--codex", "/absolute/path/to/codex",
    "--profile", "batch",
    "--sandbox", "workspace-write"
  ],
  "models": {
    "orchestrator": "gpt-6.1-sol",
    "implementer": "gpt-6.1-sol",
    "spec_reviewer": "gpt-6-astra",
    "correctness_reviewer": "gpt-6-astra"
  }
}
```

`--profile batch` reads the owner-provided profile; this adapter never creates a profile or changes
global settings. Alternatively pass `--use-user-config` to explicitly use the existing user config.
The adapter passes `approval_policy="never"` and `sandbox_workspace_write.network_access=false`
for each invocation. Select only `read-only` or `workspace-write`; no bypass, automatic approval
review, additional writable directories, config/rules suppression, login or auth repair is introduced.
Read-only supports observation and blocked outcomes, rather than delivery requiring code writes.
Restricted shell network and Git's common directory outside a linked worktree may prevent fetch,
commit or push; report the denial rather than retrying with broader permissions.

Existing Codex configuration may enable MCP tools with their own permissions. Shell sandboxing does
not establish tracker/GitHub write authorization or reproduce Claude's hooks. Configure and review
these separately. DRA-135 owns equivalent provider enforcement and supervised live permission
acceptance; this transport is not approval to run unattended tickets.

The implementation uses the installed CLI's `exec`, `--model`, `--sandbox`, `--ephemeral`, `--json`,
`-C`, stdin prompt and `--output-last-message` interfaces. See the official
[non-interactive mode documentation](https://learn.chatgpt.com/docs/non-interactive-mode).

## Invocation and evidence

The runner appends `--request FILE --result FILE` and invokes the adapter inside the requested
worktree. It receives the full protocol and request, including all four model roles. The orchestrator
gets fresh child-exec argv arrays with the selected implementer model and read-only reviewer models.
Its instructions require a fresh implementer per ticket/correction, two independent parallel reviews
on the combined diff, at most two correction passes, exact database isolation, headless questions,
checks, current-head CI and honest delivery receipts. Provider-specific Sonnet/Opus frontmatter is
overridden by the request. Worktrees and session status remain the coordinator's responsibility.

These are trusted orchestration duties expressed in a prompt. This transport starts one fresh Codex
orchestrator process; it does **not** independently spawn/count children or prove model selection,
review independence, correction limits, checks, Linear changes or remote delivery. Child evidence
must be retained in the execution log and checked during supervision. The runner and separate
verifier provide their own Git/receipt checks; none proves every AI workflow claim.

Codex JSONL progress and stderr inherit the runner's adjacent execution log. Logs and requests may
contain private ticket/tool content; the adapter does not print environment values or credentials.
The runner owns timeout/cancellation of the entire process group, including nested child exec runs.
Standalone callers must supply equivalent process supervision.

The final message is staged privately and parsed as bounded regular JSON, then validated using the
shared contract before atomic publication with mode 0600. Nonzero CLI exit, missing/symlinked,
oversized/malformed result, invalid identity, invalid delivery or an existing destination returns
nonzero without publishing a new result. The adapter accepts a structurally valid blocked result;
that is not batch delivery. The runner decides overall run success and verifies external claims.

## Validation and rollback

Run `python3 -m unittest scripts.tests.test_codex_adapter -v`. The fake CLI exercises literal argv,
model/config/sandbox selection, log visibility, valid blocked/delivery claims and rejected outputs;
it makes no AI/network calls. Rollback removes the adapter, its boundary tests and this guide and
selects another manifest adapter, without changing the provider-neutral runner or contract.

The parent session's live CLI probe used Codex `0.162.0-alpha.17.2`, `gpt-6.1-sol` and a read-only
sandbox and returned `CODEX_BATCH_SMOKE_OK`. That proves basic CLI reachability only. This adapter's
complete real ticket workflow, fresh model-role children, permission parity and delivery have not
been live-validated. CLI Linear MCP OAuth initialization currently fails; native app connector access
does not establish CLI credentials. Do not change authentication or claim DRA-135/DRA-144 live
acceptance from fake tests or the CLI probe.
