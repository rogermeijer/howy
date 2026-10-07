import { type ComponentProps, type ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { cn } from '@/lib/utils';

const variants = {
    lime: 'bg-cc-accent font-bold text-cc-ink hover:bg-cc-accent-hover',
    outline:
        'border-[1.5px] border-cc-ink font-semibold text-cc-ink hover:bg-cc-accent-tint',
    ink: 'border-[1.5px] border-cc-ink bg-cc-ink font-bold text-cc-accent hover:bg-cc-accent-hover hover:text-cc-ink',
    white: 'border-[1.5px] border-cc-ink bg-white font-semibold text-cc-ink hover:bg-cc-accent-tint',
} as const;

const sizes = {
    md: 'min-h-11 px-5 text-[15px]',
    lg: 'min-h-14 px-7 text-base',
} as const;

type Href = ComponentProps<typeof Link>['href'];

/**
 * The rounded call-to-action of the brand. An in-page anchor (`#…`) renders
 * as a plain link; anything else goes through Inertia. `arrow` adds the ink
 * circle with an arrow at the end.
 */
export function PillLink({
    href,
    variant = 'lime',
    size = 'lg',
    arrow = false,
    block = false,
    className,
    children,
}: {
    href: Href;
    variant?: keyof typeof variants;
    size?: keyof typeof sizes;
    arrow?: boolean;
    block?: boolean;
    className?: string;
    children: ReactNode;
}) {
    const classes = cn(
        'items-center justify-center gap-2.5 rounded-full no-underline transition-colors',
        block ? 'flex w-full' : 'inline-flex',
        variants[variant],
        sizes[size],
        arrow && 'pr-2',
        className,
    );

    const content = (
        <>
            {children}
            {arrow && (
                <span className="flex size-9 items-center justify-center rounded-full bg-cc-ink text-white">
                    <ArrowRight className="size-4" strokeWidth={2.4} />
                </span>
            )}
        </>
    );

    if (typeof href === 'string' && href.startsWith('#')) {
        return (
            <a href={href} className={classes}>
                {content}
            </a>
        );
    }

    return (
        <Link href={href} className={classes}>
            {content}
        </Link>
    );
}
