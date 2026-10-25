# Visual implementation and review

Load for a task that changes rendered UI or its evidence. The implementer owns the fixture and checks;
the coordinator admits the scope and gates review; the reviewer checks completeness and the result.
Keep the project matrix and accessibility thresholds in the execution profile or linked acceptance.

## Reuse components before building screens

Before editing UI, inspect the existing component library, shared layouts, approved tokens and nearby
screens that implement the same interaction. Follow the project's code-discovery policy. Record a
`component_plan`: each affected UI pattern, the exact existing component/source path, whether it is
reused or extended, and the reason for any new shared component. Carry this plan in the task's retained
handoff so the implementer resumes the same design decisions through every correction.

Compose pages from existing components. Extend a compatible component through its established props,
slots or variants before introducing another implementation. Use the existing semantic tokens;
do not invent per-page palettes, button/input styles or parallel table/form patterns. When repeated
markup or responsive behavior has no suitable component, extract one shared component and use it at
the affected call sites within scope. A genuinely unique layout can remain page composition; do not
create a configurable component for every wrapper or abstract hypothetical future reuse.

A new component needs evidence of the existing alternatives inspected and the concrete gap they cannot
serve. Preserve semantics, accessibility and interaction behavior when extending shared components;
inspect their callers and verify affected consumers. Additional shared edit surfaces still go through
coordinator admission. A visual task is not a general component-library rewrite.

Before review, the coordinator checks the component plan against the actual diff. Missing paths,
unexplained duplication, bypassed shared components or new styling that duplicates existing tokens
return to the same implementer. The reviewer checks this reuse contract alongside rendered evidence;
a passing screenshot matrix does not waive it.

## Declare coverage before implementation

Commit a task manifest based on the [example](../examples/visual.json). Enumerate every affected
page/role as a view and the applicable populated, empty, error, disabled, long-content and domain
states. Inventory affected shared layouts, overlays and excluded routes too. Record in the handoff
why a state does not apply. The runner expands every declared view/state across the entire matrix;
do not use representative pages as proof for unchecked pages. Resolve the matrix against acceptance
before work starts; do not shrink it to make a failed or missing case disappear.

Use the [shared runner](../scripts/visual/run.mjs), not a copied ticket-specific browser client.
It uses Node 22+ and an installed Chrome/Chromium (`CHROME_BIN`); no package install is needed.
The fixture server is task-owned and started separately using the project's approved environment.
Bind the fixture to the task worktree's actual components/styles and deterministic data. Record which
requests are mocked; browser layout evidence does not establish persistence or authorization.

The JSON contract:

- `baseUrl`, and per view `path`/`query`, identify the fixture. The runner supplies `mode` and `text`
  query parameters. The fixture must apply the requested appearance and root text enlargement;
  the runner measures the actual result against `darkSelector` (default `html.dark`) and `rootFontPx`
  (default 16). `system-light`/`system-dark` send `mode=system` and emulate the corresponding OS setting.
- `root` must select the rendered view; optional state `ready` selects a state-specific ready marker.
  `panels` selects its content containers. Missing root/panels fails the run.
- `fonts` lists CSS font descriptors for the real web fonts. Every descriptor must load a face.
  Set `systemFonts: true` only for intentionally platform/fallback-font cases; availability is then
  checked without claiming a downloaded face. Inspect platform font appearance separately.
- The default content probe checks headings, labels, controls, table cells and `[data-visual-text]`.
  Set `content` when needed to include important names, amounts or custom components. Inspect selector
  coverage; a geometry pass proves only what was selected. Deliberate truncation needs acceptance
  evidence and a narrowly scoped selector adjustment, never a blanket overflow exemption.
- `focus` lists controls to reach with real Tab presses, including those below the first viewport.
  State `checks` may override selectors/fonts/focus for disabled or alternative layouts. A view or
  state without targets needs `focus: []` and a substantive `noFocusReason`.
