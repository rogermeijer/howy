import { Form, Link } from '@inertiajs/react';
import { ChevronDown, RotateCw } from 'lucide-react';
import { type SyntheticEvent, useState } from 'react';
import { WithHowy } from '@/components/brand/howy-name';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { show as showColleague } from '@/routes/colleagues';
import sentRoutes from '@/routes/colleagues/sent';
import { show as showEmail } from '@/routes/emails';
import type { SentMail, SentMailStatus } from '@/types';

const STATUS_TAGS: Record<SentMailStatus, string> = {
    sent: 'cc-tag-decision',
    pending: 'cc-tag-pending',
    skipped: 'cc-tag-noise',
    failed: 'cc-tag-action',
};

/**
 * Logged mails, newest first: to whom, what kind, whether it went, and
 * whether anyone wrote back. Open one to read what Howy wrote. Without the
 * recipient column on a colleague's own page.
 */
export function SentMailList({
    mails,
    showRecipient = true,
}: {
    mails: SentMail[];
    showRecipient?: boolean;
}) {
    return (
        <ul className="flex flex-col divide-y divide-cc-border">
            {mails.map((mail) => (
                <SentMailRow
                    key={mail.id}
                    mail={mail}
                    showRecipient={showRecipient}
                />
            ))}
        </ul>
    );
}

function SentMailRow({
    mail,
    showRecipient,
}: {
    mail: SentMail;
    showRecipient: boolean;
}) {
    const t = useTranslations();
    const formatDate = useFormatDate();
    const [open, setOpen] = useState(false);
    const at = mail.sentAt ?? mail.createdAt;

    return (
        <li>
            <button
                type="button"
                onClick={() => setOpen(!open)}
                aria-expanded={open}
                className="flex w-full cursor-pointer flex-wrap items-center gap-x-4 gap-y-1.5 px-6 py-3.5 text-left transition-colors hover:bg-cc-bg"
            >
                <span className="flex min-w-0 flex-1 basis-[260px] flex-col gap-0.5">
                    <span className="truncate text-[14.5px] font-medium">
                        {mail.subject || t('(no subject)')}
                    </span>
                    <span className="truncate text-[13px] text-cc-subtle">
                        {[
                            mail.kindLabel,
                            showRecipient
                                ? (mail.colleague?.name ?? mail.recipient)
                                : null,
                            at ? formatDate(at, { dateStyle: 'medium' }) : null,
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    </span>
                </span>
                <span className="flex shrink-0 items-center gap-2">
                    {mail.replyIds.length > 0 && (
                        <span className="cc-tag cc-tag-question">
                            {mail.replyIds.length === 1
                                ? t('1 reply')
                                : t(':count replies', {
                                      count: mail.replyIds.length,
                                  })}
                        </span>
                    )}
                    <span className={cn('cc-tag', STATUS_TAGS[mail.status])}>
                        {mail.statusLabel}
                    </span>
                </span>
                <ChevronDown
                    className={cn(
                        'size-4 shrink-0 text-cc-subtle transition-transform',
                        open && 'rotate-180',
                    )}
                />
            </button>

            {open && (
                <div className="flex flex-col gap-3 px-6 pb-5">
                    <dl className="grid gap-x-4 gap-y-1 text-[13px] sm:grid-cols-[110px_minmax(0,1fr)]">
                        <dt className="text-cc-subtle">{t('To')}</dt>
                        <dd className="min-w-0 break-words">
                            {mail.colleague ? (
                                <Link
                                    href={showColleague(mail.colleague.id)}
                                    className="font-medium underline-offset-2 hover:underline"
                                >
                                    {mail.recipient}
                                </Link>
                            ) : (
                                mail.recipient
                            )}
                            {mail.cc.length > 0 &&
                                ` · ${t('cc :recipients', { recipients: mail.cc.join(', ') })}`}
                        </dd>
                        {mail.mailbox && (
                            <>
                                <dt className="text-cc-subtle">{t('From')}</dt>
                                <dd className="min-w-0 break-words">
                                    {mail.mailbox}
                                </dd>
                            </>
                        )}
                        {mail.reason && (
                            <>
                                <dt className="text-cc-subtle">
                                    {t('Why not')}
                                </dt>
                                <dd className="min-w-0 break-words text-cc-pending-fg">
                                    <WithHowy text={mail.reason} />
                                </dd>
                            </>
                        )}
                        {(mail.emailId !== null ||
                            mail.replyIds.length > 0) && (
                            <>
                                <dt className="text-cc-subtle">{t('Mail')}</dt>
                                <dd className="flex flex-wrap gap-x-3">
                                    {mail.emailId !== null && (
                                        <Link
                                            href={showEmail(mail.emailId)}
                                            className="font-medium underline-offset-2 hover:underline"
                                        >
                                            {t('The mail it answers')}
                                        </Link>
                                    )}
                                    {mail.replyIds.map((id, index) => (
                                        <Link
                                            key={id}
                                            href={showEmail(id)}
                                            className="font-medium underline-offset-2 hover:underline"
                                        >
                                            {mail.replyIds.length === 1
                                                ? t('The reply')
                                                : t('Reply :number', {
                                                      number: index + 1,
                                                  })}
                                        </Link>
                                    ))}
                                </dd>
                            </>
                        )}
                    </dl>
                    <MailFrame mail={mail} />
                    {mail.canResend && (
                        <Form
                            {...sentRoutes.resend.form(mail.id)}
                            options={{ preserveScroll: true }}
                            className="self-start"
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="outline"
                                    className="h-10"
                                    disabled={processing}
                                >
                                    <RotateCw />
                                    {t('Send again')}
                                </Button>
                            )}
                        </Form>
                    )}
                </div>
            )}
        </li>
    );
}

/**
 * The mail as its recipient got it: the stored HTML, in a frame that grows
 * with it. Nothing in it runs; the frame only reads its height.
 */
function MailFrame({ mail }: { mail: SentMail }) {
    const t = useTranslations();
    const [height, setHeight] = useState(480);

    const fit = (event: SyntheticEvent<HTMLIFrameElement>) => {
        const document = event.currentTarget.contentDocument;

        if (document) {
            setHeight(document.documentElement.scrollHeight);
        }
    };

    return (
        <div className="overflow-hidden rounded-xl border-[1.5px] border-cc-border bg-cc-bg">
            <iframe
                title={mail.subject ?? t('(no subject)')}
                src={sentRoutes.mail.url(mail.id)}
                sandbox="allow-same-origin"
                onLoad={fit}
                style={{ height, colorScheme: 'light' }}
                className="block w-full border-0"
            />
        </div>
    );
}
