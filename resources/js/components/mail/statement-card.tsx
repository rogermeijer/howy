import { Link } from '@inertiajs/react';
import { AlertTriangle, FileText, Mail } from 'lucide-react';
import type { ReactNode } from 'react';
import type { InterpretationKind } from '@/components/cc/tag';
import { Tag } from '@/components/cc/tag';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import emailRoutes from '@/routes/emails';
import documentRoutes from '@/routes/knowledge/documents';
import type { InterpretationSource, InterpretedStatement } from '@/types';

const verdictKinds: Record<
    InterpretedStatement['verdict'],
    InterpretationKind
> = {
    new: 'knowledge',
    duplicate: 'noise',
    conflict: 'action',
};

const verdictLabels: Record<InterpretedStatement['verdict'], string> = {
    new: 'Added',
    duplicate: 'Already known',
    conflict: 'Conflict',
};

/**
 * One statement a mail makes, with its verdict: what the knowledge base says
 * when it contradicts it, why someone should look, where it came from. Light
 * where it is decided on (the approvals), dark on a thread's interpretation
 * panel. What follows it (a decision, who decided) is the children.
 */
export function StatementCard({
    statement,
    light = false,
    children,
}: {
    statement: InterpretedStatement;
    light?: boolean;
    children?: ReactNode;
}) {
    const t = useTranslations();

    return (
        <li
            className={cn(
                'flex flex-col',
                light
                    ? 'gap-3 rounded-xl border border-cc-border bg-cc-panel p-4 text-cc-ink'
                    : 'gap-2 rounded-[10px] bg-cc-dark-1 px-3.5 py-3',
            )}
        >
            <Tag
                kind={verdictKinds[statement.verdict]}
                label={t(verdictLabels[statement.verdict])}
                className="self-start"
            />
            <p
                className={cn(
                    'leading-[1.5]',
                    light
                        ? 'text-[15px] font-medium text-cc-ink'
                        : 'text-[14px] text-cc-bg',
                )}
            >
                {statement.statement}
            </p>
            {statement.verdict === 'conflict' &&
                statement.existingStatement && (
                    <div className="flex flex-col gap-1.5 border-l-2 border-cc-accent pl-3">
                        <span
                            className={cn(
                                'text-[12px]',
                                light ? 'text-cc-subtle' : 'text-cc-faint',
                            )}
                        >
                            {t('The knowledge base says')}
                        </span>
                        <p
                            className={cn(
                                'text-[13px] leading-[1.5]',
                                light ? 'text-cc-muted' : 'text-cc-dark-text',
                            )}
                        >
                            {statement.existingStatement}
                        </p>
                        {statement.existingSource && (
                            <SourceLink
                                source={statement.existingSource}
                                light={light}
                                className="self-start"
                            />
                        )}
                    </div>
                )}
            {statement.explanation && (
                <p
                    className={cn(
                        'text-[13px] leading-[1.5]',
                        light ? 'text-cc-muted' : 'text-cc-dark-text',
                    )}
                >
                    {statement.explanation}
                </p>
            )}
            {statement.fromHistory && (
                <p
                    className={cn(
                        'text-[12px]',
                        light ? 'text-cc-muted' : 'text-cc-faint',
                    )}
                >
                    {t('From earlier in the thread')}
                </p>
            )}
            {statement.flag && (
                <p className="flex gap-2 rounded-[8px] bg-cc-pending-bg px-3 py-2 text-[13px] leading-[1.5] text-cc-pending-fg">
                    <AlertTriangle className="mt-0.5 size-3.5 shrink-0" />
                    <span>{statement.flag}</span>
                </p>
            )}
            {children}
        </li>
    );
}

/**
 * Where a fact a statement was held against comes from, as a link.
 */
export function SourceLink({
    source,
    light = false,
    className = '',
}: {
    source: InterpretationSource;
    /** On a light card rather than the dark panel. */
    light?: boolean;
    className?: string;
}) {
    const t = useTranslations();
    const href =
        source.type === 'email'
            ? emailRoutes.show(source.id)
            : documentRoutes.show(source.id, {
                  query: source.sectionId
                      ? { section: source.sectionId }
                      : undefined,
              });
    const lastHeading = source.headingPath?.split(' › ').at(-1);
    const label =
        source.type === 'email'
            ? source.title || t('(no subject)')
            : [
                  source.title,
                  lastHeading ? `§ ${lastHeading}` : null,
                  source.page ? t('p. :page', { page: source.page }) : null,
              ]
                  .filter(Boolean)
                  .join(' · ');

    return (
        <Link
            href={href}
            className={cn(
                'inline-flex max-w-full items-center gap-1.5 rounded-md border px-2 py-1 text-[12px] font-medium transition-colors',
                light
                    ? 'border-cc-border bg-cc-panel text-cc-muted hover:border-cc-border-strong hover:text-cc-ink'
                    : 'border-cc-dark-border text-cc-dark-text hover:border-cc-faint hover:text-cc-bg',
                className,
            )}
        >
            {source.type === 'email' ? (
                <Mail className="size-3.5 shrink-0" />
            ) : (
                <FileText className="size-3.5 shrink-0" />
            )}
            <span className="truncate">{label}</span>
        </Link>
    );
}
