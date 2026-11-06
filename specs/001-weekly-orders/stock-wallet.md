# 001 — Weekly orders: Stock, wallet and accounts

Part of [001 — Weekly orders](spec.md): glossary, time and roles are defined there.

Stock movements and return items, global household wallets and receipts, households, members and account roles.

## Requirements

### Stock

- **R25** A manager creates stock products from the supplying producer's record (R1a), and edits,
  deactivates and reactivates them: name, unit, price (≥ 0), variable flag, and containers per unit
  with the same bounds as weekly products (R1). The producer does not administer these records.
  Unused stock products may be deleted under R1c.
  A manager creates, edits, deactivates and reactivates return items: name and amount (> 0).
- **R25a** Once a stock product has a stock movement or has appeared in a basket line, its unit and
  variable flag can never change, even if available stock later returns to zero or the line is removed.
  A different unit or variable flag requires a new product record. Name, price and containers per unit
  remain editable; existing basket stock lines retain the snapshots required by R20.
- **R25b** A producer can consult their own stock product records, current prices and available
  quantities, and their movement history under R28a, but cannot create, edit, delete, deactivate or
  reactivate them, change their stock flag or record stock movements while acting as a producer. An account also acting as a manager has the
  manager permissions (R36a).
- **R25c** Members can consult an informational catalogue of the cooperative's active stock products,
  including those of deactivated producers. Each shows its producer, name, unit, current unit price
  and current available quantity (R28). Products with zero available quantity remain visible as
  *sold out*; deactivated products are hidden. The catalogue does not depend on an open cycle or on
  producer participation. It offers no reservation or way to add stock to the weekly order. Viewing
  it does not reserve or change stock: availability is the quantity on hand when consulted, and the
  basket addition/validation checks in R20 and R22 still apply.
- **R26** A manager records entries for stock products only (quantity > 0, optional note,
  e.g. "bought from X"). A weekly product cannot receive stock entries or adjustments (R27).
- **R27** A manager records stock adjustments (positive or negative, mandatory note, e.g. "broken jar",
  "inventory count", optional cycle). Available stock can never go below 0. After an inventory count a
  manager may instead set the available quantity directly; the system records an adjustment for the
  difference.
- **R28** Available stock = Σ entries + Σ adjustments − Σ quantities sold at validated baskets,
  including signed corrective movements. Validation records the sale once (R22); physical collection
  records no stock movement. Stock movements are never edited or deleted.
- **R28a** Managers and Finance can consult the movement history of any stock product; a producer, of
  their own stock products only (R25b). The history stays available after the product or its producer
  is deactivated; weekly products have none. Each line shows the movement's date, type (entry,
  adjustment, sale or correction), signed quantity, the available stock right after it (R28), its note
  (for a correction, its reason), its author's name (kept after that account is deactivated) and its
  optional cycle. For managers and Finance, sale and correction lines link to their basket; a producer
  sees neither the basket nor the household. Lines are listed newest first in a stable order, 50 per
  page, without filters, so the newest line's available stock equals the current available quantity.
  Consulting the history is read-only: only a manager records entries and adjustments (R26, R27).

### Wallet

- **R29** Finance or a manager records a confirmed receipt/top-up after checking money received
  (e.g. cash or bank transfer): positive amount and optional note. Date it when recorded. It credits
  the global household Wallet independently of basket validation and is not allocated to a basket
  or cycle. A receipt may cover several charges or leave credit; no payment gateway is included.
- **R30** Balance = Σ top-ups + Σ credits − Σ charges ± corrections. It may be negative, with no limit.
- **R31** Members see their household balance and every movement. Charges/credits show their basket
  and optional cycle; corrections preserve the original source and optional cycle. Finance and Manager
  can read the statement of any household. Baskets have no paid/part-paid status.
- **R32** Movements are never edited or deleted. Finance or a manager fixes mistakes with a new
  correcting movement: non-zero amount, mandatory reason, original source and optional basket/cycle
  reference. A correction does not rewrite the receipt, validated basket or original charge. Linked
  stock corrections follow R23/R27 and preserve non-negative stock.

### Households and accounts

- **R33** Only a manager creates households, renames them, and links or unlinks members. There is no
  self sign-up.
- **R34** Adding a person sends them an email invitation to set their password. Until they accept it,
  they cannot log in. An invitation expires after 7 days. While it is not accepted, a manager can resend it
  (the previous link stops working) or change its email.
- **R35** A person belongs to at most one household. Being linked to a household is what makes an account a
  member of it: it can order for it and see its wallet, whatever other roles it holds. An account with no
  household and no other role cannot log in until a manager links it.
  - Moving a member links the same account to the new household. The old household keeps its orders,
    baskets, balance and history; nothing moves with the person.
  - Unlinking a member keeps the account active, without a household; it can be linked again to any
    household, the same one included.
  - Records of what a person did (e.g. who added a line) keep naming them after a move.
