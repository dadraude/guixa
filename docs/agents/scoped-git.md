# Host-scoped Git commits

`scripts/scoped_git.py` constructs commits only after the trusted coordinator supplies a
`CommitApproval`. The module is not an agent tool or command listener and does not inspect worker
claims of approval. Load it and its dependencies from trusted host code outside worker writes.
No runtime uses this module automatically yet; merging it does not authorize Git operations.

Construct `HostGit` with explicit linked worktree, repository and absolute trusted Git executable.
Its repository/common-directory checks bind the worktree to that repository. An approval pins the
branch, baseline ancestor, current head, exact relative file names, SHA-256 of each file's bytes,
Git file modes, and commit metadata. Only exact files under scripts/docs are supported. Protected
components, case aliases, symlinks, attributes files, other dirty files and pre-staged changes are
refused. File removal is intentionally unsupported in this first scope.

The host must finish required checks and independent reviews, stop all workers, and exclude other
writers before constructing an approval. Never deserialize an approval directly from worker output.
The byte/mode checks do not establish that a review happened or that a test result is trustworthy.
Keep the checked/reviewed baseline and evidence in host-owned state.

The implementation avoids repository hooks and filters when constructing objects, checks bytes and
modes again before moving the branch, and uses an expected-old-head ref update. It rejects executable
filters and configuration redirection before diff/status inspection. The environment omits inherited
Git overrides, system/global configuration and credentials; commands have bounded timeouts.
Disabling arbitrary hooks is not permission to omit their required checks: the trusted coordinator
must already have performed the equivalent checks independently before using this operation.

The branch update detects a changed head, but the entire filesystem/index operation is not atomic
against other host writers. A failure can leave unreachable objects, or an updated branch before
index refresh succeeds. Inspect Git state before deciding on recovery; do not assume rollback or
retry blindly. Main/force push and PR merge are not operations in this module.

Tests use real disposable linked repositories and verify exact commit contents, dirty/staged state,
protected paths, modes, repository/head/branch scope and hostile filter rejection. They touch no
remote. The [delivery module](scoped-delivery.md) adds a separate exact-head feature delivery API.
DRA-135/DRA-144 still require trusted coordinator integration and supervised live acceptance.
