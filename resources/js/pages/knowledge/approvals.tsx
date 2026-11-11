import { Head, Link, router } from '@inertiajs/react';
import { Check, FileText, Mail, Upload, X } from 'lucide-react';
import { useState } from 'react';
import EmailStatementController from '@/actions/App/Http/Controllers/EmailStatementController';
import { WithHowy } from '@/components/brand/howy-name';
import { PageHeader } from '@/components/cc/page-header';
import { Tag } from '@/components/cc/tag';
import { EmailSourceChip } from '@/components/knowledge/source-chip';
import { SenderRequestNote } from '@/components/mail/sender-request';
import { StatementCard } from '@/components/mail/statement-card';
import { Button } from '@/components/ui/button';
import { useFormatDate } from '@/hooks/use-format-date';
import { useAccountUpdates } from '@/hooks/use-account-updates';
import { useTranslations } from '@/hooks/use-translations';
import { knowledge } from '@/routes';
import emailRoutes from '@/routes/emails';
import documentRoutes from '@/routes/knowledge/documents';
import type {
    ApprovalDocument,
    ApprovalMail,
    InterpretedStatement,
} from '@/types';

type Props = {
    documents: ApprovalDocument[];
    mails: ApprovalMail[];
};

/**
 * Everything that waits before it reaches the knowledge base, in one place:
 * processed documents, uploaded or mailed, each judged on its own page; and
 * what mail would add or change, decided here, statement by statement.
 */
