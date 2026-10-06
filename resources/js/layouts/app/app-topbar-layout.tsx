import { Link, router, usePage } from '@inertiajs/react';
import { Bell, Search } from 'lucide-react';
import { useTranslations } from '@/hooks/use-translations';
import type { PropsWithChildren } from 'react';
import { HowyLogo } from '@/components/brand/howy-logo';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { inbox, knowledge } from '@/routes';
import knowledgeRoutes from '@/routes/knowledge';
import type { Auth } from '@/types';

function TopBar() {
    const t = useTranslations();
    const { auth, inbox: inboxCounts } = usePage<{
        auth: Auth;
        inbox: { needsReview: number };
    }>().props;
    const needsReview = inboxCounts?.needsReview ?? 0;

    // Titles are English source strings, translated where they are rendered.
    // The inbox counts the mails waiting for review; none, no badge.
    const navItems = [
        {
            title: 'Inbox',
            href: inbox(),
            badge: needsReview > 0 ? needsReview : null,
            badgeLabel: t(':count emails to review', { count: needsReview }),
        },
        {
            title: 'Knowledge base',
            href: knowledge(),
            badge: null,
            badgeLabel: null,
        },
    ];

    const { isCurrentOrParentUrl } = useCurrentUrl();
    const getInitials = useInitials();

    return (
        <header className="sticky top-0 z-40 flex h-16 items-center gap-3 border-b border-cc-border bg-cc-bg px-4 lg:gap-9 lg:px-10">
            <Link
                href={inbox()}
                aria-label={t('Howy home')}
                className="shrink-0"
            >
                <HowyLogo size={24} />
            </Link>

            <nav
                aria-label={t('Main menu')}
                className="flex h-16 flex-1 [scrollbar-width:none] items-center gap-1 overflow-x-auto [&::-webkit-scrollbar]:hidden"
            >
                {navItems.map((item) => {
                    const active = isCurrentOrParentUrl(item.href);

                    return (
                        <Link
                            key={item.title}
                            href={item.href}
                            className={cn(
                                'flex h-16 shrink-0 items-center gap-2 border-b-2 px-3 text-sm transition-colors',
                                active
                                    ? 'border-cc-accent font-semibold text-cc-ink'
                                    : 'border-transparent font-medium text-cc-subtle hover:text-cc-ink',
                            )}
                        >
                            {t(item.title)}
                            {item.badge !== null && (
                                <span
                                    aria-label={item.badgeLabel ?? undefined}
                                    className={cn(
                                        'inline-flex h-5 min-w-5 items-center justify-center rounded-full px-1.5 text-[11px] font-semibold',
                                        active
                                            ? 'bg-cc-ink text-cc-bg'
                                            : 'bg-cc-raised text-cc-ink',
                                    )}
                                >
                                    {item.badge}
                                </span>
                            )}
                        </Link>
                    );
                })}
            </nav>

            <form
                role="search"
                onSubmit={(event) => {
                    event.preventDefault();
                    const q = new FormData(event.currentTarget).get('q');

                    if (typeof q === 'string' && q.trim() !== '') {
                        router.get(knowledgeRoutes.search().url, { q });
                    }
                }}
                className="hidden h-10 w-[300px] shrink-0 items-center gap-2.5 rounded-[10px] border-[1.5px] border-cc-border bg-cc-panel px-3.5 text-cc-subtle focus-within:border-cc-ink lg:flex"
            >
                <Search className="size-4 shrink-0" />
                <input
                    type="search"
                    name="q"
                    placeholder={t('Search emails and knowledge')}
                    aria-label={t('Search')}
                    className="min-w-0 flex-1 border-none bg-transparent text-sm text-cc-ink outline-none placeholder:text-cc-subtle"
                />
                <span className="shrink-0 rounded-[5px] border border-cc-border px-1.5 text-[11px] font-semibold text-cc-faint">
                    ⌘K
                </span>
            </form>

            <button
                type="button"
                aria-label={t('Notifications')}
                className="hidden size-10 shrink-0 cursor-pointer items-center justify-center rounded-[10px] border-[1.5px] border-cc-border bg-cc-panel text-cc-ink transition-colors hover:border-cc-border-strong sm:flex"
            >
                <Bell className="size-[18px]" />
            </button>

            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <button
                        type="button"
                        aria-label={t('Account: :name', {
                            name: auth.user.name,
                        })}
                        className="flex size-10 shrink-0 cursor-pointer items-center justify-center rounded-full bg-cc-ink text-[13px] font-semibold text-cc-bg"
                    >
                        {getInitials(auth.user.name)}
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-56">
                    <UserMenuContent user={auth.user} />
                </DropdownMenuContent>
            </DropdownMenu>
        </header>
    );
}

export default function AppTopbarLayout({ children }: PropsWithChildren) {
    return (
        <div className="min-h-svh bg-cc-bg text-cc-ink">
            <TopBar />
            <main className="mx-auto w-full max-w-[1400px] px-4 py-8 lg:px-10 lg:py-10">
                {children}
            </main>
        </div>
    );
}
