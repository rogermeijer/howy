import type { ReactNode } from 'react';

/**
 * One figure with its label, for the rows of totals on admin pages.
 */
export function Stat({
    label,
    value,
    hint,
}: {
    label: string;
    value: ReactNode;
    hint?: ReactNode;
}) {
    return (
        <div className="cc-panel flex flex-col gap-1.5 px-5 py-4">
            <span className="cc-label">{label}</span>
            <span className="text-[22px] leading-tight font-bold tracking-[-0.02em] tabular-nums">
                {value}
            </span>
            {hint && <span className="cc-caption">{hint}</span>}
        </div>
    );
}
