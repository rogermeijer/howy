import { Head, Link } from '@inertiajs/react';
import { ChevronLeft, FileText, Paperclip, Quote } from 'lucide-react';
import { Fragment, useState } from 'react';
import { CcReply, hasCcReply } from '@/components/mail/cc-reply';
import { InterpretationPanel } from '@/components/mail/interpretation-panel';
import { InterpretationTag } from '@/components/mail/interpretation-tag';
import { useFormatDate } from '@/hooks/use-format-date';
import { useInitials } from '@/hooks/use-initials';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { inbox } from '@/routes';
import type { Interpretation } from '@/types';

type Address = { name: string | null; email: string };

type QuotedMessage = {
    name: string | null;
    date: string | null;
    dateText: string | null;
    text: string;
    messageId: number | null;
};

type Message = {
    id: number;
    fromName: string | null;
    fromEmail: string | null;
    to: Address[];
    cc: Address[];
    receivedAt: string | null;
    contentHtml: string | null;
    contentText: string | null;
    excerpt: string;
    attachments: { filename: string; size: number }[];
    quotes: QuotedMessage[];
    interpretation: Interpretation | null;
};

type Props = {
    currentId: number;
    canReview: boolean;
    subject: string | null;
    mailbox: string | null;
    participants: string[];
    messages: Message[];
};

const kilobytes = (bytes: number) =>
    bytes >= 1024 * 1024
        ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;

const anchor = (id: number) => `message-${id}`;

const toggled = (set: Set<number>, id: number, on?: boolean) => {
    const next = new Set(set);

    if (on ?? !next.has(id)) {
        next.add(id);
    } else {
        next.delete(id);
    }

    return next;
};

export default function EmailShow({
    currentId,
    canReview,
    subject,
    mailbox,
    participants,
    messages,
}: Props) {
    const t = useTranslations();
    const title = subject || t('(no subject)');

    // Newest first. The newest message and the one that was opened start
    // expanded; everything older is one line until clicked.
    const [expanded, setExpanded] = useState<Set<number>>(
        () => new Set([messages[0]?.id, currentId]),
    );
    const [quotesOpen, setQuotesOpen] = useState<Set<number>>(new Set());

    // The panel shows one message's interpretation: the opened one, else one
    // waiting for review, else the newest. Each message can switch it.
    const interpretedMessages = messages.filter(
        (message) => message.interpretation,
    );
    const [selectedId, setSelectedId] = useState<number | null>(
        () =>
            (
                interpretedMessages.find(
                    (message) => message.id === currentId,
                ) ??
                interpretedMessages.find(
                    (message) => message.interpretation?.needsReview,
                ) ??
                interpretedMessages[0]
            )?.id ?? null,
    );
    const interpreted = interpretedMessages.find(
        (message) => message.id === selectedId,
    );

    const jumpTo = (id: number) => {
        setExpanded((current) => toggled(current, id, true));
        requestAnimationFrame(() =>
            document
                .getElementById(anchor(id))
                ?.scrollIntoView({ behavior: 'smooth', block: 'start' }),
        );
    };

    const meta = [
        messages.length === 1
            ? t('1 message')
            : t(':count messages', { count: messages.length }),
        participants.join(', '),
        mailbox,
    ].filter(Boolean);

    return (
        <>
            <Head title={title} />

            <div className="flex flex-col gap-5">
                <div className="cc-caption flex items-center gap-2">
                    <Link
                        href={inbox()}
                        className="inline-flex items-center gap-1.5 text-cc-subtle transition-colors hover:text-cc-ink"
                    >
                        <ChevronLeft className="size-3.5" />
                        {t('Inbox')}
                    </Link>
                    <span>/</span>
                    <span className="truncate font-medium text-cc-ink">
                        {title}
                    </span>
                </div>

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_400px]">
                    <div className="flex min-w-0 flex-col gap-4">
                        <div className="flex flex-wrap items-end justify-between gap-4">
                            <div className="flex min-w-0 flex-col gap-1.5">
                                <h1 className="text-[26px] leading-[1.2] font-semibold tracking-[-0.02em]">
                                    {title}
                                </h1>
                                <p className="cc-caption">{meta.join(' · ')}</p>
                            </div>
                            {messages.length > 1 && (
                                <span className="text-[12px] whitespace-nowrap text-cc-faint">
                                    {t('Newest first')}
                                </span>
                            )}
                        </div>

                        <ol className="cc-panel flex flex-col overflow-hidden">
                            {messages.map((message) => (
                                <Fragment key={message.id}>
                                    {message.interpretation &&
                                        hasCcReply(message.interpretation) && (
                                            <CcReply
                                                messageId={message.id}
                                                interpretation={
                                                    message.interpretation
                                                }
                                            />
                                        )}
                                    {expanded.has(message.id) ? (
                                        <OpenMessage
                                            key={message.id}
                                            message={message}
                                            quotesOpen={quotesOpen.has(
                                                message.id,
                                            )}
                                            onCollapse={() =>
                                                setExpanded((current) =>
                                                    toggled(
                                                        current,
                                                        message.id,
                                                        false,
                                                    ),
                                                )
                                            }
                                            onToggleQuotes={() =>
                                                setQuotesOpen((current) =>
                                                    toggled(
                                                        current,
                                                        message.id,
                                                    ),
                                                )
                                            }
                                            onJump={jumpTo}
                                            selectable={
                                                interpretedMessages.length > 1
                                            }
                                            selected={message.id === selectedId}
                                            onSelect={() =>
                                                setSelectedId(message.id)
                                            }
                                        />
                                    ) : (
                                        <ClosedMessage
                                            key={message.id}
                                            message={message}
                                            onOpen={() =>
                                                setExpanded((current) =>
                                                    toggled(
                                                        current,
                                                        message.id,
                                                        true,
                                                    ),
                                                )
                                            }
                                        />
                                    )}
                                </Fragment>
                            ))}
                        </ol>
                    </div>

                    <InterpretationPanel
                        interpretation={interpreted?.interpretation ?? null}
                        emailId={interpreted?.id ?? null}
                        canReview={canReview}
                        about={
                            interpreted && messages.length > 1
                                ? t('Email from :sender', {
                                      sender:
                                          interpreted.fromName ??
                                          interpreted.fromEmail ??
                                          '',
                                  })
                                : null
                        }
                    />
                </div>
            </div>
        </>
    );
}

