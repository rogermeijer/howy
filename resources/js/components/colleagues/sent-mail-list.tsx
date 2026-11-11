import { ChevronRight } from 'lucide-react';
import { useState } from 'react';
import {
    SentMailDialog,
    STATUS_TAGS,
} from '@/components/colleagues/sent-mail-dialog';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import type { SentMail } from '@/types';

/**
 * Logged mails, newest first: to whom, what kind, whether it went, and
 * whether anyone wrote back. Each opens in a dialog, read the way a mail in
 * the inbox is. Without the recipient on a colleague's own page.
 */
export function SentMailList({
    mails,
    showRecipient = true,
}: {
    mails: SentMail[];
    showRecipient?: boolean;
}) {
    const [openId, setOpenId] = useState<number | null>(null);

    return (
        <>
            <ul className="flex flex-col divide-y divide-cc-border">
                {mails.map((mail) => (
                    <SentMailRow
                        key={mail.id}
                        mail={mail}
                        showRecipient={showRecipient}
                        onOpen={() => setOpenId(mail.id)}
                    />
                ))}
            </ul>
            <SentMailDialog
                mail={mails.find((mail) => mail.id === openId) ?? null}
                onClose={() => setOpenId(null)}
            />
        </>
    );
}

function SentMailRow({
    mail,
    showRecipient,
    onOpen,
}: {
    mail: SentMail;
    showRecipient: boolean;
    onOpen: () => void;
}) {
    const t = useTranslations();
    const formatDate = useFormatDate();
    const at = mail.sentAt ?? mail.createdAt;

    return (
        <li>
            <button
                type="button"
                onClick={onOpen}
                aria-haspopup="dialog"
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
                    {mail.replies.length > 0 && (
                        <span className="cc-tag cc-tag-question">
                            {mail.replies.length === 1
                                ? t('1 reply')
                                : t(':count replies', {
                                      count: mail.replies.length,
                                  })}
                        </span>
                    )}
                    <span className={cn('cc-tag', STATUS_TAGS[mail.status])}>
                        {mail.statusLabel}
                    </span>
                </span>
                <ChevronRight className="size-4 shrink-0 text-cc-subtle" />
            </button>
        </li>
    );
}
