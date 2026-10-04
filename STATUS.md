# Project status

## Done

- **Session 1 (2026-09-30):** initial SDD structure (`CLAUDE.md`, `specs/`).
- **Session 2 (2026-09-30):** SDD workflow based on Matt Pocock's skills
  (ADR 0001). Tickets tracker set up in Linear (team Draude, project Guixa, triage labels);
  specs stay in `specs/`. `docs/agents/` configured. Everything translated to English.
- **Session 3 (2026-09-30):** vision written in `docs/vision.md` with `/grill-with-docs`;
  `CONTEXT.md` started (households, members, commissions, order cycle, producer orders, boxes,
  balances). Open question: data migration from the current software.

- **Session 4 (2026-09-30):** principles written in `docs/principles.md` with
  `/grill-with-docs`: Laravel + Inertia + Vue 3 (TS) monolith with offline islands for packing
  boxes and recording payments (ADR 0002); plain VPS with PostgreSQL and Redis, Horizon,
  Deployer from GitHub Actions (ADR 0003); magic link + optional password, invitation-only;
  Pest + Vitest + Playwright, blocking CI. `CONTEXT.md`: invitation, inactive household.
  Open questions: backup strategy and data migration.

- **Session 5 (2026-10-01):** `/grill-with-docs` on the first feature (households, members,
  commissions). Decisions in `specs/001-households-members-commissions/decisions.md`;
  ADR 0004 (fixed roles assigned to dynamic commissions, administrator outside households);
  `CONTEXT.md`: administrator, inactive member, household number, role, commission updated;
  `principles.md`: password now required when accepting the invitation.
  Glossary reviewed: every term in `CONTEXT.md` has its agreed Catalan UI word (household =
  "unitat familiar", order = "comanda" with no separate cart, box = "caixa"…); definitions
  now say permissions come from roles, not commissions. `README.md` added.

- **Session 6 (2026-10-02):** `/to-spec` wrote `specs/001-households-members-commissions/spec.md`
  (`Status: draft`). Test seam agreed: Pest feature tests at the HTTP level plus one Playwright
  path (invitation → password → magic-link code). Dev and tests temporarily on MySQL
  (production stays PostgreSQL), noted in `principles.md`.

- **Session 6b (2026-10-02):** spec 001 reviewed with the user and **approved**. Changes:
  **owner** (the Artisan-created administrator; only one who manages administrators, cannot be
  deactivated from the app), household number is an auto-increment ID (name editable),
  every member sees all commissions and their members. `CONTEXT.md` and `decisions.md`
  updated.

- **Session 6c (2026-10-02):** ADR 0005: UUIDv7 primary keys whenever possible; household
  number is a separate auto-increment column. `principles.md`, spec 001 and `CONTEXT.md`
  updated.

- **Session 6d (2026-10-02):** `/grill-with-docs` on project-wide technical decisions:
  Laravel 13 Vue starter kit with Fortify plus our own magic link (ADR 0006); `User` is the
  sign-in identity (`CONTEXT.md`); Laravel Sail locally; PostgreSQL everywhere again (the
  temporary MySQL exception is dropped); PHP 8.5 and Node 22 LTS. `principles.md` and spec 001
  updated.

- **Session 7 (2026-10-02):** `/to-tickets` split spec 001 into 14 vertical-slice tickets in
  Linear (project Guixa, `ready-for-agent`, native blocking relations): DRA-10 skeleton and CI
  → DRA-11 owner + magic link → DRA-12 sign-in screen, DRA-13 administrators, DRA-14
  households → DRA-15 invitations → DRA-16 pending invitations, DRA-17 profile switch,
  DRA-18 commissions, DRA-19 own profile → DRA-20 roles (opens membership work to the
  membership role) → DRA-21 edit/move members, DRA-22 deactivate/reactivate, DRA-23
  directory + warning + Playwright path. Until DRA-20, membership work is administrator-only
  through one shared "can manage membership" rule.

- **Session 8 (2026-10-03):** DRA-10 implemented (PR open, Linear "In Progress"): Laravel 13
  Vue starter kit trimmed to password sign-in (no registration, password reset, two-factor,
  passkeys, email verification, account deletion or welcome page); Pest on PostgreSQL;
  `User` with UUIDv7 key and stubs that make UUIDs the default; Europe/Madrid, `ca` locale,
  four-week sessions, Redis for sessions, cache and queue; Herd for PHP with DBngin PostgreSQL and
  Redis (replaced Sail); all UI text in `lang/ca.json` and `lang/ca/*.php` (Vue uses `t()` from
  `@/lib/i18n`, and a test fails on a missing translation); CI on GitHub Actions (Pest, Pint,
  Larastan level 7, Oxlint/Oxfmt, vue-tsc). The kit's Vite+ replaces ESLint and Prettier
  (ADR 0007). Reviewed with `/code-review` and findings fixed.

- **Session 9 (2026-10-03):** DRA-11 and DRA-12 implemented test-first in one PR stacked on
  the DRA-10 PR (Linear "In Progress", PR linked). `guixa:create-owner {email} {name}` creates
  the owner (administrator profile, no password; a partial unique index keeps one owner) and
  queues a magic link after commit. Magic links: HMAC-hashed token, 15 minutes, single use, a
  new one invalidates older ones; opening the link shows a one-click confirmation (POST) so
  mail scanners cannot spend it. From the sign-in screen the email also carries a 6-digit
  code accepted only in the requesting session; the response is the same for unknown emails.
  Password, link and code attempts share Fortify's `login` limiter (5/min per email+IP,
  20/min per IP). A user without a password is held on "set your password". `Gate::before`
  lets active administrators pass every check; `can:administer` guards the administrator
  home; `/` sends administrators there and others to the dashboard; the header shows
  "acting as administrator". Reviewed with `/code-review` and findings fixed.

