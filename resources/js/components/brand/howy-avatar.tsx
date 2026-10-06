import { BRAND_NAME } from '@/components/brand/brand';
import { cn } from '@/lib/utils';

/**
 * Howy's face, wherever Howy appears as a participant: in a CC line, next to
 * a reply, in a banner. The one place to change it. public/favicon.svg carries
 * the same drawing for the browser tab.
 */
export function HowyAvatar({
    size = 40,
    className,
}: {
    size?: number;
    className?: string;
}) {
    return (
        <svg
            role="img"
            aria-label={BRAND_NAME}
            width={size}
            height={size}
            viewBox="0 0 24 24"
            fill="none"
            className={cn('shrink-0', className)}
        >
            <circle cx="12" cy="12" r="11.5" fill="var(--color-cc-accent)" />
            {/* Hairline */}
            <path
                d="M5.2 7.6 C 7 4.2, 17 4.2, 18.8 7.6"
                stroke="var(--color-cc-accent-deep)"
                strokeWidth="1.3"
                strokeLinecap="round"
            />
            {/* Glasses */}
            <circle
                cx="8.4"
                cy="10.8"
                r="3.1"
                fill="#ffffff"
                stroke="var(--color-cc-ink)"
                strokeWidth="1.3"
            />
            <circle
                cx="15.6"
                cy="10.8"
                r="3.1"
                fill="#ffffff"
                stroke="var(--color-cc-ink)"
                strokeWidth="1.3"
            />
            <path
                d="M11.5 10.4 Q 12 9.9 12.5 10.4"
                stroke="var(--color-cc-ink)"
                strokeWidth="1.2"
                strokeLinecap="round"
            />
            <circle cx="8.9" cy="11.1" r="1" fill="var(--color-cc-ink)" />
            <circle cx="16.1" cy="11.1" r="1" fill="var(--color-cc-ink)" />
            {/* Smile and tooth */}
            <path
                d="M8.4 15.6 Q 12 19.2 15.6 15.6"
                stroke="var(--color-cc-ink)"
                strokeWidth="1.3"
                strokeLinecap="round"
            />
            <rect
                x="11"
                y="16.4"
                width="2"
                height="1.5"
                rx="0.3"
                fill="#ffffff"
                stroke="var(--color-cc-ink)"
                strokeWidth="0.6"
            />
        </svg>
    );
}
