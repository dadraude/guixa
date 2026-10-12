"""Conservative public CI proof boundary; unfamiliar facts fail closed."""

import re


class ProofError(ValueError):
    pass


def require(condition, reason):
    if not condition:
        raise ProofError(reason)


def required_configuration(checks, statuses):
    require(checks or statuses, "operator-reviewed required CI contexts are missing")
    identities = set()
    for value in checks:
        match = re.fullmatch(r"([1-9][0-9]*)/(.+)", value)
        require(match and match[2].strip(), "required checks must be APP_ID/NAME")
        identity = (int(match[1]), match[2])
        require(identity not in identities, "duplicate required check identities are unsupported")
        identities.add(identity)
    require(all(isinstance(context, str) and context.strip() for context in statuses), "invalid required status context")
    return identities, set(statuses)


def assert_green(checks, statuses, required_checks, required_statuses, head):
    mandatory_checks, mandatory_statuses = required_configuration(required_checks, required_statuses)
    latest = {}
    for check in checks:
        require(check["head_sha"] == head, "check belongs to another SHA")
        key = (check["app"]["id"], check["check_suite"]["id"], check["name"])
        if key not in latest or check["id"] > latest[key]["id"]:
            latest[key] = check
    require(all(check["status"] == "completed" and check["conclusion"] == "success"
                for check in latest.values()), "checks pending or unsuccessful")
    require(mandatory_checks <= {(key[0], key[2]) for key in latest}, "required check has not appeared")
    contexts = {}
    for status in statuses:
        if status["context"] not in contexts or status["id"] > contexts[status["context"]]["id"]:
            contexts[status["context"]] = status
    require(all(status["state"] == "success" for status in contexts.values()), "statuses pending or unsuccessful")
    require(mandatory_statuses <= contexts.keys(), "required status has not appeared")


def matching(path, pattern):
    if pattern == "**/*.md":
        return path.endswith(".md")
    if pattern.endswith("/**") and re.fullmatch(r"[A-Za-z0-9_./-]+", pattern[:-3]):
        return path.startswith(pattern[:-2])
    require(re.fullmatch(r"[A-Za-z0-9_./-]+", pattern), "unsupported workflow glob")
    return path == pattern


def event_blocks(source):
    lines = source.splitlines()
    event_keys = [line for line in lines if re.match(r"^(on\s*:|[\"\']on[\"\']\s*:)", line)]
    require(event_keys == ["on:"], "unsupported workflow event syntax")
    blocks, current = {}, None
    for line in lines[lines.index("on:") + 1:]:
        if not line.strip() or line.lstrip().startswith("#"):
            continue
        if not line.startswith(" "):
            break
        if not line.startswith("    "):
            match = re.fullmatch(r"  (push|pull_request|workflow_dispatch):", line)
            require(match and match[1] not in blocks, "unsupported or duplicate workflow event")
            current = match[1]
            blocks[current] = []
        else:
            require(current, "workflow option outside an event")
            blocks[current].append(line)
    return blocks


def path_filter(block):
    require(block and block[0] in ("    paths:", "    paths-ignore:"), "unsupported PR/path filter")
    patterns = []
    for line in block[1:]:
        match = re.fullmatch(r"      - (['\"])([^'\"]+)\1", line)
        require(match, "unsupported path list")
        pattern = match[2]
        matching("validation", pattern)
        patterns.append(pattern)
    require(patterns, "empty path filter")
    return block[0].strip(), patterns


def excluded_push_branch(block, branch):
    require(block, "unrestricted push event may run")
    inline = re.fullmatch(r"    branches: \[([A-Za-z0-9_./-]+)\]", block[0])
    branches, remaining = [], block[1:]
    if inline:
        branches.append(inline[1])
    else:
        require(block[0] == "    branches:", "push requires supported literal branch exclusions")
        while remaining and remaining[0].startswith("      "):
            line = remaining.pop(0)
            match = re.fullmatch(r"      - (?:(['\"])([A-Za-z0-9_./-]+)\1|([A-Za-z0-9_./-]+))", line)
            require(match, "unsupported push branch list")
            branches.append(match[2] or match[3])
    require(branches and branch not in branches, "push event may run on current branch")
    if remaining:
        path_filter(remaining)


def prove_skipped(source, paths, branch):
    blocks = event_blocks(source)
    require("pull_request" in blocks, "workflow has no supported PR filter")
    mode, patterns = path_filter(blocks["pull_request"])
    matched = [any(matching(path, pattern) for pattern in patterns) for path in paths]
    require(paths and (all(matched) if mode == "paths-ignore:" else not any(matched)),
            "workflow should run for these paths")
    if "push" in blocks:
        excluded_push_branch(blocks["push"], branch)
