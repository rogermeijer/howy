import { Form, usePage } from '@inertiajs/react';
import { ArrowRight, Info } from 'lucide-react';
import SetupController from '@/actions/App/Http/Controllers/SetupController';
import { AccountFields } from '@/components/account-fields';
import { WithHowy } from '@/components/brand/howy-name';
import { LocaleFields } from '@/components/locale-fields';
import { SetupStep } from '@/components/setup/setup-step';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import type { Auth, LocaleOption } from '@/types';

type Props = {
    account: {
        name: string;
        domain: string | null;
        locale: string;
        timezone: string;
    };
    locales: LocaleOption[];
    timezones: string[];
};

/**
 * Setup, step one: the basics registration filled in, to check, and the
 * domain the company mails from.
 */
export default function Workspace({ account, locales, timezones }: Props) {
    const t = useTranslations();
    const { auth } = usePage<{ auth: Auth }>().props;
    const firstName = auth.user.name.split(' ')[0];

    return (
        <Form
            {...SetupController.updateWorkspace.form()}
            disableWhileProcessing
            className="flex flex-col gap-6"
        >
            {({ processing, errors }) => (
                <SetupStep
                    current="workspace"
                    head={t('Your workspace')}
                    eyebrow={t('Step 1 of 3 · About 3 minutes in all')}
                    title={
                        <WithHowy
                            text={t('Welcome to Howy, :name', {
                                name: firstName,
                            })}
                        />
                    }
                    description={
                        <WithHowy
                            text={t(
                                'Three short steps and Howy is ready for your first thread. First the basics of your workspace: we filled them in from your registration, so check them and carry on.',
                            )}
                        />
                    }
                    footer={
                        <div className="flex justify-end">
                            <Button
                                type="submit"
                                className="h-11 px-5"
                                data-test="setup-workspace-button"
                            >
                                {processing && <Spinner />}
                                {t('Continue')}
                                <ArrowRight />
                            </Button>
                        </div>
                    }
                    note={t('You can change all of this later in Settings.')}
                >
                    <div className="flex flex-col gap-5">
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
                        <p className="flex gap-3 rounded-xl bg-cc-bg px-4 py-3.5 text-[13px] leading-[1.5] text-cc-muted">
                            <Info className="mt-px size-[18px] shrink-0" />
                            <span>
                                {t(
                                    'Everyone in this account without their own preference follows this language and timezone, for the screens and for dates and times. It is not the language of your knowledge base: you choose that next.',
                                )}
                            </span>
                        </p>
                    </div>
                </SetupStep>
            )}
        </Form>
    );
}
