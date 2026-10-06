# 001 — Households, members and commissions

Status: approved

Decisions come from [`decisions.md`](decisions.md) (grilling session of 2026-10-01, amended
on 2026-10-06 for DRA-46: members are created by hand, without invitations, and may have no
household yet; see [ADR 0008](../../docs/adr/0008-members-created-by-hand.md)). Terms are
defined in [`CONTEXT.md`](../../CONTEXT.md); see also
[ADR 0004](../../docs/adr/0004-fixed-roles-on-dynamic-commissions.md) and
[`principles.md`](../../docs/principles.md).

## Problem Statement

The cooperative cannot use Guixa for anything until it knows who belongs to it. Today the
list of households, who is in each one, who can sign in and who is in which commission lives
in the old, unmaintained software and partly by hand. Commissions change over time (new ones
appear, others merge), but the work each one is allowed to do in the app must stay under
control. There is also no safe way to start: someone has to exist before anyone else can be
created, and there must be a way out if the people managing membership lose access.

## Solution

Guixa gets the membership foundation every later feature builds on:

- An **administrator**, acting outside any household, can always do everything. The first
  one, the **owner**, is created from the command line and is the only one who manages the
  other administrators: they are members to whom the owner adds the administrator profile.
  The owner can never be deactivated from the app.
- Members with the **membership** role (and administrators) create **households** (with a
  name; the app assigns the **household number**) and create **members** by hand: name, email,
  optional phone, and optionally their household and commissions. There is no invitation and
  no open sign-up.
- A new member gets a welcome email pointing to the sign-in screen. They sign in with a
  **magic link** (which also carries a 6-digit code) or with their password, and set a
  password on first access.
- A member with no household and no commission only sees a waiting screen (and can change the
  light/dark theme) until the membership commission assigns one. Roles apply with or
  without a household; a household is needed to see "my household" and, later, to order.
- **Commissions** are created, renamed and deleted freely, and members are added to them.
  Permissions never come from a commission's name: only administrators assign one or more of
  four fixed **roles** (orders, boxes, treasury, membership) to commissions, and every member
  of a commission gets its roles.
- Households and members can be made inactive and reactivated without losing history.
- Members in any commission see a directory with the contact details of every household and
  its members; others see only
  their own household. Members with the membership role are warned about households that have
  nobody in any commission.

## User Stories

### Bootstrap and administrators

1. As the person installing Guixa, I want to create the owner with a command on the server,
   so that someone can sign in to an empty app.
2. As the owner just created, I want to receive a sign-in email, so that I can reach the app
   without knowing a password yet.
3. As anyone without a password yet (the owner or a new member), I want to be asked to set
   one on my first access, so that both sign-in methods always work for me.
4. As the owner, I want to add the administrator profile to an active member in the app, so
   that the cooperative does not depend on a single person or on server access.
5. As the owner, I want to remove the administrator profile from a member, so that people who
   no longer help with administration lose that access while their history is kept.
6. As the owner, I want to add the administrator profile again to a returning helper, so that
   they do not need a new account.
7. As the owner, I want to be the only one who can add and remove administrators, so that
   administrators cannot remove each other or me.
8. As the owner, I want to be impossible to deactivate or remove from the app, so that the
   cooperative always keeps a way in.
9. As an administrator, I want to do everything any role allows, so that I can fix any
   problem or recover from a lock-out.
10. As an administrator, I want acting as administrator to be outside any household and to
   place no orders, so that administration is not mixed with being a member.
11. As a person who is both a member and an administrator with the same email, I want to
   choose which one to act as after signing in (unless my member profile has no household,
   story 66), so that I use the right permissions for the
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

### Creating members

24. As a member with the membership role, an administrator or the owner, I want to create a
    member with a name, an email and an optional phone, so that people join the cooperative
    without open sign-up.
25. As whoever creates a member, I want to optionally choose their household and commissions
    in the same form, so that a new family is set up in one go.
26. As a newly created member, I want a welcome email that points me to the sign-in screen,
    so that I know I can get in; it carries no sign-in link that could expire or leak.
27. As a newly created member, I want to be asked to set a password on my first access, so
    that both sign-in methods always work for me.
28. As whoever creates a member, I want to be stopped from using an email that already
    belongs to a member, so that one person does not end up with two accounts.
29. As a member with no household and no commission, I want a screen telling me the
    membership commission will assign me a household, where I can only edit my name, phone, email and password, change the theme and sign out, so
    that I know my account works while nothing else is open to me yet.
30. As a member of a commission without a household (e.g. while moving house), I want to keep
    doing my commission's work, so that leaving a household never takes my roles away.
31. As a member with the membership role, I want to see the active members without a
    household, so that I can assign them one.

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
43. As a member with the membership role, I want to edit any member's name and phone, and the
    email of members who neither hold the membership role nor are administrators, so that I
    can fix details for people who cannot do it themselves without being able to take over a
    colleague's or an administrator's account.
