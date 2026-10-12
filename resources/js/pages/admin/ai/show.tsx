import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { AdminShell, BackLink, Chip } from '@/components/admin/admin-shell';
import {
    formatCost,
    formatLatency,
    formatNumber,
} from '@/components/admin/format';
import { Section } from '@/components/cc/section';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslations } from '@/hooks/use-translations';
import { show as showAccount } from '@/routes/admin/accounts';
import { index } from '@/routes/admin/ai';
import type { AiCallDetail } from '@/types';

type Props = {
    call: AiCallDetail;
    retentionDays: number;
};

export default function AdminAiCall({ call, retentionDays }: Props) {
    const t = useTranslations();
    const formatDate = useFormatDate();

    const facts: [string, ReactNode][] = [
        [
            t('When'),
            call.created_at
                ? formatDate(call.created_at, { timeStyle: 'medium' })
                : '—',
        ],
        [
            t('Account'),
            <Link
                key="account"
                href={showAccount(call.account.id)}
                className="font-medium hover:text-cc-accent-deep"
            >
                {call.account.name}
            </Link>,
        ],
        [t('Step'), <code key="step">{call.step}</code>],
        [t('Agent'), call.agent ?? '—'],
        [t('Model'), `${call.provider} · ${call.model}`],
        [
            t('For'),
            call.subject_type
                ? `${call.subject_type} #${call.subject_id}${call.subject_label ? ` — ${call.subject_label}` : ''}`
                : '—',
        ],
        [
            t('Thread'),
            call.thread_key ? (
                <Link
                    key="thread"
                    href={index({
                        query: {
                            account: call.account.id,
                            thread: call.thread_key,
                        },
                    })}
                    className="font-mono text-[13px] hover:text-cc-accent-deep"
                >
                    {call.thread_key}
                </Link>
            ) : (
                '—'
            ),
        ],
        [
            t('Tokens'),
            t(':input in (:cached cached) · :output out', {
                input: formatNumber(call.input_tokens),
                cached: formatNumber(call.cached_input_tokens),
                output: formatNumber(call.output_tokens),
            }),
        ],
        [t('Cost'), formatCost(call.cost_micros)],
        [t('Time'), formatLatency(call.latency_ms)],
    ];

    if (call.input_count !== null) {
        facts.push([t('Texts'), formatNumber(call.input_count)]);
    }

    return (
        <AdminShell
            title={t('AI call #:id', { id: call.id })}
            description={
                <span className="flex flex-wrap gap-2">
                    {call.status === 'failed' ? (
                        <Chip tone="bad">{t('Failed')}</Chip>
                    ) : (
                        <Chip tone="good">{t('Succeeded')}</Chip>
                    )}
                    {call.is_batch && <Chip>{t('Batch')}</Chip>}
                </span>
            }
            breadcrumb={<BackLink href={index()}>{t('AI usage')}</BackLink>}
        >
            <Section title={t('Details')}>
                <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-[160px_minmax(0,1fr)]">
                    {facts.map(([label, value]) => (
                        <div key={label} className="contents">
                            <dt className="cc-caption">{label}</dt>
                            <dd className="text-sm break-words">{value}</dd>
                        </div>
                    ))}
                </dl>
                {call.error && (
                    <pre className="rounded-xl border border-cc-pending-fg/30 bg-cc-pending-bg p-4 font-mono text-[12px] whitespace-pre-wrap text-cc-pending-fg">
                        {call.error}
                    </pre>
                )}
            </Section>

            {call.payload ? (
                <>
                    {call.payload.attachments.length > 0 && (
                        <Section title={t('Attachments')}>
                            <ul className="cc-caption flex flex-col gap-1">
                                {call.payload.attachments.map((file, i) => (
                                    <li key={i}>
                                        {file.name ?? file.type}
                                        {file.mime_type &&
                                            ` · ${file.mime_type}`}
                                    </li>
                                ))}
                            </ul>
                        </Section>
                    )}
                    <PayloadBlock
                        title={t('Instructions')}
                        text={call.payload.instructions}
                    />
                    <PayloadBlock title={t('Sent')} text={call.payload.input} />
                    <PayloadBlock
                        title={t('Received')}
                        text={call.payload.output}
                    />
                </>
            ) : (
                <Section title={t('What was sent')}>
                    <p className="cc-caption">
                        {t(
                            'Not kept for this call. Sent and received text is deleted after :days days, and calls from before the AI log was added have none.',
                            { days: retentionDays },
                        )}
                    </p>
                </Section>
            )}
        </AdminShell>
    );
}

function PayloadBlock({ title, text }: { title: string; text: string | null }) {
    const t = useTranslations();

    if (text === null || text === '') {
        return null;
    }

    return (
        <Section
            title={title}
            description={t(':count characters', {
                count: text.length.toLocaleString('en-US'),
            })}
        >
            <pre className="max-h-[480px] overflow-auto rounded-xl border border-cc-border bg-cc-bg p-4 font-mono text-[12px] leading-[1.6] whitespace-pre-wrap">
                {text}
            </pre>
        </Section>
    );
}
