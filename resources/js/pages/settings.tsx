import { Form, Head } from '@inertiajs/react';
import AccountController from '@/actions/App/Http/Controllers/Settings/AccountController';
import { AccountFields } from '@/components/account-fields';
import { PageHeader } from '@/components/cc/page-header';
import { Section } from '@/components/cc/section';
import { LocaleFields } from '@/components/locale-fields';
import { MailboxesSection } from '@/components/mailboxes/mailboxes-section';
import { KnowledgeLanguageSection } from '@/components/settings/knowledge-language-section';
import { LiveSection } from '@/components/settings/live-section';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import type {
    LiveState,
    LocaleOption,
    Mailbox,
    MailFacts,
    WelcomeCandidate,
} from '@/types';

type Props = {
    account: {
        id: number;
        name: string;
        domain: string | null;
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
    publicDomains: string[];
    mailFacts: MailFacts;
    live: LiveState;
    /** Loaded when the dialog to go live opens. */
    welcomeList?: WelcomeCandidate[];
};

export default function Settings({
    account,
    canManageAccount,
    canManageMailboxes,
    mailboxes,
    openConnect,
    locales,
    timezones,
    publicDomains,
    mailFacts,
    live,
    welcomeList,
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
                    publicDomains={publicDomains}
                    company={account.name}
                    facts={mailFacts}
                    isLive={live.since !== null}
                />

                <LiveSection
                    live={live}
                    candidates={welcomeList}
                    canManage={canManageAccount}
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
                                    <AccountFields
                                        name={account.name}
                                        domain={account.domain}
                                        errors={errors}
                                    />

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
