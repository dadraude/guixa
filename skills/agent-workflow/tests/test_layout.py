"""Offline contract checks. Run with python3 -m unittest discover -s this-directory."""

from pathlib import Path
import re
import unittest


ROOT = Path(__file__).resolve().parents[3]
WORKFLOW = ROOT / "skills/agent-workflow"
PROFILE = ROOT / ".agent-workflow/profile.md"
ENTRIES = {"implement-session": "session", "implement-task": "task", "to-tickets": "tickets"}
PROJECT_TERMS = r"\bLinear\b|\bTnn\b|\bDRA\b|guixa|origin/main|ready-for-agent|parentId"


def local_links(path):
    for target in re.findall(r"\]\(([^)]+)\)", path.read_text()):
        if "://" not in target and not target.startswith("#"):
            yield (path.parent / target.split("#", 1)[0]).resolve()


def headings(path):
    return re.findall(r"^## (.+)$", path.read_text(), re.MULTILINE)


def codex_entry(name):
    matches = sorted((ROOT / ".agents/skills").glob(f"*-{name}"))
    if len(matches) != 1:
        raise AssertionError(f"expected one Codex entrypoint for {name}, found {matches}")
    return matches[0]


class WorkflowLayoutTest(unittest.TestCase):
    def test_both_runtimes_load_the_same_process(self):
        for name, process in ENTRIES.items():
            entries = (("codex", codex_entry(name) / "SKILL.md"),
                       ("claude-code", ROOT / ".claude/skills" / name / "SKILL.md"))
            for runtime, entry in entries:
                with self.subTest(skill=name, runtime=runtime):
                    links = set(local_links(entry))
                    self.assertIn(WORKFLOW / "core" / f"{process}.md", links)
                    self.assertIn(WORKFLOW / "adapters" / f"{runtime}.md", links)
                    self.assertIn(PROFILE, links)

    def test_explicit_invocation_is_preserved(self):
        for name in ENTRIES:
            claude = (ROOT / ".claude/skills" / name / "SKILL.md").read_text()
            self.assertIn("disable-model-invocation: true", claude.split("---")[1])
            codex_dir = codex_entry(name)
            metadata = (codex_dir / "SKILL.md").read_text().split("---")[1]
            self.assertIn(f"name: {codex_dir.name}", metadata)
            self.assertNotIn("disable-model-invocation", metadata)
            policy = (codex_dir / "agents/openai.yaml").read_text()
            self.assertRegex(policy, r"policy:\s+allow_implicit_invocation: false")

    def test_local_references_exist(self):
        files = list(WORKFLOW.rglob("*.md")) + [PROFILE]
        for name in ENTRIES:
            files += [ROOT / ".claude/skills" / name / "SKILL.md", codex_entry(name) / "SKILL.md"]
        for file in files:
            for target in local_links(file):
                with self.subTest(source=file, target=target):
                    self.assertTrue(target.is_file())

    def test_profile_has_every_template_section(self):
        self.assertEqual(headings(PROFILE), headings(WORKFLOW / "profile-template.md"))

    def test_core_does_not_bind_a_provider_or_machine(self):
        files = list((WORKFLOW / "core").glob("*.md"))
        self.assertEqual({file.stem for file in files},
                         {"session", "task", "scheduling", "implementer", "reviewer", "tickets", "handoff"})
        for file in files:
            with self.subTest(source=file):
                self.assertNotRegex(file.read_text(),
                                    r"/Users/|SendMessage|ToolSearch|spawn_agent|haiku|sonnet|opus|\.claude/|\.agents/")

    def test_shared_files_do_not_bind_a_project(self):
        files = [*(WORKFLOW / "core").glob("*.md"), *(WORKFLOW / "adapters").glob("*.md"),
                 WORKFLOW / "README.md", WORKFLOW / "profile-template.md"]
        for file in files:
            with self.subTest(source=file):
                self.assertNotRegex(file.read_text(), PROJECT_TERMS)


if __name__ == "__main__":
    unittest.main()
