import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Search, UserPlus, Users } from 'lucide-react';
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
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
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
    /** How many have no mailbox for their Howy mails: theirs was disconnected. */
    withoutMailbox: number;
    mailboxes: MailboxOption[];
    isLive: boolean;
};

const STATUSES: ColleagueStatus[] = [
    'not_welcomed',
    'welcomed',
    'ignored',
    'no_mailbox',
    'opted_out',
    'off',
];

const rowGrid =
    'lg:grid lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_150px_minmax(0,1fr)_110px] lg:items-center lg:gap-5';

export default function Colleagues({
    colleagues,
    filters,
    total,
    withoutMailbox,
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
    useAccountUpdates('mail', ['colleagues', 'total', 'withoutMailbox']);
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

                {withoutMailbox > 0 && filters.status !== 'no_mailbox' && (
                    <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2.5 rounded-xl bg-cc-pending-bg px-4 py-3 text-[13.5px] leading-[1.5] text-cc-pending-fg">
                        <p className="flex min-w-0 flex-[1_1_420px] gap-2">
                            <AlertTriangle className="mt-[3px] size-4 shrink-0" />
                            <span>
                                <WithHowy
                                    text={
                                        withoutMailbox === 1
                                            ? t(
                                                  '1 colleague has no mailbox for their Howy mails: theirs was disconnected. Choose another on their page, or Howy takes the one they mail next.',
                                              )
                                            : t(
                                                  ':count colleagues have no mailbox for their Howy mails: theirs was disconnected. Choose another on their page, or Howy takes the one they mail next.',
                                                  { count: withoutMailbox },
                                              )
                                    }
                                />
                            </span>
                        </p>
                        <Button
                            variant="outline"
                            className="h-9 shrink-0 bg-cc-panel px-3.5 text-[13px]"
                            onClick={() => filter({ status: 'no_mailbox' })}
                        >
                            {withoutMailbox === 1
                                ? t('Show them')
                                : t('Show all :count', {
                                      count: withoutMailbox,
                                  })}
                        </Button>
                    </div>
                )}

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
                                {/* One line, so every row is as high: the
                                    address of someone with a name on hover. */}
                                <div className="flex min-w-0 items-center gap-2">
                                    {colleague.name !== null ? (
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <span className="truncate text-[15px] font-medium">
                                                    {colleague.name}
                                                </span>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                {colleague.email}
                                            </TooltipContent>
                                        </Tooltip>
                                    ) : (
                                        <span className="truncate text-[15px] font-medium">
                                            {colleague.email}
                                        </span>
                                    )}
                                    {colleague.isMember && (
                                        <span className="cc-tag cc-tag-action shrink-0">
                                            {t('Member')}
                                        </span>
                                    )}
                                </div>

                                <div className="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                                    {/* Where it comes from, on hover. */}
                                    {colleague.homeMailbox === null ? (
                                        // No mailbox, so no rule applies.
                                        <>
                                            <span
                                                aria-hidden="true"
                                                className="text-cc-faint"
                                            >
                                                –
                                            </span>
                                            <span className="sr-only">
                                                {t('No mailbox')}
                                            </span>
                                        </>
                                    ) : (
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <AccessTag
                                                    access={colleague.access}
                                                />
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <WithHowy
                                                    text={
                                                        colleague.accessFrom ===
                                                        'own'
                                                            ? t(
                                                                  'Their own rule',
                                                              )
                                                            : colleague.accessFrom ===
                                                                'domain'
                                                              ? t(
                                                                    'Through the rule for :domain',
                                                                    {
                                                                        domain:
                                                                            colleague.email.split(
                                                                                '@',
                                                                            )[1] ??
                                                                            '',
                                                                    },
                                                                )
                                                              : t(
                                                                    'No rule covers them, so Howy ignores their mail',
                                                                )
                                                    }
                                                />
                                            </TooltipContent>
                                        </Tooltip>
                                    )}
                                </div>

                                <div>
                                    <ColleagueStatusTag
                                        status={colleague.status}
                                    />
                                </div>

                                <div className="text-[13.5px] text-cc-muted">
                                    {colleague.emailsCount === 0 ? (
                                        <>
                                            <span aria-hidden="true">–</span>
                                            <span className="sr-only">
                                                {activity(colleague)}
                                            </span>
                                        </>
                                    ) : (
                                        activity(colleague)
                                    )}
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
