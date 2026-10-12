# Issue tracker: Linear (tickets) + repo (specs)

Specs live in this repo. Implementation tickets live in Linear.
Use the Linear MCP tools (`save_issue`, `get_issue`, `list_issues`, `save_comment`, …).

## Workspace check (first Linear call of every session)

Before any other Linear call, call `get_workspace` and check that the workspace **name** is
`Draude` (URL `https://linear.app/draude`). If it is anything else, stop: do not read, create
or change any issue, and tell the user which workspace is connected. Sub-agents that use
Linear run the same check before their first Linear call.

- Workspace team: **Draude** (key `DRA`). Product work belongs to **Guixa**; workflow and
  agent/process work belongs to **Workflow autonomy** (`P-DRA-2`). Both use this repository.
- All content (titles, bodies, comments) is written in English.

## Project selection

`/run-session <project name or ID>` works on exactly the project the user names. If omitted, ask
and wait; never default to Guixa. After checking the workspace, resolve the project with
`get_project`; unknown or ambiguous input also requires an answer. Use the resolved ID in every
project-scoped read and write: status, leftovers, triage, scope, new open topics and close checks.
Do not mix queues or close another project's Todo. A standalone `/ship-ticket DRA-NN` gets its
project from that ticket; within a session, a different project requires a new scope decision.
For ticket creation outside a session, ask for the destination project when no parent supplies it.

Follow-ups and escalations always inherit the parent ticket's project and, when present, its
milestone. If the parent has no project, ask before creating the follow-up. With no parent milestone,
omit `milestone`; do not invent one or copy one from a different project.

## Specs (repo)

- One feature per directory: `specs/NNN-<feature-slug>/`, numbered from `001`.
- The spec is `specs/NNN-<feature-slug>/spec.md`, committed with the code.
- A spec carries a `Status:` line near the top: `draft` → `approved`.
  Tickets are only created from an `approved` spec (the user approves it).

## Tickets (Linear)

- **Create**: `save_issue` with `team: "Draude"`, `project: <selected project ID>`, a title,
  and a body that starts with `Spec: specs/NNN-<feature-slug>/spec.md` (plus a
  `links` entry to the file on GitHub: `https://github.com/dadraude/guixa/blob/main/specs/…`),
  and `milestone` set to the spec's milestone (see Milestones). For process work without a spec,
  record the problem, decided scope and verification criteria in the issue instead; omit the
  spec prefix/link and milestone. Follow-ups use the parent project/milestone rule above.
- **Blocking**: native Linear relations via `blockedBy` / `blocks`.
- **Read**: `get_issue` with the identifier (e.g. `DRA-12`), plus `list_comments`.
- **List**: `list_issues` filtered by `project: <selected project ID>` and label/state.
- **Comment**: `save_comment`. **Labels**: `addLabels` / `removeLabels`.
- **Close**: set `state: "Done"` (or `"Canceled"` for wontfix) with a closing comment.
- Workflow states: Backlog → Todo → In Progress → In Review → Done.
- **Todo is the current session's scope**: issues move there when the session picks them and leave it
  before the session ends (In Review, Done, or back to Backlog). Outside a session, Todo is empty.

## Project status (ADR 0009)

- Linear is the only record of project status; there is no status file in the repo.
- **Session update**: at the end of each session, one status update in the selected project
  (`save_status_update`, `type: "project"`, `project: <selected project ID>`) whose body starts with
  `**Session NN (YYYY-MM-DD)**`: what was done
  (tickets and PRs by identifier, decisions with their ADR or `decisions.md` entry) and the
  concrete next step. Read the latest one with `get_status_updates` filtered to that project.
  Session numbering is per project: latest number + 1, or 1 when it has no updates. Ask if an
  existing update has no usable number; do not borrow another project's number.
- **Autonomy metrics**: every session update also carries the block below, counted from Linear and
  GitHub (never from memory), covering the period since that project's previous status update.
  For its first update, use the project's creation time as the start and state that window.
  These are project metrics, not repository totals: filter issues by project ID and attribute PRs
  through their linked DRA issues. A PR with several issues in one project counts once. A PR linked
  to multiple projects is reported separately as shared and excluded from each project's autonomous
  PR count and streak; missing or uncertain attribution is reported as unknown, never a success.
  Resolve linked issues even if their IDs occur only in the PR title/body; never assume every PR in
  this repository belongs to Guixa. Use current project membership, including moved issues.
  - **PRs merged** (`gh pr list --state merged`) and how many had **no human correction**: no changes
    requested and no commits pushed by the repo owner after the PR was opened
    (`gh pr view <PR> --json reviews,commits`; the owner's commits after the PR's creation time count
    as corrections, the agents' CI-fix and correction commits do not). A PR is judged when merged: PRs
    still In Review are not counted yet. Also the **streak**: consecutive merged PRs with no human
    correction in the selected project, counting back from the latest attributed PR (input for the
    "5-10 consecutive PRs" threshold in DRA-128 and DRA-139). The streak may extend before this update's reporting window: walk back
    through attributed PRs until a correction; shared or uncertain attribution cannot extend it.
    If the available history or actor identity cannot establish human correction, report an unknown
    streak instead of treating an agent-authored commit under the owner's account as proof.
  - **Follow-ups created** in the selected project, grouped by their `cause` label
    (`list_issues` filtered by project, creation window and label): `spec-gap`,
    `ticket-too-big`, `pre-existing`, `product-question`, `review-finding`, `other`. Every follow-up
    issue carries exactly one (Linear label group `cause`).
  - **Reading**: one line. If most follow-ups share a cause, name the process fix (e.g. spec gaps →
    fix the spec review).
- **Open topics** (questions, decisions to take, manual checks) are issues in the selected project,
  never only text in a status update.

## Milestones

Milestones group tickets and show progress; the order of work still comes from blocking relations.

- **One per spec in its owning project**, named `NNN Feature name` (e.g. `001 Households, members and commissions`),
  created with `save_milestone` when the spec is approved. It holds the spec's tickets, its
  amendments and their review follow-ups.
- **`Ready to go live`**: what must be settled before production (backups, data migration, CI
  protection), independent of any spec.
- Tooling and process work (linters, agents, review flow) belongs to Workflow autonomy, without a
  milestone by default. Product specs and `Ready to go live` belong to Guixa.
- A follow-up created during review takes its parent ticket's project and optional milestone.

## When a skill says "publish to the issue tracker"

- `/to-spec` is replaced by `/guixa-to-spec`; do not use the global one. It writes `specs/NNN-<feature-slug>/spec.md` in the repo (NOT Linear), with `Status: draft`.
- `/to-tickets` is replaced by `/guixa-to-tickets`; do not use the global one. `/triage`, and anything else → create a Linear issue in the selected project (or the parent's project for follow-ups).

## When a skill says "fetch the relevant ticket"

A Linear identifier (`DRA-…`) → `get_issue`. A `specs/…` path → read the file.

## Wayfinding operations

Used by `/wayfinder`.

- **Map**: a Linear issue in the explicitly selected project labelled `wayfinder:map`, holding the Notes / Decisions-so-far / Fog body.
- **Child ticket**: a sub-issue of the map (`parentId`), in its project and optional milestone, labelled `wayfinder:<type>` (`research`/`prototype`/`grilling`/`task`).
- **Blocking**: `blockedBy`. A ticket is unblocked when every blocker is Done.
- **Frontier**: open, unblocked, unassigned children of the map; first by creation order wins.
- **Claim**: `assignee: "me"`, the session's first write.
- **Resolve**: comment the answer, set `state: "Done"`, then append a gist + link to the map's Decisions-so-far.
