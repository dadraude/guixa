import ast
import copy
import json
import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from scripts.batch_contract import ATTESTED_FIELDS, MEASURED_FIELDS, ContractError, verify_exposure

CONTRACT = {"version": 1, "tools": ["read"], "mcp_servers": [],
            "writable_paths": ["/work/tree/scripts"], "network": False, "inherited_config": []}


class FakeAdapter:
    """Reports exposure with no Codex import: measured fields in a report, attested ones apart."""

    def __init__(self, **changes):
        self.report = {key: copy.deepcopy(CONTRACT[key]) for key in MEASURED_FIELDS}
        self.attestation = {"network": False, "writable_paths": ["/work/tree/scripts/a.py"]}
        for key, value in changes.items():
            (self.attestation if key in ATTESTED_FIELDS else self.report)[key] = value

    def exposure(self):
        return json.dumps(self.report)

    def attested(self):
        return self.attestation

    def verify(self, contract):
        return verify_exposure(self.exposure(), contract, self.attested())


class NeutralVerifierTest(unittest.TestCase):
    def check(self, **changes):
        return FakeAdapter(**changes).verify(CONTRACT)

    def test_conforming_report_passes(self):
        result = self.check()
        self.assertEqual(result["attested"]["network"], False)
        self.assertEqual(set(result["measured"]), set(MEASURED_FIELDS))

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
                verify_exposure(raw, CONTRACT, FakeAdapter().attested())

    def test_both_halves_are_required(self):
        adapter = FakeAdapter()
        for attestation in (None, {}, {"network": False}, {"writable_paths": []},
                            {**adapter.attested(), "tools": []}):
            with self.subTest(attestation), self.assertRaises(ContractError):
                verify_exposure(adapter.exposure(), CONTRACT, attestation)

    def test_attested_fields_in_the_report_are_refused(self):
        for report in ({**FakeAdapter().report, "network": False},
                       {**FakeAdapter().report, "writable_paths": []}):
            with self.subTest(report), self.assertRaises(ContractError):
                verify_exposure(json.dumps(report), CONTRACT, FakeAdapter().attested())

    def test_measured_fields_in_the_attestation_are_refused(self):
        with self.assertRaises(ContractError):
            verify_exposure(FakeAdapter().exposure(), CONTRACT, {**FakeAdapter().attested(), "tools": ["read"]})

    def test_malformed_contract_is_refused(self):
        with self.assertRaises(ContractError):
            FakeAdapter().verify({**CONTRACT, "extra": 1})

    def test_network_allowed_only_when_declared(self):
        self.assertTrue(FakeAdapter(network=True).verify({**CONTRACT, "network": True})["attested"]["network"])


class StrictReportTest(unittest.TestCase):
    def test_duplicate_keys_and_non_finite_numbers_are_refused(self):
        body = json.dumps(FakeAdapter().report)
        duplicate = body.replace('"mcp_servers": []', '"mcp_servers": ["x"], "mcp_servers": []')
        self.assertNotEqual(body, duplicate)
        for raw in (duplicate, body.replace('"version": 1', '"version": NaN'),
                    body.replace('"version": 1', '"version": Infinity')):
            with self.subTest(raw), self.assertRaises(ContractError):
                verify_exposure(raw, CONTRACT, FakeAdapter().attested())

    def test_boolean_contract_version_is_refused(self):
        with self.assertRaises(ContractError):
            FakeAdapter().verify({**CONTRACT, "version": True})

    def test_inherited_config_must_be_canonical_absolute_paths(self):
        for bad in ("config.toml", "/a/../b/config.toml"):
            with self.subTest(bad), self.assertRaises(ContractError):
                FakeAdapter(inherited_config=[bad]).verify({**CONTRACT, "inherited_config": [bad]})

    def test_symlink_inside_a_root_is_refused(self):
        with tempfile.TemporaryDirectory() as raw:
            root = Path(raw).resolve()
            (root / "outside").mkdir()
            (root / "scope").mkdir()
            (root / "scope" / "link").symlink_to(root / "outside")
            contract = {**CONTRACT, "writable_paths": [str(root / "scope")]}
            ok = FakeAdapter(writable_paths=[str(root / "scope" / "a.py")])
            ok.verify(contract)
            escaped = FakeAdapter(writable_paths=[str(root / "scope" / "link" / "a.py")])
            with self.assertRaises(ContractError):
                escaped.verify(contract)
            with self.assertRaises(ContractError):
                ok.verify({**contract, "writable_paths": [str(root / "scope" / "link")]})

    def test_inherited_config_is_compared_as_a_path_set(self):
        contract = {**CONTRACT, "inherited_config": ["/a/config.toml"]}
        FakeAdapter(inherited_config=["/a/config.toml"]).verify(contract)

    def test_attested_writable_paths_must_be_canonical(self):
        for bad in ("relative", "/work/tree/scripts/../specs"):
            with self.subTest(bad), self.assertRaises(ContractError):
                FakeAdapter(writable_paths=[bad]).verify(CONTRACT)


class NeutralImportsTest(unittest.TestCase):
    def test_this_module_imports_no_codex_module(self):
        tree = ast.parse(Path(__file__).read_text())
        imported = [node.module or "" for node in ast.walk(tree) if isinstance(node, ast.ImportFrom)]
        imported += [alias.name for node in ast.walk(tree) if isinstance(node, ast.Import) for alias in node.names]
        self.assertFalse([name for name in imported if "codex" in name.lower()], imported)


if __name__ == "__main__":
    unittest.main()
