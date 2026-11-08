declare namespace App {
namespace Http {
export type AccountRow = {
readonly accountId: string,
readonly name: string,
readonly email: string,
readonly roles: Guixa.Shared.Domain.Role[],
readonly householdId: string | null,
readonly householdName: string | null,
readonly active: boolean,
readonly invitationPending: boolean,
};
export type BasketActionsRow = {
readonly edit: boolean,
readonly validate: boolean,
readonly cancel: boolean,
readonly markCollected: boolean,
readonly correct: boolean,
readonly correctCollection: boolean,
};
export type BasketAdjustmentLineRow = {
readonly stockProductId: string,
readonly quantityDelta: string,
};
export type BasketAdjustmentRow = {
readonly id: string,
readonly amountCents: number,
readonly reason: string,
readonly authorName: string,
readonly occurredAt: string,
readonly lines: App.Http.BasketAdjustmentLineRow[],
};
export type BasketCollectionEventRow = {
readonly id: string,
readonly type: string,
readonly authorName: string,
readonly occurredAt: string,
readonly reason: string | null,
};
export type BasketHouseholdRow = {
readonly id: string,
readonly name: string,
};
export type BasketReturnLineRow = {
readonly returnItemId: string,
readonly name: string,
readonly amountCents: number,
readonly quantity: number,
readonly creditCents: number,
};
export type BasketRow = {
readonly id: string,
readonly householdId: string,
readonly householdName: string,
readonly state: string,
readonly stockLines: App.Http.BasketStockLineRow[],
readonly returnLines: App.Http.BasketReturnLineRow[],
readonly totalCents: number,
readonly balanceCents: number,
readonly projectedBalanceCents: number | null,
readonly validatedAt: string | null,
readonly collectedAt: string | null,
readonly effectiveTotalCents: number,
readonly adjustments: App.Http.BasketAdjustmentRow[],
readonly collectionEvents: App.Http.BasketCollectionEventRow[],
readonly pendingCollection: boolean,
readonly can: App.Http.BasketActionsRow,
readonly status: string,
readonly settled: boolean,
readonly collected: boolean,
readonly weekly: boolean,
readonly weeklyLines: App.Http.BasketWeeklyLineRow[],
readonly kind: string,
readonly late: boolean,
};
export type BasketStockLineRow = {
readonly stockProductId: string,
readonly name: string,
readonly unit: string,
readonly quantity: string,
readonly unitPriceCents: number,
readonly amountCents: number,
readonly effectiveQuantity: string,
readonly effectiveAmountCents: number,
};
export type BasketSummaryRow = {
readonly id: string,
readonly householdName: string,
readonly state: string,
readonly totalCents: number,
readonly createdAt: string,
readonly pendingCollection: boolean,
readonly status: string,
readonly validatedAt: string | null,
};
export type BasketWeeklyLineRow = {
readonly productId: string,
readonly name: string,
readonly unit: string,
readonly ordered: string,
readonly allocated: string,
readonly quantity: string,
readonly unitPriceCents: number,
readonly amountCents: number,
readonly effectiveQuantity: string,
readonly effectiveAmountCents: number,
readonly differsFromAllocation: boolean,
};
export type ContainerCountRow = {
readonly total: number,
readonly households: App.Http.HouseholdContainerRow[],
};
export type CutoffRuleRow = {
readonly daysBefore: number,
readonly time: string,
};
export type CycleOptionRow = {
readonly id: string,
readonly pickupDate: string,
};
export type CycleParticipantRow = {
readonly producerId: string,
readonly producerName: string,
readonly participating: boolean,
readonly cutoff: string | null,
readonly open: boolean,
readonly sent: boolean,
readonly canSendManually: boolean,
};
export type CycleRow = {
readonly id: string,
readonly pickupDate: string,
readonly cancelled: boolean,
readonly participants: App.Http.CycleParticipantRow[],
readonly closed: boolean,
readonly settled: boolean,
};
export type CycleSummaryRow = {
readonly cycleId: string,
readonly pickupDate: string,
readonly cancelled: boolean,
readonly producers: App.Http.ProducerTotalsRow[],
};
export type HouseholdContainerRow = {
readonly householdId: string,
readonly householdName: string,
readonly count: number,
readonly balance: number,
};
export type HouseholdOptionRow = {
readonly id: string,
readonly name: string,
};
export type HouseholdQuantityRow = {
readonly householdId: string,
readonly householdName: string,
readonly quantity: string,
};
export type HouseholdRow = {
readonly id: string,
readonly name: string,
readonly active: boolean,
readonly members: App.Http.MemberRow[],
};
export type HouseholdSettlementRow = {
readonly householdId: string,
readonly householdName: string,
readonly balanceCents: number,
readonly openBaskets: App.Http.OpenBasketRow[],
};
export type HouseholdSummaryRow = {
readonly id: string,
readonly name: string,
readonly active: boolean,
readonly memberCount: number,
};
export type MemberCycleRow = {
readonly cycleId: string,
readonly pickupDate: string,
readonly open: boolean,
readonly cancelled: boolean,
readonly lineCount: number,
};
export type MemberRow = {
readonly accountId: string,
readonly name: string,
readonly email: string,
readonly invitationPending: boolean,
readonly invitationExpiresAt: string | null,
readonly invitationExpired: boolean,
};
export type OfferedProductRow = {
readonly id: string,
readonly name: string,
readonly unit: string,
readonly price: string,
readonly variable: boolean,
};
export type OpenBasketRow = {
readonly basketId: string,
readonly status: string,
readonly date: string,
};
export type OrderLineRow = {
readonly productId: string,
readonly producerId: string,
readonly producerName: string,
readonly name: string,
readonly unit: string,
readonly price: string,
readonly variable: boolean,
readonly quantity: string,
readonly notDelivered: boolean,
};
export type OrderPreviewRow = {
readonly totalCents: number,
readonly amountCents: Record<string, number>,
};
export type OrderProducerRow = {
readonly producerId: string,
readonly producerName: string,
readonly open: boolean,
readonly products: App.Http.OfferedProductRow[],
};
export type OrderRow = {
readonly cycleId: string,
readonly pickupDate: string,
readonly cancelled: boolean,
readonly producers: App.Http.OrderProducerRow[],
readonly lines: App.Http.OrderLineRow[],
readonly basketId: string | null,
};
export type ProducerCycleSummaryRow = {
readonly cycleId: string,
readonly pickupDate: string,
readonly sent: boolean,
readonly products: App.Http.ProducerProductTotalRow[],
};
export type ProducerOptionRow = {
readonly id: string,
readonly name: string,
};
export type ProducerProductRow = {
readonly id: string,
readonly producerId: string,
readonly name: string,
readonly unit: string,
readonly price: string,
readonly variable: boolean,
readonly containersPerUnit: string | null,
readonly active: boolean,
readonly stock: boolean,
readonly used: boolean,
};
export type ProducerProductTotalRow = {
readonly productId: string,
readonly name: string,
readonly unit: string,
readonly available: boolean,
readonly quantity: string | null,
};
export type ProducerRow = {
readonly id: string,
readonly name: string,
readonly active: boolean,
readonly cutoffDaysBefore: number,
readonly cutoffTime: string,
readonly accountEmail: string | null,
readonly accountInvitationPending: boolean,
};
export type ProducerStockRow = {
readonly id: string,
readonly name: string,
readonly unit: string,
readonly price: string,
readonly active: boolean,
readonly available: string,
};
export type ProducerTotalsRow = {
readonly producerId: string,
readonly producerName: string,
readonly products: App.Http.ProductTotalRow[],
};
export type ProductRow = {
readonly id: string,
readonly name: string,
readonly unit: string,
readonly price: string,
readonly variable: boolean,
readonly containersPerUnit: string | null,
readonly active: boolean,
};
export type ProductTotalRow = {
readonly productId: string,
readonly name: string,
readonly unit: string,
readonly quantity: string,
readonly households: App.Http.HouseholdQuantityRow[],
};
export type ReceptionAllocationRow = {
readonly householdId: string,
readonly householdName: string,
readonly ordered: string,
readonly allocated: string,
readonly byHand: boolean,
readonly delivered: string,
readonly adjusted: boolean,
};
export type ReceptionOptionRow = {
readonly productId: string,
readonly name: string,
readonly unit: string,
readonly variable: boolean,
};
export type ReceptionProductRow = {
readonly productId: string,
readonly name: string,
readonly unit: string,
readonly variable: boolean,
readonly nobodyOrdered: boolean,
readonly ordered: string,
readonly received: string,
readonly allocated: string,
readonly delivered: string,
readonly difference: string,
readonly balanced: boolean,
readonly balancedForDistribution: boolean,
readonly hasManualAllocations: boolean,
readonly hasAdjustments: boolean,
readonly allocations: App.Http.ReceptionAllocationRow[],
};
export type ReceptionRow = {
readonly cycleId: string,
readonly producerId: string,
readonly producerName: string,
readonly pickupDate: string,
readonly products: App.Http.ReceptionProductRow[],
readonly unorderedOptions: App.Http.ReceptionOptionRow[],
};
export type ReturnItemRow = {
readonly id: string,
readonly name: string,
readonly amount: string,
readonly active: boolean,
readonly amountCents: number,
};
export type RoleArea = {
readonly role: Guixa.Shared.Domain.Role,
readonly label: string,
readonly href: string,
};
export type StockLevelRow = {
readonly id: string,
readonly producerId: string,
readonly name: string,
readonly unit: string,
readonly price: string,
readonly variable: boolean,
readonly containersPerUnit: string | null,
readonly active: boolean,
readonly available: string,
};
export type StockMovementPageRow = {
readonly lines: App.Http.StockMovementRow[],
readonly total: number,
readonly page: number,
readonly prevUrl: string | null,
readonly nextUrl: string | null,
};
export type StockMovementRow = {
readonly id: string,
readonly occurredAt: string,
readonly type: string,
readonly quantity: string,
readonly balanceAfter: string,
readonly note: string | null,
readonly authorName: string,
readonly cycleId: string | null,
readonly pickupDate: string | null,
readonly basketId: string | null,
};
export type StockOfferRow = {
readonly id: string,
readonly producerId: string,
readonly producerName: string,
readonly name: string,
readonly unit: string,
readonly price: string,
readonly available: string,
readonly soldOut: boolean,
};
export type StockProductRow = {
readonly id: string,
readonly producerId: string,
readonly name: string,
readonly unit: string,
readonly price: string,
readonly variable: boolean,
readonly containersPerUnit: string | null,
readonly active: boolean,
};
export type WalletHouseholdRow = {
readonly id: string,
readonly name: string,
};
export type WalletMovementRow = {
readonly id: string,
readonly type: string,
readonly amount: string,
readonly occurredAt: string,
readonly note: string | null,
readonly cycleId: string | null,
readonly basketId: string | null,
readonly sourceMovementId: string | null,
readonly authorName: string,
};
export type WalletStatementRow = {
readonly balance: string,
readonly movements: App.Http.WalletMovementRow[],
};
}
}
declare namespace Guixa {
namespace Sales {
namespace Domain {
export type BasketKind = 'stock' | 'weekly';
export type BasketLineKind = 'stock' | 'return' | 'weekly';
export type BasketState = 'draft' | 'validated' | 'collected' | 'cancelled' | 'nothing_to_collect';
}
}
namespace Shared {
namespace Domain {
export type Role = 'manager' | 'finance' | 'member' | 'producer';
}
}
namespace Stock {
namespace Domain {
export type MovementType = 'entry' | 'adjustment' | 'sale';
}
}
namespace Wallet {
namespace Domain {
export type MovementType = 'top_up' | 'charge' | 'credit' | 'correction';
}
}
}
