# Guixa

A food cooperative of 20–60 households that buys from producers through a weekly order cycle.

## Language

### Membership

**Household**:
The consumer unit of the cooperative (a family, a shared flat or a single person); it owns orders and is the unit of membership of the cooperative.
_Catalan (UI)_: unitat familiar
_Avoid_: Consumer unit, family, account, unitat de consum, caixa

**Member**:
A person who belongs to a household and uses the app with their own login.
_Catalan (UI)_: membre
_Avoid_: User, sòcia (ambiguous between person and household)

**Invitation**:
The email a member with the membership role sends to a new member so they can join their household; there is no open sign-up.
_Catalan (UI)_: invitació
_Avoid_: Registration, sign-up

**Inactive household**:
A household that has left the cooperative; its data and balance are kept, and it can no longer place orders.
_Catalan (UI)_: unitat familiar inactiva
_Avoid_: Deleted household, former member

**Administrator**:
A person who can do everything in the app without belonging to any household; it is not a member and places no orders.
_Catalan (UI)_: administrador/a
_Avoid_: Superadmin, root, admin member

**Inactive member**:
A member who has left the cooperative while their household stays; they can no longer sign in and leave all commissions, but their history is kept. All members of an inactive household are inactive.
_Catalan (UI)_: membre inactiu
_Avoid_: Deleted member, removed member

**Household number**:
A unique, never-reused number given to each household by a member with the membership role, used to label boxes and in lists.
_Catalan (UI)_: número d'unitat familiar
_Avoid_: Household ID, member number

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
