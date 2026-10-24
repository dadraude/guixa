# Member ordering redesign

Shared Catalan translation foundation and behavior-preserving member ordering presentation.
Branch: feature/member-ordering-redesign; delivery: stacked-to-main; baseline: 19d4ac9; focused runners: Vite+/Pest.

## Specs

S1. "Yes, start with the behavior-preserving member ordering redesign. Update the SDD plan, tasks and log first, keep the UI in Catalan, and leave new features for a separate spec."
S2. "Yes, split out and implement T36’s shared translation foundation, then continue the member ordering redesign. Update the SDD documents first and leave the remaining app-wide migration in T36."
S3. "Use stacked review slices: translation foundation first, then the member ordering redesign. Complete the remaining checks and native review before committing, and don’t push or open PRs yet."
Acceptance and scope: specs/001-weekly-orders/plan.md, Member ordering presentation and Translations;
tasks T38 and T37. Preserve all existing save/remove requests, amounts, roles and ordering states.
No new features, no app-wide migration, no publication or ticket mutations.

## Tasks

- U4 — CI/conflict correction; inline (bounded locale fixture and sequential stack maintenance); reproduce ca/en behavior, fix the fixture, rebase all three slices, verify before leased updates; pending.

- U1 (S1,S2) — T38 translation foundation; inline (T37 depends on it); implemented and locally verified; no commit yet.
- U2 (S1,S2) — T37 member presentation and ordering shell; inline; implemented and locally verified; no commit yet.
- U3 (S1,S2) — focused checks, visual checks and independent verifier; delegated verifier required by risk items 3/5 (shared shell/bootstrap and dependency); local verification complete; delivery/native review and full CI pending.

## Log

L1. User request verbatim: "Yes, split out and implement T36’s shared translation foundation, then continue the member ordering redesign. Update the SDD documents first and leave the remaining app-wide migration in T36."
L2. SDD plan/tasks/log updated before source writes. Prior 3-file documentation diff retained; .env.swp is unrelated and untouched.
L3. Forecast approximately 800 authored changed lines including tests/docs, generated lockfile excluded. No commit until delivery strategy is resolved if the review budget is exceeded. No push/PR authorization.
L4. Public verification seams: PHP/Vue translations, existing orderRows/orderTotal helpers and HTTP/Inertia page contracts; browser presentation/interaction checks. Existing tests cannot prove visual layout.

L5. RED: missing foundation module/PHP translation and four English-baseline presentation assertions failed before implementation. GREEN: 17 Vitest tests, standalone PHP test (3 assertions), types, affected lint/Pint, diff check and client/SSR build passed.
L6. Independent verifier confirmed baseline-equivalent editor orchestration and unchanged arithmetic helpers; repeated 17 tests and types in scratch. Scratch Herd PHP invocation and custom-renderer interaction harness unavailable, not product failures. Parent repeated focused tests and PHP successfully.
L7. Actual production assets inspected using isolated synthetic Inertia fixtures, no DB/account mutations: 375 px light and 1280 px dark; no horizontal overflow, ordering controls 44 px; drafts/totals, Enter/button save, marked removal, closed/cancelled states, keyboard and mobile navigation checked. PUT/DELETE URLs, quantity payload and order,errors partials observed. Scratch removed.
L8. Existing locale environment overrides remain untouched; deployments need APP_LOCALE=ca and APP_FALLBACK_LOCALE=ca for server translations. Remaining app-wide migration stays T36.
L9. Database-backed HTTP checks/full CI not run. Native review on; initial assessment needs untracked inventory and preflight awaits intended-untracked selection excluding .env.swp. No approval or review transaction started. Delivery exceeds the approximate 400-line budget; ask-on-risk chain strategy unresolved before any commit.

L10. 2026-10-24 user decision verbatim: "Use stacked review slices: translation foundation first, then the member ordering redesign. Complete the remaining checks and native review before committing, and don’t push or open PRs yet."
L11. Stacked-to-main selected. All checks and native review precede commits. Foundation is the first staged candidate; member list/shell and editor follow it. Hosted CI cannot run without publication and remains explicitly unrun. No remote operation is authorized.

L12. Isolated PostgreSQL HTTP/role-access/translation checks: 40 tests, 275 assertions passed; affected PHPStan 0 errors. Database creation guards and cleanup verified. Full hosted CI still unrun.
L13. Foundation staged-only snapshot passed 13 frontend tests, types and client/SSR builds independently of T37. Native review uses the staged projection and excludes all unrelated untracked files.

## Publication correction (2026-10-24)

User authorization verbatim: "Yes, rebase the three branches onto current main, fix the CI failure, and push with --force-with-lease only after the required checks pass. Preserve all three PRs and don’t merge anything."

PRs #191/#192/#193 remain the delivery units. Main advanced to ba2532b; original slice tips are preserved under refs/backup/member-stack-20261024/. Foundation rebased first without changing production behavior. CI reported an English-only translation fixture mismatch; reproduce under explicit Catalan locale before changing it. Required local proof: locale matrix, affected PHP tests, frontend/types per slice, and static/build checks; full validation remains CI-owned after checked updates. No PR merges.

Correction evidence: original focused test failed under ca/ca and passed with only APP_LOCALE=en. Explicit ca/en dataset reproduced ca failure before fixture repair. Registering the fixture in the selected locale fixes both paths without production changes. Catalan affected HTTP/translation/producer-summary checks: 45 tests, 320 assertions; Pint passed. Foundation client/SSR build, 13 frontend tests and types passed. Risk: none (test fixture and passive tracking only). Remaining branch replay and checks still pending; do not push yet.
