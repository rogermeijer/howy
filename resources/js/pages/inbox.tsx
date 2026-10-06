import { Head, Link } from '@inertiajs/react';
import { Copy, Inbox as InboxIcon, Paperclip } from 'lucide-react';
import { WithHowy } from '@/components/brand/howy-name';
import { PageHeader } from '@/components/cc/page-header';
import { InterpretationTag } from '@/components/mail/interpretation-tag';
import { MailboxBanner } from '@/components/mailboxes/mailbox-banner';
import { Button } from '@/components/ui/button';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { show } from '@/routes/emails';
import type { InterpretationBrief } from '@/types';

type InboxThread = {
    id: number;
    subject: string | null;
    participants: string[];
    snippet: string | null;
    lastReceivedAt: string | null;
    messagesCount: number;
    hasAttachments: boolean;
    interpretation: InterpretationBrief | null;
};

type Paginated<T> = {
    data: T[];
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Props = {
    threads: Paginated<InboxThread>;
    hasMailbox: boolean;
    canManageMailboxes: boolean;
};

const headerGrid =
    'hidden lg:grid grid-cols-[24px_220px_minmax(0,1fr)_170px_150px_72px] items-center gap-5';

export default function Inbox({
    threads,
    hasMailbox,
    canManageMailboxes,
}: Props) {
    const t = useTranslations();
    const formatDate = useFormatDate();

    const time = (value: string) => {
        const date = new Date(value);
        const today = new Date().toDateString() === date.toDateString();

        return formatDate(
            value,
            today
                ? { dateStyle: undefined, timeStyle: 'short' }
                : { dateStyle: undefined, day: 'numeric', month: 'short' },
        );
    };

    return (
        <>
            <Head title={t('Inbox')} />

            <div className="flex flex-col gap-6">
                {!hasMailbox && (
                    <MailboxBanner canManage={canManageMailboxes} />
                )}

                <PageHeader
                    title={t('Inbox')}
                    description={
                        threads.total === 0
                            ? t('No emails processed yet')
                            : t(':count conversations', {
                                  count: threads.total,
                              })
                    }
                    actions={
                        <Button className="h-10">
                            {t('Copy address')}
                            <span className="font-mono font-medium text-cc-dark-text">
                                inbox@cc.nl
                            </span>
                            <Copy />
                        </Button>
                    }
                />

                {threads.data.length === 0 ? (
                    <div className="cc-panel flex flex-col items-center gap-3 px-6 py-[72px] text-center">
                        <div className="flex size-14 items-center justify-center rounded-2xl bg-cc-raised text-cc-subtle">
                            <InboxIcon className="size-7" strokeWidth={1.75} />
                        </div>
                        <p className="text-[15px] font-semibold">
                            {t('No emails yet')}
                        </p>
                        <p className="cc-caption max-w-[420px]">
                            <WithHowy
                                text={t(
                                    'As soon as a mailbox is connected or you put Howy in CC, your emails show up here with their interpretation.',
                                )}
                            />
                        </p>
                    </div>
                ) : (
                    <div className="cc-panel overflow-hidden">
                        <div
                            className={cn(
                                headerGrid,
                                'cc-label border-b border-cc-border px-6 py-2.5',
                            )}
                        >
                            <div />
                            <div>{t('Sender')}</div>
                            <div>{t('Subject & interpretation')}</div>
                            <div>{t('Type')}</div>
                            <div />
                            <div className="text-right">{t('Time')}</div>
                        </div>

                        {threads.data.map((thread) => (
                            <Link
                                key={thread.id}
                                href={show(thread.id)}
                                className="cc-row-inbox border-b border-cc-border px-6 py-4 transition-colors last:border-b-0 hover:bg-cc-bg"
                            >
                                <div className="flex justify-center text-cc-subtle [grid-area:dot]">
                                    {thread.hasAttachments && (
                                        <Paperclip
                                            className="size-3.5"
                                            aria-label={t('Attachment')}
                                        />
                                    )}
                                </div>

                                <div className="flex min-w-0 items-center gap-2 [grid-area:sender]">
                                    <span className="truncate text-[15px] font-medium">
                                        {thread.participants.join(', ')}
                                    </span>
                                    {thread.messagesCount > 1 && (
                                        <span
                                            className="inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-cc-raised px-1.5 text-[11px] font-semibold text-cc-ink"
                                            aria-label={t(':count messages', {
                                                count: thread.messagesCount,
                                            })}
                                        >
                                            {thread.messagesCount}
                                        </span>
                                    )}
                                </div>

                                <div className="flex min-w-0 flex-col gap-1 [grid-area:subject]">
                                    <div className="text-[15px] font-medium lg:truncate">
                                        {thread.subject || t('(no subject)')}
                                    </div>
                                    <div className="text-[14px] text-cc-muted lg:truncate">
                                        {thread.snippet}
                                    </div>
                                </div>

                                <div className="[grid-area:tag]">
                                    <InterpretationTag
                                        interpretation={thread.interpretation}
                                    />
                                </div>

                                <div className="cc-caption text-right [grid-area:time]">
                                    {thread.lastReceivedAt &&
                                        time(thread.lastReceivedAt)}
                                </div>
                            </Link>
                        ))}
                    </div>
                )}

                {(threads.prev_page_url || threads.next_page_url) && (
                    <div className="flex justify-between">
                        {threads.prev_page_url ? (
                            <Button asChild variant="outline" className="h-10">
                                <Link
                                    href={threads.prev_page_url}
                                    preserveScroll
                                >
                                    {t('Newer')}
                                </Link>
                            </Button>
                        ) : (
                            <span />
                        )}
                        {threads.next_page_url && (
                            <Button asChild variant="outline" className="h-10">
                                <Link
                                    href={threads.next_page_url}
                                    preserveScroll
                                >
                                    {t('Older')}
                                </Link>
                            </Button>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}
