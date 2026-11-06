import { Link } from '@inertiajs/react';
import { ArrowRight, ChevronDown, Sparkles } from 'lucide-react';
import type { ReactNode } from 'react';
import { WithHowy } from '@/components/brand/howy-name';
import { EmailSourceChip } from '@/components/knowledge/source-chip';
import { InterpretationTag } from '@/components/mail/interpretation-tag';
import { SourceLink, StatementCard } from '@/components/mail/statement-card';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslations } from '@/hooks/use-translations';
import knowledgeRoutes from '@/routes/knowledge';
import type { Interpretation, InterpretedStatement } from '@/types';

type Props = {
    interpretation: Interpretation | null;
    /** May approve or reject what it would add, in the knowledge base. */
    canReview: boolean;
    /** Which message this is about, when the thread has more than one. */
    about: string | null;
};

/**
 * How Howy read a mail and what followed: the dark side panel of a thread.
 */
export function InterpretationPanel({
    interpretation,
    canReview,
    about,
}: Props) {
    const t = useTranslations();
    const formatDate = useFormatDate();

    // What waits is decided in the knowledge base, so here it only says so;
    // what was decided is compact; what was already known folds.
    const statements = interpretation?.statements ?? [];
    const pending = statements.filter(
        (statement) => statement.review === 'pending',
    );
    const known = statements.filter(
        (statement) =>
            statement.verdict === 'duplicate' && statement.review !== 'pending',
    );
    const decided = statements.filter(
        (statement) =>
            statement.review !== 'pending' && statement.verdict !== 'duplicate',
    );

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
                    <WithHowy
                        text={t(
                            'Howy interprets mail sent to the mailbox or copied to it. This message was imported, sent by the mailbox itself or sent automatically, so it is stored but not interpreted.',
                        )}
                    />
                </Note>
            ) : (
                <>
                    {interpretation.summary && (
                        <Note>{interpretation.summary}</Note>
                    )}

                    {interpretation.outcome === 'duplicate' &&
                        interpretation.statements.length === 0 &&
                        interpretation.citations.length > 0 && (
                            <div className="flex flex-col gap-1.5">
                                <span className="text-[12px] text-cc-faint">
                                    {t('Read before in')}
                                </span>
                                <ul className="flex flex-wrap gap-1.5">
                                    {interpretation.citations.map((source) => (
                                        <li
                                            key={source.id}
                                            className="max-w-full"
                                        >
                                            <EmailSourceChip
                                                subject={
                                                    source.title ||
                                                    t('(no subject)')
                                                }
                                                emailId={source.id}
                                            />
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}

                    {(interpretation.status === 'queued' ||
                        interpretation.status === 'processing') && (
                        <Note>
                            <WithHowy
                                text={t(
                                    'Howy is reading this email. The outcome will appear here.',
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

                    {pending.length > 0 && (
                        <Block
                            title={t('Waiting for approval (:count)', {
                                count: pending.length,
                            })}
                        >
                            <ul className="flex flex-col gap-2">
                                {pending.map((statement) => (
                                    <StatementCard
                                        key={statement.index}
                                        statement={statement}
                                    />
                                ))}
                            </ul>
                            {canReview ? (
                                <Link
                                    href={knowledgeRoutes.approvals()}
                                    className="inline-flex min-h-10 items-center gap-1.5 self-start text-[13px] font-semibold text-cc-bg underline-offset-4 hover:underline"
                                >
                                    {t('Approve in the knowledge base')}
                                    <ArrowRight className="size-3.5" />
                                </Link>
                            ) : (
                                <p className="text-[12px] text-cc-faint">
                                    {t(
                                        'Waiting for an administrator to approve it in the knowledge base.',
                                    )}
                                </p>
                            )}
                        </Block>
                    )}

                    {decided.length > 0 && (
                        <Block
                            title={
                                pending.length > 0
                                    ? t('Reviewed')
                                    : t('Statements')
                            }
                        >
                            <ul className="flex flex-col gap-2">
                                {decided.map((statement) => (
                                    <StatementCard
                                        key={statement.index}
                                        statement={statement}
                                    >
                                        <Decided statement={statement} />
                                    </StatementCard>
                                ))}
                            </ul>
                        </Block>
                    )}

                    {known.length > 0 && (
                        <details className="group border-t border-cc-dark-border pt-4">
                            <summary className="flex min-h-10 cursor-pointer list-none items-center justify-between gap-3 text-[13px] font-semibold text-cc-dark-text hover:text-cc-bg">
                                <span>
                                    {known.length === 1
                                        ? t('1 statement already known')
                                        : t(':count statements already known', {
                                              count: known.length,
                                          })}
                                </span>
                                <ChevronDown className="size-4 transition-transform group-open:rotate-180" />
                            </summary>
                            <ul className="mt-2 flex flex-col gap-2">
                                {known.map((statement) => (
                                    <li
                                        key={statement.index}
                                        className="flex flex-col gap-1.5 rounded-[10px] bg-cc-dark-1 px-3.5 py-2.5"
                                    >
                                        <p className="text-[13px] leading-[1.5] text-cc-dark-text">
                                            {statement.statement}
                                        </p>
                                        {statement.existingSource && (
                                            <SourceLink
                                                source={
                                                    statement.existingSource
                                                }
                                                className="self-start"
                                            />
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </details>
                    )}

                    {interpretation.note && (
                        <Note>
                            <WithHowy text={interpretation.note} />
                        </Note>
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

/** Who approved or rejected a statement, and when. */
function Decided({ statement }: { statement: InterpretedStatement }) {
    const t = useTranslations();
    const formatDate = useFormatDate();

    if (statement.review !== 'approved' && statement.review !== 'rejected') {
        return null;
    }

    return (
        <p className="text-[12px] text-cc-faint">
            {(statement.review === 'approved'
                ? t('Approved by :name', { name: statement.reviewedBy ?? '' })
                : t('Rejected by :name', {
                      name: statement.reviewedBy ?? '',
                  })) +
                (statement.reviewedAt
                    ? ' · ' +
                      formatDate(statement.reviewedAt, {
                          dateStyle: 'medium',
                          timeStyle: 'short',
                      })
                    : '')}
        </p>
    );
}
