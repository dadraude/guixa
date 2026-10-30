"""Fixed, reusable entry points for CI workflow tooling checks."""

from pathlib import Path
import subprocess
import sys


ROOT = Path(__file__).resolve().parents[2]
COMMANDS = {
    "selection": (("python3", "-B", "-m", "unittest", "discover", "-s", ".github/scripts", "-p", "test_*.py"),),
    "contracts": (
        ("python3", "-B", "-m", "unittest", "discover", "-s", "skills/agent-workflow/tests"),
        ("python3", "-B", "-m", "unittest", "discover", "-s", ".agent-workflow/tests"),
    ),
}
HELPER_PATTERNS = (
    "skills/agent-workflow/tests/*.test.mjs",
    "skills/agent-workflow/tests/visual/*.test.mjs",
    ".agent-workflow/tests/*.test.mjs",
)


def helper_command(root=ROOT):
    """Expand the same three test groups that the workflow previously passed to Node."""
    paths = []
    for pattern in HELPER_PATTERNS:
        matches = sorted(root.glob(pattern))
        if not matches:
            raise ValueError(f"No helper tests match {pattern}")
        paths.extend(str(path.relative_to(root)) for path in matches)
    return ("node", "--test", *paths)


def commands_for(selector, root=ROOT):
    if selector == "helpers":
        return (helper_command(root),)
    if selector not in COMMANDS:
        raise ValueError(f"Unknown tooling check: {selector}")
    return COMMANDS[selector]


def main(argv=None):
    args = sys.argv[1:] if argv is None else argv
    if len(args) != 1:
        print("Usage: python3 .github/scripts/test_commands.py {selection|contracts|helpers}", file=sys.stderr)
        return 2
    try:
        commands = commands_for(args[0])
    except ValueError as error:
        print(error, file=sys.stderr)
        return 2
    for command in commands:
        subprocess.run(command, cwd=ROOT, check=True)
    return 0


if __name__ == "__main__":
    sys.exit(main())
