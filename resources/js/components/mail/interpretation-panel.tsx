import { Link } from '@inertiajs/react';
import { FileText, Mail, Sparkles } from 'lucide-react';
import type { ReactNode } from 'react';
import type { InterpretationKind } from '@/components/cc/tag';
import { Tag } from '@/components/cc/tag';
import { WithCcLogo } from '@/components/cc/with-cc-logo';
import { InterpretationTag } from '@/components/mail/interpretation-tag';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslations } from '@/hooks/use-translations';
import emailRoutes from '@/routes/emails';
import documentRoutes from '@/routes/knowledge/documents';
import type {
    Interpretation,
    InterpretationSource,
    InterpretedStatement,
} from '@/types';

type Props = {
    interpretation: Interpretation | null;
    /** Which message this is about, when the thread has more than one. */
    about: string | null;
};

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
 * How cc: read a mail and what followed: the dark side panel of a thread.
 */
export function InterpretationPanel({ interpretation, about }: Props) {
    const t = useTranslations();
    const formatDate = useFormatDate();

    return (
        <aside className="cc-panel-dark flex flex-col gap-5 p-7">
            <div className="flex flex-col gap-1">
                <div className="cc-label text-cc-faint">
                    {t('Interpretation')}
                </div>
                {about && (
                    <div className="text-[12px] text-cc-faint">{about}</div>
                )}
            </div>

            <InterpretationTag
                interpretation={interpretation}
                className="self-start"
            />

            {interpretation === null ? (
                <Note>
                    <WithCcLogo
                        text={t(
                            '[cc]: interprets mail sent to the mailbox or copied to it. This message was imported, sent by the mailbox itself or sent automatically, so it is stored but not interpreted.',
                        )}
                    />
                </Note>
            ) : (
                <>
                    {interpretation.summary && (
                        <Note>{interpretation.summary}</Note>
                    )}

                    {(interpretation.status === 'queued' ||
                        interpretation.status === 'processing') && (
                        <Note>
                            <WithCcLogo
                                text={t(
                                    '[cc]: is reading this email. The outcome will appear here.',
                                )}
                            />
                        </Note>
                    )}

                    {interpretation.intent && (
                        <dl className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1.5 text-[13px]">
                            <dt className="text-cc-faint">{t('Read as')}</dt>
                            <dd className="text-cc-bg">
                                {interpretation.intentLabel}
                                {interpretation.confidence !== null && (
                                    <span className="text-cc-faint">
                                        {' · '}
                                        {t(':percent% sure', {
                                            percent: Math.round(
                                                interpretation.confidence * 100,
                                            ),
                                        })}
                                    </span>
                                )}
                            </dd>
                            <dt className="text-cc-faint">{t('Role')}</dt>
                            <dd className="text-cc-bg">
                                {interpretation.modeLabel}
                            </dd>
                            {interpretation.processedAt && (
                                <>
                                    <dt className="text-cc-faint">
                                        {t('Processed')}
                                    </dt>
                                    <dd className="text-cc-bg">
                                        {formatDate(
                                            interpretation.processedAt,
                                            {
                                                dateStyle: 'medium',
                                                timeStyle: 'short',
                                            },
                                        )}
                                    </dd>
                                </>
                            )}
                        </dl>
                    )}

                    {interpretation.question && (
                        <Block title={t('Question')}>
                            <p className="text-[14px] leading-[1.55] text-cc-bg">
                                {interpretation.question}
                            </p>
                        </Block>
                    )}

                    {interpretation.statements.length > 0 && (
                        <Block title={t('Statements')}>
                            <ul className="flex flex-col gap-3">
                                {interpretation.statements.map(
                                    (statement, index) => (
                                        <Statement
                                            key={index}
                                            statement={statement}
                                        />
                                    ),
                                )}
                            </ul>
                        </Block>
                    )}

                    {interpretation.error && (
                        <p className="rounded-[10px] bg-cc-dark-1 px-3.5 py-3 text-[13px] text-cc-dark-text">
                            {interpretation.error}
                        </p>
                    )}
                </>
            )}
        </aside>
    );
}

function Note({ children }: { children: ReactNode }) {
    return (
        <p className="flex gap-2.5 text-[14px] leading-[1.55] text-cc-dark-text">
            <Sparkles className="mt-0.5 size-4 shrink-0 text-cc-accent" />
            <span>{children}</span>
        </p>
    );
}

function Block({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="flex flex-col gap-2.5 border-t border-cc-dark-border pt-4">
            <h2 className="cc-label text-cc-faint">{title}</h2>
            {children}
        </section>
    );
}

function Statement({ statement }: { statement: InterpretedStatement }) {
    const t = useTranslations();

    return (
        <li className="flex flex-col gap-2 rounded-[10px] bg-cc-dark-1 px-3.5 py-3">
            <Tag
                kind={verdictKinds[statement.verdict]}
                label={t(verdictLabels[statement.verdict])}
                className="self-start"
            />
            <p className="text-[14px] leading-[1.5] text-cc-bg">
                {statement.statement}
            </p>
            {statement.verdict === 'conflict' &&
                statement.existingStatement && (
                    <div className="flex flex-col gap-1.5 border-l-2 border-cc-accent pl-3">
                        <span className="text-[12px] text-cc-faint">
                            {t('The knowledge base says')}
                        </span>
                        <p className="text-[13px] leading-[1.5] text-cc-dark-text">
                            {statement.existingStatement}
                        </p>
                        {statement.existingSource && (
                            <SourceLink
                                source={statement.existingSource}
                                className="self-start"
                            />
                        )}
                    </div>
                )}
            {statement.explanation && (
                <p className="text-[13px] leading-[1.5] text-cc-dark-text">
                    {statement.explanation}
                </p>
            )}
        </li>
    );
}

function SourceLink({
    source,
    className = '',
}: {
    source: InterpretationSource;
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
            className={`inline-flex max-w-full items-center gap-1.5 rounded-md border border-cc-dark-border px-2 py-1 text-[12px] font-medium text-cc-dark-text transition-colors hover:border-cc-faint hover:text-cc-bg ${className}`}
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
