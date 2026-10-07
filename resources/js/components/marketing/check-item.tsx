import type { ReactNode } from 'react';
import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

const tones = {
    lime: 'text-cc-accent',
    green: 'text-cc-accent-deep',
} as const;

/** A list item with a check mark in front, for feature and benefit lists. */
export function CheckItem({
    children,
    tone = 'green',
    className,
}: {
    children: ReactNode;
    tone?: keyof typeof tones;
    className?: string;
}) {
    return (
        <li className={cn('flex items-start gap-2.5', className)}>
            <Check
                aria-hidden="true"
                className={cn('mt-[0.2em] size-[1.15em] shrink-0', tones[tone])}
                strokeWidth={3}
            />
            <span className="min-w-0">{children}</span>
        </li>
    );
}
