# 001 — Households, members and commissions: decisions

Outcome of the `/grill-with-docs` session on 2026-10-01. Input for `/to-spec`, which writes
`spec.md` in this folder. Terms are defined in [`CONTEXT.md`](../../CONTEXT.md); see also
[ADR 0004](../../docs/adr/0004-fixed-roles-on-dynamic-commissions.md).

## Scope

Households, members, invitations, sign-in (magic link and password), commissions, roles and
administrators.

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
