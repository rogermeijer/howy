import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { RequestAccessDialog } from '@/components/marketing/private-beta';
import { store } from '@/routes/register';
import {
    destroy as forgetVoucher,
    store as checkVoucher,
} from '@/routes/register/voucher';
import { useTranslations } from '@/hooks/use-translations';

type Props = {
    passwordRules: string;
    /** The checked invite code, or null while the code step still has to be done. */
    voucher: string | null;
};

export default function Register({ passwordRules, voucher }: Props) {
    const t = useTranslations();

    return (
        <>
            <Head title={t('Create an account')} />
            {voucher ? (
                <DetailsStep passwordRules={passwordRules} voucher={voucher} />
            ) : (
                <CodeStep />
            )}
        </>
    );
}

/**
 * Private beta: the code comes first. The server checks it again on
 * registration, so this step is a courtesy, not the gate.
 */
function CodeStep() {
    const t = useTranslations();
    // A code that ran out between the two steps comes back as a `voucher`
    // error from the registration itself, not from this form.
    const { errors: pageErrors } = usePage().props;

    return (
        <div className="flex flex-col gap-6">
            <Form
                {...checkVoucher.form()}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="flex gap-3 rounded-xl border border-cc-dark-border bg-cc-dark-1 p-4 text-sm leading-[1.5] text-cc-dark-text">
                            <span
                                aria-hidden="true"
                                className="mt-1.5 size-2 shrink-0 rounded-full bg-cc-accent"
                            />
                            <p className="m-0">
                                {t(
                                    'Howy is in private beta. Enter the invite code you received to create your account.',
                                )}
                            </p>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="code">{t('Invite code')}</Label>
                            <Input
                                id="code"
                                type="text"
                                name="code"
                                required
                                autoFocus
                                autoComplete="off"
                                autoCapitalize="characters"
                                spellCheck={false}
                                placeholder={t('HOWY-XXXX-XXXX')}
                                className="font-mono tracking-[0.12em] uppercase placeholder:tracking-[0.12em]"
                            />
                            <InputError
                                message={errors.code ?? pageErrors.voucher}
                            />
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            data-test="check-voucher-button"
                        >
                            {processing && <Spinner />}
                            {t('Continue')}
                        </Button>
                    </>
                )}
            </Form>

            {/* Outside the form: the dialog holds a form of its own, and React
                bubbles its submit through the portal to any form around it. */}
            <div className="flex flex-col gap-2 text-center text-sm text-muted-foreground">
                <div>
                    {t('No code yet?')}{' '}
                    <RequestAccessDialog>
                        <button
                            type="button"
                            className="cursor-pointer font-semibold text-cc-bg underline underline-offset-[3px] transition-colors hover:text-cc-accent"
                        >
                            {t('Request access')}
                        </button>
                    </RequestAccessDialog>
                </div>
                <div>
                    {t('Already have an account?')}{' '}
                    <TextLink href={login()}>{t('Log in')}</TextLink>
                </div>
            </div>
        </div>
    );
}

function DetailsStep({ passwordRules, voucher }: Props & { voucher: string }) {
    const t = useTranslations();

    return (
        <div className="flex flex-col gap-6">
            <div className="flex items-center justify-between gap-3 rounded-xl border border-cc-dark-border bg-cc-dark-1 px-4 py-3">
                <div className="flex min-w-0 items-center gap-3">
                    <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-cc-accent">
                        <Check
                            aria-hidden="true"
                            className="size-4 text-cc-ink"
                            strokeWidth={2.8}
                        />
                    </span>
                    <div className="min-w-0">
                        <div className="text-xs text-cc-faint">
                            {t('Invite code')}
                        </div>
                        <div className="truncate font-mono text-sm font-semibold tracking-[0.08em] text-cc-bg">
                            {voucher}
                        </div>
                    </div>
                </div>
                <Link
                    href={forgetVoucher()}
                    as="button"
                    className="shrink-0 cursor-pointer rounded-full px-3 py-1.5 text-[13px] font-semibold text-cc-dark-text transition-colors hover:text-cc-accent"
                >
                    {t('Change')}
                </Link>
            </div>

            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">{t('Name')}</Label>
                                <Input
                                    id="name"
                                    type="text"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="name"
                                    name="name"
                                    placeholder={t('First and last name')}
                                />
                                <InputError
                                    message={errors.name}
                                    className="mt-2"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="account_name">
                                    {t('Company name')}
                                </Label>
                                <Input
                                    id="account_name"
                                    type="text"
                                    required
                                    tabIndex={2}
                                    autoComplete="organization"
                                    name="account_name"
                                    placeholder={t(
                                        'Name of your company or team',
                                    )}
                                />
                                <InputError message={errors.account_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {t('Email address')}
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    tabIndex={3}
                                    autoComplete="email"
                                    name="email"
                                    placeholder={t('name@company.com')}
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">
                                    {t('Password')}
                                </Label>
                                <PasswordInput
                                    id="password"
                                    required
                                    tabIndex={4}
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder={t('Choose a password')}
                                    passwordrules={passwordRules}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    {t('Confirm password')}
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    required
                                    tabIndex={5}
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder={t('Repeat your password')}
                                    passwordrules={passwordRules}
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 w-full"
                                tabIndex={6}
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                {t('Create an account')}
                            </Button>
                        </div>

                        <div className="text-center text-sm text-muted-foreground">
                            {t('Already have an account?')}{' '}
                            <TextLink href={login()} tabIndex={7}>
                                {t('Log in')}
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </div>
    );
}

Register.layout = {
    title: 'Create an account',
    description: 'Add Howy to your next thread and keep knowledge findable.',
};
