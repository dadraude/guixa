import json
import subprocess
import sys
import unittest
from pathlib import Path
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from scripts.batch_contract import ContractError, verify_exposure
from scripts.tests import test_codex_worker as codex_tests
import codex_worker as worker


class CodexExposureTest(unittest.TestCase):
    def setUp(self):
        # Reuse the Codex fixture by composition so its own tests are not re-run here.
        self.base = codex_tests.WorkerTest("test_fixed_native_argv_and_clean_environment")
        self.base.setUp()
        self.addCleanup(self.base.temp.cleanup)
        self.tree, self.config, self.home = self.base.tree, self.base.config, self.base.home
        self.plan = self.base.prepare()  # before patching: Git helpers also go through subprocess.run
        self.calls = []

    def measure(self, servers, features):
        def run(argv, **kwargs):
            self.calls.append((argv, kwargs))
            out = json.dumps(servers) if "mcp" in argv else features
            return subprocess.CompletedProcess(argv, 0, out, "")
        with patch.object(worker.subprocess, "run", side_effect=run):
            return worker.measured_inventory(self.plan)

    def contract(self, **changes):
        layers = worker.inherited_layers(self.plan)
        return {"version": 1, "tools": [], "mcp_servers": [], "network": False,
                "writable_paths": [str(self.tree / "scripts/new.py")], "inherited_config": layers, **changes}

    def full(self, measured):
        return json.dumps({"version": 1, "network": False, "writable_paths": [], **measured})

    def test_supported_configuration_passes(self):
        measured = self.measure([{"name": "test", "enabled": False}], "multi_agent stable false")
        self.assertEqual(measured["mcp_servers"], [])
        verify_exposure(self.full(measured), self.contract())

    def test_report_comes_from_inventory_not_from_flags(self):
        measured = self.measure([{"name": "test", "enabled": True}], "multi_agent stable true")
        self.assertEqual((measured["mcp_servers"], measured["tools"]), (["test"], ["multi_agent"]))
        with self.assertRaises(ContractError):
            verify_exposure(self.full(measured), self.contract())

    def test_full_desktop_configuration_is_refused(self):
        features = "\n".join(f"{name} stable true" for name in
                             ("apps", "plugins", "browser_use", "computer_use"))
        servers = [{"name": name, "enabled": True} for name in ("test", "linear", "figma")]
        measured = self.measure(servers, features)
        self.assertEqual(len(measured["tools"]), 4)
        with self.assertRaises(ContractError):
            verify_exposure(self.full(measured), self.contract())

    def test_unparseable_or_unknown_inventory_is_refused(self):
        for servers, features in (({"not": "a list"}, "multi_agent stable false"),
                                  ([], "garbage"), ([{"name": "test", "enabled": False}], ""),
                                  ([{"name": "test", "enabled": False}], "multi_agent odd false")):
            with self.subTest(features), self.assertRaises(ValueError):
                self.measure(servers, features)

    def test_incomplete_mcp_inventory_is_refused(self):
        with self.assertRaises(ValueError):
            self.measure([], "multi_agent stable false")

    def test_probes_run_in_the_workers_context(self):
        self.measure([{"name": "test", "enabled": False}], "multi_agent stable false")
        overrides = [item for flag, item in zip(self.plan["argv"], self.plan["argv"][1:]) if flag == "-c"]
        self.assertEqual(len(self.calls), 2)
        for argv, kwargs in self.calls:
            self.assertEqual(kwargs["env"], self.plan["env"])
            self.assertEqual(kwargs["cwd"], str(self.tree))
            self.assertEqual([item for flag, item in zip(argv, argv[1:]) if flag == "-c"], overrides)

    def test_inherited_config_is_discovered_not_constant(self):
        declared = self.contract()
        (self.home / "AGENTS.md").write_text("instructions")
        measured = self.measure([{"name": "test", "enabled": False}], "multi_agent stable false")
        self.assertIn(str(self.home / "AGENTS.md"), measured["inherited_config"])
        with self.assertRaises(ContractError):
            verify_exposure(self.full(measured), declared)

    def test_network_and_writable_paths_have_no_measured_source_yet(self):
        def run(argv, **kwargs):
            out = json.dumps([{"name": "test", "enabled": False}]) if "mcp" in argv else "multi_agent stable false"
            return subprocess.CompletedProcess(argv, 0, out, "")
        with patch.object(worker.subprocess, "run", side_effect=run), self.assertRaises(ValueError):
            worker.exposure_report(self.plan)


if __name__ == "__main__":
    unittest.main()