44. As a member with the membership role, I want one "change household" action that assigns a
    member's first household or moves them to another active one, so that people who join or
    change household stay in the cooperative; past orders and balance stay with the old
    household.
45. As a member with the membership role, I want to deactivate a member who leaves, with or
    without a household (also someone who never joined), so that they can no longer sign in.
46. As a member with the membership role, I want deactivating a member to remove them from
    every commission and end their open sessions, so that they lose access at once.
47. As a member with the membership role, I want to reactivate an inactive member, so that a
    person who comes back keeps their history.
48. As a member with the membership role, I want to be prevented from reactivating a member of
    an inactive household, so that the household is reactivated first; a member without a
    household can always be reactivated, and an inactive member can first be moved to an
    active household and then reactivated there.

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

61. As a member of any commission, I want a directory of all households and their members
    with their contact details, so that I can reach people for commission work.
62. As a member who is not in any commission, I want to see only my own household, so that
    other people's contact details are kept private.
63. As a member with the membership role, I want to see which active households have no member
    in any commission, so that I can encourage them to take part; nothing is blocked.

### Language

64. As a member, I want every screen and email in Catalan, using the agreed words
    ("unitat familiar", "membre", "comissió", "rol"…), so that the app speaks the
    cooperative's language.

### Members without a household and administrators (DRA-46)

65. As a member with the membership role, I want to remove a member from their household, so
    that someone who leaves it but stays in the cooperative waits for a new one; past orders
    and balance stay with the household and they keep their commissions.
66. As a person who is an administrator and a member without a household, I want to sign in
    straight as administrator, so that I am not offered a profile that cannot do anything.

## Implementation Decisions

### Identity, administrators and members

- One **user** (the sign-in identity, see `CONTEXT.md`) per email address holds the
  credentials (email, password). A **member** profile and an **administrator** profile can
  each be attached to it; a person
  with both chooses one after signing in and can switch later. The profile in use is kept in
  the session and shown in the layout.
- **Active or inactive belongs to the user (the person), not to a profile (DRA-46).** An
  inactive user cannot sign in with any profile, and deactivating them ends all their
  sessions. Being an administrator is a yes/no the owner adds or removes; there is no
  inactive administrator.
- Consequences: one password per person, whichever profile they use; changing a member's
  email changes it for the administrator profile too; deactivating a member (or their
  household) also stops them acting as administrator. Deactivation does not remove the
  administrator profile: reactivating the person brings it back unless the owner removed it.
- **Administrator** acts outside any household, cannot hold a member profile's permissions
  while acting as administrator and places no orders. A global authorization rule lets
  administrators pass every check.
- The **owner** is the administrator created by a console command from an email and name;
  the command sends them a magic link and refuses to run if an owner already exists. There is
  exactly one owner.
- Only the owner adds and removes other administrators. The owner cannot be deactivated or
  removed from the app; changing the owner is done on the server.
- Administrators are no longer created by email: the owner adds the administrator profile to
  an existing **active** member (ADR 0008). Removing it ends their open administrator sessions
  and keeps the history of what they did. Apart from the owner, every user has a member
  profile.
- Any user with no password (the owner, a new member) is sent to a "set your password" screen
  after signing in and cannot continue until they set one; after it, a member with no
  household and no commission goes to the waiting screen.
- Adding the administrator profile sends no email. The owner's picker lists only active
  members who are not already administrators.
- A person with both profiles whose member profile has no household and no commission is not
  offered the choice: while they have an administrator profile, such a member profile is not
  an available profile, and they act as administrator. If a household or commission is
  assigned mid-session they keep acting as administrator and the profile switcher appears; if
  they are acting as member and lose both, the next request falls back to administrator.

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
- Inactive users cannot sign in with any profile. Deactivation invalidates all their
  existing sessions.
- Sessions are long-lived (weeks) as stated in the principles.

### Creating members

- There are no invitations (ADR 0008). The owner, administrators and members with the
  membership role create members by hand: name, email, optional phone, and optionally a
  household (active households only) and commissions.
- Creating a member with an email that already belongs to a member is rejected. If the email
  belongs to a user without a member profile (only the owner can be one), the member profile
  is attached to that user and no welcome email is sent. An email that belongs to an inactive
  member is rejected with a message pointing to reactivating that member.
- A double submit or a race on the same email ends as a validation error on the email field.
- New members are created active. Creating a member with a household locks that household as
  "change household" does; a household that is no longer active, or a household or commission
  that no longer exists when the form is submitted, gives a validation error.
- Creating a member sends a welcome email with a link to the sign-in screen and no token;
  there is no "resend", since the person can always ask for a magic link there.
