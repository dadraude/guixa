# 002 — Member management UX

Status: draft

Based on the confirmed [interview decisions](decisions.md). This specification complements
[spec 001](../001-households-members-commissions/spec.md); its business rules and permissions
remain authoritative. Terms follow [CONTEXT.md](../../CONTEXT.md). Relevant decisions are
[ADR 0008](../../docs/adr/0008-members-created-by-hand.md) (member creation) and
[ADR 0011](../../docs/adr/0011-inactive-only-blocks-sign-in.md) (inactive status).

## Problem Statement

People managing membership need to find a person, understand their situation and perform
one deliberate action, often on a phone. The current Members screen puts the creation form
before the list and exposes several editing forms and actions for every person. This makes
consultation slow, buries existing members on mobile and makes it difficult to distinguish
reading from changing data.

There is no global search, no dedicated member page, and limited explanation of restricted
actions. Moving forms to separate screens must also preserve the person's place in the list
and protect work that has not yet been saved.

## Solution

Members opens as a searchable, read-only list with a compact filter selector and a
`Nou membre` action. Each member has a dedicated detail page, initially in read mode, with
personal details, household, status and commissions in separate blocks. Only one block is
edited at a time, with explicit saving, cancellation and protection against accidental loss.

Creating a member uses its own page and opens the resulting member's detail page. Removing
a household assignment and changing a member's active state require an explanation and
confirmation. Restricted options explain why they are unavailable. The same model works
on phones and desktops, preserving all existing authorization and domain behavior.

## User Stories

In these stories, a **membership manager** means an active person authorized by spec 001:
a member acting with the membership role, or a person acting as administrator, including
the owner. This is shorthand for the audience, not a new role or a new UI term.

### Find and browse

1. As a membership manager, I want to see a read-only list first, so that I can find an existing member without scrolling past a creation form.
2. As a membership manager, I want each item to show the member's name and household number and name, so that I can distinguish people with similar names.
3. As a membership manager, I want to distinguish an inactive member, no household and an inactive household, so that I understand which situation needs attention.
4. As a membership manager, I want one search field for name, email, phone and household number or name, so that I can use whichever detail I know.
5. As a membership manager, I want partial, case- and accent-insensitive matching across all pages, so that a small spelling variation or the current page does not hide a match.
6. As a membership manager, I want to choose all members, active members, inactive members or active members without an active household, so that I can focus on the task at hand.
7. As a membership manager, I want search and the selected filter to combine, so that I can narrow a specific group.
8. As a membership manager, I want changing a filter to keep my search and return to the first page, so that I see the beginning of the new results.
9. As a membership manager, I want names ordered consistently using Catalan collation, so that browsing remains predictable.
10. As a membership manager, I want 15 members per page, previous/next navigation and a result range, so that I can understand and navigate the list on a phone.
11. As a membership manager, I want the number of pages to reflect the current results, so that search and filtering never leave a fixed or misleading page count.
12. As a membership manager, I want an out-of-range page to resolve to the last available page, so that changes to the data do not strand me on an empty page.
13. As a membership manager, I want an explanation and a way to clear search and filters when there are no matches, so that I can recover without guessing what happened.
14. As a membership manager, I want the URL to preserve search, filter and page, so that I can reload, bookmark or share the same list view with another authorized person.

### Consult and navigate

15. As a membership manager, I want opening a member to show a dedicated page with its own URL, so that I can focus on one person and return directly to their details.
16. As a membership manager, I want personal details, household, status and commissions in separate read-only blocks, so that I can understand the member before acting.
17. As a membership manager, I want to return to the same list search, filter, page and scroll position, so that checking a member does not lose my place.
18. As a membership manager opening a shared detail link, I want a usable way to reach the member list, so that navigation works without a prior visit in this browser.
19. As a membership manager, I want to see the member's commissions or an explicit empty state, so that I understand their participation.
20. As a membership manager, I want a link to the existing Commissions screen, so that I can manage membership there without a second competing editor.
21. As a member without management permission, I want existing access restrictions to apply to the new pages too, so that a direct URL does not expose management data.

