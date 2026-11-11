import { Link } from '@inertiajs/react';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { index, sent } from '@/routes/colleagues';

/**
 * The colleagues and the mails Howy sent them, side by side.
 */
export function ColleagueTabs({ current }: { current: 'colleagues' | 'sent' }) {
    const t = useTranslations();
    const tabs = [
        { key: 'colleagues', label: t('Colleagues'), href: index() },
        { key: 'sent', label: t('Sent mails'), href: sent() },
    ] as const;

    return (
        <nav
            aria-label={t('Colleagues')}
            className="flex gap-1 border-b border-cc-border"
        >
            {tabs.map((tab) => (
                <Link
                    key={tab.key}
                    href={tab.href}
                    aria-current={tab.key === current ? 'page' : undefined}
                    className={cn(
                        '-mb-px flex h-11 items-center border-b-2 px-3 text-sm transition-colors',
                        tab.key === current
                            ? 'border-cc-accent font-semibold text-cc-ink'
                            : 'border-transparent font-medium text-cc-subtle hover:text-cc-ink',
                    )}
                >
                    {tab.label}
                </Link>
            ))}
        </nav>
    );
}
