import { Head, router } from '@inertiajs/react';
import { Mail, Search } from 'lucide-react';
import { useState } from 'react';
import { WithHowy } from '@/components/brand/howy-name';
import { Pager } from '@/components/admin/pager';
import { PageHeader } from '@/components/cc/page-header';
import { ColleagueTabs } from '@/components/colleagues/colleague-tabs';
import { SentMailList } from '@/components/colleagues/sent-mail-list';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useAccountUpdates } from '@/hooks/use-account-updates';
import { useTranslations } from '@/hooks/use-translations';
import { sent } from '@/routes/colleagues';
import type { Option, Paginated, SentMail } from '@/types';

type Filters = {
    q: string;
    kind: string | null;
    status: string | null;
    from: string | null;
    until: string | null;
};

type Props = {
    mails: Paginated<SentMail>;
    filters: Filters;
    kinds: Option[];
    statuses: Option[];
};

export default function SentMails({ mails, filters, kinds, statuses }: Props) {
    const t = useTranslations();

    // A mail logged, sent, held back or failed shows as it happens.
    useAccountUpdates('sent-mails', ['mails']);
    const [query, setQuery] = useState(filters.q);

    const filter = (changes: Partial<Filters>) => {
        const next = { ...filters, q: query, ...changes };

        router.get(
            sent.url({
                query: {
                    q: next.q || undefined,
                    kind: next.kind ?? undefined,
                    status: next.status ?? undefined,
                    from: next.from ?? undefined,
                    until: next.until ?? undefined,
                },
            }),
            {},
            { preserveState: true, preserveScroll: true },
        );
    };

    const filtered = Object.values({ ...filters, q: filters.q || null }).some(
        (value) => value !== null,
    );

    const choice = (
        label: string,
        value: string | null,
        options: Option[],
        every: string,
        onChange: (value: string | null) => void,
    ) => (
        <Select
            value={value ?? 'all'}
            onValueChange={(picked) =>
                onChange(picked === 'all' ? null : picked)
            }
        >
            <SelectTrigger
                aria-label={label}
                className="h-11 w-full bg-cc-panel sm:w-[200px]"
            >
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">{every}</SelectItem>
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );

    return (
        <>
            <Head title={t('Sent mails')} />

            <div className="flex flex-col gap-6">
                <PageHeader
                    title={t('Colleagues')}
                    description={
                        <WithHowy
                            text={t(
                                'Every mail Howy sent from your mailboxes, or held back: to whom, whether it went, and whether anyone wrote back.',
                            )}
                        />
                    }
                />

                <ColleagueTabs current="sent" />

                <div className="flex flex-wrap items-center gap-2.5">
                    <form
                        className="w-full sm:max-w-xs"
                        onSubmit={(event) => {
                            event.preventDefault();
                            filter({ q: query });
                        }}
                    >
                        <label className="relative block">
                            <span className="sr-only">{t('Search')}</span>
                            <Search
                                aria-hidden="true"
                                className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-cc-subtle"
                            />
                            <Input
                                type="search"
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                                placeholder={t('Search by recipient')}
                                className="h-11 bg-cc-panel pl-10"
                            />
                        </label>
                    </form>

                    {choice(
                        t('Kind'),
                        filters.kind,
                        kinds,
                        t('Every kind'),
                        (kind) => filter({ kind }),
                    )}
                    {choice(
                        t('Status'),
                        filters.status,
                        statuses,
                        t('Every status'),
                        (status) => filter({ status }),
                    )}

                    <div className="flex flex-wrap items-center gap-2.5">
                        <label className="flex items-center gap-2 text-[13px] text-cc-subtle">
                            {t('From')}
                            <Input
                                type="date"
                                value={filters.from ?? ''}
                                onChange={(event) =>
                                    filter({ from: event.target.value || null })
                                }
                                className="h-11 w-[150px] bg-cc-panel"
                            />
                        </label>
                        <label className="flex items-center gap-2 text-[13px] text-cc-subtle">
                            {t('Until')}
                            <Input
                                type="date"
                                value={filters.until ?? ''}
                                onChange={(event) =>
                                    filter({
                                        until: event.target.value || null,
                                    })
                                }
                                className="h-11 w-[150px] bg-cc-panel"
                            />
                        </label>
                    </div>

                    {filtered && (
                        <Button
                            variant="ghost"
                            className="h-11 px-3 text-cc-subtle"
                            onClick={() => {
                                setQuery('');
                                router.get(
                                    sent.url(),
                                    {},
                                    { preserveScroll: true },
                                );
                            }}
                        >
                            {t('Clear filters')}
                        </Button>
                    )}
                </div>

                {mails.data.length === 0 ? (
                    <div className="cc-panel flex flex-col items-center gap-3 px-6 py-[72px] text-center">
                        <div className="flex size-14 items-center justify-center rounded-2xl bg-cc-raised text-cc-subtle">
                            <Mail className="size-7" strokeWidth={1.75} />
                        </div>
                        <p className="text-[15px] font-semibold">
                            {filtered
                                ? t('No mail matches these filters')
                                : t('Howy has not sent anything yet')}
                        </p>
                    </div>
                ) : (
                    <div className="cc-panel overflow-hidden">
                        <SentMailList mails={mails.data} />
                    </div>
                )}

                <Pager page={mails} />
            </div>
        </>
    );
}
