import { CircleHelp } from 'lucide-react';
import { HowyAvatar } from '@/components/brand/howy-avatar';
import { WithHowy } from '@/components/brand/howy-name';
import { Tag } from '@/components/cc/tag';
import {
    EmailSourceChip,
    SourceChip,
} from '@/components/knowledge/source-chip';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslations } from '@/hooks/use-translations';
import type { Interpretation } from '@/types';

export const replyAnchor = (messageId: number) => `reply-${messageId}`;

/**
 * Whether a message has a reply from Howy worth showing in the thread.
 */
export function hasCcReply(interpretation: Interpretation | null): boolean {
    if (interpretation === null) {
        return false;
    }

    // An answer found for someone else but not sure enough to suggest is
    // still shown, so the people in the conversation can judge it.
    if (interpretation.outcome === 'unsure') {
        return interpretation.answer !== null;
    }

    return (
        interpretation.replyStatus !== 'none' &&
        (interpretation.answer !== null || interpretation.replyText !== null)
    );
}

/**
 * The reply Howy wrote to a message, shown in the thread itself, above the
 * message it answers: an answer to the sender, or, when the mailbox was only
 * copied, a suggestion to the people who were asked. Gmail files a sent reply
 * under Sent, not the inbox, so it never arrives as a message of its own; this
 * is where it is seen.
 */
export function CcReply({
    messageId,
    interpretation,
}: {
    messageId: number;
    interpretation: Interpretation;
}) {
    const t = useTranslations();
    const formatDate = useFormatDate();
    const sent = interpretation.replyStatus === 'sent';
    const outcome = interpretation.outcome;
    const unsure = outcome === 'unsure';
    const answered =
        (outcome === 'answered' || outcome === 'suggested' || unsure) &&
        interpretation.answer !== null;
    const recipients = interpretation.replyRecipients.join(', ');

    const title =
        outcome === 'suggested'
            ? t('Suggestion from Howy')
            : unsure
              ? t('Answer Howy did not suggest')
              : t('Reply from Howy');
    const caption =
        outcome === 'suggested'
            ? t('Only to :recipients, not to the sender', { recipients })
            : unsure
              ? t('Not sure enough to suggest (:percent% sure)', {
                    percent: Math.round(
                        (interpretation.answerConfidence ?? 0) * 100,
                    ),
                })
              : [
                    answered
                        ? t('Answer from the knowledge base')
                        : interpretation.outcomeLabel,
                    recipients ? t('to :recipients', { recipients }) : null,
                ]
                    .filter(Boolean)
                    .join(' · ');

    return (
        <li
            id={replyAnchor(messageId)}
            className={`scroll-mt-24 border-b border-cc-border last:border-b-0 ${unsure ? 'bg-cc-bg' : 'bg-cc-accent-tint/40'}`}
        >
            <div
                className={`flex flex-col gap-3.5 border-l-[3px] px-7 py-5 ${unsure ? 'border-cc-border-strong' : 'border-cc-accent'}`}
            >
                <div className="flex flex-wrap items-center gap-3">
                    <HowyAvatar size={40} />
                    <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                        <span className="text-[15px] font-semibold">
                            <WithHowy text={title} />
                        </span>
                        <span className="cc-caption">{caption}</span>
                    </span>
                    {interpretation.replyStatus !== 'none' && (
                        <Tag
                            kind={
                                sent
                                    ? 'decision'
                                    : interpretation.replyStatus === 'blocked'
                                      ? 'pending'
                                      : 'action'
                            }
                            label={interpretation.replyStatusLabel}
                        />
                    )}
                    {sent && interpretation.repliedAt && (
                        <span className="cc-caption">
                            {formatDate(interpretation.repliedAt, {
                                dateStyle: 'medium',
                                timeStyle: 'short',
                            })}
                        </span>
                    )}
                </div>

                <div className="flex flex-col gap-3.5 sm:pl-[52px]">
                    {interpretation.replyStatus === 'blocked' && (
                        <p className="cc-caption">
                            {t(
                                'Not sent: the reply settings of the mailbox do not allow replying to this sender.',
                            )}
                        </p>
                    )}
                    {interpretation.replyStatus === 'failed' &&
                        interpretation.error && (
                            <p className="cc-caption">
                                {t('Not sent: :error', {
                                    error: interpretation.error,
                                })}
                            </p>
                        )}

                    <div className="rounded-xl border-[1.5px] border-cc-border bg-cc-panel px-5 py-4 text-[15px] leading-[1.6] whitespace-pre-line">
                        {answered
                            ? interpretation.answer
                            : interpretation.replyText}
                    </div>

                    {answered && interpretation.answerGaps && (
                        <div className="flex gap-3 rounded-xl border-[1.5px] border-dashed border-cc-border-strong bg-cc-panel px-5 py-3.5">
                            <CircleHelp className="mt-0.5 size-4 shrink-0 text-cc-pending-fg" />
                            <div className="flex flex-col gap-1">
                                <span className="text-[13px] font-semibold">
                                    {t('Not in the knowledge base yet')}
                                </span>
                                <span className="text-[14px] leading-[1.55] text-cc-muted">
                                    {interpretation.answerGaps}
                                </span>
                            </div>
                        </div>
                    )}

                    {answered && interpretation.citations.length > 0 && (
                        <div className="flex flex-col gap-2">
                            <span className="cc-label">{t('Sources')}</span>
                            <ul className="flex flex-wrap gap-1.5">
                                {interpretation.citations.map(
                                    (source, index) => (
                                        <li key={index} className="max-w-full">
                                            {source.type === 'email' ? (
                                                <EmailSourceChip
                                                    subject={
                                                        source.title ||
                                                        t('(no subject)')
                                                    }
                                                    emailId={source.id}
                                                />
                                            ) : (
                                                <SourceChip
                                                    documentId={source.id}
                                                    title={source.title}
                                                    headingPath={
                                                        source.headingPath
                                                    }
                                                    pageFrom={source.page}
                                                    sectionId={source.sectionId}
                                                />
                                            )}
                                        </li>
                                    ),
                                )}
                            </ul>
                        </div>
                    )}
                </div>
            </div>
        </li>
    );
}
