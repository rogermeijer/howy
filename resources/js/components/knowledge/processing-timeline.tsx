import { AlertCircle, Check, Loader2, MinusCircle } from 'lucide-react';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import type { ProcessingStepView } from '@/types';

export const stepLabels: Record<string, string> = {
    extract: 'Read text',
    structure: 'Sections and chunks',
    contextualize: 'Context lines',
    embed: 'Index (embeddings)',
    enrich: 'Summaries, facts and folders',
    embed_facts: 'Index facts',
};

const metaLabels: Record<string, string> = {
    method: 'Method',
    pages: 'Pages',
    blocks: 'Blocks',
    sections: 'Sections',
    unchanged_sections: 'Unchanged sections',
    chunks: 'Chunks',
    reused_chunks: 'Reused chunks',
    contextualized: 'Context lines written',
    embedded: 'Chunks embedded',
    scanned_pages: 'Pages read by AI',
    unread_pages: 'Pages without text',
    requests: 'Requests',
    facts: 'Facts',
    summaries: 'Summaries',
    topics: 'Folder links',
    reason: 'Reason',
};

function duration(start: string | null, end: string | null): string | null {
    if (!start || !end) {
        return null;
    }

    const ms = new Date(end).getTime() - new Date(start).getTime();

    return ms < 1000 ? `${ms} ms` : `${(ms / 1000).toFixed(1)} s`;
}

export function ProcessingTimeline({ steps }: { steps: ProcessingStepView[] }) {
    const t = useTranslations();

    if (steps.length === 0) {
        return (
            <p className="cc-caption">{t('Processing has not started yet.')}</p>
        );
    }

    return (
        <ol className="flex flex-col">
            {steps.map((step) => {
                const meta = Object.entries(step.meta).filter(
                    ([key, value]) =>
                        key in metaLabels &&
                        !(Array.isArray(value) && value.length === 0),
                );

                return (
                    <li
                        key={step.step}
                        className="flex gap-4 border-b border-cc-border py-4 last:border-b-0"
                    >
                        <span
                            className={cn(
                                'flex size-8 shrink-0 items-center justify-center rounded-full',
                                step.status === 'succeeded' &&
                                    'bg-cc-decision-bg text-cc-decision-fg',
                                step.status === 'failed' &&
                                    'bg-cc-action-bg text-cc-action-fg',
                                step.status === 'running' &&
                                    'bg-cc-pending-bg text-cc-pending-fg',
                                step.status === 'skipped' &&
                                    'bg-cc-raised text-cc-muted',
                            )}
                        >
                            {step.status === 'succeeded' && (
                                <Check className="size-4" strokeWidth={2.5} />
                            )}
                            {step.status === 'failed' && (
                                <AlertCircle className="size-4" />
                            )}
                            {step.status === 'running' && (
                                <Loader2 className="size-4 animate-spin" />
                            )}
                            {step.status === 'skipped' && (
                                <MinusCircle className="size-4" />
                            )}
                        </span>
                        <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                            <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                <span className="text-[15px] font-semibold">
                                    {t(stepLabels[step.step] ?? step.step)}
                                </span>
                                <span className="cc-caption">
                                    {[
                                        duration(
                                            step.startedAt,
                                            step.finishedAt,
                                        ),
                                        step.attempts > 1
                                            ? t(':count attempts', {
                                                  count: step.attempts,
                                              })
                                            : null,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </span>
                            </div>
                            {step.error && (
                                <p className="text-[13px] text-cc-action-fg">
                                    {step.error}
                                </p>
                            )}
                            {meta.length > 0 && (
                                <dl className="flex flex-wrap gap-x-5 gap-y-1 text-[13px]">
                                    {meta.map(([key, value]) => (
                                        <div key={key} className="flex gap-1.5">
                                            <dt className="text-cc-subtle">
                                                {t(metaLabels[key])}
                                            </dt>
                                            <dd className="font-medium">
                                                {Array.isArray(value)
                                                    ? value.join(', ')
                                                    : String(value)}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                            )}
                            {step.usage && (
                                <p className="text-[13px] text-cc-subtle">
                                    {t(
                                        ':calls calls · :input input tokens (:cached cached) · :output output tokens · $:cost',
                                        {
                                            calls: step.usage.calls,
                                            input: step.usage.inputTokens.toLocaleString(),
                                            cached: step.usage.cachedInputTokens.toLocaleString(),
                                            output: step.usage.outputTokens.toLocaleString(),
                                            cost: (
                                                step.usage.costMicros /
                                                1_000_000
                                            ).toFixed(4),
                                        },
                                    )}
                                </p>
                            )}
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}
