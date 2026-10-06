export type User = {
    id: string;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

/** Which profile the signed-in person is acting as. */
export type ActingAs = 'administrator' | 'member';

export type Auth = {
    user: User;
    actingAs: ActingAs | null;
    /** The profiles the person may act as; more than one means they can switch. */
    profiles: ActingAs[];
    isOwner: boolean;
    /** Whether the person manages households, invitations and commissions. */
    canManageMembership: boolean;
    /** A member with no household and no commission, who can only wait. */
    isWaiting: boolean;
};
