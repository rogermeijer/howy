import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import type { SectionChange, SectionNode } from '@/types';

const changeStyles: Record<SectionChange, string> = {
    unchanged: 'bg-cc-raised',
    changed: 'bg-cc-pending-fg',
    added: 'bg-cc-decision-fg',
};

export const changeLabels: Record<SectionChange, string> = {
    unchanged: 'Unchanged',
    changed: 'Changed',
    added: 'Added',
};

type Props = {
    sections: SectionNode[];
    selectedId: number | null;
    onSelect: (id: number) => void;
    showChanges: boolean;
};

/**
 * The document's headings as an indented tree. A dot marks how a section
 * compares to the previous version.
 */
export function SectionTree({
    sections,
    selectedId,
    onSelect,
    showChanges,
}: Props) {
    const t = useTranslations();
    const minLevel = Math.min(...sections.map((section) => section.level));

    return (
        <nav aria-label={t('Sections')} className="flex flex-col py-2">
            {sections.map((section) => (
                <button
                    key={section.id}
                    type="button"
                    onClick={() => onSelect(section.id)}
                    aria-current={
                        section.id === selectedId ? 'true' : undefined
                    }
                    className={cn(
                        'group flex w-full cursor-pointer items-start gap-2.5 py-2 pr-4 text-left transition-colors',
                        section.id === selectedId
                            ? 'bg-cc-accent-tint/60 text-cc-ink'
                            : 'hover:bg-cc-bg',
                    )}
                    style={{
                        paddingLeft: 16 + (section.level - minLevel) * 16,
                    }}
                >
                    {showChanges && (
                        <span
                            title={t(changeLabels[section.change])}
                            className={cn(
                                'mt-[7px] size-1.5 shrink-0 rounded-full',
                                changeStyles[section.change],
                            )}
                        />
                    )}
                    <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                        <span
                            className={cn(
                                'text-[14px] leading-snug',
                                section.level === minLevel
                                    ? 'font-semibold'
                                    : 'font-medium',
                                section.heading === null &&
                                    'text-cc-subtle italic',
                            )}
                        >
                            {section.heading ?? t('Introduction')}
                        </span>
                        <span className="text-[12px] text-cc-faint">
                            {[
                                section.pageFrom !== null
                                    ? section.pageFrom === section.pageTo
                                        ? t('p. :page', {
                                              page: section.pageFrom,
                                          })
                                        : t('p. :from–:to', {
                                              from: section.pageFrom,
                                              to:
                                                  section.pageTo ??
                                                  section.pageFrom,
                                          })
                                    : null,
                                section.chunksCount > 0
                                    ? t(':count chunks', {
                                          count: section.chunksCount,
                                      })
                                    : null,
                            ]
                                .filter(Boolean)
                                .join(' · ')}
                        </span>
                    </span>
                </button>
            ))}
        </nav>
    );
}
