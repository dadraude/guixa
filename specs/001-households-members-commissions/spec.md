# 001 — Households, members and commissions

Status: approved

Decisions come from [`decisions.md`](decisions.md) (grilling session of 2026-10-01). Terms are
defined in [`CONTEXT.md`](../../CONTEXT.md); see also
[ADR 0004](../../docs/adr/0004-fixed-roles-on-dynamic-commissions.md) and
[`principles.md`](../../docs/principles.md).

## Problem Statement

The cooperative cannot use Guixa for anything until it knows who belongs to it. Today the
list of households, who is in each one, who can sign in and who is in which commission lives
in the old, unmaintained software and partly by hand. Commissions change over time (new ones
appear, others merge), but the work each one is allowed to do in the app must stay under
control. There is also no safe way to start: someone has to exist before anyone can be
invited, and there must be a way out if the people managing membership lose access.

## Solution

Guixa gets the membership foundation every later feature builds on:

- An **administrator**, outside any household, can always do everything. The first one, the
  **owner**, is created from the command line and is the only one who manages the other
  administrators; the owner can never be deactivated from the app.
- Members with the **membership** role create **households** (with a name; the app assigns
  the **household number**)
  and send **invitations** by email. There is no open sign-up.
- An invited person accepts the invitation, confirms their name and sets a password, and
  becomes a **member** of the household. From then on they sign in with a **magic link**
  (which also carries a 6-digit code) or with their password.
- **Commissions** are created, renamed and deleted freely, and members are added to them.
  Permissions never come from a commission's name: only administrators assign one or more of
  four fixed **roles** (orders, boxes, treasury, membership) to commissions, and every member
  of a commission gets its roles.
- Households and members can be made inactive and reactivated without losing history.
- Members in any commission see a directory with everyone's contact details; others see only
  their own household. Members with the membership role are warned about households that have
  nobody in any commission.

## User Stories

### Bootstrap and administrators

1. As the person installing Guixa, I want to create the owner with a command on the server,
   so that someone can sign in to an empty app.
2. As a new administrator, I want to receive a sign-in email, so that I can reach the app
   without knowing a password yet.
3. As a new administrator, I want to be asked to set a password on my first access, so that
   both sign-in methods always work for me.
4. As the owner, I want to create other administrators in the app, so that the cooperative
   does not depend on a single person or on server access.
5. As the owner, I want to deactivate an administrator, so that people who no longer help
   with administration lose that access while their history is kept.
6. As the owner, I want to reactivate an inactive administrator, so that a returning helper
   does not need a new account.
7. As the owner, I want to be the only one who can create, deactivate and reactivate
   administrators, so that administrators cannot remove each other or me.
8. As the owner, I want to be impossible to deactivate or remove from the app, so that the
   cooperative always keeps a way in.
9. As an administrator, I want to do everything any role allows, so that I can fix any
   problem or recover from a lock-out.
10. As an administrator, I want to not belong to any household and not place orders, so that
   administration is not mixed with being a member.
11. As a person who is both a member and an administrator with the same email, I want to
   choose which one to act as after signing in, so that I use the right permissions for the
   task.
12. As a person who is both, I want to switch between acting as a member and as an
   administrator without signing in again, so that changing hats is quick.
13. As a person who is both, I want to always see which one I am acting as, so that I do not
   make changes with the wrong permissions by mistake.

### Sign-in

14. As a member, I want to ask for a magic link by typing my email, so that I can sign in
    without remembering a password.
15. As a member, I want the magic link to expire after 15 minutes and work only once, so that
    an old email cannot be used to get into my account.
16. As a member who asked for the link on my computer but reads email on my phone, I want to
    type the 6-digit code from the email on the computer, so that I sign in on the device
    where I asked for it.
17. As a member, I want to sign in with my email and password, so that I can get in without
    waiting for an email.
18. As a member who forgot my password, I want to sign in with a magic link and then change
    my password, so that I do not need a separate reset flow.
19. As a member, I want my session to last for weeks, so that I am not asked to sign in every
    time I open the app on my phone.
20. As a member, I want to sign out, so that nobody else can use my session on a shared
    device.
