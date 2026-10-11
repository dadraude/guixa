# 0008 — Members are created by hand, without invitations

- Status: accepted
- Date: 2026-10-06
- Amended 2026-10-07 (DRA-75): the owner is also a member, so every user has a member profile.

Members join the cooperative because someone creates them by hand: the owner, an
administrator or a member with the membership role enters their name, email and optional
phone, and optionally their household and commissions. There is no invitation and no open
sign-up. The new member gets a welcome email that points to the sign-in screen and carries no
token; they sign in with a magic link or code and set a password on first access. A member may
have no household yet: with no household and no commission they only see a waiting screen;
their roles apply with or without a household, which is needed to order. New administrators are not created by email either: the owner adds the
administrator profile to an existing active member.

## Considered options

- **Email invitation tied to a household (the first version of spec 001, built in DRA-15 and
  DRA-16):** the person confirms their name and sets a password from a 7-day, single-use link.
  More code (tokens, expiry, resend, cancel, pending list) for a cooperative of 20–60
  households where the membership commission meets every new family anyway, and it forced the
  household to be decided before the person joined.
- **Invitation without a household:** kept the token machinery and still left the commission
  creating households and assigning people by hand.
- **Welcome email with a magic link:** the link expires after 15 minutes, so it fails whenever
  the person reads the email later.
- **Initial password set by whoever creates the member:** someone else would know the
  person's password.

## Consequences

- One way in for everyone except the owner (created on the server, as an active member with no
  household plus the administrator profile): being created as a member. Every user has a member
  profile, so creating or updating a member with an email that already exists is always rejected.
- No sign-in token is ever emailed on creation; the existing magic link and code are reused.
- "Member" no longer implies a household: every rule that needs one (roles, directory, orders)
  must check for it.
- The invitation code, its tests and the invitation-based e2e path are removed.
- Active or inactive belongs to the user, not to a profile: an inactive person cannot sign in
  at all, and being an administrator is a yes/no that only the owner adds or removes.
- Amends the last consequence of [ADR 0004](0004-fixed-roles-on-dynamic-commissions.md).
- Removing the administrator profile deletes the `administrators` row and adding it again creates a new id, so any future record of administrator actions references `user_id`, never `administrators.id`.
