"""Prepare an inactive, reviewable native Codex permissions fragment."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import subprocess
import sys
import tomllib
import unicodedata

PROFILE = "guixa-batch"
PROTECTED = (".git", ".codex", ".agents", ".claude", ".github", "specs", "docs/adr",
             "CLAUDE.md", "AGENTS.md", "database/migrations", "composer.json",
             "composer.lock", "package.json", "package-lock.json", "pnpm-lock.yaml",
             "yarn.lock", "bun.lock", "bun.lockb", "pint.json", "phpstan.neon",
             "phpstan.neon.dist", ".oxlintrc.json", ".oxfmtrc.json",
             "eslint.config.js", "eslint.config.ts", ".prettierrc", "tsconfig.json")


def comparison_parts(path):
    """Conservatively match aliases on case-insensitive macOS filesystems."""
    return tuple(unicodedata.normalize("NFD", part).casefold() for part in Path(path).parts)


def git(root, *args):
    return subprocess.check_output(["git", "-C", str(root), *args], text=True,
                                   stderr=subprocess.DEVNULL).strip()


def reject_pattern_syntax(value):
    if any(char in "*?[]{}\\" or unicodedata.category(char) == "Cc" for char in value):
        raise ValueError("paths must not contain glob or control syntax")


def absolute_path(value):
    reject_pattern_syntax(value)
    path = Path(value)
    if not path.is_absolute() or ".." in path.parts or path != path.resolve():
        raise ValueError("paths must be absolute, canonical and free of symlinks")
    return path


def prepare(worktree, repository, writes):
    worktree, repository = absolute_path(worktree), absolute_path(repository)
    if not worktree.is_dir() or not repository.is_dir():
        raise ValueError("worktree and repository must exist")
    if Path(git(worktree, "rev-parse", "--show-toplevel")) != worktree:
        raise ValueError("worktree must be its Git root")
    if Path(git(repository, "rev-parse", "--show-toplevel")) != repository:
        raise ValueError("repository must be its Git root")
    common = Path(git(worktree, "rev-parse", "--path-format=absolute", "--git-common-dir"))
    other_common = Path(git(repository, "rev-parse", "--path-format=absolute", "--git-common-dir"))
    git_dir = Path(git(worktree, "rev-parse", "--absolute-git-dir"))
    if common != other_common or git_dir == common or not (worktree / ".git").is_file():
        raise ValueError("expected a linked worktree of the explicit repository")
    protected_names = {comparison_parts(item)[-1] for item in PROTECTED}
    approved = []
    for value in writes:
        reject_pattern_syntax(value)
        relative = Path(value)
        if not value or relative.is_absolute() or any(part in ("..", ".") for part in value.split("/")):
            raise ValueError("write paths must be canonical relative paths")
        if str(relative) != value or relative.parts[0] not in ("scripts", "docs"):
            raise ValueError("this preparation slice allows only scripts and docs")
        path = absolute_path(str(worktree / relative))
        parts = comparison_parts(relative)
        for protected in PROTECTED:
            target = comparison_parts(protected)
            if parts[:len(target)] == target or target[:len(parts)] == parts:
                raise ValueError("write scope overlaps a protected path")
        if any(part.startswith(".env") or part in protected_names
               for part in parts):
            raise ValueError("write scope contains a protected component")
        if path.is_dir() or (path.exists() and not path.is_file()) or len(relative.parts) < 2:
            raise ValueError("only exact file grants are supported; directory grants are refused")
        approved.append(str(path))
    if not approved:
        raise ValueError("at least one explicit write path is required")

    def quote(value):
        return json.dumps(value, ensure_ascii=False)

    lines = ['approval_policy = "never"',
             'web_search = "disabled"', f'default_permissions = "{PROFILE}"', "",
             f"[permissions.{PROFILE}.filesystem]", '":root" = "read"']
    permissions = {str(worktree): "read", str(repository): "read", str(common): "read"}
    for protected in PROTECTED:
        permissions[str(worktree / protected)] = "read"
    permissions[str(worktree / ".env")] = "deny"
    permissions.update({path: "write" for path in approved})
    lines += [f"{quote(path)} = {quote(mode)}" for path, mode in sorted(permissions.items())]
    lines += ["", f"[permissions.{PROFILE}.network]", "enabled = false", ""]
    fragment = "\n".join(lines)
    tomllib.loads(fragment)
    return {"schema_version": 1, "profile": PROFILE, "activation_ready": False,
            "worktree": str(worktree), "repository": str(repository),
            "git_common_dir": str(common), "approved_writes": sorted(set(approved)),
            "toml": fragment, "sha256": hashlib.sha256(fragment.encode()).hexdigest(),
            "required_gates": ["sealed launch without inherited tools", "host delivery gate",
                               "service scope before activation", "effective native policy acceptance",
                               "launch-time revalidation of exact write paths"],
            "limitations": ["fragment does not disable inherited MCP or other tools",
                            "native permissions cannot compose with --sandbox or sandbox_mode",
                            "review full config and CLI; no inherited legacy sandbox configuration",
                            "artifact is preparation only; no delivery or acceptance proof",
                            "write paths may change type after generation; revalidate before launch"]}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--worktree", required=True)
    parser.add_argument("--repository", required=True)
    parser.add_argument("--write", action="append", default=[])
    parser.add_argument("--output", required=True)
    args = parser.parse_args()
    try:
        output = absolute_path(args.output)
        output_parts = comparison_parts(output)
        if (".codex" in output_parts or output_parts[-1] == "config.toml"
                or output_parts[-1].endswith(".config.toml")):
            raise ValueError("output must be outside configuration autoload locations")
        artifact = prepare(args.worktree, args.repository, args.write)
        content = json.dumps(artifact, indent=2, sort_keys=True) + "\n"
        descriptor = os.open(output, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
        with os.fdopen(descriptor, "w") as stream:
            stream.write(content)
        print(json.dumps({"output": str(output), "sha256": artifact["sha256"], "activation_ready": False}))
        return 0
    except (ValueError, OSError, subprocess.CalledProcessError) as error:
        print(f"codex profile refused: {error}", file=sys.stderr)
        return 2


if __name__ == "__main__":
    sys.exit(main())
