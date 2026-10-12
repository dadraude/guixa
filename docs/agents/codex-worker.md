# Opt-in isolated Codex workers

`scripts/codex_worker.py` adds a host-side launch API without changing the existing batch adapter.
Importing it does not launch a model. `prepare_launch` validates a host policy and returns an
inactive plan; it calls only the pinned CLI's feature inventory. `launch` is the separate execution
entry point. Merging this code activates no profile, changes no global configuration and does not
complete DRA-135 or DRA-144.

The trusted coordinator retains a JSON policy outside the repository and worker write grants,
plus its expected SHA-256. It supplies canonical repository/worktree paths, exact `writes`, the
absolute Codex `executable`, explicit `model`, `codex_home` and real `user_home`, `files` fingerprints
from `inventory`, and the CLI's `features` mapping from names to registration status. Retain the
reviewed policy and its digest separately from worker output. Run the host module from code the
worker cannot change. Workers must be stopped before host controls execute, with other writers
excluded throughout each operation.

The optional `activation`, `owner_tool_audit_authorized` and `owner_config_audit_authorized` fields
all default to false. They record explicit host/owner decisions; they are not machine evidence of
effective configuration or tool exposure. Do not set them from an agent's result. This session has
not authorized these gates. The returned `activation_ready` remains false even when flags are
present, so callers cannot mistake the preparation result for acceptance proof.

Preparation reruns the exact-file profile validation, fingerprints the executable and known trust
inputs, checks the registered feature inventory, and rejects configuration drift. Project, parent,
profile and known system configuration layers are unsupported and refused. Unknown user-config
keys, including legacy sandbox settings, are refused. The current full desktop user configuration
is intentionally unsupported; do not delete settings to make this check pass. A future reviewed
configuration must preserve stricter rules and hooks and account for every effective managed layer.
The inventory is not an exhaustive detector for unknown managed configuration layouts.

The argv uses native file permissions, network denial, explicit model, strict config and a fresh
ephemeral process with no daemon. It never adds legacy `--sandbox`, permission bypass, extra argv,
rule-ignore or hook-trust bypass options. Each inherited MCP server is disabled individually.
Supported registered features are disabled except hooks and the local shell execution features;
removed flags are tracked but not emitted. New registrations or changed registration status refuse
launch. The environment contains only the explicit HOME and CODEX_HOME; generated shell commands
inherit no host environment. No token is copied into the worker environment.

For independent reviewers, pass `read_only=True`; every approved file write becomes read-only.
The same profile restrictions and audit gates apply. A timeout terminates the worker process group;
a nonzero exit is an error. A zero exit is transport success, not proof of independent review,
completed checks or delivery. The trusted coordinator must interpret and validate the output.

This is a write boundary, not secret isolation: the filesystem root remains readable, as described
in the [profile guide](codex-provider-profile.md). Path validation cannot prevent concurrent trusted
host changes. Actual native tool exposure and effective configuration still need supervised
acceptance with the selected installed CLI. Do not connect this launcher to unattended batches yet.

Tests use disposable linked worktrees and fake CLI execution to exercise policy/config drift,
protected and substituted paths, tool-feature inventory, environment/argv restrictions, opt-in
gates, read-only scope and launch failures. They do not call a model. Earlier native sandbox probes
in the profile guide establish only their recorded filesystem/network cases, not this full launch.
