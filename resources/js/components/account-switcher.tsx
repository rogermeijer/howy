import { router, usePage } from '@inertiajs/react';
import { ArrowRightLeft, Search } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Spinner } from '@/components/ui/spinner';
import { useInitials } from '@/hooks/use-initials';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { accountSwitcher } from '@/routes';
import { update as updateCurrentAccount } from '@/routes/current-account';
import type { Account, AccountSwitcherResponse, Auth } from '@/types';

/** How long typing has to pause before the search goes out, in ms. */
const DEBOUNCE = 200;

/** A server answer, with the search it answers. */
type Result = AccountSwitcherResponse & { query: string };

function AccountInitials({
    name,
    className,
}: {
    name: string;
    className?: string;
}) {
    const getInitials = useInitials();

    return (
        <span
            aria-hidden="true"
            className={cn(
                'flex shrink-0 items-center justify-center rounded-md bg-cc-raised font-semibold text-cc-ink',
                className,
            )}
        >
            {getInitials(name)}
        </span>
    );
}

/**
 * The round switcher in the top bar. With a single account there is nothing to
 * switch to, so it renders nothing at all.
 *
 * The current account heads the panel; below it a search and at most five of
 * the other accounts, so it stays the same size with five accounts or fifty.
 * The page only knows how many accounts there are: the list is searched on the
 * server when the panel opens and as the user types.
 */
