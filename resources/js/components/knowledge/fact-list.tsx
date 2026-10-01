import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import type { FactStatus, TopicFact } from '@/types';
import { SourceChip } from './source-chip';

const groups: { status: FactStatus; label: string; tone: string }[] = [
    {
        status: 'core',
        label: 'Core',
        tone: 'bg-cc-knowledge-bg text-cc-knowledge-fg',
    },
    {
        status: 'supplementary',
        label: 'Supplementary',
        tone: 'bg-cc-question-bg text-cc-question-fg',
    },
    { status: 'expired', label: 'Expired', tone: 'bg-cc-raised text-cc-muted' },
];

/**
 * Facts grouped by standing. Expired facts are folded away: they are history,
 * kept so a later mail can be told what changed.
 */
export function FactList({ facts }: { facts: TopicFact[] }) {
    const t = useTranslations();
    const formatDate = useFormatDate();
    const [showExpired, setShowExpired] = useState(false);

    return (
        <div className="flex flex-col gap-5">
            {groups.map(({ status, label, tone }) => {
                const items = facts.filter((fact) => fact.status === status);

                if (items.length === 0) {
                    return null;
                }

                const folded = status === 'expired' && !showExpired;

                return (
                    <div key={status} className="flex flex-col gap-2.5">
                        <button
                            type="button"
                            disabled={status !== 'expired'}
                            onClick={() => setShowExpired(!showExpired)}
                            className="flex items-center gap-2 self-start disabled:cursor-default"
                        >
                            <span
                                className={cn(
                                    'rounded-md px-2 py-0.5 text-[12px] font-semibold',
                                    tone,
                                )}
                            >
                                {t(label)}
                            </span>
                            <span className="cc-caption">{items.length}</span>
                            {status === 'expired' && (
                                <ChevronDown
                                    className={cn(
                                        'size-4 text-cc-subtle transition-transform',
                                        showExpired && 'rotate-180',
                                    )}
                                />
                            )}
                        </button>

                        {!folded && (
                            <ul className="flex flex-col gap-2">
                                {items.map((fact) => (
                                    <li
                                        key={fact.id}
                                        className={cn(
                                            'flex flex-col gap-2 rounded-xl border border-cc-border bg-cc-panel px-4 py-3',
                                            status === 'expired' && 'bg-cc-bg',
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'cc-body',
                                                status === 'expired' &&
                                                    'text-cc-muted line-through decoration-cc-faint',
                                            )}
                                        >
                                            {fact.statement}
                                        </span>
                                        <span className="flex flex-wrap items-center gap-2">
                                            <SourceChip
                                                documentId={fact.documentId}
                                                title={fact.documentTitle}
                                                versionNumber={
                                                    fact.versionNumber ??
                                                    undefined
                                                }
                                                headingPath={fact.headingPath}
                                                pageFrom={fact.pageFrom}
                                                sectionId={fact.sectionId}
                                            />
                                            {fact.validFrom && (
                                                <span className="cc-caption">
                                                    {t('from :date', {
                                                        date: formatDate(
                                                            fact.validFrom,
                                                        ),
                                                    })}
                                                </span>
                                            )}
                                            {fact.validUntil && (
                                                <span className="cc-caption">
                                                    {t('until :date', {
                                                        date: formatDate(
                                                            fact.validUntil,
                                                        ),
                                                    })}
                                                </span>
                                            )}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
