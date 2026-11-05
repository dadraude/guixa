"""Select CI jobs from changed paths and verify their final results."""

import json
import os
import subprocess
import sys


JOBS = ("frontend", "backend", "tooling")
# Run on every change, outside path selection.
ALWAYS = ("docs", "audit")
TOOLING = ("skills/", ".agents/", ".claude/", ".codex/", ".agent-workflow/")


def select_jobs(paths, event):
    selected = dict.fromkeys(JOBS, event != "pull_request")
    for path in paths:
        if path.startswith(".github/"):
            return dict.fromkeys(JOBS, True)
        if path.startswith(TOOLING):
            selected["tooling"] = True
        elif path.startswith(("docs/", "specs/", "odd/")) or (
            "/" not in path and path.endswith(".md")
        ):
            continue
        elif path.startswith(("resources/js/", "resources/css/")):
            selected["frontend"] = True
        elif path.startswith("tests/") or path in ("phpunit.xml", "phpstan.neon"):
            selected["backend"] = True
        else:
            # PHP can change generated routes/types/translations. Unknown paths and
            # dependency/build configuration also retain full application coverage.
            selected["frontend"] = selected["backend"] = True
    return selected


def failed_jobs(needs):
    outputs = needs.get("changes", {}).get("outputs", {})
    failures = []
    if any(outputs.get(job) not in ("true", "false") for job in JOBS):
        failures.append("changes")
    for job in ("changes", *ALWAYS, *JOBS):
        result = needs.get(job, {}).get("result")
        required = job == "changes" or job in ALWAYS or (
            needs.get("changes", {}).get("outputs", {}).get(job) == "true"
        )
        if result != "success" and (required or result != "skipped"):
            if job not in failures:
                failures.append(job)
    return failures


def main():
    if sys.argv[1] == "gate":
        failures = failed_jobs(json.loads(os.environ["NEEDS"]))
        if failures:
            sys.exit("CI did not pass: " + ", ".join(failures))
        print("All required checks passed.")
        return

    event = os.environ["EVENT"]
    paths = []
    if event == "pull_request":
        # Disable renames so both the removed and added paths select their jobs.
        changed = subprocess.check_output([
            "git", "diff", "--name-only", "--no-renames", "-z",
            os.environ["BASE_SHA"] + "..." + os.environ["HEAD_SHA"], "--",
        ])
        paths = changed.decode("utf-8", errors="surrogateescape").split("\0")[:-1]
    selected = select_jobs(paths, event)
    with open(os.environ["GITHUB_OUTPUT"], "a") as output:
        for job, required in selected.items():
            output.write(f"{job}={str(required).lower()}\n")
    print(json.dumps(selected))


if __name__ == "__main__":
    main()
