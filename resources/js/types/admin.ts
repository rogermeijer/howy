export type Paginated<T> = {
    data: T[];
    total: number;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type AccountOption = { id: number; name: string };

export type AdminAccountRow = {
    id: number;
    name: string;
    locale: string;
    timezone: string;
    members_count: number;
    admins: string[];
    ai_calls: number;
    ai_cost_micros: number;
    created_at: string | null;
};

export type AdminMember = {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    is_super_admin: boolean;
    joined_at: string | null;
};

export type AdminMembership = {
    id: number;
    name: string;
    is_admin: boolean;
};

export type AdminUserRow = {
    id: number;
    name: string;
    email: string;
    is_super_admin: boolean;
    email_verified: boolean;
    accounts: AdminMembership[];
    beta_code: string | null;
    created_at: string | null;
};

export type BetaStatus = 'pending' | 'invited' | 'registered';

export type BetaInviteRow = {
    id: number;
    email: string;
    locale: string | null;
    code: string;
    status: BetaStatus;
    created_at: string | null;
    invited_at: string | null;
    redeemed_at: string | null;
    user: { id: number; name: string } | null;
};

export type AiTotals = {
    calls: number;
    failed: number;
    input_tokens: number;
    cached_input_tokens: number;
    output_tokens: number;
    cost_micros: number;
    avg_latency_ms: number | null;
};

export type AiBreakdownRow = {
    key: string;
    label: string | null;
    filter: Record<string, string | number>;
    calls: number;
    failed: number;
    tokens: number;
    cost_micros: number;
};

export type AiDay = { date: string; calls: number; cost_micros: number };

export type AiCall = {
    id: number;
    created_at: string | null;
    account: { id: number; name: string };
    step: string;
    agent: string | null;
    provider: string;
    model: string;
    status: 'succeeded' | 'failed';
    error: string | null;
    latency_ms: number | null;
    input_count: number | null;
    input_tokens: number;
    cached_input_tokens: number;
    output_tokens: number;
    cost_micros: number;
    is_batch: boolean;
    subject_type: string | null;
    subject_id: number | null;
    subject_label: string | null;
    thread_key: string | null;
    has_payload: boolean;
};

export type AiCallDetail = AiCall & {
    payload: {
        instructions: string | null;
        input: string | null;
        output: string | null;
        attachments: {
            type: string;
            name: string | null;
            mime_type: string | null;
        }[];
    } | null;
};

export type AiFilters = {
    from: string;
    to: string;
    account: number | null;
    step: string | null;
    model: string | null;
    agent: string | null;
    status: string | null;
    subject_type: string | null;
    subject_id: number | null;
    thread: string | null;
    batch: string | null;
};
