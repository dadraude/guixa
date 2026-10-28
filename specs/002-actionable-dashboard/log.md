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
