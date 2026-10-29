# CI checks

`workflows/tests.yml` starts on every pull request and every push to `main`.
The `changes` job selects checks with `scripts/checks.py`; the final `ci` job
requires each selected check to succeed. Keep `ci` as the required branch check.

| Changed paths | Selected checks |
| --- | --- |
| `docs/`, `specs/`, `odd/`, root Markdown | Formatting and lint (`docs`) |
| `resources/js/`, `resources/css/` | `docs`, frontend build, types and tests |
| `tests/`, `phpunit.xml`, `phpstan.neon` | `docs`, PHP format, analysis and tests (`backend`) |
| `skills/`, `.agents/`, `.claude/`, `.codex/`, `.agent-workflow/` | `docs`, workflow contracts, helpers and browser harness (`tooling`) |
| Other application, shared or unknown paths | `docs`, `frontend`, `backend` |
| `.github/` or any push to `main` | All checks |

Mixed changes select the union of their checks. Renames consider both old and
new paths, and deletions retain their checks. PHP production changes also run
frontend checks because they can change generated routes, types and translations.
Frontend builds need PHP for Wayfinder, but do not start PostgreSQL or Redis.
Backend checks retain application setup and its build, because rendered views
need the Vite manifest. Only backend checks start those services.

The `docs` job always runs `pnpm run check`, including on mixed documentation and
code changes. `ci` runs even after failures and rejects failed, cancelled, missing
or unexpectedly skipped required jobs. Intentional path-based skips are accepted.

Run selector, real Git diff and gate regressions without application dependencies:

```sh
python3 -B -m unittest discover -s .github/scripts -p 'test_*.py'
```

No application behavior changed; these regression checks and workflow syntax
validation are the focused checks for this CI refactor.
