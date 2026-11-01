"""Regression tests for Vite+ synchronization and privileged workflow boundaries."""

import json
from pathlib import Path
import unittest
from unittest.mock import Mock, patch

from sync_vite_plus import FILES, repair, resolve_lockfile, synchronize


ROOT = Path(__file__).resolve().parents[2]
WORKSPACE = (ROOT / "pnpm-workspace.yaml").read_text()
PACKAGE = json.loads((ROOT / "package.json").read_text())


def fixture(version="1.4.0", core="1.3.0"):
    package = json.loads(json.dumps(PACKAGE))
    package["devDependencies"]["vite-plus"] = version
    package["devDependencies"]["vite"] = "npm:@voidzero-dev/vite-plus-core@" + core
    # Preserve current config while making the test independent of main's versions.
    import re
    workspace = re.sub(r"(?m)^    vite: .*$", "    vite: npm:@voidzero-dev/vite-plus-core@" + core, WORKSPACE)
    workspace = re.sub(r"(?m)^    vite-plus: .*$", "    vite-plus: " + version, workspace)
    return json.dumps(package, indent=4) + "\n", workspace


class SyncTest(unittest.TestCase):
    def test_updates_both_aliases_and_preserves_other_settings(self):
        package, workspace = fixture()
        updated, catalog, version = synchronize(package, workspace)
        self.assertEqual(version, "1.4.0")
        expected = json.loads(package)
        expected["devDependencies"]["vite"] = "npm:@voidzero-dev/vite-plus-core@1.4.0"
        self.assertEqual(json.loads(updated), expected)
        self.assertEqual(catalog, workspace.replace("core@1.3.0", "core@1.4.0"))
        self.assertEqual(synchronize(updated, catalog), (updated, catalog, version))

    def test_repairs_catalog_even_when_package_is_aligned(self):
        package, _ = fixture(core="1.4.0")
        _, workspace = fixture()
        updated, catalog, _ = synchronize(package, workspace)
        self.assertEqual(updated, package)
        self.assertIn("core@1.4.0", catalog)

    def test_rejects_unexpected_versions_or_catalog_layout(self):
        for version in ("latest", "^1.4.0", "file:../untrusted"):
            package, workspace = fixture(version=version)
            with self.assertRaises(ValueError):
                synchronize(package, workspace)
        package, workspace = fixture()
        with self.assertRaises(ValueError):
            synchronize(package, workspace.replace("    vite:", "    renamed:"))


class LockfileTest(unittest.TestCase):
    def test_pnpm_receives_only_registry_dependencies_and_trusted_config(self):
        package, _ = fixture()
        data = json.loads(package)
        data.update({"scripts": {"preinstall": "exit 1"},
                     "packageManager": "pnpm@0.0.1", "pnpm": {"hooks": "untrusted"}})
        def install(command, *, cwd, env, **kwargs):
            self.assertIn("--ignore-scripts", command)
            self.assertIn("--ignore-pnpmfile", command)
            self.assertNotIn("GH_TOKEN", env)
            clean = json.loads((cwd / "package.json").read_text())
            self.assertNotIn("scripts", clean)
            self.assertNotIn("pnpm", clean)
            self.assertEqual(clean["packageManager"], PACKAGE["packageManager"])
            self.assertEqual(clean["dependencies"], data["dependencies"])
            (cwd / "pnpm-lock.yaml").write_text("resolved")
        with patch.dict("os.environ", {"GH_TOKEN": "secret"}):
            with patch("sync_vite_plus.subprocess.run", side_effect=install):
                self.assertEqual(resolve_lockfile(json.dumps(data), "old", "1.4.0"), "resolved")

    def test_non_registry_dependencies_fail_before_pnpm_runs(self):
        package, _ = fixture()
        data = json.loads(package)
        data["dependencies"]["malicious"] = "git+https://example.com/code"
        with patch("sync_vite_plus.subprocess.run") as install:
            with self.assertRaisesRegex(ValueError, "registry"):
                resolve_lockfile(json.dumps(data), "old", "1.4.0")
            install.assert_not_called()