21. As a person typing an email that does not belong to anyone, I want to see the same
    message as for a known email, so that the app does not reveal who is a member.
22. As the cooperative, I want repeated sign-in attempts and wrong codes to be throttled, so
    that passwords and codes cannot be guessed.
23. As an inactive member, I want to be refused when I try to sign in, so that people who
    left no longer access the cooperative's data.

### Invitations

24. As a member with the membership role, I want to invite a person by email to a household,
    so that they can join it without open sign-up.
25. As an invited person, I want to open the invitation, confirm my name and set a password,
    so that my account is ready and I am signed in.
26. As an invited person, I want the invitation to be valid for 7 days and usable once, so
    that a forgotten email cannot be used later by someone else.
27. As an invited person opening an expired or used invitation, I want a clear message telling
    me to ask the membership commission for a new one, so that I know what to do.
28. As a member with the membership role, I want to see pending invitations, so that I know
    who has not joined yet.
29. As a member with the membership role, I want to resend an invitation, so that someone who
    lost it or let it expire can join; the previous link stops working.
30. As a member with the membership role, I want to cancel an invitation, so that a mistaken
    or no longer wanted invitation cannot be used.
31. As a member with the membership role, I want to be stopped from inviting an email that
    already belongs to a member, so that one person does not end up with two accounts.

### Households

32. As a member with the membership role, I want to create a household with a name, so that
    new families can join the cooperative.
33. As a member with the membership role, I want the app to give each new household the next
    household number automatically, never reused, so that numbers on boxes are never
    ambiguous.
34. As a member with the membership role, I want to edit a household's name, so that I can fix
    mistakes; its number never changes.
35. As a member with the membership role, I want to see all households with their numbers and
    members, active and inactive, so that I can manage membership.
36. As a member with the membership role, I want to deactivate a household that leaves the
    cooperative, so that it can no longer order while its history is kept.
37. As a member with the membership role, I want deactivating a household to make all its
    members inactive, so that nobody from it keeps access.
38. As a member with the membership role, I want to reactivate an inactive household with the
    same number, so that a family coming back keeps its history.
39. As a member with the membership role, I want to reactivate the members of a reactivated
    household one by one, so that only the people actually returning get access again.
40. As a member, I want to see my own household and its members, so that I know who shares
    our orders and balance.

### Members

41. As a member, I want to edit my own name, phone and password, so that my details stay
    correct.
42. As a member, I want to change my email and confirm the new address from a link sent to
    it, so that I cannot lock myself out with a typo.
43. As a member with the membership role, I want to edit any member's name, email and phone,
    so that I can fix details for people who cannot do it themselves; the email changes
    without confirmation.
44. As a member with the membership role, I want to move a member to another household, so
    that people who change household stay in the cooperative; past orders and balance stay
    with the old household.
45. As a member with the membership role, I want to deactivate a member who leaves while their
    household stays, so that they can no longer sign in.
46. As a member with the membership role, I want deactivating a member to remove them from
    every commission and end their open sessions, so that they lose access at once.
47. As a member with the membership role, I want to reactivate an inactive member, so that a
    person who comes back keeps their history.
48. As a member with the membership role, I want to be prevented from reactivating a member of
    an inactive household, so that the household is reactivated first.

### Commissions and roles

49. As a member with the membership role, I want to create a commission with a name, so that
    the app follows how the cooperative organises its work.
50. As a member with the membership role, I want to rename a commission, so that names stay
    up to date when the cooperative reorganises.
51. As a member with the membership role, I want to delete a commission only when it has no
    members, so that nobody loses permissions by surprise.
52. As a member with the membership role, I want to add active members to a commission and
    remove them, so that the app knows who does each job.
53. As an administrator, I want to manage commissions and their members too, so that I can
    act when the membership commission cannot.
54. As an administrator, I want to assign and remove the orders, boxes, treasury and
    membership roles on each commission, so that permissions are under my control.
55. As an administrator, I want a commission to hold several roles and a role to be held by
    several commissions, so that merged or split commissions can be modelled without code
    changes.
