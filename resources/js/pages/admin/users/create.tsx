import { Form } from '@inertiajs/react';
import { AdminShell, BackLink } from '@/components/admin/admin-shell';
import { UserLocaleSelect } from '@/components/admin/user-locale-select';
import { Section } from '@/components/cc/section';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslations } from '@/hooks/use-translations';
import { index, store } from '@/routes/admin/users';
import type { AccountOption, LocaleOption } from '@/types';

type Props = {
    accounts: AccountOption[];
    locales: LocaleOption[];
    accountId: number | null;
};

export default function AdminCreateUser({
    accounts,
    locales,
    accountId,
}: Props) {
    const t = useTranslations();

    return (
        <AdminShell
            title={t('New user')}
            breadcrumb={<BackLink href={index()}>{t('Users')}</BackLink>}
        >
            <div className="max-w-3xl">
                <Section
                    title={t('User details')}
                    description={t(
                        'The user gets an email with a link to choose their own password.',
                    )}
                >
                    <Form {...store.form()} className="flex flex-col gap-5">
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">
                                            {t('Name')}
                                        </Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            required
                                            autoFocus
                                            placeholder={t(
                                                'First and last name',
                                            )}
                                            className="h-11"
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="email">
                                            {t('Email address')}
                                        </Label>
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                            required
                                            placeholder={t('name@company.com')}
                                            className="h-11"
                                        />
                                        <InputError message={errors.email} />
                                    </div>
                                </div>

                                <UserLocaleSelect
                                    locales={locales}
                                    value={null}
                                    error={errors.locale}
                                />

                                <div className="grid gap-2">
                                    <Label htmlFor="account_id">
                                        {t('Account')}
                                    </Label>
                                    <Select
                                        name="account_id"
                                        defaultValue={
                                            accountId
                                                ? String(accountId)
                                                : undefined
                                        }
                                    >
                                        <SelectTrigger
                                            id="account_id"
                                            className="h-11 w-full"
                                        >
                                            <SelectValue
                                                placeholder={t(
                                                    'No account yet',
                                                )}
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {accounts.map((account) => (
                                                <SelectItem
                                                    key={account.id}
                                                    value={String(account.id)}
                                                >
                                                    {account.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.account_id} />
                                </div>

                                <div className="flex flex-col gap-3">
                                    <label className="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            name="account_admin"
                                            value="1"
                                        />
                                        {t('Administrator of this account')}
                                    </label>
                                    <label className="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            name="is_super_admin"
                                            value="1"
                                        />
                                        {t(
                                            'Super-admin: full access to this admin area',
                                        )}
                                    </label>
                                </div>

                                <Button
                                    disabled={processing}
                                    className="h-10 self-start"
                                >
                                    {t('Create user')}
                                </Button>
                            </>
                        )}
                    </Form>
                </Section>
            </div>
        </AdminShell>
    );
}
