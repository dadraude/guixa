"""Repair Vite+ aliases on same-repository Dependabot PRs using trusted code only."""

import base64
import json
import os
from pathlib import Path
import re
import subprocess
import tempfile
from urllib.parse import quote
from urllib.request import Request, urlopen


FILES = ("package.json", "pnpm-workspace.yaml", "pnpm-lock.yaml")
VERSION = r"\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?"


def synchronize(package_text, workspace):
    package = json.loads(package_text)
    version = package["devDependencies"]["vite-plus"]
    if not re.fullmatch(VERSION, version):
        raise ValueError("vite-plus must have an exact version")
    alias = "npm:@voidzero-dev/vite-plus-core@" + version
    old = package["devDependencies"]["vite"]
    if not re.fullmatch("npm:@voidzero-dev/vite-plus-core@" + VERSION, old):
        raise ValueError("Unexpected Vite alias")
    for name, value in (("vite", alias), ("vite-plus", version)):
        # Match the existing catalog layout; fail closed if its shape changes.
        workspace, count = re.subn(
            rf"(?m)^(    {name}: )[^\n]+$", lambda m: m[1] + value, workspace
        )
        if count != 1:
            raise ValueError(f"Expected exactly one catalog entry for {name}")
    package["devDependencies"]["vite"] = alias
    if old == alias:
        updated = package_text
    else:
        updated = json.dumps(package, indent=4, ensure_ascii=False) + "\n"
    return updated, workspace, version


def resolve_lockfile(package_text, lockfile, version):
    # Never give pnpm PR-supplied hooks, scripts, plugins, packageManager or config.
    package = json.loads(package_text)
    trusted = json.loads((Path(__file__).resolve().parents[2] / "package.json").read_text())
    clean = {"private": True, "packageManager": trusted["packageManager"]}
    for section in ("dependencies", "devDependencies", "optionalDependencies"):
        clean[section] = package.get(section, {})
        for name, spec in clean[section].items():
            if not re.fullmatch(r"@?[a-z0-9._-]+(?:/[a-z0-9._-]+)?", name):
                raise ValueError("Unexpected dependency name")
            if not isinstance(spec, str) or not re.fullmatch(
                r"(?:npm:@?[a-z0-9._-]+(?:/[a-z0-9._-]+)?@)?[0-9A-Za-z.*^~<>=| +\-]+", spec
            ):
                raise ValueError("Only registry dependency versions are supported")
    with tempfile.TemporaryDirectory() as directory:
        root = Path(directory)
        (root / "package.json").write_text(json.dumps(clean))
        (root / "pnpm-lock.yaml").write_text(lockfile)
        (root / "pnpm-workspace.yaml").write_text(
            "packages:\n  - '.'\nignoreScripts: true\ncatalog:\n"
            f"  vite: npm:@voidzero-dev/vite-plus-core@{version}\n"
            f"  vite-plus: {version}\noverrides:\n  vite@*: 'catalog:'\n"
            "peerDependencyRules:\n  allowAny:\n    - vite\n"
            "  allowedVersions:\n    vite: '*'\n"
        )
        env = {key: value for key, value in os.environ.items()
               if key not in ("GH_TOKEN", "GITHUB_TOKEN", "NODE_AUTH_TOKEN", "NPM_TOKEN")}
        subprocess.run(
            ["pnpm", "install", "--lockfile-only", "--ignore-scripts", "--ignore-pnpmfile"],
            cwd=root, env=env, check=True, timeout=180,
        )
        return (root / "pnpm-lock.yaml").read_text()


class GitHub:
    def __init__(self):
        self.repo = os.environ["GITHUB_REPOSITORY"]
        self.token = os.environ["GH_TOKEN"]

    def api(self, path, data=None, method=None):
        request = Request(
            os.environ["GITHUB_API_URL"] + "/repos/" + self.repo + "/" + path,
            data=None if data is None else json.dumps(data).encode(),
            method=method,
            headers={"Authorization": "Bearer " + self.token,
                     "Accept": "application/vnd.github+json",
                     "Content-Type": "application/json"},
        )
        with urlopen(request, timeout=30) as response:
            body = response.read()
        return json.loads(body) if body else None

    def read(self, path, ref):
        result = self.api(f"contents/{path}?ref={quote(ref, safe='')}")
        if result["type"] != "file" or result["size"] > 5_000_000:
            raise ValueError("Unexpected dependency file")
        return base64.b64decode(result["content"]).decode()


def repair(github, run, resolve=resolve_lockfile):
    if run["event"] != "pull_request" or run["head_repository"]["full_name"] != github.repo:
        return None
    owner = github.repo.split("/")[0]
    prs = github.api("pulls?state=open&head=" + quote(owner + ":" + run["head_branch"], safe=""))
    for candidate in prs:
        pr = github.api(f"pulls/{candidate['number']}")
        sha = pr["head"]["sha"]
        if (pr["user"]["login"] != "dependabot[bot]"
                or pr["head"]["repo"]["full_name"] != github.repo
                or sha != run["head_sha"]):
            continue
        # Only dependency-only PRs are admitted; don't run code from a PR checkout.
        changed = github.api(f"pulls/{pr['number']}/files?per_page=100")
        if pr["changed_files"] > 100 or any(
            item["filename"] not in FILES or item["status"] != "modified" for item in changed
        ):
            continue
        original = {path: github.read(path, sha) for path in FILES}
        package, workspace, version = synchronize(original[FILES[0]], original[FILES[1]])
        if package == original[FILES[0]] and workspace == original[FILES[1]]:
            continue
        updated = {FILES[0]: package, FILES[1]: workspace,
                   FILES[2]: resolve(package, original[FILES[2]], version)}
        parent = github.api("git/commits/" + sha)
        tree = github.api("git/trees", {"base_tree": parent["tree"]["sha"], "tree": [
            {"path": path, "mode": "100644", "type": "blob", "content": content}
            for path, content in updated.items()
        ]})
        commit = github.api("git/commits", {
            "message": f"fix(deps): synchronize Vite+ core aliases with {version}",
            "tree": tree["sha"], "parents": [sha],
        })
        # A normal fast-forward update refuses to overwrite a newer Dependabot push.
        github.api("git/refs/heads/" + quote(pr["head"]["ref"], safe="/"),
                   {"sha": commit["sha"], "force": False}, method="PATCH")
        # GITHUB_TOKEN pushes don't reliably start CI automatically. Dispatch it explicitly.
        github.api("actions/workflows/tests.yml/dispatches", {"ref": pr["head"]["ref"]})
        return commit["sha"]
    return None


if __name__ == "__main__":
    event = json.loads(Path(os.environ["GITHUB_EVENT_PATH"]).read_text())
    result = repair(GitHub(), event["workflow_run"])
    print("Synchronized aliases: " + result if result else "No synchronization needed.")