### Edit and protect work

22. As a membership manager, I want `Edita les dades` to open editing of name, email and phone with explicit save and cancel actions, so that consultation does not accidentally become editing.
23. As a membership manager, I want unavailable email and status actions to show their reason visibly, so that I understand the restriction on both phone and desktop.
24. As a membership manager viewing my own record, I want my own email change to retain its confirmation process, so that this screen cannot bypass the self-service rule.
25. As a membership manager, I want only one block open for editing, so that each save has a clear scope.
26. As a membership manager, I want a warning before changed values are discarded by cancellation, navigation or another action, so that I do not lose work accidentally.
27. As a membership manager, I want to continue editing or explicitly discard changes, so that I control how to leave an unfinished edit.
28. As a membership manager, I want no warning for an unchanged form, so that normal navigation remains quick.
29. As a membership manager, I want failed saves to keep my values and identify the relevant fields, so that I can correct the problem without re-entering everything.
30. As a membership manager, I want visible progress and success feedback, so that I know whether my action is still running or has completed.

### Create a member

31. As a membership manager, I want `Nou membre` to open a dedicated creation page, so that the form has enough room on a phone.
32. As a membership manager, I want to supply required name and email and optional phone, household and commissions, so that creation retains the capabilities of spec 001.
33. As a membership manager, I want successful creation to open the new member's detail page with `Membre creat`, so that I can verify the result even when it does not match my previous list filters.
34. As a membership manager, I want cancelling creation to restore my list context and warn about entered data, so that starting a new member does not disrupt an existing task.
35. As a newly created member, I want the existing welcome email and first-access behavior to remain intact, so that the redesigned management screen does not change how I join.

### Household and status actions

36. As a membership manager, I want to find an active household by number or name within the household block, so that I can assign or move a member accurately.
37. As a membership manager, I want to see the current and proposed household before saving, so that I can check the destination.
38. As a membership manager, I want an inactive current household clearly identified, so that it is not confused with a selectable active destination.
39. As a membership manager, I want changing household to preserve the member's active state, so that assignment and reactivation remain separate decisions.
40. As a membership manager, I want removing household membership to be a separate confirmed action naming the person and household, so that removal is deliberate.
41. As a membership manager, I want to remove active or inactive members when allowed and retain their commissions and status, so that the presentation preserves spec 001.
42. As a membership manager, I want confirmation before deactivating or reactivating a member, so that I understand the effect on sign-in and existing sessions.
43. As a membership manager, I want successful actions to update the detail page and report their outcome, so that I see the current assignment or status immediately.
44. As a membership manager, I want current permissions and state checked when I submit, so that a stale screen cannot bypass a rule or protection that changed while it was open.
45. As a keyboard or assistive-technology user, I want labelled fields, understandable errors and predictable focus in forms and dialogs, so that I can complete the same tasks.

## Implementation Decisions

### Relationship to spec 001

- This spec replaces the Members screen's inline list editing and creation presentation with
  list, detail and creation pages. It does not replace the membership domain or its policies.
- In this management flow, restricted email and status options are disabled with visible
  explanations. This explicitly supersedes spec 001's instruction to *hide* self-deactivation
  for a member acting with the membership role; server rejection remains unchanged.
- Reuse the existing Laravel, Inertia, Vue and shared UI infrastructure. There is no new
  public API, role, account type or offline capability. No new domain entity is needed.
- Keep spec 001's field limits, uniqueness rules, welcome email, first-access password setup,
  transactions, locks, idempotency and status effects. Verify these guarantees rather than
  assuming the current implementation already satisfies them. UI confirmation never grants
  permission. The stale household-removal rule below explicitly tightens the current action.
- All UI text is Catalan through the existing translation system. Reuse domain terms rather
  than introducing a new vocabulary for the redesign.

### List, search and filters

- The list presents member name, household number and name or `Sense unitat familiar`, and
  independent member/household inactive markers. A readable item opens the detail page.
