"""Regression coverage for job selection and the required CI gate."""

import os
from pathlib import Path
import subprocess
import tempfile
import unittest

from checks import JOBS, failed_jobs, select_jobs


class SelectionTest(unittest.TestCase):
    def selected(self, *paths, event="pull_request"):
        return {job for job, enabled in select_jobs(paths, event).items() if enabled}

    def test_docs_do_not_boot_application(self):
        self.assertEqual(self.selected("README.md", "docs/a.md", "specs/a.md", "odd/a.md"), set())

    def test_agent_tooling_is_checked_without_application(self):
        for folder in ("skills", ".agents", ".claude", ".codex", ".agent-workflow"):
            with self.subTest(folder=folder):
                self.assertEqual(self.selected(folder + "/file.md"), {"tooling"})

    def test_frontend_and_php_tests_can_run_independently(self):
        self.assertEqual(self.selected("resources/js/pages/Home.vue", "resources/css/app.css"), {"frontend"})
        self.assertEqual(self.selected("tests/Feature/ExampleTest.php", "phpstan.neon"), {"backend"})

    def test_shared_contracts_and_unknown_files_check_both_sides(self):
        for path in ("app/Models/User.php", "src/Ordering/Domain/Cycle.php", "routes/web.php",
                     "lang/ca.json", "resources/views/app.blade.php", "composer.lock",
                     "package.json", "pnpm-lock.yaml", "vite.config.ts", ".env.example", "new-file"):
            with self.subTest(path=path):
                self.assertEqual(self.selected(path), {"frontend", "backend"})

    def test_mixed_changes_union_the_selected_jobs(self):
        self.assertEqual(self.selected("docs/a.md", "skills/a.md", "resources/js/a.ts", "tests/a.php"), set(JOBS))

    def test_ci_changes_and_main_pushes_run_every_job(self):
        self.assertEqual(self.selected(".github/scripts/checks.py"), set(JOBS))
        self.assertEqual(self.selected("README.md", event="push"), set(JOBS))

    def test_empty_diff_and_unusual_filenames(self):
        self.assertEqual(self.selected(), set())
        self.assertEqual(self.selected("resources/js/file with spaces\nand newline.ts"), {"frontend"})


class GateTest(unittest.TestCase):
    def needs(self, selected=()):
        return {
            "changes": {"result": "success", "outputs": {job: str(job in selected).lower() for job in JOBS}},
            "docs": {"result": "success"},
            "audit": {"result": "success"},
            **{job: {"result": "success" if job in selected else "skipped"} for job in JOBS},
        }

    def test_success_and_intentional_skips_pass(self):
        self.assertEqual(failed_jobs(self.needs()), [])
        self.assertEqual(failed_jobs(self.needs(JOBS)), [])

    def test_failed_cancelled_and_unexpectedly_skipped_required_jobs_fail(self):
        for job in ("changes", "docs", "audit", *JOBS):
            for result in ("failure", "cancelled", "skipped", None):
                with self.subTest(job=job, result=result):
                    needs = self.needs(JOBS)
                    needs[job]["result"] = result
                    self.assertIn(job, failed_jobs(needs))

    def test_unselected_job_failure_is_still_reported(self):
        needs = self.needs()
        needs["frontend"]["result"] = "failure"
        self.assertEqual(failed_jobs(needs), ["frontend"])

    def test_missing_or_invalid_selection_outputs_fail_closed(self):
        for value in (None, "", "TRUE", "unexpected"):
            with self.subTest(value=value):
                needs = self.needs()
                needs["changes"]["outputs"]["backend"] = value
                self.assertEqual(failed_jobs(needs), ["changes"])

    def test_missing_job_results_fail_closed(self):
        self.assertEqual(set(failed_jobs({})), {"changes", "docs", "audit", *JOBS})


class DiffTest(unittest.TestCase):
    def test_real_git_diff_handles_deletions_renames_and_bad_base(self):
        script = Path(__file__).with_name("checks.py").resolve()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)

            def git(*args):
                return subprocess.check_output(["git", *args], cwd=root, stderr=subprocess.DEVNULL).decode().strip()

            git("init", "-q")
            git("config", "user.email", "test@example.invalid")
            git("config", "user.name", "Test")
            (root / "resources/js").mkdir(parents=True)
            (root / "resources/js/old\nname.ts").write_text("old frontend")
            (root / "tests").mkdir()
            (root / "tests/removed.php").write_text("old test")
            git("add", ".")
            git("commit", "-qm", "base")
            base = git("rev-parse", "HEAD")
            (root / "docs").mkdir()
            (root / "resources/js/old\nname.ts").rename(root / "docs/moved.md")
            (root / "tests/removed.php").unlink()
            git("add", "-A")
            git("commit", "-qm", "move and delete")
            output = root / "output"
            env = {**os.environ, "EVENT": "pull_request", "BASE_SHA": base,
                   "HEAD_SHA": git("rev-parse", "HEAD"), "GITHUB_OUTPUT": str(output)}
            subprocess.run(["python3", str(script), "changes"], cwd=root, env=env,
                           check=True, capture_output=True)
            self.assertEqual(output.read_text(), "frontend=true\nbackend=true\ntooling=false\n")
            output.unlink()
            env["BASE_SHA"] = "missing-ref"
            result = subprocess.run(["python3", str(script), "changes"], cwd=root, env=env,
                                    capture_output=True)
            self.assertNotEqual(result.returncode, 0)
            self.assertFalse(output.exists())


if __name__ == "__main__":
    unittest.main()
