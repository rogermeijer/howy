import { Link } from '@inertiajs/react';
import { Paperclip } from 'lucide-react';
import type { ComponentProps } from 'react';
import { useFormatDate } from '@/hooks/use-format-date';
import { useInitials } from '@/hooks/use-initials';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';

/**
 * Who wrote a message, as initials in a round badge; Howy's own in lime.
 */
export function MailAvatar({
    name,
    small = false,
    howy = false,
}: {
    name: string;
    small?: boolean;
    howy?: boolean;
}) {
    const initials = useInitials();

    return (
        <span
            className={cn(
                'flex shrink-0 items-center justify-center rounded-full font-semibold text-cc-ink',
                howy ? 'bg-cc-accent' : 'bg-cc-raised',
                small ? 'size-8 text-[12px]' : 'size-10 text-[13px]',
            )}
        >
            {initials(name)}
        </span>
    );
}

/**
 * The head of an opened message: who sent it, to whom, and when. A button
 * that folds the message, where it can fold.
 */
export function MessageHead({
    sender,
    senderEmail,
    recipients,
    date,
    howy = false,
    onFold,
}: {
    sender: string;
    /** Beside the name, when there is one. */
    senderEmail: string | null;
    /** "to …", "cc …". */
    recipients: string[];
    date: string | null;
    howy?: boolean;
    onFold?: () => void;
}) {
    const formatDate = useFormatDate();

    const content = (
        <>
            <MailAvatar name={sender} howy={howy} />
            <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                <span className="truncate text-[15px] font-semibold">
                    {sender}{' '}
                    {senderEmail && (
                        <span className="font-normal text-cc-subtle">
                            {senderEmail}
                        </span>
                    )}
                </span>
                {recipients.length > 0 && (
                    <span className="cc-caption truncate">
                        {recipients.join(' · ')}
                    </span>
                )}
            </span>
            {date && (
                <span className="cc-caption shrink-0">
                    {formatDate(date, {
                        dateStyle: 'medium',
                        timeStyle: 'short',
                    })}
                </span>
            )}
        </>
    );
    const className =
        'flex w-full items-center gap-3.5 px-7 pt-5 pb-3 text-left';

    return onFold ? (
        <button
            type="button"
            onClick={onFold}
            aria-expanded
            className={cn(className, 'cursor-pointer')}
        >
            {content}
        </button>
    ) : (
        <div className={className}>{content}</div>
    );
}

/**
 * A message folded to one line: who, the start of what they wrote, and when.
 * Opens in place, or links to where it is read.
 */
export function MessageRow({
    sender,
    excerpt,
    date,
    attachment = false,
    onOpen,
    href,
}: {
    sender: string;
    excerpt: string;
    date: string | null;
    attachment?: boolean;
    onOpen?: () => void;
    href?: ComponentProps<typeof Link>['href'];
}) {
    const t = useTranslations();
    const formatDate = useFormatDate();

    const content = (
        <>
            <span className="flex justify-center">
                <MailAvatar name={sender} small />
            </span>
            <span className="truncate text-[14px] font-semibold">{sender}</span>
            <span className="hidden truncate text-[14px] text-cc-muted sm:block">
                {excerpt}
            </span>
            <span className="cc-caption flex items-center gap-2 whitespace-nowrap">
                {attachment && (
                    <Paperclip
                        className="size-3.5"
                        aria-label={t('Attachment')}
                    />
                )}
                {date &&
                    formatDate(date, {
                        dateStyle: undefined,
                        day: 'numeric',
                        month: 'short',
                    })}
            </span>
        </>
    );
    const className =
        'grid w-full cursor-pointer grid-cols-[40px_minmax(0,1fr)_auto] items-center gap-3.5 px-7 py-3.5 text-left transition-colors hover:bg-cc-bg sm:grid-cols-[40px_160px_minmax(0,1fr)_auto]';

    return href !== undefined ? (
        <Link href={href} className={className}>
            {content}
        </Link>
    ) : (
        <button
            type="button"
            onClick={onOpen}
            aria-expanded={false}
            className={className}
        >
            {content}
        </button>
    );
}
