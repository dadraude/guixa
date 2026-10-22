declare namespace App {
namespace Http {
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
export type StockProductRow = {
readonly id: string,
readonly name: string,
readonly unit: string,
readonly price: string,
readonly variable: boolean,
readonly containersPerUnit: string | null,
readonly active: boolean,
};
}
}
declare namespace Guixa {
namespace Shared {
namespace Domain {
export type Role = 'manager' | 'member' | 'producer';
}
}
}
