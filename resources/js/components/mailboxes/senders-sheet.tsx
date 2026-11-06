import { Building2, ChevronDown, Copy, Search, User, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    AccessMenuItems,
    AccessPicker,
    AccessTag,
    useAccessLooks,
} from '@/components/mailboxes/sender-access';
import { shownEntry } from '@/components/mailboxes/sender-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { useTranslations } from '@/hooks/use-translations';
import { ACCESSES } from '@/lib/mailbox-rules';
import { cn } from '@/lib/utils';
import type { SenderAccess, SenderRule } from '@/types';

type Filter = SenderAccess | 'all';

/**
 * Every sender on a mailbox's list, for when there are too many to show on
 * the card: found by search or by what Howy may do with them, changed or
 * removed one by one or several at once. It edits the same unsaved rules as
 * the card, which is where they are saved. Read-only without onChange.
 */
export function SendersSheet({
    open,
    onOpenChange,
    address,
    senders,
    onChange,
    filter: initialFilter,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    address: string;
    senders: SenderRule[];
    onChange?: (senders: SenderRule[]) => void;
    filter: Filter;
}) {
    const t = useTranslations();
    const looks = useAccessLooks();
    const [filter, setFilter] = useState<Filter>(initialFilter);
    const [query, setQuery] = useState('');
    const [selected, setSelected] = useState<string[]>([]);
    const [copied, setCopied] = useState(false);

    // Opened from a group's +N, the sheet shows that group.
    useEffect(() => {
        if (open) {
            setFilter(initialFilter);
            setQuery('');
            setSelected([]);
        }
    }, [open, initialFilter]);

    const needle = query.trim().toLowerCase().replace(/^@/, '');
    const shown = senders
        .filter(
            (rule) =>
                (filter === 'all' || rule.access === filter) &&
                (needle === '' || rule.entry.includes(needle)),
        )
        .sort((a, b) => a.entry.localeCompare(b.entry));
    const picked = selected.filter((entry) =>
        shown.some((rule) => rule.entry === entry),
    );

    const update = (entries: string[], access: SenderAccess | null) => {
        onChange?.(
            access === null
                ? senders.filter((rule) => !entries.includes(rule.entry))
                : senders.map((rule) =>
                      entries.includes(rule.entry) ? { ...rule, access } : rule,
                  ),
        );
        setSelected([]);
    };

    const toggle = (entry: string, on: boolean) =>
        setSelected(
            on
                ? [...selected, entry]
                : selected.filter((other) => other !== entry),
        );

    const copy = async () => {
        await navigator.clipboard.writeText(
            shown.map((rule) => shownEntry(rule.entry)).join('\n'),
        );
        setCopied(true);
        window.setTimeout(() => setCopied(false), 2000);
    };

    const count = (access: Filter) =>
        access === 'all'
            ? senders.length
            : senders.filter((rule) => rule.access === access).length;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                overlayClassName="bg-cc-ink/40"
                className="w-full gap-0 border-cc-border bg-cc-panel p-0 sm:max-w-[520px] [&>button:last-child]:hidden"
            >
                <div className="flex items-start gap-3 px-6 pt-6 pb-4">
                    <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                        <SheetTitle className="cc-subtitle">
                            {t('Exceptions')}
                        </SheetTitle>
                        <SheetDescription className="cc-caption truncate">
                            {t(':address · :count addresses and domains', {
                                address,
                                count: senders.length,
                            })}
                        </SheetDescription>
                    </div>
                    <SheetClose asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={t('Close the list')}
                            className="size-10 shrink-0"
                        >
                            <X className="size-5" />
                        </Button>
                    </SheetClose>
                </div>

                <div className="flex flex-col gap-3 border-b border-cc-border px-6 pb-4">
                    <div className="flex h-11 items-center gap-2 rounded-full border-[1.5px] border-cc-border-strong bg-cc-panel px-3.5 focus-within:border-cc-ink">
                        <Search className="size-4 shrink-0 text-cc-subtle" />
                        <input
                            type="search"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            aria-label={t('Find an address or domain')}
                            placeholder={t('Find an address or domain')}
                            className="min-w-0 flex-1 border-none bg-transparent text-[14px] outline-none placeholder:text-cc-faint"
                        />
                    </div>
                    <div
                        role="group"
                        aria-label={t('Show')}
                        className="flex flex-wrap gap-1.5"
                    >
                        {(['all', ...ACCESSES] as Filter[]).map((option) => (
                            <button
                                key={option}
                                type="button"
                                aria-pressed={filter === option}
                                onClick={() => setFilter(option)}
                                className={cn(
                                    'inline-flex h-8 items-center gap-1.5 rounded-full border-[1.5px] px-3 text-[13px] font-semibold',
                                    filter === option
                                        ? 'border-cc-ink bg-cc-ink text-cc-bg'
                                        : 'border-cc-border-strong bg-cc-panel text-cc-ink hover:border-cc-ink',
                                )}
                            >
                                {option !== 'all' && (
                                    <span
                                        className={cn(
                                            'size-2 rounded-full',
                                            looks[option].dot,
                                            filter === option &&
                                                'ring-1 ring-cc-bg',
                                        )}
                                    />
                                )}
                                {option === 'all'
                                    ? t('All')
                                    : looks[option].label}
                                <span className="font-medium opacity-70">
                                    {count(option)}
                                </span>
                            </button>
                        ))}
                    </div>
                </div>

                {/* Always there, so selecting never moves the list. */}
                {onChange && shown.length > 0 && (
                    <div className="mx-4 mt-3 flex min-h-12 flex-wrap items-center gap-2 rounded-xl bg-cc-raised py-2 pr-2 pl-3">
                        <Checkbox
                            checked={
                                picked.length === 0
                                    ? false
                                    : picked.length === shown.length
                                      ? true
                                      : 'indeterminate'
                            }
                            onCheckedChange={(checked) =>
                                setSelected(
                                    checked === true
                                        ? shown.map((rule) => rule.entry)
                                        : [],
                                )
                            }
                            aria-label={t('Select everything shown')}
                            className="size-[18px] border-[1.5px] border-cc-border-strong bg-cc-panel"
                        />
                        {picked.length === 0 ? (
                            <span className="flex-1 text-[13px] text-cc-subtle">
                                {t('Select several to change them at once')}
                            </span>
                        ) : (
                            <>
                                <span className="flex-1 text-[13px] font-semibold">
                                    {t(':count selected', {
                                        count: picked.length,
                                    })}
                                </span>
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button
                                            variant="outline"
                                            className="h-8 border-[1.5px] border-cc-border-strong bg-cc-panel pr-2.5 pl-3 text-[13px]"
                                        >
                                            {t('Set to')}
                                            <ChevronDown className="size-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        className="w-64 rounded-[14px] border-cc-border p-1.5"
                                    >
                                        <AccessMenuItems
                                            value={null}
                                            onChange={(access) =>
                                                update(picked, access)
                                            }
                                        />
                                    </DropdownMenuContent>
                                </DropdownMenu>
                                <Button
                                    variant="ghost"
                                    className="h-8 px-2.5 text-[13px] text-cc-pending-fg hover:bg-cc-pending-bg hover:text-cc-pending-fg"
                                    onClick={() => update(picked, null)}
                                >
                                    {t('Remove')}
                                </Button>
                            </>
                        )}
                    </div>
                )}

                <ul className="flex min-h-0 flex-1 flex-col gap-0.5 overflow-y-auto px-4 py-2">
                    {shown.map((rule) => {
                        const checked = picked.includes(rule.entry);
                        const Icon = rule.entry.includes('@')
                            ? User
                            : Building2;

                        return (
                            <li
                                key={rule.entry}
                                className={cn(
                                    // On a phone the choice goes under the address.
                                    'flex flex-wrap items-center gap-x-3 gap-y-1 rounded-[10px] py-1.5 pr-1.5 pl-3',
                                    checked && 'bg-cc-bg',
                                )}
                            >
                                {onChange && (
                                    <Checkbox
                                        checked={checked}
                                        onCheckedChange={(on) =>
                                            toggle(rule.entry, on === true)
                                        }
                                        aria-label={t('Select :sender', {
                                            sender: shownEntry(rule.entry),
                                        })}
                                        className="size-[18px] border-[1.5px] border-cc-border-strong bg-cc-panel"
                                    />
                                )}
                                <span className="flex min-w-0 flex-1 basis-[calc(100%-30px)] items-center gap-2 text-[14px] font-medium sm:basis-0">
                                    <Icon className="size-[15px] shrink-0 text-cc-subtle" />
                                    <span
                                        className={cn(
                                            'truncate',
                                            rule.access === 'ignore' &&
                                                'text-cc-subtle line-through',
                                        )}
                                    >
                                        {shownEntry(rule.entry)}
                                    </span>
                                </span>
                                {onChange ? (
                                    <span className="ml-[30px] flex items-center gap-1 sm:ml-0">
                                        <AccessPicker
                                            value={rule.access}
                                            onChange={(access) =>
                                                update([rule.entry], access)
                                            }
                                            label={shownEntry(rule.entry)}
                                            size="sm"
                                        />
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Remove :sender', {
                                                sender: shownEntry(rule.entry),
                                            })}
                                            className="size-8 shrink-0 text-cc-subtle"
                                            onClick={() =>
                                                update([rule.entry], null)
                                            }
                                        >
                                            <X className="size-4" />
                                        </Button>
                                    </span>
                                ) : (
                                    <AccessTag access={rule.access} />
                                )}
                            </li>
                        );
                    })}
                    {shown.length === 0 && (
                        <li className="cc-caption px-3 py-6 text-center">
                            {needle === ''
                                ? t('No senders here.')
                                : t('No sender matches ":query".', {
                                      query,
                                  })}
                        </li>
                    )}
                </ul>

                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-cc-border px-6 py-3.5">
                    <Button
                        variant="ghost"
                        className="h-10 px-3 text-[13px] text-cc-subtle"
                        onClick={copy}
                        disabled={shown.length === 0}
                    >
                        <Copy />
                        {copied ? t('Copied') : t('Copy list')}
                    </Button>
                    <div className="flex items-center gap-3">
                        {onChange && (
                            <span className="text-[12.5px] text-cc-subtle">
                                {t('You save at the mailbox')}
                            </span>
                        )}
                        <SheetClose asChild>
                            <Button className="h-10 bg-cc-ink px-5 text-cc-bg hover:bg-cc-ink/90">
                                {t('Back to the mailbox')}
                            </Button>
                        </SheetClose>
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    );
}
