import { Link, router } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { X } from 'lucide-react';
import { AdminShell, Chip } from '@/components/admin/admin-shell';
import { BreakdownTable } from '@/components/admin/breakdown-table';
import { DailyCostChart } from '@/components/admin/daily-cost-chart';
import {
    formatCompact,
    formatCost,
    formatLatency,
    formatNumber,
} from '@/components/admin/format';
import { Pager } from '@/components/admin/pager';
import { Stat } from '@/components/admin/stat';
import { DatePickerField } from '@/components/date-picker-field';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslations } from '@/hooks/use-translations';
import { index, show } from '@/routes/admin/ai';
import type {
    AiBreakdownRow,
    AiCall,
    AiDay,
    AiFilters,
    AiTotals,
    Paginated,
} from '@/types';

type Props = {
    filters: AiFilters;
    options: {
        accounts: { id: number; name: string }[];
        steps: string[];
        models: string[];
        agents: string[];
        subject_types: string[];
    };
    totals: AiTotals;
    daily: AiDay[];
    breakdowns: Record<
        'account' | 'step' | 'model' | 'agent' | 'thread' | 'subject',
        AiBreakdownRow[]
    >;
    calls: Paginated<AiCall>;
    retentionDays: number;
};

/** A Radix select cannot hold an empty value, so "all" travels as this. */
const ALL = '__all__';

const callGrid =
    'lg:grid lg:grid-cols-[110px_minmax(0,1fr)_minmax(0,1.3fr)_150px_90px_80px] lg:items-center lg:gap-4';

