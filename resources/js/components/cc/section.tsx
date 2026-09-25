import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    description?: ReactNode;
    action?: ReactNode;
    children: ReactNode;
    tone?: 'default' | 'danger';
    className?: string;
};

export function Section({
    title,
    description,
    action,
    children,
    tone = 'default',
    className,
}: Props) {
    return (
        <section
            className={cn(
                'cc-panel flex flex-col gap-5 p-6 lg:p-8',
                tone === 'danger' &&
                    'border-cc-action-fg/30 bg-cc-action-bg/40',
                className,
            )}
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="flex flex-col gap-1">
                    <h2
                        className={cn(
                            'text-[17px] leading-tight font-semibold tracking-[-0.01em]',
                            tone === 'danger' && 'text-cc-action-fg',
                        )}
                    >
                        {title}
                    </h2>
                    {description && (
                        <p className="cc-caption max-w-prose">{description}</p>
                    )}
                </div>
                {action}
            </div>
            {children}
        </section>
    );
}
