# Host-scoped GitHub delivery

`scripts/scoped_delivery.py` extends the [host Git control](scoped-git.md) with one fixed delivery
operation. It is not an exposed MCP or worker endpoint and is not connected to the batch adapter.
No push, PR creation or permission activation happens on import or merge.

The trusted coordinator constructs `DeliveryApproval` with a worktree, feature branch, reviewed
head/tree, pinned baseline ancestor, destination PR base branch, title and body. The only permitted
repository is dadraude/guixa. `HostDelivery` checks branch/head/tree/ancestry, clean local state,
repository identity, origin and configuration before delivery. The host must bind the approval to
independent reviews and required checks; a worker result cannot supply authorization.

The host supplies GitHub authentication through GH_TOKEN or GITHUB_TOKEN. The control obtains the
token with the pinned gh executable and does not pass it to workers. Push runs from a disposable
bare repository to the literal HTTPS GitHub URL, with one immutable SHA-to-feature-branch refspec.
No force, main push, merge or arbitrary remote/API operation is exposed. Source repository hooks,
helpers and redirecting configuration cannot choose the push command or destination. As with the
commit control, required checks must run independently before delivery because arbitrary local
hooks are disabled for this host operation.

After pushing, the control reuses an exact matching open PR or creates one and checks its returned
identity. A push can succeed before PR creation or verification fails; the error does not mean the
push was rolled back. Reconcile the remote branch/PR before recovery. The host must stop workers
and exclude concurrent writers, including competing delivery, for the whole operation.

A returned PR URL is not a green-CI receipt. Run the existing [GitHub verifier](github-verifier.md)
against the exact head and mandatory contexts, reconcile Linear and retain human merge authority.
Reviews, CI and permission acceptance cannot be inferred from a successful subprocess exit.

Tests use disposable local repositories and a fake GitHub boundary to exercise allowed push argv,
exact PR reuse, head/tree/origin/base scope and failures after push. No production mutation or real
credential is used. DRA-135/DRA-144 remain open for integration, effective native tool/configuration
acceptance and a supervised no-dev-prompt ticket. Automatic merge and unattended schedules remain
gated separately.
