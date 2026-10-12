import { Form } from '@inertiajs/react';
import { AdminShell, BackLink } from '@/components/admin/admin-shell';
import { Section } from '@/components/cc/section';
import InputError from '@/components/input-error';
import { LocaleFields } from '@/components/locale-fields';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/hooks/use-translations';
import { index, store } from '@/routes/admin/accounts';
import type { LocaleOption } from '@/types';

type Props = {
    locales: LocaleOption[];
    timezones: string[];
    defaultTimezone: string;
};

export default function AdminCreateAccount({
    locales,
    timezones,
    defaultTimezone,
}: Props) {
    const t = useTranslations();

    return (
        <AdminShell
            title={t('New account')}
            breadcrumb={<BackLink href={index()}>{t('Accounts')}</BackLink>}
        >
            <div className="max-w-3xl">
                <Section
                    title={t('Account details')}
                    description={t(
                        'The owner becomes the first administrator. Create the user first if they do not have one yet.',
                    )}
                >
                    <Form {...store.form()} className="flex flex-col gap-5">
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">
                                        {t('Account name')}
                                    </Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        autoFocus
                                        className="h-11"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="owner_email">
                                        {t('Owner email address')}
                                    </Label>
                                    <Input
                                        id="owner_email"
                                        name="owner_email"
                                        type="email"
                                        required
                                        placeholder={t('name@company.com')}
                                        className="h-11"
                                    />
                                    <InputError message={errors.owner_email} />
                                </div>

                                <LocaleFields
                                    locales={locales}
                                    timezones={timezones}
                                    locale={locales[0]?.value ?? null}
                                    timezone={defaultTimezone}
                                    errors={errors}
                                />

                                <Button
                                    disabled={processing}
                                    className="h-10 self-start"
                                >
                                    {t('Create account')}
                                </Button>
                            </>
                        )}
                    </Form>
                </Section>
            </div>
        </AdminShell>
    );
}