export default function AdminAiUsage({
    filters,
    options,
    totals,
    daily,
    breakdowns,
    calls,
    retentionDays,
}: Props) {
    const t = useTranslations();
    const formatDate = useFormatDate();

    const selects: {
        name: keyof AiFilters;
        label: string;
        values: { value: string; label: string }[];
    }[] = [
        {
            name: 'account',
            label: t('Account'),
            values: options.accounts.map((a) => ({
                value: String(a.id),
                label: a.name,
            })),
        },
        {
            name: 'step',
            label: t('Step'),
            values: options.steps.map((v) => ({ value: v, label: v })),
        },
        {
            name: 'agent',
            label: t('Agent'),
            values: options.agents.map((v) => ({ value: v, label: v })),
        },
        {
            name: 'model',
            label: t('Model'),
            values: options.models.map((v) => ({ value: v, label: v })),
        },
        {
            name: 'subject_type',
            label: t('Subject'),
            values: options.subject_types.map((v) => ({ value: v, label: v })),
        },
        {
            name: 'status',
            label: t('Status'),
            values: [
                { value: 'succeeded', label: t('Succeeded') },
                { value: 'failed', label: t('Failed') },
            ],
        },
    ];

    // Empty and "all" drop out of the URL; a pinned thread or subject stays.
    const applyFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const query: Record<string, string | number> = {};

        for (const [key, value] of new FormData(event.currentTarget)) {
            if (typeof value === 'string' && value !== '' && value !== ALL) {
                query[key] = value;
            }
        }

        if (filters.thread) {
            query.thread = filters.thread;
        }

        if (filters.subject_id) {
            query.subject_id = filters.subject_id;
            query.subject_type ??= filters.subject_type ?? '';
        }

        router.get(index.url(), query, { preserveScroll: true });
    };

    // Narrowed to one thread or one subject from a breakdown: shown as a
    // removable chip, since there is no select for them.
    const pinned = [
        filters.thread && {
            key: 'thread',
            label: t('Thread :label', {
                label:
                    breakdowns.thread.find(
                        (row) => row.filter.thread === filters.thread,
                    )?.label ?? filters.thread,
            }),
            clear: { thread: null },
        },
        filters.subject_id && {
            key: 'subject',
            label: t(':type #:id', {
                type: filters.subject_type ?? t('Subject'),
                id: filters.subject_id,
            }),
            clear: { subject_id: null, subject_type: null },
        },
    ].filter(Boolean) as {
        key: string;
        label: string;
        clear: Partial<AiFilters>;
    }[];

    return (
        <AdminShell
            title={t('AI usage')}
            description={t(
                'Every AI call across all accounts: what it cost, how long it took, and what was sent. Sent and received text is kept for :days days; tokens and cost are kept for good.',
                { days: retentionDays },
            )}
        >
            <form
                onSubmit={applyFilters}
                className="cc-panel flex flex-col gap-4 p-5"
            >
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="grid gap-1.5">
                        <Label htmlFor="from">{t('From')}</Label>
                        <DatePickerField
                            id="from"
                            name="from"
                            defaultValue={filters.from}
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="to">{t('To')}</Label>
                        <DatePickerField
                            id="to"
                            name="to"
                            defaultValue={filters.to}
                        />
                    </div>
                    {selects.map((select) => (
                        <div key={select.name} className="grid gap-1.5">
                            <Label htmlFor={`filter-${select.name}`}>
                                {select.label}
                            </Label>
                            <Select
                                name={select.name}
                                defaultValue={
                                    filters[select.name] !== null
                                        ? String(filters[select.name])
                                        : ALL
                                }
                            >
                                <SelectTrigger
                                    id={`filter-${select.name}`}
                                    className="h-10 w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>
                                        {t('All')}
                                    </SelectItem>
                                    {select.values.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    ))}
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button className="h-10">{t('Apply')}</Button>
                    <Button asChild variant="ghost" className="h-10">
                        <Link href={index()}>{t('Reset')}</Link>
                    </Button>
                    {pinned.map((pin) => (
                        <Link
                            key={pin.key}
                            href={index({
                                query: { ...filters, ...pin.clear },
                            })}
                            preserveScroll
                            className="cc-pill cc-pill-active inline-flex max-w-full items-center gap-1.5"
                        >
                            <span className="truncate">{pin.label}</span>
                            <X className="size-3.5 shrink-0" />
                        </Link>
                    ))}
                </div>
            </form>

            <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
                <Stat
                    label={t('Cost')}
                    value={formatCost(totals.cost_micros)}
                />
                <Stat
                    label={t('Calls')}
                    value={formatNumber(totals.calls)}
                    hint={
                        totals.calls > 0
                            ? t(':count failed', { count: totals.failed })
                            : undefined
                    }
                />
                <Stat
                    label={t('Input tokens')}
                    value={formatCompact(totals.input_tokens)}
                    hint={t(':count cached', {
                        count: formatCompact(totals.cached_input_tokens),
                    })}
                />
                <Stat
                    label={t('Output tokens')}
                    value={formatCompact(totals.output_tokens)}
                />
                <Stat
                    label={t('Average latency')}
                    value={formatLatency(totals.avg_latency_ms)}
                />
            </div>

            <section className="cc-panel p-5">
                <DailyCostChart days={daily} />
            </section>

            <div className="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                <BreakdownTable
                    title={t('By account')}
                    rows={breakdowns.account}
                    filters={filters}
                />
                <BreakdownTable
                    title={t('By step')}
                    rows={breakdowns.step}
                    emptyLabel={t('Not recorded')}
                    filters={filters}
                    mono
                />
                <BreakdownTable
                    title={t('By agent')}
                    rows={breakdowns.agent}
                    emptyLabel={t('Not recorded')}
                    filters={filters}
                    mono
                />
                <BreakdownTable
                    title={t('By model')}
                    rows={breakdowns.model}
                    emptyLabel={t('Not recorded')}
                    filters={filters}
                    mono
                />
                <BreakdownTable
                    title={t('Most expensive threads')}
                    rows={breakdowns.thread}
                    filters={filters}
                />
                <BreakdownTable
                    title={t('Most expensive messages and documents')}
                    rows={breakdowns.subject}
                    filters={filters}
                />
            </div>

            <section className="flex flex-col gap-3">
                <h2 className="cc-subtitle">
                    {t('Calls')}{' '}
                    <span className="text-cc-subtle tabular-nums">
                        {formatNumber(calls.total)}
                    </span>
                </h2>

                <div className="cc-panel overflow-hidden">
                    <div
                        className={`${callGrid} hidden border-b border-cc-border px-5 py-3`}
                    >
                        <span className="cc-label">{t('When')}</span>
                        <span className="cc-label">{t('Step')}</span>
                        <span className="cc-label">{t('For')}</span>
                        <span className="cc-label text-right">
                            {t('Tokens in / out')}
                        </span>
                        <span className="cc-label text-right">{t('Cost')}</span>
                        <span className="cc-label text-right">{t('Time')}</span>
                    </div>

                    {calls.data.length === 0 && (
                        <p className="cc-caption px-5 py-10 text-center">
                            {t('No AI calls in this range.')}
                        </p>
                    )}

                    {calls.data.map((call) => (
                        <Link
                            key={call.id}
                            href={show(call.id)}
                            className={`${callGrid} flex flex-col gap-1 border-b border-cc-border px-5 py-3 text-[13px] transition-colors last:border-b-0 hover:bg-cc-bg`}
                        >
                            <span className="text-cc-subtle tabular-nums">
                                {call.created_at &&
                                    formatDate(call.created_at, {
                                        dateStyle: undefined,
                                        day: 'numeric',
                                        month: 'short',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                    })}
                            </span>
                            <span className="flex min-w-0 flex-wrap items-center gap-1.5">
                                <span className="font-mono">{call.step}</span>
                                {call.agent && (
                                    <span className="truncate text-cc-subtle">
                                        {call.agent}
                                    </span>
                                )}
                                {call.status === 'failed' && (
                                    <Chip tone="bad">{t('Failed')}</Chip>
                                )}
                                {call.is_batch && <Chip>{t('Batch')}</Chip>}
                            </span>
                            <span className="flex min-w-0 flex-col">
                                <span className="truncate font-medium">
                                    {call.subject_label ??
                                        (call.subject_type
                                            ? t('(no subject)')
                                            : '—')}
                                </span>
                                <span className="truncate text-cc-subtle">
                                    {call.account.name}
                                    {' · '}
                                    {call.model}
                                </span>
                            </span>
                            <span className="tabular-nums lg:text-right">
                                {formatCompact(call.input_tokens)}
                                <span className="text-cc-subtle"> / </span>
                                {formatCompact(call.output_tokens)}
                            </span>
                            <span className="font-semibold tabular-nums lg:text-right">
                                {formatCost(call.cost_micros)}
                            </span>
                            <span className="text-cc-subtle tabular-nums lg:text-right">
                                {formatLatency(call.latency_ms)}
                            </span>
                        </Link>
                    ))}
                </div>

                <Pager page={calls} />
            </section>
        </AdminShell>
    );
}
