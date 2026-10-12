import { Link } from '@inertiajs/react';
import {
    formatCompact,
    formatCost,
    formatNumber,
} from '@/components/admin/format';
import { useTranslations } from '@/hooks/use-translations';
import { index } from '@/routes/admin/ai';
import type { AiBreakdownRow, AiFilters } from '@/types';

type Props = {
    title: string;
    rows: AiBreakdownRow[];
    filters: AiFilters;
    /** Show keys in monospace: steps, models, agents. */
    mono?: boolean;
    /** What a row without a label is called; defaults to "(no subject)". */
    emptyLabel?: string;
};

/**
 * Calls and cost grouped by one dimension. Every row narrows the log to it.
 */
export function BreakdownTable({
    title,
    rows,
    filters,
    mono = false,
    emptyLabel,
}: Props) {
    const t = useTranslations();
    const max = Math.max(...rows.map((row) => row.cost_micros), 0);

    return (
        <section className="cc-panel flex flex-col gap-3 p-5">
            <h3 className="cc-label">{title}</h3>

            {rows.length === 0 ? (
                <p className="cc-caption">{t('Nothing in this range.')}</p>
            ) : (
                <ol className="flex flex-col">
                    {rows.map((row) => (
                        <li key={row.key}>
                            <Link
                                href={index({
                                    query: { ...filters, ...row.filter },
                                })}
                                preserveScroll
                                className="group flex flex-col gap-1 rounded-lg px-2 py-2 hover:bg-cc-bg"
                            >
                                <span className="flex items-baseline justify-between gap-3 text-[13px]">
                                    <span
                                        className={
                                            mono
                                                ? 'truncate font-mono'
                                                : 'truncate font-medium'
                                        }
                                    >
                                        {row.label ??
                                            emptyLabel ??
                                            t('(no subject)')}
                                    </span>
                                    <span className="shrink-0 tabular-nums">
                                        <span className="text-cc-subtle">
                                            {t(':count calls', {
                                                count: formatNumber(row.calls),
                                            })}
                                            {' · '}
                                            {formatCompact(row.tokens)}
                                            {' · '}
                                        </span>
                                        <span className="font-semibold">
                                            {formatCost(row.cost_micros)}
                                        </span>
                                    </span>
                                </span>
                                <span className="h-1 overflow-hidden rounded-full bg-cc-border">
                                    <span
                                        className="block h-full rounded-full bg-cc-accent-deep/70"
                                        style={{
                                            width:
                                                max > 0
                                                    ? `${(row.cost_micros / max) * 100}%`
                                                    : '0%',
                                        }}
                                    />
                                </span>
                            </Link>
                        </li>
                    ))}
                </ol>
            )}
        </section>
    );
}
