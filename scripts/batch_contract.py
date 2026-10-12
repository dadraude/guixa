"""Versioned, provider-neutral input and output boundary for automatic batches."""

import copy
import os
import re
from pathlib import Path
from urllib.parse import urlparse


class ContractError(ValueError):
    pass


def require(condition, message):
    if not condition:
        raise ContractError(message)


def text(value):
    return isinstance(value, str) and bool(value.strip()) and "\x00" not in value


def keys(value, required, optional=()):
    require(isinstance(value, dict), "expected an object")
    require(set(required) <= value.keys(), f"missing fields: {set(required) - value.keys()}")
    require(value.keys() <= set(required) | set(optional), "unknown fields")


def integer(value, minimum, maximum):
    return type(value) is int and minimum <= value <= maximum


def ref(value):
    return (text(value) and re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9/_-]*", value)
            and "//" not in value and not value.endswith("/"))


def command(value):
    require(isinstance(value, list) and value and all(text(arg) for arg in value),
            "commands must be nonempty argv arrays")
    executable = Path(value[0])
    require(executable.is_absolute() and executable.is_file() and os.access(executable, os.X_OK),
            "command executable must be an absolute executable file")


def acyclic(edges):
    visited, active = set(), set()

    def visit(node):
        require(node not in active, "dependency cycle")
        if node in visited:
            return
        active.add(node)
        for parent in edges[node]:
            require(parent in edges, f"unknown dependency: {parent}")
            visit(parent)
        active.remove(node)
        visited.add(node)

    for node in edges:
        visit(node)


def validate_manifest(value):
    keys(value, ("version", "project", "repository", "worktree_root", "base", "adapter",
                 "verifier", "models", "batches"), ("max_parallel", "timeout_seconds"))
    result = copy.deepcopy(value)
    require(type(result["version"]) is int and result["version"] == 1, "unsupported version")
    require(text(result["project"]), "explicit project is required")
    require(ref(result["base"]), "invalid base ref")
    for name in ("repository", "worktree_root"):
        require(text(result[name]) and Path(result[name]).is_absolute(), f"{name} must be absolute")
        result[name] = str(Path(result[name]).resolve())
    repo, worktrees = Path(result["repository"]), Path(result["worktree_root"])
    require(repo.is_dir(), "repository directory is missing")
    require(worktrees.is_relative_to(Path.home().resolve()), "worktrees must be under HOME")
    require(not worktrees.is_relative_to(repo) and not repo.is_relative_to(worktrees),
            "worktree root must be separate from repository")
    command(result["adapter"])
    command(result["verifier"])
    roles = ("orchestrator", "implementer", "spec_reviewer", "correctness_reviewer")
    keys(result["models"], roles)
    require(all(text(model) for model in result["models"].values()), "all model roles are required")
    result.setdefault("max_parallel", 3)
    result.setdefault("timeout_seconds", 3600)
    require(integer(result["max_parallel"], 1, 16), "max_parallel must be 1..16")
    require(integer(result["timeout_seconds"], 1, 86400), "timeout_seconds must be 1..86400")
    batches = result["batches"]
    require(isinstance(batches, list) and 1 <= len(batches) <= 100, "expected 1..100 batches")
    owners, ticket_edges, batch_edges, branches = {}, {}, {}, set()
    explicit_parents = {}
    for batch in batches:
        keys(batch, ("id", "branch", "tickets"), ("depends_on", "cross_cutting"))
        name = batch["id"]
        require(text(name) and re.fullmatch(r"[a-z][a-z0-9-]{0,63}", name), "invalid batch id")
        require(name not in batch_edges, "duplicate batch id")
        branch = batch["branch"]
        require(ref(branch) and "/dra-" in branch and branch not in branches, "invalid/duplicate branch")
        branches.add(branch)
        batch.setdefault("depends_on", [])
        batch.setdefault("cross_cutting", False)
        require(type(batch["cross_cutting"]) is bool, "cross_cutting must be boolean")
        require(isinstance(batch["depends_on"], list) and all(text(x) for x in batch["depends_on"]),
                "invalid batch dependencies")
        batch_edges[name] = set(batch["depends_on"])
        explicit_parents[name] = set(batch["depends_on"])
        tickets = batch["tickets"]
        require(isinstance(tickets, list) and 1 <= len(tickets) <= 4, "expected 1..4 tickets per batch")
        for ticket in tickets:
            keys(ticket, ("id",), ("depends_on", "context"))
            identifier = ticket["id"]
            require(text(identifier) and re.fullmatch(r"DRA-[1-9][0-9]*", identifier), "invalid ticket id")
            require(identifier not in owners, "duplicate ticket")
            ticket.setdefault("depends_on", [])
            require(isinstance(ticket["depends_on"], list) and all(text(x) for x in ticket["depends_on"]),
                    "invalid ticket dependencies")
            require("context" not in ticket or text(ticket["context"]), "invalid ticket context")
            owners[identifier] = name
            ticket_edges[identifier] = set(ticket["depends_on"])
        first = tickets[0]["id"].split("-")[1]
        require(f"/dra-{first}-" in branch, "branch must identify the first ticket")
        batch["database"] = f"testing_dra{first}"
    acyclic(ticket_edges)
    for batch in batches:
        positions = {ticket["id"]: index for index, ticket in enumerate(batch["tickets"])}
        for ticket in batch["tickets"]:
            for parent in ticket["depends_on"]:
                if owners[parent] == batch["id"]:
                    require(positions[parent] < positions[ticket["id"]], "tickets must be in dependency order")
                else:
                    batch_edges[batch["id"]].add(owners[parent])
        batch["depends_on"] = sorted(batch_edges[batch["id"]])
        required = {parent for ticket in batch["tickets"] for parent in ticket["depends_on"]
                    if owners[parent] != batch["id"]}
        required.update(ticket for ticket, owner in owners.items() if owner in explicit_parents[batch["id"]])
        batch["required_tickets"] = sorted(required)
    acyclic(batch_edges)
    require(not any(batch["cross_cutting"] for batch in batches) or len(batches) == 1,
            "cross-cutting batch must run alone")
    return result


