"""Guixa-specific workflow checks. Run with python3 -m unittest discover -s this-directory."""

from pathlib import Path
import os
import re
import shutil
import subprocess
import unittest


ROOT = Path(__file__).resolve().parents[2]
WORKFLOW = ROOT / "skills/agent-workflow"
PHP_RUNTIME = os.environ.get("GUIXA_WORKFLOW_PHP")


def local_links(path):
    for target in re.findall(r"\]\(([^)]+)\)", path.read_text()):
        if "://" not in target and not target.startswith("#"):
            yield (path.parent / target.split("#", 1)[0]).resolve()


class ProjectWorkflowTest(unittest.TestCase):
    def test_legacy_briefs_are_forwarders(self):
        aliases = {"implement-session/scheduling.md": "scheduling",
                   "implement-task/implementer.md": "implementer",
                   "implement-task/reviewer.md": "reviewer"}
        for old, new in aliases.items():
            path = ROOT / ".claude/skills" / old
            self.assertIn(WORKFLOW / "core" / f"{new}.md", set(local_links(path)))
            self.assertLess(len(path.read_text().splitlines()), 12)

    def test_codex_agents_pin_the_profile_models(self):
        section = (ROOT / ".agent-workflow/profile.md").read_text().split("## Model routing", 1)[1]
        for tier, model in re.findall(r"^\| (Light|Standard|Strong) .*\| `([\w.-]+)` *\|$", section, re.MULTILINE):
            with self.subTest(tier=tier):
                agent = (ROOT / f".codex/agents/agent-workflow-{tier.lower()}.toml").read_text()
                self.assertIn(f'name = "agent-workflow-{tier.lower()}"', agent)
                self.assertIn(f'model = "{model}"', agent)
                self.assertRegex(agent, r'model_reasoning_effort = "\w+"')

    def test_cleanup_keeps_a_single_implementation(self):
        shared = ROOT / ".agent-workflow/scripts/drop-test-databases.php"
        self.assertTrue(shared.is_file())
        self.assertIn("__DIR__.'/../../.env'", shared.read_text())
        legacy = ROOT / ".claude/skills/implement-task/drop-test-databases.php"
        self.assertIn("require", legacy.read_text())
        self.assertNotIn("DROP DATABASE", legacy.read_text())

    @unittest.skipUnless(PHP_RUNTIME or shutil.which("herd") or shutil.which("php"), "PHP runtime unavailable")
    def test_cleanup_rejects_invalid_ticket_through_both_entrypoints(self):
        if PHP_RUNTIME:
            self.assertTrue(Path(PHP_RUNTIME).is_absolute() and Path(PHP_RUNTIME).is_file())
            runner = [PHP_RUNTIME]
        else:
            runner = ["herd", "php"] if shutil.which("herd") else ["php"]
        paths = [ROOT / ".agent-workflow/scripts/drop-test-databases.php",
                 ROOT / ".claude/skills/implement-task/drop-test-databases.php"]
        for path in paths:
            with self.subTest(entrypoint=path):
                result = subprocess.run([*runner, str(path), "invalid-ticket"], cwd=ROOT,
                                        capture_output=True, text=True, timeout=15)
                self.assertEqual(result.returncode, 1)
                self.assertEqual(result.stdout, "")
                self.assertEqual(result.stderr,
                                 "Usage: php drop-test-databases.php <ticket number> [--dry-run]\n")


if __name__ == "__main__":
    unittest.main()
