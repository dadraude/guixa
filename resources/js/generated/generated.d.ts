declare namespace App {
namespace Http {
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