- One search applies to member name, email, phone, household number and household name over
  the authorized dataset before pagination. A match in any one field qualifies. Matching is
  partial, case-insensitive and accent-insensitive; an empty search adds no restriction.
- Keep matching distinct from ordering: results retain Catalan name order with a stable
  identifier tie-breaker, as today. Search does not rank or change the order of matches.
- Search is literal rather than allowing database wildcard syntax. Trim surrounding search
  whitespace. Searching household fields does not exclude other matches for members without
  households; an inactive assigned household still has a searchable number and name.
- Use one compact, labelled selector with the following mutually exclusive values:

| UI label | Result set |
|----------|------------|
| Tots | All members; the initial selection. |
| Actius | Active members only. |
| Inactius | Inactive members only. |
| Actius sense unitat familiar activa | Active members with no household or an inactive household; excludes inactive members. |

- Search intersects the selected filter. Changing search or filter returns to the first
  result page; changing filter preserves search. Clearing both returns to `Tots` on page one.
- The result count, pagination links and rows must describe the same applied query. Show
  loading feedback during requests; a superseded response must not replace a newer result.
- A failed search, filter or page request is not an empty result. Keep the last successful
  applied query, URL, count and rows coherent, show an error and allow retrying the requested
  query. Unapplied input must not be presented as the criteria for the retained rows.
- Persist applied search, filter and page in the list URL. Preserve existing links using
  the active-without-household filter. Continue safely normalizing unknown filters and
  malformed page parameters; arbitrary query keys do not become return destinations.

### Dynamic pagination and empty states

- Page size stays at 15. For a positive result count, page count is the count divided by 15,
  rounded up. For example, 31 matches have three pages; filtering to 14 produces one.
- Show a result range such as `1–15 de 48 membres` with `Anterior` and `Següent`. Do not offer
  navigation beyond the first or last page. Recompute counts on requests and list revisits;
  dynamic pagination does not introduce live polling or subscriptions.
- A page beyond the current end resolves to the last available page, preserving search and
  filter. With zero matches, show the empty state on the first page, no misleading range
  starting at one, and no previous/next action.
- Show `No s'han trobat membres` and `Neteja la cerca i els filtres` when nothing matches.
  Keep the creation entry point available. Clearing criteria does not create any data.

### Detail, creation and return context

- Detail and creation are dedicated, directly addressable pages in the existing application
  layout on both mobile and desktop. They use the same management authorization as the list.
- Detail contains personal details, household, status and commissions. Commissions are
  read-only here, with `No pertany a cap comissió` when empty and an ordinary link to their
  existing management screen. Active and inactive members retain their memberships.
- Returning from Commissions to the detail page, including browser Back, revalidates
  commissions and effective permissions. If the actor has lost management access, apply
  the existing access flow instead of restoring an authorized-looking stale page.
- Opening either page from the list retains the originating applied search, filter, page
  and scroll position. The page's return action and browser Back restore that context.
- Returning revalidates list data; if an edit changes membership in the filtered results,
  reflect that change and apply page clamping. Restore scroll where the resulting content
  permits, rather than showing stale rows to preserve exact pixels.
- A directly opened page without a usable origin returns to the default list. Retained
  return context can identify only the application's member list, not an arbitrary URL.
- Successful creation opens the created member with `Membre creat`, carrying the originating
  list context forward. It works even if the new member is outside that list's results.
- After creation from the list, browser Back returns to that list instead of restoring a
  filled creation form. Saving detail changes does not add repeated visits to the same
  detail page that must be traversed to return. Direct-entry pages retain their ordinary
  browser history; their explicit return action still uses the default-list fallback.
- Successful detail mutations remain on the detail page, refresh the affected information
  and show confirmation, while retaining list context. Existing session/profile transitions
  take precedence if the actor's action removes their own access.

### Forms, dirty state and feedback

- Personal details initially appear in read mode. `Edita les dades` opens the permitted
  fields with `Desa` and `Cancel·la`. Keep restricted email readable and explain the reason.
  A person's own email is changed through the existing confirmed self-service process;
  the management form never performs an immediate self-email change. Its explanation links
  to the existing profile screen, including pending-change management. Following that link
  obeys the same unsaved-change protection as other application navigation.
