# 001 — Weekly orders · Diagrams

Derived from [plan.md](plan.md) and the [areas](spec.md#areas); when they disagree, those documents win.

## 1. Domains (bounded contexts and ports)

```mermaid
flowchart LR
    subgraph Shared
        S[Money · Quantity · Clock · LocalTime<br/>Actor · Role · TransactionRunner]
    end
    subgraph Membership
        M[Household · Account · Invitation]
    end
    subgraph Catalog
        C[Producer · CutoffRule · Product]
    end
    subgraph Ordering
        O[CooperativeSettings · Cycle · ProducerOrder<br/>Order · OrderLine · StockLine · ReturnLine<br/>ProductAvailability · ReceivedTotal<br/>ProportionalAllocator · Reception · ContainerCount]
    end
    subgraph Stock
        ST[StockProduct · ReturnItem · StockMovement<br/>SignedQuantity · StockLedger]
    end
    subgraph Wallet
        W[Movement]
    end

    O -- ProductCatalog / ProducerCutoffRules --> C
    O -- StockCatalog / ReturnItemCatalog / StockSeller --> ST
    O -- WalletLedger --> W
    O -- ProducerNotifier / Households --> M
    C -- ProducerCycles --> O
    C -- ProducerAccess --> M
    M -- ProducerDirectory --> C
    M -- HouseholdBalances --> W
    M -- HouseholdPickups --> O

    Membership & Catalog & Ordering & Stock & Wallet -.uses.-> Shared
```

## 2. Model relations

```mermaid
erDiagram
    HOUSEHOLD ||--o{ ACCOUNT : "has members"
    PRODUCER ||--o| ACCOUNT : "has one producer account"
    ACCOUNT ||--|{ ACCOUNT_ROLE : "member | manager | producer"
    ACCOUNT ||--o| INVITATION : "activated by"

    PRODUCER ||--o{ PRODUCT : sells
    PRODUCER ||--|| CUTOFF_RULE : "default cutoff"

    CYCLE ||--o{ PRODUCER_ORDER : "participating producers"
    PRODUCER ||--o{ PRODUCER_ORDER : "takes part in"
    CYCLE ||--o{ PRODUCT_UNAVAILABILITY : marks
    CYCLE ||--o{ RECEIVED_TOTAL : "per product"
    PRODUCT ||--o{ RECEIVED_TOTAL : ""

    CYCLE ||--o{ ORDER : "one per household"
    HOUSEHOLD ||--o{ ORDER : places
    ORDER ||--o{ ORDER_LINE : "ordered / allocated / delivered"
    ORDER ||--o{ STOCK_LINE : "added at pickup"
    ORDER ||--o{ RETURN_LINE : "added at pickup"
    PRODUCT ||--o{ ORDER_LINE : "snapshot of"
    STOCK_PRODUCT ||--o{ STOCK_LINE : "snapshot of"
    RETURN_ITEM ||--o{ RETURN_LINE : "snapshot of"

    STOCK_PRODUCT ||--o{ STOCK_MOVEMENT : "entry | adjustment | sale"
    ORDER |o--o{ STOCK_MOVEMENT : "sale at pickup"
    HOUSEHOLD ||--o{ WALLET_MOVEMENT : "top-up | charge | credit | correction"
    CYCLE |o--o{ WALLET_MOVEMENT : "charge / credit"

    CYCLE {
        date pickupDate
        ts cancelledAt
        ts settledAt
        ts closedAt
    }
    PRODUCER_ORDER {
        bool participating
        ts cutoff
        ts sentAt
        ts validatedAt
        ts distributedAt
    }
    ORDER {
        enum pickup "none|pending|completed|notCollected|nothingToCollect"
        money charged
    }
    ORDER_LINE {
        qty ordered
        qty allocated
        bool allocatedByHand
        qty delivered
        bool notDelivered
        bool dropped
    }
    PRODUCT {
        string unit
        money price
        bool variable
        qty containersPerUnit
        bool active
    }
```

## 3. User paths

```mermaid
flowchart TB
    subgraph Manager
        direction TB
        M0[Set pickup weekday] --> M1[Open cycle + participants]
        M1 --> M2[Set / clear cutoffs · close producer now]
        M2 --> M3[Send producer order<br/>→ email to producer]
        M3 --> M4[Reception: received totals<br/>→ ProportionalAllocator · manual allocations]
        M4 --> M5[Validate reception]
        M5 --> M6[Mark producer distributed]
        M6 -->|last producer| M7[Cycle settled]
        M7 --> M8[Pickup: adjust delivered · add stock · add returns · top-up]
        M8 --> M9{Complete or<br/>not collected}
        M9 -->|charge wallet + sell stock| M10[Cycle closed when every pickup is done]
        M6 -. reopen .-> M4
        MA[Admin: households · accounts · invitations · producers · stock · wallet corrections]
    end

    subgraph Member
        direction TB
        U0[Accept invitation · set password] --> U1[See my cycles]
        U1 --> U2[Edit household order<br/>until producer cutoff]
        U2 --> U3[See order, then pickup once settled]
        U1 --> U4[Wallet statement]
    end

    subgraph Producer
        direction TB
        P0[Accept invitation] --> P1[Manage own products]
        P0 --> P2[Set default cutoff]
        P1 --> P3[Mark product unavailable in a cycle]
        P3 --> P4[See cycle summary of sent totals]
    end

    M1 -. opens ordering .-> U2
    P2 -. default cutoff .-> M1
    U2 -. lines .-> M3
    M3 -. sent order .-> P4
    M8 -. resulting balance .-> U4
```
