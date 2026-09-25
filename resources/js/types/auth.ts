export type Account = {
    id: number;
    name: string;
};

export type AccountMembership = Account & {
    is_admin: boolean;
};

export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    is_admin: boolean;
    active_account_id: number | null;
    locale: string | null;
    timezone: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    /** The account currently being worked in, or null when the user has none. */
    account: Account | null;
    /** Every account the user can switch to. */
    accounts: AccountMembership[];
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
