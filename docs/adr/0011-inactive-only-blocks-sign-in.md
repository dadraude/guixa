# 0011 — Inactive only blocks sign-in

- Status: accepted
- Date: 2026-10-09

Being inactive changes one thing about a person: they cannot sign in (with any profile, so
also not as administrator), and their open sessions end. Everything else stays: their
household, their commissions and the roles those give, their history. Being inactive changes
one thing about a household: its household access goes (no "my household", and later no
ordering). Its members are not touched unless the person deactivating it asks for it; an
active member of an inactive household is treated exactly as a member without a household and
keeps their `household_id`, so reactivating the household gives it back to them. Reactivating
a household likewise asks whether to reactivate its inactive members. A member can be
reactivated, and added to a commission, whatever their own or their household's state.

## Considered options

- **Deactivation cascades (the first version of spec 001):** deactivating a member removed them
  from every commission, and deactivating a household deactivated all its members. Someone who
  leaves for a while lost commission places that had to be rebuilt by hand, a household leaving
  took out members who stay in the cooperative (e.g. in a commission), and the cascade raced
  with moving a member to another household (DRA-86).
- **Cascade with exceptions:** keep the cascade but skip members in a commission. Harder to
  explain, and the same question comes back for every new kind of membership data.

## Consequences

- An inactive member can stay in commissions; their roles do nothing while they cannot sign in,
  but they still count when protecting that member: rules that stop the membership role from
  acting on a colleague (DRA-34, email-change levels) read the target's roles whether active or
  not, and reactivating a member with the membership role or the administrator profile is
  reserved to administrators. Otherwise anyone with the role could take over an inactive
  colleague's account by changing its email and reactivating it.
- "Member without a household" now also covers active members of an inactive household: every
  rule that needs a household must check that it is active, not only that it exists.
- The rule "a member can only be reactivated while their household is active" is dropped.
- Deactivating a household with its members re-checks, per member, that they are still in it
  when the action runs, so a member moved away meanwhile is not deactivated (DRA-86).
- Keeps ADR 0008's rule that active or inactive belongs to the user, not to a profile.
