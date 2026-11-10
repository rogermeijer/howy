import { Link, router, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { HowyLogo } from '@/components/brand/howy-logo';
import { LanguageSwitcher } from '@/components/language-switcher';
import { useTranslations } from '@/hooks/use-translations';
import { logout } from '@/routes';
import type { Auth } from '@/types';

/**
 * Setup: the slim top bar without the app's navigation, which would lead away
 * from a step half done, and one narrow column.
 */
export default function SetupLayout({ children }: PropsWithChildren) {
    const t = useTranslations();
    const page = usePage<{ auth: Auth }>();
    const { auth } = page.props;
    const finished = page.component === 'setup/done';

    return (
        <div className="min-h-svh bg-cc-bg">
            <header className="sticky top-0 z-40 border-b border-cc-border bg-white/90 backdrop-blur-md">
                <div className="mx-auto flex h-16 w-full max-w-[1120px] items-center gap-4 px-4 lg:px-10">
                    <HowyLogo size={24} />
                    {auth.account && (
                        <span className="hidden min-w-0 truncate border-l border-cc-border pl-4 text-[13px] text-cc-subtle sm:block">
                            {finished
                                ? auth.account.name
                                : t('Setting up :account', {
                                      account: auth.account.name,
                                  })}
                        </span>
                    )}
                    <div className="ml-auto flex shrink-0 items-center gap-2">
                        <LanguageSwitcher />
                        <Link
                            href={logout()}
                            as="button"
                            onClick={() => router.flushAll()}
                            className="h-10 cursor-pointer rounded-full px-3 text-sm font-semibold text-cc-muted transition-colors hover:text-cc-ink"
                        >
                            {t('Log out')}
                        </Link>
                    </div>
                </div>
            </header>

            <main className="mx-auto flex w-full max-w-[680px] flex-col gap-6 px-4 pt-10 pb-16">
                {children}
            </main>
        </div>
    );
}
