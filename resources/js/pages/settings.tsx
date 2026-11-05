import { Form, Head } from '@inertiajs/react';
import AccountController from '@/actions/App/Http/Controllers/Settings/AccountController';
import { PageHeader } from '@/components/cc/page-header';
import { Section } from '@/components/cc/section';
import InputError from '@/components/input-error';
import { LocaleFields } from '@/components/locale-fields';
import { MailboxesSection } from '@/components/mailboxes/mailboxes-section';
import { KnowledgeLanguageSection } from '@/components/settings/knowledge-language-section';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/hooks/use-translations';
import type { LocaleOption, Mailbox, MailFacts } from '@/types';

type Props = {
    account: {
        id: number;
        name: string;
        locale: string;
        knowledgeLocale: string;
        timezone: string;
    };
    canManageAccount: boolean;
    canManageMailboxes: boolean;
    mailboxes: Mailbox[];
    openConnect: boolean;
    locales: LocaleOption[];
    timezones: string[];
    sendPolicies: LocaleOption[];
    mailFacts: MailFacts;
};

export default function Settings({
    account,
    canManageAccount,
    canManageMailboxes,
    mailboxes,
    openConnect,
    locales,
    timezones,
    sendPolicies,
    mailFacts,
}: Props) {
    const t = useTranslations();

    return (
        <>
            <Head title={t('Settings')} />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6">
                <PageHeader
                    title={t('Settings')}
                    description={t('Settings for your workspace.')}
                />

                <MailboxesSection
                    mailboxes={mailboxes}
                    canManage={canManageMailboxes}
                    openConnect={openConnect}
                    sendPolicies={sendPolicies}
                    company={account.name}
                    facts={mailFacts}
                />

                <Section
                    title={t('Account')}
                    description={t(
                        'The name, language and timezone for this account. Everyone without their own preference follows these.',
                    )}
                >
                    {canManageAccount ? (
                        <Form
                            {...AccountController.update.form()}
                            options={{ preserveScroll: true }}
                            className="flex flex-col gap-5"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">
                                            {t('Account name')}
                                        </Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            defaultValue={account.name}
                                            required
                                            className="h-11"
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <LocaleFields
                                        locales={locales}
                                        timezones={timezones}
                                        locale={account.locale}
                                        timezone={account.timezone}
                                        errors={errors}
                                    />

                                    <Button
                                        disabled={processing}
                                        className="h-10 self-start"
                                        data-test="update-account-button"
                                    >
                                        {t('Save')}
                                    </Button>
                                </>
                            )}
                        </Form>
                    ) : (
                        <p className="cc-caption">
                            {t(
                                'Only an administrator of this account can change these settings.',
                            )}
                        </p>
                    )}
                </Section>

                {/* Keyed on what is stored, so it starts over whenever that changes. */}
                <KnowledgeLanguageSection
                    key={`${account.id}:${account.knowledgeLocale}`}
                    current={account.knowledgeLocale}
                    locales={locales}
                    canManage={canManageAccount}
                />
            </div>
        </>
    );
}
