import { router, usePage } from '@inertiajs/react';
import { echo, echoIsConfigured } from '@laravel/echo-react';
import { useEffect } from 'react';
import type { Auth } from '@/types';

/** Mirrors App\Enums\AccountTopic. */
export type AccountTopic = 'mail' | 'sent-mails' | 'approvals';

/**
 * Reload some of the page's props whenever the account says that topic
 * changed, so mail that came in, or went out, shows without a refresh. Only
 * the topic comes over the account's private channel; the props come, as
 * ever, from a scoped reload, which keeps the page's own state and runs
 * beside any other. Without Reverb it does nothing.
 */
export function useAccountUpdates(topic: AccountTopic, only: string[]) {
    const accountId = usePage<{ auth: Auth }>().props.auth.account?.id;
    const props = only.join(',');

    useEffect(() => {
        if (accountId === undefined || !echoIsConfigured()) {
            return;
        }

        const channel = echo().private(`account.${accountId}`);
        const heard = (event: { topic: string }) => {
            if (event.topic === topic) {
                router.reload({ only: props.split(','), async: true });
            }
        };

        channel.listen('.changed', heard);

        return () => {
            channel.stopListening('.changed', heard);
        };
    }, [accountId, topic, props]);
}
