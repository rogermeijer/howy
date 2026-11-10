import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import type { Mailbox } from '@/types';

/** How far a running import of older mail has come. */
export function ImportProgress({
    progress,
    className,
}: {
    progress: NonNullable<Mailbox['import']>;
    className?: string;
}) {
    const t = useTranslations();

    return (
        <div className={cn('flex flex-col gap-2', className)}>
            <div className="flex justify-between text-[13px]">
                <span className="font-semibold">{t('Import running')}</span>
                <span className="text-cc-subtle">
                    {t(':processed of :total emails', {
                        processed: progress.processed,
                        total: progress.total,
                    })}
                </span>
            </div>
            <div
                role="progressbar"
                aria-label={t('Import progress')}
                aria-valuemin={0}
                aria-valuemax={progress.total}
                aria-valuenow={progress.processed}
                className="h-1.5 overflow-hidden rounded-full bg-cc-raised"
            >
                <div
                    className="h-1.5 rounded-full bg-cc-accent transition-[width]"
                    style={{
                        width: `${Math.round((progress.processed / Math.max(1, progress.total)) * 100)}%`,
                    }}
                />
            </div>
        </div>
    );
}