export default function Approvals({ documents, mails }: Props) {
    const t = useTranslations();

    // What comes to wait, or is dealt with elsewhere, shows as it happens.
    useAccountUpdates('approvals', ['documents', 'mails']);

    return (
        <>
            <Head title={t('Ready for approval')} />

            <div className="flex flex-col gap-7">
                <PageHeader
                    title={t('Ready for approval')}
                    breadcrumb={
                        <div className="cc-caption flex items-center gap-2">
                            <Link
                                href={knowledge()}
                                className="text-cc-subtle transition-colors hover:text-cc-ink"
                            >
                                {t('Knowledge base')}
                            </Link>
                            <span>/</span>
                            <span className="font-medium text-cc-ink">
                                {t('Ready for approval')}
                            </span>
                        </div>
                    }
                    description={
                        <WithHowy
                            text={t(
                                'What Howy processed from uploads and mail. None of it is searched until you approve it.',
                            )}
                        />
                    }
                />

                {documents.length === 0 && mails.length === 0 && (
                    <div className="cc-panel cc-caption px-6 py-10 text-center">
                        {t('Nothing waits for your approval.')}
                    </div>
                )}

                {documents.length > 0 && (
                    <section className="flex flex-col gap-3">
                        <h2 className="cc-label">
                            {t('Documents (:count)', {
                                count: documents.length,
                            })}
                        </h2>
                        <div className="cc-panel overflow-hidden">
                            {documents.map((document) => (
                                <DocumentRow
                                    key={document.versionId}
                                    document={document}
                                />
                            ))}
                        </div>
                    </section>
                )}

                {mails.length > 0 && (
                    <section className="flex flex-col gap-3">
                        <h2 className="cc-label">
                            {t('From mail (:count)', { count: mails.length })}
                        </h2>
                        <div className="flex flex-col gap-4">
                            {mails.map((mail) => (
                                <MailCard key={mail.emailId} mail={mail} />
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}

function DocumentRow({ document }: { document: ApprovalDocument }) {
    const t = useTranslations();
    const formatDate = useFormatDate();
    const [first, ...others] = document.sources;

    return (
        <div className="grid grid-cols-[20px_minmax(0,1fr)] items-center gap-x-3 gap-y-2 border-b border-cc-border px-6 py-4 last:border-b-0 lg:grid-cols-[28px_minmax(0,1fr)_minmax(0,280px)_110px_auto] lg:gap-5">
            <FileText className="size-5 text-cc-ink" strokeWidth={1.8} />

            <div className="flex min-w-0 flex-col gap-1">
                <Link
                    href={documentRoutes.show(document.documentId)}
                    className="truncate text-[15px] font-semibold hover:underline"
                >
                    {document.title}
                </Link>
                <div className="cc-caption flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span>
                        {document.replaces !== null
                            ? t('Version :number, replaces version :replaces', {
                                  number: document.versionNumber,
                                  replaces: document.replaces,
                              })
                            : t('New document')}
                    </span>
                    {document.hint && (
                        <Tag
                            kind="pending"
                            label={
                                document.hint.kind === 'same_content'
                                    ? t('Same text as :title', {
                                          title: document.hint.title ?? '',
                                      })
                                    : t('Looks like :title', {
                                          title: document.hint.title ?? '',
                                      })
                            }
                        />
                    )}
                </div>
                {document.error && (
                    <p className="text-[13px] text-cc-pending-fg">
                        {document.error}
                    </p>
                )}
            </div>

            <div className="col-start-2 flex min-w-0 flex-wrap items-center gap-1.5 lg:col-start-auto">
                {first?.kind === 'mail' ? (
                    <EmailSourceChip
                        subject={[
                            first.from,
                            first.subject || t('(no subject)'),
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                        emailId={first.emailId}
                    />
                ) : (
                    <span className="cc-caption inline-flex items-center gap-1.5">
                        <Upload className="size-3.5 shrink-0" />
                        {t('Uploaded by :name', {
                            name: first?.user ?? t('unknown'),
                        })}
                    </span>
                )}
                {others.length > 0 && (
                    <span className="cc-caption">+{others.length}</span>
                )}
            </div>

            <div className="cc-caption col-start-2 lg:col-start-auto">
                {document.processedAt &&
                    formatDate(document.processedAt, {
                        dateStyle: undefined,
                        day: 'numeric',
                        month: 'short',
                    })}
            </div>

            <Button
                variant="outline"
                className="col-start-2 h-10 justify-self-start lg:col-start-auto"
                asChild
            >
                <Link href={documentRoutes.show(document.documentId)}>
                    {t('Review')}
                </Link>
            </Button>
        </div>
    );
}

function MailCard({ mail }: { mail: ApprovalMail }) {
    const t = useTranslations();
    const formatDate = useFormatDate();

    return (
        <article className="cc-panel flex flex-col gap-4 p-6">
            <div className="flex items-start gap-3">
                <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-cc-raised text-cc-ink">
                    <Mail className="size-[18px]" strokeWidth={1.8} />
                </div>
                <div className="flex min-w-0 flex-col gap-0.5">
                    <Link
                        href={emailRoutes.show(mail.emailId)}
                        className="truncate text-[15px] font-semibold hover:underline"
                    >
                        {mail.subject || t('(no subject)')}
                    </Link>
                    <div className="cc-caption">
                        {[
                            mail.from,
                            mail.receivedAt &&
                                formatDate(mail.receivedAt, {
                                    dateStyle: 'medium',
                                    timeStyle: 'short',
                                }),
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    </div>
                </div>
            </div>

            {mail.summary && (
                <p className="text-[14px] leading-[1.55] text-cc-muted">
                    {mail.summary}
                </p>
            )}

            <ul className="flex flex-col gap-3">
                {mail.statements.map((statement) => (
                    <StatementCard
                        key={statement.index}
                        statement={statement}
                        light
                    >
                        <SenderRequestNote statement={statement} />
                        <Decision
                            emailId={mail.emailId}
                            statement={statement}
                        />
                    </StatementCard>
                ))}
            </ul>
        </article>
    );
}

function Decision({
    emailId,
    statement,
}: {
    emailId: number;
    statement: InterpretedStatement;
}) {
    const t = useTranslations();
    const [busy, setBusy] = useState(false);

    const decide = (action: 'approve' | 'reject' | 'handled') =>
        router.post(
            EmailStatementController[action].url({
                email: emailId,
                statement: statement.index,
            }),
            {},
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
            },
        );

    // In the knowledge base already: the sender's request is for an admin to
    // deal with by hand, and then to mark done.
    if (statement.review !== 'pending') {
        return (
            <div className="flex flex-wrap gap-2 pt-1">
                <Button
                    variant="outline"
                    className="h-10"
                    disabled={busy}
                    onClick={() => decide('handled')}
                >
                    <Check />
                    {t('Mark as done')}
                </Button>
            </div>
        );
    }

    return (
        <div className="flex flex-wrap gap-2 pt-1">
            <Button
                className="h-10"
                disabled={busy}
                onClick={() => decide('approve')}
            >
                <Check />
                {statement.verdict === 'conflict'
                    ? t('Approve change')
                    : t('Add to knowledge base')}
            </Button>
            <Button
                variant="outline"
                className="h-10"
                disabled={busy}
                onClick={() => decide('reject')}
            >
                <X />
                {t('Reject')}
            </Button>
        </div>
    );
}
