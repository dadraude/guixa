# 001 — Weekly orders: Open questions

Part of [001 — Weekly orders](spec.md): glossary, time and roles are defined there.

Raised while resolving OQ1–OQ18 on 2026-10-19. Each question lists the requirements and scenarios it
blocks; everything else stays accepted.

### OQ19 — Counting the containers distributed (low)

Blocks: R1, R20a.

Decided: a product's price already includes its container, and a return item credits a fixed amount
(R20a). A product can also be linked to the container that comes with it (e.g. honey → glass jar), only to
know how many containers were distributed; it adds nothing to the charge.

Questions:

1. How many containers does a delivered quantity count? One per unit is clear for non-variable products;
   for a variable product (2.500 kg of yoghurt) it is not.
2. Where is the count shown (per cycle, per household, overall), and to whom?
3. Is the link snapshotted in the order line, like the rest of the product (R10)?

Options for 1:

- **(a)** Only non-variable products can be linked; one container per unit delivered. — Simple; variable
  products in jars are not counted.
- **(b)** Each product sets how many containers go with one unit (e.g. 1 per 0.500 kg), rounded up.
  — Covers everything; one more field per product.

Proposal: (a).
