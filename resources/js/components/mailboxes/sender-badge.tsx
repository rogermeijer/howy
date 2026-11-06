import { AlertTriangle, Trash2, X } from 'lucide-react';
import {
    AccessMenuItems,
    useAccessLooks,
} from '@/components/mailboxes/sender-access';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import type { SenderAccess, SenderRule } from '@/types';

/** A domain shows as @klant.nl, an address as itself. */
export const shownEntry = (entry: string): string =>
    entry.includes('@') ? entry : `@${entry}`;

/**
 * One sender on a mailbox's list, in the colour of what Howy may do with
 * their mail. Click it to change that or to take it off the list; read-only
 * without the handlers.
 */
export function SenderBadge({
    rule,
    error,
    onChange,
    onRemove,
}: {
    rule: SenderRule;
    error?: string;
    onChange?: (access: SenderAccess) => void;
    onRemove?: () => void;
}) {
    const t = useTranslations();
    const look = useAccessLooks()[rule.access];

    const text = (
        <>
            {error && <AlertTriangle className="size-3.5 shrink-0" />}
            <span
                className={cn(
                    'truncate',
                    rule.access === 'ignore' &&
                        'line-through decoration-current/50',
                )}
            >
                {shownEntry(rule.entry)}
            </span>
        </>
    );

    const tone = error
        ? 'bg-cc-pending-bg text-cc-pending-fg ring-1 ring-cc-pending-fg/50'
        : look.pill;

    if (!onChange || !onRemove) {
        return (
            <span
                title={error ?? look.label}
                className={cn(
                    'inline-flex h-[30px] max-w-full items-center gap-1.5 rounded-full px-3 text-[13px] font-medium',
                    tone,
                )}
            >
                {text}
            </span>
        );
    }

    return (
        <span
            className={cn(
                'inline-flex h-[30px] max-w-full items-center rounded-full text-[13px] font-medium',
                tone,
            )}
        >
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <button
                        type="button"
                        title={error ?? look.label}
                        aria-label={t(':sender: :access. Change', {
                            sender: shownEntry(rule.entry),
                            access: look.label,
                        })}
                        className="inline-flex h-full min-w-0 items-center gap-1.5 rounded-l-full pr-1 pl-3 hover:underline focus-visible:underline focus-visible:outline-none"
                    >
                        {text}
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="start"
                    className="w-64 rounded-[14px] border-cc-border p-1.5 shadow-[0_12px_32px_rgba(13,27,30,0.12)]"
                >
                    <DropdownMenuLabel className="truncate px-2.5 text-[12.5px] font-medium text-cc-subtle">
                        {shownEntry(rule.entry)}
                    </DropdownMenuLabel>
                    {error && (
                        <p className="px-2.5 pb-1.5 text-[12px] font-medium text-cc-pending-fg">
                            {error}
                        </p>
                    )}
                    <AccessMenuItems value={rule.access} onChange={onChange} />
                    <DropdownMenuSeparator className="mx-1.5 my-1 bg-cc-border" />
                    <DropdownMenuItem
                        onSelect={onRemove}
                        className="rounded-[10px] px-2.5 py-2 text-[13.5px] font-semibold text-cc-pending-fg focus:bg-cc-pending-bg focus:text-cc-pending-fg [&_svg]:!text-cc-pending-fg"
                    >
                        <Trash2 />
                        {t('Remove from the list')}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            <button
                type="button"
                onClick={onRemove}
                aria-label={t('Remove :sender', {
                    sender: shownEntry(rule.entry),
                })}
                className="mr-[3px] flex size-6 shrink-0 items-center justify-center rounded-full opacity-70 hover:bg-current/10 hover:opacity-100 focus-visible:opacity-100 focus-visible:outline-none"
            >
                <X className="size-3.5" strokeWidth={2.5} />
            </button>
        </span>
    );
}
