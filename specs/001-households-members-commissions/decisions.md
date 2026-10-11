# 001 — Households, members and commissions: decisions

Outcome of the `/grill-with-docs` session on 2026-10-01. Input for `/to-spec`, which writes
`spec.md` in this folder. Terms are defined in [`CONTEXT.md`](../../CONTEXT.md); see also
[ADR 0004](../../docs/adr/0004-fixed-roles-on-dynamic-commissions.md).

## Scope

Households, members, invitations, sign-in (magic link and password), commissions, roles and
administrators. Invitations are superseded by the amendment at the end of this file.

## Access and bootstrap

- An **administrator** is outside any household, can do everything and places no orders.
  The first one is created with an Artisan command; administrators can create others in the
  app.
- The same email can be both a member and an administrator; after signing in the person
  chooses which one to act as.
- **Sign-in:** a magic link (single use, valid 15 minutes) that also carries a 6-digit code
  to type on the device that asked for it; or a password.
- The password is required when accepting the invitation (members) or on first access
  (administrators), so both ways always work. It can be changed later. A forgotten password
  is handled by signing in with the magic link.
- **Invitations:** valid 7 days, single use. On opening it the member confirms their name and
  sets a password. Members with the membership role see pending invitations and can resend (the
  previous link stops working) or cancel them.

## Households and members

- **Household:** name and household number. The number is chosen by a member with the
  membership role, unique across all households (including inactive ones) and never reused.
- **Member:** name, email, optional phone. Belongs to exactly one household; members with the
  membership role can move them to another household (orders and balance stay with the old one).
- Members edit their own name, phone and password; changing their email requires confirming
  the new address. Members with the membership role can edit everything, including the email
  without confirmation.
- **Inactive member:** cannot sign in, leaves all commissions, history is kept; can be
  reactivated. Deactivating a household makes all its members inactive.
- An inactive household can be reactivated with the same number and balance; its members are
  reactivated one by one.

## Commissions and roles

- Commissions are fully dynamic (create, rename, delete). The membership role and
  administrators manage them and who is in each one. A commission can only be deleted when
  it has no members. Roles belong to the app, not to commissions: deleting a commission only
  removes the roles it was assigned, never the roles themselves.
- Four **roles** fixed in code:
  - **Orders:** catalogue, producers, order cycles, producer deadlines.
  - **Boxes:** packing boxes.
  - **Treasury:** payments and balances of all households.
  - **Membership:** households, members, invitations, commissions and who is in them.
- Only administrators assign roles to commissions. A commission holds zero or more roles; a
  role can be held by several commissions. All members of a commission share its roles.
- Every household should have at least one member in some commission. The app only warns the
  members with the membership role about households that don't; it blocks nothing.
- Any member of any commission sees the directory with contact details. Other members only
  see their own household.

## Spec review (2026-10-02)

- Confirmed: one user (sign-in identity) per email, with a member and/or an administrator profile.
- Kept: rate limits on sign-in, responses that never reveal whether an email exists, no
  invitations to an email that already belongs to a member, and deactivation ends sessions.
- **Owner:** the administrator created with the Artisan command (exactly one). Only the owner
  creates, deactivates and reactivates other administrators; the owner cannot be deactivated
  from the app.
- **Household number:** an auto-increment number (separate from the UUIDv7 primary key, ADR 0005), assigned on creation, never chosen,
  edited or reused (replaces "chosen by a member with the membership role"). The name stays
  editable.
- Every member sees all commissions and the names of their members; contact details still
  follow the directory rule.

## Amendment (2026-10-06, DRA-46)

Outcome of the `/grill-with-docs` session on 2026-10-06; supersedes the invitation decisions
above. See [ADR 0008](../../docs/adr/0008-members-created-by-hand.md).

- **No invitations.** The owner, administrators and members with the membership role create
  members by hand: name, email, optional phone, and optionally household and commissions.
- **First access:** a welcome email with no token points to the sign-in screen; a password is
  required on first access.
- **Member without a household:** roles apply with or without a household; a household is
  needed for "my household" and to order. With no household and no commission, only a waiting
  screen (edit name, phone, email, password; sign out). Commissions can
  be assigned before or after the household.
- **One "change household" action** assigns the first household or moves the member (membership
  role or administrator, active households only, target household locked). A member can be
  removed from their household and waits again; they keep their commissions.
