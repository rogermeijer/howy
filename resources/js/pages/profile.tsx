import { Form, Head, Link, usePage } from '@inertiajs/react';
import { useRef } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import { PageHeader } from '@/components/cc/page-header';
import { Section } from '@/components/cc/section';
import DeleteUser from '@/components/delete-user';
import InputError from '@/components/input-error';
import { LocaleFields } from '@/components/locale-fields';
import { useTranslations } from '@/hooks/use-translations';
import type { Props as ManagePasskeysProps } from '@/components/manage-passkeys';
import ManagePasskeys from '@/components/manage-passkeys';
import type { Props as ManageTwoFactorProps } from '@/components/manage-two-factor';
import ManageTwoFactor from '@/components/manage-two-factor';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { send } from '@/routes/verification';
import type { Auth, LocaleOption } from '@/types';

// oxfmt-ignore
type Props = {
    mustVerifyEmail: boolean;
    status?: string;
    passwordRules: string;
    locales: LocaleOption[];
    timezones: string[];
} & ManagePasskeysProps &
    ManageTwoFactorProps;

export default function Profile({
    mustVerifyEmail,
    status,
    passwordRules,
    locales,
    timezones,
    ...security
}: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const t = useTranslations();
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    return (
        <>
            <Head title={t('Profile')} />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6">
                <PageHeader
                    title={t('Profile')}
                    description={t(
                        'Your details and the security of your account.',
                    )}
                />

                <Section
                    title={t('Details')}
                    description={t(
                        'Your name and email address. We also use this address to reach you about your inbox.',
                    )}
                >
                    <Form
                        {...ProfileController.update.form()}
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
                                        defaultValue={auth.user.name}
                                        required
                                        autoComplete="name"
                                        placeholder={t('First and last name')}
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
                                        type="email"
                                        name="email"
                                        defaultValue={auth.user.email}
                                        required
                                        autoComplete="username"
                                        placeholder={t('name@company.com')}
                                        className="h-11"
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                {mustVerifyEmail &&
                                    auth.user.email_verified_at === null && (
                                        <div className="rounded-xl bg-cc-pending-bg px-4 py-3 text-[14px] text-cc-pending-fg">
                                            {t(
                                                'Your email address is not verified yet.',
                                            )}{' '}
                                            <Link
                                                href={send()}
                                                as="button"
                                                className="font-semibold underline underline-offset-[3px]"
                                            >
                                                {t(
                                                    'Resend the verification link',
                                                )}
                                            </Link>
                                            {status ===
                                                'verification-link-sent' && (
                                                <span className="mt-1 block font-medium">
                                                    {t(
                                                        'A new link has been sent to your email address.',
                                                    )}
                                                </span>
                                            )}
                                        </div>
                                    )}

                                <LocaleFields
                                    locales={locales}
                                    timezones={timezones}
                                    locale={auth.user.locale}
                                    timezone={auth.user.timezone}
                                    inheritable
                                    errors={errors}
                                />

                                <Button
                                    disabled={processing}
                                    className="h-10 self-start"
                                    data-test="update-profile-button"
                                >
                                    {t('Save')}
                                </Button>
                            </>
                        )}
                    </Form>
                </Section>

                <Section
                    title={t('Password')}
                    description={t(
                        'Use a long, unique password that you do not use anywhere else.',
                    )}
                >
                    <Form
                        {...SecurityController.update.form()}
                        options={{ preserveScroll: true }}
                        resetOnError={[
                            'password',
                            'password_confirmation',
                            'current_password',
                        ]}
                        resetOnSuccess
                        onError={(errors) => {
                            if (errors.password) {
                                passwordInput.current?.focus();
                            }

                            if (errors.current_password) {
                                currentPasswordInput.current?.focus();
                            }
                        }}
                        className="flex flex-col gap-5"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="current_password">
                                        {t('Current password')}
                                    </Label>
                                    <PasswordInput
                                        id="current_password"
                                        ref={currentPasswordInput}
                                        name="current_password"
                                        autoComplete="current-password"
                                        placeholder={t('Your current password')}
                                        className="h-11"
                                    />
                                    <InputError
                                        message={errors.current_password}
                                    />
                                </div>

                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="password">
                                            {t('New password')}
                                        </Label>
                                        <PasswordInput
                                            id="password"
                                            ref={passwordInput}
                                            name="password"
                                            autoComplete="new-password"
                                            placeholder={t('New password')}
                                            passwordrules={passwordRules}
                                            className="h-11"
                                        />
                                        <InputError message={errors.password} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="password_confirmation">
                                            {t('Repeat password')}
                                        </Label>
                                        <PasswordInput
                                            id="password_confirmation"
                                            name="password_confirmation"
                                            autoComplete="new-password"
                                            placeholder={t('Repeat password')}
                                            passwordrules={passwordRules}
                                            className="h-11"
                                        />
                                        <InputError
                                            message={
                                                errors.password_confirmation
                                            }
                                        />
                                    </div>
                                </div>

                                <Button
                                    disabled={processing}
                                    className="h-10 self-start"
                                    data-test="update-password-button"
                                >
                                    {t('Save password')}
                                </Button>
                            </>
                        )}
                    </Form>
                </Section>

                {security.canManageTwoFactor && (
                    <Section
                        title={t('Two-factor authentication')}
                        description={t(
                            'An extra code from your authenticator app when logging in.',
                        )}
                    >
                        <ManageTwoFactor
                            canManageTwoFactor={security.canManageTwoFactor}
                            requiresConfirmation={security.requiresConfirmation}
                            twoFactorEnabled={security.twoFactorEnabled}
                        />
                    </Section>
                )}

                {security.canManagePasskeys && (
                    <Section
                        title={t('Passkeys')}
                        description={t(
                            'Log in with Touch ID, Face ID or your password manager, without a password.',
                        )}
                    >
                        <ManagePasskeys
                            canManagePasskeys={security.canManagePasskeys}
                            passkeys={security.passkeys}
                        />
                    </Section>
                )}

                <Section
                    tone="danger"
                    title={t('Delete account')}
                    description={t(
                        'This deletes your account, your emails and your entire knowledge base. This cannot be undone.',
                    )}
                >
                    <DeleteUser />
                </Section>
            </div>
        </>
    );
}
