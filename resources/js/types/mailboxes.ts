export type MailboxStatus = 'active' | 'needs_reauth' | 'disconnected';

/** What Howy may do with a sender's mail. Mirrors App\Enums\SenderAccess. */
export type SenderAccess = 'learn_answer' | 'learn' | 'answer' | 'ignore';

/**
 * One address (jan@x.nl) or domain (x.nl, stored without the @) on a mailbox.
 * An address is a colleague's own rule; a domain may make the people it lets
 * in colleagues. Not yet saved, neither is known.
 */
export type SenderRule = {
    entry: string;
    access: SenderAccess;
    createsColleagues?: boolean;
    colleagueId?: number | null;
};

export type Mailbox = {
    id: number;
    emailAddress: string;
    provider: 'gmail';
    status: MailboxStatus;
    lastMessageAt: string | null;
    emailsCount: number;
    import: { processed: number; total: number } | null;
    domain: string;
    /** Anyone can have an address there (gmail.com…), so no colleagues. */
    publicDomain: boolean;
    /** For senders at the mailbox's own domain; everyone else not listed is ignored. */
    domainAccess: SenderAccess;
    senderRules: SenderRule[];
    /** Never imported: offer its last 30 days, once (administrators only). */
    importOffered: boolean;
};

/** The numbers the explanation of a mailbox quotes, as configured. */
export type MailFacts = {
    /** How sure an answer must be, in percent, before Howy sends it. */
    answerConfidence: number;
    /** Days what went to the AI provider is kept; 0 when it is not kept. */
    aiLogDays: number;
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
