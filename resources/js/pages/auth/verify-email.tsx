// Components
import { Form, Head, Link, usePoll } from '@inertiajs/react';
import { LoaderCircle, Mail } from 'lucide-react';
import { WithHowy } from '@/components/brand/howy-name';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { dashboard, logout } from '@/routes';
import { send } from '@/routes/verification';
import { useTranslations } from '@/hooks/use-translations';

type Props = {
    status?: string;
    email: string | null;
    /** Setup follows verification, for an administrator of a new account. */
    setupAhead: boolean;
};

// English source strings, translated where rendered.
const setupSteps = [
    {
        title: 'Your workspace',
        detail: 'Its name, email domain, language and timezone',
    },
    {
        title: 'Knowledge base language',
        detail: 'The one language Howy writes your knowledge in',
    },
    {
        title: 'An email address for Howy',
        detail: 'Optional: you can skip it for now',
    },
];

export default function VerifyEmail({ status, email, setupAhead }: Props) {
    const t = useTranslations();

    // Once the link is clicked, in this browser or on another device, this
    // page redirects onwards; looking again now and then is enough to follow.
    usePoll(5000);

    return (
        <>
            <Head title={t('Verify email')} />

            <div className="flex flex-col items-center gap-4 text-center">
                <div className="flex size-[52px] items-center justify-center rounded-[14px] bg-cc-dark-2 text-cc-accent">
                    <Mail className="size-6" />
                </div>
                {email && (
                    <p className="m-0 text-sm leading-[1.5] text-cc-dark-text">
                        {t('We sent the link to :email.', { email })}
                    </p>
                )}
            </div>

            {status === 'verification-link-sent' && (
                <div className="text-center text-sm font-medium text-cc-accent">
                    {t(
                        'We have sent a new verification link to the email address you registered with.',
                    )}
                </div>
            )}

            <div
                role="status"
                className="flex items-center justify-center gap-2.5 text-[13px] text-cc-dark-text"
            >
                <LoaderCircle className="size-4 animate-spin text-cc-accent motion-reduce:animate-none" />
                {t(
                    'Waiting for you to click the link. This page moves on by itself.',
                )}
            </div>

            {setupAhead && (
                <div className="flex flex-col gap-3.5 rounded-xl border border-cc-dark-border bg-cc-dark-1 p-[18px]">
                    <div className="flex items-baseline justify-between gap-3">
                        <p className="m-0 text-[11px] font-semibold tracking-[0.08em] text-cc-dark-text uppercase">
                            {t('Then three short steps')}
                        </p>
                        <p className="m-0 text-[12px] text-cc-dark-text">
                            {t('About 3 minutes')}
                        </p>
                    </div>
                    <ol className="m-0 flex list-none flex-col gap-3 p-0">
                        {setupSteps.map((step, index) => (
                            <li key={step.title} className="flex gap-3">
                                <span
                                    aria-hidden="true"
                                    className="flex size-6 shrink-0 items-center justify-center rounded-full border-[1.5px] border-cc-dark-border text-[12px] font-bold text-cc-accent"
                                >
                                    {index + 1}
                                </span>
                                <span className="flex flex-col gap-0.5">
                                    <span className="text-sm font-semibold text-cc-bg">
                                        {t(step.title)}
                                    </span>
                                    <span className="text-[13px] text-cc-dark-text">
                                        <WithHowy text={t(step.detail)} />
                                    </span>
                                </span>
                            </li>
                        ))}
                    </ol>
                </div>
            )}

            <Form {...send.form()} className="flex flex-col gap-4 text-center">
                {({ processing }) => (
                    <>
                        <Button
                            disabled={processing}
                            variant="secondary"
                            className="h-11 w-full"
                        >
                            {processing && <Spinner />}
                            {t('Resend')}
                        </Button>

                        <p className="m-0 text-sm text-cc-dark-text">
                            {t('Verified on another device?')}{' '}
                            <Link
                                href={dashboard()}
                                className="font-semibold text-cc-bg underline underline-offset-[3px] hover:text-cc-accent"
                            >
                                {t('Continue')}
                            </Link>
                        </p>

                        <TextLink
                            href={logout()}
                            className="mx-auto block text-sm"
                        >
                            {t('Log out')}
                        </TextLink>
                    </>
                )}
            </Form>
        </>
    );
}

VerifyEmail.layout = {
    title: 'Verify your email address',
    description: 'Click the link we just emailed you to confirm your address.',
};
