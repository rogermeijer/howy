import { Link } from '@inertiajs/react';
import { Reply } from 'lucide-react';
import { type SyntheticEvent, useState } from 'react';
import { BRAND_NAME } from '@/components/brand/brand';
import { WithHowy } from '@/components/brand/howy-name';
import { MailAvatar, MessageRow } from '@/components/mail/message-parts';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import sentRoutes from '@/routes/colleagues/sent';
import { show as showEmail } from '@/routes/emails';
import type { SentMail, SentMailStatus } from '@/types';

export const STATUS_TAGS: Record<SentMailStatus, string> = {
    sent: 'cc-tag-decision',
    pending: 'cc-tag-pending',
    skipped: 'cc-tag-noise',
    failed: 'cc-tag-action',
};

/**
 * One logged mail, opened from the log the way a mail in the inbox reads:
 * who it went to and when, the mail as its recipient got it, and the replies
 * under it. What the mail says loads only now.
 */
export function SentMailDialog({
    mail,
    onClose,
}: {
    mail: SentMail | null;
    onClose: () => void;
}) {
    return (
        <Dialog
            open={mail !== null}
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            {mail !== null && (
                <DialogContent className="flex max-h-[calc(100svh-4rem)] flex-col gap-0 overflow-hidden rounded-2xl bg-cc-panel p-0 sm:max-w-[760px]">
                    <OpenedMail mail={mail} />
                </DialogContent>
            )}
        </Dialog>
    );
}

function OpenedMail({ mail }: { mail: SentMail }) {
    const t = useTranslations();
    const formatDate = useFormatDate();
    const title = mail.subject || t('(no subject)');

    const recipients = [
        t('to :recipients', { recipients: mail.recipient }),
        ...(mail.cc.length > 0
            ? [t('cc :recipients', { recipients: mail.cc.join(', ') })]
            : []),
    ];

    return (
        <>
            <DialogHeader className="gap-2 border-b border-cc-border px-7 pt-6 pb-4 text-left">
                <div className="flex flex-wrap items-center gap-2 pr-8">
                    <span className={cn('cc-tag', STATUS_TAGS[mail.status])}>
                        {mail.statusLabel}
                    </span>
                    <span className="cc-caption">{mail.kindLabel}</span>
                </div>
                <DialogTitle className="text-[20px] leading-[1.25] font-semibold tracking-[-0.02em]">
                    {title}
                </DialogTitle>
                <DialogDescription
                    className={cn(
                        'text-[13px]',
                        mail.reason ? 'text-cc-pending-fg' : 'sr-only',
                    )}
                >
                    <WithHowy
                        text={
                            mail.reason ??
                            t('What Howy sent :recipient.', {
                                recipient: mail.recipient,
                            })
                        }
                    />
                </DialogDescription>
                {/* Who it went from and to, staying in view as the mail scrolls. */}
                <div className="flex items-center gap-2.5 pt-1">
                    <MailAvatar name={BRAND_NAME} small howy />
                    <p className="min-w-0 flex-1 truncate text-[13px] text-cc-subtle">
                        <span className="font-semibold text-cc-ink">
                            {BRAND_NAME}
                        </span>
                        {mail.mailbox && ` ${mail.mailbox}`}
                        {' · '}
                        {recipients.join(' · ')}
                    </p>
                    {mail.sentAt && (
                        <span className="cc-caption shrink-0">
                            {formatDate(mail.sentAt, {
                                dateStyle: 'medium',
                                timeStyle: 'short',
                            })}
                        </span>
                    )}
                </div>
            </DialogHeader>

            <div className="min-h-0 flex-1 overflow-y-auto">
                {mail.answers !== null && (
                    <p className="flex items-center gap-2 border-b border-cc-border px-7 py-3 text-[13px] text-cc-subtle">
                        <Reply className="size-3.5 shrink-0" />
                        <span className="min-w-0 truncate">
                            {t('In answer to')}{' '}
                            <Link
                                href={showEmail(mail.answers.id)}
                                className="font-medium text-cc-ink underline-offset-2 hover:underline"
                            >
                                {mail.answers.subject || t('(no subject)')}
                            </Link>
                        </span>
                    </p>
                )}
                {mail.hasContent ? (
                    <MailFrame mail={mail} />
                ) : (
                    <p className="cc-caption px-7 py-6">
                        <WithHowy
                            text={t(
                                'Howy did not write this mail out, because it did not go.',
                            )}
                        />
                    </p>
                )}

                {mail.replies.length > 0 && (
                    <ol className="border-t border-cc-border">
                        {mail.replies.map((reply) => (
                            <li
                                key={reply.id}
                                className="border-b border-cc-border last:border-b-0"
                            >
                                <MessageRow
                                    sender={
                                        reply.fromName ?? reply.fromEmail ?? ''
                                    }
                                    excerpt={reply.snippet}
                                    date={reply.receivedAt}
                                    href={showEmail(reply.id)}
                                />
                            </li>
                        ))}
                    </ol>
                )}
            </div>
        </>
    );
}

/**
 * The mail as its recipient got it: the stored HTML, in a frame that grows
 * with it. Nothing in it runs; the frame only reads its height.
 */
function MailFrame({ mail }: { mail: SentMail }) {
    const t = useTranslations();
    // Small to start with: a frame reports at least its own height, so a
    // short mail would keep the space of a long one.
    const [height, setHeight] = useState(160);

    const fit = (event: SyntheticEvent<HTMLIFrameElement>) => {
        const document = event.currentTarget.contentDocument;

        if (document) {
            setHeight(document.documentElement.scrollHeight);
        }
    };

    return (
        <iframe
            title={mail.subject ?? t('(no subject)')}
            src={sentRoutes.mail.url(mail.id)}
            sandbox="allow-same-origin"
            onLoad={fit}
            style={{ height, colorScheme: 'light' }}
            className="block w-full border-0"
        />
    );
}
