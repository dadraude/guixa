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
