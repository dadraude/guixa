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
