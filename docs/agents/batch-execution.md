# Provider-neutral batch contract

The batch protocol separates a deterministic local runner from an external AI wrapper. No provider,
model or permission mode is selected implicitly. The wrapper must support the four configured model
roles, fresh per-ticket implementers and two independent reviewers. Provider-specific agent files are
workflow guidance; their frontmatter does not override the manifest's model roles.

## Manifest version 1

A manifest has `version: 1`, an explicit Linear `project` ID, absolute `repository` and `worktree_root`
paths, a Git `base` ref, `adapter` and `verifier` executable argv arrays, `models` and `batches`.
The executable at argv[0] must be an absolute executable file. Arguments remain literal: no shell,
interpolation, provider fallback or bypass-permissions option is introduced by the runner.

`models` contains nonempty `orchestrator`, `implementer`, `spec_reviewer` and `correctness_reviewer`
values interpreted by the wrapper. `max_parallel` defaults to 3 (1–16); `timeout_seconds` defaults to
3600 (1–86400). Each batch has a unique lowercase slug `id`, a unique branch identifying its first
ticket (`feature/dra-12-name`), one to four ordered `tickets`, optional batch `depends_on` IDs and
optional boolean `cross_cutting`. Each ticket has a unique `DRA-NN` ID, optional `depends_on` ticket
IDs and optional full ticket text in `context`. Ticket dependencies automatically imply batch
dependencies. Internal dependencies must precede dependents. Both graphs must be acyclic.
A cross-cutting batch is the manifest's only batch. The worktree root is under the user's home,
outside the repository, so future CodeGraph indexes can belong to each checkout separately.

## Result version 1

An adapter result repeats `version`, `run_id`, `project`, `batch_id` and `branch`. Its ordered `tickets`
exactly match the request. Every ticket has one outcome:

- `delivered` with a full commit SHA;
- `product_question` with a concrete `question`, `state: Backlog` and `triage_issue` containing an
  issue `id`, the parent's `project` and `cause: product-question`;
- `blocked` or `failed` with a `reason`.

The batch `status` is `complete` if all tickets delivered, `partial` if some did, `failed` if none did
and any failed, otherwise `blocked`. An undelivered internal dependency prevents its dependent from
being marked delivered. A partial result may deliver independent tickets while questions remain.
Any delivery requires `checks: passed`, `reviews: {spec: approved, correctness: approved}`,
`correction_passes` from 0 to 2, and `delivery` with `pr` (HTTPS URL), `state: open`, `ci: green` or
`not_required`, full `head` SHA and expected `base` branch. No delivery means no `delivery` object.

These fields describe the wrapper's claims. Schema validation does **not** prove reviews, Git safety,
CI success, Linear writes or provider supervision. A verifier and coordinator reconciliation must
establish the external facts. Live provider integration is a separate acceptance gate; fake local
adapters prove the protocol and scheduler only.

Run the contract tests with `python3 -m unittest scripts.tests.test_batch_contract -v`.

## Effective-exposure contract

A launched worker may only see and do what the host declared. The host declares a **contract** and
the adapter reports its **measured** exposure; both are JSON objects with exactly these fields
(`scripts/batch_contract.py`, `verify_exposure`):

- `version`: `1`;
- `tools`: names of every tool the worker can call, including built-in features;
- `mcp_servers`: names of every enabled MCP server;
- `writable_paths`: absolute paths (no `..`) the worker can write; in a contract they are scope roots,
  in a report each must lie inside one of them;
- `network`: boolean; a report may say `true` only if the contract does;
- `inherited_config`: configuration layers the worker inherits (absolute paths).

For list fields the contract is an allowlist: the report must be a subset. A missing, unparseable or
non-object report, an unknown or missing field, a wrong type or any undeclared item is a refusal
(`ContractError`); there are no defaults. An unsupported setup (for example a full desktop
configuration with inherited MCP servers) is refused simply because it exceeds the contract.

An adapter must build its report from the provider's own inventory of the effective configuration
and tools, not by echoing the flags it passed, and must run the verifier before any worker starts.
The report proves configuration inventory, not service-argument scoping.

### First adapter

The Codex adapter
(`codex_worker.exposure_report`) replays the launch overrides against `mcp list --json` and
`features list`; its probes are described in `docs/agents/codex-provider-profile.md`. This defines
the measurement only: nothing here activates a permission, profile or global configuration.
Test with `python3 -m unittest scripts.tests.test_exposure -v`; the fake adapter there has no
provider import.

## Isolated lifecycle and receipts

The runtime checks the entire manifest's Git repository, base ref, branch availability and worktree
paths before launching a wrapper. Each invocation creates a random run ID and a new worktree for each
batch. It never switches the primary checkout, removes a worktree, drops a database or deletes a
branch. The test database is `testing_dra<FIRST>`; adapters inherit `DB_DATABASE` and must carry it
on **every** test command, including reviewer commands and parallel workers. They must provision
tools/dependencies and a safe worktree `.env` before tests; the runner does not copy a user's database
configuration. Every delivered ticket must identify a distinct new commit on the batch branch.

