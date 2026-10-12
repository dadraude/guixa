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
        self.assertEqual({path for path, mode in policy["filesystem"].items() if mode == "write"},
                         {str(self.worktree / "scripts/new.py")})
        self.assertIn("launch-time revalidation of exact write paths", artifact["required_gates"])
        self.assertEqual(policy["filesystem"][":root"], "read")
        self.assertFalse(policy["network"]["enabled"])
        self.assertFalse(artifact["activation_ready"])
        self.assertIn("sealed launch without inherited tools", artifact["required_gates"])

    def test_preserves_non_bmp_path_in_valid_toml(self):
        scope = "scripts/🧪-é.py"
        result = self.run_cli(scope)
        self.assertEqual(result.returncode, 0, result.stderr)
        artifact = json.loads(self.output.read_text())
        config = tomllib.loads(artifact["toml"])
        expected = str(self.worktree / scope)
        self.assertEqual(artifact["approved_writes"], [expected])
        self.assertEqual(config["permissions"]["guixa-batch"]["filesystem"][expected], "write")

    def test_refuses_protected_and_noncanonical_scopes(self):
        for scope in ("docs", "docs/adr/new.md", "docs/ADR/new.md",
                      "scripts/agents.MD", "scripts/AGENTS.md", "scripts/.CODEX/new.py",
                      "scripts/.ENV.local", "scripts/../docs/note.md", "scripts/.env",
                      "scripts/.git/hooks/new", "scripts/package.json", "package.json", "app/new.php", "", "scripts//new.py"):
            self.output.unlink(missing_ok=True)
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
                       self.worktree / ".codex/review.json", self.worktree / ".CODEX/review.json",
                       self.root / "CONFIG.TOML", self.root / "batch.CONFIG.TOML"):
            output.parent.mkdir(parents=True, exist_ok=True)
            with self.subTest(output=output):
                self.assertNotEqual(self.run_cli("scripts/new.py", output=output).returncode, 0)
                self.assertFalse(output.exists())
                output.unlink(missing_ok=True)
        self.output.write_text("keep")
        self.assertNotEqual(self.run_cli("scripts/new.py").returncode, 0)
        self.assertEqual(self.output.read_text(), "keep")

    def test_refuses_directory_grants(self):
        for scope in ("scripts", "scripts/subdir", "docs/subdir"):
            (self.worktree / scope).mkdir(parents=True, exist_ok=True)
            self.output.unlink(missing_ok=True)
            with self.subTest(scope=scope):
                self.assertNotEqual(self.run_cli(scope).returncode, 0)
                self.assertFalse(self.output.exists())

    def test_refuses_glob_and_control_syntax_in_grants(self):
        for scope in ("scripts/*.py", "scripts/a?.py", "scripts/[ab].py",
                      "scripts/{a,b}.py", "scripts/a\\b.py", "scripts/a\n.py",
                      "scripts/a\t.py", "scripts/a\x7f.py"):
            self.output.unlink(missing_ok=True)
            with self.subTest(scope=scope):
                self.assertNotEqual(self.run_cli(scope).returncode, 0)
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
