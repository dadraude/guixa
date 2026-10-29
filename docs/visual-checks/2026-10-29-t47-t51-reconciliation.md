# T47–T51 integration and visual-evidence reconciliation

Verified on 2026-10-29 against `origin/main` at `1a9a41adcafa8b2f23ed162d7b3a52a5ac541ac0`.
This bounded reconciliation preserves the accepted reference design. It implements no Catalog/Sales
behavior and does not close feature 001. The historical baseline stays unchanged; current spec 001
and T52–T63 govern superseded behavior.

## Integration and approval

| Delivery                              | Implementation head                        | Merge in inspected main                    | Evidence                                                                          |
| ------------------------------------- | ------------------------------------------ | ------------------------------------------ | --------------------------------------------------------------------------------- |
| T47 pilot                             | `e49487cbc52c7e9e3210d5b848e398ad0780a8a0` | `668056849868a62250e95c88d0ea81589685c37a` | [#239](https://github.com/dadraude/guixa/pull/239), merged, CI passed             |
| Combined T48–T51                      | `e62858ac29395c57cfa108209503187444d3ff3f` | `7f95324b3445b17995a871a7902f1718a6b3c8ce` | [#240](https://github.com/dadraude/guixa/pull/240), merged, CI passed             |
| T46 settings/auth                     | `0899cfc3cac8f5c1d21d5cd8a8d6a1ce035097aa` | `ee7e68cbb36996c07706cdf4154ea13b355a2b5e` | [#242](https://github.com/dadraude/guixa/pull/242), DRA-200 Done                  |
| T44 dashboard/wallet/producer         | `8071b449ba2f44da2ed29cda24b9ec4cddb748cc` | `63c0523e2d7642d40f7c416a14a2389220016430` | [#243](https://github.com/dadraude/guixa/pull/243), DRA-198 Done                  |
| Subsequent translation/state coverage | `4a500235bf168d013e1598ca2ce737bb4746ca58` | `8574cd3c8cfd19a37197d607958fe7679537a381` | [#245](https://github.com/dadraude/guixa/pull/245), DRA-205 Done                  |
| Accepted spec/task publication        | `fa81841ec03a3c0fa46be72c3d474ea944471f03` | `a2ed902a91c9e5fb72848c0d9445fa798952e447` | [#248](https://github.com/dadraude/guixa/pull/248), merged                        |
| Cooperative-timezone test fixture     | `055a2b24653e772cc2b625ec6428310e526582c6` | `1a9a41adcafa8b2f23ed162d7b3a52a5ac541ac0` | [#249](https://github.com/dadraude/guixa/pull/249), merged; PR and main CI passed |

Every implementation head above is an ancestor of the inspected HEAD. No merge or cleanup was
performed here. In particular, #249 is now merged, rather than merely green/open.

The original Codex chat **Apply style using palette** (`01a17f1c-54e2-72a1-9d01-36a86c34d237`)
was consulted. The user's “approved!” accepted the concept direction. After viewing the integrated
pilot, the user requested top breadcrumbs/removal of repeated titles, then “Also apply the same style
to all pages. I think some of them still have old style.” This is the recorded pilot-continuation
approval. It remains valid; no second aesthetic approval is requested. It does not waive missing
route/role/state checks. PRs #239/#240 have no GitHub review records; merge is not being counted as
an independent visual review.

## Exact retained scope and implementation

The 37 route components partition as 2 + 9 + 8 + 7 + 11. Overlays remain with their owning task.

| Task                                                     | Owned routes and roles                                                                                                                                                                                                                                                                                                                                  | Implementation / closure                                                                                                                             |
| -------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| T47 / [DRA-219](https://linear.app/draude/issue/DRA-219) | Manager `Manage/Cycles/Index`, `Manage/Producers/Index`; populated/empty/long content, creation dialog and mobile drawer. Unchanged invalid/processing outcomes use the existing regression evidence as declared in the approved pilot inventory.                                                                                                       | Pilot #239 and breadcrumb regression #240 integrated. Historical gate and continuation approval satisfied: **Done**.                                 |
| T48 / [DRA-220](https://linear.app/draude/issue/DRA-220) | `Dashboard` (member/producer/manager), `Orders/{Index,Edit}` (member), `Wallet/Statement` (member/manager), `Producer/{Cutoff,CycleSummary,Products/Index,Products/Create,Products/Edit}` (producer). Includes applicable empty/error/pending, long/multirow, inactive product, sent/unsent and cancelled/closed states.                                | #240 integrated; T44/#243 retained. **Integrated implementation; evidence gate open**.                                                               |
| T49 / [DRA-221](https://linear.app/draude/issue/DRA-221) | Manager `Manage/Accounts/Index`, `Manage/Households/Index`, `Manage/ReturnItems/{Index,Create,Edit}`, `Manage/Stock/{Index,Create,Edit}`. Includes applicable empty/long lists, invalid/disabled forms, invitation status and inline settlement details.                                                                                                | #240 on T45/#230, then translations #245, integrated. **Integrated implementation; evidence gate open**.                                             |
| T50 / [DRA-222](https://linear.app/draude/issue/DRA-222) | Manager `Manage/Cycles/Show`, `Manage/Households/Show`, `Manage/Pickups/{Index,Show}`, `Manage/Reception/Show`, `Manage/Settings`, `Manage/Stock/Movements`. Also cycle/participant management, stock edit/status, reception scope/overwrite and workflow confirmations opened from T47/T49 indexes, with owning errors/pending/long/domain states.     | #240 migrated Pickups/Reception containers and shared surfaces; #245 added owning-state evidence. **Integrated implementation; evidence gate open**. |
| T51 / [DRA-223](https://linear.app/draude/issue/DRA-223) | `settings/{Appearance,Profile,Security}` (member/producer/manager), `auth/{AcceptInvitation,ConfirmPassword,ForgotPassword,Login,ResetPassword,TwoFactorChallenge,VerifyEmail}` (guest/auth challenge), `Welcome` (guest/authenticated). Includes appearance, passkeys, 2FA/recovery, errors/processing, deletion and password/email/invitation states. | #240 and T46/#242 integrated and retained. **Integrated implementation; evidence gate open**.                                                        |

Source inspection established the actual path: `layouts/AppLayout.vue` resolves `lib/pilotTheme.ts`
and passes the theme to `layouts/app/AppSidebarLayout.vue`. That shell includes `reference-style`,
shared navigation, desktop/mobile breadcrumbs and the content slot. `AuthLayout.vue` uses
`layouts/auth/AuthSimpleLayout.vue`; `Welcome.vue` uses `components/WelcomePage.vue`.
`resources/css/app.css` supplies `.reference-style*` surface/heading/navigation/dialog rules through
`public/theme-colors.css` semantic tokens. Existing `Heading`, `Breadcrumbs`, `NavMain`,
`StatusIndicator`, manager `NativeDialog`, shared controls and dialog/sheet primitives remain reused.
No new component system or reimplementation is required.

## Retained browser evidence

All five directories below retain `report.json`, `index.html` and captures. This session recomputed
capture SHA-256 hashes, historical manifest and runner hashes from Git, and checked declared cases,
measured dimensions/appearance, focus targets, duplicates, failures and identities with the shared
`checkCoverage` function. All **974 artifact records passed integrity/declared-coverage checks**;
they overlap and are not 974 unique application states. Reports record clean historical candidates,
and their manifests remain byte-identical at the inspected integrated HEAD.

| Directory under `/private/tmp`    | Candidate / fixed base | Cases | Established scope                                                                                                                                                                                                                           |
| --------------------------------- | ---------------------- | ----- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `guixa-reference-pilot-final`     | `e62858a` / `6680568`  | 19    | Both manager-only pilot views, 375/1280 light/dark, 1024/200%, empty/long, creation dialog and drawer after breadcrumb correction. This directory was overwritten by the #240 regression; it is **not** the original #239 candidate report. |
| `guixa-reference-all-pages-final` | `e62858a` / `6680568`  | 164   | All 37 route components, baseline and eight distinct reflow patterns. Signed-in fixture combines roles; most focus lists name only the sidebar trigger.                                                                                     |
| `dra-198-visual-final`            | `8071b44` / `ee7e68c`  | 85    | Separate dashboard/wallet roles and actual producer-role pages, forms/tables and unique states. [T44 measurements](dra-198.md).                                                                                                             |
| `dra200-visual-final`             | `0899cfc` / `5f96548`  | 192   | Ten settings/auth pages, three settings roles, security/profile overlays, appearance, system/fallback/reduced motion. [T46 measurements](dra-200.md).                                                                                       |
| `dra205-visual-phpstan-fix`       | `4a50023` / `efb9695`  | 514   | 40 application route/role views plus eight real Laravel errors, 168 states, actual component overlays and first/last keyboard targets. [T36 state truth and inspection](dra-205.md).                                                        |

The original chat records successful `run` and `verify` commands for #240's 164 + 19 cases,
representative inspection and pilot target/focus/contrast/reduced-motion probes. Retained T46
`/private/tmp/dra200-acceptance.json` has 96 control/padding baselines; `dra200-recovery-acceptance.json`
has six recovery-code/target/wrapping/containment cases. T36 retains the final verifier receipt
`/private/tmp/dra205-visual-phpstan-fix-verify.log`, trusted Appearance-menu measurements and final
producer-availability focus measurements at `4a50023`.

These fixtures mount real components with deterministic props and mocked requests. They prove the
declared rendering/interactions, not database persistence, route permission enforcement or a real
passkey ceremony. Real HTTP/regression tests provide application evidence. Framework error HTML is
separately generated through Laravel. The cleanup documentation archive is not visual app evidence.

No report is relabelled as a current-HEAD verifier pass. Since `4a50023`, a bounded comparison under
`resources/js`, `resources/css`, `public/theme-colors.css` and `lang/ca.json` finds only
`composables/usePilotTheme.spec.ts` changed; rendered source in that comparison is unchanged. This
supports retaining historical work, but does not repair missing role/state coverage or waive exact
candidate/base requirements for new evidence.

Audit script/result: `/private/tmp/guixa-t47-t51-reconciliation/{audit.mjs,audit.json}`. Manifest
`plan` checks passed for 19, 164 and 514 cases. No browser matrix was rerun, new screenshot taken or
new visual approval claimed during this reconciliation.

## Concrete remaining gates

1. **T48 producer role:** `dra-205-visual.ts:15` defaults `role` to `member`; all five `Producer/*`
   views omit `query.role`. Its `auth.roles`/`roleAreas` select member navigation. T44 has valid
   historical producer-role evidence, but the 514-case report does not provide final translated
   producer-shell coverage. A task-owned follow-up manifest must specify `role=producer`, cover
   the five owning views at 375/1280 light/dark, distinct form/table/summary reflow at 1024/200%,
   and affected unique states. Keep accepted dashboard/member/wallet work.
2. **T49/T50 manager role:** the same defect affects all 17 `Manage/*` views. Their content/overlay
   measurements remain useful, but navigation and inferred breadcrumbs are not manager-only.
   Combined-role rollout evidence and the two-route manager pilot do not fill every owning-page
   manager baseline. Declare the eight T49 and seven T50 routes with `role=manager`; measure their
   breadcrumb/navigation/drawer and below-fold/overlay focus at baseline and risky reflow cases,
   with applicable unique states on the owning route. T47 already has its two manager baselines;
   only later changed states need extra probes there. Keep T49 → T50 unchanged.
3. **T51 authenticated Welcome:** both all-pages and T36 fixtures force `auth.user=null` on Welcome.
   `Welcome.vue` actually changes label and URL to Open dashboard and `/dashboard` for a session.
   Add that owning state, assert the translated accessible action/URL and measure focus, 44 px
   targets and reflow at its riskiest combinations. Preserve guest evidence and T46 settings/auth
   work. The final handoff must explicitly carry the separate settings-role evidence; T36's member
   default is not proof of all three roles after translated content changes.
4. **Handoff for additions:** declare selected cases before capture; measure DOM/styles/geometry
   first. Use a committed fixture/manifest, fixed base and the shared runner/verifier, then inspect
   distinct changed role/state captures. Retain focused contrast, targets, clipping, focus and
   reduced-motion checks: the runner alone does not measure contrast or every control. Do not
   repeat the full unrelated suite, rebuild the style, or request pilot approval again. Any
   reproduced product defect needs its concrete mechanism recorded before implementation.

These are evidence gaps, not confirmed production UI defects. No exhaustive claim is made about
all application states, authenticator exchanges or future Catalog/Sales screens.

## Tests and tracking

Bounded source reads found meaningful actual HTTP assertions: `tests/Feature/Http/RoleAccessTest.php`
checks guest redirects, wrong-role 403s and shared role props; `tests/Feature/Ordering/CyclesPagesTest.php`
checks saved cycles and cutoff props. Current Cycles Vue tests exercise cancellation/participant
separation and closed history. Scenario identifier presence alone was not counted as proof.

The [main pipeline](https://github.com/dadraude/guixa/actions/runs/40785272441) passed at `1a9a41a`,
including application setup and CI checks. Current `composer ci:check` runs frontend format/lint,
types, Vitest, Pint, PHPStan and parallel Pest. #249 PR CI also passed at `055a2b2`. No redundant
full local suite was run for docs-only reconciliation. The no-executable-behavior TDD exception
applies; document/history/link/manifest/artifact checks are the local validation.

Linear readback confirmed draude/DRA/Coop and its authorized IDs. All five tasks started Backlog,
ready-for-agent, unassigned, standalone. Reconciliation recognizes T47 as Done from its already
integrated approved delivery. T48–T51 remain Backlog with integrated implementation and exact
remaining evidence recorded. Only descriptions and T47 status change; assignment/delegation,
parent, project, labels, priority and all native relations remain intact. T33 stays Todo with all
18 blockers, including T48/T50/T51. No new ticket, reparenting or removed blocker, and no feature
closure is performed.

## Safe T52 handoff

This chat reserves only documentary branch `docs/reconcile-reference-style-gates`, worktree
`guixa-worktrees/reconcile-t47-t51`: this note, proposal/rollout status, plan annotation, canonical
task tracking and appended feature-001 log entry. Application code, fixtures/manifests, generated
routes, dependencies and schema are free of this chat's edits. T52 can start in a fresh worktree
from current `origin/main`; it should incorporate this documentary PR after integration, preserve
log entries and coordinate the shared plan/tasks/log paths. This chat does not start T52.

Preserve the accepted shell/tokens/components. Current accepted spec and T52–T63 govern future
Sales/Finance behavior; never restore old pickup charging/ownership to satisfy a historical fixture.
The current dashboard still calls its integrated Ordering/Wallet queries; #248 aligned future
spec ownership and did not implement that future runtime.

All other retained worktrees remain untouched: `dashboard-manager-attention` (stopped feature-002
T01), `dra-183` (T33 reservation), `ci-skip-tests-agent-tooling`, `workflow-no-env-copy`, and
`ci-cycle-cutoff-clock`. Although #249 is merged, cleanup is outside this reconciliation. The
cleanup archive remains historical documentation. No database or machine environment was changed.

Graph limitation: the requested codebase-memory project/generation/coverage APIs are not exposed
in this session. The available Codegraph tool reports the new worktree unindexed. Verification used
bounded exact Git/source reads; no clean graph coverage or exhaustive architecture audit is claimed.
