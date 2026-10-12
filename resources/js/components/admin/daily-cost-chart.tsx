import { useState } from 'react';
import { formatCost, formatNumber } from '@/components/admin/format';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import type { AiDay } from '@/types';

/**
 * Estimated cost per day: one series, so one hue and no legend. Each bar has a
 * hit area the full height of the plot and shows its day on hover or focus.
 */
export function DailyCostChart({ days }: { days: AiDay[] }) {
    const t = useTranslations();
    const formatDate = useFormatDate();
    const [active, setActive] = useState<number | null>(null);
    const max = Math.max(...days.map((day) => day.cost_micros), 0);
    const shown = active !== null ? days[active] : null;
    const total = days.reduce((sum, day) => sum + day.cost_micros, 0);

    return (
        <figure className="flex flex-col gap-3">
            <figcaption className="flex flex-wrap items-baseline justify-between gap-2">
                <span className="cc-label">{t('Cost per day')}</span>
                <span className="cc-caption tabular-nums">
                    {shown
                        ? t(':date: :cost over :calls calls', {
                              date: formatDate(shown.date, { timeZone: 'UTC' }),
                              cost: formatCost(shown.cost_micros),
                              calls: formatNumber(shown.calls),
                          })
                        : t(':cost over :days days', {
                              cost: formatCost(total),
                              days: days.length,
                          })}
                </span>
            </figcaption>

            <div className="relative">
                <span className="cc-caption absolute top-0 left-0 tabular-nums">
                    {formatCost(max)}
                </span>
                <div
                    className="flex h-40 items-end gap-[2px] border-b border-cc-border pt-6"
                    onMouseLeave={() => setActive(null)}
                >
                    {days.map((day, i) => (
                        <button
                            key={day.date}
                            type="button"
                            className="group flex h-full min-w-0 flex-1 cursor-default items-end focus:outline-none"
                            onMouseEnter={() => setActive(i)}
                            onFocus={() => setActive(i)}
                            onBlur={() => setActive(null)}
                            aria-label={`${day.date}: ${formatCost(day.cost_micros)}, ${t(':count calls', { count: day.calls })}`}
                        >
                            <span
                                className={cn(
                                    'block w-full rounded-t-[4px] transition-colors',
                                    active === i
                                        ? 'bg-cc-ink'
                                        : 'bg-cc-accent-deep group-focus-visible:bg-cc-ink',
                                )}
                                style={{
                                    height:
                                        max > 0 && day.cost_micros > 0
                                            ? `max(2px, ${(day.cost_micros / max) * 100}%)`
                                            : '0px',
                                }}
                            />
                        </button>
                    ))}
                </div>
                <div className="cc-caption mt-1.5 flex justify-between tabular-nums">
                    <span>
                        {days[0] &&
                            formatDate(days[0].date, { timeZone: 'UTC' })}
                    </span>
                    <span>
                        {days.length > 0 &&
                            formatDate(days[days.length - 1].date, {
                                timeZone: 'UTC',
                            })}
                    </span>
                </div>
            </div>
        </figure>
    );
}
