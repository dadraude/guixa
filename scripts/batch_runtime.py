"""Isolated local lifecycle. External facts remain the configured verifier's trust boundary."""

import fcntl
import json
import os
import signal
import subprocess
import threading
import time
import uuid
from pathlib import Path

from scripts.batch_contract import ContractError, identity, keys, require, validate_result


def read_json(path):
    path = Path(path)
    require(path.is_file() and not path.is_symlink() and path.stat().st_size <= 1024 * 1024,
            f"missing, symlinked or oversized JSON file: {path}")
    try:
        return json.loads(path.read_text())
    except (ValueError, UnicodeError) as error:
        raise ContractError(f"invalid JSON in {path}: {error}") from error


def write_json(path, value):
    Path(path).write_text(json.dumps(value, indent=2) + "\n")


class BatchRuntime:
    def __init__(self, manifest):
        self.manifest = manifest
        self.repo = Path(manifest["repository"])
        self.cancelled = threading.Event()
        self.locks = []
        self.children = set()
        self.mutex = threading.Lock()
        self.run_id = uuid.uuid4().hex
        self.state = self.repo / ".batch-runs" / self.run_id
        self.worktree_root = Path(manifest["worktree_root"])
        self.cross_cutting = any(batch["cross_cutting"] for batch in manifest["batches"])
        try:
            self.validate_git()
            self.acquire_locks()
            self.state.mkdir(parents=True, mode=0o700)
            self.worktree_root.mkdir(parents=True, exist_ok=True)
            write_json(self.state / "manifest.json", manifest)
        except BaseException:
            self.close()
            raise

    def git(self, *args, cwd=None):
        result = subprocess.run(["git", *args], cwd=cwd or self.repo, text=True,
                                capture_output=True, timeout=60)
        require(result.returncode == 0, f"git {' '.join(args)} failed: {result.stderr.strip()}")
        return result.stdout.strip()

    def validate_git(self):
        require(Path(self.git("rev-parse", "--show-toplevel")).resolve() == self.repo,
                "repository must be a Git workspace root")
        self.common = Path(self.git("rev-parse", "--path-format=absolute", "--git-common-dir"))
        self.git("rev-parse", "--verify", self.manifest["base"] + "^{commit}")
        for batch in self.manifest["batches"]:
            self.git("check-ref-format", "--branch", batch["branch"])
            require(not self.git("branch", "--list", batch["branch"]), "batch branch already exists")
            path = self.worktree_path(batch)
            require(not path.exists(), "batch worktree path already exists")
        for path in (self.repo / ".batch-runs", self.worktree_root):
            require(not path.is_symlink(), "state/worktree directories cannot be symlinks")

    def lock(self, path, exclusive):
        handle = path.open("a+")
        try:
            fcntl.flock(handle, (fcntl.LOCK_EX if exclusive else fcntl.LOCK_SH) | fcntl.LOCK_NB)
        except BlockingIOError as error:
            handle.close()
            raise ContractError(f"active repository batch conflicts with lock {path}") from error
        self.locks.append(handle)

    def acquire_locks(self):
        lock_root = self.common / "batch-locks"
        lock_root.mkdir(exist_ok=True)
        self.lock(lock_root / "repository.lock", self.cross_cutting)
        for batch in sorted(self.manifest["batches"], key=lambda item: item["database"]):
            self.lock(lock_root / (batch["database"] + ".lock"), True)

    def worktree_path(self, batch):
        return self.worktree_root / f"{self.run_id}-{batch['id']}"

    def terminate(self, process):
        try:
            os.killpg(process.pid, signal.SIGTERM)
        except ProcessLookupError:
            pass
        try:
            process.wait(timeout=2)
        except subprocess.TimeoutExpired:
            try:
                os.killpg(process.pid, signal.SIGKILL)
            except ProcessLookupError:
                pass
            process.wait()
        # Descendants must not outlive a fresh batch, even if their parent exited first.
        try:
            os.killpg(process.pid, signal.SIGKILL)
        except ProcessLookupError:
            pass

    def invoke(self, argv, request, directory, name, cwd, database=None):
        directory.mkdir(parents=True, exist_ok=True, mode=0o700)
        request_path, result_path = directory / (name + "-request.json"), directory / (name + ".json")
        write_json(request_path, request)
        environment = os.environ.copy()
        if database:
            environment["DB_DATABASE"] = database
        with (directory / (name + ".log")).open("w") as log:
            with self.mutex:
                require(not self.cancelled.is_set(), "run is cancelled")
                process = subprocess.Popen([*argv, "--request", str(request_path), "--result", str(result_path)],
                                           cwd=cwd, env=environment, stdout=log, stderr=subprocess.STDOUT,
                                           start_new_session=True)
                self.children.add(process)
            deadline = time.monotonic() + self.manifest["timeout_seconds"]
            try:
                while process.poll() is None:
                    if self.cancelled.wait(0.05):
                        raise InterruptedError("batch run interrupted")
                    require(time.monotonic() < deadline, f"{name} timed out; see {log.name}")
                require(process.returncode == 0, f"{name} exited {process.returncode}; see {log.name}")
            finally:
                self.terminate(process)
                with self.mutex:
                    self.children.discard(process)
        return read_json(result_path)

    def preflight(self):
        request = {"version": 1, "kind": "repository_preflight", "run_id": self.run_id,
                   "project": self.manifest["project"], "repository": str(self.repo),
                   "cross_cutting": self.cross_cutting}
        receipt = self.invoke(self.manifest["verifier"], request, self.state, "preflight", self.repo)
        keys(receipt, ("version", "run_id", "project", "repository", "active_cross_cutting", "open_prs"))
        require(all(receipt[key] == request[key] for key in ("version", "run_id", "project", "repository")),
                "preflight identity mismatch")
        require(type(receipt["active_cross_cutting"]) is bool and isinstance(receipt["open_prs"], list),
                "invalid repository preflight receipt")
        require(not receipt["active_cross_cutting"], "repository has an open cross-cutting PR")
        require(not self.cross_cutting or not receipt["open_prs"], "cross-cutting batch needs no open PRs")

    def execute(self, batch, base, parents=()):
        require(not self.cancelled.is_set(), "run is cancelled")
        directory, worktree = self.state / batch["id"], self.worktree_path(batch)
        directory.mkdir(mode=0o700)
        # Record the recovery location before a Git operation can partially create it.
        write_json(directory / "recovery.json", {"worktree": str(worktree), "branch": batch["branch"],
                                                 "database": batch["database"], "cleanup": "manual"})
        self.git("worktree", "add", "-b", batch["branch"], str(worktree), base)
        for parent in parents:
            self.git("merge", "--no-edit", parent, cwd=worktree)
        starting_head = self.git("rev-parse", "HEAD", cwd=worktree)
        request = {"version": 1, "kind": "execute_batch", "run_id": self.run_id,
                   "project": self.manifest["project"], "repository": str(self.repo),
                   "batch": batch, "base": base, "parents": list(parents),
                   "worktree": str(worktree), "models": self.manifest["models"],
                   "protocol": str(Path(__file__).resolve().parent.parent / "docs/agents/batch-execution.md")}
        write_json(directory / "request.json", request)
        result = self.invoke(self.manifest["adapter"], request, directory, "result", worktree, batch["database"])
        validate_result(result, request, base)
        delivered = [ticket["id"] for ticket in result["tickets"] if ticket["status"] == "delivered"]
        if delivered:
            require(self.git("branch", "--show-current", cwd=worktree) == batch["branch"], "branch changed")
            head = self.git("rev-parse", "HEAD", cwd=worktree)
            require(head == result["delivery"]["head"], "reported delivery HEAD differs from worktree")
            require(not self.git("status", "--porcelain", cwd=worktree), "delivered worktree is dirty")
            commits = [ticket["commit"] for ticket in result["tickets"] if ticket["status"] == "delivered"]
            require(len(set(commits)) == len(commits) and starting_head not in commits,
                    "each delivered ticket needs its own new commit")
            for ticket in result["tickets"]:
                if ticket["status"] == "delivered":
                    self.git("merge-base", "--is-ancestor", starting_head, ticket["commit"], cwd=worktree)
                    self.git("merge-base", "--is-ancestor", ticket["commit"], head, cwd=worktree)
            verification = {"version": 1, "kind": "verify_delivery", "run_id": self.run_id,
                            "project": request["project"], "batch_id": batch["id"], "branch": batch["branch"],
                            "tickets": delivered, "delivery": result["delivery"], "worktree": str(worktree)}
            receipt = self.invoke(self.manifest["verifier"], verification, directory, "receipt", worktree)
            keys(receipt, ("version", "run_id", "project", "batch_id", "branch", "tickets", "delivery", "ready"))
            identity(receipt, request)
            require(type(receipt["version"]) is int and receipt["version"] == 1 and receipt["ready"] is True
                    and receipt["tickets"] == delivered and receipt["delivery"] == result["delivery"],
                    "delivery verification rejected or mismatched")
        result = result | {"worktree": str(worktree)}
        write_json(directory / "outcome.json", result)
        return result

    def close(self):
        with self.mutex:
            self.cancelled.set()
            for process in list(self.children):
                self.terminate(process)
            for handle in self.locks:
                handle.close()
            self.locks.clear()