56. As a member with the membership role, I want to be unable to assign roles, so that the
    membership role cannot grant itself or others more permissions.
57. As a member, I want to get every role of every commission I belong to, so that I can do my
    commission's work.
58. As a member, I want to see which commissions I belong to and which roles they give me, so
    that I understand what I can do in the app.
59. As a member, I want to see every commission and who is in it, so that I know whom to
    contact about each area of the cooperative's work.
60. As anyone, I want deleting a commission to remove only its role assignments, never the
    roles themselves, so that other commissions keep working.

### Directory and warnings

61. As a member of any commission, I want a directory of all households and members with
    their contact details, so that I can reach people for commission work.
62. As a member who is not in any commission, I want to see only my own household, so that
    other people's contact details are kept private.
63. As a member with the membership role, I want to see which active households have no member
    in any commission, so that I can encourage them to take part; nothing is blocked.

### Language

64. As a member, I want every screen and email in Catalan, using the agreed words
    ("unitat familiar", "membre", "comissió", "rol", "invitació"…), so that the app speaks
    the cooperative's language.

## Implementation Decisions

### Identity, administrators and members

- One **user** (the sign-in identity, see `CONTEXT.md`) per email address holds the
  credentials (email, password). A **member** profile and an **administrator** profile can
  each be attached to it; a person
  with both chooses one after signing in and can switch later. The profile in use is kept in
  the session and shown in the layout.
- Consequences: one password per person, whichever profile they use; changing a member's
  email changes it for the administrator profile too; an inactive member who is also an
  administrator can still sign in, but only as administrator.
- **Administrator** has no household, cannot hold a member profile's permissions and places no
  orders. A global authorization rule lets administrators pass every check.
- The **owner** is the administrator created by a console command from an email and name;
  the command sends them a magic link and refuses to run if an owner already exists. There is
  exactly one owner.
- Only the owner creates, deactivates and reactivates other administrators. The owner cannot
  be deactivated or removed from the app; changing the owner is done on the server.
- Administrators created in the app are sent a magic link. An administrator with no password
  is sent to a "set your password" screen after signing in and cannot continue until they set
  one.
- An **inactive administrator** cannot act as administrator (and cannot sign in unless they
  also have an active member profile); deactivation ends their open administrator sessions.

### Sign-in

- **Magic link:** requesting one stores a single-use token (hashed) with a 6-digit code,
  valid 15 minutes, bound to the browser session that asked for it. Opening the link on any
  device signs in that device; typing the code is only accepted in the requesting session.
  Using either consumes the token. Requesting a new link invalidates earlier unused ones.
- **Password:** standard email + password sign-in through Fortify (ADR 0006); any minimum length is enforced with
  Laravel's password rules.
- The sign-in screen offers both ways; there is no separate "forgot password" flow.
- Responses never reveal whether an email exists. Link requests, password attempts and code
  attempts are rate-limited per email and per IP.
- Inactive members cannot sign in (unless also active administrators, as above).
  Deactivation invalidates the member's existing sessions.
- Sessions are long-lived (weeks) as stated in the principles.

### Invitations

- An invitation belongs to a household and has an email, a hashed single-use token and an
  expiry 7 days after sending. States: pending, accepted, cancelled, expired (derived from
  the expiry).
- Inviting an email already used by a member is rejected. Inviting an email with a pending
  invitation replaces the old invitation.
- **Resend** creates a new token and expiry; the previous link stops working. **Cancel**
  makes the link unusable.
- **Accepting** asks for the name (pre-filled if given when inviting) and a password, creates
  the member in the invitation's household (attaching to an existing user if the
  email already belongs to an administrator), marks the invitation accepted and signs the
  person in.
- Invitation, magic link and email-change emails are sent through the queue after the
  transaction commits.

### Households and members

- **Household:** household number, editable name, active flag. The household number is an
  auto-increment column separate from the UUIDv7 primary key (ADR 0005): assigned by the
  database on creation, never chosen, edited or reused.
- Every table in this spec uses UUIDv7 primary keys (ADR 0005); membership of commissions and
  role assignments are pivot tables with composite keys.
