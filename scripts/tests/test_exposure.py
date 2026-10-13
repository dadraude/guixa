import copy
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

CONTRACT = {"version": 1, "tools": ["read"], "mcp_servers": [],
            "writable_paths": ["/work/tree/scripts"], "network": False, "inherited_config": []}


class FakeAdapter:
    """Reports exposure with no Codex import: any adapter only has to emit this shape."""

    def __init__(self, **changes):
        self.report = {**copy.deepcopy(CONTRACT), "writable_paths": ["/work/tree/scripts/a.py"], **changes}

    def exposure(self):
        return json.dumps(self.report)


class NeutralVerifierTest(unittest.TestCase):
    def check(self, **changes):
        return verify_exposure(FakeAdapter(**changes).exposure(), CONTRACT)

    def test_conforming_report_passes(self):
        self.assertEqual(self.check()["network"], False)

    def test_rejections(self):
        for change in ({"tools": ["read", "shell"]}, {"mcp_servers": ["linear"]},
                       {"writable_paths": ["/work/tree/specs/x.md"]},
                       {"writable_paths": ["/work/tree/scripts/../specs/x"]},
                       {"writable_paths": ["relative"]}, {"network": True}, {"network": "no"},
                       {"inherited_config": ["/home/u/.x/config.toml"]}, {"extra": 1}, {"version": 2}):
            with self.subTest(change), self.assertRaises(ContractError):
                self.check(**change)

    def test_missing_and_malformed_reports_are_refused(self):
        for raw in ("", "not json", "[]", "null", json.dumps({"version": 1})):
            with self.subTest(raw), self.assertRaises(ContractError):
                verify_exposure(raw, CONTRACT)

    def test_malformed_contract_is_refused(self):
        with self.assertRaises(ContractError):
            verify_exposure(FakeAdapter().exposure(), {**CONTRACT, "extra": 1})

    def test_network_allowed_only_when_declared(self):
        self.assertTrue(verify_exposure(FakeAdapter(network=True).exposure(),
                                        {**CONTRACT, "network": True})["network"])


class CodexExposureTest(unittest.TestCase):
    def setUp(self):
        # Reuse the Codex fixture by composition so its own tests are not re-run here.
        self.base = codex_tests.WorkerTest("test_fixed_native_argv_and_clean_environment")
        self.base.setUp()
        self.addCleanup(self.base.temp.cleanup)
        self.tree, self.config, self.prepare = self.base.tree, self.base.config, self.base.prepare

    def measure(self, servers, features):
        def run(argv, **kwargs):
            out = json.dumps(servers) if "mcp" in argv else features
            return subprocess.CompletedProcess(argv, 0, out, "")
        plan = self.prepare()  # before patching: Git helpers also go through subprocess.run
        with patch.object(worker.subprocess, "run", side_effect=run):
            return worker.exposure_report(plan)

    def contract(self):
        return {"version": 1, "tools": [], "mcp_servers": [], "network": False,
                "writable_paths": [str(self.tree / "scripts/new.py")], "inherited_config": [str(self.config)]}

    def test_supported_configuration_passes(self):
        report = self.measure([{"name": "test", "enabled": False}], "multi_agent stable false")
        self.assertEqual(report["mcp_servers"], [])
        verify_exposure(json.dumps(report), self.contract())

    def test_report_comes_from_inventory_not_from_flags(self):
        report = self.measure([{"name": "test", "enabled": True}], "multi_agent stable true")
        self.assertEqual((report["mcp_servers"], report["tools"]), (["test"], ["multi_agent"]))
        with self.assertRaises(ContractError):
            verify_exposure(json.dumps(report), self.contract())

    def test_unparseable_inventory_is_refused(self):
        with self.assertRaises(ValueError):
            self.measure({"not": "a list"}, "multi_agent stable false")
        with self.assertRaises(ValueError):
            self.measure([], "garbage")


if __name__ == "__main__":
    unittest.main()
