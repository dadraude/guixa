# 001 — Weekly orders · Tasks

Status: T01–T51 retain their historical approvals. **2026-10-28 amendment: accepted**
(T52–T63 and updated T33). The user accepted the document structure and task breakdown. Application
implementation and Linear publication remain separate scopes.

**2026-10-31 reslicing: accepted and published to Linear.** T56–T63/T33
are reconciled below and T64–T68 are accepted additions; existing issue identities are retained.
T64–T68 map to DRA-224–DRA-228. The [review and publication delta](tasks/reslicing-review.md) separates the current tracker snapshot
from the accepted graph. Product requirements and plan decisions are unchanged. Implementation status belongs to Linear, not this table.

Source: [spec](spec.md) and areas · [plan](plan.md) · [coverage allocation](tasks/coverage.md).

## File map

| File                                                             | Responsibility                                                                                                                     |
| ---------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| `tasks.md`                                                       | Canonical Tnn registry and prerequisite graph; one row per executable task                                                         |
| [tasks/catalog.md](tasks/catalog.md)                             | T52–T54: unified producer catalogue, safe history and stock visibility                                                             |
| [tasks/sales.md](tasks/sales.md)                                 | T55–T58 and T64–T66/T68: independent basket, Finance, returns, receipts, member reads and corrections                              |
| [tasks/weekly-and-collection.md](tasks/weekly-and-collection.md) | T59–T63/T67: weekly cutover, supplements, collection, indicators, lifecycle and cleanup; T33 closure                               |
| [tasks/coverage.md](tasks/coverage.md)                           | All 65 requirements and 91 scenarios (S1–S92, S90 retired): retained responsibility and amendment responsibility                   |
| [tasks/traceability.md](tasks/traceability.md)                   | T33 audit: assertion-level evidence and verdict for every requirement and scenario, closure prerequisites and gaps filled          |
| [tasks/audit-2026-11-05.md](tasks/audit-2026-11-05.md)           | T69–T76: follow-up tasks from the 2026-11-05 SDD↔code audit (documents, bugfixes, refactors, tests, send button, movement history) |
| [tasks/baseline.md](tasks/baseline.md)                           | Historical T01–T51 scopes, approval gates and dated evidence; superseded behavior is explicitly historical                         |

IDs are feature-global. Detail files do not start separate numbering or redefine dependencies.
The spec owns behavior, the plan owns design; task details describe a reviewable delivery and checks.
The index remains at `specs/001-weekly-orders/tasks.md`, matching the workflow's Tnn lookup.

## Execution contract

- Authorization refactor (2026-11-01, direct user request, no new task ID): existing and future slices
  use the plan's Laravel gates and policies inside use cases. Preserve each operation's role/ownership
  permissions and HTTP 403 behavior; domain invariants remain in Domain. This supersedes the historical
  T04 implementation detail about Actor assertions and `NotAllowed`, without changing its requirements.
- Each new task owns its domain/application/adapters/persistence/HTTP/UI changes, tests and affected
  documentation together. Start with failing public Application tests; use shared repository contracts
  and focused HTTP/role tests. T63 is a justified contract-stage refactor, not a schema-only feature.
- T52 is a catalogue tracer; T55 opens the independent purchase path end to end. Later slices widen
  those working paths. T59 is the coupled weekly writer cutover; T63 removes superseded ownership.
  Expand/migrate/contract here means code sequencing, never production data migration or backfill.
- Use a fresh schema in isolated task databases. No production import, remapping, reset operation or
  permanent compatibility route/dual writer is part of these tasks. Earlier slices keep the still-owned
  weekly path working until T59 switches it atomically; new Sales never charges a source through both paths.
- Preserve allocation math, cutoffs, invitations, translations and accepted visual work. Reuse the plan's
  existing forms/components and shared UI library. All new copy is translated immediately, even while
  T36 remains open. Follow the profile's measure-first visual workflow for changed UI and risk states.
- `After` lists technical prerequisites. Original human/visual approval gates still apply in the
  historical scopes. Dependency-ready does not mean authorized or safe to edit in parallel: catalogue,
  routes, navigation, migrations and settlement files are shared surfaces handled by scheduling.
- Each revised slice names a first behavioral/persisted/rendered checkpoint before broad implementation.
  The implementer reports the assertion and actual result to the coordinator; this is not a new user
  approval or independent-review round. Extend changed shared contracts in both adapters and assert
  exact fixture amounts/status/identity before expanding the visual matrix.
