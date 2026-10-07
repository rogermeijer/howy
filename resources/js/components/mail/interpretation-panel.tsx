import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import EmailStatementController from '@/actions/App/Http/Controllers/EmailStatementController';
import {
    AlertTriangle,
    Check,
    ChevronDown,
    FileText,
    Mail,
    Sparkles,
    X,
} from 'lucide-react';
import type { ReactNode } from 'react';
import type { InterpretationKind } from '@/components/cc/tag';
import { Tag } from '@/components/cc/tag';
import { WithHowy } from '@/components/brand/howy-name';
import { InterpretationTag } from '@/components/mail/interpretation-tag';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import emailRoutes from '@/routes/emails';
import documentRoutes from '@/routes/knowledge/documents';
import type {
    Interpretation,
    InterpretationSource,
    InterpretedStatement,
} from '@/types';

type Props = {
    interpretation: Interpretation | null;
    /** The message the interpretation belongs to. */
    emailId: number | null;
    /** May approve or reject what it would add to the knowledge base. */
    canReview: boolean;
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
 * How Howy read a mail and what followed: the dark side panel of a thread.
 */
export function InterpretationPanel({
    interpretation,
    emailId,
    canReview,
    about,
}: Props) {
    const t = useTranslations();
    const formatDate = useFormatDate();

    // What waits for a decision is the call to action, so it comes first and
    // stands out; what was decided is compact; what was already known folds.
    const statements = (interpretation?.statements ?? []).map(
        (statement, index) => ({ statement, index }),
    );
    const pending = statements.filter(
        ({ statement }) => statement.review === 'pending',
    );
    const known = statements.filter(
        ({ statement }) =>
            statement.verdict === 'duplicate' && statement.review !== 'pending',
    );
    const decided = statements.filter(
        ({ statement }) =>
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
                            title={t('To review (:count)', {
                                count: pending.length,
                            })}
                        >
                            <ul className="flex flex-col gap-3">
                                {pending.map(({ statement, index }) => (
                                    <Statement
                                        key={index}
                                        statement={statement}
                                        emailId={emailId}
                                        index={index}
                                        canReview={canReview}
                                        prominent
                                    />
                                ))}
                            </ul>
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
                                {decided.map(({ statement, index }) => (
                                    <Statement
                                        key={index}
                                        statement={statement}
                                        emailId={emailId}
                                        index={index}
                                        canReview={canReview}
                                    />
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
                                {known.map(({ statement, index }) => (
                                    <li
                                        key={index}
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

function Statement({
    statement,
    emailId,
    index,
    canReview,
    prominent = false,
}: {
    statement: InterpretedStatement;
    emailId: number | null;
    index: number;
    canReview: boolean;
    /** Waiting for a decision: a light card that stands out on the dark panel. */
    prominent?: boolean;
}) {
    const t = useTranslations();
    const formatDate = useFormatDate();
    const [busy, setBusy] = useState(false);

    const decide = (action: 'approve' | 'reject') => {
        if (emailId === null) {
            return;
        }

        const route =
            action === 'approve'
                ? EmailStatementController.approve
                : EmailStatementController.reject;

        router.post(
            route.url({ email: emailId, statement: index }),
            {},
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <li
            className={cn(
                'flex flex-col',
                prominent
                    ? 'gap-3 rounded-xl bg-cc-panel p-4 text-cc-ink'
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
                    prominent
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
                                prominent ? 'text-cc-subtle' : 'text-cc-faint',
                            )}
                        >
                            {t('The knowledge base says')}
                        </span>
                        <p
                            className={cn(
                                'text-[13px] leading-[1.5]',
                                prominent
                                    ? 'text-cc-muted'
                                    : 'text-cc-dark-text',
                            )}
                        >
                            {statement.existingStatement}
                        </p>
                        {statement.existingSource && (
                            <SourceLink
                                source={statement.existingSource}
                                light={prominent}
                                className="self-start"
                            />
                        )}
                    </div>
                )}
            {statement.explanation && (
                <p
                    className={cn(
                        'text-[13px] leading-[1.5]',
                        prominent ? 'text-cc-muted' : 'text-cc-dark-text',
                    )}
                >
                    {statement.explanation}
                </p>
            )}
            {statement.flag && (
                <p className="flex gap-2 rounded-[8px] bg-cc-pending-bg px-3 py-2 text-[13px] leading-[1.5] text-cc-pending-fg">
                    <AlertTriangle className="mt-0.5 size-3.5 shrink-0" />
                    <span>{statement.flag}</span>
                </p>
            )}

            {statement.review === 'pending' &&
                (canReview ? (
                    <div className="flex flex-wrap gap-2 pt-1">
                        <button
                            type="button"
                            disabled={busy}
                            onClick={() => decide('approve')}
                            className="flex h-11 cursor-pointer items-center gap-1.5 rounded-[10px] bg-cc-ink px-4 text-[14px] font-semibold text-cc-bg transition-colors hover:bg-cc-dark-2 disabled:opacity-60"
                        >
                            <Check className="size-4" />
                            {statement.verdict === 'conflict'
                                ? t('Approve change')
                                : t('Add to knowledge base')}
                        </button>
                        <button
                            type="button"
                            disabled={busy}
                            onClick={() => decide('reject')}
                            className="flex h-11 cursor-pointer items-center gap-1.5 rounded-[10px] border-[1.5px] border-cc-border-strong px-4 text-[14px] font-semibold text-cc-ink transition-colors hover:border-cc-ink disabled:opacity-60"
                        >
                            <X className="size-4" />
                            {t('Reject')}
                        </button>
                    </div>
                ) : (
                    <p className="text-[12px] text-cc-subtle">
                        {t('Waiting for an administrator to review it.')}
                    </p>
                ))}

            {(statement.review === 'approved' ||
                statement.review === 'rejected') && (
                <p className="text-[12px] text-cc-faint">
                    {(statement.review === 'approved'
                        ? t('Approved by :name', {
                              name: statement.reviewedBy ?? '',
                          })
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
            )}
        </li>
    );
}

function SourceLink({
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
