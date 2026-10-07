import { Link, router, usePage } from '@inertiajs/react';
import { Check, LogOut, Settings, UserRound } from 'lucide-react';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { cn } from '@/lib/utils';
import { logout } from '@/routes';
import { settings } from '@/routes';
import { update as updateCurrentAccount } from '@/routes/current-account';
import { edit as editProfile } from '@/routes/profile';
import { useTranslations } from '@/hooks/use-translations';
import type { Auth, User } from '@/types';

type Props = {
    user: User;
};

export function UserMenuContent({ user }: Props) {
    const cleanup = useMobileNavigation();
    const { auth } = usePage<{ auth: Auth }>().props;
    const t = useTranslations();

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    const switchAccount = (accountId: number) => {
        if (accountId === auth.account?.id) {
            return;
        }

        cleanup();

        router.put(
            updateCurrentAccount().url,
            { account_id: accountId },
            {
                // Inertia caches prefetched pages, so without this a page fetched
                // for the previous account can be served from the client cache.
                onSuccess: () => router.flushAll(),
            },
        );
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />

            {auth.accounts.length > 1 && (
                <>
                    <DropdownMenuLabel className="px-2 py-1.5 text-xs font-medium text-cc-subtle">
                        {t('Switch account')}
                    </DropdownMenuLabel>
                    <DropdownMenuGroup>
                        {auth.accounts.map((account) => (
                            <DropdownMenuItem
                                key={account.id}
                                className="cursor-pointer"
                                onSelect={() => switchAccount(account.id)}
                            >
                                <Check
                                    className={cn(
                                        'mr-2 size-4 shrink-0',
                                        account.id === auth.account?.id
                                            ? 'text-cc-accent-deep'
                                            : 'invisible',
                                    )}
                                />
                                <span className="truncate">{account.name}</span>
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuGroup>
                    <DropdownMenuSeparator />
                </>
            )}

            <DropdownMenuGroup>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={editProfile()}
                        prefetch
                        onClick={cleanup}
                    >
                        <UserRound className="mr-2" />
                        {t('Profile')}
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={settings()}
                        prefetch
                        onClick={cleanup}
                    >
                        <Settings className="mr-2" />
                        {t('Settings')}
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={logout()}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    {t('Log out')}
                </Link>
            </DropdownMenuItem>
        </>
    );
}
