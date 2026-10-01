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
  sets a password. The membership commission sees pending invitations and can resend (the
  previous link stops working) or cancel them.

## Households and members

- **Household:** name and household number. The number is chosen by the membership
  commission, unique across all households (including inactive ones) and never reused.
- **Member:** name, email, optional phone. Belongs to exactly one household; the membership
  commission can move them to another household (orders and balance stay with the old one).
- Members edit their own name, phone and password; changing their email requires confirming
  the new address. The membership commission can edit everything, including the email
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
  membership commission about households that don't; it blocks nothing.
- Any member of any commission sees the directory with contact details. Other members only
  see their own household.
