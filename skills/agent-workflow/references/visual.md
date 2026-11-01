# Measurement-based UI implementation and review

Load for a task that changes rendered UI or its evidence. The implementer owns fixtures and checks;
the coordinator admits scope and coverage; the independent reviewer checks acceptance and semantics.
Use compact text/JSON reports by default. Routine implementation and review generate and open no
screenshots. Measurements establish only the selected properties, not aesthetic quality.

## Reuse components before building screens

Inspect existing components, layouts, tokens and nearby screens using repository discovery rules.
Record a `component_plan`: affected patterns, exact existing source paths, reuse or extension, and
the concrete gap justifying a new shared component. Carry it through corrections and resumptions.
Extend compatible props/slots/variants before adding parallel styling or another implementation.
Inspect consumers of a shared change; new edit surfaces still require admission. The coordinator
compares the plan with the diff before review; unexplained duplication returns to implementation.

## Declare coverage before implementation

Derive a route/role/state inventory from acceptance. Name each distinct layout, form, table and
overlay pattern and its owning page. Declare applicable populated, empty, error, disabled,
long-content and domain states, explaining omissions. Make fixture roles explicit; a member shell
does not prove manager or producer coverage. Use deterministic data and record mocked requests.
Browser measurements do not establish authorization or persistence.

Use the [manifest example](../examples/visual.json) and the project profile's minimum dimensions.
Select explicit state `cases` by risk; omitted `cases` retains the legacy Cartesian expansion.
Run `node skills/agent-workflow/scripts/visual/run.mjs plan path/to/visual.json` before broad
implementation. The coordinator checks case count, rationale, component reuse and acceptance coverage.
Prove the first end-to-end route and fixture state before widening equivalent patterns. Do not remove
failing cases or omit baseline page/role coverage to reduce execution time.
For a new essential interaction, prove one focused path through the actual component before expanding
the matrix: open its portal, use its real keyboard model, expose a long real option, select it, and
inspect the submitted source, id and action. Inventory portal surfaces from the components in use.
Closed initial markup or geometry alone does not prove that interaction. Apply this checkpoint only
to interaction work that has this risk.

During implementation probe changed patterns at their riskiest combinations first: narrow enlarged
text for forms/tables, both themes for colors, and opened overlays for portals. Fix the pattern before
rolling it out. Use DOM structure, computed styles, bounds and keyboard interactions before any image.

## Fixture and assertion contract

The runner needs Node 22+, installed Chrome/Chromium (`CHROME_BIN`), and a separately started task-owned
fixture server. Use the project profile for its exact server command and environment; record any build
prerequisite. Serve the actual task source and committed assets. The fixture must apply the requested
appearance, text enlargement and motion preference.

- `baseUrl` plus view `path`/`query` selects the route/role. State `query` selects deterministic states.
- `root` and `panels` must match visible content. State `ready` and `settled` select the real prepared
  state, including opened portal surfaces. Do not mistake an in-flight menu animation for readiness.
- `fonts` declares real font faces. `systemFonts: true` is only for intentional platform-font cases.
- `content` selects important text/controls for bounds and clipping checks; the default includes
  headings, labels, controls, links and cells. Narrow truncation exceptions need acceptance evidence.
- `focus` lists controls to reach with real Tab presses, including below the viewport. An empty list
  needs `noFocusReason`. Verify arrow-key menus with their actual interaction model in a focused probe.
- State `checks` can override view selectors/assertions for its owning state.
- Optional `assertions` is an array of rules, each with a CSS `selector` and one or more expectations:
  `count` (including zero), `text` (exact trimmed text), `attributes` (exact values; null means absent),
  `styles` (CSS property/value pairs), `fontSizeRem`, `gapRem` (both axes; distinct gaps use `rowGapRem`/`columnGapRem`), `minWidth`/`minHeight` (CSS pixels), or
  `contrast` (minimum ratio). All matching nodes must satisfy the expectations. Rem expectations
  scale with enlarged text. Use selectors covering every instance of a repeated acceptance pattern.

For example:

```json
{
    "assertions": [
        { "selector": "h2", "fontSizeRem": 0.875 },
        { "selector": ".actions", "gapRem": 0.5 },
        {
            "selector": "button",
            "minWidth": 44,
            "minHeight": 44,
            "contrast": 4.5
        },
        { "selector": "#confirm", "attributes": { "aria-expanded": "true" } }
    ]
}
```

Contrast handles solid RGB/RGBA foregrounds and ancestor backgrounds, including alpha composition.
Images, gradients, opacity, filters, blending or an unknown background are explicitly unverified.
Unsupported assertions/properties also remain unverified and block the gate. Resolve them with a
focused check or the justified preview path; do not remove the requirement or claim a full
accessibility audit. Cover relevant DOM state and interaction semantics alongside geometry.

