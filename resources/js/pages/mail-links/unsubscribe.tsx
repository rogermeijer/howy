import { Form } from '@inertiajs/react';
import { WithHowy } from '@/components/brand/howy-name';
import { MailLinkShell } from '@/components/mail-links/mail-link-shell';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import unsubscribe from '@/routes/mail-links/unsubscribe';

type Props = {
    token: string;
    company: string;
    email: string;
    unsubscribed: boolean;
};

/**
 * The way out of Howy's own mails, and back in.
 */
export default function Unsubscribe({
    token,
    company,
    email,
    unsubscribed,
}: Props) {
    const t = useTranslations();

    return (
        <MailLinkShell title={t('Howy mails')} company={company}>
            <div className="flex flex-col gap-2">
                <h1 className="cc-subtitle">
                    {unsubscribed
                        ? t('You are unsubscribed')
                        : t('Unsubscribe from Howy mails?')}
                </h1>
                <p className="text-[15px] leading-[1.6] text-cc-muted">
                    <WithHowy
                        text={
                            unsubscribed
                                ? t(
                                      ':email gets no more welcome, tips or reminders from Howy. Answers to your own questions still come.',
                                      { email },
                                  )
                                : t(
                                      'For :email: no more welcome, tips or reminders from Howy. Answers to your own questions still come.',
                                      { email },
                                  )
                        }
                    />
                </p>
            </div>

            <Form
                {...unsubscribe.store.form(token)}
                options={{ preserveScroll: true }}
            >
                {({ processing }) => (
                    <>
                        {unsubscribed && (
                            <input type="hidden" name="subscribe" value="1" />
                        )}
                        <Button
                            type="submit"
                            variant={unsubscribed ? 'outline' : 'default'}
                            className="h-11 px-[18px]"
                            disabled={processing}
                        >
                            {unsubscribed
                                ? t('Subscribe again')
                                : t('Unsubscribe')}
                        </Button>
                    </>
                )}
            </Form>
        </MailLinkShell>
    );
}
