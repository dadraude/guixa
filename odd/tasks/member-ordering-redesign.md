# Member ordering redesign

Shared Catalan translation foundation and behavior-preserving member ordering presentation.
Branch: feature/member-ordering-redesign; delivery: stacked-to-main; baseline: 19d4ac9; focused runners: Vite+/Pest.

## Specs

S1. "Yes, start with the behavior-preserving member ordering redesign. Update the SDD plan, tasks and log first, keep the UI in Catalan, and leave new features for a separate spec."
S2. "Yes, split out and implement T36’s shared translation foundation, then continue the member ordering redesign. Update the SDD documents first and leave the remaining app-wide migration in T36."
S3. "Use stacked review slices: translation foundation first, then the member ordering redesign. Complete the remaining checks and native review before committing, and don’t push or open PRs yet."
S4. "Yes, commit only the approved translation foundation now, then complete native review for each remaining slice before committing it. Don’t push or open PRs."
S5. "Yes, commit the cycle-list/navigation slice without native review, then continue with native review of the editor slice before committing it. Don’t push or open PRs."
Acceptance and scope: specs/001-weekly-orders/plan.md, Member ordering presentation and Translations;
tasks T38 and T37. Preserve all existing save/remove requests, amounts, roles and ordering states.
No new features, no app-wide migration, no publication or ticket mutations.

## Tasks

- U4 — CI/conflict correction; inline (bounded locale fixture and sequential stack maintenance); reproduce ca/en behavior, fix the fixture, rebase all three slices, verify before leased updates; pending.

- U1 (S1,S2) — T38 translation foundation; inline (T37 depends on it); implemented, locally verified and native-reviewed; commit 0c56c738bed223e1e614e1385f6339061d63789e.
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
L14. User granted foundation native review (answer "1"). Three provider lenses returned no findings; exact acknowledgement consumed authority for target sha256:79d885a693c78bc6f70925d6dc6f57a110818932b047183e4ccdfe1022c71e3a. Staged foundation unchanged, uncommitted.
L15. T37 empty/error/enlarged-text browser checks exposed clipping at 200% text. Wrapping fix passed visual/element-boundary recheck, builds, types/lint and 17 tests. Browser scratch removed; no database activity.
L16. One slicing pass: cycle list/navigation with Index.spec.ts, then editor/OrderProductRow with Edit.spec.ts. Editor source/test slice is 408 lines before brief evidence notes; recommend size exception to retain a cohesive consuming page/component/test unit. No native UI review or commits yet.
L17. Cycle-list/navigation staged slice is 250 authored lines over the reviewed foundation tree. Its isolated index checkout passes 14 tests and type checking. Initial scratch startup lacked vendor/PHP PATH; corrected test harness only, then passed.
L18. Native staged-overlay preflight for the next uncommitted slice returned terminal staged_workspace_overlay_recovery_unavailable. No new START/authority created. Stop before commits: ask whether the already-reviewed foundation may be committed as the baseline, then review each following slice before committing it. Current index includes foundation plus cycle-list/navigation; preserve reviewed foundation tree d8123af1f30a75f17c5834454fefc3334591b24a for exact restoration if authorized. No commit/push/PR.

L19. User authorization verbatim: "Yes, commit only the approved translation foundation now, then complete native review for each remaining slice before committing it. Don’t push or open PRs."
L20. Foundation committed as 0c56c738bed223e1e614e1385f6339061d63789e; HEAD tree exactly equals reviewed d8123af1f30a75f17c5834454fefc3334591b24a. Remaining source preserved. Native review continues per slice against committed bases, no push/PR.

L21. Post-commit foundation assessment: medium/configuration change, review_due=false (under_budget). This informational result is recorded separately from the completed explicit pre-commit review; exact commit-tree equality preserves that evidence. Next slice rollback boundary is the cycle-list and navigation presentation plus its test, retaining the foundation and editor baseline.

L22. User selected "2" (Skip this time) for the cycle-list/navigation candidate. Native returned declined_this_candidate; no review approval exists and future reviews remain enabled.
L23. User authorization verbatim: "Yes, commit the cycle-list/navigation slice without native review, then continue with native review of the editor slice before committing it. Don’t push or open PRs."
L24. Cycle-list/navigation commit authorized without native review. Its isolated 14-test/type check and broader browser/HTTP verification remain valid; only documentation has changed since those checks. Risk: item 3 (shared navigation presentation). Independent verifier already completed; editor stays out of this commit.

## Publication correction (2026-10-24)

User authorization verbatim: "Yes, rebase the three branches onto current main, fix the CI failure, and push with --force-with-lease only after the required checks pass. Preserve all three PRs and don’t merge anything."

PRs #191/#192/#193 remain the delivery units. Main advanced to ba2532b; original slice tips are preserved under refs/backup/member-stack-20261024/. Foundation rebased first without changing production behavior. CI reported an English-only translation fixture mismatch; reproduce under explicit Catalan locale before changing it. Required local proof: locale matrix, affected PHP tests, frontend/types per slice, and static/build checks; full validation remains CI-owned after checked updates. No PR merges.

Correction evidence: original focused test failed under ca/ca and passed with only APP_LOCALE=en. Explicit ca/en dataset reproduced ca failure before fixture repair. Registering the fixture in the selected locale fixes both paths without production changes. Catalan affected HTTP/translation/producer-summary checks: 45 tests, 320 assertions; Pint passed. Foundation client/SSR build, 13 frontend tests and types passed. Risk: none (test fixture and passive tracking only). Remaining branch replay and checks still pending; do not push yet.
