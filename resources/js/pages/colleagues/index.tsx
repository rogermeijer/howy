import { Head, Link, router } from '@inertiajs/react';
import { Search, UserPlus, Users } from 'lucide-react';
import { useState } from 'react';
import { WithHowy } from '@/components/brand/howy-name';
import { Pager } from '@/components/admin/pager';
import { PageHeader } from '@/components/cc/page-header';
import { AddColleagueDialog } from '@/components/colleagues/add-colleague-dialog';
import { ColleagueTabs } from '@/components/colleagues/colleague-tabs';
import {
    ColleagueStatusTag,
    useActivityLine,
    useStatusLooks,
} from '@/components/colleagues/colleague-status';
import {
    AccessTag,
    useAccessLooks,
} from '@/components/mailboxes/sender-access';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useFormatDate } from '@/hooks/use-format-date';
import { useAccountUpdates } from '@/hooks/use-account-updates';
import { useTranslations } from '@/hooks/use-translations';
import { ACCESSES } from '@/lib/mailbox-rules';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/colleagues';
import type {
    ColleagueRow,
    ColleagueStatus,
    MailboxOption,
    Paginated,
    SenderAccess,
} from '@/types';

type Filters = {
    q: string;
    status: ColleagueStatus | null;
    access: SenderAccess | null;
};

type Props = {
    colleagues: Paginated<ColleagueRow>;
    filters: Filters;
    total: number;
    mailboxes: MailboxOption[];
    isLive: boolean;
};

const STATUSES: ColleagueStatus[] = [
    'not_welcomed',
    'welcomed',
    'ignored',
    'opted_out',
    'off',
];

const rowGrid =
    'lg:grid lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_150px_minmax(0,1fr)_110px] lg:items-center lg:gap-5';

