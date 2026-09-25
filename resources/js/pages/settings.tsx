import { Form, Head } from '@inertiajs/react';
import { SlidersHorizontal } from 'lucide-react';
import AccountController from '@/actions/App/Http/Controllers/Settings/AccountController';
import { PageHeader } from '@/components/cc/page-header';
import { Section } from '@/components/cc/section';
import InputError from '@/components/input-error';
import { LocaleFields } from '@/components/locale-fields';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/hooks/use-translations';
import type { LocaleOption } from '@/types';

type Props = {
    account: {
        id: number;
        name: string;
        locale: string;
        timezone: string;
    };
    canManageAccount: boolean;
    locales: LocaleOption[];
    timezones: string[];
};

export default function Settings({
    account,
    canManageAccount,
    locales,
    timezones,
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

                <Section
                    title={t('Inbox and knowledge base')}
                    description={t(
                        'This is where your inbox address, your knowledge base folder structure and when cc: asks for confirmation will live.',
                    )}
                >
                    <div className="flex flex-col items-center gap-3 px-6 py-12 text-center">
                        <div className="flex size-14 items-center justify-center rounded-2xl bg-cc-raised text-cc-subtle">
                            <SlidersHorizontal className="size-7" />
                        </div>
                        <p className="text-[15px] font-semibold">
                            {t('Nothing to configure yet')}
                        </p>
                    </div>
                </Section>
            </div>
        </>
    );
}
