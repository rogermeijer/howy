export type MailboxStatus = 'active' | 'needs_reauth' | 'disconnected';

export type SendPolicy = 'off' | 'domain' | 'whitelist' | 'always';

export type Mailbox = {
    id: number;
    emailAddress: string;
    provider: 'gmail';
    status: MailboxStatus;
    lastMessageAt: string | null;
    emailsCount: number;
    import: { processed: number; total: number } | null;
    domain: string;
    sendPolicy: SendPolicy;
    sendWhitelist: string[];
    sendBlacklist: string[];
};

export type MailboxSearchResult = {
    id: string;
    threadId: string | null;
    fromName: string | null;
    fromEmail: string | null;
    subject: string | null;
    snippet: string | null;
    receivedAt: string | null;
    hasAttachments: boolean;
    alreadyImported: boolean;
};

export type MailboxSearchResponse = {
    messages: MailboxSearchResult[];
    nextPageToken: string | null;
    estimate: number;
};
