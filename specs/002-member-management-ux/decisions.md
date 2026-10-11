# 002 — Member management UX: decisions

Mobile-first member management starts with a searchable, read-only list and opens each
member on a dedicated page. This document records the decisions individually confirmed
during the `/grill-with-docs` interview on 2026-10-11. The user confirmed the final shared
understanding on 2026-10-11. This is ready as input for `/to-spec`; specification approval
is a separate step.

## Scope and existing contract

Improve consulting, creating and managing members. Preserve the business rules and
authorization of [spec 001](../001-households-members-commissions/spec.md), using the
terminology in [CONTEXT.md](../../CONTEXT.md).

The existing specification remains authoritative for field validation, email changes,
protected members, household assignment and the effects of activation/deactivation.
This interview changes their presentation and interaction, not their domain meaning.

## Find a member

### List contents and entry points — Q1

- The initial screen is a read-only list, with search, filters and a `Nou membre` action
  before the list. Opening a member leads to their detail page.
- Each item shows the member's full name as the primary information, the household number
  and name or `Sense unitat familiar`, and an inactive marker when applicable.
- An inactive member and a member belonging to an inactive household are distinct states
  and must be distinguishable.
- Use this interaction model on both mobile and desktop.

### Search — Q5

- One search field matches the member's name, email, phone, and household number or name.
- Search applies across the entire list, not just the current page, accepts partial matches,
  and ignores case and accents.
- Search combines with the selected filter and displays the result count.

### Filters — Q6

Use one compact selector with four mutually exclusive views:

| UI label | Meaning |
|----------|---------|
| Tots | All members; selected initially. |
| Actius | Active members. |
| Inactius | Inactive members. |
| Actius sense unitat familiar activa | Active members with no household or with an inactive household, preserving spec 001's existing definition. |

Changing the filter preserves the search and returns to the first result page.

### Pagination and empty results — Q13

- Keep 15 members per page and the current Catalan name ordering.
- Provide `Anterior` and `Següent` controls and a range/count such as `1–15 de 48 membres`.
- The number of pages is dynamic: calculate it from the current filtered search result
  count, rather than fixing it in advance.
- If data changes make the current page disappear, show the last available page.
- Store search, filter and page in the URL so the list view can be recovered or shared.
- When there are no matches, show `No s'han trobat membres` and an action labelled
  `Neteja la cerca i els filtres`.

## Consult and edit a member

### Dedicated detail page — Q2, Q3 and Q12

Each member has a dedicated page with its own URL, on mobile and desktop. Returning to
the originating list preserves search, filters, pagination and scroll position.

Show the following blocks in read mode initially:

| Block | Information and actions |
|-------|-------------------------|
| Personal details | Name, email and phone. `Edita les dades` opens editing, with `Desa` and `Cancel·la`. |
| Household | Current assignment and actions to assign/change the household or remove the member from it. |
| Member status | Active/inactive state and the permitted deactivate/reactivate action. |
| Commissions | Commission memberships, or `No pertany a cap comissió`, and a link to the existing Commissions screen for management. |

Commission membership is read-only on the detail page in this iteration. Creating a
member still supports choosing commissions in the creation form.

### Unsaved changes and save errors — Q4 and Q14

- Warn before discarding changed personal details when cancelling, returning to the list
  or leaving the detail page. Do not warn when nothing has changed.
- Offer `Continua editant` and `Descarta els canvis`.
- Preserve entered values when saving fails and show errors beside the relevant fields.
- Allow one editable block at a time. If there are pending edits and the person attempts
  to switch blocks or execute another action, apply the same warning before discarding them.

### Restricted actions — Q11

When permissions prevent changing an email or deactivating/reactivating a member, show
the restricted option disabled with a visible explanation next to it. The explanation
must work on mobile without hovering and reflect the exact applicable rule from spec 001.

Examples include `Només el propietari pot canviar el correu d'un administrador` and
`El propietari no es pot desactivar`. These examples do not replace the full permission
rules, including the separate process for changing one's own email.

## Create a member — Q7

