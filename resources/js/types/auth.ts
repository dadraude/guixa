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
    isOwner: boolean;
};