- Deactivating a household deactivates all its members and removes them from commissions.
  When balances exist (a later spec), deactivation with a non-zero balance will require a
  warning and confirmation as stated in the principles; this spec only leaves room for that.
- Reactivating a household keeps its number and data; its members stay inactive until
  reactivated one by one. A member can only be reactivated while their household is active.
- A member with the membership role cannot deactivate themselves or their own household: the
  app rejects it and hides the action, so they cannot lose the role by mistake. Administrators
  are not restricted (DRA-34).
- **Member:** name, email, optional phone, household, active flag. Belongs to exactly one
  household. Moving a member changes only their household; orders and balances (later specs)
  stay with the household they belong to.
- **Own email change:** stored as pending, a confirmation link (single use, time-limited) is
  sent to the new address, and the email changes only when it is opened. Changes made by the
  membership role apply immediately.
- Emails are unique across sign-in identities, compared case-insensitively.

### Commissions and roles

- **Role** is a fixed enumeration in code: orders, boxes, treasury, membership. Policies only
  ever check roles (or administrator), never commission names.
- **Commission:** name (unique). Many-to-many with roles and with members.
- Creating, renaming, deleting commissions and changing who is in them need the membership
  role (or administrator). Deleting is rejected while the commission has members; deleting
  removes its role assignments.
- Only administrators assign or remove roles on commissions.
- Only active members can be added to a commission; deactivating a member removes them from
  all commissions.
- Every member can see the list of commissions and the names of who is in each one. Contact
  details still follow the directory rule below.
- A single authorization module answers "does this member have role X?" from their active
  commissions; it is the only place that knows how roles reach members, and every policy
  uses it.

### Directory and warnings

- The directory (all households, members, contact details) is visible to administrators and
  to members who belong to at least one commission. Other members only see their own
  household.
- The households list for the membership role flags active households with no active member
  in any commission. It is informational only.

### General

- All user-facing text lives in translation files, Catalan only, using the Catalan UI words
  from `CONTEXT.md`.
- Server-side validation and policies on every action; pages are Inertia + Vue 3 (TS). None
  of these screens work offline.
- Built on the Laravel Vue starter kit with Fortify (ADR 0006): registration, two-factor
  authentication and password reset are disabled and their screens removed; the magic link,
  the code and invitations are our own code.

## Testing Decisions

- **One seam: HTTP.** Pest feature tests drive the app through HTTP requests and assert only
  what is observable from outside: status, redirects, Inertia page and props, session errors,
  database state, emails sent (mail fake) and whether the user is signed in. No tests of
  internal classes, models or private helpers.
- The console command that creates the owner is tested with Pest through the command
  interface (output, created owner, email sent, refusal when an owner exists).
- Time-dependent rules (15-minute magic link, 7-day invitation, email confirmation) are tested
  by moving the clock, not by waiting.
- Each permission rule is tested from both sides: an allowed actor succeeds and a forbidden
  one (member without the role, membership role trying to assign roles, inactive member…) is
  refused.
- Tests run against a real PostgreSQL database (DBngin locally, a service container in CI).
- **One Playwright test** for the critical end-to-end path: open an invitation → confirm name
  and set password → sign out → request a magic link → sign in by typing the 6-digit code.
- Prior art: none yet; this is the first feature of the codebase, so its tests set the
  pattern for later specs.

## Out of Scope

- Orders, catalogue, producers, order cycles, boxes, balances, payments and charges (later
  specs). The balance warning when deactivating a household arrives with balances.
- Changing the owner from the app (done on the server).
- Open sign-up, social or SSO sign-in, two-factor authentication.
- Deleting or anonymising households or members.
- Notifications about households without a commission member beyond the in-app warning.
- Migrating data from the current software.
- Shift calendars, tasks and reminders for commissions.
- Any language other than Catalan in the UI (only the translation-file structure is in
  scope).
- Offline use of any of these screens.

## Further Notes

- The user shared by member and administrator profiles is this spec's reading of
  ADR 0004 ("the same email can be both… the person chooses which one to act as"), confirmed in
  the spec review of 2026-10-02.
