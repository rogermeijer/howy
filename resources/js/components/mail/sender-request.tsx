import { useTranslations } from '@/hooks/use-translations';
import type { InterpretedStatement } from '@/types';

/**
 * What whoever sent the mail asked about a statement, from the page linked in
 * Saved! (04): to take it out, or a correction.
 */
export function SenderRequestNote({
    statement,
}: {
    statement: InterpretedStatement;
}) {
    const t = useTranslations();
    const request = statement.senderRequest;

    if (request === null) {
        return null;
    }

    return (
        <div className="flex flex-col gap-1 rounded-lg bg-cc-pending-bg px-3 py-2 text-[13px] leading-[1.5] text-cc-pending-fg">
            <span className="font-semibold">
                {request.choice === 'withdraw'
                    ? t('The sender asks to take this out.')
                    : t('The sender says this is not right:')}
                {request.handled && ` ${t('Done.')}`}
            </span>
            {request.correction && <span>“{request.correction}”</span>}
        </div>
    );
}
