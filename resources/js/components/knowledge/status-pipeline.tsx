import { AlertCircle, Check } from 'lucide-react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import type { ProcessingStatus } from '@/types';
import { pipeline, statusLabels } from './labels';

type Props = {
    status: ProcessingStatus;
    error?: string | null;
    className?: string;
};

/**
 * Where a document is in processing: one segment per pipeline step, filled up
 * to the current one, with the step's name beside it.
 */
export function StatusPipeline({ status, error, className }: Props) {
    const t = useTranslations();

    if (status === 'failed') {
        return (
            <Tooltip>
                <TooltipTrigger asChild>
                    <span className="inline-flex w-fit items-center gap-1.5 rounded-full bg-cc-action-bg px-2.5 py-1 text-[12px] font-semibold text-cc-action-fg">
                        <AlertCircle className="size-3.5" />
                        {t(statusLabels.failed)}
                    </span>
                </TooltipTrigger>
                {error && (
                    <TooltipContent className="max-w-[320px]">
                        {error}
                    </TooltipContent>
                )}
            </Tooltip>
        );
    }

    if (status === 'ready') {
        return (
            <span className="inline-flex w-fit items-center gap-1.5 rounded-full bg-cc-decision-bg px-2.5 py-1 text-[12px] font-semibold text-cc-decision-fg">
                <Check className="size-3.5" strokeWidth={2.5} />
                {t(statusLabels.ready)}
            </span>
        );
    }

    const current = pipeline.indexOf(status);

    return (
        <div
            className={cn('flex min-w-0 flex-col gap-1.5', className)}
            role="progressbar"
            aria-valuemin={0}
            aria-valuemax={pipeline.length - 1}
            aria-valuenow={current}
            aria-valuetext={t(statusLabels[status])}
        >
            <div className="flex gap-0.5">
                {pipeline.slice(0, -1).map((step, index) => (
                    <span
                        key={step}
                        className={cn(
                            'h-1.5 flex-1 rounded-full',
                            index < current
                                ? 'bg-cc-ink'
                                : index === current
                                  ? 'animate-pulse bg-cc-accent'
                                  : 'bg-cc-raised',
                        )}
                    />
                ))}
            </div>
            <span className="text-[12px] font-medium text-cc-muted">
                {t(statusLabels[status])}
            </span>
        </div>
    );
}
