"""Bounded DAG scheduler for fresh, provider-neutral batch orchestrators."""

import argparse
import concurrent.futures
import json
import signal
import subprocess
from pathlib import Path
import sys

from scripts.batch_contract import ContractError, identity, keys, require, validate_manifest
from scripts.batch_runtime import BatchRuntime, read_json, write_json


def reverify(runtime, parent, child):
    result = runtime.outcomes[parent["id"]]
    delivered = [ticket["id"] for ticket in result["tickets"] if ticket["status"] == "delivered"]
    request = {"version": 1, "kind": "verify_delivery", "run_id": runtime.run_id,
               "project": runtime.manifest["project"], "batch_id": parent["id"], "branch": parent["branch"],
               "tickets": delivered, "delivery": result["delivery"], "worktree": result["worktree"]}
    receipt = runtime.invoke(runtime.manifest["verifier"], request, runtime.state / parent["id"],
                             "before-" + child["id"], result["worktree"])
    keys(receipt, ("version", "run_id", "project", "batch_id", "branch", "tickets", "delivery", "ready"))
    identity(receipt, {"run_id": runtime.run_id, "project": runtime.manifest["project"], "batch": parent})
    require(type(receipt["version"]) is int and receipt["version"] == 1 and receipt.get("ready") is True
            and receipt.get("tickets") == delivered and receipt.get("delivery") == result["delivery"],
            "parent delivery no longer verifies")


def schedule(runtime):
    batches = {batch["id"]: batch for batch in runtime.manifest["batches"]}
    pending, running, outcomes = dict(batches), {}, {}
    runtime.outcomes = outcomes
    with concurrent.futures.ThreadPoolExecutor(max_workers=runtime.manifest["max_parallel"]) as pool:
        while pending or running:
            if runtime.cancelled.is_set():
                for name in pending:
                    outcomes[name] = {"status": "blocked", "reason": "run interrupted"}
                pending.clear()
            pending_before = len(pending)
            for name, batch in list(pending.items()):
                if runtime.cancelled.is_set():
                    break
                if len(running) >= runtime.manifest["max_parallel"]:
                    break
                if not all(parent in outcomes for parent in batch["depends_on"]):
                    continue
                delivered = {ticket["id"] for parent in batch["depends_on"]
                             for ticket in outcomes[parent].get("tickets", []) if ticket["status"] == "delivered"}
                if not set(batch["required_tickets"]) <= delivered:
                    outcomes[name] = {"status": "blocked", "reason": "dependency did not deliver required tickets"}
                    del pending[name]
                    continue
                try:
                    for parent in batch["depends_on"]:
                        reverify(runtime, batches[parent], batch)
                    refs = [batches[parent]["branch"] for parent in batch["depends_on"]]
                    base = refs[0] if refs else runtime.manifest["base"]
                    heads = [outcomes[parent]["delivery"]["head"] for parent in batch["depends_on"]]
                    start_ref = heads[0] if heads else runtime.base_head
                    running[pool.submit(runtime.execute, batch, base, heads[1:], start_ref)] = name
                except (ContractError, OSError, InterruptedError) as error:
                    outcomes[name] = {"status": "failed", "reason": str(error)}
                del pending[name]
            if not running:
                # Failure propagation may unblock earlier entries on the next pass.
                if pending and len(pending) < pending_before:
                    continue
                require(not pending, "scheduler has no ready batch")
                break
            finished, _ = concurrent.futures.wait(running, timeout=0.1,
                                                   return_when=concurrent.futures.FIRST_COMPLETED)
            for future in finished:
                name = running.pop(future)
                try:
                    outcomes[name] = future.result()
                except Exception as error:
                    outcomes[name] = {"status": "failed", "reason": str(error),
                                      "worktree": str(runtime.worktree_path(batches[name]))}
            write_json(runtime.state / "progress.json", outcomes)
    status = ("cancelled" if runtime.cancelled.is_set() else "complete"
              if all(outcome["status"] == "complete" for outcome in outcomes.values()) else "incomplete")
    summary = {"version": 1, "run_id": runtime.run_id, "project": runtime.manifest["project"],
               "status": status, "batches": outcomes, "cleanup": "coordinator reconciliation required"}
    write_json(runtime.state / "summary.json", summary)
    return summary


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("manifest", help="version 1 JSON manifest with explicit project and executables")
    parser.add_argument("--validate-only", action="store_true", help="check schema and Git without starting adapters")
    args = parser.parse_args()
    runtime = None
    handlers = {}
    try:
        manifest = validate_manifest(read_json(args.manifest))
        if args.validate_only:
            # Validation does not create state, take locks or execute either configured wrapper.
            probe = BatchRuntime.__new__(BatchRuntime)
            probe.manifest, probe.repo = manifest, Path(manifest["repository"])
            probe.run_id, probe.worktree_root = "validation", Path(manifest["worktree_root"])
            probe.validate_git()
            print("Manifest and Git paths valid; provider permissions and live receipts remain unchecked.")
            return 0
        runtime = BatchRuntime(manifest)
        for event in (signal.SIGINT, signal.SIGTERM):
            handlers[event] = signal.signal(event, lambda *_: runtime.cancelled.set())
        runtime.preflight()
        summary = schedule(runtime)
        print(json.dumps({"status": summary["status"], "state": str(runtime.state),
                          "cleanup": summary["cleanup"]}))
        return 130 if summary["status"] == "cancelled" else 0 if summary["status"] == "complete" else 1
    except (ContractError, OSError, ValueError, subprocess.SubprocessError, InterruptedError, KeyboardInterrupt) as error:
        print(f"Batch run failed: {error}", file=sys.stderr)
        if runtime:
            write_json(runtime.state / "summary.json", {"status": "failed", "reason": str(error),
                       "cleanup": "coordinator reconciliation required"})
            print(f"Recovery state: {runtime.state}", file=sys.stderr)
        return 130 if runtime and runtime.cancelled.is_set() else 2
    finally:
        if runtime:
            runtime.close()
        for event, handler in handlers.items():
            signal.signal(event, handler)


if __name__ == "__main__":
    sys.exit(main())
