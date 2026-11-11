import type { InterpretationBrief } from './interpretations';
import type { MailboxStatus, SenderAccess } from './mailboxes';

/** Where someone's Howy mails stand. Mirrors ColleagueController::status(). */
export type ColleagueStatus =
    | 'not_welcomed'
    | 'welcomed'
    | 'ignored'
    | 'no_mailbox'
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

/** Mirrors App\Enums\OutboundMailStatus. */
export type SentMailStatus = 'pending' | 'sent' | 'skipped' | 'failed';

/** A mail that came in as a reply to one of Howy's. */
export type SentMailReply = {
    id: number;
    fromName: string | null;
    fromEmail: string | null;
    snippet: string;
    receivedAt: string | null;
};

/** A mail Howy sent, or meant to. Mirrors SentMailController::present(). */
export type SentMail = {
    id: number;
    recipient: string;
    cc: string[];
    colleague: { id: number; name: string } | null;
    mailbox: string | null;
    kind: string;
    kindLabel: string;
    status: SentMailStatus;
    statusLabel: string;
    /** Why it did not go: the skip reason, or the error. */
    reason: string | null;
    subject: string | null;
    /** Something to show once opened: the HTML it went with, or its text. */
    hasContent: boolean;
    /** The mail it answers, or is about. */
    emailId: number | null;
    answers: { id: number; subject: string | null } | null;
    /** The mails that came in as a reply to it, oldest first. */
    replies: SentMailReply[];
    /** One of Howy's own mails to a colleague that went, or failed. */
    canResend: boolean;
    createdAt: string | null;
    sentAt: string | null;
};

/** Whether Howy sends its own mails to colleagues, and since when. */
export type LiveState = { since: string | null };

/** Someone the welcome would go to on going live. */
export type WelcomeCandidate = {
    id: number;
    name: string | null;
    email: string;
    mailbox: string | null;
};
