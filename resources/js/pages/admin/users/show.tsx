import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { AdminShell, BackLink, Chip } from '@/components/admin/admin-shell';
import { DeleteDialog } from '@/components/admin/delete-dialog';
import { MembershipRows } from '@/components/admin/members-list';
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
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslations } from '@/hooks/use-translations';
import { show as showAccount } from '@/routes/admin/accounts';
import { store as addMember } from '@/routes/admin/accounts/members';
import { destroy, index, update } from '@/routes/admin/users';
import type { AccountOption, AdminMembership, LocaleOption } from '@/types';

type Props = {
    user: {
        id: number;
        name: string;
        email: string;
        locale: string | null;
        is_super_admin: boolean;
        email_verified: boolean;
        two_factor: boolean;
        beta_code: string | null;
        created_at: string | null;
        is_self: boolean;
    };
    memberships: AdminMembership[];
    accounts: AccountOption[];
    locales: LocaleOption[];
};

export default function AdminUser({
    user,
    memberships,
    accounts,
    locales,
}: Props) {
    const t = useTranslations();
    const formatDate = useFormatDate();
    const joinable = accounts.filter(
        (account) => !memberships.some((m) => m.id === account.id),
    );
    const [accountId, setAccountId] = useState<string>('');

    return (
        <AdminShell
            title={user.name}
            description={
                <span className="flex flex-wrap items-center gap-2">
                    {user.email}
                    {user.is_super_admin && (
                        <Chip tone="info">{t('Super-admin')}</Chip>
                    )}
                    {!user.email_verified && (
                        <Chip tone="warn">{t('Unverified')}</Chip>
                    )}
                    {user.two_factor && (
                        <Chip tone="good">{t('Two-factor on')}</Chip>
                    )}
                </span>
            }
            breadcrumb={<BackLink href={index()}>{t('Users')}</BackLink>}
        >
            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <Section
                    title={t('Profile')}
                    description={
                        [
                            user.created_at
                                ? t('Joined :date', {
                                      date: formatDate(user.created_at),
                                  })
                                : null,
                            user.beta_code
                                ? t('Registered with invite code :code', {
                                      code: user.beta_code,
                                  })
                                : null,
                        ]
                            .filter(Boolean)
                            .join(' · ') || undefined
                    }
                >
                    <Form
                        {...update.form(user.id)}
                        options={{ preserveScroll: true }}
                        className="flex flex-col gap-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">{t('Name')}</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        defaultValue={user.name}
                                        required
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
                                        defaultValue={user.email}
                                        required
                                        className="h-11"
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <UserLocaleSelect
                                    locales={locales}
                                    value={user.locale}
                                    error={errors.locale}
                                />

                                <div className="flex flex-col gap-2">
                                    {user.is_self ? (
                                        <>
                                            <input
                                                type="hidden"
                                                name="is_super_admin"
                                                value="1"
                                            />
                                            <label className="flex items-center gap-2 text-sm text-cc-subtle">
                                                <Checkbox checked disabled />
                                                {t(
                                                    'Super-admin (you cannot remove this from yourself)',
                                                )}
                                            </label>
                                        </>
                                    ) : (
                                        <label className="flex items-center gap-2 text-sm">
                                            <Checkbox
                                                name="is_super_admin"
                                                value="1"
                                                defaultChecked={
                                                    user.is_super_admin
                                                }
                                            />
                                            {t(
                                                'Super-admin: full access to this admin area',
                                            )}
                                        </label>
                                    )}
                                    <InputError
                                        message={errors.is_super_admin}
                                    />
                                </div>

                                <Button
                                    disabled={processing}
                                    className="h-10 self-start"
                                >
                                    {t('Save')}
                                </Button>
                            </>
                        )}
                    </Form>
                </Section>

                <div className="flex flex-col gap-6">
                    <Section title={t('Accounts')}>
                        <MembershipRows
                            empty={t(
                                'Not a member of any account. Without one, this user cannot use Howy.',
                            )}
                            rows={memberships.map((membership) => ({
                                accountId: membership.id,
                                userId: user.id,
                                title: membership.name,
                                href: showAccount(membership.id),
                                isAdmin: membership.is_admin,
                            }))}
                        />

                        {joinable.length > 0 && (
                            <Form
                                {...addMember.form(Number(accountId) || 0)}
                                options={{ preserveScroll: true }}
                                onSuccess={() => setAccountId('')}
                                className="flex flex-col gap-3 sm:flex-row sm:items-end"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="email"
                                            value={user.email}
                                        />
                                        <div className="grid flex-1 gap-2">
                                            <Label htmlFor="join-account">
                                                {t('Add to an account')}
                                            </Label>
                                            <Select
                                                value={accountId}
                                                onValueChange={setAccountId}
                                            >
                                                <SelectTrigger
                                                    id="join-account"
                                                    className="h-11 w-full"
                                                >
                                                    <SelectValue
                                                        placeholder={t(
                                                            'Choose an account',
                                                        )}
                                                    />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {joinable.map((account) => (
                                                        <SelectItem
                                                            key={account.id}
                                                            value={String(
                                                                account.id,
                                                            )}
                                                        >
                                                            {account.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={errors.email}
                                            />
                                        </div>
                                        <label className="flex h-11 items-center gap-2 text-sm">
                                            <Checkbox
                                                name="is_admin"
                                                value="1"
                                            />
                                            {t('Administrator')}
                                        </label>
                                        <Button
                                            disabled={processing || !accountId}
                                            className="h-11"
                                        >
                                            {t('Add')}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        )}
                    </Section>

                    {!user.is_self && (
                        <Section
                            tone="danger"
                            title={t('Delete user')}
                            description={t(
                                'Deletes the user and their sign-in. The accounts they belong to stay, with all their data.',
                            )}
                        >
                            <DeleteDialog
                                action={destroy.form(user.id)}
                                trigger={t('Delete user')}
                                title={t('Delete :name?', { name: user.name })}
                                description={t('This cannot be undone.')}
                            />
                        </Section>
                    )}
                </div>
            </div>
        </AdminShell>
    );
}
