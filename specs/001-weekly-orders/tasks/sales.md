# 001 — Sales task details

Part of the [canonical registry](../tasks.md); dependencies live there. Status: T55 remains accepted; the remaining scopes below are **proposed (2026-10-31)** for review.
Authoritative behavior: [spec](../spec.md) and its areas; technical contracts: [plan](../plan.md).
Every slice includes its affected adapters, tests, translations, UI evidence and document reconciliation.
Paths in the ownership notes are edit surfaces, not new cross-context domain dependencies.

## T55

**Independent stock purchase tracer.** A Manager selects an active household, creates a cycleless
basket, adds a stock line, validates it and records physical collection as a separate action. This
thin path runs through Sales, Catalog, Stock, Wallet, storage, HTTP and reviewable staff UI.

Source: [Sales](../plan.md#sales), [Wallet](../plan.md#wallet),
[transaction seam](../plan.md#validatebasket-the-transactional-seam), R19–R23b/R28/R30/R31.

Acceptance:

- Basket exists without an Order/Cycle. Draft line addition checks mode/quantity/combined availability
  under the Catalog product lock, snapshots the product and reserves/charges nothing. Remove/cancel
  retains history; Catalog's usage query includes removed lines/cancelled Sales drafts immediately.
- Validation atomically records immutable contents/total/actor/time, stock sale and one Wallet
  charge/credit by basket source. Recheck stock under ordered locks; negative global balance is allowed.
  Use line-level half-up rounding. Zero totals have no movement; repeated/concurrent validation has
  one financial/stock effect. A confirmed receipt remains an independent operation.
- Manager collection records actor/time on a validated basket, repeat is a no-op preserving the first
  record, with no ledger writes. Original fields cannot be edited after validation. Existing inactive
  lines retain their snapshot and may validate; new/increased inactive lines are forbidden.
- Wallet statements identify the basket and optional cycle; source indexes admit multiple purchases
  for the same household. Add Sales architecture/repository contracts. Keep the old weekly writer
  isolated until T59; never expose old and new writers for the same weekly purchase.
- Register or extend Sales in `architecture/modules.php` with its delivered basket table ownership,
  exact integration exports/DTOs, permitted callers and dependencies, including the Stock/Wallet
  permissions needed by this tracer. Preserve Ordering permissions still required by the isolated
  weekly flow until T59; Stock keeps inventory ownership and Wallet keeps ledger ownership. Update
  the module-contract documentation and relevant rejection tests, then run `tests/Architecture`.
  This adds no dependency on T54.

Verify S18/S21/S22/S45/S79/S81, Sales history in S87 and S72's unit/variable guard after removed/cancelled basket use. Repository tests cover active-line uniqueness,
archived references, immutable snapshot reload and sale/charge source uniqueness. PostgreSQL integration
proves rollback after an injected stock/wallet failure, two baskets competing for final units, replay,
and multiple-product additions in opposite order. HTTP denies members/producers Finance actions.

Ownership/reuse: Sales context and ports/adapters, basket/line/collection persistence, Stock sales,
Wallet basket sources, Finance basket create/list/show routes initially Manager-gated, statement links.
Refactor existing pickup stock/content/total patterns into shared basket presentation; T56 adds Finance
access, not another financial writer. Show draft→validated→collected and insufficient-stock states.

## T56

**Grant Finance access to existing basket operations.**

A Manager grants/revokes Finance; a Finance-only account can sign in and operate the existing
independent basket flow and read the required household statements. Extend current authorization,
not a second basket implementation.

Source: [Membership](../plan.md#membership), [Shared](../plan.md#shared), R19a/R36a/R36c and S84.

Done when:

- Given an active account with no household/producer, when Manager grants Finance, then the next
  request can create/add/validate/collect a basket and read its household statement. Actor checks,
  middleware, role areas and navigation agree; the original actor is propagated through adapters.
- Given Finance-only, combined roles, revoked Finance and member-only accounts, when direct requests
  target baskets, statements or administration, then only the current capabilities apply. Manager
  retains access; member privacy and last-Manager safeguards remain. Finance gains no catalogue,
  cycle, reception, account or arbitrary inventory administration.
- Show translated Finance navigation, denied access and grant/revoke states using existing account
  and basket components. An authorization test must exercise a real request, not only a role marker.

First checkpoint: a failing Finance-only HTTP request becomes a successful real basket detail page,
with the same actor accepted by the public Application use case; revocation then denies it.
Complete mutation permissions before the final review. Keep all work in one implementation owner.

Existing paths: `src/Shared/Application/Actor.php`, `src/Shared/Domain/Role.php`,
`src/Shared/Infrastructure/DatabaseActorResolver.php`, `src/Membership/Domain/Account.php`,
`app/Http/RoleAreas.php`, `app/Http/Middleware/EnsureRole.php`,
`resources/js/pages/Manage/Accounts/Index.vue`; T55's Sales/Finance paths are pending integration.
Planned: Finance role commands and route/controller wiring specified by the Membership plan.
Focused consumers: Membership role tests, role-access HTTP tests, Sales HTTP/Application tests,
Wallet statement permissions, navigation/theme/layout and translation coverage.

Dependency reasons: T55 supplies the basket pathway; T07 supplies account/role safeguards;
T38 supplies translated shared controls. S84's receipt capability is completed by T64, not claimed here.
Existing Manager receipt/correction actions stay working and Manager-only until T64 exposes the
complete Finance receipt path. Finance sees no nonworking receipt controls in this intermediate slice.

Size/rollout: roughly 400–800 authored changed lines including tests, low-confidence estimate.
The role-to-HTTP propagation crosses several consumers and must ship together; inspect that first
checkpoint before widening permission cases. No flag, schema-only delivery or new product behavior.
Out of scope: receipt changes (T64), returns (T57), member basket views (T65), collection policy changes.

## T64

**Record and correct confirmed global receipts.**

Finance/Manager records confirmed cash/bank money independently of baskets, and corrects an erroneous
receipt without editing it. Extend existing Manager RecordTopUp/RecordCorrection and statement forms.

Source: [Wallet](../plan.md#wallet), R29–R32, S19(receipt)/S24/S85.

Done when:

- Given two basket charges totalling €30, when Finance confirms and records €50 received, then the
  global balance is €20 and the receipt appears once for that successful recording, with actor/time.
  Neither basket gains a paid/part-paid state. Positive receipts work before/after validation; a failed
  basket validation cannot undo independently received money. No basket/cycle allocation is required.
- Given a €50 receipt, when staff appends a −€50 correction with reason duplicated, then both immutable
  movements remain visible and the corrected balance is unchanged from before the receipt. Store and
  verify the original movement source; reject a foreign-household/missing source, zero correction or
  missing reason. Replaying the same correction identity produces no duplicate correction.
- Finance-only and Manager use the real statement forms; member-only accounts cannot write, and
  revocation denies the next request. Show confirmation, validation errors and linked correction text
  in Catalan. Preserve existing legitimate Manager receipt behavior and own-household read privacy.

First checkpoint: drive S24 through the existing receipt form, persist/reload the two linked movements
and assert the exact statement amounts/source. Then check S85 and basket-failure independence.

Existing paths: `src/Wallet/Application/RecordTopUp.php`, `RecordCorrection.php`,
`src/Wallet/Domain/Movement.php`, `MovementRepository.php`,
`app/Http/Controllers/Manage/HouseholdWalletController.php`, `resources/js/pages/Wallet/Statement.vue`.
Planned: Finance HTTP receipt path and corrected-movement/correction-id persistence per Wallet plan.
Focused checks: Wallet public commands and both repository adapters, source uniqueness, HTTP roles,
statement/form tests and translation coverage; preserve Ordering's existing Wallet consumers.
Dependency reason: T56 supplies Finance identity/access and transitively T55/T26's existing ledger and forms.

Size/rollout: roughly 350–700 authored lines, low confidence. Keep receipt creation and its safe,
source-linked repair together because this is already a single receipt lifecycle on existing forms.
No gateway, cash payout, automatic reconciliation, new receipt retry contract or payment allocation.
Basket adjustment history is T58/T68; collection correction is T66.

## T57

**Validate return-only and mixed stock-return baskets.**

Finance/Manager adds and removes return-item lines on an independent draft and validates a credit,
mixed or real-content net-zero basket. Reuse T55's draft, sale, charge and collection transaction.

Source: [Basket model](../plan.md#basket-aggregate-and-history), R20a/R22/R24a, S41/S88.

Done when:

- Given a return item, when staff adds a positive whole quantity, then its snapshot and credit survive
  a PostgreSQL reload; fractional/nonpositive quantities are rejected. Removing/re-adding preserves
  history and creates the appropriate new snapshot. Catalogue editing remains Manager-only.
- Given return-only, mixed, net-zero and empty drafts, when validated/cancelled, then a return-only
  basket credits Wallet, real-content net-zero goods can be collected with no zero movement, and
  an empty stock draft cannot validate but can cancel without ledger effects. Stock sales and Wallet
  writes remain atomic and replay once; rounding is per line. Weekly no-contents/revival is T59.
- Given deactivated stock or a deactivated producer, when the existing draft path is reused, then
  T55's snapshot, addition, availability and retained-history behavior still holds. This is regression
  coverage for S16(stock)/S17/S55/S75/S76(sale), not a second implementation of those rules.
- The actual Finance detail UI renders return editor, credit, zero, empty, error and long-name states.
  Assert exact amounts and actions before geometry; stock/return totals must agree with HTTP props.

First checkpoint: the S88 return-only public use case fails, then records one credit through both
repository adapters, reloads from PostgreSQL and appears as the same amount on the actual detail page.
Next prove S41 exactly (€10 goods minus four €0.50 returns = €8), then net-zero and rollback. No separate schema/backend/UI tickets.

Existing paths: `src/Stock/Domain/ReturnItem.php`, `src/Ordering/Application/AddReturnToPickup.php`,
`resources/js/components/pickup/ReturnLines.vue`; pending T55 Sales validation/repository and Finance
detail. Planned: Sales return commands/line kind and return-item read adapter from the accepted plan.
Focused consumers: shared Basket/Wallet contracts in both adapters, Sales persistence/HTTP/concurrency,
old pickup return consumers and shared ReturnLines tests; translation and architecture checks.
Dependency reason: T56 supplies the authorized Finance basket path (and transitively T55/T24).

Size/rollout: roughly 500–900 authored lines, low confidence; one return path plus inherited atomic
validation is the smallest useful unit. Preserve original weekly pickup behavior until T59. No flag.
Member read access moves to T65; financial corrections to T58/T68; no new stock-reservation flow.

## T65

**Read own-household basket contents and status.**

A current member can list and read their household's existing independent baskets, contents, totals,
balance and statement link, without access to Finance mutations.

Source: [Sales interface](../plan.md#sales), R19a/R23a/R24a and the original T57 member scope.

Done when:

- Given baskets from two households, when a member lists/opens one by URL, then only their current
  household's baskets are exposed. A forged identifier, moved/unlinked member or deactivated account
  cannot retain access through an old link. Server-side scope applies to both query and HTTP layers.
- Draft, empty, cancelled, validated and collected states show actual contents, total and balance;
  drafts are preparation, not ready for collection. Validation originals remain immutable and visible.
  Statement references identify the basket. Member has no edit/validate/receipt/collection control yet.
- Reuse the basket presentation in member layout, with translated empty/error/long-content states,
  exact values and meaningful action absence assertions before responsive measurements.

First checkpoint: a forged cross-household read fails while an own-basket page renders persisted
data; prove absence of mutations with HTTP requests, not just hidden buttons.

Pending existing paths: T55 Sales ListBaskets/GetBasket and HTTP DTOs/Finance presentation.
Existing baseline: `src/Shared/Application/Actor.php`, `app/Http/Controllers/WalletController.php`,
`resources/js/pages/Wallet/Statement.vue`. Planned: member basket query/HTTP/page wiring per Sales plan.
Focused checks: Sales public read and HTTP privacy, Member/Manager statement/navigation, translations.
Dependency reasons: T55 supplies actual baskets; T38 supplies translated shared controls.

Size/rollout: roughly 300–600 authored lines, low confidence. Safe read-only delivery needs no flag.
Returns (T57), adjustments (T58/T68), collection corrections (T66) and weekly data (T59) must each
extend this read path with their own assertions when introduced; this task does not pretend future
data exists. Self-collection is T61; aggregate pending indicators are T67.

## T58

**Append amount-only basket corrections.**

Finance/Manager corrects the money amount of a validated independent purchase with a reason,
without claiming that goods were physically returned. Original sale, contents, validation and
collection records remain immutable; members can inspect the resulting linked adjustment.

Source: [Corrections](../plan.md#collection-and-corrections), R23/R32. This is the amount-only part
of the original T58; S86 physical-return completion belongs to T68.

Done when:

- Given a validated basket, when staff submits a nonzero signed amount, reason and correction identity,
  then a linked adjustment and Wallet correction persist atomically, with actor/time/source and
  unchanged original values. No stock or quantity changes are implied by an amount correction.
- Given a missing reason, invalid source, unauthorized actor or repeated correction identity, then
  no invalid/duplicate effect survives. Different legitimate corrections remain distinct and ordered.
- Given the corrected basket, when staff or its current household member reads it, then original
  and effective amounts/history are distinct. Other households cannot read or correct it.

First checkpoint: reproduce a duplicate amount correction through the public command; prove one
effect in both adapters and after PostgreSQL reload, then submit/view the same correction via HTTP/UI.
Include an injected Wallet failure proving the basket adjustment also rolls back.

Existing/pending paths: T55 Sales Basket/DTO/repository/Finance detail, T64 Wallet correction source
contract, T65 member read path, `resources/js/components/pickup/Adjustments.vue` as reuse candidate.
Planned: `CorrectBasket` amount-only branch and append-only adjustment persistence from the plan.
Focused checks: shared adjustment/Wallet contracts, transaction rollback/replay, staff/member HTTP,
original/effective amount assertions, translation and module boundaries.
Dependency reasons: T64 supplies Finance authorization through T56 and the source-linked Wallet correction
contract; T65 provides member history presentation. No dependency on return-item entry is needed.

Size/rollout: roughly 350–650 authored lines, low confidence; larger scope would trigger a checkpoint
reassessment, not omission of atomicity or permissions. Expose only amount correction controls.
Goods quantities/stock/full reversal are T68; collection-mark correction is T66; weekly source is T59.

## T68

**Correct physical goods and effective basket quantities.**

Finance/Manager records a justified goods correction, explicitly distinguishing a physical stock
return from an amount-only correction. Extend T58's append-only history; preserve validated originals.

Source: [Corrections](../plan.md#collection-and-corrections), R23/R27/R32, S86.

Done when:

- Given a validated stock basket, when goods actually return with a corresponding amount correction,
  then linked signed quantity, stock and Wallet adjustments persist atomically with source/reason and
  actor/time. Original contents, validation total/time, sale and charge are unchanged.
- Partial, repeated and full corrections use effective quantities/amounts, reject invalid sources,
  missing reason, stock-negative effects and duplicate correction identity. Inject failure after a
  stock or Wallet write and prove all effects roll back. Both adapters enforce the new source contract.
- A full justified reversal leaves no effective goods pending collection. Staff and member detail
  show original/effective quantities and amounts correctly; amount-only corrections never restore stock.
  Finance gains no arbitrary inventory adjustment access. Preserve receipt-correction regressions.
- Provide the narrow signed quantity/amount adjustment seam T59 will reuse, proven through these
  actual independent purchases. Do not register a future weekly caller or implement Ordering here.

First checkpoint: S86 fails then passes through the public command, PostgreSQL reload and actual
correction/detail UI. Prove rollback and replay before widening to full reversal and negative cases.

Pending paths: T58 CorrectBasket/adjustment repository and member history; T57 line kinds; existing
Stock/Wallet ledgers. Planned: source-linked CorrectBasketStock adapter and effective quantity read
models from the plan. Focused checks: both shared adapters, public correction/rollback/concurrency,
staff/member HTTP, exact original/effective fixture values, module boundaries and translations.
Dependency reasons: T58 supplies adjustment history/Wallet source handling; T57 supplies all independent
line kinds whose effective contents must remain correct.

Size/rollout: roughly 500–900 authored lines, low confidence. Stock/money atomicity cannot be shipped
as separate layer tasks. No flag; financial-only behavior from T58 remains usable until this extension.
Weekly redistribution/closure is T59; collection event correction T66; pending summaries T67.

## T66

**Correct collection marks without financial effects.**

Finance/Manager corrects a mistaken collection mark with a reason while preserving the original
event and exposing the effective collection state to staff and the household member.

Source: [Collection and corrections](../plan.md#collection-and-corrections), R23b and original T58 scope.

Done when:

- Given a collected basket, when staff corrects the mistaken mark, then original actor/time remain
  in append-only history with the correction reason/actor/time; effective collection state changes
  without a Stock or Wallet movement and without changing validation.
- Given correction/collection replay or concurrent requests, then retained original events and
  effective state are consistent with the accepted collection rules, with no duplicate financial effects.
  A member cannot correct, and an unrelated household cannot read the history.
- Staff/member detail shows original versus effective collection, including error and pending states.
  Generic pending readers added later must consume this effective state, not merely an original timestamp.

First checkpoint: a real staff correction request reloads retained original plus correction from
PostgreSQL and renders the new effective state; assert unchanged ledger row counts and amounts.
Then exercise replay/concurrency through public interfaces and both repository implementations.

Pending paths: T55 MarkBasketCollected, Basket/repository/collection events, T65 member view.
Planned: CorrectBasketCollection and correction event/read model specified in the plan.
Dependency reasons: T56 supplies Finance operations; T65 supplies household-scoped history views.
Focused checks: collection event repository contracts, Sales Application/persistence/HTTP, role and
rendered-state assertions. No dependency on amount correction or returns is required.

Size/rollout: roughly 300–600 authored lines, low confidence. One staff correction path, no flag.
Member collection is T61; aggregate indicators T67; stock/money/contents corrections T58/T68.
