import { BRAND_NAME } from '@/components/brand/brand';
import { cn } from '@/lib/utils';

export type LogoTone = 'ink' | 'ivory' | 'on-accent';

const wordColor: Record<LogoTone, string> = {
    ink: 'text-cc-ink',
    ivory: 'text-white',
    'on-accent': 'text-cc-ink',
};

// The dot is lime, ringed in the word's own colour; on the lime ground it
// turns white so it still reads as a dot.
const dotColor: Record<LogoTone, string> = {
    ink: 'bg-cc-accent',
    ivory: 'bg-cc-accent',
    'on-accent': 'bg-white',
};

/**
 * The Howy wordmark: the name in heavy type with the lime dot after it.
 * `size` is the font size in px; the dot scales with it.
 */
export function HowyLogo({
    size = 26,
    tone = 'ink',
    className,
}: {
    size?: number;
    tone?: LogoTone;
    className?: string;
}) {
    const dot = Math.max(5, Math.round(size * 0.3));

    return (
        <span
            className={cn(
                'inline-flex items-baseline gap-[0.12em] font-logo leading-none font-extrabold tracking-[-0.05em]',
                wordColor[tone],
                className,
            )}
            style={{ fontSize: size }}
        >
            {BRAND_NAME}
            <span
                aria-hidden="true"
                className={cn(
                    'inline-block shrink-0 rounded-full shadow-[0_0_0_1px_currentColor]',
                    dotColor[tone],
                )}
                style={{ width: dot, height: dot }}
            />
        </span>
    );
}
