# Issue tracker: Linear (tickets) + repo (specs)

Specs live in this repo. Implementation tickets live in Linear.
Use the Linear MCP tools (`save_issue`, `get_issue`, `list_issues`, `save_comment`, …).

## Workspace check (first Linear call of every session)

Before any other Linear call, call `get_workspace` and check that the workspace **name** is
`Draude` (URL `https://linear.app/draude`). If it is anything else, stop: do not read, create
or change any issue, and tell the user which workspace is connected. Sub-agents that use
Linear run the same check before their first Linear call.

- Workspace team: **Draude** (key `DRA`) · Project: **Guixa** (every ticket belongs to it)
- All content (titles, bodies, comments) is written in English.

## Specs (repo)

- One feature per directory: `specs/NNN-<feature-slug>/`, numbered from `001`.
- The spec is `specs/NNN-<feature-slug>/spec.md`, committed with the code.
- A spec carries a `Status:` line near the top: `draft` → `approved`.
  Tickets are only created from an `approved` spec (the user approves it).

## Tickets (Linear)

- **Create**: `save_issue` with `team: "Draude"`, `project: "Guixa"`, a title,
  and a body that starts with `Spec: specs/NNN-<feature-slug>/spec.md` (plus a
  `links` entry to the file on GitHub: `https://github.com/dadraude/guixa/blob/main/specs/…`).
- **Blocking**: native Linear relations via `blockedBy` / `blocks`.
- **Read**: `get_issue` with the identifier (e.g. `DRA-12`), plus `list_comments`.
- **List**: `list_issues` filtered by `project: "Guixa"` and label/state.
- **Comment**: `save_comment`. **Labels**: `addLabels` / `removeLabels`.
- **Close**: set `state: "Done"` (or `"Canceled"` for wontfix) with a closing comment.
- Workflow states: Backlog → Todo → In Progress → Done.

## When a skill says "publish to the issue tracker"

- `/to-spec` → write `specs/NNN-<feature-slug>/spec.md` in the repo (NOT Linear), with `Status: draft`.
- `/to-tickets`, `/triage`, and anything else → create a Linear issue in project Guixa.

## When a skill says "fetch the relevant ticket"

A Linear identifier (`DRA-…`) → `get_issue`. A `specs/…` path → read the file.

## Wayfinding operations

Used by `/wayfinder`.

- **Map**: a Linear issue in project Guixa labelled `wayfinder:map`, holding the Notes / Decisions-so-far / Fog body.
- **Child ticket**: a sub-issue of the map (`parentId`), labelled `wayfinder:<type>` (`research`/`prototype`/`grilling`/`task`).
- **Blocking**: `blockedBy`. A ticket is unblocked when every blocker is Done.
- **Frontier**: open, unblocked, unassigned children of the map; first by creation order wins.
- **Claim**: `assignee: "me"`, the session's first write.
- **Resolve**: comment the answer, set `state: "Done"`, then append a gist + link to the map's Decisions-so-far.
