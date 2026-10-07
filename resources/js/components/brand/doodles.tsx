import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/*
 * The hand-drawn marks of the brand: a marker swash behind a word, a circle
 * around it, a wavy line under it, and a few loose doodles. Each draws in a
 * stretched SVG box, so it fits any word at any size.
 */

const ink = 'var(--color-cc-ink)';

const markerColor = {
    lime: 'var(--color-cc-accent)',
    yellow: 'var(--color-cc-highlight)',
} as const;

const markerPath = {
    rise: 'M6 28 C 48 16, 120 10, 194 18',
    wave: 'M6 24 C 50 30, 140 12, 194 20',
} as const;

/** A highlighter swash behind the lower half of the words. */
export function Marker({
    children,
    color = 'lime',
    shape = 'rise',
    className,
}: {
    children: ReactNode;
    color?: keyof typeof markerColor;
    shape?: keyof typeof markerPath;
    className?: string;
}) {
    return (
        <span className={cn('relative z-0 inline-block', className)}>
            {children}
            <svg
                aria-hidden="true"
                viewBox="0 0 200 40"
                preserveAspectRatio="none"
                className="absolute bottom-0 left-[-4%] -z-10 h-[46%] w-[108%]"
            >
                <path
                    d={markerPath[shape]}
                    stroke={markerColor[color]}
                    strokeWidth="24"
                    strokeLinecap="round"
                    fill="none"
                />
            </svg>
        </span>
    );
}

const lineColor = {
    ink,
    green: 'var(--color-cc-accent-deep)',
    lime: 'var(--color-cc-accent)',
} as const;

/** A pen loop around the words, slightly open where it started. */
export function Circled({
    children,
    color = 'ink',
    className,
}: {
    children: ReactNode;
    color?: keyof typeof lineColor;
    className?: string;
}) {
    return (
        <span className={cn('relative inline-block', className)}>
            {children}
            <svg
                aria-hidden="true"
                viewBox="0 0 200 80"
                preserveAspectRatio="none"
                className="pointer-events-none absolute top-[-24%] left-[-9%] h-[148%] w-[118%] overflow-visible"
            >
                <path
                    d="M34 10 C 96 -2, 192 6, 196 40 C 200 72, 84 80, 24 68 C -8 60, 0 22, 60 8"
                    stroke={lineColor[color]}
                    strokeWidth="3"
                    strokeLinecap="round"
                    fill="none"
                    vectorEffect="non-scaling-stroke"
                />
            </svg>
        </span>
    );
}

/** A wavy line under the words. */
export function Squiggle({
    children,
    color = 'ink',
    className,
}: {
    children: ReactNode;
    color?: keyof typeof lineColor;
    className?: string;
}) {
    return (
        <span className={cn('relative inline-block', className)}>
            {children}
            <svg
                aria-hidden="true"
                viewBox="0 0 200 20"
                preserveAspectRatio="none"
                className="pointer-events-none absolute bottom-[-0.2em] left-0 h-[0.22em] min-h-2.5 w-full overflow-visible"
            >
                <path
                    d="M3 12 C 20 2, 30 20, 50 10 S 80 2, 100 10 S 130 20, 150 10 S 180 2, 197 10"
                    stroke={lineColor[color]}
                    strokeWidth="4"
                    strokeLinecap="round"
                    fill="none"
                    vectorEffect="non-scaling-stroke"
                />
            </svg>
        </span>
    );
}

/** A note in handwriting, optionally on a white tag. */
export function HandNote({
    children,
    boxed = false,
    className,
}: {
    children: ReactNode;
    boxed?: boolean;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'font-hand text-[26px] leading-[1.2] font-bold text-cc-ink',
                boxed && 'rounded-lg bg-white px-3 py-0.5',
                className,
            )}
        >
            {children}
        </span>
    );
}

/** Three short strokes, like a drawn burst of attention. */
export function Burst({ className }: { className?: string }) {
    return (
        <svg
            aria-hidden="true"
            width="48"
            height="48"
            viewBox="0 0 48 48"
            fill="none"
            stroke={ink}
            strokeWidth="3"
            strokeLinecap="round"
            className={className}
        >
            <path d="M41 28 L38 12" />
            <path d="M33 33 L21 21" />
            <path d="M28 41 L12 38" />
        </svg>
    );
}

/** A four-pointed sparkle. */
export function Sparkle({ className }: { className?: string }) {
    return (
        <svg
            aria-hidden="true"
            width="56"
            height="56"
            viewBox="0 0 56 56"
            fill="none"
            stroke={ink}
            strokeWidth="3"
            strokeLinecap="round"
            strokeLinejoin="round"
            className={className}
        >
            <path d="M28 6 C 29 20, 34 26, 50 28 C 34 30, 29 36, 28 50 C 27 36, 22 30, 6 28 C 22 26, 27 20, 28 6 Z" />
        </svg>
    );
}

/** A loose curved arrow pointing down and to the right. */
export function CurvedArrow({ className }: { className?: string }) {
    return (
        <svg
            aria-hidden="true"
            width="70"
            height="56"
            viewBox="0 0 70 56"
            fill="none"
            stroke={ink}
            strokeWidth="3"
            strokeLinecap="round"
            strokeLinejoin="round"
            className={className}
        >
            <path d="M8 4 C 4 22, 14 40, 40 48" />
            <path d="M30 38 L41 48 L28 54" />
        </svg>
    );
}
