#!/usr/bin/env python3
"""Read-only GitHub receipts. Unknown repository/CI/classification facts fail closed."""

import argparse
import datetime
import json
import re
import shutil
import subprocess
import sys
from pathlib import Path


class VerificationError(ValueError):
    pass


def require(condition, reason):
    if not condition:
        raise VerificationError(reason)


def command(argv, cwd):
    result = subprocess.run(argv, cwd=cwd, capture_output=True, text=True, timeout=60)
    require(result.returncode == 0, f"read-only {argv[0]} command failed: {result.stderr.strip()}")
    return result.stdout.strip()


def matching(path, pattern):
    if pattern == "**/*.md":
        return path.endswith(".md")
    if pattern.endswith("/**") and re.fullmatch(r"[A-Za-z0-9_./-]+", pattern[:-3]):
        return path.startswith(pattern[:-2])
    require(re.fullmatch(r"[A-Za-z0-9_./-]+", pattern), "unsupported workflow glob")
    return path == pattern


def workflow_filter(source):
    lines = source.splitlines()
    event_keys = [line for line in lines if re.match(r"^(on\s*:|[\"\']on[\"\']\s*:)", line)]
    require(event_keys == ["on:"], "unsupported workflow event syntax")
    events = []
    for line in lines[lines.index("on:") + 1:]:
        if not line.strip() or line.lstrip().startswith("#"):
            continue
        if not line.startswith(" "):
            break
        events.append(line)
    headers = [line for line in events if not line.startswith("    ")]
    require(all(re.fullmatch(r"  (push|pull_request|workflow_dispatch):", line) for line in headers)
            and len(headers) == len(set(headers)), "unsupported or duplicate workflow event")
    require(events.count("  pull_request:") == 1, "workflow has no supported PR filter")
    start = events.index("  pull_request:") + 1
    block = []
    for line in events[start:]:
        if not line.startswith("    "):
            break
        block.append(line)
    require(block and block[0] in ("    paths:", "    paths-ignore:"), "unsupported PR filter")
    patterns = []
    for line in block[1:]:
        match = re.fullmatch(r"      - (['\"])([^'\"]+)\1", line)
        require(match, "unsupported PR path list")
        pattern = match[2]
        matching("validation", pattern)
        patterns.append(pattern)
    require(patterns, "empty PR path filter")
    return block[0].strip(), patterns


