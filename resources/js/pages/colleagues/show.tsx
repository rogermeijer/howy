import { Head, Link } from '@inertiajs/react';
import { type ReactNode, useState } from 'react';
import { WithHowy } from '@/components/brand/howy-name';
import { ColleagueHeader } from '@/components/colleagues/colleague-header';
import { useActivityLine } from '@/components/colleagues/colleague-status';
import { HowyMailsProgress } from '@/components/colleagues/howy-mails-progress';
import { SentMailList } from '@/components/colleagues/sent-mail-list';
import { InterpretationTag } from '@/components/mail/interpretation-tag';
import { useAccountUpdates } from '@/hooks/use-account-updates';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { show as showEmail } from '@/routes/emails';
import type {
    ColleagueDetail,
    ColleagueEmail,
    ColleagueMailboxAccess,
    MailboxOption,
    SentMail,
} from '@/types';

type Props = {
    colleague: ColleagueDetail;
    access: ColleagueMailboxAccess[];
    emails: ColleagueEmail[];
    sent: SentMail[];
    mailboxes: MailboxOption[];
    /** Live, and they may get the welcome but have not had it. */
    canWelcome: boolean;
    /** The kinds of Howy's own mails to them that are on their way. */
    onTheirWay: string[];
    /** Per kind of Howy's own mail, the one to send again. */
    resendable: Record<string, number>;
    /** When the follow-ups to the welcome are due; null for one that will not come. */
    planned: { how_it_works: string | null; nudge: string | null };
    isLive: boolean;
    /** Working days after the welcome for each follow-up. */
    followUpDays: { how_it_works: number; nudge: number };
};

type Tab = 'activity' | 'log';

/** Howy's own mails, as against its answers. */
const FLOW_KINDS = ['welcome', 'how_it_works', 'first_save', 'nudge'];
const REPLY_KINDS = ['answer', 'not_found', 'conflict'];

export default function Colleague({
    colleague,
    access,
    emails,
    sent,
    mailboxes,
    canWelcome,
    onTheirWay,
    resendable,
    planned,
    isLive,
    followUpDays,
}: Props) {
    const t = useTranslations();
    const activity = useActivityLine();
    const [tab, setTab] = useState<Tab>('activity');

    // A mail that goes, or is held back, moves the bar and the log along.
    useAccountUpdates('sent-mails', [
        'colleague',
        'sent',
        'onTheirWay',
        'resendable',
        'planned',
        'canWelcome',
    ]);
    // Their mail coming in moves the activity, and the reminder, along.
    useAccountUpdates('mail', ['colleague', 'emails', 'planned']);

    const tabs: { key: Tab; label: string; count: number | null }[] = [
        { key: 'activity', label: t('Activity'), count: colleague.emailsCount },
        { key: 'log', label: t('Mail log'), count: sent.length },
    ];

    return (
        <>
            <Head title={colleague.name ?? colleague.email} />

            <div className="flex flex-col gap-7">
                <ColleagueHeader colleague={colleague} access={access} />

                <HowyMailsProgress
                    colleague={colleague}
                    home={
                        access.find(
                            (item) =>
                                item.mailboxId === colleague.homeMailboxId,
                        ) ?? null
                    }
                    mailboxes={mailboxes}
                    planned={planned}
                    onTheirWay={onTheirWay}
                    resendable={resendable}
                    sent={sent}
                    canWelcome={canWelcome}
                    isLive={isLive}
                    followUpDays={followUpDays}
                    onShowLog={() => setTab('log')}
                />

                <div className="flex flex-col gap-7">
                    <div
                        role="tablist"
                        aria-label={t('About this colleague')}
                        className="flex gap-1 overflow-x-auto border-b border-cc-border"
                    >
                        {tabs.map((item) => (
                            <button
                                key={item.key}
                                type="button"
                                role="tab"
                                id={`colleague-tab-${item.key}`}
                                aria-selected={tab === item.key}
                                aria-controls="colleague-tab-panel"
                                onClick={() => setTab(item.key)}
                                className={cn(
                                    '-mb-px flex h-11 shrink-0 cursor-pointer items-center gap-2 border-b-2 px-3 text-sm transition-colors',
                                    tab === item.key
                                        ? 'border-cc-accent font-semibold text-cc-ink'
                                        : 'border-transparent font-medium text-cc-subtle hover:text-cc-ink',
                                )}
                            >
                                {item.label}
                                {item.count !== null && (
                                    <span className="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-cc-raised px-1.5 text-[11px] font-semibold text-cc-ink">
                                        {item.count}
                                    </span>
                                )}
                            </button>
                        ))}
                    </div>

                    <div
                        id="colleague-tab-panel"
                        role="tabpanel"
                        aria-labelledby={`colleague-tab-${tab}`}
                    >
                        {tab === 'activity' && (
                            <ListPanel
                                title={t('Their mails to Howy')}
                                description={activity(colleague)}
                            >
                                <div className="px-6 py-3">
                                    <EmailList emails={emails} />
                                </div>
                            </ListPanel>
                        )}

                        {tab === 'log' && <MailLog sent={sent} />}
                    </div>
                </div>
            </div>
        </>
    );
}

