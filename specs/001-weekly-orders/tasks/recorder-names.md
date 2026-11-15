# 001 — Recorder names task details (2026-11-07)

Part of the [canonical registry](../tasks.md); dependencies live there. Authoritative behavior:
[spec](../spec.md) and its areas (R23a in `pickup.md`, R31 in `stock-wallet.md`); technical contracts:
[plan](../plan.md) (Sales "Collection and corrections", Wallet).

## T82

**Show recorder names instead of account ids (R23a, R31).** Spec and plan accepted on 2026-11-07. The
wallet statement, basket amount adjustments and basket collection history show the recorder's account
UUID; Stock's movement history (T76) already shows the name. Build it as one vertical slice:

1. Application tests through `GetWalletStatement`, `GetBasket` and `GetMemberBasket`: each movement,
   adjustment and collection event (corrections included) carries its author's name; a deactivated
   account keeps its name; an unknown account gives an empty name; names are resolved in one batch.
2. Wallet and Sales `AccountNames` ports with Membership adapters over `GetAccountNames`, bound in
   `AppServiceProvider`. `MovementData`, `BasketAdjustmentData` and `BasketCollectionEventData` carry
   `authorName` in place of the account id.
3. HTTP: `WalletMovementRow`, `BasketAdjustmentRow` and `BasketCollectionEventRow` expose `authorName`
   and no account id; feature tests assert the name is in each payload and the id is not. Generated
   TypeScript types.
4. Pages: `Wallet/Statement.vue` ("Registrat per :name", hidden when the name is empty),
   `components/baskets/AmountAdjustments.vue` and `components/baskets/BasketCollectionHistory.vue`;
   Catalan translation updated; Vitest specs and visual fixtures follow the new field. Measure the
   statement at 375 px for wrapping of long names.

Done when the Application and HTTP tests pass and no page renders an account id. Linear:
[DRA-247](https://linear.app/draude/issue/DRA-247).