- Done means the slice's meaningful assertions, repository/transaction/authorization checks, focused
  regressions, applicable architecture/static checks and required UI evidence pass; use candidate-bound
  CI for the full suite. Naming an R#/S# in a test alone is insufficient. Log deviations before its PR.
- When a slice changes module interfaces or persistence, update `architecture/modules.php`,
  the [module-contract documentation](../../docs/architecture/modules.md) and relevant rejection tests;
  run the complete `tests/Architecture` suite. Declare only delivered exports, permitted callers,
  dependencies and table ownership. Add a rejection case for any new checker syntax or exception;
  keep exceptions narrow and do not register future behavior early. A11/A12 apply to each slice.

Retained T36 clarification from integrated DRA-205: all applicable static user-facing text,
including original Catalan literals, titles, breadcrumbs, placeholders, accessible labels, mail and
framework messages, must be translatable and display Catalan. Coverage includes source translation
calls and whole-message placeholders; machine identifiers, proper names and user data stay intact.
See [current translation contracts](plan.md#translations); the linked T36 baseline remains historical.

## Canonical task registry

For T01–T51 except T33, linked scopes are historical: keep delivered behavior that the amendment
preserves; implement changed behavior through T52–T68; T69–T76 are the 2026-11-05 audit follow-ups. Existing issue IDs keep their lineage; explicit scope narrowing below is accepted, never a silent repurposing.

| ID  | Task                                                                                                                 | Covers                                                                                                                                                                                      | After                                                                                    |
| --- | -------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------- |
| T01 | <a id="t01"></a>[Application bootstrap and CI](tasks/baseline.md#t01)                                                | [Retained scope](tasks/baseline.md#t01)                                                                                                                                                     | —                                                                                        |
| T02 | <a id="t02"></a>[Architecture tests](tasks/baseline.md#t02)                                                          | [Retained scope](tasks/baseline.md#t02)                                                                                                                                                     | T01                                                                                      |
| T03 | <a id="t03"></a>[Shared value objects and transactions](tasks/baseline.md#t03)                                       | [Retained scope](tasks/baseline.md#t03)                                                                                                                                                     | T01                                                                                      |
| T04 | <a id="t04"></a>[Actor roles and shared layout](tasks/baseline.md#t04)                                               | [Retained scope](tasks/baseline.md#t04)                                                                                                                                                     | T01                                                                                      |
| T05 | <a id="t05"></a>[Repository contract-test support](tasks/baseline.md#t05)                                            | [Retained scope](tasks/baseline.md#t05)                                                                                                                                                     | T03                                                                                      |
| T06 | <a id="t06"></a>[Households and invitations](tasks/baseline.md#t06)                                                  | [Retained scope](tasks/baseline.md#t06)                                                                                                                                                     | T04, T05                                                                                 |
| T07 | <a id="t07"></a>[Account and membership administration](tasks/baseline.md#t07)                                       | [Retained scope](tasks/baseline.md#t07)                                                                                                                                                     | T06                                                                                      |
| T08 | <a id="t08"></a>[Producers and cutoff rules](tasks/baseline.md#t08)                                                  | [Retained scope](tasks/baseline.md#t08)                                                                                                                                                     | T06                                                                                      |
| T09 | <a id="t09"></a>[Producer product administration](tasks/baseline.md#t09)                                             | [Retained scope](tasks/baseline.md#t09)                                                                                                                                                     | T08                                                                                      |
| T10 | <a id="t10"></a>[Orderable catalogue queries](tasks/baseline.md#t10)                                                 | [Retained scope](tasks/baseline.md#t10)                                                                                                                                                     | T09                                                                                      |
| T11 | <a id="t11"></a>[Cycles, participation and pickup weekday](tasks/baseline.md#t11)                                    | [Retained scope](tasks/baseline.md#t11)                                                                                                                                                     | T10                                                                                      |
| T12 | <a id="t12"></a>[Producer cutoff administration](tasks/baseline.md#t12)                                              | [Retained scope](tasks/baseline.md#t12)                                                                                                                                                     | T11                                                                                      |
| T13 | <a id="t13"></a>[Household weekly ordering](tasks/baseline.md#t13)                                                   | [Retained scope](tasks/baseline.md#t13)                                                                                                                                                     | T12, T07                                                                                 |
| T14 | <a id="t14"></a>[Member cycle list](tasks/baseline.md#t14)                                                           | [Retained scope](tasks/baseline.md#t14)                                                                                                                                                     | T13                                                                                      |
| T15 | <a id="t15"></a>[Product availability and snapshots](tasks/baseline.md#t15)                                          | [Retained scope](tasks/baseline.md#t15)                                                                                                                                                     | T13                                                                                      |
| T16 | <a id="t16"></a>[Producer participation and activation](tasks/baseline.md#t16)                                       | [Retained scope](tasks/baseline.md#t16)                                                                                                                                                     | T15                                                                                      |
| T17 | <a id="t17"></a>[Cycle cancellation](tasks/baseline.md#t17)                                                          | [Retained scope](tasks/baseline.md#t17)                                                                                                                                                     | T13                                                                                      |
| T18 | <a id="t18"></a>[Send producer orders and scheduler](tasks/baseline.md#t18)                                          | [Retained scope](tasks/baseline.md#t18)                                                                                                                                                     | T16                                                                                      |
| T19 | <a id="t19"></a>[Producer cycle summary](tasks/baseline.md#t19)                                                      | [Retained scope](tasks/baseline.md#t19)                                                                                                                                                     | T18                                                                                      |
| T20 | <a id="t20"></a>[Manager cycle summary](tasks/baseline.md#t20)                                                       | [Retained scope](tasks/baseline.md#t20)                                                                                                                                                     | T13                                                                                      |
| T21 | <a id="t21"></a>[Proportional allocation](tasks/baseline.md#t21)                                                     | [Retained scope](tasks/baseline.md#t21)                                                                                                                                                     | T03                                                                                      |
| T22 | <a id="t22"></a>[Reception and allocation editing](tasks/baseline.md#t22)                                            | [Retained scope](tasks/baseline.md#t22)                                                                                                                                                     | T18, T20, T21                                                                            |
| T23 | <a id="t23"></a>[Reception validation and settlement](tasks/baseline.md#t23)                                         | [Retained scope](tasks/baseline.md#t23)                                                                                                                                                     | T22                                                                                      |
| T24 | <a id="t24"></a>[Stock and return-item administration](tasks/baseline.md#t24)                                        | [Retained scope](tasks/baseline.md#t24)                                                                                                                                                     | T04, T05                                                                                 |
| T25 | <a id="t25"></a>[Stock ledger](tasks/baseline.md#t25)                                                                | [Retained scope](tasks/baseline.md#t25)                                                                                                                                                     | T24                                                                                      |
| T26 | <a id="t26"></a>[Household wallet](tasks/baseline.md#t26)                                                            | [Retained scope](tasks/baseline.md#t26)                                                                                                                                                     | T06                                                                                      |
| T27 | <a id="t27"></a>[Cycle pickup view and actual quantities](tasks/baseline.md#t27)                                     | [Retained scope](tasks/baseline.md#t27)                                                                                                                                                     | T23, T26                                                                                 |
| T28 | <a id="t28"></a>[Pickup stock and returns](tasks/baseline.md#t28)                                                    | [Retained scope](tasks/baseline.md#t28)                                                                                                                                                     | T27, T25                                                                                 |
| T29 | <a id="t29"></a>[Combined pickup completion](tasks/baseline.md#t29)                                                  | [Retained scope](tasks/baseline.md#t29)                                                                                                                                                     | T28                                                                                      |
| T30 | <a id="t30"></a>[Pickup resolution and redistribution](tasks/baseline.md#t30)                                        | [Retained scope](tasks/baseline.md#t30)                                                                                                                                                     | T29                                                                                      |
| T31 | <a id="t31"></a>[Container counts](tasks/baseline.md#t31)                                                            | [Retained scope](tasks/baseline.md#t31)                                                                                                                                                     | T29, T20                                                                                 |
| T32 | <a id="t32"></a>[Household deactivation and to-settle list](tasks/baseline.md#t32)                                   | [Retained scope](tasks/baseline.md#t32)                                                                                                                                                     | T07, T30                                                                                 |
| T33 | <a id="t33"></a>[Current traceability audit and feature closure](tasks/weekly-and-collection.md#t33)                 | All 65 R# and S1–S92 (S90 retired); semantic test audit                                                                                                                                     | T02, T14, T17, T19, T31, T32, T34, T35, T36, T37, T38, T42, T44, T46, T48, T50, T51, T63 |
| T34 | <a id="t34"></a>[First manager installation command](tasks/baseline.md#t34)                                          | [Retained scope](tasks/baseline.md#t34)                                                                                                                                                     | T06                                                                                      |
| T35 | <a id="t35"></a>[Cancelled cycle summary](tasks/baseline.md#t35)                                                     | [Retained scope](tasks/baseline.md#t35)                                                                                                                                                     | T17, T20                                                                                 |
| T36 | <a id="t36"></a>[Remaining app-wide Catalan translations](tasks/baseline.md#t36)                                     | [Retained scope](tasks/baseline.md#t36)                                                                                                                                                     | T38                                                                                      |
| T37 | <a id="t37"></a>[Member ordering presentation](tasks/baseline.md#t37)                                                | [Retained scope](tasks/baseline.md#t37)                                                                                                                                                     | T14, T15, T17, T38                                                                       |
| T38 | <a id="t38"></a>[Shared Catalan translation foundation](tasks/baseline.md#t38)                                       | [Retained scope](tasks/baseline.md#t38)                                                                                                                                                     | T04                                                                                      |
| T39 | <a id="t39"></a>[Visual theme and ordering pilot](tasks/baseline.md#t39)                                             | [Retained scope](tasks/baseline.md#t39)                                                                                                                                                     | T37, T38                                                                                 |
| T40 | <a id="t40"></a>[Dashboard, wallet and producer theme](tasks/baseline.md#t40)                                        | [Retained scope](tasks/baseline.md#t40)                                                                                                                                                     | T39                                                                                      |
| T41 | <a id="t41"></a>[Manager screen theme](tasks/baseline.md#t41)                                                        | [Retained scope](tasks/baseline.md#t41)                                                                                                                                                     | T40                                                                                      |
| T42 | <a id="t42"></a>[Settings/authentication theme and cross-role review](tasks/baseline.md#t42)                         | [Retained scope](tasks/baseline.md#t42)                                                                                                                                                     | T41                                                                                      |
| T43 | <a id="t43"></a>[Layout tracer and ordering pilot](tasks/baseline.md#t43)                                            | [Retained scope](tasks/baseline.md#t43)                                                                                                                                                     | T42                                                                                      |
| T44 | <a id="t44"></a>[Dashboard, wallet and producer layout](tasks/baseline.md#t44)                                       | [Retained scope](tasks/baseline.md#t44)                                                                                                                                                     | T43                                                                                      |
| T45 | <a id="t45"></a>[Manager layout](tasks/baseline.md#t45)                                                              | [Retained scope](tasks/baseline.md#t45)                                                                                                                                                     | T43                                                                                      |
| T46 | <a id="t46"></a>[Settings/authentication layout](tasks/baseline.md#t46)                                              | [Retained scope](tasks/baseline.md#t46)                                                                                                                                                     | T43                                                                                      |
| T47 | <a id="t47"></a>[Reference style manager pilot](tasks/baseline.md#t47)                                               | [Retained scope](tasks/baseline.md#t47)                                                                                                                                                     | T45                                                                                      |
| T48 | <a id="t48"></a>[Reference style member and producer rollout](tasks/baseline.md#t48)                                 | [Retained scope](tasks/baseline.md#t48)                                                                                                                                                     | T47                                                                                      |
| T49 | <a id="t49"></a>[Reference style manager directories](tasks/baseline.md#t49)                                         | [Retained scope](tasks/baseline.md#t49)                                                                                                                                                     | T47                                                                                      |
| T50 | <a id="t50"></a>[Reference style manager details and overlays](tasks/baseline.md#t50)                                | [Retained scope](tasks/baseline.md#t50)                                                                                                                                                     | T49                                                                                      |
| T51 | <a id="t51"></a>[Reference style settings and authentication](tasks/baseline.md#t51)                                 | [Retained scope](tasks/baseline.md#t51)                                                                                                                                                     | T47                                                                                      |
| T52 | <a id="t52"></a>[Unified producer catalogue tracer](tasks/catalog.md#t52)                                            | R1a, R2, R3, R14, R25, R26, R27; S65, S66 (identity/weekly), S71, S76 (inventory), S77                                                                                                      | T10, T22, T24, T25                                                                       |
| T53 | <a id="t53"></a>[Retained history and protected catalogue edits](tasks/catalog.md#t53)                               | R1, R1b, R1c, R4b, R9, R19b, R25a; S67–S70, S72, S87 (order history)                                                                                                                        | T52, T13, T17, T28                                                                       |
| T54 | <a id="t54"></a>[Read-only stock discovery](tasks/catalog.md#t54)                                                    | R2, R25b, R25c, R28; S66 (visibility), S73, S74                                                                                                                                             | T52                                                                                      |
| T55 | <a id="t55"></a>[Independent stock purchase tracer](tasks/sales.md#t55)                                              | R19, R19a, R19b, R20, R21, R22, R23b, R28, R30, R31; S18, S21, S22, S45, S72 (basket history), S79, S81, S87 (basket history)                                                               | T53, T25, T26                                                                            |
| T56 | <a id="t56"></a>[Grant Finance access to existing basket operations](tasks/sales.md#t56)                             | R19a, R21, R31, R36a, R36b, R36c; S78/S79 (Finance), S84 (basket access)                                                                                                                    | T55, T07, T38                                                                            |
| T57 | <a id="t57"></a>[Validate return-only and mixed stock-return baskets](tasks/sales.md#t57)                            | R19b, R20a, R21, R22, R24a; S41, S53 (returns), S88 (real contents)                                                                                                                         | T56                                                                                      |
| T58 | <a id="t58"></a>[Append amount-only basket corrections](tasks/sales.md#t58)                                          | R23, R31, R32; amount-only correction invariants; S85 regression                                                                                                                            | T64, T65                                                                                 |
| T59 | <a id="t59"></a>[Weekly basket cutover and redistribution](tasks/weekly-and-collection.md#t59)                       | R4, R12a, R18, R19, R19a, R22–R24b; S9, S14–S16 (weekly), S19 (validation), S20, S27–S30, S39, S40, S42, S46, S47, S53, S54, S61, S63, S64, S78, S83 (closure), S88 (no contents), S89, S91 | T68, T66, T23, T30                                                                       |
| T60 | <a id="t60"></a>[Household container balance](tasks/weekly-and-collection.md#t60)                                    | R18a, R19, R24b; S60, S80, S92                                                                                                                                                              | T59, T31                                                                                 |
| T61 | <a id="t61"></a>[Collect an own-household validated basket](tasks/weekly-and-collection.md#t61)                      | R19a, R23a, R23b, R35; S26 (action), S82                                                                                                                                                    | T66                                                                                      |
| T62 | <a id="t62"></a>[Household obligations across lifecycle changes](tasks/weekly-and-collection.md#t62)                 | R35, R35a, R36b; S43, S58, S59                                                                                                                                                              | T59, T32                                                                                 |
| T63 | <a id="t63"></a>[Remove superseded pickup ownership and schema](tasks/weekly-and-collection.md#t63)                  | Plan: final context/schema/route contracts; S1–S92 regression boundary (S90 retired)                                                                                                        | T54, T67, T62                                                                            |
| T64 | <a id="t64"></a>[Record and correct confirmed global receipts](tasks/sales.md#t64)                                   | R21, R29–R32, R36c; S19 (receipt), S24, S84 (receipts), S85                                                                                                                                 | T56                                                                                      |
| T65 | <a id="t65"></a>[Read own-household basket contents and status](tasks/sales.md#t65)                                  | R19a, R21, R23a, R24a, R31, R35; own-household read and privacy invariants                                                                                                                  | T55, T38                                                                                 |
| T66 | <a id="t66"></a>[Correct collection marks without financial effects](tasks/sales.md#t66)                             | R23b, R24a; collection correction, history and replay invariants                                                                                                                            | T56, T65                                                                                 |
| T67 | <a id="t67"></a>[Keep pending collection visible across pages and cycle closure](tasks/weekly-and-collection.md#t67) | R23a, R24, R24b, R35; S26/S42/S54/S83 (indicators; one shared `pendingCollections` query)                                                                                                   | T60, T61                                                                                 |
| T68 | <a id="t68"></a>[Correct physical goods and effective basket quantities](tasks/sales.md#t68)                         | R23, R24a, R27, R28, R32; S86; full-reversal and signed-quantity invariants                                                                                                                 | T58, T57                                                                                 |
| T69 | <a id="t69"></a>[Align 001 documents with the delivered code](tasks/audit-2026-11-05.md#t69)                         | Documents only: plan; R4b, R12a, R14, R17, R18, R23b, R27, R31 wording; dashboard interim description                                                                                       | T68                                                                                      |
| T70 | <a id="t70"></a>[Refuse adding a producer to a settled cycle](tasks/audit-2026-11-05.md#t70)                         | R4a; S51 (settled boundary)                                                                                                                                                                 | T69                                                                                      |
| T71 | <a id="t71"></a>[Inventory count inside the stock lock](tasks/audit-2026-11-05.md#t71)                               | R27, R28; S23                                                                                                                                                                               | T69                                                                                      |
| T72 | <a id="t72"></a>[Remove the duplicate manager wallet and cycle pickups index](tasks/audit-2026-11-05.md#t72)         | R31; household wallet and cycle pickups routes                                                                                                                                              | T69                                                                                      |
| T73 | <a id="t73"></a>[Delete dead cycle code and fix stale docblocks](tasks/audit-2026-11-05.md#t73)                      | R4b; code hygiene, no behaviour change                                                                                                                                                      | T69                                                                                      |
| T74 | <a id="t74"></a>[Fill the audited test gaps](tasks/audit-2026-11-05.md#t74)                                          | S27, S61, S69, S87                                                                                                                                                                          | T69                                                                                      |
| T75 | <a id="t75"></a>[Manager send button for producer orders](tasks/audit-2026-11-05.md#t75)                             | R12a; S36                                                                                                                                                                                   | T69                                                                                      |
| T76 | <a id="t76"></a>[Stock movement history](tasks/audit-2026-11-05.md#t76)                                              | New requirement (spec first, gated); R28                                                                                                                                                    | T69                                                                                      |
| T77 | <a id="t77"></a>[The Locks port, with a PostgreSQL adapter](tasks/sqlite.md#t77) | ADR 0002 rule 2; no behaviour change | T69 |
| T78 | <a id="t78"></a>[Rewrite the schema as portable migrations](tasks/sqlite.md#t78) | ADR 0002 rules 3–4; S1–S92 regression boundary | T69 |
| T79 | <a id="t79"></a>[Switch to SQLite](tasks/sqlite.md#t79) | ADR 0002 rules 1, 2 and 6; R19–R24 invariants | T77, T80 |
| T80 | <a id="t80"></a>[Store timestamps in UTC](tasks/sqlite.md#t80) | ADR 0002 rule 5; cutoff scenarios | T78 |
| T81 | <a id="t81"></a>[Remove PostgreSQL leftovers](tasks/sqlite.md#t81) | ADR 0002 consequences; documents and tooling only | T79 |

## Linear mapping — 2026-11-05 audit tasks

All eight are children of the non-executable parent
[DRA-239](https://linear.app/draude/issue/DRA-239) (no Tnn, no ready label), like DRA-206 for T52–T63.

| Task        | Linear issue                                       | After |
| ----------- | -------------------------------------------------- | ----- |
| [T69](#t69) | [DRA-231](https://linear.app/draude/issue/DRA-231) | T68   |
| [T70](#t70) | [DRA-232](https://linear.app/draude/issue/DRA-232) | T69   |
| [T71](#t71) | [DRA-233](https://linear.app/draude/issue/DRA-233) | T69   |
| [T72](#t72) | [DRA-234](https://linear.app/draude/issue/DRA-234) | T69   |
| [T73](#t73) | [DRA-235](https://linear.app/draude/issue/DRA-235) | T69   |
| [T74](#t74) | [DRA-236](https://linear.app/draude/issue/DRA-236) | T69   |
| [T75](#t75) | [DRA-237](https://linear.app/draude/issue/DRA-237) | T69   |
| [T76](#t76) | [DRA-238](https://linear.app/draude/issue/DRA-238) | T69   |

## Linear mapping — 2026-11-06 SQLite migration tasks

All five are children of the non-executable parent
[DRA-241](https://linear.app/draude/issue/DRA-241) (no Tnn, no ready label). Decision: [ADR 0002](../../docs/adr/0002-sqlite-as-the-only-database.md).

| Task        | Linear issue                                       | After    |
| ----------- | -------------------------------------------------- | -------- |
| [T77](#t77) | [DRA-242](https://linear.app/draude/issue/DRA-242) | T69 |
| [T78](#t78) | [DRA-243](https://linear.app/draude/issue/DRA-243) | T69 |
| [T79](#t79) | [DRA-244](https://linear.app/draude/issue/DRA-244) | T77, T80 |
| [T80](#t80) | [DRA-245](https://linear.app/draude/issue/DRA-245) | T78 |
| [T81](#t81) | [DRA-246](https://linear.app/draude/issue/DRA-246) | T79 |

## Linear publication and current gates

The section below is the **2026-10-28 publication snapshot**, retained for provenance. It is not
the proposed graph above or a current status claim. See the 2026-10-31 readback and proposed exact
relation changes in [reslicing review](tasks/reslicing-review.md). No tracker changes have been made.

Published with the user’s explicit authorization on 2026-10-28 to workspace `draude`, team `DRA`,
project [Coop](https://linear.app/draude/project/coop-f80f57e1496d). Connector readback verified the
project/team identities, the existing `ready-for-agent` label and all 72 project issues, including
archived issues. T36 / DRA-205 is now Done; T33 / DRA-183 remains Todo. This is tracker status,
not a claim that the amendment is implemented or semantically covered.

Grouping parent: [Products, Sales baskets and Finance / DRA-206](https://linear.app/draude/issue/DRA-206/products-sales-baskets-and-finance).
It is Backlog, has no executable Tnn ID or ready label, and has only T52–T63 as children. All 12
children are Backlog, labelled `ready-for-agent`, and have no assignee or delegate. The parent
does not block any child. Existing issues keep their original parents.

| Task        | Linear issue                                                                                          | Native blocked by                  | Native related to               |
| ----------- | ----------------------------------------------------------------------------------------------------- | ---------------------------------- | ------------------------------- |
| [T52](#t52) | [DRA-207](https://linear.app/draude/issue/DRA-207/t52-unified-producer-catalogue-tracer)              | DRA-162, DRA-157, DRA-164, DRA-175 | —                               |
| [T53](#t53) | [DRA-208](https://linear.app/draude/issue/DRA-208/t53-retained-history-and-protected-catalogue-edits) | DRA-170, DRA-178, DRA-167, DRA-207 | —                               |
| [T54](#t54) | [DRA-209](https://linear.app/draude/issue/DRA-209/t54-read-only-stock-discovery)                      | DRA-207                            | —                               |
| [T55](#t55) | [DRA-210](https://linear.app/draude/issue/DRA-210/t55-independent-stock-purchase-tracer)              | DRA-208, DRA-161, DRA-162          | —                               |
| [T56](#t56) | [DRA-211](https://linear.app/draude/issue/DRA-211/t56-finance-role-and-global-manual-receipts)        | DRA-159, DRA-194, DRA-210          | —                               |
| [T57](#t57) | [DRA-212](https://linear.app/draude/issue/DRA-212/t57-basket-contents-returns-and-member-view)        | DRA-211                            | —                               |
| [T58](#t58) | [DRA-213](https://linear.app/draude/issue/DRA-213/t58-append-only-basket-corrections)                 | DRA-212                            | —                               |
| [T59](#t59) | [DRA-214](https://linear.app/draude/issue/DRA-214/t59-weekly-basket-cutover-and-redistribution)       | DRA-180, DRA-176, DRA-213          | —                               |
| [T60](#t60) | [DRA-215](https://linear.app/draude/issue/DRA-215/t60-household-container-balance)                    | DRA-214, DRA-181                   | —                               |
| [T61](#t61) | [DRA-216](https://linear.app/draude/issue/DRA-216/t61-collect-an-own-household-validated-basket)      | DRA-215                            | DRA-202, DRA-203 (context only) |
| [T62](#t62) | [DRA-217](https://linear.app/draude/issue/DRA-217/t62-household-obligations-across-lifecycle-changes) | DRA-214, DRA-182                   | —                               |
| [T63](#t63) | [DRA-218](https://linear.app/draude/issue/DRA-218/t63-remove-superseded-pickup-ownership-and-schema)  | DRA-209, DRA-216, DRA-217          | —                               |

The 28 native child prerequisite edges match the canonical `After` column. T61’s two related
links to feature-002 dashboard data tasks DRA-202/DRA-203 supply context; they are not blockers
and this publication implements no feature-002 work.

[T33 / DRA-183](https://linear.app/draude/issue/DRA-183/t33-traceability-check-and-close-feature-001) now describes the semantic audit of all 65 requirements
and S1–S92 (S90 retired). Its 11 existing native blockers remain: T38/DRA-194, T37/DRA-195, T36/DRA-205,
T42/DRA-193, T35/DRA-187, T32/DRA-182, T19/DRA-174, T31/DRA-181, T14/DRA-168,
T17/DRA-170 and T34/DRA-158. Added blockers: T02/DRA-151, T44/DRA-198, T46/DRA-200
and T63/DRA-218, giving 15 native blockers. Identity, title, project, Todo status, milestone,
assignment/delegation, labels and all other existing fields/relations were preserved.

**Visual-scope reconciliation:** with explicit follow-up authorization, T47–T51 were published
as standalone Coop tasks, all Backlog, `ready-for-agent`, unassigned and without a delegate.
They are outside DRA-206, whose only children remain T52–T63. Native prerequisites follow the
canonical registry; T48/T51 have contextual links to the already integrated T44/T46 layout work.

| Task        | Linear issue                                                                                        | Native blocked by | Native related to |
| ----------- | --------------------------------------------------------------------------------------------------- | ----------------- | ----------------- |
| [T47](#t47) | [DRA-219](https://linear.app/draude/issue/DRA-219/t47-reference-style-manager-pilot)                | DRA-199           | —                 |
| [T48](#t48) | [DRA-220](https://linear.app/draude/issue/DRA-220/t48-reference-style-member-and-producer-rollout)  | DRA-219           | DRA-198           |
| [T49](#t49) | [DRA-221](https://linear.app/draude/issue/DRA-221/t49-reference-style-manager-directories)          | DRA-219           | —                 |
| [T50](#t50) | [DRA-222](https://linear.app/draude/issue/DRA-222/t50-reference-style-manager-details-and-overlays) | DRA-221           | —                 |
| [T51](#t51) | [DRA-223](https://linear.app/draude/issue/DRA-223/t51-reference-style-settings-and-authentication)  | DRA-219           | DRA-200           |

T33 now also has native blockers T48/DRA-220, T50/DRA-222 and T51/DRA-223, giving all 18
canonical closure prerequisites. Its description no longer lists unresolved tracker mappings.
All prior fields, Todo status and relations were preserved. No closure relation remains pending.

Final readback confirmed one parent and exactly 12 mapped children, the requested project/status/
label/parent fields, both contextual links, no duplicate task issues, no dependency/parent cycles
or self-blocking, and preservation of existing parents and relations. No remote operation failed
or remains of unknown outcome. No task or feature was marked Done by this publication.

**Source publication:** the user authorized publishing this documentation branch and opening a PR.
Repository source links target `docs/product-producer-stock`; review that branch/PR until it is merged.
They do not imply that `main` already contains the amendment. No merge or application implementation
is authorized here. The historical baseline remains byte-identical. The dashboard compatibility
alignment is recorded in [feature 002](../002-actionable-dashboard/tasks.md).

## Reference-style delivery reconciliation — 2026-10-29

The [verified integration/evidence record](../../docs/visual-checks/2026-10-29-t47-t51-reconciliation.md)
reconciles T47–T51 against `origin/main` at `1a9a41a`. PRs #239/#240 already deliver the pilot
and combined rollout. T44/#243 and T46/#242 are integrated; their approvals and layout work
remain valid. PR #248 is merged, and #249 is now also merged with main CI passing. Earlier
publication notes above describe their publication-time state.

| Task          | Implementation and integration                                | Reconciled tracker state           | Remaining closure gate                                                                    |
| ------------- | ------------------------------------------------------------- | ---------------------------------- | ----------------------------------------------------------------------------------------- |
| T47 / DRA-219 | Pilot #239; breadcrumb regression #240; continuation approved | Done                               | None for the accepted pilot delivery                                                      |
| T48 / DRA-220 | Combined #240; T44/#243 and T36/#245 retained                 | Backlog; integrated implementation | Final producer-role shell/state coverage; preserve valid member/dashboard/wallet evidence |
| T49 / DRA-221 | Combined #240 on T45/#230; T36/#245 retained                  | Backlog; integrated implementation | Explicit manager-role coverage for eight directory/form routes and owning states          |
| T50 / DRA-222 | Combined #240; T36/#245 owning overlays retained              | Backlog; integrated implementation | Explicit manager-role coverage for seven workflow routes and affected overlays            |
| T51 / DRA-223 | Combined #240; T46/#242 and T36/#245 retained                 | Backlog; integrated implementation | Authenticated Welcome state; explicit retained settings-role evidence in final handoff    |

The 19/164/85/192/514-case retained reports passed historical artifact-integrity and declared
coverage checks. They are not relabelled as current-HEAD browser verification. T36's manager
and producer views omit the role query and default to member navigation; Welcome is guest-only.
See the linked record for exact routes, candidates/bases, tests, approvals and bounded follow-up.
These evidence gaps do not authorize repeating the redesign.

Descriptions record the reconciliation; only T47 changes status. Fields and native relations
outside that authorized tracking update remain intact. T33 stays Todo with all 18 blockers;
T48/T50/T51 remain closure gates. The historical baseline remains byte-identical. No T52 work,
feature closure, deployment or PR merge is performed by this reconciliation.