/**
 * Their mails to Howy, newest first, each with what Howy made of it.
 */
function EmailList({ emails }: { emails: ColleagueEmail[] }) {
    const t = useTranslations();
    const formatDate = useFormatDate();

    if (emails.length === 0) {
        return (
            <p className="cc-caption">
                <WithHowy text={t('No mail from them has reached Howy yet.')} />
            </p>
        );
    }

    return (
        <ul className="-mx-2 flex flex-col">
            {emails.map((email) => (
                <li key={email.id}>
                    <Link
                        href={showEmail(email.id)}
                        className="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-[10px] px-2 py-2.5 transition-colors hover:bg-cc-bg"
                    >
                        <span className="min-w-0 flex-1 truncate text-[14.5px] font-medium">
                            {email.subject || t('(no subject)')}
                        </span>
                        <InterpretationTag
                            interpretation={email.interpretation}
                        />
                        <span className="cc-caption w-[100px] text-right">
                            {email.receivedAt !== null &&
                                formatDate(email.receivedAt)}
                        </span>
                    </Link>
                </li>
            ))}
        </ul>
    );
}

/**
 * Everything Howy sent them, or held back, newest first: all of it, its own
 * mails, or its answers.
 */
function MailLog({ sent }: { sent: SentMail[] }) {
    const t = useTranslations();
    const [filter, setFilter] = useState<'all' | 'flow' | 'replies'>('all');

    const filters = [
        { key: 'all', label: t('All') },
        { key: 'flow', label: t('Howy mails') },
        { key: 'replies', label: t('Answers to their questions') },
    ] as const;

    const shown = sent.filter((mail) =>
        filter === 'flow'
            ? FLOW_KINDS.includes(mail.kind)
            : filter === 'replies'
              ? REPLY_KINDS.includes(mail.kind)
              : true,
    );

    return (
        <ListPanel
            title={t('Mail log')}
            description={
                <WithHowy
                    text={t('What Howy sent them, or held back, newest first.')}
                />
            }
            action={
                sent.length > 0 && (
                    <div className="flex flex-wrap gap-1.5">
                        {filters.map((item) => (
                            <button
                                key={item.key}
                                type="button"
                                aria-pressed={filter === item.key}
                                onClick={() => setFilter(item.key)}
                                className={cn(
                                    'cc-pill',
                                    filter === item.key && 'cc-pill-active',
                                )}
                            >
                                <WithHowy text={item.label} />
                            </button>
                        ))}
                    </div>
                )
            }
        >
            {shown.length === 0 ? (
                <p className="cc-caption px-6 py-5">
                    <WithHowy
                        text={
                            sent.length === 0
                                ? t('Howy has not sent them anything yet.')
                                : t('Nothing of this kind yet.')
                        }
                    />
                </p>
            ) : (
                <SentMailList mails={shown} showRecipient={false} />
            )}
        </ListPanel>
    );
}

/**
 * A list in a panel, under a header on an off-white band, so the header never
 * reads as one of the rows.
 */
function ListPanel({
    title,
    description,
    action,
    children,
}: {
    title: string;
    description?: ReactNode;
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="cc-panel overflow-hidden">
            <div className="flex flex-wrap items-start justify-between gap-3 border-b border-cc-border bg-cc-bg/70 px-6 py-5">
                <div className="flex flex-col gap-1">
                    <h2 className="text-[17px] leading-tight font-semibold tracking-[-0.01em]">
                        {title}
                    </h2>
                    {description && (
                        <p className="cc-caption max-w-prose">{description}</p>
                    )}
                </div>
                {action}
            </div>
            {children}
        </section>
    );
}
