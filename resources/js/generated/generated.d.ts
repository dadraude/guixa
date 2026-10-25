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
};
export type CycleRow = {
readonly id: string,
readonly pickupDate: string,
readonly cancelled: boolean,
readonly participants: App.Http.CycleParticipantRow[],
readonly closed: boolean,
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
readonly pendingPickups: App.Http.PendingPickupRow[],
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
readonly pickup: App.Http.PickupRow | null,
};
export type PendingPickupRow = {
readonly cycleId: string,
readonly pickupDate: string,
};
export type PickupAdjustmentRow = {
readonly id: string,
readonly productId: string,
readonly name: string,
readonly unit: string,
readonly quantityDelta: string,
readonly amountCents: number,
readonly recordedAt: string,
};
export type PickupLineRow = {
readonly productId: string,
readonly name: string,
readonly unit: string,
readonly variable: boolean,
readonly unitPriceCents: number,
readonly ordered: string,
readonly allocated: string,
readonly delivered: string,
readonly adjusted: boolean,
readonly amountCents: number,
};
export type PickupReturnLineRow = {
readonly returnItemId: string,
readonly name: string,
readonly amountCents: number,
readonly quantity: number,
readonly creditCents: number,
};
export type PickupRow = {
readonly cycleId: string,
readonly pickupDate: string,
readonly householdId: string,
readonly householdName: string,
readonly status: string,
readonly lines: App.Http.PickupLineRow[],
readonly totalCents: number,
readonly balanceCents: number | null,
readonly resultingBalanceCents: number | null,
readonly stockLines: App.Http.PickupStockLineRow[],
readonly returnLines: App.Http.PickupReturnLineRow[],
readonly adjustments: App.Http.PickupAdjustmentRow[],
readonly originalChargeCents: number | null,
};
export type PickupStockLineRow = {
readonly stockProductId: string,
readonly name: string,
readonly unit: string,
readonly variable: boolean,
readonly unitPriceCents: number,
readonly quantity: string,
readonly amountCents: number,
};
export type PickupSummaryRow = {
readonly householdId: string,
readonly householdName: string,
readonly status: string,
readonly totalCents: number,
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
readonly hasDonePickups: boolean,
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
readonly name: string,
readonly unit: string,
readonly price: string,
readonly variable: boolean,
readonly containersPerUnit: string | null,
readonly active: boolean,
readonly available: string,
};
export type StockProductRow = {
readonly id: string,
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
};
export type WalletStatementRow = {
readonly balance: string,
readonly movements: App.Http.WalletMovementRow[],
};
}
}
declare namespace Guixa {
namespace Ordering {
namespace Domain {
export type PickupStatus = 'none' | 'pending' | 'nothing_to_collect' | 'completed' | 'not_collected';
}
}
namespace Shared {
namespace Domain {
export type Role = 'manager' | 'member' | 'producer';
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
