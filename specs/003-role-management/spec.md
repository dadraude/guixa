# 003 — Role management

**Status: Draft (2026-11-08).** Supersedes the *Commissions* roadmap entry and its
[decisions](../../docs/commissions/2026-10-25-commission-decisions.md): staff powers stay fixed roles, like Finance.

A manager needs one place to see who holds each staff role and to grant or remove it. Today roles are toggled
per row on the accounts page, and only Manager and Finance exist. This feature adds two roles that take a
slice of the manager's work, Catalogue and Receiver, lets Finance run receptions, and gives every role
management action its own screen.

## Language

Terms from [001 — Weekly orders](../001-weekly-orders/spec.md#glossary) keep their meaning. Member is still
not a role (001 R35), and Producer is still given only through its producer's invitation (001 R36, R3a).

| Term | Català | Meaning |
| --- | --- | --- |
| Staff role | Rol de gestió | A role a manager grants to or removes from an account: Manager, Finance, Catalogue or Receiver. |
| Catalogue | Catàleg | Staff role that administers every producer's weekly and stock products, product classification and the return-item catalogue. |
| Receiver | Receptor/a | Staff role that receives a sent producer order: opens its reception, sets received totals and allocates to households. |
| Holder | Titular | An account that holds a given staff role. |

## Roles

The roles in 001 change as follows; R# below are this feature's.

- **Catalogue**: R3–R5. No producer, account, inventory movement, cycle or reception administration.
- **Receiver**: R6. No validation or distribution, and no cycle, basket, wallet or catalogue administration.
- **Finance**: keeps its 001 powers and also runs every reception step (R7).
- **Manager**: keeps every power it has, and also every Catalogue power, so it can now edit any producer's
  weekly products (R3). Manager is still the only role that grants or removes staff roles.

## Requirements

### Roles and holders

- **R1** Only a manager grants or removes the Manager, Finance, Catalogue and Receiver roles. Any account
  can hold any combination of them, a producer's account included. A role can be granted only to an active
  account; an account whose invitation is pending can receive it. Removing the Manager role from the last
  active manager is rejected (001 R36a).
- **R2** Catalogue and Receiver are login roles: an account holding either can log in with no household
  link (001 R35). A deactivated account keeps its roles and gets them back on reactivation (001 R36b);
  while deactivated it cannot receive a new role, and any of its roles can be removed.

### Catalogue

- **R3** Catalogue creates, edits and deactivates any producer's weekly products, under the same rules a
  producer has for its own (001 R1, R1c). The producer keeps every power it has over them; the last saved
  edit wins and nobody is notified. A Catalogue account that is also a producer's account edits other
  producers' products only through this role, never through its Producer area (001 R2).
- **R4** Catalogue administers stock products as the manager does (001 R25, R25a, R1c) and changes a
  product's stock flag under the manager's rule (001 R1b).
- **R5** Catalogue administers the return-item catalogue (001 R20a). It sees the producer list, without
  creating, editing, deactivating or reactivating producers or inviting their accounts (001 R3a, R36).

### Reception

- **R6** Receiver opens the reception of any sent producer order, adds unordered weekly products to it,
  sets received totals and allocates to households (001 R14–R16). It cannot validate a reception or mark
  it distributed, and cannot reopen it (001 R17a, R18). Changing a received total after validation clears
  the validation, as for any other actor (001 R17a).
- **R7** Finance and the manager run every reception step: R14–R16, validation (R17a), distribution and
  reopening (R18).
- **R8** Receiver and Finance see each cycle that has a sent producer order, with its receptions; they
  cannot open, cancel or change a cycle, its participating producers, cutoffs or sending (001 R4–R6).

### Role management screen

- **R9** A manager opens one screen grouped by staff role: Manager, Finance, Catalogue and Receiver, in that
  order. Each group lists its holders by name with their email, and marks an account whose invitation is
  pending or that is deactivated. Producer and household membership are not shown.
- **R10** **Granting.** From a group, the manager searches accounts by name or email. Results include only
  active accounts, pending invitations included, that do not hold that role yet. Choosing one grants the
  role and lists the account in the group.
- **R11** **Removing.** Each holder has a remove action that asks for confirmation before removing the role.
  For the last active manager the action is disabled and says why. When a manager removes its own Manager
  role, the confirmation warns that it will lose access to this screen; after removing it, the manager
  leaves the screen.
- **R12** The accounts page shows each account's roles but no longer grants or removes them; it links to
  the role management screen. Household, deactivation and invitation actions stay there.
- **R13** A grant or removal shows its result at once. If the state changed meanwhile (the role was already
  granted or removed, the account was deactivated, or it became the last active manager), the action is
  rejected with the reason and the screen shows the current holders.

### Areas and access

- **R14** The role switch gives Catalogue a *Catàleg* area: the producer list, and per producer its weekly
  and stock products. It gives Receiver a *Recepció* area: the cycles with a sent producer order and their
  receptions. Finance reaches the same receptions from its area. Each area shows only what the account's
  roles allow; a direct link to anything else is refused.
- **R15** On the dashboard, Catalogue and Receiver get a shortcut to their area and no new cards.
- **R16** New visible copy is translatable and Catalan is the default. The screen follows Guixa's visual
  system, works at 375 px, in light and dark appearance and at 200% text, by keyboard, with targets of at
  least 44 × 44 px.

## Acceptance scenarios

**S1 — Granting Catalogue (R1, R2, R10).** Given Anna is an active account with no household and no role,
when a manager searches "Anna" in the Catalogue group and chooses her, then she is listed as a holder and
can log in to the *Catàleg* area. She no longer appears in that group's search.

**S2 — Pending and deactivated accounts (R1, R2, R9, R10).** Given Bernat's invitation is pending and Carla
is deactivated while holding Receiver, when a manager opens the screen, then Carla is listed under Receiver
marked deactivated and can be removed; the Finance search offers Bernat, marked pending, and does not offer
Carla.

**S3 — Last manager (R1, R11).** Given Dolors is the only active manager, when she opens the screen, then her
remove action in the Manager group is disabled with the reason. Given Ernest is also an active manager,
when Dolors removes her own Manager role and confirms the warning, then she leaves the screen and can no
longer open it.

**S4 — Catalogue edits a weekly product (R3).** Given producer P's weekly product "Tomàquet" has lines in an
unsettled cycle, when a Catalogue account changes its price, then the price changes; when it tries to change
its unit, then it is rejected as it would be for P. P's later edit replaces the Catalogue edit.

**S5 — Producer account with Catalogue (R1, R3).** Given producer P's account holds Catalogue, when it uses
its Producer area, then it sees only P's products (001 R2); in the *Catàleg* area it edits producer Q's
weekly products.

**S6 — Catalogue limits (R4, R5).** Given a Catalogue account, when it opens the producer list, then it can
open any producer's products, change an unused product's stock flag and edit the return-item catalogue, and
it has no action to create or deactivate a producer, invite its account, record a stock entry or adjustment,
or manage a cycle.

**S7 — Receiver receives an order (R6, R8, R14).** Given producer P's order in cycle C is sent, when a
Receiver account opens P's reception from the *Recepció* area, sets a received total and changes a
household's allocation, then both are saved; it has no validate, distribute or reopen action, and no cycle
action.

**S8 — Finance closes a reception (R6, R7).** Given a Receiver recorded P's reception and its allocations are
balanced, when a Finance account validates it and marks it distributed, then P's order is distributed. If
the Receiver changed a received total after validation, the validation would be cleared first.

**S9 — Stale action (R13).** Given two managers have the screen open, when one removes Anna's Finance role
and the other then removes it too, then the second action is rejected with the reason and the screen shows
the current holders.

**S10 — Accounts page (R12).** Given an account with Manager and Catalogue, when a manager opens the accounts
page, then it shows both roles with no grant or remove action and links to the role management screen.

**S11 — Refused link (R14).** Given a Receiver-only account, when it opens a cycle's settings, a producer's
products or the role management screen by URL, then access is refused.

## Open questions

None.
