# Project overview

A visual map of Guixa for reading use cases and spotting steps to improve. It summarises the current
implementation of feature [001 — Weekly orders](../specs/001-weekly-orders/spec.md); where this page
and a spec or [module contract](architecture/modules.md) disagree, they win.

Sources: `routes/web.php`, `src/*/Domain` enums and aggregates, `architecture/modules.php`, the 001 spec
areas and the [UX discovery](ux-discovery/2026-10-24-guixa-discovery.md). The
[001 diagrams](../specs/001-weekly-orders/diagrams.md) go deeper on the data model.

## 1. Who does what

Four roles. _Member_ is never stored: an account is a member exactly when it is linked to a household.
A manager keeps every Finance power. Finance gets baskets and receipts only.

```mermaid
flowchart LR
    Member([Member])
    Producer([Producer])
    Finance([Finance])
    Manager([Manager])

    subgraph Ordering
        UC1[Order weekly products<br/>before the cutoff]
        UC2[Open, cancel and configure cycles]
        UC3[Send producer orders]
        UC4[Receive, allocate and distribute]
    end
    subgraph Sales
        UC5[Prepare and validate baskets]
        UC6[Mark a basket collected]
        UC7[Correct a validated basket]
    end
    subgraph Wallet
        UC8[Record top-ups and corrections]
        UC9[Consult balance and movements]
    end
    subgraph Catalog
        UC10[Manage own weekly products<br/>cutoff and availability]
        UC11[Manage producers and stock products]
    end
    subgraph Stock
        UC12[Record stock entries and adjustments]
        UC13[Manage return items]
    end
    subgraph Membership
        UC14[Invite and manage accounts<br/>households and roles]
    end

    Member --> UC1
    Member --> UC6
    Member --> UC9
    Producer --> UC10
    Finance --> UC5
    Finance --> UC6
    Finance --> UC7
    Finance --> UC8
    Manager --> UC2
    Manager --> UC3
    Manager --> UC4
    Manager --> UC11
    Manager --> UC12
    Manager --> UC13
    Manager --> UC14
    Manager -.->|inherits| Finance
```

## 2. One cycle, end to end

The weekly rhythm. The cooperative has about 10 managers sharing the work, so every step may be done by
a different person. The left side is Ordering; from the base basket on, Sales owns the money.

```mermaid
sequenceDiagram
    autonumber
    actor Man as Manager
    actor Mem as Member
    actor Pro as Producer
    actor Fin as Finance
    participant Ord as Ordering
    participant Sal as Sales
    participant Sto as Stock
    participant Wal as Wallet

    Man->>Ord: Open cycle, add participating producers
    Pro->>Ord: Mark product availability for the cycle
    Mem->>Ord: Edit weekly order (saved on each change)
    Note over Ord: Producer cutoff passes<br/>or manager closes it now
    Man->>Ord: Send producer order
    Pro-->>Man: Delivers goods (outside the app)
    Man->>Ord: Enter received totals, adjust allocation
    Man->>Ord: Validate reception, then distribute
    Ord->>Sal: Cycle settled: one base basket draft per household order
    Fin->>Sal: Check actual quantities, add stock and returns
    Fin->>Sal: Validate basket
    Sal->>Sto: Sell stock lines
    Sal->>Wal: Charge household once per basket
    Mem->>Sal: Collect at pickup (or Finance marks it)
    Note over Sal,Ord: Every weekly basket validated or empty
    Sal->>Ord: Close cycle
    Fin->>Wal: Record confirmed payment as a top-up
```

## 3. States

### Producer order inside a cycle

Each participating producer moves on its own. A cycle is _settled_ when every one is distributed.

```mermaid
stateDiagram-v2
    [*] --> Open: producer participates
    Open --> Closed: cutoff passes or manager closes now
    Closed --> Sent: manager sends order
    Closed --> Sent: scheduler sends an empty order
    Sent --> Validated: reception validated
    Validated --> Distributed: manager distributes
    Distributed --> Validated: manager reopens until the cycle closes
    Distributed --> [*]
```

### Cycle

```mermaid
stateDiagram-v2
    [*] --> Ordering
    Ordering --> Settled: every producer distributed
    Settled --> Closed: every weekly basket validated or empty
    Ordering --> Cancelled: manager cancels
    Closed --> [*]
    Cancelled --> [*]
```

Closed is reached after validation, not collection. A closed cycle can still have baskets waiting to be
picked up, and those keep showing as pending.

### Basket

Weekly baskets start as drafts built from the order. Stock baskets are created by hand, at any time,
with no cycle.

```mermaid
stateDiagram-v2
    [*] --> Draft: weekly source order or manual stock basket
    [*] --> NothingToCollect: every allocation is zero
    Draft --> Validated: validate (stock sold, wallet charged, one transaction)
    Draft --> Cancelled: cancel draft
    Draft --> Draft: validation fails (e.g. not enough stock)
    Validated --> Collected: mark collected
    Collected --> Validated: justified collection correction
    NothingToCollect --> [*]
    Validated --> [*]: justified correction appends records
    Cancelled --> [*]
```

