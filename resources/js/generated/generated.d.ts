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
export type CutoffRuleRow = {
readonly daysBefore: number,
readonly time: string,
};
export type HouseholdRow = {
readonly id: string,
readonly name: string,
readonly active: boolean,
readonly members: App.Http.MemberRow[],
};
export type HouseholdSummaryRow = {
readonly id: string,
readonly name: string,
readonly active: boolean,
readonly memberCount: number,
};
export type MemberRow = {
readonly accountId: string,
readonly name: string,
readonly email: string,
readonly invitationPending: boolean,
readonly invitationExpiresAt: string | null,
readonly invitationExpired: boolean,
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
export type ProductRow = {
readonly id: string,
readonly name: string,
readonly unit: string,
readonly price: string,
readonly variable: boolean,
readonly containersPerUnit: string | null,
readonly active: boolean,
};
export type ReturnItemRow = {
readonly id: string,
readonly name: string,
readonly amount: string,
readonly active: boolean,
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
