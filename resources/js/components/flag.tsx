import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/*
 * Flags drawn inline (emoji flags don't render on Windows), keyed by locale.
 * A new locale gets its flag here; without one it falls back to its code.
 */
const flags: Record<string, ReactNode> = {
    nl: (
        <>
            <rect width="30" height="20" fill="#21468b" />
            <rect width="30" height="13.33" fill="#ffffff" />
            <rect width="30" height="6.67" fill="#ae1c28" />
        </>
    ),
    en: (
        <>
            <rect width="30" height="20" fill="#012169" />
            <path d="M0 0 30 20M30 0 0 20" stroke="#ffffff" strokeWidth="4" />
            <path d="M0 0 30 20M30 0 0 20" stroke="#c8102e" strokeWidth="1.6" />
            <path d="M15 0v20M0 10h30" stroke="#ffffff" strokeWidth="6" />
            <path d="M15 0v20M0 10h30" stroke="#c8102e" strokeWidth="3.4" />
        </>
    ),
};

export function Flag({
    locale,
    className,
}: {
    locale: string;
    className?: string;
}) {
    const flag = flags[locale];

    if (!flag) {
        return (
            <span className={cn('text-xs font-bold uppercase', className)}>
                {locale}
            </span>
        );
    }

    return (
        <svg
            aria-hidden="true"
            viewBox="0 0 30 20"
            className={cn('block h-4 w-6 rounded-[3px]', className)}
        >
            {flag}
        </svg>
    );
}
