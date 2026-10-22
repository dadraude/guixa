declare namespace App {
namespace Http {
export type RoleArea = {
readonly role: Guixa.Shared.Domain.Role,
readonly label: string,
readonly href: string,
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