- Only one block may be edited at a time. Entering another block or requesting a mutation
  while the current form is dirty first offers `Continua editant` or `Descarta els canvis`.
  Continuing keeps the form and cancels the attempted action. Discarding restores the saved
  values and allows the attempted action to proceed to its normal step or confirmation.
- Dirty means current editable values differ from the last saved values, or from the empty
  initial creation form. Reverting to those values clears dirty state. A clean cancellation
  or departure does not open a warning. Successful saving establishes a new clean baseline.
- Apply this protection to cancellation, application navigation and browser Back. For
  document unload/reload, use the browser's supported unsaved-change warning: its wording
  and availability are browser-controlled, so custom dialog text cannot be guaranteed there.
- A failed validation/save retains entered values while the form remains accessible and
  shows errors beside the fields. A request failure must not look like success or silently
  switch to read mode. If access expires or is revoked, the existing access flow wins;
  this spec does not promise draft recovery across sign-in or a closed tab.
- While a mutation is pending, show progress and freeze the submitted form and its dialog.
  Block editing, duplicate submission, cancellation/discard, block switching, other mutations
  and application-controlled departure from the page until the request resolves. This also
  applies to creation and confirmed household/status actions. On a definitive failure,
  unlock the controls and preserve submitted values; success establishes the saved baseline.
  Browser-controlled departure cannot be guaranteed to stop a submitted server operation:
  keep the supported unload warning and never claim leaving cancels or rolls it back.
- A connection failure can leave the write outcome unknown. Explain this uncertainty and
  require refreshing current server state before retrying a mutation; retain entered values
  for comparison while access remains available. Do not automatically replay the request.
  Do not submit simply because an editable field changed or focus moved elsewhere.
- Creation retains spec 001's name/email requirements and optional phone, active household
  and commissions. Existing duplicate-email and stale household/commission validation
  errors remain recoverable on the creation page.

### Household assignment

- The household block shows the current number/name, `Sense unitat familiar`, or the
  assigned inactive household identified as `#N Nom · Inactiva`.
- Assigning or moving opens a picker searchable by household number or name, offering only
  active households. Show current and proposed assignments with `Desa el canvi` and
  `Cancel·la`. An inactive current assignment is context, not a selectable destination.
- Save only an explicit selected destination. Preserve the member's active status and
  commissions. The server still checks the destination under the existing locking rules.
- Removal is a separate action, available for active or inactive members with an assignment
  when authorized. It requires a confirmation naming the member and their current household:
  `Vols treure [nom] de la unitat familiar #N [nom]? Mantindrà el seu estat i les seves
  comissions. Necessitarà una unitat familiar activa per fer comandes.`
- Confirm with `Treu de la unitat familiar`. Cancelling makes no change; success shows
  `Sense unitat familiar`. The request identifies the household named in the confirmation.
  Under the member lock, compare that identifier with the current assignment before removal.
  If it changed, remove nothing, explain that the assignment changed and refresh the block.
  Removing a new assignment requires a new confirmation naming it. If there is no assignment
  left, show `Sense unitat familiar` and explain that no removal was performed.
- This is an explicit exception to preserving the current removal action, which removes
  whichever assignment exists at submission. It does not change spec 001's last-write-wins
  rule for competing assignment/move operations or add general conflict coordination.

### Status, permissions and stale pages

- Show current member status and the relevant deactivate/reactivate action. If restricted,
  show it disabled with a visible, contextual reason, without relying on hover or color.
- Both actions require confirmation identifying the member:

| Action | Message | Confirming button |
|--------|---------|-------------------|
| Deactivate | Desactiva [nom]? No podrà iniciar sessió i es tancaran les sessions obertes. Es conservaran les seves dades, unitat familiar i comissions. | Desactiva el membre |
| Reactivate | Reactiva [nom]? Podrà tornar a iniciar sessió. Es mantindran la seva unitat familiar i les seves comissions. | Reactiva el membre |

