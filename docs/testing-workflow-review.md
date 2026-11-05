# Testing workflow review

First written 2026-10-30 against `origin/main` at `9b4d525c`; refreshed 2026-11-05 against
`d15ee8d1`. This page records what the review found, what has shipped and what the user decided.

## Current state

The foundation is good: scoped local checks, separate Application and Integration tests, real
PostgreSQL 17 and Redis 7 in CI, shared repository contracts, scenario-labelled tests, parallel PHP
execution and a fail-closed `ci` gate. Coverage and reproducibility matter more than CI latency.

| Scope        | Test files |
| ------------ | ---------: |
| Unit         |         30 |
| Application  |         44 |
| Integration  |         25 |
| Feature      |         52 |
| Architecture |          4 |
| Frontend     |         41 |

CI ran in about 2m40s before the coverage gate. The backend job now takes about 4m, 1m40s more
with pcov. Both are far inside a 10–15 minute budget. Two of 12 sampled runs failed (one backend,
one tooling); the gate reported both correctly, and neither cause was investigated.

## Recommendations and status

| ID  | Recommendation                              | Status                                                             |
| --- | ------------------------------------------- | ------------------------------------------------------------------ |
| A   | Isolated testing and reproduction guide     | Done: [testing.md](testing.md)                                     |
| B   | Shared tooling commands                     | Partly done (#264): `selection`, `contracts`, `helpers` commands   |
| C   | Composer updates and dependency audit gate  | Done: Dependabot entry, then an always-on `audit` job (#308)       |
| D   | Coverage over `app/` and `src/` with a gate | Done (#309): 75.2% baseline, `--min=75`, floor rises with features |
| E   | Real-application browser journeys           | Deferred by decision                                               |
| F   | Optional Compose services matching CI       | Open, not selected                                                 |
| G   | Flaky-test triage and quarantine policy     | Open                                                               |
| H   | Test reports and failure artifacts          | Open                                                               |
| I   | Scheduled browser, visual and load checks   | Open, depends on E                                                 |
| J   | Scenario-to-test manifest                   | Open                                                               |

B's remaining gap is fast, unit and integration entry points; the existing `composer test` and
`pnpm` scripts cover the rest.

## Decisions

- **Pre-push:** no hook. Focused checks locally, full validation in CI.
- **PR selection:** stay path-based (`.github/scripts/checks.py`); `main` pushes run everything.
- **Coverage:** gate PHP coverage of `app/` and `src/`. The floor starts just under the measured
  baseline and rises as features add tests for the old code they touch (see the
  [workflow profile](../.agent-workflow/profile.md)).
- **Audit:** `composer audit` and `pnpm audit` fail the build. A Composer exception lives in
  `composer.json` under `config.audit.ignore` with a reason and a review date.
- **Browser tests:** not now. Revisit once the gates above have settled.

## Still open

- Who owns flaky-test triage and what expiry applies (G).
- Whether local containers should exist as an optional alternative to managed services (F).
- Which browser journeys, load budgets and deployment runtimes are supported (E, I).
- Branch protection, repository security settings and developer-specific hooks were never inspected.
- Acceptance coverage per scenario is unverified; test names carry R#/S# identifiers, but identifier
  presence is not proof of coverage (J).