- Members without a household can be deactivated and reactivated.
- The members list shows everyone and the active members without a household; the directory
  lists only households and their members.
- **Administrators:** ADR 0004 stays (two profiles). Only the owner adds the administrator
  profile to an active member; creating administrators by email is removed. A person whose
  member profile has no household signs in straight as administrator.

### Spec review of the amendment (2026-10-06)

- **D1/D2 — Emails by levels.** Each person changes their own email with confirmation. For
  someone else: the owner can change anyone's; an administrator, members who are not
  administrators; the membership role, members who neither hold the membership role nor are
  administrators. Such changes apply immediately (fixes typos at creation). Emails stay unique.
- **D3 — Inactive member of an inactive household** can be moved with "change household" to
  an active one and reactivated there. Only active members can be removed from a household.
- **D4 — Profile changes mid-session:** a member profile with no household and no commission
  is not an available profile while the person is an administrator; gaining one keeps them
  acting as administrator with the switcher shown; losing both falls back to administrator.
- **D5/D6 — Active or inactive belongs to the user,** not to a profile. An inactive person
  cannot sign in with any profile. Being an administrator is a yes/no the owner adds or
  removes; there is no inactive administrator. Deactivation keeps the administrator profile.
- **D7 — The membership role** can change the household of, or remove from their household,
  any member, including themselves and administrators.
- **D8 — Roles apply with or without a household.** A household is needed for "my household"
  and to order. Only a member with no household and no commission sees the waiting screen.
- **D9 — DRA-34 stays:** the membership role cannot deactivate themselves or their own
  household, and cannot deactivate a member who is an administrator.
- **Model:** two profiles (member, administrator) on one user; the owner is an administrator,
  not a third profile.

## Amendment (2026-10-09, DRA-87)

Inactive only blocks sign-in; see [ADR 0011](../../docs/adr/0011-inactive-only-blocks-sign-in.md).
Supersedes these earlier decisions:

- "Inactive member … leaves all commissions": an inactive member keeps their household,
  commissions and roles; they only cannot sign in.
- "Deactivating a household makes all its members inactive": it asks whether to deactivate its
  active members too; those not deactivated are treated as members without a household.
- "Its members are reactivated one by one": reactivating a household asks whether to
  reactivate its inactive members too; otherwise they are reactivated one by one.
- D3 "Only active members can be removed from a household": active and inactive members can be.
- D9 "cannot deactivate … their own household": deactivating a household alone is always
  allowed, also one's own and the owner's; with its members, the protected ones are skipped
  and named.
- Spec review of 2026-10-02 "Only the owner creates, deactivates and reactivates other
  administrators": already replaced by DRA-46 (the owner adds and removes the administrator
  profile; active or inactive belongs to the person).

## Spec review (2026-10-11)

Review of the approved spec against the code, to decide gaps before tickets reopen them.

- **Commissions:** the membership role adds anyone, themselves included, to any commission
  whatever its roles; only administrators decide what each commission may do (ADR 0004).
  Accepted consequence: an inactive member in a commission holding the membership role can then
  only be reactivated by an administrator or the owner.
- **Magic link code:** after 5 wrong codes for the same token the code stops being accepted;
  the emailed link keeps working.
- **Rate limits:** 5 per minute per email and IP, 20 per minute per IP, shared by link
  requests, codes and passwords; no limit per email alone, so nobody can lock someone else out.
- **Links opened in a signed-in browser:** a magic link of someone other than the signed-in
  user asks to sign out first (button), signs out nobody and is not consumed; of the same user,
  it is consumed and goes home. The email-change confirmation is public, ignores the session
  and always shows the same page.
- **Own email change:** deactivating cancels the pending change; an inactive person's
  confirmation link is invalid; confirming invalidates magic links sent to the old address but
  ends no session.
- **Password change:** ends the person's other sessions and rotates the remember token; the
  15-minute window after a magic link sign-in (no current password needed) is recorded.
- **"My household"** lists inactive members marked "Inactive"; the directory never lists them.
- Obvious answers written straight into the spec: commission deletion vs. adding a member
  (row lock), reactivation re-checked under lock, idempotent (de)activation and commission
  membership/roles, administrator powers only while acting as administrator, session
  lifetime and limits, field lengths, password rules, name uniqueness, pending email change
  rules, Catalan-collation sorting of every list of people, editing inactive records, the
  owner command's refusal and code-less magic link.