function Avatar({ name, small = false }: { name: string; small?: boolean }) {
    const initials = useInitials();

    return (
        <span
            className={cn(
                'flex shrink-0 items-center justify-center rounded-full bg-cc-raised font-semibold text-cc-ink',
                small ? 'size-8 text-[12px]' : 'size-10 text-[13px]',
            )}
        >
            {initials(name)}
        </span>
    );
}

function OpenMessage({
    message,
    quotesOpen,
    onCollapse,
    onToggleQuotes,
    onJump,
    selectable,
    selected,
    onSelect,
}: {
    message: Message;
    quotesOpen: boolean;
    onCollapse: () => void;
    onToggleQuotes: () => void;
    onJump: (id: number) => void;
    /** More than one message in the thread was interpreted: each can be picked. */
    selectable: boolean;
    selected: boolean;
    onSelect: () => void;
}) {
    const t = useTranslations();
    const formatDate = useFormatDate();

    const sender = message.fromName ?? message.fromEmail ?? '';
    const list = (addresses: Address[]) =>
        addresses.map((address) => address.email).join(', ');
    const recipients = [
        message.to.length > 0 &&
            t('to :recipients', { recipients: list(message.to) }),
        message.cc.length > 0 &&
            t('cc :recipients', { recipients: list(message.cc) }),
    ].filter(Boolean);

    const count = message.quotes.length;

    return (
        <li
            id={anchor(message.id)}
            className="scroll-mt-24 border-b border-cc-border last:border-b-0"
        >
            <button
                type="button"
                onClick={onCollapse}
                aria-expanded
                className="flex w-full cursor-pointer items-center gap-3.5 px-7 pt-5 pb-3 text-left"
            >
                <Avatar name={sender} />
                <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                    <span className="truncate text-[15px] font-semibold">
                        {sender}{' '}
                        {message.fromName && (
                            <span className="font-normal text-cc-subtle">
                                {message.fromEmail}
                            </span>
                        )}
                    </span>
                    {recipients.length > 0 && (
                        <span className="cc-caption truncate">
                            {recipients.join(' · ')}
                        </span>
                    )}
                </span>
                {message.receivedAt && (
                    <span className="cc-caption shrink-0">
                        {formatDate(message.receivedAt, {
                            dateStyle: 'medium',
                            timeStyle: 'short',
                        })}
                    </span>
                )}
            </button>

            <div className="flex flex-col gap-3.5 px-7 pb-6 sm:pl-[82px]">
                {selectable && message.interpretation && (
                    <button
                        type="button"
                        onClick={onSelect}
                        aria-pressed={selected}
                        className={cn(
                            'flex min-h-10 cursor-pointer items-center gap-2.5 self-start rounded-[10px] border-[1.5px] px-3 text-[13px] font-medium transition-colors',
                            selected
                                ? 'border-cc-ink text-cc-ink'
                                : 'border-cc-border-strong text-cc-muted hover:border-cc-ink hover:text-cc-ink',
                        )}
                    >
                        <InterpretationTag
                            interpretation={message.interpretation}
                        />
                        {selected
                            ? t('Shown alongside')
                            : t('Show interpretation')}
                    </button>
                )}
                {message.contentHtml ? (
                    <div
                        className="cc-email-body"
                        // Sanitised on the server: no scripts, styles, handlers or images.
                        dangerouslySetInnerHTML={{
                            __html: message.contentHtml,
                        }}
                    />
                ) : (
                    <div className="cc-email-body whitespace-pre-line">
                        {message.contentText ??
                            t('This email has no plain-text body.')}
                    </div>
                )}

                {message.attachments.length > 0 && (
                    <ul className="flex flex-wrap gap-2 pt-1">
                        {message.attachments.map((attachment) => (
                            <li
                                key={attachment.filename}
                                className="flex items-center gap-2.5 rounded-[10px] border-[1.5px] border-cc-border px-3.5 py-2 text-[13px]"
                            >
                                <FileText className="size-4 text-cc-subtle" />
                                <span className="font-semibold">
                                    {attachment.filename}
                                </span>
                                <span className="text-cc-faint">
                                    {kilobytes(attachment.size)}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}

                {count > 0 &&
                    (quotesOpen ? (
                        <div className="flex flex-col gap-2.5 pt-1">
                            <div className="flex items-center justify-between gap-3">
                                <span className="cc-label">
                                    {count === 1
                                        ? t('Quoted · 1 earlier message')
                                        : t(
                                              'Quoted · :count earlier messages',
                                              { count },
                                          )}
                                </span>
                                <button
                                    type="button"
                                    onClick={onToggleQuotes}
                                    aria-expanded
                                    className="cursor-pointer py-1 text-[13px] font-semibold underline underline-offset-[3px]"
                                >
                                    {t('Hide')}
                                </button>
                            </div>
                            {message.quotes.map((quote, index) => (
                                <blockquote
                                    key={index}
                                    className="flex flex-col gap-2 rounded-xl bg-cc-bg px-4 py-3.5"
                                >
                                    <div className="flex items-center gap-2.5">
                                        <Quote className="size-3.5 shrink-0 fill-cc-border-strong text-cc-border-strong" />
                                        <span className="min-w-0 flex-1 truncate text-[13px]">
                                            <span className="font-semibold">
                                                {quote.name ??
                                                    t('Earlier message')}
                                            </span>
                                            {(quote.date || quote.dateText) && (
                                                <span className="text-cc-subtle">
                                                    {' · '}
                                                    {quote.date
                                                        ? formatDate(
                                                              quote.date,
                                                              {
                                                                  dateStyle:
                                                                      'medium',
                                                                  timeStyle:
                                                                      'short',
                                                              },
                                                          )
                                                        : quote.dateText}
                                                </span>
                                            )}
                                        </span>
                                        {quote.messageId !== null && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    onJump(quote.messageId!)
                                                }
                                                className="shrink-0 cursor-pointer text-[12px] font-semibold text-cc-muted hover:text-cc-ink"
                                            >
                                                {t('In this conversation')} ↓
                                            </button>
                                        )}
                                    </div>
                                    <p className="text-[14px] leading-[1.6] whitespace-pre-line text-cc-muted">
                                        {quote.text}
                                    </p>
                                </blockquote>
                            ))}
                        </div>
                    ) : (
                        <button
                            type="button"
                            onClick={onToggleQuotes}
                            aria-expanded={false}
                            aria-label={
                                count === 1
                                    ? t('Show quoted text (1 earlier message)')
                                    : t(
                                          'Show quoted text (:count earlier messages)',
                                          { count },
                                      )
                            }
                            title={t('Show quoted text')}
                            className="flex h-6 w-11 cursor-pointer items-center justify-center gap-[3px] self-start rounded-full border-[1.5px] border-cc-border bg-cc-bg text-cc-muted transition-colors hover:border-cc-border-strong hover:text-cc-ink"
                        >
                            <span className="size-1 rounded-full bg-current" />
                            <span className="size-1 rounded-full bg-current" />
                            <span className="size-1 rounded-full bg-current" />
                        </button>
                    ))}
            </div>
        </li>
    );
}

function ClosedMessage({
    message,
    onOpen,
}: {
    message: Message;
    onOpen: () => void;
}) {
    const t = useTranslations();
    const formatDate = useFormatDate();
    const sender = message.fromName ?? message.fromEmail ?? '';

    return (
        <li
            id={anchor(message.id)}
            className="scroll-mt-24 border-b border-cc-border last:border-b-0"
        >
            <button
                type="button"
                onClick={onOpen}
                aria-expanded={false}
                className="grid w-full cursor-pointer grid-cols-[40px_minmax(0,1fr)_auto] items-center gap-3.5 px-7 py-3.5 text-left transition-colors hover:bg-cc-bg sm:grid-cols-[40px_160px_minmax(0,1fr)_auto]"
            >
                <span className="flex justify-center">
                    <Avatar name={sender} small />
                </span>
                <span className="truncate text-[14px] font-semibold">
                    {sender}
                </span>
                <span className="hidden truncate text-[14px] text-cc-muted sm:block">
                    {message.excerpt}
                </span>
                <span className="cc-caption flex items-center gap-2 whitespace-nowrap">
                    {message.attachments.length > 0 && (
                        <Paperclip
                            className="size-3.5"
                            aria-label={t('Attachment')}
                        />
                    )}
                    {message.receivedAt &&
                        formatDate(message.receivedAt, {
                            dateStyle: undefined,
                            day: 'numeric',
                            month: 'short',
                        })}
                </span>
            </button>
        </li>
    );
}
