# 001 — Weekly orders: Stock, wallet and accounts

Part of [001 — Weekly orders](spec.md): glossary, time and roles are defined there.

Stock products and movements, household wallets, households, members and invitations.

## Requirements

### Stock

- **R25** A manager creates, edits and deactivates stock products: name, unit, price (≥ 0), variable flag,
  and containers per unit as for products (R1).
  A manager creates, edits and deactivates return items: name and amount (> 0).
- **R26** A manager records stock entries (quantity > 0, optional note, e.g. "bought from X").
- **R27** A manager records stock adjustments (positive or negative, mandatory note, e.g. "broken jar",
  "inventory count", optional cycle). Available stock can never go below 0.
- **R28** Available stock = Σ entries + Σ adjustments − Σ quantities sold at completed pickups.
  Stock movements are never edited or deleted.

### Wallet

- **R29** A manager records top-ups, at a pickup (R21) or on their own: positive amount, optional note
  (e.g. "transfer of the 3rd", "cash"). A top-up is dated when recorded and is not linked to a cycle.
- **R30** Balance = Σ top-ups + Σ credits − Σ charges ± corrections. It may be negative, with no limit.
- **R31** Members see their household balance and every movement: top-ups, charges and credits with their
  cycle, corrections with their cycle if any.
- **R32** Movements are never edited or deleted. Mistakes are fixed with a correcting movement
  recorded by a manager: a non-zero amount, a mandatory note and an optional cycle.

### Households and accounts

- **R33** Only a manager creates households, renames them, and links or unlinks members. There is no
  self sign-up.
- **R34** Adding a person sends them an email invitation to set their password. Until they accept it,
  they cannot log in. An invitation expires after 7 days. While it is not accepted, a manager can resend it
  (the previous link stops working) or change its email.
- **R35** A person belongs to at most one household.
  - Moving a member links the same account to the new household. The old household keeps its orders,
    pickups, balance and history; nothing moves with the person.
  - Unlinking a member keeps the account active, without a household; it can be linked again to any
    household, the same one included.
  - Records of what a person did (e.g. who added a line) keep naming them after a move.
- **R35a** A manager can deactivate a household at any time, even with a balance or pending pickups,
  after a warning listing what is left to settle. A household is deactivated automatically when its last
  member is unlinked or moved. On deactivation: its members are unlinked and nobody can order for it; its
  existing lines and pending pickups are kept, and a manager resolves them by hand (complete, or mark not
  collected). A deactivated household with a balance other than 0, or with pending pickups, stays in a
  *to settle* list for managers until both are resolved.
  A manager can reactivate a household at any time and link members to it (R35 allows linking to any
  household, an inactive one included, which reactivates it). While a household is deactivated its wallet
  still accepts top-ups and corrections. A household created without members is active.
- **R36** A manager creates producer accounts, with the same invitation flow. Each producer has exactly
  one account.
- **R36a** One account can hold several roles (e.g. member of H and manager). A manager grants or removes the
  manager role on any account, except removing it from the last manager. The first manager is created
  when the system is installed, outside the app.
- **R36b** A manager can deactivate and reactivate any account. A deactivated account cannot log in and is
  unlinked from its household (R35); its history is kept. The last active manager's account cannot be
  deactivated.

## Acceptance scenarios

### Stock

**S23 — Entries and adjustments** (R26, R27, R28)
Given honey has 0 jars
When the manager records an entry of 10 and an adjustment of −1 noted "broken jar"
Then 9 jars are available; an adjustment of −10 is rejected.

### Wallet and accounts

**S24 — Top-up and correction** (R29, R32)
Given H's balance is €0.00
When the manager records a €50.00 top-up and then a −€50.00 correction noted "duplicated"
Then H's balance is €0.00 and both movements are visible to H.

**S25 — Member invitation** (R33, R34)
Given the manager creates household H and adds anna@example.org as a member
When Anna opens the invitation link and sets a password
Then she can log in and sees H's order and wallet; before accepting, login is rejected
And a link older than 7 days, or replaced by a resent one, is rejected.

**S43 — Moving a member** (R35, R35a)
Given Anna and Biel are members of H, and H's balance is €12.00
When the manager moves Anna to household J
Then Anna logs in with the same account and sees J's order and wallet; H keeps its €12.00 with Biel
And when Biel is unlinked too, H is deactivated and listed as to settle.

**S44 — Several roles** (R36a)
Given Anna is a member of H
When a manager grants her the manager role
Then with one login she orders for H and manages cycles
And removing the manager role from the last manager is rejected.

**S56 — Only managers create households** (R33)
Given Anna is a member of H and is logged in
When she tries to create a household or add a member
Then it is rejected; a manager can do both.

**S57 — Producer account** (R36)
Given the manager creates a producer account for baker@example.org
When the baker opens the invitation and sets a password
Then they can log in and see only their own products
And creating a second account for the same producer is rejected.

**S58 — Deactivating a household** (R35a)
Given household H has a balance of −€5.00, a pending pickup and members Anna and Biel
When the manager deactivates H after the warning that lists the balance and the pending pickup
Then Anna and Biel are unlinked and nobody can order for H
And H's lines and pending pickup are kept and H is listed as to settle
And once the manager completes the pickup and records a top-up that brings the balance to €0.00, H leaves
the list.

**S59 — Deactivating an account** (R36b)
Given Anna is a member of H and Carla is the only manager
When the manager tries to deactivate Carla's account
Then it is rejected; deactivating Anna's account unlinks her from H, she cannot log in and her history is
kept.
