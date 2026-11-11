import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { HowyLogo } from '@/components/brand/howy-logo';
import { useTranslations } from '@/hooks/use-translations';

/**
 * A page opened from a link in one of Howy's mails, without logging in: the
 * wordmark with the company it is for, and one card. No app navigation, since
 * whoever opens it usually has no account.
 */
export function MailLinkShell({
    title,
    company,
    children,
}: {
    title: string;
    company: string;
    children: ReactNode;
}) {
    const t = useTranslations();

    return (
        <>
            <Head title={title} />
            <div className="min-h-svh bg-cc-bg px-4 py-10 sm:py-16">
                <div className="mx-auto flex w-full max-w-[560px] flex-col gap-6">
                    <div className="flex items-center gap-2.5">
                        <HowyLogo size={24} />
                        <span className="cc-caption truncate">
                            {t('for :company', { company })}
                        </span>
                    </div>
                    <div className="cc-panel flex flex-col gap-5 p-6 sm:p-8">
                        {children}
                    </div>
                </div>
            </div>
        </>
    );
}
