import { Link } from '@inertiajs/react';
import { ArrowRight, Mail } from 'lucide-react';
import { WithHowy } from '@/components/brand/howy-name';
import { useTranslations } from '@/hooks/use-translations';
import { settings } from '@/routes';

type Props = {
    canManage: boolean;
};

/**
 * Shown on the inbox until the account has a mailbox connected. Links straight
 * into the connect dialog on the settings page.
 */
export function MailboxBanner({ canManage }: Props) {
    const t = useTranslations();

    return (
        <section
            aria-label={t('Connect a mailbox')}
            className="cc-panel-dark flex flex-col gap-5 p-6 lg:flex-row lg:items-center lg:gap-6 lg:px-7"
        >
            <div className="flex size-[52px] shrink-0 items-center justify-center rounded-[14px] bg-cc-dark-2 text-cc-accent">
                <Mail className="size-6" />
            </div>

            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <h2 className="text-[17px] font-semibold tracking-[-0.01em]">
                    {t('Connect a support or knowledge mailbox')}
                </h2>
                <p className="max-w-[700px] text-[14px] leading-[1.55] text-cc-dark-text">
                    <WithHowy
                        text={
                            canManage
                                ? t(
                                      'Connect a shared Gmail mailbox, such as support@ or knowledge@. Howy processes every email that arrives there, and you can import existing emails.',
                                  )
                                : t(
                                      'Ask an administrator of this account to connect a shared mailbox, such as support@ or knowledge@.',
                                  )
                        }
                    />
                </p>
            </div>

            {canManage && (
                <div className="flex shrink-0 flex-wrap gap-3">
                    <Link
                        href={settings({ query: { connect: 'mailbox' } })}
                        className="cc-btn-accent inline-flex items-center gap-2 px-[22px] py-[13px] text-[15px]"
                    >
                        {t('Connect mailbox')}
                        <ArrowRight className="size-4" />
                    </Link>
                </div>
            )}
        </section>
    );
}
