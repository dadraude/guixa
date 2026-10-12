import json
import subprocess
import sys
import tempfile
import tomllib
import unittest
from pathlib import Path

SCRIPT = Path(__file__).resolve().parents[1] / "codex_profile.py"


class CodexProfileTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name).resolve()
        self.repo = self.root / "repo"
        self.repo.mkdir()
        self.git("init", cwd=self.repo)
        self.git("-c", "user.name=Test", "-c", "user.email=test@example.invalid",
                 "commit", "--allow-empty", "-m", "fixture", cwd=self.repo)
        self.worktree = self.root / "worktree"
        self.git("worktree", "add", "-b", "fixture", str(self.worktree), cwd=self.repo)
        self.output = self.root / "review.json"

    def git(self, *args, cwd):
        return subprocess.run(["git", *args], cwd=cwd, check=True, capture_output=True)

    def run_cli(self, *paths, output=None, repository=None):
        args = [sys.executable, str(SCRIPT), "--worktree", str(self.worktree),
                "--repository", str(repository or self.repo), "--output", str(output or self.output)]
        for path in paths:
            args.extend(["--write", path])
        return subprocess.run(args, capture_output=True, text=True)

    def test_generates_inactive_exact_scope_artifact(self):
        result = self.run_cli("scripts/new.py")
        self.assertEqual(result.returncode, 0, result.stderr)
        artifact = json.loads(self.output.read_text())
        config = tomllib.loads(artifact["toml"])
        self.assertEqual(config["approval_policy"], "never")
        self.assertEqual(config["default_permissions"], "guixa-batch")
        self.assertEqual(config["web_search"], "disabled")
        self.assertNotIn("sandbox_mode", config)
        policy = config["permissions"]["guixa-batch"]
        self.assertEqual(policy["filesystem"][str(self.worktree / "scripts/new.py")], "write")
        self.assertEqual(policy["filesystem"][":root"], "read")
        self.assertFalse(policy["network"]["enabled"])
        self.assertFalse(artifact["activation_ready"])
        self.assertIn("sealed launch without inherited tools", artifact["required_gates"])

    def test_refuses_protected_and_noncanonical_scopes(self):
        for scope in ("docs", "docs/adr/new.md", "scripts/../docs/note.md", "scripts/.env",
                      "scripts/.git/hooks/new", "scripts/package.json", "package.json", "app/new.php", "", "scripts//new.py"):
            with self.subTest(scope=scope):
                self.assertNotEqual(self.run_cli(scope).returncode, 0)
                self.assertFalse(self.output.exists())

    def test_refuses_symlinks_and_unrelated_repository(self):
        (self.worktree / "scripts").mkdir()
        (self.worktree / "scripts/link").symlink_to(self.root, target_is_directory=True)
        self.assertNotEqual(self.run_cli("scripts/link/new.py").returncode, 0)
        unrelated = self.root / "unrelated"
        unrelated.mkdir()
        self.git("init", cwd=unrelated)
        self.assertNotEqual(self.run_cli("scripts/new.py", repository=unrelated).returncode, 0)
        self.assertFalse(self.output.exists())

    def test_refuses_autoload_outputs_and_overwrite(self):
        for output in (self.root / "config.toml", self.root / "batch.config.toml",
                       self.worktree / ".codex/review.json"):
            self.assertNotEqual(self.run_cli("scripts/new.py", output=output).returncode, 0)
        self.output.write_text("keep")
        self.assertNotEqual(self.run_cli("scripts/new.py").returncode, 0)
        self.assertEqual(self.output.read_text(), "keep")

    def test_refuses_directory_grants(self):
        (self.worktree / "scripts").mkdir()
        self.assertNotEqual(self.run_cli("scripts").returncode, 0)
        self.assertFalse(self.output.exists())

    def test_refuses_main_checkout_empty_scope_and_output_symlinks(self):
        self.assertNotEqual(self.run_cli().returncode, 0)
        original = self.worktree
        self.worktree = self.repo
        self.assertNotEqual(self.run_cli("scripts/new.py").returncode, 0)
        self.worktree = original
        target = self.root / "target.json"
        target.write_text("keep")
        self.output.symlink_to(target)
        self.assertNotEqual(self.run_cli("scripts/new.py").returncode, 0)
        self.assertEqual(target.read_text(), "keep")

    def test_deterministic_fragment_and_digest(self):
        import hashlib
        self.assertEqual(self.run_cli("scripts/b.py", "docs/note.md").returncode, 0)
        first = json.loads(self.output.read_text())
        second_path = self.root / "second.json"
        self.assertEqual(self.run_cli("docs/note.md", "scripts/b.py", output=second_path).returncode, 0)
        self.assertEqual(first, json.loads(second_path.read_text()))
        self.assertEqual(first["sha256"], hashlib.sha256(first["toml"].encode()).hexdigest())


if __name__ == "__main__":
    unittest.main()
