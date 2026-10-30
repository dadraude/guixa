"""Regression coverage for tooling command selection and argument safety."""

from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

from test_commands import COMMANDS, commands_for, helper_command, main


class CommandSelectionTest(unittest.TestCase):
    def test_selection_and_contract_commands_preserve_check_groups(self):
        self.assertEqual(commands_for("selection"), COMMANDS["selection"])
        self.assertEqual(commands_for("contracts"), COMMANDS["contracts"])
        self.assertEqual(len(commands_for("contracts")), 2)

    def test_helpers_expand_all_groups_and_keep_paths_as_argv(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            for path in (
                "skills/agent-workflow/tests/a.test.mjs",
                "skills/agent-workflow/tests/visual/b.test.mjs",
                ".agent-workflow/tests/with space.test.mjs",
            ):
                target = root / path
                target.parent.mkdir(parents=True, exist_ok=True)
                target.touch()
            self.assertEqual(helper_command(root), (
                "node", "--test",
                "skills/agent-workflow/tests/a.test.mjs",
                "skills/agent-workflow/tests/visual/b.test.mjs",
                ".agent-workflow/tests/with space.test.mjs",
            ))

    def test_missing_helper_group_fails_before_running_partial_suite(self):
        with tempfile.TemporaryDirectory() as directory:
            with self.assertRaisesRegex(ValueError, "No helper tests match"):
                helper_command(Path(directory))

    def test_unknown_selector_and_extra_arguments_do_not_run_children(self):
        with patch("test_commands.subprocess.run") as run:
            with patch("sys.stderr"):
                self.assertEqual(main(["unknown"]), 2)
                self.assertEqual(main(["selection", "--unexpected"]), 2)
                self.assertEqual(main([]), 2)
            run.assert_not_called()

    def test_known_selector_runs_fixed_argv_in_repository_root(self):
        with patch("test_commands.subprocess.run") as run:
            self.assertEqual(main(["contracts"]), 0)
        self.assertEqual([call.args[0] for call in run.call_args_list], list(COMMANDS["contracts"]))
        self.assertTrue(all(call.kwargs["check"] for call in run.call_args_list))


if __name__ == "__main__":
    unittest.main()
