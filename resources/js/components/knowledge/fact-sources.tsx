import { FileX } from 'lucide-react';
import { useTranslations } from '@/hooks/use-translations';
import type { FactSourceView } from '@/types';
import { EmailSourceChip, SourceChip } from './source-chip';

/**
 * Every place a fact is stated, as chips: the one it is cited from first. A
 * removed document is named, not linked. Leave out the place you are looking
 * at with `except`.
 */
export function FactSources({
    sources,
    except,
    label,
}: {
    sources: FactSourceView[];
    /** The section being looked at: only the other places are shown. */
    except?: number;
    /** A lead-in such as "Also in", shown when anything is left. */
    label?: string;
}) {
    const t = useTranslations();
    const shown = sources.filter(
        (source) => except === undefined || source.sectionId !== except,
    );

    if (shown.length === 0) {
        return null;
    }

    return (
        <span className="flex flex-wrap items-center gap-1.5">
            {label && <span className="cc-caption">{label}</span>}
            {shown.map((source, index) =>
                source.removed ? (
                    <span
                        key={index}
                        className="inline-flex max-w-full items-center gap-1.5 rounded-md border border-dashed border-cc-border px-2 py-1 text-[12px] font-medium text-cc-subtle"
                    >
                        <FileX className="size-3.5 shrink-0" />
                        <span className="truncate">
                            {t(':title (removed)', {
                                title: source.title || t('(no subject)'),
                            })}
                        </span>
                    </span>
                ) : source.kind === 'mail' ? (
                    <EmailSourceChip
                        key={index}
                        subject={source.title || t('(no subject)')}
                        emailId={source.emailId}
                    />
                ) : (
                    <SourceChip
                        key={index}
                        documentId={source.documentId}
                        title={source.title}
                        versionNumber={source.versionNumber ?? undefined}
                        headingPath={source.headingPath}
                        pageFrom={source.pageFrom}
                        sectionId={source.sectionId}
                    />
                ),
            )}
        </span>
    );
}
