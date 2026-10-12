# Read-only GitHub verifier

`scripts/adapters/github_verifier.py` implements the batch verifier contract for **github.com**.
It invokes `gh api` using only explicit `GET`, host `github.com`, literal argv and complete
`--paginate --slurp` page collection. Git commands inspect local objects/state only; it never
fetches, edits branches, pushes, changes permissions, writes issues or merges a PR.

Configure the manifest verifier argv with the absolute Python executable, absolute script path,
`--repository owner/repo` and optionally an absolute `--gh` executable. The runner appends
`--request` and `--result`; the request supplies the project/run and worktree/repository identity.
`origin` must exactly match this owner/repo as HTTPS (with optional `.git`) or
`git@github.com:owner/repo.git`. Enterprise hosts and fork PR delivery are deliberately unsupported.
The caller supplies reviewed credentials with read access. Missing credentials/API objects/local
Git objects or unsupported data fail visibly; no permission setting or authentication is invented.

## Repository preflight

Every open PR, across all projects, needs an operator-reviewed classification pinned to its current
head SHA: repeated `--ordinary-pr NUMBER@SHA` or `--cross-cutting-pr NUMBER@SHA` argv pairs.
There are no file-count/title heuristics or implicit ordinary classifications. Unknown PRs, duplicate
classifications or a changed head fail closed and require a new review. A known sweep produces
`active_cross_cutting: true`, so the runner blocks; a new sweep additionally requires an empty PR list.
These classifications are operator assertions about scope, not automated proof of product semantics.

## Delivery and CI

The verifier checks exact URL/owner/repo/number, open non-draft state, branch, expected PR base and
head SHA against current GitHub data and a clean local branch/HEAD. All paginated PR commits must
exist locally, belong to the claimed head ancestry and match GitHub commit messages. Every delivered
DRA identifier must occur as the conventional commit subject suffix `(DRA-NN)`. Partial/truncated
commit data fails rather than silently accepting weak ticket coverage. This establishes commit
coverage; it does not prove ticket acceptance criteria, reviews or tracker writes.

Delivery requires explicit operator-reviewed mandatory contexts: repeated `--required-check
APP_ID/NAME` and/or `--required-status CONTEXT` argv pairs. Missing configuration or any missing
required job fails, even if unrelated visible checks are green. The owner reviews the complete
applicable job list for this repository/batch, including independently triggered workflows; never
infer requirements from only the checks currently visible. Repository job/filter changes require
configuration review, and a conservative superset may block until an owner supplies the correct
reviewed profile. The verifier cannot prove the operator selected that profile correctly. See
`github-ci.md` for the independent proof boundary.

Current-head check runs (`filter=all`) and commit statuses are fully paginated. Declared check-run
`total_count` values, when present, must equal the complete collected count; a changed/truncated
count fails. Pagination establishes the pages GitHub exposes, not that every expected job has
registered; the mandatory context configuration establishes that independent requirement.
Retry identity is app ID/check-suite ID/check name, so success in one independent suite cannot hide
failure in another. The latest retry within each identity and latest status per context must pass;
pending, failed, neutral or skipped check runs are rejected. Status-only CI is supported. Claims must match observed CI: `green` needs the complete mandatory current contexts.
`not_required` is rejected by this verifier. A final PR/head read rejects
changes observed during verification. Remote state can still change after a receipt; these reads are
observations, not a transaction or an alternative to the human merge gate.

Missing mandatory contexts can never be waived by ignored workflow paths. External statuses need
independent evidence; even required check names have no trusted binding to particular workflow
files in the current protocol. This verifier therefore conservatively rejects `not_required` and
all no-check deliveries, including docs-only PRs whose workflows appear skipped. A future explicit
workflow/context binding can enable a narrowly proved exemption; until then the coordinator must
report the blocked readiness gate rather than drop required contexts or infer success. The
independent `github_ci.py` helper retains conservative push/PR skip proof for such diagnostic work,
but that proof is not delivery authority and cannot waive missing mandatory contexts.

Multiple workflows using the same app ID/check name are unsupported. Duplicate configured names
fail; use distinct job names or a future workflow-aware identity contract instead of assuming one
registered suite proves every same-named workflow ran. The owner-reviewed profile remains an
explicit trust boundary; omission of an applicable workflow from that profile is not justified.

Run `python3 -m unittest scripts.tests.test_github_verifier -v`. Tests use a fake gh executable and
real temporary Git repositories to verify GET-only calls, pagination, pinned classifications,
identity/ticket/CI failures and mandatory-context waiver rejection. They are not a live GitHub or AI-service
validation. This concrete verifier complements the Codex adapter; DRA-135 permission enforcement
and a supervised real ticket batch remain separate unattended-execution acceptance gates.