- Explanations derive from the same effective permissions as the actions, including the
  actor's current profile, the owner's protection, self-action rules and the target's
  retained roles while inactive. Do not duplicate or weaken the authorization hierarchy.
- Direct page requests and every mutation still enforce server authorization. A hidden or
  disabled client control is not the access check. Newly stale options fail safely through
  the existing validation/authorization behavior; they cannot bypass it.
- Activation/deactivation checks current protection and state under the member lock.
  An already-active/inactive outcome performs no transition, repeats no side effects and
  explains that the member already had the requested state. This is required by spec 001;
  source review found the current actions and success messages do not fully implement it.
  Closing this gap with HTTP tests is a prerequisite of the status UX slice, not a follow-up
  or a new product decision. Do not treat passing existing regression tests as proof of it.
- An administrator allowed to deactivate themselves can lose the current session; the
  existing signed-out flow takes precedence over staying on the detail page.

### Responsive and accessible interaction

- Keep the same information and available tasks on mobile and desktop, with a single-column
  form layout where space is limited. Labels remain visible after values are entered.
- Search, filters, household picking, forms and confirmations work with the keyboard, with
  visible focus and meaningful accessible names. Dialogs focus their content when opened
  and return focus to the invoking control when cancelled.
- Associate errors with their fields and announce failed validation or success appropriately.
  Make the first error easy to reach. Status distinctions and restrictions include text.
- Validate narrow layouts with realistic long names, emails and household names, rather
  than relying on the short local sample data. Keep actions comfortably tappable and avoid
  horizontal page scrolling in the member management flow.

## Testing Decisions

The user confirmed the testing approach during `/to-spec`: retain Pest through HTTP and
extend Playwright coverage on mobile and desktop. These are existing test boundaries;
no new component-test framework or unit-test boundary is required.

### HTTP: data, authorization and mutations

Pest feature tests use the existing PostgreSQL setup and exercise observable HTTP results:
status, redirects, Inertia data, validation errors, persisted changes and mail/session
effects. Avoid tests tied to the internal decomposition of controllers or Vue components.

- Search each supported field, partial matches, case/accent variants, no household, inactive
  households, literal wildcard characters and results beyond the first page. Confirm that
  search does not change sorting or duplicate members.
- Cover each filter and its intersection with search, especially active members assigned to
  inactive households versus inactive members with the same assignment.
- Cover result counts at 0, 1, 15, 16, 30 and 31, stable equal-name ordering, invalid/huge page
  values, and shrinking results that move the request to the last page. Preserve the applied
  search/filter in navigation and clamping redirects.
- Cover direct access to list, detail and creation by allowed and forbidden actors, including
  signed-out visitors and people with an administrator profile acting as ordinary members.
- Verify detail data and restricted-action explanations agree with permissions for owner,
  administrators, membership-role holders, ordinary members and inactive protected targets.
- Adapt mutation redirect assertions to creation/detail destinations while retaining existing
  validations and protections. Keep regression coverage for welcome mail, immediate changes
  to someone else's permitted email, self-email confirmation, retained commissions, status
  effects, stale destinations and session invalidation.
- Change the stale-removal regression to reject a request naming a former household after
  reassignment; also cover an assignment already removed, and successful reconfirmation of
  the new assignment. Check unchanged commissions and member status in each outcome.
- Prove status idempotency and protection revalidation under the lock, including a repeated
  deactivation that must not repeat session/email effects, repeated reactivation and a
  protection change before the locked write. Assert distinct already-in-state feedback.
- Verify return context accepts only the member list's supported criteria and cannot redirect
  to an external destination. Keep the existing bounded list-query regression guard relevant.

Prior art: the existing edit-and-move member tests cover Catalan sorting, stable pagination,
malformed pages and return handling; household-change tests cover the no-household filter,
filter-preserving redirects and page shrinkage. Member creation, email-level, inactive
household and membership-protection suites cover the business rules that must be retained.

### Browser: complete user interactions

