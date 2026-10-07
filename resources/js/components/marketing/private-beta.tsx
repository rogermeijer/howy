import { type ReactNode } from 'react';
import { Form, Link, usePage } from '@inertiajs/react';
import { ArrowRight, Check } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { register } from '@/routes';
import { store } from '@/routes/beta-access';
import type { Auth } from '@/types';

/**
 * The private beta notice for guests: a dark band under the nav that says
 * accounts are invite-only, with a way in for those who have a code and a way
 * to ask for one. Renders nothing for someone who is signed in.
 */
export function PrivateBetaBar({ className }: { className?: string }) {
    const page = usePage<{ auth: Auth }>();
    const t = useTranslations();

    if (page.props.auth.user) {
        return null;
    }

    return (
        <div
            className={cn(
                'bg-cc-dark text-cc-bg shadow-[0_6px_14px_-8px_color-mix(in_oklab,var(--color-cc-ink)_60%,transparent)]',
                className,
            )}
        >
            <div className="mx-auto flex w-full max-w-[1320px] items-center gap-x-4 gap-y-2 px-[clamp(16px,4vw,40px)] py-2.5 text-sm">
                <span className="inline-flex shrink-0 items-center gap-2 font-bold text-cc-accent">
                    <span aria-hidden="true" className="relative flex size-2">
                        <span className="absolute inline-flex size-full animate-ping rounded-full bg-cc-accent opacity-60 motion-reduce:hidden" />
                        <span className="relative inline-flex size-2 rounded-full bg-cc-accent" />
                    </span>
                    {t('Private beta')}
                </span>
                <span className="hidden min-w-0 flex-1 text-cc-dark-text md:block">
                    {t(
                        'Howy is invite-only for now. New accounts start with an invite code.',
                    )}
                </span>
                <div className="ml-auto flex shrink-0 items-center gap-1 md:ml-0">
                    {page.component !== 'auth/register' && (
                        <Link
                            href={register()}
                            className="hidden rounded-full px-3 py-2 font-semibold whitespace-nowrap text-cc-bg no-underline transition-colors hover:text-cc-accent sm:inline-flex"
                        >
                            {t('I have a code')}
                        </Link>
                    )}
                    <RequestAccessDialog>
                        <button
                            type="button"
                            className="inline-flex min-h-9 cursor-pointer items-center gap-1.5 rounded-full bg-cc-accent px-4 text-[13px] font-bold whitespace-nowrap text-cc-ink transition-colors hover:bg-cc-accent-hover"
                        >
                            {t('Request access')}
                            <ArrowRight
                                aria-hidden="true"
                                className="size-3.5"
                                strokeWidth={2.6}
                            />
                        </button>
                    </RequestAccessDialog>
                </div>
            </div>
        </div>
    );
}

/**
 * The modal behind every "request access" button. `children` is the trigger.
 * Closing it unmounts the form, so it opens fresh each time.
 */
export function RequestAccessDialog({ children }: { children: ReactNode }) {
    const t = useTranslations();

    return (
        <Dialog>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent className="gap-0 rounded-[20px] border-cc-border bg-cc-panel p-0 text-cc-ink sm:max-w-[460px]">
                <Form
                    {...store.form()}
                    options={{ preserveScroll: true, preserveState: true }}
                    resetOnSuccess
                    disableWhileProcessing
                    className="flex flex-col gap-6 p-[clamp(24px,5vw,36px)]"
                >
                    {({ processing, errors, wasSuccessful }) =>
                        wasSuccessful ? (
                            <div className="flex flex-col items-center gap-4 py-4 text-center">
                                <span className="flex size-14 items-center justify-center rounded-full bg-cc-accent">
                                    <Check
                                        aria-hidden="true"
                                        className="size-7 text-cc-ink"
                                        strokeWidth={2.6}
                                    />
                                </span>
                                <DialogTitle className="text-[22px] leading-tight font-extrabold tracking-[-0.03em]">
                                    {t("You're on the list.")}
                                </DialogTitle>
                                <DialogDescription className="max-w-[320px] text-[15px] leading-[1.55] text-cc-muted">
                                    {t(
                                        "We'll email you an invite code as soon as there's room for your team.",
                                    )}
                                </DialogDescription>
                                <DialogClose asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="mt-2 h-11 cursor-pointer rounded-full border-[1.5px] border-cc-ink px-6 text-[15px] font-semibold"
                                    >
                                        {t('Close')}
                                    </Button>
                                </DialogClose>
                            </div>
                        ) : (
                            <>
                                <div className="flex flex-col gap-3 pr-6">
                                    <DialogTitle className="text-[22px] leading-tight font-extrabold tracking-[-0.03em]">
                                        {t('Request private beta access')}
                                    </DialogTitle>
                                    <DialogDescription className="text-[15px] leading-[1.55] text-cc-muted">
                                        {t(
                                            "We're letting teams in a few at a time. Leave your work email and we'll send you an invite code as soon as there's room.",
                                        )}
                                    </DialogDescription>
                                </div>

                                <div className="grid gap-2">
                                    <Label
                                        htmlFor="beta-access-email"
                                        className="text-[13px] font-semibold"
                                    >
                                        {t('Work email')}
                                    </Label>
                                    <Input
                                        id="beta-access-email"
                                        type="email"
                                        name="email"
                                        required
                                        autoFocus
                                        autoComplete="email"
                                        placeholder={t('name@company.com')}
                                        className="h-12 rounded-[10px] border-[1.5px] border-cc-border-strong bg-white px-4 text-[15px] md:text-[15px]"
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="flex flex-col gap-3">
                                    <Button
                                        type="submit"
                                        className="h-12 w-full cursor-pointer rounded-full text-[15px] font-bold hover:bg-cc-accent-hover"
                                    >
                                        {processing && <Spinner />}
                                        {t('Request access')}
                                    </Button>
                                    <p className="m-0 text-center text-[13px] text-cc-subtle">
                                        {t(
                                            'We only use your address to send your invite.',
                                        )}
                                    </p>
                                </div>
                            </>
                        )
                    }
                </Form>
            </DialogContent>
        </Dialog>
    );
}
