import { Form } from '@inertiajs/react';
import { WithHowy } from '@/components/brand/howy-name';
import { MailLinkShell } from '@/components/mail-links/mail-link-shell';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { renew } from '@/routes/mail-links';

type Props = {
    token: string;
    company: string;
    /** The address the link went to, partly hidden. */
    recipient: string;
    /** The mailbox the link came from is connected. */
    canRenew: boolean;
    renewed: boolean;
};

/**
 * A link from a mail that no longer works, and a new one by mail, to the
 * address the old one went to.
 */
export default function Expired({
    token,
    company,
    recipient,
    canRenew,
    renewed,
}: Props) {
    const t = useTranslations();

    return (
        <MailLinkShell title={t('This link has expired')} company={company}>
            <div className="flex flex-col gap-2">
                <h1 className="cc-subtitle">{t('This link has expired')}</h1>
                <p className="text-[15px] leading-[1.6] text-cc-muted">
                    {renewed
                        ? t('A new link is on its way to :recipient.', {
                              recipient,
                          })
                        : t(
                              'Links in mails work for a while only. Ask for a new one and it goes to :recipient.',
                              { recipient },
                          )}
                </p>
            </div>

            {canRenew ? (
                !renewed && (
                    <Form
                        {...renew.form(token)}
                        options={{ preserveScroll: true }}
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                className="h-11 px-[18px]"
                                disabled={processing}
                            >
                                {t('Send me a new link')}
                            </Button>
                        )}
                    </Form>
                )
            ) : (
                <p className="rounded-xl bg-cc-pending-bg px-4 py-3 text-[14px] font-medium text-cc-pending-fg">
                    <WithHowy
                        text={t(
                            'Howy cannot send mail right now. Try again later.',
                        )}
                    />
                </p>
            )}
        </MailLinkShell>
    );
}