Playwright drives the real application using the existing seeded test environment. Exercise
the principal flow at mobile and desktop widths, with targeted keyboard and layout checks.
Assert visible behavior, URLs and persisted outcomes, not component state or CSS class names.

- Search and filter, move to a later page, open a member and return through the page action
  and browser Back; verify criteria, page and scroll restoration. Also open a detail URL
  without originating list context and verify its default return path.
- Fail a list request and verify error/retry behavior with coherent retained query, URL,
  rows and counts. A late or failed old response must not replace a newer successful query.
- Edit and save; cancel a clean form; make dirty changes and test both warning choices for
  cancellation, navigation, switching blocks and attempting another action. Reverting all
  changes removes the warning. Failed validation preserves values and exposes field errors.
- Create a member, including optional selections, and verify the success message and detail
  destination even when the preceding list filter excludes that member. Test dirty creation
  cancellation and the existing duplicate-email validation path.
- After creation and after detail saves, use browser Back and verify the originating list
  is reached without a restored creation draft or duplicate detail-history entries.
- Return from Commissions and verify refreshed commission memberships and permissions.
  Follow the own-email link to the profile, testing the dirty-form warning on departure.
- Search the household picker, compare current/new assignments, move an inactive member
  without reactivating them, and confirm/cancel household removal.
- Confirm/cancel deactivate and reactivate, checking the named target, consequences,
  resulting state and feedback. Check visible explanations for representative forbidden
  actions rather than trying every authorization combination again in the browser.
- Change the assigned household after opening the removal confirmation, then submit and
  verify rejection, refreshed context and the need to confirm the new household explicitly.
- Delay a mutation response and attempt editing, cancellation, another mutation, block
  switching and application navigation; controls remain blocked with visible progress.
  Resolve success or validation failure and check the correct baseline or retained values.
  Simulate a lost response and verify uncertainty feedback and refresh before any retry.
- Check keyboard focus and dialog return, visible labels, accessible error association and
  narrow-screen wrapping with long content. Check loading feedback and duplicate-submit
  prevention through observable behavior.

Prior art: the existing household-status browser tests exercise dialog choices, cancellation,
reopening and result feedback. Existing sign-in and profile-email-change journeys provide
the authenticated browser setup and end-to-end account behavior.

Browser unload protection uses the supported native dialog and is checked where the test
browser exposes it. Manual mobile verification covers platform limitations, touch comfort,
scroll restoration, and light/dark readability. HTTP tests alone cannot prove these behaviors.

## Out of Scope

- A redesign of Households, Commissions, Directory, administrator management or the home page.
- Global branding, a new logo, a new design system, or the separate sign-in-method UX defect.
- Editing commission membership or roles from the member detail page; creation keeps its
  existing optional commission assignments.
- New permissions, bulk actions, member deletion, import/export or an invitation system.
- A user-selectable page size, infinite scrolling, fuzzy/ranked search or a separate search service.
- Live updates, multi-device editing coordination, offline operation or durable draft recovery.
- New order, balance or payment functionality. Confirmation copy about ordering describes the
  existing domain rule; it does not add ordering to this feature.

## Further Notes

- The 14 product decisions were confirmed together after the interview; the user separately
  confirmed the HTTP/browser testing boundaries while this draft was prepared.
- An independent read-only subagent review checked all 45 stories against spec 001, the
  domain documents and related source. On 2026-10-11 the user additionally confirmed
  rejecting removal of a changed household assignment and blocking page actions during
  pending mutations. The review clarifications and agreed decisions are recorded in
  [the decision record](decisions.md#spec-review-clarifications).
- Literal search, safe direct-link fallbacks, zero-result pagination and browser-controlled
  unload warnings make the agreed behavior explicit for implementation and review. They do
  not add a new domain model or require an ADR.
- A bounded second review found the two decisions and five clarifications resolved, with
  no remaining material contradiction in the reviewed changes. This is documentary review;
  implementation must still prove the HTTP and browser criteria above.
- This is a draft, not implementation authorization. Review and user approval precede
  ticket creation through `/to-tickets`, following the repository's SDD workflow.