## 4. Modules and who calls whom

A modular monolith. Arrows are the integration exports in
[`modules.md`](architecture/modules.md); each is a port in the caller and an adapter in its
Infrastructure.

```mermaid
flowchart LR
    Cat[Catalog]
    Mem[Membership]
    Ord[Ordering]
    Sal[Sales]
    Sto[Stock]
    Wal[Wallet]
    Sha[(Shared kernel<br/>Money · Quantity · Clock · Actor)]

    Cat <--> Ord
    Cat <--> Mem
    Cat --> Sal
    Cat --> Sto
    Ord <--> Sal
    Mem <--> Sal
    Mem --> Ord
    Mem --> Wal
    Sal --> Sto
    Sal --> Wal
    Sto --> Cat
    Wal --> Sal
```

Inside every module the dependency points inwards:

```mermaid
flowchart LR
    Http[app/Http<br/>thin controllers] --> App[Application<br/>use cases and DTOs]
    App --> Dom[Domain<br/>pure PHP]
    Inf[Infrastructure<br/>Eloquent, mail, clock] --> Dom
    Inf -. calls other module's .-> App
    Vue[Vue pages via Inertia] --> Http
```

## 5. Money and stock

Sales is the only writer of weekly money. Wallet and Stock are append-only ledgers, and a correction
adds a record instead of editing one.

```mermaid
flowchart TB
    V[Validate basket]
    V -->|sell lines, ascending product order| SM[(stock_movements)]
    V -->|charge once by basket id| WM[(wallet_movements)]
    TU[Top-up recorded by Finance<br/>or Manager] --> WM
    COR[Justified correction] --> WM
    COR --> SM
    ENT[Stock entry or adjustment] --> SM
    WM --> BAL[Household balance<br/>can be negative]
    SM --> AV[Available quantity per stock product]
```

## 6. Candidates to improve

Each point comes from something visible in the code or the specs. They are prompts for a decision, not
agreed work. Check the spec before acting on one.

| #   | Observation                                                                                                                                                                      | Evidence                                                                                     | Possible step                                                                                           |
| --- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| 1   | No "what do I do now" view. The dashboard spec was retired, yet managers work in short, infrequent sessions and forget rare tasks.                                               | [Spec 002](../specs/002-actionable-dashboard/spec.md) retired; UX discovery, Manager persona | Reopen the dashboard decision once 001 is complete, starting from the attention list                    |
| 2   | A cycle has about six manual manager steps per producer (send, receive, validate, distribute, plus reopen when wrong). With about 7 producers a week this is the heaviest load.  | Producer order diagram; routes under `manage/cycles/{cycle}/receptions/{producer}`           | Measure time per step before automating. Bulk "send all ready" is the cheapest candidate                |
| 3   | No notifications except sending the order to a producer. Members and producers learn about cutoffs and pickups outside the app.                                                  | Roadmap: Notifications; spec R12a                                                            | Cutoff and pickup reminders, the first notification feature                                             |
| 4   | A producer cannot confirm or adjust a sent order. Gaps surface only at reception.                                                                                                | Roadmap: Producer confirmation                                                               | Add `Confirmed` between `Sent` and `Validated`                                                          |
| 5   | Payment is a separate manual top-up, not tied to a basket, and validation does not wait for it. Reconciling who paid for what is manual.                                         | Money section; spec stock-wallet                                                             | Per-household "owes" list from the negative balances                                                    |
| 6   | Wallet top-ups and corrections lived twice, under `manage/households/{household}/wallet` and `finance/households/{household}/wallet`; T72 (DRA-234) kept only the Finance route. | `routes/web.php`                                                                             | Done in T72: one set serves both roles                                                                  |
| 7   | Catalog↔Ordering and Ordering↔Sales depend on each other. This is intentional and tested, but it is the likeliest place for a cycle in the constructors to appear.               | [Module contracts](architecture/modules.md), reciprocal dependencies                         | Keep reads narrow, as the contract says. Watch new exports in review                                    |
| 8   | The `Manager` to `Admin` rename is queued before Commissions and touches specs, code and screens.                                                                                | Roadmap: Admin rename, Commissions                                                           | Do it as one mechanical change before any new permission work                                           |
| 9   | Producer invoicing, the monthly amount payable and container returns to producers are all missing, so the cooperative tracks what it owes producers elsewhere.                   | Roadmap                                                                                      | Start with the monthly report. Its open question is the basis, validated reception or distributed goods |

## 7. Where to read next

| To know                         | Read                                                                               |
| ------------------------------- | ---------------------------------------------------------------------------------- |
| Rules and acceptance scenarios  | [`specs/001-weekly-orders`](../specs/001-weekly-orders/spec.md) and its area files |
| Architecture rules A1–A17       | [Constitution](../specs/constitution.md)                                           |
| Module exports and table owners | [Module contracts](architecture/modules.md)                                        |
| Data model                      | [001 diagrams](../specs/001-weekly-orders/diagrams.md)                             |
| What is next                    | [Roadmap](../specs/README.md#roadmap)                                              |
