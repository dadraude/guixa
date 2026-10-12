#!/usr/bin/env python3
"""Read-only GitHub receipts. Unknown repository/CI/classification facts fail closed."""

import argparse
import json
import re
import shutil
import subprocess
import sys
from pathlib import Path

if __package__:
    from .github_ci import ProofError, assert_green
else:
    from github_ci import ProofError, assert_green


class VerificationError(ValueError):
    pass


def require(condition, reason):
    if not condition:
        raise VerificationError(reason)


def command(argv, cwd):
    result = subprocess.run(argv, cwd=cwd, capture_output=True, text=True, timeout=60)
    require(result.returncode == 0, f"read-only {argv[0]} command failed: {result.stderr.strip()}")
    return result.stdout.strip()


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
        if field == "check_runs":
            counts = [page["total_count"] for page in pages if "total_count" in page]
            require(all(type(count) is int and count == len(result) for count in counts),
                    "check-run count changed or pagination is incomplete")
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

    def checks(self, pr):
        head = pr["head"]["sha"]
        checks = self.items(f"commits/{head}/check-runs?filter=all&per_page=100", "check_runs")
        statuses = self.items(f"commits/{head}/statuses?per_page=100")
        assert_green(checks, statuses, self.args.required_check, self.args.required_status, head)
        require(self.request["delivery"]["ci"] == "green", "CI claim contradicts mandatory current checks")

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
    parser.add_argument("--required-check", action="append", default=[], help="operator-reviewed APP_ID/NAME")
    parser.add_argument("--required-status", action="append", default=[], help="operator-reviewed status context")
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
    except (VerificationError, ProofError, OSError, ValueError, KeyError, TypeError, subprocess.SubprocessError) as error:
        print(f"GitHub verification failed: {error}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
