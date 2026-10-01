import { Fragment } from 'react';
import { CcLogoInline } from '@/components/cc-logo';

const TOKEN = '[cc]:';

/**
 * Render a translated string with every literal "[cc]:" swapped for the
 * wordmark, so the product name reads as the logo in running text at whatever
 * size the surrounding copy is. Keep "[cc]:" in both the key and lang/nl.json.
 */
export function WithCcLogo({ text }: { text: string }) {
    const parts = text.split(TOKEN);

    return (
        <>
            {parts.map((part, index) => (
                <Fragment key={index}>
                    {index > 0 && <CcLogoInline />}
                    {part}
                </Fragment>
            ))}
        </>
    );
}
