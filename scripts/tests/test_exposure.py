import ast
import copy
import json
import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from scripts.batch_contract import ContractError, verify_exposure

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


class StrictReportTest(unittest.TestCase):
    def test_duplicate_keys_and_non_finite_numbers_are_refused(self):
        body = json.dumps({**CONTRACT, "writable_paths": []})
        duplicate = body.replace('"mcp_servers": []', '"mcp_servers": ["x"], "mcp_servers": []')
        self.assertNotEqual(body, duplicate)
        for raw in (duplicate, body.replace('"version": 1', '"version": NaN'),
                    body.replace('"version": 1', '"version": Infinity')):
            with self.subTest(raw), self.assertRaises(ContractError):
                verify_exposure(raw, CONTRACT)

    def test_boolean_contract_version_is_refused(self):
        with self.assertRaises(ContractError):
            verify_exposure(FakeAdapter().exposure(), {**CONTRACT, "version": True})

    def test_inherited_config_must_be_canonical_absolute_paths(self):
        for bad in ("config.toml", "/a/../b/config.toml"):
            with self.subTest(bad), self.assertRaises(ContractError):
                verify_exposure(FakeAdapter(inherited_config=[bad]).exposure(),
                                {**CONTRACT, "inherited_config": [bad]})

    def test_symlink_inside_a_root_is_refused(self):
        with tempfile.TemporaryDirectory() as raw:
            root = Path(raw).resolve()
            (root / "outside").mkdir()
            (root / "scope").mkdir()
            (root / "scope" / "link").symlink_to(root / "outside")
            contract = {**CONTRACT, "writable_paths": [str(root / "scope")]}
            ok = FakeAdapter(writable_paths=[str(root / "scope" / "a.py")])
            verify_exposure(ok.exposure(), contract)
            escaped = FakeAdapter(writable_paths=[str(root / "scope" / "link" / "a.py")])
            with self.assertRaises(ContractError):
                verify_exposure(escaped.exposure(), contract)
            with self.assertRaises(ContractError):
                verify_exposure(ok.exposure(), {**contract, "writable_paths": [str(root / "scope" / "link")]})

    def test_inherited_config_is_compared_as_a_path_set(self):
        contract = {**CONTRACT, "inherited_config": ["/a/config.toml"]}
        verify_exposure(FakeAdapter(inherited_config=["/a/config.toml"]).exposure(), contract)


class NeutralImportsTest(unittest.TestCase):
    def test_this_module_imports_no_codex_module(self):
        tree = ast.parse(Path(__file__).read_text())
        imported = [node.module or "" for node in ast.walk(tree) if isinstance(node, ast.ImportFrom)]
        imported += [alias.name for node in ast.walk(tree) if isinstance(node, ast.Import) for alias in node.names]
        self.assertFalse([name for name in imported if "codex" in name.lower()], imported)


if __name__ == "__main__":
    unittest.main()
