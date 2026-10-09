# Guixa

A food cooperative of 20–60 households that buys from producers through a weekly order cycle.

## Language

### Membership

**Household**:
The consumer unit of the cooperative (a family, a shared flat or a single person); it owns orders and is the unit of membership of the cooperative.
_Catalan (UI)_: unitat familiar
_Avoid_: Consumer unit, family, account, unitat de consum, caixa

**Member**:
A person of the cooperative who uses the app with their own login. A member may have no household yet (right after being created) and may be in no commission; a member with no household and no commission can only wait; a household is needed to order. Members are created by hand by the owner, administrators or members with the membership role; there is no invitation and no open sign-up.
_Catalan (UI)_: membre
_Avoid_: Sòcia (ambiguous between person and household); "user" when the member is meant

**User**:
The sign-in identity of a person (email and password), to which a member and/or an administrator are attached; it is never a synonym of member.
_Catalan (UI)_: (not shown; the UI says membre or administrador/a)
_Avoid_: Account, login

**Inactive household**:
A household that has left the cooperative; its data and balance are kept, and it can no longer place orders. Its members are not made inactive unless asked when deactivating it: an active member of an inactive household is treated as a member without a household.
_Catalan (UI)_: unitat familiar inactiva
_Avoid_: Deleted household, former member

**Administrator**:
A profile that can do everything in the app; acting as administrator is outside any household and places no orders. Every administrator is a member: the owner is created as a member by the console command, and the others are members to whom the owner has added the administrator profile.
_Catalan (UI)_: administrador/a
_Avoid_: Superadmin, root, admin member

**Owner**:
The one administrator created from the server when the app is installed; only the owner adds and removes other administrators, and the owner cannot be deactivated from the app.
_Catalan (UI)_: propietari/ària
_Avoid_: Superadmin, main administrator, root

**Inactive member**:
A member who has left the cooperative (or never joined after being created) while their household, if any, stays; they can no longer sign in with any profile (also not as administrator); nothing else changes: they keep their household, commissions and history (ADR 0011).
_Catalan (UI)_: membre inactiu
_Avoid_: Deleted member, removed member

**Household number**:
A unique number assigned automatically to each household when it is created (an auto-increment number, not the household's internal ID), never edited or reused, used to label boxes and in lists.
_Catalan (UI)_: número d'unitat familiar
_Avoid_: Household ID, member number, box number

**Commission**:
A group of members in charge of an area of the cooperative's work (e.g. orders, treasury, boxes, cleaning); it grants permissions only through the roles it holds. Every household is expected to have at least one member in a commission.
_Catalan (UI)_: comissió
_Avoid_: Committee, team, shift

**Role**:
A fixed set of permissions defined by the app (e.g. orders, boxes, treasury, membership) that an administrator assigns to commissions; a commission can hold several roles and a role can be held by several commissions. All members of a commission share its roles.
_Catalan (UI)_: rol (Comandes, Caixes, Tresoreria, Membres)
_Avoid_: Permission group, profile, coordinator

### Ordering

**Order cycle**:
One round, usually weekly, in which orders are placed, sent to producers and distributed.
_Catalan (UI)_: cicle de comanda
_Avoid_: Week, round

**Producer**:
A farm or supplier the cooperative buys from.
_Catalan (UI)_: productor/a
_Avoid_: Supplier, vendor

**Active producer**:
A producer whose products can be ordered in a given order cycle.
_Catalan (UI)_: productor/a actiu/iva
_Avoid_: Enabled producer, available producer

**Order**:
What one household asks for in one order cycle; it is saved as it is filled in and can be edited until each producer's ordering closes, with no separate submit step.
_Catalan (UI)_: comanda
_Avoid_: Basket, cart, purchase, cistella

**Producer deadline**:
The planned moment, per producer and order cycle, when ordering from that producer closes; members with the orders role can close it earlier or extend it.
_Catalan (UI)_: termini
_Avoid_: Cutoff, closing time

**Producer order**:
The total of all households' orders for one producer in one order cycle, sent to the producer by email when their ordering closes.
_Catalan (UI)_: comanda al productor
_Avoid_: Consolidated order, purchase order

**Box**:
The goods one household actually receives in one order cycle, with real quantities and weights recorded when packing.
_Catalan (UI)_: caixa
_Avoid_: Basket, delivery, cistella

**Catalogue**:
The stable list of products the cooperative can buy, of which a subset is offered in each order cycle.
_Catalan (UI)_: catàleg
_Avoid_: Product list, menu

**Weighed product**:
A product ordered as an estimated quantity whose final amount is set by its real weight when the boxes are packed.
_Catalan (UI)_: producte a pes
_Avoid_: Variable product, bulk product

### Money

**Balance**:
The running amount between a household and the cooperative; it can be in the household's favour or owed by it.
_Catalan (UI)_: saldo
_Avoid_: Account, credit, debt

**Payment**:
Money a household hands to the cooperative, recorded by a member with the treasury role, which increases its balance.
_Catalan (UI)_: pagament
_Avoid_: Top-up, deposit

**Charge**:
The final amount of a household's box, taken from its balance once the box is packed.
_Catalan (UI)_: càrrec
_Avoid_: Invoice, bill, debit
