import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useState } from 'react';
import { WithHowy } from '@/components/brand/howy-name';
import { Section } from '@/components/cc/section';
import { ColleagueHeader } from '@/components/colleagues/colleague-header';
import { useActivityLine } from '@/components/colleagues/colleague-status';
import { HowyMailsProgress } from '@/components/colleagues/howy-mails-progress';
import { SentMailList } from '@/components/colleagues/sent-mail-list';
import { InterpretationTag } from '@/components/mail/interpretation-tag';
import { shownEntry } from '@/components/mailboxes/sender-badge';
import {
    AccessTag,
    useAccessLooks,
} from '@/components/mailboxes/sender-access';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslations } from '@/hooks/use-translations';
import { ACCESSES } from '@/lib/mailbox-rules';
import { cn } from '@/lib/utils';
import colleagueAccess from '@/routes/colleagues/access';
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

type Tab = 'overview' | 'activity' | 'log';

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
    const [tab, setTab] = useState<Tab>('overview');

    const tabs: { key: Tab; label: string; count: number | null }[] = [
        { key: 'overview', label: t('Overview'), count: null },
        { key: 'activity', label: t('Activity'), count: colleague.emailsCount },
        { key: 'log', label: t('Mail log'), count: sent.length },
    ];

    return (
        <>
            <Head title={colleague.name ?? colleague.email} />

            <div className="flex flex-col gap-7">
                <ColleagueHeader colleague={colleague} />

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
                        {tab === 'overview' && (
                            <div className="grid items-start gap-7 xl:grid-cols-2">
                                <Section
                                    title={t('What Howy does with their mail')}
                                    description={
                                        <WithHowy
                                            text={t(
                                                'Per mailbox: a rule of their own, or the rule for their domain.',
                                            )}
                                        />
                                    }
                                >
                                    {access.length === 0 ? (
                                        <p className="cc-caption">
                                            {t('No mailbox is connected yet.')}
                                        </p>
                                    ) : (
                                        <ul className="flex flex-col divide-y divide-cc-border">
                                            {access.map((item) => (
                                                <MailboxAccessRow
                                                    key={item.mailboxId}
                                                    colleagueId={colleague.id}
                                                    item={item}
                                                />
                                            ))}
                                        </ul>
                                    )}
                                </Section>

                                <Section
                                    title={t('Recent activity')}
                                    description={activity(colleague)}
                                    action={
                                        emails.length > 3 && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setTab('activity')
                                                }
                                                className="inline-flex h-9 cursor-pointer items-center gap-1.5 text-[13px] font-semibold text-cc-ink"
                                            >
                                                {t('All mails')}
                                                <ArrowRight className="size-[15px]" />
                                            </button>
                                        )
                                    }
                                >
                                    <EmailList emails={emails.slice(0, 3)} />
                                </Section>
                            </div>
                        )}

                        {tab === 'activity' && (
                            <Section
                                title={t('Their mails to Howy')}
                                description={activity(colleague)}
                            >
                                <EmailList emails={emails} />
                            </Section>
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
        <Section
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
            className="pb-2 lg:pb-3"
        >
            {shown.length === 0 ? (
                <p className="cc-caption pb-4">
                    <WithHowy
                        text={
                            sent.length === 0
                                ? t('Howy has not sent them anything yet.')
                                : t('Nothing of this kind yet.')
                        }
                    />
                </p>
            ) : (
                <div className="-mx-6 lg:-mx-8">
                    <SentMailList mails={shown} showRecipient={false} />
                </div>
            )}
        </Section>
    );
}

/**
 * Their access on one mailbox: what it comes to, why, and a choice between
 * following the domain's rule and one of their own.
 */
function MailboxAccessRow({
    colleagueId,
    item,
}: {
    colleagueId: number;
    item: ColleagueMailboxAccess;
}) {
    const t = useTranslations();
    const looks = useAccessLooks();

    const because =
        item.own !== null
            ? t('Their own rule')
            : item.domain !== null
              ? t('Through the rule for :domain', {
                    domain: shownEntry(item.domain.domain),
                })
              : t('No rule covers them, so Howy ignores their mail');

    const set = (value: string) =>
        router.put(
            colleagueAccess.update.url({
                colleague: colleagueId,
                mailbox: item.mailboxId,
            }),
            { access: value },
            { preserveScroll: true, preserveState: true },
        );

    return (
        <li className="flex flex-wrap items-center gap-x-4 gap-y-2 py-3.5 first:pt-0 last:pb-0">
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <span className="flex min-w-0 items-center gap-2">
                    <span className="truncate text-[14.5px] font-medium">
                        {item.emailAddress}
                    </span>
                    {item.status !== 'active' && (
                        <span className="cc-tag cc-tag-noise shrink-0">
                            {t('Not connected')}
                        </span>
                    )}
                </span>
                <span className="flex flex-wrap items-center gap-2">
                    <AccessTag access={item.access} />
                    <span className="text-[12.5px] text-cc-subtle">
                        {because}
                    </span>
                </span>
            </div>
            <Select value={item.own ?? 'default'} onValueChange={set}>
                <SelectTrigger
                    aria-label={t('What Howy does on :mailbox', {
                        mailbox: item.emailAddress,
                    })}
                    className="h-10 w-full sm:w-[230px]"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="default">
                        {item.domain !== null
                            ? t('Follow :domain', {
                                  domain: shownEntry(item.domain.domain),
                              })
                            : t('Follow the mailbox rules')}
                    </SelectItem>
                    {ACCESSES.map((access) => (
                        <SelectItem key={access} value={access}>
                            {looks[access].label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </li>
    );
}