- `Nou membre` opens a dedicated creation page.
- Name and email are required; phone, household and commissions are optional, as in spec 001.
- After successful creation, open the new member's detail page and show `Membre creat`.
  This works even if the new member does not match the originating list's filters.
- Cancelling returns to the list with its context preserved. Warn before discarding entered data.

## Change household assignment — Q9 and Q10

### Assign or move

Inside the household block, offer a picker searchable by household number or name. Only
active households are selectable. Show the current and proposed assignments before the
person saves with `Desa el canvi`; also offer `Cancel·la`.

Identify an existing inactive household as `#N Nom · Inactiva`. Moving a member does not
change whether that member is active or inactive.

### Remove from the household

Keep removal separate from the household picker and require confirmation identifying
both the member and the household:

> Vols treure [nom] de la unitat familiar #N [nom]? Mantindrà el seu estat i les seves
> comissions. Necessitarà una unitat familiar activa per fer comandes.

The confirming action is `Treu de la unitat familiar`; afterwards the detail page shows
`Sense unitat familiar`. The action remains available for active and inactive members,
subject to spec 001's authorization.

## Deactivate or reactivate — Q8

Require confirmation for both actions, identifying the member and explaining the effect.

| Action | Proposed confirmation copy | Confirming button |
|--------|----------------------------|-------------------|
| Deactivate | Desactiva [nom]? No podrà iniciar sessió i es tancaran les sessions obertes. Es conservaran les seves dades, unitat familiar i comissions. | Desactiva el membre |
| Reactivate | Reactiva [nom]? Podrà tornar a iniciar sessió. Es mantindran la seva unitat familiar i les seves comissions. | Reactiva el membre |

After success, show the updated status and confirmation on the member's detail page.
All existing protections still apply; confirmation does not confer permission.

## Testing agreement during `/to-spec`

The user confirmed retaining Pest through HTTP for search, filters, pagination and
permissions, and extending Playwright on mobile and desktop for navigation, editing,
confirmations and unsaved-change protection.

## Spec review clarifications

An independent read-only subagent reviewed the draft against spec 001, the domain documents
and related source on 2026-10-11. The user confirmed two additional decisions:

1. Household removal confirms a specific assignment. If another action has changed that
   assignment before the locked write, reject removal, refresh the household information
   and require a new confirmation. If already unassigned, explain that nothing was removed.
   This deliberately tightens the existing removal behavior; competing moves retain
   spec 001's last-write-wins rule.
2. While a mutation is pending, freeze editing, cancellation/discard, block switching,
   other mutations and application-controlled departure, with visible progress. Restore
   editing and submitted values after failure. Browser departure does not cancel a write
   already sent. An unknown result after connection loss requires refreshing server state
   before retry, rather than assuming the operation failed or replaying it automatically.

The following clarifications follow existing decisions and do not introduce new domain rules:

- Verify spec 001's status idempotency and locked protection checks during the status UX
  slice. Source review found these guarantees are not fully implemented yet; existing
  regression tests alone are not sufficient evidence. This is required work in that slice.
- Returning from Commissions refreshes detail memberships and effective permissions.
- Creating from the list and saving detail changes preserve useful browser Back behavior,
  without reviving a filled creation form or accumulating duplicate detail visits.
- Own-email restrictions link to the existing profile flow and honor dirty-form protection.
- Failed list requests show an error and retry action while preserving a coherent last
  successful query; a network error is not an empty result.

The review used targeted source reads because this worktree has no CodeGraph index and
Codebase Memory tools were unavailable. It was a contract review, not a runtime verification
or an exhaustive audit of the application. The configured Opus reviewer was unavailable;
the subagent used the session's available model.
A bounded second pass confirmed the seven findings were resolved and found no material
new contradiction in the changes. No runtime behavior was verified during either pass.

## Next step

The shared understanding is confirmed and the [draft specification](spec.md) has been
synthesized and reviewed. Explicit user approval of the spec still precedes `/to-tickets`
and implementation, following the project's SDD flow.

No new domain term or architectural trade-off requiring an ADR was decided in this interview.
