import { BRAND_NAME, UNDERLINE_PATH } from '@/components/brand/brand';
import { cn } from '@/lib/utils';

export type LogoTone = 'ink' | 'ivory' | 'on-accent';

const wordColor: Record<LogoTone, string> = {
    ink: 'text-cc-ink',
    ivory: 'text-cc-bg',
    'on-accent': 'text-cc-ink',
};

// On the pink ground the underline would vanish, so it turns ink there.
const underlineColor: Record<LogoTone, string> = {
    ink: 'text-cc-accent',
    ivory: 'text-cc-accent',
    'on-accent': 'text-cc-ink',
};

/**
 * The Howy wordmark: the name in the logo face with the pink arch under it.
 * `size` is the font size in px; the underline and spacing scale with it.
 */
export function HowyLogo({
    size = 30,
    tone = 'ink',
    className,
}: {
    size?: number;
    tone?: LogoTone;
    className?: string;
}) {
    return (
        <span
            className={cn('inline-flex flex-col', wordColor[tone], className)}
        >
            <span
                className="font-logo leading-[0.9] font-normal"
                style={{ fontSize: size }}
            >
                {BRAND_NAME}
            </span>
            <svg
                aria-hidden="true"
                viewBox="0 0 200 24"
                preserveAspectRatio="none"
                className={cn('-ml-[3%] block w-full', underlineColor[tone])}
                style={{
                    height: Math.max(6, Math.round(size * 0.3)),
                    marginTop: Math.max(3, Math.round(size * 0.2)),
                }}
            >
                <path d={UNDERLINE_PATH} fill="currentColor" />
            </svg>
        </span>
    );
}
