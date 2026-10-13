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


ATTESTATION = {"network": False, "writable_paths": []}


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
        return json.dumps({"version": 1, **measured})

    def report(self, servers, features, plan=None):
        def run(argv, **kwargs):
            out = json.dumps(servers) if "mcp" in argv else features
            return subprocess.CompletedProcess(argv, 0, out, "")
        with patch.object(worker.subprocess, "run", side_effect=run):
            return worker.exposure_report(plan or self.plan)

    def verify(self, report, contract):
        return verify_exposure(json.dumps(report["measured"]), contract, report["attested"])

    def test_supported_configuration_passes(self):
        measured = self.measure([{"name": "test", "enabled": False}], "multi_agent stable false")
        self.assertEqual(measured["mcp_servers"], [])
        verify_exposure(self.full(measured), self.contract(), ATTESTATION)

    def test_report_comes_from_inventory_not_from_flags(self):
        measured = self.measure([{"name": "test", "enabled": True}], "multi_agent stable true")
        self.assertEqual((measured["mcp_servers"], measured["tools"]), (["test"], ["multi_agent"]))
        with self.assertRaises(ContractError):
            verify_exposure(self.full(measured), self.contract(), ATTESTATION)

    def test_full_desktop_configuration_is_refused(self):
        features = "\n".join(f"{name} stable true" for name in
                             ("apps", "plugins", "browser_use", "computer_use"))
        servers = [{"name": name, "enabled": True} for name in ("test", "linear", "figma")]
        measured = self.measure(servers, features)
        self.assertEqual(len(measured["tools"]), 4)
        with self.assertRaises(ContractError):
            verify_exposure(self.full(measured), self.contract(), ATTESTATION)

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
            verify_exposure(self.full(measured), declared, ATTESTATION)

    def test_attestation_comes_from_the_sealed_plan_and_measurement_stays_separate(self):
        report = self.report([{"name": "test", "enabled": False}], "multi_agent stable false")
        self.assertEqual(set(report["measured"]), {"version", "tools", "mcp_servers", "inherited_config"})
        self.assertEqual(report["attested"]["network"], False)
        writable = sorted(path for path, mode in self.plan["filesystem"].items() if mode == "write")
        self.assertEqual(report["attested"]["writable_paths"], writable)
        self.assertTrue(writable)
        contract = self.contract(writable_paths=writable)
        self.assertEqual(self.verify(report, contract)["attested"], report["attested"])
        with self.assertRaises(ContractError):  # attested scope is checked against the contract
            self.verify(report, self.contract(writable_paths=[str(self.tree / "other")]))

    def test_plan_network_is_read_from_the_profile_not_override_text(self):
        self.assertIs(self.plan["network"], False)
        plan = {**self.plan, "network": True}
        report = self.report([{"name": "test", "enabled": False}], "multi_agent stable false", plan)
        self.assertTrue(report["attested"]["network"])
        with self.assertRaises(ContractError):
            self.verify(report, self.contract(writable_paths=report["attested"]["writable_paths"]))

    def test_server_named_network_does_not_change_the_attestation(self):
        report = self.report([{"name": "network", "enabled": False}, {"name": "test", "enabled": False}],
                             "multi_agent stable false")
        self.assertFalse(report["attested"]["network"])

    def test_enabled_web_search_is_a_network_exposure(self):
        report = self.report([{"name": "test", "enabled": False}], "web_search stable true")
        self.assertTrue(report["attested"]["network"])
        contract = self.contract(tools=["web_search"], writable_paths=report["attested"]["writable_paths"])
        with self.assertRaises(ContractError):
            self.verify(report, contract)
        plan = {**self.plan, "web_search": "live"}
        report = self.report([{"name": "test", "enabled": False}], "multi_agent stable false", plan)
        self.assertTrue(report["attested"]["network"])


if __name__ == "__main__":
    unittest.main()
