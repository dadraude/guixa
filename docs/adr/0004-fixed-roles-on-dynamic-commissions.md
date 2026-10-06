# 0004 — Fixed roles on dynamic commissions, and an administrator outside households

- Status: accepted
- Date: 2026-10-01
- Amended by [ADR 0008](0008-members-created-by-hand.md): apart from the owner, every
  administrator is also a member; the two profiles stay separate.

Commissions are fully dynamic: the membership commission creates, renames and deletes them and
decides who is in each one. Permissions do not belong to commissions directly; they come from
a small set of **roles** fixed in code (orders, boxes, treasury, membership) that only an
**administrator** assigns to commissions. A commission can hold zero or more roles and a role
can be held by several commissions; all members of a commission share its roles. The
administrator is a separate kind of account outside any household. It can do everything and
is the way to bootstrap the app and to recover from lock-outs. The same email can be both a
member and an administrator; the person chooses which one to act as when signing in.

## Considered options

- **Commissions fixed in code:** simplest, but every new or merged commission needs a deploy.
- **Configurable permission matrix per commission:** most flexible, but much more code and
  UI, and easy to misconfigure.
- **Superadmin flag on a member:** no separate account, but mixes household and
  administration concerns and makes "member" mean two things.

## Consequences

- Policies check roles, never commission names, so commissions can change freely.
- The membership role cannot grant itself other roles: only administrators assign roles.
- The first administrator is created with an Artisan command; later ones from the app.
  Amended by [ADR 0008](0008-members-created-by-hand.md): later administrators are members
  to whom the owner adds the administrator profile; none is created by email.
