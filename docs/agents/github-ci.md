# Conservative GitHub CI proof

`github_ci.py` is the public proof boundary used by the GitHub verifier. It does not run commands or
read credentials: callers supply current-head check runs/statuses or trusted-base workflow text.
`assert_green` requires operator-reviewed mandatory contexts and rejects a missing configuration,
missing required jobs, pending work, or any unsuccessful current check/status. A lone unrelated green
result cannot establish readiness.

Check identities are configured as `APP_ID/NAME`; status contexts use their literal context string.
The owner must inspect the repository's actual required/applicable jobs and review the complete list,
including independently triggered workflows such as batch-runner CI. Do not discover the required
list from the currently visible checks: that would treat a job that has not appeared as optional.
The caller cannot substitute a favorable subset when required jobs are absent. Repository changes
that add/rename jobs require configuration review. Multiple workflows exposing the same app ID/check name are unsupported: duplicate mandatory
identities are rejected rather than deduplicated. Use distinct job names or a future workflow-aware
identity contract; do not configure one name and assume it proves multiple workflows ran.
The configuration is an explicit operator trust
boundary; this helper cannot establish that the operator selected every applicable job correctly.

All observed check runs must pass. Retry grouping uses **app ID, check-suite ID and check name**,
keeping each independent suite separate; a success from a different suite cannot erase a failure.
Within that same identity only the latest run ID applies. Commit statuses use the latest status ID
per context. Required app/name pairs and status contexts must all be present in that current data.

`prove_skipped` conservatively checks automatic event absence. Its supported workflow syntax is the
repository's plain `on` mapping with quoted PR `paths`/`paths-ignore` lists, literal paths/directory
`/**` and `**/*.md`. Each PR filter must skip all changed and renamed paths. If `push` exists, its
literal branch list must explicitly exclude the actual head branch. Both the multiline branch list
and the repository's single-literal inline list (`branches: [main]`) are supported. Unrestricted push,
a matching push branch, branch globs, unfamiliar events/YAML/globs or extra unsupported options fail
closed. Push path filtering alone cannot justify absence: the PR diff is not proof of the actual
push-event diff. Manual `workflow_dispatch` does not establish an automatic run.

This helper does not prove workflow provenance, API pagination or ticket identity; the delivery
verifier must establish those before calling it. In particular, workflow set/blob equality against
the trusted base prevents an adapter from deleting CI to claim it was skipped. Receipts remain
observations and do not enforce permissions or replace human merge approval.

Run `python3 -m unittest scripts.tests.test_github_ci -v`. The focused tests exercise missing jobs,
separate suites, retry/status semantics and push/PR skip proofs without invoking any external service.