class RepairTest(unittest.TestCase):
    def setUp(self):
        self.github = Mock(repo="owner/repo")
        self.pr = {"number": 266, "user": {"login": "dependabot[bot]"},
                   "changed_files": 3,
                   "head": {"sha": "head", "ref": "dependabot/npm-group",
                            "repo": {"full_name": "owner/repo"}}}
        self.run = {"event": "pull_request", "head_sha": "head",
                    "head_branch": "dependabot/npm-group",
                    "head_repository": {"full_name": "owner/repo"}}
        package, workspace = fixture()
        self.github.read.side_effect = [package, workspace, "old lock"]
        self.changed = [{"filename": name, "status": "modified"} for name in FILES]
        self.github.api.side_effect = self.api
        self.resolve = Mock(return_value="new lock")

    def api(self, path, data=None, method=None):
        if path.startswith("pulls?state="):
            return [{"number": 266}]
        if path == "pulls/266":
            return self.pr
        if path.startswith("pulls/266/files?"):
            return self.changed
        if path == "git/commits/head":
            return {"tree": {"sha": "parent tree"}}
        if path == "git/trees":
            self.assertEqual(data["base_tree"], "parent tree")
            self.assertEqual([entry["path"] for entry in data["tree"]], list(FILES))
            return {"sha": "new tree"}
        if path == "git/commits":
            self.assertEqual(data["parents"], ["head"])
            return {"sha": "fixed"}
        return None

    def test_atomic_commit_fast_forward_and_explicit_ci_dispatch(self):
        self.assertEqual(repair(self.github, self.run, self.resolve), "fixed")
        self.resolve.assert_called_once()
        calls = self.github.api.call_args_list
        self.assertEqual(calls[-2].args, ("git/refs/heads/dependabot/npm-group", {"sha": "fixed", "force": False}))
        self.assertEqual(calls[-2].kwargs, {"method": "PATCH"})
        self.assertEqual(calls[-1].args, ("actions/workflows/tests.yml/dispatches", {"ref": "dependabot/npm-group"}))

    def test_skips_non_pr_events_and_forks(self):
        for field, value in (("event", "workflow_dispatch"),
                             ("head_repository", {"full_name": "fork/repo"})):
            run = dict(self.run, **{field: value})
            self.assertIsNone(repair(self.github, run, self.resolve))
        self.github.api.assert_not_called()

    def test_skips_human_prs_stale_runs_and_changed_code(self):
        self.pr["user"]["login"] = "human"
        self.assertIsNone(repair(self.github, self.run, self.resolve))
        self.pr["user"]["login"] = "dependabot[bot]"
        self.pr["head"]["sha"] = "newer"
        self.assertIsNone(repair(self.github, self.run, self.resolve))
        self.pr["head"]["sha"] = "head"
        self.changed.append({"filename": ".pnpmfile.cjs", "status": "added"})
        self.assertIsNone(repair(self.github, self.run, self.resolve))
        self.resolve.assert_not_called()
        self.github.read.assert_not_called()

    def test_no_commit_or_install_when_already_aligned(self):
        package, workspace = fixture(core="1.4.0")
        self.github.read.side_effect = [package, workspace, "lock"]
        self.assertIsNone(repair(self.github, self.run, self.resolve))
        self.resolve.assert_not_called()
        self.assertFalse(any(call.args[0] == "git/trees" for call in self.github.api.call_args_list))

    def test_failed_branch_update_never_dispatches_ci(self):
        original = self.api
        def fail_push(path, data=None, method=None):
            if path.startswith("git/refs/"):
                raise RuntimeError("Concurrent update")
            return original(path, data, method)
        self.github.api.side_effect = fail_push
        with self.assertRaisesRegex(RuntimeError, "Concurrent update"):
            repair(self.github, self.run, self.resolve)
        self.assertFalse(any("dispatches" in call.args[0] for call in self.github.api.call_args_list))


if __name__ == "__main__":
    unittest.main()
