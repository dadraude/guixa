# 001 — Weekly orders · Diagrams

Revised (2026-10-28). Derived from [plan.md](plan.md) and the [specification areas](spec.md#areas);
their written rules take precedence.

## 1. Contexts and interfaces

```mermaid
flowchart LR
    M[Membership<br/>Households · Accounts · Finance role]
    C[Catalog<br/>Producer · Product · stock flag]
    O[Ordering<br/>Cycles · Weekly orders<br/>Reception · Allocation]
    SA[Sales<br/>Basket · Validation<br/>Corrections · Collection]
    ST[Stock<br/>Return items · Stock ledger]
    W[Wallet<br/>Global household ledger]

    O -->|Weekly products and cutoffs| C
    O -->|Create and synchronize base drafts| SA
    O -->|Validation and effective quantity summaries| SA
    SA -->|Weekly source readiness and closure| O
    SA -->|Stock and return offers; sell and correct| ST
    SA -->|Charge or credit by basket| W
    SA -->|Household identity and active state| M
    ST -->|Product identity and lock| C
    C -->|Retained product references| O
    C -->|Retained basket references| SA
    C -->|Movement history and availability| ST
    C -->|Producer access| M
    M -->|Pending collection summaries| SA
    M -->|Household balance| W
```

Each arrow is a caller-owned port with an Infrastructure adapter calling the other context's
Application interface. Models/DTOs are translated there. All contexts remain in the same application;
validation and linked stock/Wallet corrections share one local transaction.

## 2. Model relations

```mermaid
erDiagram
    HOUSEHOLD |o--o{ ACCOUNT : "current members"
    ACCOUNT ||--o{ ACCOUNT_ROLE : "manager or finance or producer"
    PRODUCER |o--o| ACCOUNT : "producer account"
    PRODUCER ||--o{ PRODUCT : "immutable source"

    CYCLE ||--o{ PRODUCER_ORDER : "participating producers"
    PRODUCER ||--o{ PRODUCER_ORDER : "participates"
    CYCLE ||--o{ ORDER : "weekly demand"
    HOUSEHOLD ||--o{ ORDER : "one per cycle"
    ORDER ||--o{ ORDER_LINE : "weekly quantities and retained history"
    PRODUCT ||--o{ ORDER_LINE : "snapshot source"
    CYCLE ||--o{ RECEIVED_TOTAL : "weekly reception"
    PRODUCT ||--o{ RECEIVED_TOTAL : "weekly only"

    HOUSEHOLD ||--o{ BASKET : "actual purchases"
    ORDER |o--o| BASKET : "optional unique weekly source"
    CYCLE |o--o{ BASKET : "optional attribution, including closed cycles"
    BASKET ||--o{ BASKET_LINE : "current and retained contents"
    PRODUCT |o--o{ BASKET_LINE : "weekly or stock source"
    RETURN_ITEM |o--o{ BASKET_LINE : "return source"
    ORDER_LINE |o--o{ BASKET_LINE : "weekly source reference"
    BASKET ||--o{ BASKET_ADJUSTMENT : "immutable corrections"
    BASKET ||--o{ COLLECTION_EVENT : "collection and justified corrections"
    PRODUCT ||--o{ STOCK_MOVEMENT : "stock mode only"
    BASKET |o--o{ STOCK_MOVEMENT : "sale or correction source"
    HOUSEHOLD ||--o{ WALLET_MOVEMENT : "global balance"
    BASKET |o--o{ WALLET_MOVEMENT : "charge or correction source"

    PRODUCT {
        uuid producerId
        bool stock
        string unit
        money price
        bool variable
        qty containersPerUnit
        bool active
    }
    ORDER_LINE {
        qty ordered
        qty allocated
        bool allocatedByHand
        ts removedAt
        ts createdAt
    }
    BASKET {
        uuid householdId
        uuid sourceOrderId
        enum state "draft or validated or cancelled or nothingToCollect"
        ts validatedAt
        money validatedTotal
    }
    BASKET_LINE {
        enum kind "weekly or stock or return"
        qty actual
        ts removedAt
    }
    COLLECTION_EVENT {
        enum kind "collected or correctedToPending"
        uuid recordedBy
        ts recordedAt
        string reason
    }
```

A source order is required only for the automatic weekly base. A stock-only basket can have neither
order nor cycle.
Member is derived from the household link, not stored in ACCOUNT_ROLE. Removed/cancelled source
records remain for first-use evidence; no separate used-product marker is added.

## 3. Weekly and independent sales

```mermaid
flowchart TB
    O[Members edit weekly demand before cutoff]
    P[Manager sends producer totals]
    R[Manager receives, allocates and distributes]
    D[Sales creates one base draft per household order]
    S[Finance or Manager creates extra stock basket<br/>any time, no cycle]
    E[Finance or Manager checks actual quantities<br/>and adds stock or return lines]
    V[Validate basket<br/>confirm contents and check stock]
    A[One local transaction<br/>save validation, stock sale and Wallet charge or credit]
    F[Insufficient stock or another failure<br/>remain draft with no partial financial effects]
    CP[Validated, pending physical collection]
    CC[Finance, Manager or own household member<br/>marks collected; no financial effect]
    CL[Close weekly cycle when distribution finished<br/>and every base basket validated or empty]
    WARN[Show pending collection warning<br/>even after pickup day or cycle closure]
    PAY[Finance or Manager records confirmed receipt<br/>independent credit to household Wallet]
    WB[Household global Wallet balance]
    COR[Staff append justified corrections<br/>original records remain unchanged]

    O --> P --> R --> D --> E
    S --> E
    E --> V
    V -->|success| A --> CP --> CC
    V -->|failure| F --> E
    A -->|weekly source only| CL
    CP --> WARN
    CL -->|while collection remains pending| WARN
    A --> WB
    PAY --> WB
    A -. later correction .-> COR
    COR --> WB
```

Receipts independently affect the household balance; they are not allocated to a basket and are
not a prerequisite to validation/collection. Empty-source baskets need no collection;
zero-price goods and net-zero real-content baskets still follow the confirmed validation rules.