- Welcome, magic link and email-change emails are sent through the queue after the
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
- **Member:** name, email, optional phone, optional household, active flag. Belongs to at
  most one household. One "change household" action assigns the first household or moves the
  member; it needs the membership role (or administrator), accepts only active households and
  locks the target household. Removing a member from their household leaves them without
  one and keeps their commissions; only active members can be removed. "Change household"
  also works on an inactive member, who stays inactive until reactivated. Orders and balances (later specs) stay with the household
  they belong to.
- **Member without a household:** their commissions and roles work as for any member (D8 of
  the DRA-46 amendment): they can do their commission's work and, being in a commission, see
  the directory. Without a household they have no "my household" page and, later, cannot
  order. A member with **no household and no commission** signs in to a waiting screen where
  they can only edit their name, phone, email (with confirmation) and password, change the
  light/dark theme, and sign out.
  Members without a household can be deactivated and reactivated; the "household must be
  active" rule only applies when they have one.
- A member with the membership role can change the household of, or remove from their
  household, any member, including themselves and members who are administrators.
- The DRA-34 rule stays for deactivation, and a member with the membership role cannot
  deactivate a member who is an administrator (that would stop them acting as administrator,
  story 7); only administrators and the owner can.
- Members of a commission are listed on the commissions page whether or not they have a
  household.
- Removing a member from their household takes effect on their next request; if they are left
  with no household and no commission, it redirects to the waiting screen, and pages they
  already have open are refused or redirect there.
- Two simultaneous "change household" actions on the same member: the last one wins; each one
  checks under the lock that its target household is active.
- **Own email change:** stored as pending, a confirmation link (single use, time-limited) is
  sent to the new address, and the email changes only when it is opened.
- **Changing someone else's email works by levels (DRA-46)**, because the email is shared with
  the administrator profile and controls sign-in: the owner can change anyone's; an
  administrator can change the email of members who are not administrators; a member with the
  membership role can change the email of members who neither hold the membership role nor
  are administrators. Nobody else can change another person's email, and the owner's email is
  changed only by the owner. A change made for someone else applies immediately, without
  confirmation (so a mistyped email can be fixed); emails stay unique.
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
  commissions, with or without a household; it is the only place that knows how roles reach members, and every policy
  uses it.

### Directory and warnings

- The directory (all households and their members, with contact details; members without a
  household are not listed) is visible to administrators and
  to members who belong to at least one commission. Other members only see their own
  household.
- The households list for the membership role flags active households with no active member
  in any commission, counting only the members currently in the household. It is
  informational only.
- The members list for the membership role shows every member and has a filter or section for
  the active members without a household, with the same sorting and inactive handling as the
  rest of the list.

### General

- All user-facing text lives in translation files, Catalan only, using the Catalan UI words
  from `CONTEXT.md`.
- Server-side validation and policies on every action; pages are Inertia + Vue 3 (TS). None
  of these screens work offline.
- Built on the Laravel Vue starter kit with Fortify (ADR 0006): registration, two-factor
  authentication and password reset are disabled and their screens removed; the magic link,
  the code and member creation are our own code.

## Testing Decisions

- **One seam: HTTP.** Pest feature tests drive the app through HTTP requests and assert only
  what is observable from outside: status, redirects, Inertia page and props, session errors,
  database state, emails sent (mail fake) and whether the user is signed in. No tests of
  internal classes, models or private helpers.
- The console command that creates the owner is tested with Pest through the command
  interface (output, created owner, email sent, refusal when an owner exists).
- Time-dependent rules (15-minute magic link, email confirmation) are tested
  by moving the clock, not by waiting.
- Each permission rule is tested from both sides: an allowed actor succeeds and a forbidden
  one (member without the role, membership role trying to assign roles, inactive member…) is
  refused.
- Tests run against a real PostgreSQL database (DBngin locally, a service container in CI).
- **One Playwright test** for the critical end-to-end path: a member is created with a
  household → the welcome email arrives → request a magic link → sign in by typing the 6-digit
  code → set a password → sign out → sign in with the password.
- Prior art: none yet; this is the first feature of the codebase, so its tests set the
  pattern for later specs.

## Out of Scope

- Orders, catalogue, producers, order cycles, boxes, balances, payments and charges (later
  specs). The balance warning when deactivating a household arrives with balances.
- Changing the owner from the app (done on the server).
- Open sign-up, invitations, social or SSO sign-in, two-factor authentication.
- Emailing the membership commission when a member is created without a household.
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
- **Amendment of 2026-10-06 (DRA-46):** invitations are removed and members are created by
  hand (ADR 0008); members may have no household; administrators are added by promoting
  members. The app is not in production, so no data migration is needed; the invitation code
  and tests built in DRA-15/DRA-16 and the e2e path built in DRA-23 are replaced.
