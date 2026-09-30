# Vision

Guixa is the app of a food cooperative. Domain terms are defined in [`CONTEXT.md`](../CONTEXT.md).

## The cooperative

A consumer group of 20–60 **households** that buys directly from **producers** through a
weekly **order cycle**. Work is organised in **commissions**: orders/producers, treasury,
distribution, boxes, cleaning and membership (more may appear).

## The problem

The cooperative runs on custom software built in-house that is now:

- obsolete and unmaintained;
- hard to use, both for members and for commissions;
- missing features, so part of the work is still done by hand outside it.

Guixa replaces it.

## Who uses it

- **Members**: people who belong to a household, each with their own login. Any member of a
  household can place or edit the household's order.
- **Commissions**: members acting in a commission role, with the permissions that role needs.

Producers do not use the app; they receive emails.

## Platform

A responsive web app that works well on phones and desktops. No native apps.

## First version

The first version must be enough to stop using the current software.

1. **Order cycle**
   - The orders commission maintains a stable **catalogue** and, for each cycle, opens it and
     chooses the **active producers** (adjusting products and prices as needed).
   - Households place their **order**. Some products are **weighed products**, ordered as an
     estimate.
   - Each producer has a **producer deadline**; the commission can close earlier or extend it.
     When ordering from a producer closes, the app emails them the **producer order** and
     households can no longer edit that part of their order.
2. **Boxes**
   - The boxes commission records what actually goes into each household's **box**: real
     weights for weighed products and real quantities when a producer could not supply
     everything.
3. **Balances and payments**
   - Each household has a **balance** that can be in its favour or owed.
   - When a box is packed, its final amount is **charged** to the balance.
   - Households pay whenever they want (or let it accumulate); the treasury commission records
     each **payment**.
4. **Households, members and commissions**
   - Households, their members, logins, and commission membership that grants permissions.

## Later

- Shift calendars, tasks and reminders for commissions (boxes, cleaning, distribution…).
- Tracking payments from the cooperative to producers.
- Possibly, read access for producers.

## Out of scope

- Online payments (card, Bizum or any payment gateway).
- Accounting or invoicing; Guixa does not replace the official books.
- Selling to non-members, or serving more than one cooperative.

## Open questions

- **Data migration**: whether to extract data from the current software and migrate it with a
  script is not decided yet.
