# 005 — Landing identity rollout discovery

**Status:** Discovery recorded (2026-11-13); no spec, plan, tasks, tickets or implementation are authorized. The user chose a new spec 005 (not 004, not 001) in a Q&A. 005 takes over the delivery of 001 plan phases 3–5; 001 keeps the pilot (T83–T85) and a later Change in 001 will point its draft phases to 005. Until that Change is recorded, 001 plan "Llenguatge visual de la portada a tota l'aplicació" (`specs/001-weekly-orders/plan.md:1130`) remains the accepted visual direction and its draft deployment inventory (`plan.md:1231`) is a draft, not an obligation.

## Problem and outcome

Request (verbatim): "we should work on specs 004 so all the app has the same landing style".

The public landing (T83) and two member/staff pilots (Manage/Stock/Index, T84; Orders/Index and Orders/Edit, T85, PR #413 merged) use the landing identity (blue/cream paper, Nunito, warm tokens). Every other screen still shows the previous green system. A person moving between screens sees two products. The outcome is that every screen a person can reach, for every role, shows the landing identity in light and dark, with behaviour unchanged, so the app reads as one cooperative product.

**Audiences:** every role that signs in (household member, producer, Manager, Finance, Catalogue, Receiver) plus unauthenticated visitors reaching sign-in and error pages. Nothing here changes what any role can do.

**Reference:** `resources/js/components/WelcomePage.vue` and the `.landing` block in `resources/css/app.css:2119-2135` (dark in the following rules) are the source of truth for values. The opt-in mechanism is `resources/js/lib/pilotTheme.ts` (`pilotPages` at line 1, `landingPages` at line 54): a page is migrated only when it is in `landingPages`; `pilotPages` is the older green opt-in and does not mean migrated.

**Done so far (001):** landing T83, Manage/Stock/Index T84, Orders/Index and Orders/Edit T85 (`specs/001-weekly-orders/tasks.md`, rows T83–T85). T85's user visual approval ("decision B") is still pending.

## Boundaries and dependencies

**Non-goals**
- No change to routes, roles, permissions, Inertia props, validation, translated copy, domain data or calculations beyond strictly visual corrections (`plan.md`, "Seqüència d'implementació futura").
- No new screens or components when an existing pattern covers the need; no basket illustration on tables, errors or destructive confirmations.
- Mail templates (`resources/views/mail/*`) stay on Laravel defaults (decided 2026-11-13, `plan.md` deployment inventory).
- No revival of the retired 002 or of the "Reference style refresh" green direction, which the landing plan supersedes.
- No new dashboard capability: 004 owns dashboard behaviour; 005 only restyles it.
- No promotion of landing values into global `:root` in `public/theme-colors.css` during the rollout unless the plan says so (`plan.md`, "Contenció del pilot"); promotion and removal of the temporary boundary belong to the closing slice.

**Shared invariants for every slice**
- Contrast at least 4.5:1 for normal text, 3:1 for large text, meaningful borders and focus; essential field borders use their own token, not the decorative landing border (1.28:1 light, 2.31:1 dark).
- 44 x 44 px touch targets, visible focus, tab order, Escape and focus return in overlays.
- No clipping or horizontal page scroll; tabular alignment of quantities, amounts and dates preserved.
- Checked at 375 and 1280 px in light and dark; 1024 px at 200% text for each distinct composition pattern; full, empty, long, invalid and disabled states, plus open menu, select, dialog, sheet and toast where present.
- Behaviour preserved: existing Vue/PHP tests of the affected family stay green; evidence is measured as text first (`getComputedStyle`, `getBoundingClientRect`, `measureLayout`/`measureFocus`), screenshots only for a new direction or a failure (`AGENTS.md`, "Measure before screenshot").
- Danger, success and warning keep their semantics; ochre is not a warning colour.
- A route or component added or moved updates the deployment inventory before any boundary is removed.

**Dependencies**
- Delivered pilot tokens and shell from T84 and T85; T85 visual approval pending (see decisions).
- Spec 003 role decisions (Roles page keeps them); 003 R16 requires the Guixa visual system.
- Spec 004 dashboard delivered; the dashboard restyle needs the visual-approval gate.
- Unthemed today: `Manage/Roles/Index`, `Member/Baskets/{Index,Show}`, `Catalogue/Producers/Availability`, `resources/views/errors/minimal.blade.php`; `ui/select/SelectContent.vue` does not consume the theme context. Orphans to delete once confirmed unused: `layouts/app/AppHeaderLayout`, `layouts/auth/{AuthCardLayout,AuthSplitLayout}`, `components/AppHeader.vue` (`plan.md` inventory).
- Process: each change in its own worktree; seeder check per `AGENTS.md` (expected "no change"; state it in the handoff).

## Candidate delivery slices

Each leaves a role or audience able to use all its screens in the landing style. Page lists come from the draft inventory (`plan.md:1231`, snapshot `0074d383`) and must be re-verified against current routes before a slice's spec.

1. **Catalogue role complete.**
   - Outcome: a Catalogue user works through Producers, their products, weekly-product create/edit, availability and stock movements in the landing identity.
   - Boundary: `Catalogue/Producers/{Index,Products,Availability}`, `WeeklyProduct/{Create,Edit}`, `Catalogue/Stock/Movements`.
   - Dependency reason: first real forms (FormSelect, Input, errors, buttons) outside the stock dialog, plus one unthemed page (Availability); unblocks reuse of form patterns by every later slice. Needs the select decision below.
   - Complete when: all pages in landingPages, the invariants measured per route, existing tests green, visual approval recorded.
2. **Producer role complete.**
   - Outcome: a producer manages products, sees cutoff and cycle summary and reads stock movements in the landing style.
   - Boundary: `Producer/{Cutoff,CycleSummary}`, `Producer/Products/{Index,Create,Edit}`, `Producer/Stock/Movements`.
   - Dependency reason: reuses slice 1 form patterns; independent of finance and admin files.
   - Complete when: as slice 1; producer privacy (own context only) unchanged.
3. **Finance and reception complete.**
   - Outcome: Finance and Receiver work baskets, finance stock and receptions in the landing style with amounts, totals and states still scannable.
   - Boundary: `Finance/Baskets/{Index,Create,Show}`, `Finance/Stock/{Index,Movements}`, `Receptions/{Index,Show}` and the `components/{baskets,pickup,reception}` consumers.
   - Dependency reason: densest tables and sensitive actions (validate, distribute, collect); confirms the table pattern before admin.
   - Complete when: amounts, balances and sensitive-action states are measured with no calculation or permission change.
4. **Manager administration complete.**
   - Outcome: Manager runs accounts, households, producers, cycles, roles and settings in the landing style.
   - Boundary: `Manage/{Accounts/Index,Households/{Index,Show},Producers/Index,Cycles/{Index,Show},Settings,Roles/Index}`, `SettlementDetails`, `CycleCreateDialog`, `CycleManageDialog`, `components/roles`.
   - Dependency reason: depends on table, form and dialog patterns from slices 1-3; Roles keeps 003 decisions. The plan splits this family into smaller deliveries (cycles/households, accounts/roles/settings); the split is a spec-time decision.
   - Complete when: overlays (dialogs) keep Escape and focus return; Roles semantics unchanged.
5. **Remaining stock, returns and member views.**
   - Outcome: a member can use every member screen in the landing style (wallet, stock, baskets), and staff can use stock create/edit/movements and return items.
   - Boundary: `Manage/Stock/{Create,Edit,Movements}`, `Manage/ReturnItems/{Index,Create,Edit}`, `Wallet/Statement`, `Stock/Index`, `Member/Baskets/{Index,Show}`.
   - Dependency reason: completes the families whose pilots are already live; `Member/Baskets` is unthemed.
   - Complete when: the member journey from Orders to basket and wallet shows one identity.
6. **Access and account settings.**
   - Outcome: sign-in, invitation, password, two-factor and verification pages, plus profile, appearance and security settings, match the landing, including the public-to-sign-in-to-app transition.
   - Boundary: `auth/*`, `settings/{Profile,Appearance,Security}`, `layouts/settings/Layout.vue`, the two-factor and passkey components, `DeleteUser`; delete the orphan auth/app header layouts.
   - Dependency reason: independent of role slices (can run in parallel if no shared files in edit); contains security-sensitive overlays.
   - Complete when: destination after sign-in and the clear/dark/system preference are unchanged.
7. **Dashboard and closure.**
   - Outcome: no route or portal remains on the old theme; one identity from browser first paint to error pages.
   - Boundary: `Dashboard` reconcile with 004, `Welcome` transitions, `errors/minimal.blade.php` (landing palette and type only, no illustration), `app.blade.php` first-paint background, favicon and tab icons, promotion of values to the global mapping and removal of the temporary boundary, orphan deletion (`AppHeader.vue`).
   - Dependency reason: requires every earlier slice; global promotion is only safe when all consumers are reviewed.
   - Complete when: an inventory with no old-theme page, both modes verified on a migrated and an ex-pilot route after Inertia navigation with the mobile menu open.

**Recommended first slice: 1, Catalogue role complete.** It matches the accepted plan order (`plan.md:1297`, step 3), reduces the most uncertainty (first form hierarchy, an unthemed page, selects) at low business risk, and its patterns are consumed by all later slices. Alternatives: slice 2 (smaller, one audience, the same form uncertainty with fewer pages); slice 3 (highest daily value for volunteers but the highest risk: money and tables); slice 6 (visible to every user at sign-in and parallel-safe, but little new pattern learning).

## Decisions before and after first-slice selection

**Before the first slice (settled 2026-11-13, see Q&A history):** 1 own R1/S1/T01 numbering; 2 slice 1 Catalogue selected; 3 T85 visual approval gates the slice 1 spec; 4 slice 1 owns theming SelectContent; 5 takeover recorded by a 001 Change plus a pointer in the 001 plan; 6 the next authorized step is the slice 1 spec only (no Linear mapping or tickets). Original wording:
1. Numbering: precedent in 003 and 004 is a per-feature numbering restarting at R1/S1/T01 (`specs/README.md` keeps R/S/T global within a feature's slices). Recommended: 005 starts its own R1/S1/T01 and cites 001 items as "001 T84". T86+ and R37+ would only apply if 005 were part of 001. Confirm.
2. First slice: confirm slice 1 or choose an alternative.
3. T85 visual approval (decision B): does 005 start before it, or is it a gate for slice 1? Slice 1 reuses patterns that approval would still change.
4. Select/SelectContent: it must be themed before any form slice. Which slice owns it (slice 1 or a base task)? The draft inventory placed it in the shared base of phase 1, which T84/T85 did not close.
5. How the takeover is recorded: a 001 Change pointing phases 3–5 to 005, and which 001 documents stay as the source of the visual direction.
6. Authorized next step (spec for the chosen slice only); Linear mapping and ticket publication are not authorized.

**After the first slice (safe to defer)**
- Exact sequence and splitting of slices 3–5 (e.g. finance versus reception, cycles/households versus accounts/roles/settings).
- When to promote landing values to global `:root` and remove the `pilotPages` opt-in (slice 7 proposed).
- Whether to remove `pilotPages` or keep it as a reversible exit per variant during the rollout.
- Whether dashboard restyle waits for slice 7 or ships with its own visual approval.
- Per-slice review samples and seed data for the visual runner.

## Sources and readiness

- `specs/001-weekly-orders/plan.md:1130-1382`: landing identity plan, deployment inventory (`:1231`, draft 2026-11-13), implementation sequence (`:1297`), acceptance criteria (`:1323`), risks (`:1350`).
- `specs/001-weekly-orders/tasks.md` rows T83–T85 and `tasks/landing*.md`.
- `specs/004-dashboard/discovery.md` (format reference); `specs/README.md` (discovery, delivery slice, history and per-feature ID rules).
- `resources/js/lib/pilotTheme.ts`, `resources/css/app.css:2119-2135`, `resources/js/components/WelcomePage.vue`.
- `AGENTS.md` (worktrees, demo seeder, history, measure-before-screenshot).
- `specs/005-landing-identity-rollout/history/` via `node .agent-workflow/scripts/history.mjs read 005-landing-identity-rollout`: the original Request and two Q&A (new spec 005; 005 takes over 001 plan phases 3–5).

**Readiness limits.** The inventory is code-derived from a snapshot (`0074d383`), not opened in a browser; it has not checked whether 004 added components outside `Dashboard.vue`. T85 PR #413 is merged but its visual approval is pending. Whether 004 is fully delivered is taken from the brief; the 004 row in `specs/README.md` records accepted tasks and published tickets. The discovery is a scope proposal and does not approve any change.