## Pre-review gate

Commit code, fixture and manifest; keep generated evidence in an untracked task artifact directory.
Run from the task worktree:

```sh
CHROME_BIN="/path/to/chrome" node skills/agent-workflow/scripts/visual/run.mjs preflight path/to/visual.json .atl/task-name/visual FIXED_BASE_SHA
CHROME_BIN="/path/to/chrome" node skills/agent-workflow/scripts/visual/run.mjs run path/to/visual.json .atl/task-name/visual FIXED_BASE_SHA
node skills/agent-workflow/scripts/visual/run.mjs verify path/to/visual.json .atl/task-name/visual FIXED_BASE_SHA
```

A preflight checks Node, the installed browser, the tracked manifest, fixed base, safe ignored output,
and readiness of the first expanded fixture case (route, ready selector, fonts and settled elements).
It writes no report or screenshots and does not start the server. Its result is readiness only; the
full run still checks every case. Confirm separately that the server uses the intended task source,
role authorization and deterministic data. Neither readiness nor visual measurement establishes
source identity, authorization or persistence acceptance.

A default run writes a version-2 `report.json` and integrity sidecar, with measurements, assertion
observations, focus results, failures and execution identities. No PNG/gallery is created. The verifier
rejects missing/duplicate/failed cases, unverified assertions, incomplete focus/matrix evidence,
dirty tracked files, altered reports and incompatible identities. A failed new run invalidates prior
success. Keep the report and successful verifier output for the reviewer.

Require source-backed acceptance coverage, component reuse, the successful report, focused behavior
checks and complete correction sweeps before launching independent review. A green report does not
prove that the manifest covers every requirement. Missing evidence returns to implementation without
consuming a review round.

## Reuse unchanged rendering evidence

Without a reviewed input declaration, evidence remains bound to its original HEAD/base. For safe reuse,
add `renderInputs` with a nonempty `review` reference identifying the coverage review, and arrays named
`fixtures`, `source`, `styles`, `assets`, `configuration`, `dependencies` and `data`. Each array lists
repository-relative tracked files or directories, without globs. Empty categories require a reason in
`notApplicable` under that category name. Include full transitive imports, shared layouts, fixture data,
fonts/assets, build configuration, lockfiles and backend providers actually used by the fixture.

The coordinator reviews completeness against the served pathway; a declaration alone is not proof.
Reuse requires deterministic fixture data and environment. Live database/network responses without
hashed deterministic snapshots, or symlinks to external content, cannot establish reuse.
Unknown coverage means no reuse: omit the declaration and rerun. Inputs with untracked files or
unresolved paths cannot establish reuse. Generated/ignored assets need a reproducible build tied to
declared source and recorded prerequisites; otherwise rerun.

The runner fingerprints declared tracked inputs, including newly added files within directories.
`verify` may reuse successful version-2 evidence across HEAD/base changes only while those inputs,
the manifest and the harness are unchanged. It leaves the execution report intact and writes
`verification.json` with original and current identities, input/report hashes and verification time.
Report reused evidence as reused, never as a fresh execution. Changed inputs require a new run.
Legacy capture reports retain their original strict identity and screenshot checks. This exception
does not waive focused application checks, acceptance reconciliation or current-candidate CI.

## Optional previews

Generate a small preview only for a new design direction, an unexplained failure, or the user's
request. Append `--capture=<case-id>` for selected cases (repeat as needed); `--capture` captures all
declared cases only when that breadth is justified. Measurement coverage still runs for every case.
Only requested cases produce PNGs; a gallery is optional output of capture mode. Do not automatically
open it. Inspect the affected region once when appearance needs judgment. Explicit design approval
gates remain in force; unchanged captures need no repeated inspection.

## Correct a defect pattern

Reproduce each measurable finding with a failing probe, name its mechanism and inventory comparable
instances inside admitted scope. Correct or verify every instance before the next review. Return
`pattern`, `instances_checked`, `fix_or_evidence_per_instance` and `remaining_gaps` with regression
results. Do not fix only the reviewer's selector. New surfaces require admission; out-of-scope
instances are recorded with evidence. Keep focused probes during corrections and regenerate or
verify reusable final evidence before review.

## Review handoff

Supply component decisions, route/role/state inventory, selected cases/rationale, current successful
verification, focused assertions/interactions, prior finding ledger and completed instance sweep.
The reviewer reads compact reports and source/tests, checking all affected requirements and the full
diff on the first round. Report all findings together and identify uninspected areas. Follow-up reviews
focus on corrections and affected patterns while retaining coverage of the candidate. Repeated new
instances of the same defect mean an incomplete sweep, not an invitation for another blind full run.