export function AccountSwitcher() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const t = useTranslations();
    const listId = useId();
    const inputRef = useRef<HTMLInputElement>(null);
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [result, setResult] = useState<Result | null>(null);
    const [failed, setFailed] = useState(false);
    // Nothing is highlighted until the pointer or an arrow key picks a row, so
    // the list never looks as if something is already hovered.
    const [active, setActive] = useState(-1);

    const term = query.trim();

    useEffect(() => {
        if (!open) {
            return;
        }

        // Aborting on every change means a slow answer to an older search can
        // never overwrite the answer to the newer one.
        const controller = new AbortController();
        const timer = window.setTimeout(
            async () => {
                try {
                    const response = await fetch(
                        accountSwitcher.url({
                            query: { q: term || undefined },
                        }),
                        {
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            signal: controller.signal,
                        },
                    );

                    if (!response.ok) {
                        throw new Error(String(response.status));
                    }

                    const data =
                        (await response.json()) as AccountSwitcherResponse;

                    setResult({ ...data, query: term });
                    setFailed(false);
                    setActive(-1);
                } catch {
                    if (!controller.signal.aborted) {
                        setFailed(true);
                    }
                }
            },
            // Opening asks straight away; typing waits for a pause.
            term === '' ? 0 : DEBOUNCE,
        );

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [open, term]);

    if (auth.accountCount < 2) {
        return null;
    }

    const accounts = result?.accounts ?? [];
    const hidden = result === null ? 0 : result.total - accounts.length;
    // Rows answering an older search stay visible while the new one is out,
    // but Enter waits for the answer to what is actually typed.
    const fresh = result !== null && result.query === term;

    const changeOpen = (next: boolean) => {
        setOpen(next);

        if (!next) {
            setQuery('');
            setActive(-1);
            setFailed(false);
            // The unfiltered list is what the next open starts with; keep it to
            // show while it refreshes, but never a filtered one.
            setResult((current) => (current?.query === '' ? current : null));
        }
    };

    const switchAccount = (account: Account) => {
        changeOpen(false);

        router.put(
            updateCurrentAccount().url,
            { account_id: account.id },
            {
                // Inertia caches prefetched pages, so without this a page fetched
                // for the previous account can be served from the client cache.
                onSuccess: () => router.flushAll(),
            },
        );
    };

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (accounts.length === 0) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive((active + 1) % accounts.length);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive(active <= 0 ? accounts.length - 1 : active - 1);
        } else if (event.key === 'Enter') {
            // With nothing highlighted, Enter takes the top match.
            event.preventDefault();

            if (fresh) {
                switchAccount(accounts[Math.max(active, 0)]);
            }
        }
    };

    return (
        <Popover open={open} onOpenChange={changeOpen}>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    aria-label={t('Switch account')}
                    data-test="account-switcher"
                    className="flex size-10 shrink-0 cursor-pointer items-center justify-center rounded-full border-[1.5px] border-cc-border bg-cc-panel text-cc-ink transition-colors hover:border-cc-border-strong data-[state=open]:border-cc-ink"
                >
                    <ArrowRightLeft className="size-[18px]" />
                </button>
            </PopoverTrigger>
            <PopoverContent
                align="end"
                className="w-64 rounded-md p-1"
                onOpenAutoFocus={(event) => {
                    event.preventDefault();
                    inputRef.current?.focus();
                }}
            >
                {auth.account !== null && (
                    <div className="flex items-center gap-2 px-1 py-1.5 text-sm">
                        <AccountInitials
                            name={auth.account.name}
                            className="size-8 text-xs"
                        />
                        <div className="grid flex-1 leading-tight">
                            <span className="truncate font-medium">
                                {auth.account.name}
                            </span>
                            <span className="truncate text-xs text-cc-subtle">
                                {t('Current account')}
                            </span>
                        </div>
                    </div>
                )}

                <div className="-mx-1 my-1 h-px bg-border" />

                <div className="p-1">
                    <label className="flex h-9 items-center gap-2 rounded-full border-[1.5px] border-cc-border bg-cc-panel px-3 text-cc-subtle focus-within:border-cc-border-strong">
                        <Search className="size-4 shrink-0" />
                        <input
                            ref={inputRef}
                            type="search"
                            role="combobox"
                            aria-label={t('Search accounts')}
                            aria-expanded={true}
                            aria-controls={listId}
                            aria-activedescendant={
                                active >= 0 && accounts[active]
                                    ? `${listId}-${accounts[active].id}`
                                    : undefined
                            }
                            maxLength={100}
                            placeholder={t('Search accounts')}
                            value={query}
                            onChange={(event) => {
                                setQuery(event.target.value);
                                setActive(-1);
                            }}
                            onKeyDown={onKeyDown}
                            className="min-w-0 flex-1 border-none bg-transparent text-sm text-cc-ink outline-none placeholder:text-cc-subtle"
                        />
                    </label>
                </div>

                <div
                    id={listId}
                    role="listbox"
                    aria-label={t('Accounts')}
                    aria-busy={!fresh}
                    onMouseLeave={() => setActive(-1)}
                >
                    {accounts.map((account, index) => (
                        <div
                            key={account.id}
                            id={`${listId}-${account.id}`}
                            role="option"
                            aria-selected={index === active}
                            onMouseMove={() => setActive(index)}
                            onClick={() => switchAccount(account)}
                            className={cn(
                                'flex cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-sm select-none',
                                index === active &&
                                    'bg-accent text-accent-foreground',
                            )}
                        >
                            <AccountInitials
                                name={account.name}
                                className="size-6 text-[10px]"
                            />
                            <span className="truncate">{account.name}</span>
                        </div>
                    ))}
                </div>

                {failed ? (
                    <p className="px-2 py-1.5 text-sm text-cc-subtle">
                        {t('Loading accounts failed. Please try again.')}
                    </p>
                ) : result === null ? (
                    <div className="flex justify-center py-3 text-cc-subtle">
                        <Spinner className="size-4" />
                    </div>
                ) : (
                    fresh &&
                    accounts.length === 0 && (
                        <p className="px-2 py-1.5 text-sm text-cc-subtle">
                            {t('No accounts found.')}
                        </p>
                    )
                )}

                {!failed && hidden > 0 && (
                    <p className="px-2 pt-1 pb-1.5 text-xs text-cc-subtle">
                        {t(':count more, search to narrow down', {
                            count: hidden,
                        })}
                    </p>
                )}
            </PopoverContent>
        </Popover>
    );
}