def identity(value, request):
    expected = {"run_id": request["run_id"], "project": request["project"],
                "batch_id": request["batch"]["id"], "branch": request["batch"]["branch"]}
    require(all(value.get(key) == item for key, item in expected.items()), "result identity mismatch")


def sha(value):
    return isinstance(value, str) and re.fullmatch(r"[0-9a-f]{40}", value)


def validate_result(value, request, base):
    keys(value, ("version", "run_id", "project", "batch_id", "branch", "status", "tickets"),
         ("delivery", "checks", "reviews", "correction_passes"))
    require(type(value["version"]) is int and value["version"] == 1, "unsupported result version")
    identity(value, request)
    tickets = value["tickets"]
    planned = request["batch"]["tickets"]
    require(isinstance(tickets, list) and len(tickets) == len(planned), "missing ticket outcomes")
    require([x.get("id") for x in tickets if isinstance(x, dict)] == [x["id"] for x in planned],
            "ticket outcomes must match request order")
    delivered, failed = set(), False
    for ticket, definition in zip(tickets, planned):
        keys(ticket, ("id", "status"), ("commit", "question", "triage_issue", "state", "reason"))
        status = ticket["status"]
        require(status in ("delivered", "product_question", "blocked", "failed"), "invalid ticket outcome")
        if status == "delivered":
            keys(ticket, ("id", "status", "commit"))
            require(sha(ticket.get("commit")), "delivered ticket needs a commit")
            local_parents = set(definition["depends_on"]) & {x["id"] for x in planned}
            require(local_parents <= delivered, "delivered ticket has an undelivered dependency")
            delivered.add(ticket["id"])
        elif status == "product_question":
            keys(ticket, ("id", "status", "question", "triage_issue", "state"))
            require(text(ticket.get("question")) and ticket.get("state") == "Backlog",
                    "product question needs a question and Backlog state")
            triage = ticket.get("triage_issue")
            keys(triage, ("id", "project", "cause"))
            require(text(triage["id"]) and triage["project"] == request["project"]
                    and triage["cause"] == "product-question", "invalid product-question triage receipt")
        else:
            keys(ticket, ("id", "status", "reason"))
            require(text(ticket.get("reason")), "blocked/failed ticket needs a reason")
            failed |= status == "failed"
    expected = ("complete" if len(delivered) == len(planned) else "partial" if delivered
                else "failed" if failed else "blocked")
    require(value["status"] == expected, "inconsistent batch status")
    if delivered:
        delivery = value.get("delivery")
        keys(delivery, ("pr", "state", "ci", "head", "base"))
        require(text(delivery["pr"]), "missing PR URL")
        try:
            url = urlparse(delivery["pr"])
        except ValueError as error:
            raise ContractError("invalid PR URL") from error
        require(url.scheme == "https" and url.hostname and url.path, "invalid PR URL")
        require(delivery["state"] == "open" and delivery["ci"] in ("green", "not_required")
                and sha(delivery["head"]) and delivery["base"] == base, "delivery is not ready")
        require(value.get("checks") == "passed", "local checks must pass")
        require(value.get("reviews") == {"spec": "approved", "correctness": "approved"},
                "both independent reviews must approve")
        require(integer(value.get("correction_passes"), 0, 2), "at most two correction passes")
    else:
        require(value.get("delivery") is None, "undelivered batch cannot advertise a PR")
        require(not any(key in value for key in ("checks", "reviews", "correction_passes")),
                "undelivered batch cannot advertise delivery checks")
    return value