export default function Colleagues({
    colleagues,
    filters,
    total,
    mailboxes,
    isLive,
}: Props) {
    const t = useTranslations();
    const formatDate = useFormatDate();
    const statusLooks = useStatusLooks();
    const accessLooks = useAccessLooks();
    const activity = useActivityLine();
    const [adding, setAdding] = useState(false);

    // Someone new who mails Howy, or their latest mail, shows as it happens.
    useAccountUpdates('mail', ['colleagues', 'total']);
    const [query, setQuery] = useState(filters.q);

    const filter = (changes: Partial<Filters>) => {
        const next = { ...filters, q: query, ...changes };

        router.get(
            index.url({
                query: {
                    q: next.q || undefined,
                    status: next.status ?? undefined,
                    access: next.access ?? undefined,
                },
            }),
            {},
            { preserveState: true, preserveScroll: true },
        );
    };

    const filtered =
        filters.q !== '' || filters.status !== null || filters.access !== null;

    return (
        <>
            <Head title={t('Colleagues')} />

            <div className="flex flex-col gap-6">
                <PageHeader
                    title={t('Colleagues')}
                    description={
                        <WithHowy
                            text={t(
                                'Everyone who mails Howy: what Howy may do with their mail, where their Howy mails stand, and what they shared and asked.',
                            )}
                        />
                    }
                    actions={
                        <Button
                            className="h-11 px-[18px]"
                            onClick={() => setAdding(true)}
                        >
                            <UserPlus />
                            {t('Add colleague')}
                        </Button>
                    }
                />

                <ColleagueTabs current="colleagues" />

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
                                placeholder={t('Search by name or address')}
                                className="h-11 bg-cc-panel pl-10"
                            />
                        </label>
                    </form>

                    <Select
                        value={filters.status ?? 'all'}
                        onValueChange={(value) =>
                            filter({
                                status:
                                    value === 'all'
                                        ? null
                                        : (value as ColleagueStatus),
                            })
                        }
                    >
                        <SelectTrigger
                            aria-label={t('Howy mails')}
                            className="h-11 w-full bg-cc-panel sm:w-[210px]"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                {t('Every status')}
                            </SelectItem>
                            {STATUSES.map((status) => (
                                <SelectItem key={status} value={status}>
                                    {statusLooks[status].label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.access ?? 'all'}
                        onValueChange={(value) =>
                            filter({
                                access:
                                    value === 'all'
                                        ? null
                                        : (value as SenderAccess),
                            })
                        }
                    >
                        <SelectTrigger
                            aria-label={t('What Howy does')}
                            className="h-11 w-full bg-cc-panel sm:w-[210px]"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                {t('Every access')}
                            </SelectItem>
                            {ACCESSES.map((access) => (
                                <SelectItem key={access} value={access}>
                                    {accessLooks[access].label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {filtered && (
                        <Button
                            variant="ghost"
                            className="h-11 px-3 text-cc-subtle"
                            onClick={() => {
                                setQuery('');
                                router.get(
                                    index.url(),
                                    {},
                                    { preserveScroll: true },
                                );
                            }}
                        >
                            {t('Clear filters')}
                        </Button>
                    )}
                </div>

                {colleagues.data.length === 0 ? (
                    <div className="cc-panel flex flex-col items-center gap-3 px-6 py-[72px] text-center">
                        <div className="flex size-14 items-center justify-center rounded-2xl bg-cc-raised text-cc-subtle">
                            <Users className="size-7" strokeWidth={1.75} />
                        </div>
                        <p className="text-[15px] font-semibold">
                            {total === 0
                                ? t('No colleagues yet')
                                : t('No colleague matches these filters')}
                        </p>
                        {total === 0 && (
                            <p className="cc-caption max-w-[420px]">
                                <WithHowy
                                    text={t(
                                        'Whoever mails Howy from your own domain becomes a colleague. You can also add someone yourself.',
                                    )}
                                />
                            </p>
                        )}
                    </div>
                ) : (
                    <div className="cc-panel overflow-hidden">
                        <div
                            className={cn(
                                rowGrid,
                                'cc-label hidden border-b border-cc-border px-6 py-2.5 lg:grid',
                            )}
                        >
                            <div>{t('Colleague')}</div>
                            <div>{t('What Howy does')}</div>
                            <div>{t('Howy mails')}</div>
                            <div>{t('Activity')}</div>
                            <div className="text-right">{t('Last mail')}</div>
                        </div>

                        {colleagues.data.map((colleague) => (
                            <Link
                                key={colleague.id}
                                href={show(colleague.id)}
                                className={cn(
                                    rowGrid,
                                    'flex flex-col gap-2 border-b border-cc-border px-6 py-4 transition-colors last:border-b-0 hover:bg-cc-bg',
                                )}
                            >
                                <div className="flex min-w-0 flex-col gap-0.5">
                                    <span className="flex min-w-0 items-center gap-2">
                                        <span className="truncate text-[15px] font-medium">
                                            {colleague.name ?? colleague.email}
                                        </span>
                                        {colleague.isMember && (
                                            <span className="cc-tag cc-tag-action shrink-0">
                                                {t('Member')}
                                            </span>
                                        )}
                                    </span>
                                    {colleague.name !== null && (
                                        <span className="truncate text-[13.5px] text-cc-subtle">
                                            {colleague.email}
                                        </span>
                                    )}
                                </div>

                                <div className="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                                    <AccessTag access={colleague.access} />
                                    {colleague.accessFrom === 'own' && (
                                        <span className="text-[12.5px] text-cc-subtle">
                                            {t('own rule')}
                                        </span>
                                    )}
                                </div>

                                <div>
                                    <ColleagueStatusTag
                                        status={colleague.status}
                                    />
                                </div>

                                <div className="text-[13.5px] text-cc-muted">
                                    {activity(colleague)}
                                </div>

                                <div className="cc-caption lg:text-right">
                                    {colleague.lastMailAt
                                        ? formatDate(colleague.lastMailAt, {
                                              dateStyle: 'medium',
                                          })
                                        : t('Never')}
                                </div>
                            </Link>
                        ))}
                    </div>
                )}

                <Pager page={colleagues} />
            </div>

            <AddColleagueDialog
                open={adding}
                onOpenChange={setAdding}
                mailboxes={mailboxes}
                isLive={isLive}
            />
        </>
    );
}
