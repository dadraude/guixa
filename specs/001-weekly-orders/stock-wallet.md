# 001 — Weekly orders: Stock, wallet and accounts

Part of [001 — Weekly orders](spec.md): glossary, time and roles are defined there.

Stock products and movements, household wallets, households, members and invitations.

## Requirements

### Stock

- **R25** A manager creates, edits and deactivates stock products: name, unit, price, variable flag.
- **R26** A manager records stock entries (quantity > 0, optional note, e.g. "bought from X").
- **R27** A manager records stock adjustments (positive or negative, mandatory note, e.g. "broken jar",
  "inventory count"). Available stock can never go below 0.
- **R28** Available stock = Σ entries + Σ adjustments − Σ quantities sold at confirmed pickups.
  Stock movements are never edited or deleted.

### Wallet

- **R29** A manager records top-ups, at a pickup (R21) or on their own: positive amount, date,
  optional note (e.g. "transfer", "cash").
- **R30** Balance = Σ top-ups − Σ charges ± corrections. It may be negative, with no limit.
- **R31** Members see their household balance and every movement (top-ups and charges, with cycle).
- **R32** Movements are never edited or deleted. Mistakes are fixed with a correcting movement
  recorded by a manager, with a mandatory note.

### Households and accounts

- **R33** Only a manager creates households and adds or removes their members. There is no self sign-up.
- **R34** Adding a member sends them an email invitation to set their password. Until they accept it,
  they cannot log in.
- **R35** A person belongs to at most one household. Removing a member keeps the household's orders
  and wallet movements intact.
- **R36** Producer accounts are also created only by a manager, with the same invitation flow.

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
Then she can log in and sees H's order and wallet; before accepting, login is rejected.
