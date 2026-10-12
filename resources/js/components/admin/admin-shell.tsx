import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/cc/page-header';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { index as accounts } from '@/routes/admin/accounts';
import { index as ai } from '@/routes/admin/ai';
import { index as beta } from '@/routes/admin/beta';
import { index as users } from '@/routes/admin/users';

type Props = {
    title: string;
    description?: ReactNode;
    breadcrumb?: ReactNode;
    actions?: ReactNode;
    children: ReactNode;
};

/**
 * The frame of every admin page: its own section tabs under the top bar —
 * a row of pills, never a sidebar.
 */
export function AdminShell({
    title,
    description,
    breadcrumb,
    actions,
    children,
}: Props) {
    const t = useTranslations();
    const { isCurrentOrParentUrl } = useCurrentUrl();

    const tabs = [
        { label: t('Accounts'), href: accounts() },
        { label: t('Users'), href: users() },
        { label: t('Private beta'), href: beta() },
        { label: t('AI usage'), href: ai() },
    ];

    return (
        <>
            <Head title={`${title} · ${t('Admin panel')}`} />

            <div className="flex flex-col gap-6">
                <nav
                    aria-label={t('Admin panel')}
                    className="flex flex-wrap items-center gap-2"
                >
                    <span className="cc-label mr-2">{t('Admin panel')}</span>
                    {tabs.map((tab) => (
                        <Link
                            key={tab.label}
                            href={tab.href}
                            prefetch
                            className={cn(
                                'cc-pill inline-flex items-center',
                                isCurrentOrParentUrl(tab.href) &&
                                    'cc-pill-active',
                            )}
                        >
                            {tab.label}
                        </Link>
                    ))}
                </nav>

                <PageHeader
                    title={title}
                    description={description}
                    breadcrumb={breadcrumb}
                    actions={actions}
                />

                {children}
            </div>
        </>
    );
}

/**
 * The "← Accounts" style link above a detail page's title.
 */
export function BackLink({
    href,
    children,
}: {
    href: Parameters<typeof Link>[0]['href'];
    children: ReactNode;
}) {
    return (
        <Link
            href={href}
            className="cc-caption self-start font-medium hover:text-cc-ink"
        >
            ← {children}
        </Link>
    );
}

/**
 * A small status chip in the tag style.
 */
export function Chip({
    tone = 'neutral',
    children,
}: {
    tone?: 'neutral' | 'good' | 'info' | 'warn' | 'bad';
    children: ReactNode;
}) {
    return (
        <span
            className={cn(
                'cc-tag',
                tone === 'neutral' && 'cc-tag-noise',
                tone === 'good' && 'bg-cc-accent-tint text-cc-accent-deep',
                tone === 'info' && 'cc-tag-question',
                tone === 'warn' && 'cc-tag-action',
                tone === 'bad' && 'cc-tag-pending',
            )}
        >
            {children}
        </span>
    );
}
