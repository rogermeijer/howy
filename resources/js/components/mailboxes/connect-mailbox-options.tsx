import { Check, Info, Mail, Minus, Server, X } from 'lucide-react';
import { WithHowy } from '@/components/brand/howy-name';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTranslations } from '@/hooks/use-translations';

// English source strings; translated where rendered.
const comingSoon = [
    {
        id: 'microsoft',
        name: 'Microsoft 365 / Outlook',
        detail: 'Exchange Online and Outlook.com',
        icon: Mail,
    },
    {
        id: 'imap',
        name: 'IMAP',
        detail: 'Any other mail server',
        icon: Server,
    },
];

/**
 * What connecting a mailbox involves: the providers, and what Howy may and
 * may not do with it. Shared by the connect dialog and setup.
 */
export function ConnectMailboxOptions() {
    const t = useTranslations();

    return (
        <>
            <fieldset className="flex flex-col gap-2.5">
                <legend className="cc-label mb-2.5">{t('Provider')}</legend>

                <label className="flex cursor-pointer items-center gap-3.5 rounded-xl border-[1.5px] border-cc-ink bg-cc-panel px-4 py-3.5">
                    <input
                        type="radio"
                        name="provider"
                        value="gmail"
                        defaultChecked
                        className="size-[18px] accent-cc-ink"
                    />
                    <ProviderIcon icon={Mail} />
                    <span className="flex flex-col gap-0.5">
                        <span className="text-[15px] font-semibold">Gmail</span>
                        <span className="cc-caption">
                            {t('Google Workspace or @gmail.com')}
                        </span>
                    </span>
                </label>

                {comingSoon.map((provider) => (
                    <label
                        key={provider.id}
                        className="flex items-center gap-3.5 rounded-xl border-[1.5px] border-cc-border bg-cc-bg px-4 py-3.5 text-cc-subtle"
                    >
                        <input
                            type="radio"
                            name="provider"
                            value={provider.id}
                            disabled
                            className="size-[18px]"
                        />
                        <ProviderIcon icon={provider.icon} />
                        <span className="flex flex-1 flex-col gap-0.5">
                            <span className="text-[15px] font-semibold">
                                {provider.name}
                            </span>
                            <span className="text-[13px]">
                                {t(provider.detail)}
                            </span>
                        </span>
                        <span className="cc-tag cc-tag-noise before:hidden">
                            {t('Coming soon')}
                        </span>
                    </label>
                ))}
            </fieldset>

            <ul className="flex flex-col gap-2.5 rounded-xl bg-cc-bg p-4 text-[13px]">
                <li className="flex items-center gap-2.5">
                    <Check
                        className="size-4 shrink-0 text-cc-decision-fg"
                        strokeWidth={2.5}
                    />
                    {t('Read emails')}
                </li>
                <li className="flex items-center gap-2.5">
                    <Check
                        className="size-4 shrink-0 text-cc-decision-fg"
                        strokeWidth={2.5}
                    />
                    {t('Get notified as soon as new mail arrives')}
                </li>
                <li className="flex items-center gap-2.5">
                    <Minus
                        className="size-4 shrink-0 text-cc-pending-fg"
                        strokeWidth={2.5}
                    />
                    {t('Only replies to whom you allow')}
                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <button
                                    type="button"
                                    aria-label={t('More about sending')}
                                    className="flex size-[22px] cursor-help items-center justify-center rounded-full border-[1.5px] border-cc-ink bg-cc-panel text-cc-ink"
                                >
                                    <Info
                                        className="size-3"
                                        strokeWidth={2.75}
                                    />
                                </button>
                            </TooltipTrigger>
                            <TooltipContent className="max-w-[280px] bg-cc-ink px-3.5 py-3 text-[12px] leading-[1.5] text-cc-bg">
                                <WithHowy
                                    text={t(
                                        'By default Howy learns from and answers colleagues on your own domain, and ignores everyone else. Per sender you decide whether Howy learns from their mail, answers it, or both.',
                                    )}
                                />
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </li>
                <li className="flex items-center gap-2.5">
                    <X
                        className="size-4 shrink-0 text-cc-action-fg"
                        strokeWidth={2.5}
                    />
                    {t('Never changes or deletes emails')}
                </li>
            </ul>
        </>
    );
}

function ProviderIcon({ icon: Icon }: { icon: typeof Mail }) {
    return (
        <span className="flex size-9 shrink-0 items-center justify-center rounded-[9px] bg-cc-raised">
            <Icon className="size-[18px]" />
        </span>
    );
}
