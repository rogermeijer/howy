import { useTranslations } from '@/hooks/use-translations';
import type { DocumentType } from '@/types';
import { documentTypeLabels } from './labels';

export function DocumentTypeBadge({
    type,
    isCore,
}: {
    type: DocumentType;
    isCore?: boolean;
}) {
    const t = useTranslations();

    return (
        <span className="inline-flex flex-wrap items-center gap-1.5">
            <span className="rounded-md bg-cc-raised px-2 py-1 text-[12px] font-semibold text-cc-muted">
                {t(documentTypeLabels[type])}
            </span>
            {isCore && (
                <span className="rounded-md bg-cc-knowledge-bg px-2 py-1 text-[12px] font-semibold text-cc-knowledge-fg">
                    {t('Core')}
                </span>
            )}
        </span>
    );
}
