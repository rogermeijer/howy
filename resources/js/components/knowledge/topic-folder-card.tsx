import { Link } from '@inertiajs/react';
import { Folder } from 'lucide-react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { useTranslations } from '@/hooks/use-translations';
import type { TopicFolder } from '@/types';

const kindLabels = {
    theme: 'Theme',
    project: 'Project',
    party: 'Relation',
} as const;

/**
 * A folder in the knowledge base. Folders made by AI carry a "new" badge until
 * an administrator approves them.
 */
export function TopicFolderCard({
    topic,
    href,
    meta,
}: {
    topic: TopicFolder;
    href: NonNullable<InertiaLinkProps['href']>;
    meta?: string;
}) {
    const t = useTranslations();

    return (
        <Link
            href={href}
            className="cc-panel flex flex-col gap-3.5 p-5 transition-colors hover:border-cc-border-strong"
        >
            <div className="flex items-start justify-between gap-2">
                <Folder
                    className="size-[26px] text-cc-ink"
                    strokeWidth={1.8}
                    fill={topic.isNew ? '#f0fbd3' : '#edf1ef'}
                />
                <span className="flex flex-wrap justify-end gap-1">
                    {topic.kind !== 'theme' && (
                        <span className="rounded-md bg-cc-raised px-1.5 py-0.5 text-[11px] font-semibold text-cc-muted">
                            {t(kindLabels[topic.kind])}
                        </span>
                    )}
                    {topic.isNew && (
                        <span className="rounded-md bg-cc-accent-tint px-1.5 py-0.5 text-[11px] font-semibold text-cc-accent-deep">
                            {t('new (AI)')}
                        </span>
                    )}
                </span>
            </div>
            <div className="flex flex-col gap-0.5">
                <div className="text-[15px] font-semibold">{topic.name}</div>
                {meta && <div className="cc-caption">{meta}</div>}
            </div>
        </Link>
    );
}