class GithubVerifier:
    def __init__(self, args, request):
        self.args, self.request = args, request
        require(re.fullmatch(r"[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+", args.repository), "invalid owner/repository")
        self.prefix = "repos/" + args.repository + "/"
        self.url = "https://github.com/" + args.repository + "/pull/"
        location = request.get("worktree", request.get("repository"))
        require(isinstance(location, str) and Path(location).is_absolute(), "explicit absolute Git workspace required")
        self.cwd = Path(location).resolve()
        require(request.get("version") == 1 and type(request["version"]) is int, "invalid request version")
        require(all(isinstance(request.get(key), str) and request[key] for key in ("run_id", "project")),
                "request needs run/project identity")
        require(self.git("rev-parse", "--show-toplevel") == str(self.cwd), "not a Git workspace root")
        origin = self.git("remote", "get-url", "origin")
        allowed = (f"git@github.com:{args.repository}.git", f"https://github.com/{args.repository}.git",
                   f"https://github.com/{args.repository}")
        require(origin in allowed, "origin does not identify the configured GitHub repository")

    def git(self, *args):
        return command(["git", *args], self.cwd)

    def api(self, path, paginated=False):
        argv = [self.args.gh, "api", self.prefix + path, "--method", "GET", "--hostname", "github.com"]
        if paginated:
            argv += ["--paginate", "--slurp"]
        return json.loads(command(argv, self.cwd))

    def items(self, path, field=None):
        pages = self.api(path, True)
        require(isinstance(pages, list), "invalid paginated response")
        result = []
        for page in pages:
            values = page[field] if field else page
            require(isinstance(values, list), "invalid API page")
            result.extend(values)
        return result

    def preflight(self):
        classifications = {}
        for sweep, values in ((False, self.args.ordinary_pr), (True, self.args.cross_cutting_pr)):
            for value in values:
                match = re.fullmatch(r"([1-9][0-9]*)@([0-9a-f]{40})", value)
                require(match and int(match[1]) not in classifications, "invalid/duplicate PR classification")
                classifications[int(match[1])] = (match[2], sweep)
        prs = self.items("pulls?state=open&per_page=100")
        active = False
        for pr in prs:
            require(pr["state"] == "open" and pr["html_url"] == self.url + str(pr["number"]), "PR identity mismatch")
            classification = classifications.get(pr["number"])
            require(classification and classification[0] == pr["head"]["sha"], "unknown or changed PR classification")
            active |= classification[1]
        return {key: self.request[key] for key in ("version", "run_id", "project", "repository")} | {
            "active_cross_cutting": active, "open_prs": [pr["html_url"] for pr in prs]}

    def no_checks(self, pr):
        updated = datetime.datetime.fromisoformat(pr["updated_at"].replace("Z", "+00:00"))
        age = (datetime.datetime.now(datetime.timezone.utc) - updated).total_seconds()
        require(age >= 60, "checks have not had time to appear")
        files = self.items(f"pulls/{pr['number']}/files?per_page=100")
        require(len(files) == pr["changed_files"] and files, "incomplete PR files")
        paths = {item["filename"] for item in files} | {
            item["previous_filename"] for item in files if "previous_filename" in item}
        base, head = pr["base"]["sha"], pr["head"]["sha"]
        workflows = [path for path in self.git("ls-tree", "-r", "--name-only", base,
                                               ".github/workflows").splitlines() if path.endswith((".yml", ".yaml"))]
        head_workflows = [path for path in self.git("ls-tree", "-r", "--name-only", head,
                                                    ".github/workflows").splitlines() if path.endswith((".yml", ".yaml"))]
        require(workflows and workflows == head_workflows, "workflow set changed or missing")
        for path in workflows:
            require(self.git("rev-parse", base + ":" + path) == self.git("rev-parse", head + ":" + path),
                    "workflow differs from trusted PR base")
            mode, patterns = workflow_filter(self.git("show", base + ":" + path))
            matched = [any(matching(path, pattern) for pattern in patterns) for path in paths]
            require(all(matched) if mode == "paths-ignore:" else not any(matched), "workflow should run for these paths")

    def checks(self, pr):
        head = pr["head"]["sha"]
        checks = self.items(f"commits/{head}/check-runs?per_page=100", "check_runs")
        statuses = self.items(f"commits/{head}/statuses?per_page=100")
        latest = {}
        for check in checks:
            require(check["head_sha"] == head, "check belongs to another SHA")
            key = (check["app"]["id"], check["name"])
            if key not in latest or check["id"] > latest[key]["id"]:
                latest[key] = check
        require(all(check["status"] == "completed" and check["conclusion"] == "success"
                    for check in latest.values()), "checks pending or unsuccessful")
        contexts = {}
        for status in statuses:
            if status["context"] not in contexts or status["id"] > contexts[status["context"]]["id"]:
                contexts[status["context"]] = status
        require(all(status["state"] == "success" for status in contexts.values()), "statuses pending or unsuccessful")
        if checks or statuses:
            require(self.request["delivery"]["ci"] == "green", "CI claim contradicts current checks")
        else:
            require(self.request["delivery"]["ci"] == "not_required", "missing checks")
            self.no_checks(pr)

    def delivery(self):
        delivery = self.request["delivery"]
        match = re.fullmatch(re.escape(self.url) + r"([1-9][0-9]*)", delivery["pr"])
        require(match and delivery["state"] == "open" and re.fullmatch(r"[0-9a-f]{40}", delivery["head"]),
                "invalid claimed PR identity")
        number = match[1]
        pr = self.api("pulls/" + number)
        require(pr["number"] == int(number) and pr["html_url"] == delivery["pr"] and pr["state"] == "open"
                and pr["draft"] is False, "PR must be open and non-draft")
        require(pr["head"]["repo"]["full_name"] == self.args.repository
                and pr["base"]["repo"]["full_name"] == self.args.repository, "PR repository mismatch")
        require(pr["head"]["sha"] == delivery["head"] and pr["head"]["ref"] == self.request["branch"]
                and pr["base"]["ref"] == delivery["base"], "PR head/base/branch mismatch")
        require(self.git("rev-parse", "HEAD") == delivery["head"]
                and self.git("branch", "--show-current") == self.request["branch"]
                and not self.git("status", "--porcelain"), "local Git identity/clean tree mismatch")
        commits = self.items(f"pulls/{number}/commits?per_page=100")
        require(len(commits) == pr["commits"], "incomplete PR commit pagination")
        coverage = set()
        for commit in commits:
            self.git("merge-base", "--is-ancestor", commit["sha"], delivery["head"])
            message = self.git("show", "-s", "--format=%B", commit["sha"])
            require(message == commit["commit"]["message"].strip(), "commit identity mismatch")
            ticket = re.search(r"\((DRA-[1-9][0-9]*)\)$", message.splitlines()[0])
            if ticket:
                coverage.add(ticket[1])
        tickets = self.request["tickets"]
        require(isinstance(tickets, list) and tickets and len(set(tickets)) == len(tickets)
                and all(re.fullmatch(r"DRA-[1-9][0-9]*", ticket) for ticket in tickets)
                and set(tickets) <= coverage, "delivered tickets missing from PR commits")
        self.checks(pr)
        require(self.api("pulls/" + number) == pr, "PR changed during verification")
        require(self.git("rev-parse", "HEAD") == delivery["head"], "local head changed during verification")
        return {key: self.request[key] for key in ("version", "run_id", "project", "batch_id", "branch",
                                                 "tickets", "delivery")} | {"ready": True}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--request", required=True)
    parser.add_argument("--result", required=True)
    parser.add_argument("--repository", required=True, help="exact owner/repo on github.com")
    parser.add_argument("--gh", default=shutil.which("gh"))
    parser.add_argument("--ordinary-pr", action="append", default=[], help="operator-reviewed NUMBER@HEAD_SHA")
    parser.add_argument("--cross-cutting-pr", action="append", default=[], help="operator-reviewed NUMBER@HEAD_SHA")
    args = parser.parse_args()
    try:
        require(args.gh, "gh executable unavailable")
        path = Path(args.request)
        require(path.is_file() and not path.is_symlink() and path.stat().st_size <= 1024 * 1024, "invalid request file")
        request = json.loads(path.read_text())
        require(isinstance(request, dict), "request must be an object")
        verifier = GithubVerifier(args, request)
        require(request.get("kind") in ("repository_preflight", "verify_delivery"), "unsupported request kind")
        receipt = verifier.preflight() if request["kind"] == "repository_preflight" else verifier.delivery()
        output = Path(args.result)
        require(not output.exists() and not output.is_symlink(), "result path already exists")
        output.write_text(json.dumps(receipt) + "\n")
        return 0
    except (VerificationError, OSError, ValueError, KeyError, TypeError, subprocess.SubprocessError) as error:
        print(f"GitHub verification failed: {error}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
