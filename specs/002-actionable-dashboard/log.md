# 002 — Actionable dashboard · Log

Append-only. See [specs/README.md](../README.md) for entry kinds. The [discovery record](../../docs/ux-discovery/2026-10-27-dashboard-discovery.md) retains the complete numbered decision summary and code evidence.

## 2026-10-27 · Request

> I want you to apply this style to this application. Can we use our color pallete?
> If you want, we can create a new spec or tasks to improve it and be done in several steps if needed.

The follow-up request for this feature was:

> I want to improve the dashboard with useful information, charts and direct access actions.

## 2026-10-27 · Q&A

Q: “Should this be a role-aware dashboard for managers, members, and producers, with each seeing relevant information and actions? My recommendation is yes; mixed-role accounts would see a priority-ordered combined view.”

A: “Yes — role-aware (recommended)”

Q: “Should the dashboard first answer ‘What needs my attention now?’ and use charts only when they help that decision? My recommendation is yes; detailed reporting can stay on its own pages.”

A: “Yes — action first (recommended)”

The following interview decisions were accepted as recommended: manager cycle work before totals; member order or pickup before balance; producer cutoff and delivery before availability; deep links to task screens; the earliest unfinished cycle as manager focus; one mixed-role attention list; grouped work; sparse-data explanations; manager actionable stages; on-navigation freshness; separate current-cycle status bars; and privacy-aware role context. The exact decisions and their order are recorded in the linked discovery record.

## 2026-10-27 · Q&A

Q: “Should ‘completed cycle’ mean the spec’s closed state, and ‘household ordered’ mean an order with at least one positive ordered line, even if delivery later changed? My recommendation is yes.”

A: “Yes — closed and positive line (recommended)”

Q: “Should the four-cycle trend use four labeled bars, exact counts, and a readable data list, with each cycle linking to its summary? My recommendation is yes.”

A: “Yes — labeled bars (recommended)”

Q: “Should the trend show the existing discovery target of 10 ordering households as a labeled reference, without marking one low cycle as a failure? My recommendation is yes.”

A: “Yes — labeled target (recommended)”

The user then corrected that last answer: “all recomended, except Q22: don't show low participation.”

Q: “When you say ‘don’t show low participation,’ should we keep the four-cycle household-count chart but remove the target line and all low-participation labels or alerts? I recommend yes.”

A: “Yes — counts only (recommended)”

## 2026-10-27 · Q&A

Q: “Should ‘overdue’ apply only when a real cutoff has passed, while pickup dates remain scheduled dates rather than invented deadlines for reception or collection? My recommendation is yes.”

A: “Yes — real deadlines only (recommended)”

Q: “Should open-order opportunities and future producer cutoffs stay in role-specific context, while sent orders to deliver and pending pickups appear in ‘Needs attention’? My recommendation is yes.”

A: “Yes — separate them (recommended)”

After reading the assembled experience, the user confirmed it: “yes”.

## 2026-10-27 · Change

Created `spec.md` (R1–R10, S1–S13), `plan.md` and `tasks.md` (T01–T04) from the confirmed discovery. The proposed status preserves the repository's acceptance gate before production implementation. The manual gap review in `plan.md` covers roles, lifecycle, sparse data, timing, access, chart counting, presentation and freshness because the referenced spec-gaps skill file is absent in this checkout.

## 2026-10-27 · Change

R7 and the plan's manager read explicitly classify an empty order after cutoff as awaiting automatic send. This keeps every participating producer in exactly one visible status while excluding scheduler-owned work from the manager's action list.

## 2026-10-27 · Q&A

Q: “Please review the proposed spec and reply ‘accept spec’ if it matches what you want; I can then start T01.”

A: “ok”

The answer accepts the proposed spec, plan and T01 start. Their status was updated to Accepted before production work.

## 2026-10-27 · Q&A

Q: “Do you approve creating the milestone and four Backlog issues with these dependencies?”

A: “yes”

## 2026-10-27 · Change

`tasks.md` now links T01–T04 to Linear DRA-201–DRA-204 under the new **Actionable dashboard** milestone in Coop. Native blocking relations are T01 → T02 → T03 → T04, with no parent or contextual links. Readback confirmed each issue is in Backlog without `ready-for-agent`. This records the user's approval to publish and track the tracer sequence; implementation remains stopped as separately instructed.

## 2026-10-28 · Request

> després del login hauríem d'anar directament al dashboard

## 2026-10-28 · Change

Added R11/S14 to `spec.md`, the login response design to `plan.md` and independent T05 to `tasks.md`, following the user’s explicit destination request. Successful login now always targets the dashboard rather than a saved session destination. The existing dashboard-content slices remain separate.

## 2026-10-28 · Change

`spec.md` R1–R3/R6/R7/R9 and S1/S5/S6/S8, `plan.md` read models/time/access/verification, and
`tasks.md` T02/T03: align the dashboard with the already accepted feature-001 Sales amendment.
Sales summaries replace Order::pickup()/PickupStatus sources; validation and collection are
separate, drafts are preparation, pending validated baskets survive closed cycles and include
cycleless/attributed supplements, and no date triggers not-collected/abandonment. Weekly-base
progress excludes independent supplements; effective full reversal clears pending collection.
Member reads enforce current household access; Finance sees only its existing authorized Sales
context and destinations, with no manager cycle/reception/history rights. Mixed roles deduplicate
the same basket. The four-cycle participation chart and counts-only/no-alert behavior stay intact.

DRA-202/DRA-203 retain their original tracer prerequisites and contextual T61/DRA-216 links.
Their descriptions are synchronized, while all four 002 issues stay Backlog without ready-for-agent
and implementation stays stopped. No application, polling, write shortcut or new permission is
introduced. This documentary compatibility update uses the test-first exception; implementation
must supply the stated meaningful scenario/access tests and rendered evidence later.
Why: the user requested “després comprova la part del dashboard si cal actualitzar-la també”;
001's accepted R23a/R23b/R24/R24a/R24b and T59–T61 already require the Sales data-source cutover.

## 2026-10-28 · Change

`spec.md`, `plan.md` and `tasks.md`: preserve origin/main's independently merged login-destination
R11/S14/T05 alongside the Sales compatibility alignment. The dashboard therefore has 11 requirements
and 14 scenarios; the original four content issues remain stopped and the independent login change
is not reimplemented or republished as a new Linear ticket. All upstream and amendment log entries
retain their exact bytes.
Why: reconcile the authorized documentation PR with the existing login-to-dashboard change in main.

## 2026-10-29 · Request

> Yes, retire 002 and abandon the dashboard implementation, but keep the request and discovery notes. Then continue with the compatible 001 tasks.

## 2026-10-29 · Change

Retired `spec.md`, `plan.md` and `tasks.md` as non-executable historical records and marked feature 002 retired in `specs/README.md`. T01–T04 and DRA-201–DRA-204 are canceled; their existing issue relations and historical source links remain. The dashboard implementation draft is abandoned and retained separately for possible historical recovery, not resumed. Preserve the original request above and the unchanged [discovery record](../../docs/ux-discovery/2026-10-27-dashboard-discovery.md). A new dashboard spec requires feature 001 to finish and a new user decision. The independently shipped T05/R11 login-to-dashboard behavior and feature 001 T61 basic dashboard summary responsibility remain in force. This retirement makes no application-code change.