- **R35a** A manager can deactivate a household at any time, even with a balance or unresolved
  baskets, after a warning listing what remains. It is deactivated automatically when its last member
  is unlinked/moved. Members are unlinked and nobody can order for it; retain existing order lines,
  basket drafts, validated pending collections and all financial/history records. Finance or Manager
  can resolve existing basket/receipt obligations, including validating existing drafts, correcting
  mistakes and recording collection; new stock baskets require an active household (R19).
  An inactive household stays in the Manager's *to settle* list until its balance is zero and it has
  no unresolved draft or collection obligation. A manager can reactivate it, and linking a member to
  it also reactivates it. An inactive Wallet still accepts receipts and corrections. Reactivation
  does not move history or restore old member links; a household created without members is active.
- **R36** A manager creates producer accounts, with the same invitation flow. Each producer has exactly
  one account.
- **R36a** One account can hold several roles (e.g. linked to household H, so a member of it, and manager). A manager grants or removes the
  manager role on any account, except removing it from the last manager. The first manager is created
  when the system is installed, outside the app.
- **R36b** A manager can deactivate and reactivate any account. A deactivated account cannot log in and is
  unlinked from its household (R35); its history is kept. The last active manager's account cannot be
  deactivated.
- **R36c** Only a manager grants or revokes the explicit Finance role on accounts. Manager retains
  every Finance power. Finance is an effective login role independently of household membership;
  Member remains derived from the current household link. Basket/Wallet capabilities are those in
  R19a/R22/R23/R28a/R29/R32, R28a being read-only; the role grants no product/cycle/reception/account
  or unrelated inventory administration. Configurable commissions are not part of this amendment.

## Acceptance scenarios

### Stock

**S23 — Entries and adjustments** (R26, R27, R28)
Given honey has 0 jars
When the manager records an entry of 10 and an adjustment of −1 noted "broken jar"
Then 9 jars are available; an adjustment of −10 is rejected.

**S71 — The manager administers stock products** (R1a, R25, R25b)
Given a beekeeper with a weekly honey product
When the manager creates stock honey from that producer's record and sets its name, unit, price,
variable flag and containers per unit
Then it belongs to the beekeeper and is administered by the manager
And acting only as a producer, the beekeeper cannot create, edit, delete, deactivate or reactivate it,
change its stock flag or record its stock movements.

**S72 — Stock units and variable flag are fixed after use** (R25, R25a)
Given stock honey has an entry of 10 jars and stock oil once had a basket line that was removed
When the manager tries to change either product's unit or variable flag
Then it is rejected, even after the honey's stock returns to zero
And the manager can still edit their name, price and containers per unit under R20
And a stock product with no movement or basket line can still have its unit and variable flag corrected.

**S73 — Members consult stock without reserving it** (R3, R25c, R28)
Given active stock honey has 5 jars, active stock oil has zero bottles, a stock jam is deactivated,
and the baker has a weekly bread product
When a member opens the stock catalogue with no open ordering cycle
Then it shows the honey and oil with their producers, units, current prices and available quantities,
marks the oil as sold out, and shows neither the deactivated jam nor the weekly bread
And there is no reservation or way to add these products to the weekly order; stock is unchanged.

**S74 — A producer consults only their own stock** (R2, R25b, R25c, R36a)
Given the beekeeper and oil producer both supply stock products
When the beekeeper, acting only as a producer, consults the catalogue
Then they can see their own stock product records, current prices and quantities without modifying them,
but cannot see the oil producer's products
And if that account is also a household member, it can consult both producers' active stock products
through the informational member catalogue, without acquiring stock administration permissions.

**S93 — Stock movement history** (R25b, R28, R28a, R36c)
Given stock honey received an entry of 10 jars noted "bought from X", then an adjustment of −1 noted
"broken jar", then a sale of 3 jars at a validated basket of household A, then a correction of +1
jar to that basket noted "wrong count"
When the manager opens the honey's movement history
Then it lists the correction, sale, adjustment and entry in that order, with available stock 7, 6, 9
and 10, each line's date, signed quantity, note, author and cycle, and the sale and correction
linked to household A's basket
And Finance sees the same lines but cannot record an entry or adjustment
And the beekeeper, acting only as a producer, sees the same lines without the basket or household,
and cannot open the oil producer's stock history
And after the honey is deactivated, the history is still listed for all three.

### Wallet and accounts

**S24 — Receipt and correction** (R29, R32)
Given H's balance is €0
When Finance records a confirmed €50 receipt and a −€50 correction with reason duplicated
Then H's balance is €0 and both movements remain visible and immutable.

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

**S58 — Deactivated household retains basket obligations** (R35a)
Given H has balance −€5, an existing basket draft, a validated pending collection and linked members
When the manager deactivates H after its obligations warning
Then members are unlinked, new orders/stock baskets are refused, and existing drafts/collections remain
And H stays to settle until Finance/Manager resolves those baskets and the resulting Wallet balance
And recording physical collection never charges the already validated basket again.

**S59 — Deactivating an account** (R36b)
Given Anna is a member of H and Carla is the only manager
When the manager tries to deactivate Carla's account
Then it is rejected; deactivating Anna's account unlinks her from H, she cannot log in and her history is
kept.
