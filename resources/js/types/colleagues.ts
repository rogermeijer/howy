import type { InterpretationBrief } from './interpretations';
import type { MailboxStatus, SenderAccess } from './mailboxes';

/** Where someone's Howy mails stand. Mirrors ColleagueController::status(). */
export type ColleagueStatus =
    | 'not_welcomed'
    | 'welcomed'
    | 'ignored'
    | 'opted_out'
    | 'off';

/** Which rule decides for them: their own, their domain's, or none. */
export type ColleagueAccessFrom = 'own' | 'domain' | 'none';

export type ColleagueRow = {
    id: number;
    email: string;
    name: string | null;
    /** Also a member of the account, who can log in. */
    isMember: boolean;
    homeMailbox: string | null;
    /** What Howy may do with their mail on their home mailbox. */
    access: SenderAccess;
    accessFrom: ColleagueAccessFrom;
    receivesMail: boolean;
    status: ColleagueStatus;
    welcomedAt: string | null;
    lastMailAt: string | null;
    emailsCount: number;
    questionsCount: number;
    contributionsCount: number;
};

export type ColleagueDetail = ColleagueRow & {
    homeMailboxId: number | null;
    user: { name: string; email: string } | null;
    firstSeenAt: string | null;
    explainedAt: string | null;
    firstSavedAt: string | null;
    nudgedAt: string | null;
    optedOutAt: string | null;
};

/** Their access on one mailbox, and the rules it comes from. */
export type ColleagueMailboxAccess = {
    mailboxId: number;
    emailAddress: string;
    status: MailboxStatus;
    /** Their own rule there; null when their domain's decides. */
    own: SenderAccess | null;
    domain: { domain: string; access: SenderAccess } | null;
    access: SenderAccess;
};

export type ColleagueEmail = {
    id: number;
    subject: string | null;
    receivedAt: string | null;
    interpretation: InterpretationBrief | null;
};

export type MailboxOption = { id: number; emailAddress: string };
