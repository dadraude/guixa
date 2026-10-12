"""Executable boundary tests; fake Codex performs no AI or network calls."""

import json
import os
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path


ADAPTER = Path(__file__).resolve().parents[1] / "adapters/codex_adapter.py"


class CodexAdapterTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name).resolve()
        self.cli = self.root / "fake-codex"
        self.cli.write_text("#!" + sys.executable + "\n" + r'''import json, os, pathlib, sys
args = sys.argv[1:]
prompt = sys.stdin.read()
pathlib.Path(os.environ["CAPTURE"]).write_text(json.dumps({"args": args, "prompt": prompt, "cwd": os.getcwd()}))
print("fake observable event", flush=True)
mode = os.environ.get("MODE", "valid")
if mode == "nonzero": sys.exit(7)
out = pathlib.Path(args[args.index("--output-last-message") + 1])
value = json.loads(os.environ["REPLY"])
if mode == "missing": sys.exit(0)
if mode == "symlink": out.symlink_to(os.environ["TARGET"]); sys.exit(0)
if mode == "identity": value["run_id"] = "another-run"
if mode == "delivery": value["tickets"][0] = {"id": "DRA-144", "status": "delivered", "commit": "a"*40}; value["status"] = "complete"
out.write_text("oops" if mode == "malformed" else "x"*(1024*1024+1) if mode == "oversized" else json.dumps(value))
''')
        self.cli.chmod(0o700)
        self.protocol = self.root / "protocol.md"
        self.protocol.write_text("The provider-neutral protocol is mandatory.")
        self.request = {
            "version": 1, "kind": "execute_batch", "run_id": "run-123", "project": "workflow",
            "repository": str(self.root), "worktree": str(self.root), "base": "main", "start_ref": "b"*40, "parents": [],
            "batch": {"id": "codex", "branch": "feature/dra-144-adapter", "database": "testing_dra144",
                      "tickets": [{"id": "DRA-144", "depends_on": [], "context": "Implement adapter."}]},
            "models": {"orchestrator": "gpt-6.1-sol", "implementer": "gpt-6.1-sol",
                       "spec_reviewer": "gpt-6-astra", "correctness_reviewer": "gpt-6-astra"},
            "protocol": str(self.protocol),
        }
        self.reply = {"version": 1, "run_id": "run-123", "project": "workflow", "batch_id": "codex",
                      "branch": "feature/dra-144-adapter", "status": "blocked",
                      "tickets": [{"id": "DRA-144", "status": "blocked", "reason": "Linear unavailable"}]}
        self.input, self.output = self.root / "request.json", self.root / "result.json"
        self.capture = self.root / "capture.json"

    def invoke(self, mode="valid", extra=()):
        self.input.write_text(json.dumps(self.request))
        env = os.environ | {"MODE": mode, "CAPTURE": str(self.capture), "REPLY": json.dumps(self.reply),
                           "TARGET": str(self.input)}
        return subprocess.run([sys.executable, str(ADAPTER), "--codex", str(self.cli), "--sandbox",
                               "workspace-write", "--request", str(self.input), "--result", str(self.output),
                               *(() if "--profile" in extra else ("--use-user-config",)), *extra], cwd=self.root, env=env, capture_output=True, text=True)

    def test_valid_blocked_result_and_explicit_fresh_cli(self):
        result = self.invoke(extra=("--profile", "batch"))
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(json.loads(self.output.read_text()), self.reply)
        self.assertIn("fake observable event", result.stdout)
        captured = json.loads(self.capture.read_text())
        args = captured["args"]
        self.assertEqual(args[0], "exec")
        self.assertEqual(args[args.index("--model") + 1], "gpt-6.1-sol")
        self.assertEqual(args[args.index("--sandbox") + 1], "workspace-write")
        self.assertEqual(args[args.index("--profile") + 1], "batch")
        self.assertIn('approval_policy="never"', args)
        self.assertIn("sandbox_workspace_write.network_access=false", args)
        self.assertIn("--ephemeral", args)
        self.assertIn("--json", args)
        self.assertEqual(args[-1], "-")
        self.assertEqual(captured["cwd"], str(self.root))
        self.assertIn("gpt-6-astra", captured["prompt"])
        self.assertIn("two independent", captured["prompt"])
        self.assertIn("at most two correction", captured["prompt"])
        self.assertIn(self.protocol.read_text(), captured["prompt"])
        self.assertIn("combined batch diff", captured["prompt"])
        self.assertEqual(self.output.stat().st_mode & 0o777, 0o600)

    def test_bad_cli_outputs_never_publish_result(self):
        for mode in ("nonzero", "missing", "malformed", "oversized", "identity", "delivery", "symlink"):
            with self.subTest(mode=mode):
                result = self.invoke(mode)
                self.assertNotEqual(result.returncode, 0)
                self.assertFalse(self.output.exists())

    def test_valid_delivery_requires_complete_contract(self):
        self.reply.update(status="complete", checks="passed", reviews={"spec": "approved", "correctness": "approved"},
                          correction_passes=2, delivery={"pr": "https://github.com/example/repo/pull/1",
                                                       "state": "open", "ci": "green", "head": "a"*40, "base": "main"})
        self.reply["tickets"] = [{"id": "DRA-144", "status": "delivered", "commit": "a"*40}]
        self.assertEqual(self.invoke().returncode, 0)
        self.assertEqual(json.loads(self.output.read_text()), self.reply)

    def test_runtime_pinned_request_is_accepted(self):
        self.request.update(base="feature/dra-12-parent", start_ref="b"*40, parents=["c"*40])
        self.request["batch"].update(depends_on=["parent"], cross_cutting=False, required_tickets=["DRA-12"])
        result = self.invoke()
        self.assertEqual(result.returncode, 0, result.stderr)
        prompt = json.loads(self.capture.read_text())["prompt"]
        self.assertIn('"start_ref": "' + "b"*40 + '"', prompt)
        self.assertIn("pinned merged baseline", prompt)
        self.assertIn("never merge unchecked mutable", prompt)

    def test_missing_or_mutable_starting_refs_prevent_cli_launch(self):
        for value in (None, "main", "b"*39, "B"*40):
            with self.subTest(start_ref=value):
                self.output.unlink(missing_ok=True)
                self.capture.unlink(missing_ok=True)
                if value is None:
                    self.request.pop("start_ref", None)
                else:
                    self.request["start_ref"] = value
                self.assertNotEqual(self.invoke().returncode, 0)
                self.assertFalse(self.capture.exists())
        self.output.unlink(missing_ok=True)
        self.capture.unlink(missing_ok=True)
        self.request["start_ref"] = "b"*40
        self.request["parents"] = ["feature/dra-12-parent"]
        self.assertNotEqual(self.invoke().returncode, 0)
        self.assertFalse(self.capture.exists())

    def test_invalid_input_prevents_cli_launch(self):
        for field, value in (("version", True), ("kind", "verify_delivery"), ("models", {}),
                             ("worktree", str(self.root / "missing")), ("protocol", str(self.root / "missing"))):
            previous = self.request[field]
            with self.subTest(field=field):
                self.request[field] = value
                self.assertNotEqual(self.invoke().returncode, 0)
                self.assertFalse(self.capture.exists())
                self.assertFalse(self.output.exists())
            self.request[field] = previous

    def test_no_permission_bypass_or_executable_fallback(self):
        self.assertNotEqual(self.invoke(extra=("--sandbox", "danger-full-access")).returncode, 0)
        self.assertNotEqual(self.invoke(extra=("--codex", "codex")).returncode, 0)
        self.assertNotEqual(self.invoke(extra=("--profile", "")).returncode, 0)
        self.assertFalse(self.capture.exists())

    def test_untrusted_request_file_is_rejected(self):
        for mode in ("malformed", "oversized", "symlink"):
            with self.subTest(mode=mode):
                target = self.root / (mode + ".json")
                if mode == "symlink":
                    self.input.write_text(json.dumps(self.request))
                    target.symlink_to(self.input)
                else:
                    target.write_text("bad" if mode == "malformed" else "x"*(1024*1024+1))
                result = self.invoke(extra=("--request", str(target)))
                self.assertNotEqual(result.returncode, 0)
                self.assertFalse(self.capture.exists())
                self.assertFalse(self.output.exists())

    def test_existing_result_is_not_overwritten(self):
        self.output.write_text("existing")
        self.assertNotEqual(self.invoke().returncode, 0)
        self.assertEqual(self.output.read_text(), "existing")
        self.assertFalse(self.capture.exists())


if __name__ == "__main__":
    unittest.main()
