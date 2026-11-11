import { router } from '@inertiajs/react';
import { ChevronRight, MoreHorizontal, RotateCw } from 'lucide-react';
import { useState } from 'react';
import {
    SentMailDialog,
    STATUS_TAGS,
} from '@/components/colleagues/sent-mail-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import sentRoutes from '@/routes/colleagues/sent';
import type { SentMail } from '@/types';

/**
 * Logged mails, newest first: to whom, what kind, whether it went, and
 * whether anyone wrote back. Each opens in a dialog, read the way a mail in
 * the inbox is; one of Howy's own goes again from its menu. Without the
 * recipient on a colleague's own page.
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

    // The whole row opens the mail; the menu sits above it.
    return (
        <li className="relative flex flex-wrap items-center gap-x-4 gap-y-1.5 px-6 py-3.5 transition-colors hover:bg-cc-bg">
            <button
                type="button"
                onClick={onOpen}
                aria-haspopup="dialog"
                className="flex min-w-0 flex-1 basis-[260px] cursor-pointer flex-col gap-0.5 text-left after:absolute after:inset-0"
            >
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
            </button>
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
            {mail.canResend ? (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label={t('Actions for :mail', {
                                mail: mail.subject || t('(no subject)'),
                            })}
                            className="relative z-[1] size-8 shrink-0 rounded-full text-cc-subtle hover:bg-cc-raised hover:text-cc-ink"
                        >
                            <MoreHorizontal className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-52">
                        <DropdownMenuItem
                            onSelect={() =>
                                router.post(
                                    sentRoutes.resend.url(mail.id),
                                    {},
                                    {
                                        preserveScroll: true,
                                        preserveState: true,
                                    },
                                )
                            }
                        >
                            <RotateCw />
                            {t('Send again')}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            ) : (
                <span aria-hidden="true" className="size-8 shrink-0" />
            )}
            <ChevronRight
                aria-hidden="true"
                className="size-4 shrink-0 text-cc-subtle"
            />
        </li>
    );
}
