import { Fragment } from 'react';
import { BRAND_NAME } from '@/components/brand/brand';
import { Marker } from '@/components/brand/doodles';
import { cn } from '@/lib/utils';

/**
 * The name in running text: the copy's own type, in the wordmark's weight.
 * `accent` puts the lime marker under it, for headings.
 */
export function HowyName({
    accent = false,
    className,
}: {
    accent?: boolean;
    className?: string;
}) {
    const name = (
        <span className={cn('font-logo font-extrabold', className)}>
            {BRAND_NAME}
        </span>
    );

    return accent ? <Marker>{name}</Marker> : name;
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
