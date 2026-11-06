export type Account = {
    id: number;
    name: string;
};

/** What the account switcher gets back for a search. */
export type AccountSwitcherResponse = {
    /** At most five of the user's other accounts, best match first. */
    accounts: Account[];
    /** How many of the other accounts match, shown or not. */
    total: number;
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
    /** How many accounts the user belongs to, the current one included. */
    accountCount: number;
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
