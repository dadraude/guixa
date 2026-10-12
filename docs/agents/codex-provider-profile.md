# Prepare a scoped Codex provider profile

Status: inactive preparation for DRA-135. Generating or merging this artifact does not activate a
profile. DRA-135 and DRA-144 still require supervised delivery acceptance.

`scripts/codex_profile.py` produces a private JSON review artifact containing the exact TOML fragment,
its SHA-256, repository/worktree identity, approved file paths and unresolved activation gates. It
never writes Codex configuration or runs a provider. Review the embedded TOML before using it.

## Generate the review artifact

Use Python 3.11 or later. Pass canonical absolute repository, linked-worktree and output paths, plus
one `--write` per approved file. The output must be new and outside Codex autoload locations.

```sh
python3 scripts/codex_profile.py \
  --repository /Users/eduard/code/practice/guixa \
  --worktree /Users/eduard/code/practice/guixa-worktrees/dra-135-provider-profile \
  --write scripts/codex_profile.py \
  --write scripts/tests/test_codex_profile.py \
  --write docs/agents/codex-provider-profile.md \
  --output /private/tmp/dra135-profile-review.json
```

The fragment grants writes only to explicitly named files under `scripts/` or `docs/`. Directories,
symlinks, traversal, protected paths and their ancestors are rejected. The repository, Git common
directory, migrations, specifications, ADRs, workflow instructions and check configuration stay
read-only. The worktree `.env` is denied, and shell network access is off, including local database
access in the tested configuration. No shared temporary-directory write grant is added.

This initial file-only scope prepares workflow work. It does not provision dependencies, allow
application test databases, allow changes to protected files, or implement a general development
profile. The filesystem remains broadly readable: this is a write boundary, not secret isolation.
Do not describe it as protecting arbitrary host credentials from reads.

## The launch must preserve the boundary

The installed CLI is `0.162.0-alpha.17.2`, at
`/Applications/ChatGPT.app/Contents/Resources/codex-cli/CodexCLI.app/Contents/MacOS/codex`.
Selected roles remain `gpt-6.1-sol` for implementation/orchestration and `gpt-6-astra` for independent
reviews. Reviewer executions need a separate fully read-only profile.

The [native permissions documentation](https://learn.chatgpt.com/docs/permissions) states that
legacy `--sandbox` / `sandbox_mode` settings override native profile selection. The existing batch
adapter always passes `--sandbox` and `sandbox_workspace_write.network_access=false`, so this
artifact cannot be attached to that adapter and treated as effective enforcement. A reviewed
launcher must reject conflicting configuration and verify the effective profile for every child.
Do not remove an existing restriction silently to make a new profile load.

The CLI's `--profile NAME` loads `NAME.config.toml` from its configuration home. This JSON output
is neither that file nor an active project `.codex/config.toml`. No user configuration is changed.

## Service authority is a separate gate

The current user configuration includes enabled MCP servers without tool allowlists. A workspace
shell sandbox does not scope their service operations. An authenticated Linear read does not prove
that other projects or write tools are blocked. The [configuration reference](https://learn.chatgpt.com/docs/config-file/config-reference)
provides server/tool enable controls, but a tool-name list does not constrain its project arguments.

Prepare the following complete boundary before requesting activation:

| Surface | Required authority |
| --- | --- |
| Worker processes | Reviewed effective tools only; no inherited service, browser, computer-use or unrestricted execution MCP. No production credentials injected into worker environment. |
| Linear | Draude workspace `1fc81da1-225b-4c39-baf7-31062f5833d8`, project `de902c96-bf85-45b6-950d-32d3694a232c`; exact ticket/transition scope checked by a trusted coordinator or scoped service. Other projects and session updates denied to children. |
| Git | Common directory remains outside worker writes. A trusted host operation validates the branch, pinned baseline, approved diff and commit before changing Git state. No arbitrary Git argv accepted. |
| GitHub | Repository `dadraude/guixa`; validated feature-branch push and PR creation only. No main push, force push, merge or generic mutation endpoint. Preserve independent exact-head CI verification. |
| Database | No access in this profile. Application acceptance needs separately enforced per-batch database credentials/service access, not only `DB_DATABASE`. |

Codex [tool hooks](https://learn.chatgpt.com/docs/hooks) can inspect supported calls, but require trust
and have coverage exceptions. Do not use a prompt, untrusted hook, or tool-name matcher as the sole
service authorization boundary. This generator does not implement these service controls. Its
`activation_ready: false` is deliberately unconditional.

## Validation and rollback

Run `python3 -m unittest scripts.tests.test_codex_profile -v` for generator rejection cases using
real disposable linked Git worktrees. These tests do not call an AI service.

On 2026-10-13, the installed CLI evaluated the generated fragment in a disposable repository and
linked worktree using `codex sandbox --include-managed-config -P guixa-batch -C <fixture>` with
literal `-c` overrides from the artifact. Fourteen cases matched their expected outcomes: approved
existing/new files writable; unapproved sibling, instructions, CI, migration, Git common-directory
write, main-ref update, shell wrapper, protected rename, `.env` read, TCP loopback and Unix socket
blocked; a writable path replaced with a symlink was rejected at sandbox startup. Protected content
remained unchanged. `--strict-config` is unsupported by this sandbox subcommand; these probes do
not establish strict parsing or the behavior of `codex exec`.

The private receipt is `/private/tmp/dra135-native-profile-receipt.json`; the reproducible local
probe is `/private/tmp/dra135-native-profile-probe.py`. The receipt records CLI/generator/artifact
hashes, fixture paths, case results and denial diagnostics. These temporary files are session
observations, not durable repository acceptance records. No real remote or database was targeted.
The evidence establishes only the tested local command paths; it does not establish MCP, child-launch,
application database, review independence or remote-delivery acceptance.

Rollback before activation is removal of the private JSON artifact. No global or project policy
needs restoring because this command changes neither. Do not activate while the required gates
remain unresolved.
