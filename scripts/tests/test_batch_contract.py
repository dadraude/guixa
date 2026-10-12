import copy
import os
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

from scripts.batch_contract import ContractError, validate_manifest, validate_result


class ContractTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.home = Path(self.temp.name)
        self.repo = self.home / "repo"
        self.repo.mkdir()
        self.env = patch.dict(os.environ, {"HOME": str(self.home)})
        self.env.start()
        self.addCleanup(self.env.stop)
        self.manifest = {
            "version": 1, "project": "P-DRA-2", "repository": str(self.repo),
            "worktree_root": str(self.home / "worktrees"), "base": "main",
            "adapter": ["/bin/echo", "literal;$(no-shell)"], "verifier": ["/bin/echo"],
            "models": {role: "configured-model" for role in
                       ("orchestrator", "implementer", "spec_reviewer", "correctness_reviewer")},
            "batches": [{"id": "first", "branch": "feature/dra-1-first", "tickets": [
                {"id": "DRA-1", "depends_on": []}, {"id": "DRA-2", "depends_on": ["DRA-1"]}]}],
        }

    def test_related_tickets_are_one_batch_with_literal_provider_arguments(self):
        manifest = validate_manifest(self.manifest)
        self.assertEqual(manifest["max_parallel"], 3)
        self.assertEqual(manifest["batches"][0]["database"], "testing_dra1")
        self.assertEqual(manifest["adapter"][1], "literal;$(no-shell)")

    def test_invalid_manifest_never_accepts_partial_configuration(self):
        changes = [
            {"project": ""}, {"max_parallel": True}, {"adapter": ["missing"]},
            {"base": "main;touch /tmp/no"}, {"unknown": True},
            {"worktree_root": str(self.repo / "nested")},
            {"models": {"orchestrator": "only-one-role"}},
        ]
        for change in changes:
            with self.subTest(change=change), self.assertRaises(ContractError):
                validate_manifest(self.manifest | change)

    def test_ticket_and_dependency_constraints(self):
        for tickets in ([{"id": "DRA-1", "depends_on": ["DRA-2"]},
                         {"id": "DRA-2", "depends_on": ["DRA-1"]}],
                        [{"id": "DRA-1", "depends_on": ["DRA-99"]}],
                        [{"id": "DRA-1"}] * 2,
                        [{"id": f"DRA-{i}"} for i in range(1, 6)]):
            bad = copy.deepcopy(self.manifest)
            bad["batches"][0]["tickets"] = tickets
            with self.subTest(tickets=tickets), self.assertRaises(ContractError):
                validate_manifest(bad)

    def test_delivery_and_partial_question_results_require_exact_identity(self):
        manifest = validate_manifest(self.manifest)
        batch = manifest["batches"][0]
        request = {"run_id": "run", "project": manifest["project"], "batch": batch}
        result = {"version": 1, "run_id": "run", "project": "P-DRA-2", "batch_id": "first",
                  "branch": batch["branch"], "status": "partial", "tickets": [
                      {"id": "DRA-1", "status": "delivered", "commit": "a" * 40},
                      {"id": "DRA-2", "status": "product_question", "question": "Which behavior?",
                       "triage_issue": {"id": "DRA-20", "project": "P-DRA-2", "cause": "product-question"},
                       "state": "Backlog"}],
                  "delivery": {"pr": "https://github.com/example/repo/pull/1", "state": "open",
                               "ci": "green", "head": "a" * 40, "base": "main"},
                  "checks": "passed", "reviews": {"spec": "approved", "correctness": "approved"},
                  "correction_passes": 0}
        self.assertEqual(validate_result(result, request, "main")["status"], "partial")
        complete = copy.deepcopy(result)
        complete["status"] = "complete"
        complete["tickets"][1] = {"id": "DRA-2", "status": "delivered", "commit": "b" * 40}
        self.assertEqual(validate_result(complete, request, "main")["status"], "complete")
        for status in ("blocked", "failed"):
            stopped = {key: item for key, item in result.items() if key not in
                       ("delivery", "checks", "reviews", "correction_passes")}
            stopped["status"] = status
            stopped["tickets"] = [{"id": ticket["id"], "status": status, "reason": "Cannot proceed"}
                                  for ticket in result["tickets"]]
            self.assertEqual(validate_result(stopped, request, "main")["status"], status)
        contradictory = copy.deepcopy(result)
        contradictory["tickets"][0].update(state="Backlog", question="Unexpected?", triage_issue=True)
        with self.assertRaises(ContractError):
            validate_result(contradictory, request, "main")
        malformed = copy.deepcopy(result)
        malformed["delivery"]["pr"] = "https://["
        with self.assertRaises(ContractError):
            validate_result(malformed, request, "main")
        for key, value in (("project", "other"), ("branch", "main"), ("status", "complete"),
                           ("correction_passes", 3), ("delivery", None)):
            with self.subTest(key=key), self.assertRaises(ContractError):
                validate_result(result | {key: value}, request, "main")


if __name__ == "__main__":
    unittest.main()