- **Session 10 (2026-10-04):** DRA-11 and DRA-12 merged (PR #3, including the Copilot review
  fixes). DRA-13 implemented test-first (owner manages administrators): the owner lists,
  creates (magic link queued after commit), deactivates and reactivates administrators; the
  owner cannot be targeted. Sessions end through a middleware that signs out, on the next
  request, any user who may not sign in (`User::canSignIn()`), so it works with Redis sessions
  and remember-me cookies; magic link, code and password sign-in refuse inactive
  administrators. `manage-administrators` is excluded from `Gate::before`, so only the owner
  passes it. Emails are unique ignoring case (`LOWER(email)` index).

- **Session 11 (2026-10-04):** DRA-14 implemented test-first (households: create, rename, list).
  The `households.number` column is a database identity column, so numbers are handed out in
  order, never reused and not fillable; rename only accepts `name`. The list shows active and
  inactive households. Every check goes through the single `manage-membership` Gate rule
  (route middleware and form request); active administrators pass it through `Gate::before`,
  others are refused until the roles ticket widens the rule. Reviewed with `/code-review`, no
  findings to fix.

- **Session 12 (2026-10-04):** DRA-15 implemented test-first (invite a person to a household
  and accept the invitation). New `members` table (member profile: user, household, optional
  phone, active; name and email stay on the `User`) and `invitations` table (household, email
  in lower case, optional name, HMAC-hashed single-use token, expiry 7 days, accepted/cancelled
  timestamps; status pending/accepted/cancelled/expired derived in `Invitation::status()`).
  `InviteToHousehold` replaces any pending invitation for the same email and queues the email
  after commit; an email that already belongs to a member is rejected ignoring case; everything
  goes through `manage-membership`. Opening the link (GET) only shows the form; accepting (POST)
  creates user and member, marks the invitation accepted and signs in. Expired, used, cancelled
  or unknown invitations show "ask the membership commission for a new one". An email that
  already has a user (an administrator) is refused for now, until the attach-to-existing-user
  ticket. The member home (`/dashboard`) shows their own household and its members only. Token
  hashing moved to `App\Auth\SecretHasher`, shared with magic links. Reviewed with
  `/code-review`, no findings to fix.

- **Session 13 (2026-10-04):** DRA-16 implemented test-first (manage pending invitations),
  stacked on the DRA-15 branch. `/households/invitations` lists open invitations (pending and
  expired, expired ones badged; accepted and cancelled are not shown), soonest expiry first,
  with an "Invitations" sidebar entry for administrators. Resend gives the invitation a new
  token and a new 7-day expiry in place (the old link stops working, an expired one becomes
  valid again) and queues the email; cancel sets `cancelled_at`, so the link shows the
  existing "ask for a new one" message. Accepted or cancelled invitations answer 404 to resend
  and cancel. Everything goes through `manage-membership`. Email queuing moved to
  `QueueInvitationEmail`, shared by invite and resend. Added a test that re-inviting an email
  kills the old link.

- **Session 14 (2026-10-04):** standard ticket flow added (PR #11): `/ship-ticket DRA-NN`
  skill with a `ticket-implementer` agent (test-first, one single-line commit) and a read-only
  `ticket-reviewer` agent; `.claude/settings.json` allows commit and push on feature branches and
  denies force-push, pushing to `main` and merging PRs. DRA-19 implemented and merged (PR #12):
  a member edits name, optional phone and email; an email change stays pending until a
  single-use 24-hour link is confirmed by POST; the magic-link session may set a password
  without the current one for 15 minutes. DRA-17 implemented (PR #10 landed on the DRA-15
  branch after it had merged, so it was re-landed on `main` in PR #13): a user with both a
  member and an administrator profile chooses which one to act as after signing in and
  switches from the header; permissions follow the active profile (`ActiveProfile`,
  `Gate::before`); accepting an invitation for an administrator's email attaches a member
  profile without overwriting name or password. Follow-ups are listed in the PR bodies.

- **Session 15 (2026-10-04):** DRA-18 rebased onto `main` (after DRA-17 and DRA-19) and merged
  (PR #14): commissions with a unique name, rename, delete only when empty; add active members
  and remove them (pivot with composite key); every active member sees all commissions and
  their members' names without contact details (`view-commissions`); changes go through
  `manage-membership`, which follows the active profile. Pest 193/193. Follow-ups in PR #14:
  tests for the two-profile case, administrator without member profile, and inactive member.
  Locally PHPStan needs `--memory-limit=1G`, and tests need `vp build` after adding a page.

## Next step

**Session 16:** `/ship-ticket DRA-20` (roles on commissions and the membership role), which
unblocks DRA-21, DRA-22 and DRA-23.

Before spec 002: define UX/UI principles with `/grill-with-docs` (mobile-first, accessibility
target, visual identity, key flows for ordering and the offline box/payment screens) and record
them in `docs/principles.md`. Today only the starter kit's Tailwind + shadcn-vue base and
"responsive, phones and desktops" are decided.

Still open (not blocking): backup strategy, data migration from the current software.
