import { router } from '@inertiajs/react';
import { Download, Paperclip, Search, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import MailboxImportController from '@/actions/App/Http/Controllers/Mailboxes/MailboxImportController';
import { WithHowy } from '@/components/brand/howy-name';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import type {
    Mailbox,
    MailboxSearchResponse,
    MailboxSearchResult,
} from '@/types';

type Props = {
    mailboxes: Mailbox[];
    mailboxId: number | null;
    onOpenChange: (open: boolean) => void;
};

type Range = '7d' | '30d' | null;

export function ImportMailboxDialog({
    mailboxes,
    mailboxId,
    onOpenChange,
}: Props) {
    const t = useTranslations();
    const formatDate = useFormatDate();

    const [selectedMailbox, setSelectedMailbox] = useState<number | null>(
        mailboxId,
    );
    const [query, setQuery] = useState('');
    const [range, setRange] = useState<Range>('30d');
    const [attachments, setAttachments] = useState(false);
    const [results, setResults] = useState<MailboxSearchResult[]>([]);
    const [estimate, setEstimate] = useState(0);
    const [nextPageToken, setNextPageToken] = useState<string | null>(null);
    const [selected, setSelected] = useState<Set<string>>(new Set());
    const [wholeThreads, setWholeThreads] = useState(true);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);

    const search = useCallback(
        async (pageToken: string | null = null) => {
            if (selectedMailbox === null) {
                return;
            }

            setLoading(true);
            setError(null);

            const url = MailboxImportController.search.url(selectedMailbox, {
                query: {
                    q: query || undefined,
                    range: range ?? undefined,
                    attachments: attachments ? 1 : undefined,
                    page_token: pageToken ?? undefined,
                },
            });

            try {
                const response = await fetch(url, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error(String(response.status));
                }

                const data = (await response.json()) as MailboxSearchResponse;

                setResults((current) =>
                    pageToken ? [...current, ...data.messages] : data.messages,
                );
                setEstimate(data.estimate);
                setNextPageToken(data.nextPageToken);

                if (!pageToken) {
                    setSelected(new Set());
                }
            } catch {
                setError(t('Searching the mailbox failed. Please try again.'));
            } finally {
                setLoading(false);
            }
        },
        [selectedMailbox, query, range, attachments, t],
    );

    useEffect(() => {
        setSelectedMailbox(mailboxId);
    }, [mailboxId]);

    // Search straight away on open, and whenever a chip or the mailbox changes.
    useEffect(() => {
        if (selectedMailbox !== null) {
            void search();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selectedMailbox, range, attachments]);

    const selectable = results.filter((message) => !message.alreadyImported);
    const allSelected =
        selectable.length > 0 && selectable.every((m) => selected.has(m.id));

    const toggle = (id: string) =>
        setSelected((current) => {
            const next = new Set(current);

            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });

    const toggleAll = () =>
        setSelected(
            allSelected ? new Set() : new Set(selectable.map((m) => m.id)),
        );

    const submit = () => {
        if (selectedMailbox === null || selected.size === 0) {
            return;
        }

        router.post(
            MailboxImportController.store.url(selectedMailbox),
            { message_ids: [...selected], whole_threads: wholeThreads },
            {
                preserveScroll: true,
                onStart: () => setSubmitting(true),
                onFinish: () => setSubmitting(false),
                onSuccess: () => onOpenChange(false),
            },
        );
    };

    const onSearch = (event: FormEvent) => {
        event.preventDefault();
        void search();
    };

    return (
        <Dialog open={mailboxId !== null} onOpenChange={onOpenChange}>
            <DialogContent
                showCloseButton={false}
                className="flex h-[min(880px,calc(100svh-2rem))] flex-col gap-0 overflow-hidden rounded-2xl bg-cc-panel p-0 sm:max-w-[880px]"
            >
                <div className="flex flex-col gap-[18px] border-b border-cc-border px-7 pt-7 pb-5">
                    <div className="flex items-start justify-between gap-4">
                        <div className="flex flex-col gap-1">
                            <DialogTitle className="cc-subtitle">
                                {t('Import emails')}
                            </DialogTitle>
                            <DialogDescription className="cc-caption">
                                <WithHowy
                                    text={t(
                                        'Search the mailbox and choose which emails Howy should process.',
                                    )}
                                />
                            </DialogDescription>
                        </div>
                        <div className="flex items-center gap-2.5">
                            {mailboxes.length > 1 && (
                                <label className="flex h-10 items-center rounded-[10px] border-[1.5px] border-cc-border-strong px-3">
                                    <span className="sr-only">
                                        {t('Mailbox')}
                                    </span>
                                    <select
                                        value={selectedMailbox ?? ''}
                                        onChange={(e) =>
                                            setSelectedMailbox(
                                                Number(e.target.value),
                                            )
                                        }
                                        className="bg-transparent text-[13px] font-semibold outline-none"
                                    >
                                        {mailboxes.map((mailbox) => (
                                            <option
                                                key={mailbox.id}
                                                value={mailbox.id}
                                            >
                                                {mailbox.emailAddress}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            )}
                            <DialogClose asChild>
                                <button
                                    type="button"
                                    aria-label={t('Close')}
                                    className="flex size-9 cursor-pointer items-center justify-center rounded-lg bg-cc-raised text-cc-ink"
                                >
                                    <X className="size-4" strokeWidth={2.25} />
                                </button>
                            </DialogClose>
                        </div>
                    </div>

                    <form onSubmit={onSearch} className="flex gap-2.5">
                        <label className="flex h-11 flex-1 items-center gap-2.5 rounded-[10px] border-[1.5px] border-cc-border-strong bg-cc-panel px-3.5 focus-within:border-cc-ink">
                            <Search className="size-4 shrink-0 text-cc-subtle" />
                            <span className="sr-only">
                                {t('Search the mailbox')}
                            </span>
                            <input
                                type="search"
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="from:example.com invoice"
                                className="min-w-0 flex-1 bg-transparent font-mono text-[14px] outline-none placeholder:text-cc-faint"
                            />
                        </label>
                        <Button type="submit" className="h-11 px-5">
                            {t('Search')}
                        </Button>
                    </form>

                    <div className="flex flex-wrap items-center gap-2">
                        <Chip
                            active={range === '7d'}
                            onClick={() =>
                                setRange(range === '7d' ? null : '7d')
                            }
                        >
                            {t('Last 7 days')}
                        </Chip>
                        <Chip
                            active={range === '30d'}
                            onClick={() =>
                                setRange(range === '30d' ? null : '30d')
                            }
                        >
                            {t('Last 30 days')}
                        </Chip>
                        <Chip
                            active={attachments}
                            onClick={() => setAttachments(!attachments)}
                        >
                            {t('With attachment')}
                        </Chip>
                        <span className="flex-1" />
                        <span className="text-[12px] text-cc-faint">
                            {t('Gmail search terms work:')}{' '}
                            <span className="font-mono">
                                from: subject: after:
                            </span>
                        </span>
                    </div>
                </div>

                <div className="flex items-center gap-3 border-b border-cc-border bg-cc-bg px-7 py-3">
                    <label className="flex cursor-pointer items-center gap-2.5 text-[13px] font-semibold">
                        <Checkbox
                            checked={allSelected}
                            onCheckedChange={toggleAll}
                            disabled={selectable.length === 0}
                            className="size-[18px] border-cc-border-strong"
                        />
                        {t('Select all · :count results', { count: estimate })}
                    </label>
                    <span className="flex-1" />
                    <label className="flex cursor-pointer items-center gap-2.5 text-[13px] font-medium">
                        <Checkbox
                            checked={wholeThreads}
                            onCheckedChange={(value) =>
                                setWholeThreads(value === true)
                            }
                            className="size-[18px] border-cc-border-strong"
                        />
                        {t('Import whole threads')}
                    </label>
                </div>

                <div className="min-h-0 flex-1 overflow-y-auto">
                    {results.map((message) => (
                        <label
                            key={message.id}
                            className={cn(
                                'grid grid-cols-[18px_minmax(0,1fr)_72px] items-center gap-4 border-b border-cc-border px-7 py-3.5 sm:grid-cols-[18px_200px_minmax(0,1fr)_80px]',
                                message.alreadyImported
                                    ? 'text-cc-faint'
                                    : 'cursor-pointer hover:bg-cc-bg',
                                selected.has(message.id) && 'bg-[#fbfdf5]',
                            )}
                        >
                            <Checkbox
                                checked={
                                    message.alreadyImported ||
                                    selected.has(message.id)
                                }
                                disabled={message.alreadyImported}
                                onCheckedChange={() => toggle(message.id)}
                                className="size-[18px] border-cc-border-strong"
                            />
                            <span className="hidden min-w-0 flex-col gap-0.5 sm:flex">
                                <span className="truncate text-[14px] font-semibold">
                                    {message.fromName ?? message.fromEmail}
                                </span>
                                {message.fromName && (
                                    <span className="truncate text-[12px] text-cc-subtle">
                                        {message.fromEmail}
                                    </span>
                                )}
                            </span>
                            <span className="flex min-w-0 flex-col gap-0.5">
                                <span className="flex min-w-0 items-center gap-2.5">
                                    <span className="truncate text-[14px] font-semibold">
                                        {message.subject || t('(no subject)')}
                                    </span>
                                    {message.alreadyImported && (
                                        <span className="cc-tag cc-tag-noise shrink-0 before:hidden">
                                            {t('Already imported')}
                                        </span>
                                    )}
                                </span>
                                {!message.alreadyImported && (
                                    <span className="truncate text-[13px] text-cc-muted">
                                        {message.snippet}
                                    </span>
                                )}
                            </span>
                            <span className="flex items-center justify-end gap-2 text-[13px] text-cc-subtle">
                                {message.hasAttachments && (
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
                        </label>
                    ))}

                    {loading && (
                        <div className="flex justify-center p-6">
                            <Spinner />
                        </div>
                    )}

                    {!loading && error && (
                        <p className="px-7 py-12 text-center text-[14px] text-cc-action-fg">
                            {error}
                        </p>
                    )}

                    {!loading && !error && results.length === 0 && (
                        <p className="px-7 py-12 text-center text-[14px] text-cc-subtle">
                            {t('No emails match this search.')}
                        </p>
                    )}

                    {!loading && nextPageToken && (
                        <div className="flex justify-center p-4">
                            <Button
                                variant="outline"
                                className="h-9"
                                onClick={() => void search(nextPageToken)}
                            >
                                {t('Load more')}
                            </Button>
                        </div>
                    )}
                </div>

                <div className="flex flex-wrap items-center gap-3 border-t border-cc-border bg-cc-panel px-7 py-4">
                    <span className="text-[14px] font-semibold">
                        {t(':count selected', { count: selected.size })}
                    </span>
                    <span className="flex-1" />
                    <DialogClose asChild>
                        <Button variant="outline" className="h-11 px-[18px]">
                            {t('Cancel')}
                        </Button>
                    </DialogClose>
                    <Button
                        className="h-11 px-[18px]"
                        disabled={selected.size === 0 || submitting}
                        onClick={submit}
                    >
                        <Download />
                        {t('Import :count emails', { count: selected.size })}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}

function Chip({
    active,
    onClick,
    children,
}: {
    active: boolean;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            aria-pressed={active}
            onClick={onClick}
            className={cn('cc-pill h-8 px-3', active && 'cc-pill-active')}
        >
            {children}
        </button>
    );
}