- Optional state `settled` lists selectors for capture readiness, including opened portal panels and
  backdrops. Otherwise the root is used. All matches must be visible, opaque, open when they expose
  `data-state`, and free of unfinished animations throughout their subtree. For a continuously animated
  loading state, select its stable capture surfaces and explicitly record that moving content remains.
  State `reducedMotion: true` emulates that preference; separately inspect the resulting motion behavior.

The shared probes detect document overflow, panel/content escape, zero-width content, text bounds,
clipping ancestors and covered/offscreen keyboard targets. A focus target must expose `:focus-visible`
and an outline or shadow; an alternative valid focus treatment needs a reviewed harness adaptation.
Contrast, touch-target size, semantic correctness, motion behavior and aesthetic hierarchy still need
the acceptance-specific checks and human inspection; this runner does not claim a complete accessibility audit.

## Pre-review gate

Commit code, fixture and manifest before producing final evidence. Keep output outside tracked source
(or in the project's ignored results directory). All fixture source and assets must be committed;
do not serve a different checkout or reuse a stale build. Record the server command and any build
prerequisite. The manifest must be tracked. From the task worktree run:

```sh
CHROME_BIN="/path/to/installed/chrome" node skills/agent-workflow/scripts/visual/run.mjs run path/to/visual.json /tmp/task-visual-evidence FIXED_BASE_SHA
node skills/agent-workflow/scripts/visual/run.mjs verify path/to/visual.json /tmp/task-visual-evidence FIXED_BASE_SHA
```

`run` writes PNGs, `report.json` and a browsable `index.html` contact sheet, and checks every case even
when one fails. It invalidates a previous report before starting. `verify` fails on missing/duplicate/
failed cases, missing keyboard coverage, wrong measured matrix, changed/missing screenshots, changed
manifest/harness or HEAD/base, and dirty tracked files. A dirty exploratory run is allowed for diagnosis
but exits nonzero and cannot satisfy review. Rerun on the final candidate after any commit or base change.
Neither command proves the manifest includes all acceptance requirements: the coordinator compares
its views/states/matrix to the declared inventory before accepting the machine result.

Before launching a reviewer, require the successful verifier command, exact HEAD/base, manifest,
report/gallery paths, acceptance-specific results and implementer inspection of every capture.
Record unavailable checks as blockers. A screenshot count or a green application test suite is not
coverage. Complete missing evidence inside implementation; do not spend an independent review round
asking the reviewer to build it. Preserve final artifacts for reviewer access and the delivery record.

For a new visual direction, provide a representative light/dark preview with cards, inputs and overlays
early enough for user feedback before final capture/publication; preserve explicit visual approval gates.

## Correct a defect pattern

After a concrete finding, reproduce it with a failing probe, name the failure mechanism, and inventory
other instances inside the admitted task scope. For example, enlarged-text clipping requires checking
all affected tables, multicolumn forms and rows with adjacent controls, across the agreed widths/roles.
Fix or verify every instance before requesting the next review; keep the original reproduction as a
regression check. Do not restrict the correction to the selector named by the reviewer.

Return `pattern`, `instances_checked`, `fix_or_evidence_per_instance`, and `remaining_gaps` along with
the original finding's result. New edit surfaces still require coordinator admission; product decisions
still go to the user. A preexisting instance outside task scope is recorded with evidence, not silently
fixed or used to waive task acceptance. Regenerate affected evidence and run the complete task matrix
when the changed pattern is shared; reuse unrelated application checks only where the lifecycle permits.

## Review handoff

Give the reviewer the component plan and reuse decisions, declared inventory, successful coverage
result, gallery, specific accessibility
checks, prior finding ledger and pattern-sweep results. Inspect the whole declared matrix and report
all actionable findings together; identify any uninspected area instead of implying completion.
On later rounds, use the prior ledger and artifact hashes to focus fresh inspection on changed outputs
and affected patterns. Do not reopen identical images merely to repeat work; still establish sufficient
coverage for the full current candidate. Record newly found defects separately from failed fixes.
Keep the existing correction/review budget. Repeated new instances of the same defect indicate an
incomplete sweep; reassess coverage before consuming another round or requesting an exception.
