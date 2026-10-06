import { Fragment } from 'react';
import { BRAND_NAME } from '@/components/brand/brand';
import { cn } from '@/lib/utils';

/**
 * The name in running text: set in the logo face at the size of the copy
 * around it. `accent` makes it pink, for headings.
 */
export function HowyName({
    accent = false,
    className,
}: {
    accent?: boolean;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'font-logo font-normal tracking-normal',
                accent && 'text-cc-accent',
                className,
            )}
        >
            {BRAND_NAME}
        </span>
    );
}

/**
 * Render a translated string with every "Howy" set as <HowyName>, so the name
 * reads as the brand wherever it falls in the sentence, in any language.
 */
export function WithHowy({
    text,
    accent = false,
}: {
    text: string;
    accent?: boolean;
}) {
    const parts = text.split(BRAND_NAME);

    return (
        <>
            {parts.map((part, index) => (
                <Fragment key={index}>
                    {index > 0 && <HowyName accent={accent} />}
                    {part}
                </Fragment>
            ))}
        </>
    );
}
