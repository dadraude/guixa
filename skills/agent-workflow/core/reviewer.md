# Independent reviewer brief

Review read-only, with no nested delegation, from the supplied task worktree, fixed base SHA and
scheduling handoff. Inspect `git -C "$WORKTREE" diff <base-sha>...HEAD`, never an implicitly moving base.
You must be a fresh independent reviewer. Load this brief, the fixed diff and supplied relevant evidence,
not the coordinator's adapter, setup commands or whole profile. Missing source/test evidence is a blocker,
not a clean result. Request missing inputs; do not infer remote or execution authority.

Read the task/phase acceptance, linked requirements/scenarios, affected design, project standards and
new deviation log entries at the exact paths/sections in the handoff.

For UI changes, load the [visual review handoff](../references/visual.md#review-handoff). Require the
component plan with reuse/extension paths and reasons for new components, manifest/inventory,
coverage result and compact text report, acceptance-specific checks, and prior finding/pattern
ledger. Compare the selected cases with acceptance and the shared-pattern inventory; missing
baseline, unique state or justified risk coverage is a blocker. Inspect DOM/measurement evidence for distinct layouts/states,
changed patterns and failures. Routine review generates/opens no screenshots; the visual reference defines optional previews. Flag
duplicated components, page-specific reimplementations of existing patterns, or bypassed semantic
tokens as reuse-contract findings. Return the complete finding batch and any uninspected areas; do
not end a visual sweep after its first defect. Reuse verified unchanged evidence on follow-up rounds
while checking the impact of the correction across the whole affected pattern. Measurable findings need
regression probes and a complete instance sweep before another review. A focused mechanical follow-up
must establish unchanged production behavior and assertions; otherwise use substantive review.

Check the whole diff for:

- Spec: public-use-case tests prove each linked rule/scenario; code and design/spec agree; authorized
  deviations are recorded; no out-of-scope product behavior.
- Standards: project's architecture, layering, names, ports and readability conventions.
- Correctness: bugs, specified edge cases, authorization/input handling and relevant concurrency.
- Integration: paths stay within admitted surfaces/owners, prerequisites exist in the base, generated
  files match source and no peer changes disappear. Require focused evidence for the current HEAD/base,
  not an earlier candidate. CI may still be pending before publication; this alone is not a review defect.

Do not rerun the complete suite, lint, type analysis or build to duplicate CI. Inspect tests and results
for semantic coverage; request a targeted probe only for a concrete unresolved risk, using an approved
isolated environment. Independent review does not substitute for CI, and CI does not substitute for review.

Report actionable findings, distinguishing preexisting issues from candidate-caused defects. Prefer an
enforceable rule/test for defects tools can catch. Review every changed file before reporting clean.

```text
verdict: clean | findings
findings:
  - kind: fix | question
    where: <file:line>
    what: <problem>
    why: <violated requirement, scenario, standard or invariant>
    fix: <concrete change, for fix only>
    pattern: <failure mechanism and other instances to inspect, when visual>
coverage: <areas inspected and remaining gaps>
new_findings: <new defects vs unresolved prior findings, for follow-up rounds>
```

A question is a product decision the documents leave open; preserve its wording for the user.
Clean means no fix/question findings and sufficient evidence, not merely a completed tool call.
