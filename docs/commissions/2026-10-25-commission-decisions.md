# Commissions: decisions (2026-10-25)

Decisions taken while grilling the request below, for the Commissions spec to start from. It is not a spec:
requirements, scenarios and open questions are written when that spec starts (see the
[roadmap](../../specs/README.md#roadmap)).

Request: "We need comissions so each user can be in a comission. For example, some of them may be in the
providers comission so they can manage providers and their products."

## Terms

| Term       | Català          | Meaning                                                                                                 |
| ---------- | --------------- | ------------------------------------------------------------------------------------------------------- |
| Commission | Comissió        | A named group of accounts that an Admin gives permissions to.                                           |
| Admin      | Administrador/a | Replaces Manager. Holds every power any commission can have.                                            |
| Producer   | Productor/a     | Grows, gathers or makes the products it supplies, and manages its own catalog. Has at most one account. |

"Providers" in the request means producers. A supplier would also include resellers, which a producer excludes.

## Decisions

1. The Manager → Admin rename is a separate change with no behaviour change, merged before Commissions.
2. Commissions add powers; the Admin keeps every power.
3. Any account can be in zero or more commissions, including a producer's account.
4. Only an Admin creates, renames, deactivates, reactivates and deletes commissions, sets their permissions
   and chooses their members.
5. A commission has a unique name (deactivated ones included), which an Admin can rename.
6. A permission is one area: producers & products, cycles & participation, reception, pickup, stock,
   wallets, households & accounts.
7. With the producers permission, a commission manages producers and edits the catalog of any producer
   without an active account. An account is active once its invitation is accepted and until it is
   deactivated: a pending invitation does not count, and a deactivated account hands the catalog back to
   the commission. An Admin can edit those catalogs too.
8. A producer's account never acts on other producers, even in a commission with the producers permission.
9. Only an Admin acts on an Admin's account, and only an Admin grants or removes Admin.
10. A commission ends by being deactivated; it can be reactivated. One whose history holds only its
    creation (no members, no changes) can be deleted.
11. A commission's history records who joined or left and which permissions were added or removed, by
    which Admin and when.
12. Commission members use the Admin screens, showing only the areas their permissions cover. No other
    action is Admin-only for now; some may become so later.
