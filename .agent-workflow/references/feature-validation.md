# Feature evidence gate

Use the read-only CLI before advancing a selected delivery slice:

```sh
node .agent-workflow/scripts/validate-feature.mjs NNN-feature next-stage
```

`next-stage` is one of `discovery`, `scope`, `spec`, `plan`, `tasks`, `implementation`,
`review`, `integration`, `acceptance`, or `complete`. A gate validates prerequisites for the named
stage, cumulatively. `discovery` needs only the original Request; `scope` additionally needs the
completed discovery. `complete` checks the final acceptance record. The CLI prints JSON with
`evidenceReady`, `stage`, and labeled `findings`; exit 0 means the evidence pointers are structurally
ready, 1 means evidence is missing or stale, and 2 means the invocation or manifest is malformed.
It never runs app checks, writes files, approves scope, or changes tracker/PR state.

Store pointers at `specs/NNN-feature/workflow.json`. Start with an empty stage map and add evidence as
work is completed:

```json
{
    "schemaVersion": 1,
    "slice": { "id": "first-delivery", "scopeRefs": [], "deferredRefs": [] },
    "stages": {}
}
```

The selected `slice.id` names the delivery slice being checked. `scopeRefs` and `deferredRefs` point
to current requirements/scenarios when they exist; a new discovery need not invent R#/S# yet. Future
roadmap slices may remain deferred. A stage record from `scope` onward includes a `sliceId` equal to
the selected slice. Each named evidence value is `{ "path": "repo/relative/file", "sha256":
"lowercase-64-hex-digest" }` over the current file bytes. Paths must be real files within the repo.
The validator rejects traversal and symlinks outside the repo. Use `shasum -a 256 path` to prepare a
pointer. `discovery.request` points to the feature's Request history entry; `discovery.discovery`
points to `specs/NNN-feature/discovery.md`. The canonical `spec.specification`, `plan.plan`, and
`tasks.tasks` point to that feature's `spec.md`, `plan.md`, and `tasks.md` respectively.

| Stage record     | Required named evidence                        |
| ---------------- | ---------------------------------------------- |
| `discovery`      | `request`, `discovery`                         |
| `scope`          | `slices`, `selectionDecision`                  |
| `spec`           | `specification`, `gapReview`, `acceptance`     |
| `plan`           | `plan`, `acceptance`                           |
| `tasks`          | `tasks`, `acceptance`                          |
| `implementation` | `changes`, `checks`                            |
| `review`         | `independentReview`                            |
| `integration`    | `integratedRevision`, `ci`                     |
| `acceptance`     | `acceptanceMatrix`, `review`, `userAcceptance` |

`spec.gapReview` points to the immutable G1–G14 review history entry with reviewed source identity.
`spec`, `plan`, and `tasks` acceptance pointers identify the recorded user decision. Each stage may
also carry `sources: [{path,sha256}]` for additional inputs. Record every linked area file or other
source used for a split spec/plan; the CLI verifies their hashes but cannot infer every Markdown
relationship. Record the integrated commit and candidate-bound CI in their evidence files.

This manifest is an evidence index, not another task registry or a claim that human acceptance can be
proved by file existence. A hash change invalidates the pointer; re-review affected scope and obtain
any required renewed acceptance before updating it. Prior explicit acceptance of the same concrete
scope can be referenced from the actual history/document source. Existing features need no fabricated
backfill: add real pointers where available and report missing evidence otherwise. A retired or
superseded feature spec blocks progression even if its manifest says otherwise; independently shipped
obligations require their own active scope. `evidenceReady: true` is not product acceptance.
