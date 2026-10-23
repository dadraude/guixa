"""Offline contract checks. Run with python3 -m unittest discover -s this-directory."""

from pathlib import Path
import os
import re
import shutil
import subprocess
import unittest


ROOT = Path(__file__).resolve().parents[3]
WORKFLOW = ROOT / "skills/guixa-workflow"
ENTRIES = {"implement-session": "session", "implement-task": "task", "to-tickets": "tickets"}
PHP_RUNTIME = os.environ.get("GUIXA_WORKFLOW_PHP")


def local_links(path):
    for target in re.findall(r"\]\(([^)]+)\)", path.read_text()):
        if "://" not in target and not target.startswith("#"):
            yield (path.parent / target.split("#", 1)[0]).resolve()


class WorkflowLayoutTest(unittest.TestCase):
    def test_both_runtimes_load_the_same_process(self):
        for name, process in ENTRIES.items():
            for runtime, folder in (("codex", ".agents"), ("claude-code", ".claude")):
                with self.subTest(skill=name, runtime=runtime):
                    skill_name = f"guixa-{name}" if runtime == "codex" else name
                    entry = ROOT / folder / "skills" / skill_name / "SKILL.md"
                    links = set(local_links(entry))
                    self.assertIn(WORKFLOW / "core" / f"{process}.md", links)
                    self.assertIn(WORKFLOW / "adapters" / f"{runtime}.md", links)
                    self.assertIn(WORKFLOW / "project.md", links)

    def test_explicit_invocation_is_preserved(self):
        for name in ENTRIES:
            claude = (ROOT / ".claude/skills" / name / "SKILL.md").read_text()
            self.assertIn("disable-model-invocation: true", claude.split("---")[1])
            codex_dir = ROOT / ".agents/skills" / f"guixa-{name}"
            metadata = (codex_dir / "SKILL.md").read_text().split("---")[1]
            self.assertIn(f"name: guixa-{name}", metadata)
            self.assertNotIn("disable-model-invocation", metadata)
            policy = (codex_dir / "agents/openai.yaml").read_text()
            self.assertRegex(policy, r"policy:\s+allow_implicit_invocation: false")

    def test_local_references_exist(self):
        files = list(WORKFLOW.rglob("*.md"))
        self.assertTrue(files)
        for folder, prefix in ((".claude", ""), (".agents", "guixa-")):
            files.extend(ROOT / folder / "skills" / f"{prefix}{name}" / "SKILL.md" for name in ENTRIES)
        for file in files:
            for target in local_links(file):
                with self.subTest(source=file, target=target):
                    self.assertTrue(target.is_file())

    def test_core_does_not_bind_a_provider_or_machine(self):
        files = list((WORKFLOW / "core").glob("*.md"))
        self.assertEqual({file.stem for file in files},
                         {"session", "task", "scheduling", "implementer", "reviewer", "tickets", "handoff"})
        for file in files:
            with self.subTest(source=file):
                self.assertNotRegex(file.read_text(),
                                    r"/Users/|SendMessage|ToolSearch|spawn_agent|haiku|sonnet|opus|\.claude/|\.agents/")

    def test_legacy_briefs_are_forwarders(self):
        aliases = {"implement-session/scheduling.md": "scheduling",
                   "implement-task/implementer.md": "implementer",
                   "implement-task/reviewer.md": "reviewer"}
        for old, new in aliases.items():
            path = ROOT / ".claude/skills" / old
            self.assertIn(WORKFLOW / "core" / f"{new}.md", set(local_links(path)))
            self.assertLess(len(path.read_text().splitlines()), 12)

    def test_cleanup_keeps_a_single_implementation(self):
        shared = WORKFLOW / "scripts/drop-test-databases.php"
        self.assertTrue(shared.is_file())
        legacy = ROOT / ".claude/skills/implement-task/drop-test-databases.php"
        self.assertIn("require", legacy.read_text())
        self.assertNotIn("DROP DATABASE", legacy.read_text())
        # Both layouts are three levels below root, preserving the helper's .env lookup.
        self.assertEqual(shared.parent.parent.parent.parent, ROOT)
        self.assertEqual(legacy.parent.parent.parent.parent, ROOT)

    @unittest.skipUnless(PHP_RUNTIME or shutil.which("herd") or shutil.which("php"), "PHP runtime unavailable")
    def test_cleanup_rejects_invalid_ticket_through_both_entrypoints(self):
        if PHP_RUNTIME:
            self.assertTrue(Path(PHP_RUNTIME).is_absolute() and Path(PHP_RUNTIME).is_file())
            runner = [PHP_RUNTIME]
        else:
            runner = ["herd", "php"] if shutil.which("herd") else ["php"]
        paths = [WORKFLOW / "scripts/drop-test-databases.php",
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