All invocations of this runner share cooperative advisory locks under Git's common directory.
Normal runs hold a shared repository lock; a sweep requires an exclusive lock. Database locks reject
concurrent invocations using the same first ticket. Different checkouts of the same Git repository
therefore share the lock domain. These locks do not control humans or external tools. A provider
wrapper still needs reviewed permissions and equivalent Git/protected-file/check enforcement.
Existing `.claude/settings.json` denies and hooks are preserved; a different provider must establish
its own concrete enforcement before unattended execution. DRA-135 owns that integration gate.

Both executables receive their configured argv followed by `--request <absolute JSON path>` and
`--result <absolute JSON path>`. Output goes to an adjacent log. Results must be regular JSON files
under 1 MiB; nonzero exit, missing/invalid results, timeouts or rejected receipts fail visibly.
Adapters use `kind: execute_batch`; requests include the complete batch, model roles, base, parent
refs, repository and worktree. A fresh adapter process is the batch orchestrator; freshness of its
implementers/reviewers remains a wrapper obligation.

The verifier runs first with `kind: repository_preflight`, `version`, `run_id`, `project`, `repository`
and `cross_cutting`. It returns matching identity plus boolean `active_cross_cutting` and repository-
wide `open_prs` URL list. It must inspect **all** open PRs across all projects, recognize sweep PRs
from coordinator records/labels, and reject an unknown classification. Any open sweep blocks a run;
a new sweep additionally requires no open PRs. A static/copied receipt is not a valid live verifier.

After local branch/clean-tree/commit checks, `kind: verify_delivery` requests carry the run/project/
batch/branch identity, delivered ticket IDs, worktree and claimed delivery. Receipts repeat these
fields with `version: 1` and `ready: true`. The trusted verifier must read GitHub (or the configured
host) and Git to check repository identity, PR openness, exact remote head SHA, expected base, current
CI and ticket coverage. `not_required` is valid only when changed paths demonstrably match CI's ignore
rules after checks have had time to appear. Missing/pending/red checks cannot pass. An exit-zero
verifier that merely echoes a request is suitable only for local protocol tests, never delivery proof.
The runner validates identities/equality, but cannot audit a verifier's implementation or permissions.

`.batch-runs/<run-id>/` preserves manifests, requests, results, logs, receipts and `recovery.json`
with each worktree/branch/database. Keep them private (they may contain ticket content). Cancellation
terminates child process groups and reaps them. Failures and interruption leave recoverable work
intact; the coordinator must inspect Git status, reconcile Linear/PR/CI, and perform authorized cleanup
only after retaining any uncommitted work. Test with
`python3 -m unittest scripts.tests.test_batch_runtime -v` using fake local services and Git fixtures.

## Invocation and scheduling

Run `scripts/run-batches.sh <manifest.json>`; `--validate-only` validates schema and Git paths without
creating state/locks/worktrees or invoking either configured executable. Relative manifest paths keep
the caller's working-directory meaning. Exit 0 means every batch delivered; exit 1 means partial,
blocked or failed outcomes; exit 2 means validation/preflight failure; exit 130 means interruption.
The printed state location and `summary.json` make failures and manual reconciliation discoverable.

Independent batches start up to `max_parallel` at a time. A child starts only after every dependency
has finished and its required tickets delivered. Explicit batch dependencies require all parent
tickets; ticket dependencies require only the named tickets, so delivered independent tickets in a
partial parent may unblock their children. Parent delivery receipts are rechecked immediately before
starting a child. Its PR base is the first parent branch in sorted batch-ID order. The worktree
`start_ref` and additional `parents` merges use verified immutable parent head SHAs; a mutable branch
advance cannot enter the child between verification and creation. Roots start at the manifest base
SHA captured during Git validation. Merge failure preserves that worktree
for reconciliation. No independent sibling is used as a base. PRs remain open for human merge.

Example manifest (replace every path, model ID and ticket context with reviewed configuration):

```json
{
  "version": 1,
  "project": "P-DRA-2",
  "repository": "/home/user/code/guixa",
  "worktree_root": "/home/user/code/guixa-worktrees",
  "base": "main",
  "adapter": ["/home/user/bin/approved-ai-wrapper"],
  "verifier": ["/home/user/bin/approved-delivery-verifier"],
  "models": {
    "orchestrator": "selected-orchestrator-model",
    "implementer": "selected-implementation-model",
    "spec_reviewer": "selected-review-model",
    "correctness_reviewer": "selected-correctness-model"
  },
  "batches": [{
    "id": "related",
    "branch": "feature/dra-12-related",
    "tickets": [{"id": "DRA-12", "context": "Full approved ticket body"},
                {"id": "DRA-13", "depends_on": ["DRA-12"], "context": "Full approved ticket body"}]
  }]
}
```

The runner supplies the protocol/request, not an implementation of every AI service. An owner must
provide a usable adapter and verifier, credentials, equivalent permissions/check enforcement, and
supervise a live run before unattended tickets. The session coordinator reconciles deduplicated
product-question issues and final ticket states against the tracker, not just adapter claims. Neither
headless adapter nor verifier publishes a project status update. New-batch execution does not take
over an existing PR-fix branch; that remains the interactive `ship-ticket` flow.

Run all focused tests with `python3 -m unittest discover -s scripts/tests -v`, and repository hooks
with `bash .claude/hooks/tests/run.sh`. The dedicated `batch-runner` GitHub workflow runs these on
relevant script, hook, contract or workflow changes; existing application CI remains intact.
