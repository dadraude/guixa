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

Current-head check runs and commit statuses are fully paginated. The latest run for each app/name
and latest status for each context must be completed/successful; pending, failed, neutral or skipped
check runs are rejected. Status-only CI is supported. Claims must match observed CI: `green` needs
checks/statuses, while `not_required` needs none plus the proof below. A final PR/head read rejects
changes observed during verification. Remote state can still change after a receipt; these reads are
observations, not a transaction or an alternative to the human merge gate.

No-check exceptions require at least 60 seconds since the PR's last update, complete changed-file
pagination (including previous names on renames), and proof against **every** workflow from the
trusted PR base. The complete workflow set and blob IDs must be unchanged at the head; an adapter
cannot delete or modify CI to justify skipping it. The conservative parser supports the repository's
plain two-space `on` mapping, `pull_request` `paths`/`paths-ignore` quoted lists, literal paths,
literal directory `/**` and `**/*.md`. Every workflow must have a supported PR filter that skips
all changed/renamed paths. Missing workflows, unfamiliar YAML/events/globs, or files that should
trigger any workflow reject the exception. The required base/workflow objects must already exist
locally; the verifier does not fetch them or approximate an unsupported filter.

Run `python3 -m unittest scripts.tests.test_github_verifier -v`. Tests use a fake gh executable and
real temporary Git repositories to verify GET-only calls, pagination, pinned classifications,
identity/ticket/CI failures and conservative skip proofs. They are not a live GitHub or AI-service
validation. This concrete verifier complements the Codex adapter; DRA-135 permission enforcement
and a supervised real ticket batch remain separate unattended-execution acceptance gates.
