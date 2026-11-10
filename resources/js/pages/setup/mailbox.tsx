import { Form, Link, router, usePoll } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    Download,
    ExternalLink,
    ShieldCheck,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import MailboxController from '@/actions/App/Http/Controllers/Mailboxes/MailboxController';
import SetupController from '@/actions/App/Http/Controllers/SetupController';
import { HowyAvatar } from '@/components/brand/howy-avatar';
import { WithHowy } from '@/components/brand/howy-name';
import { ConnectMailboxOptions } from '@/components/mailboxes/connect-mailbox-options';
import { ImportMailboxDialog } from '@/components/mailboxes/import-mailbox-dialog';
import { ImportProgress } from '@/components/mailboxes/import-progress';
import { MailboxExplainerDialog } from '@/components/mailboxes/mailbox-explainer-dialog';
import { useAccessLooks } from '@/components/mailboxes/sender-access';
import { SetupStep } from '@/components/setup/setup-step';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { ACCESSES, type MailboxRules } from '@/lib/mailbox-rules';
import { cn } from '@/lib/utils';
import mailboxRoutes from '@/routes/mailboxes';
import setup from '@/routes/setup';
import type { Mailbox, MailFacts, SenderAccess } from '@/types';

type Props = {
    /** The domain the company mails from, for Howy's own address there. */
    domain: string | null;
    company: string;
    /** The mailbox just connected, back from Google. */
    mailbox: Mailbox | null;
    mailFacts: MailFacts;
};

/**
 * Setup, step three, which can wait: a mailbox, ideally one of Howy's own.
 * Back from Google with one, the step is about whose mail Howy reads.
 */
export default function MailboxStep({
    domain,
    company,
    mailbox,
    mailFacts,
}: Props) {
    return mailbox ? (
        <Connected mailbox={mailbox} />
    ) : (
        <Connect domain={domain} company={company} mailFacts={mailFacts} />
    );
}

function Connect({
    domain,
    company,
    mailFacts,
}: Pick<Props, 'domain' | 'company' | 'mailFacts'>) {
    const t = useTranslations();
    const [explaining, setExplaining] = useState(false);
    const address = domain ? `howy@${domain}` : t('howy@yourcompany.com');

    // A full navigation, not an Inertia visit: this leaves the app for Google,
    // and comes back to this step.
    const google = mailboxRoutes.gmail.redirect.url({
        query: { from: 'setup' },
    });

    // No mailbox yet: the explanation works from Howy's own address.
    const exampleRules: MailboxRules = {
        address,
        domain: address.slice(address.indexOf('@') + 1),
        publicDomain: false,
        domainAccess: 'learn_answer',
        senders: [],
    };

    const steps = [
        t(
            'In the Google Admin console, go to Directory › Users and add a user named Howy with the address :address.',
            { address },
        ),
        t('Sign in as :address once, so its mailbox exists.', { address }),
        t('Come back here, choose Go to Google and sign in as :address.', {
            address,
        }),
    ];

    return (
        <SetupStep
            current="mailbox"
            head={t('Mailbox')}
            eyebrow={t('Step 3 of 3 · Optional')}
            title={<WithHowy text={t('Give Howy its own address')} />}
            description={
                <WithHowy
                    text={t(
                        'Howy works best as a colleague with a mailbox of its own: :address. Your team CCs Howy like anyone else, and Howy processes every new email that arrives there.',
                        { address },
                    )}
                />
            }
            footer={
                <>
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <Button
                            asChild
                            variant="outline"
                            className="h-11 px-[18px]"
                        >
                            <Link href={setup.knowledge()}>
                                <ArrowLeft />
                                {t('Back')}
                            </Link>
                        </Button>
                        <div className="flex flex-wrap items-center justify-end gap-2.5">
                            <Form {...SetupController.finish.form()}>
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="secondary"
                                        disabled={processing}
                                        className="h-11 px-[18px]"
                                        data-test="setup-skip-mailbox-button"
                                    >
                                        {t('Skip for now')}
                                    </Button>
                                )}
                            </Form>
                            <Button
                                asChild
                                className="h-11 px-5 whitespace-nowrap"
                            >
                                <a href={google}>
                                    {t('Go to Google')}
                                    <ExternalLink />
                                </a>
                            </Button>
                        </div>
                    </div>
                    <p className="text-right text-[12px] text-cc-subtle">
                        {t(
                            'At Google, sign in as :address and grant access. You come straight back here.',
                            { address },
                        )}
                    </p>
                </>
            }
            note={t(
                'No Workspace admin at hand? Skip it. The inbox reminds you, and you can connect :address anytime in Settings.',
                { address },
            )}
        >
            <div className="grid gap-3 sm:grid-cols-2">
                <div className="flex flex-col gap-2.5 rounded-2xl border-[1.5px] border-cc-knowledge-fg/25 bg-cc-knowledge-bg/40 p-4">
                    <span className="cc-tag cc-tag-knowledge self-start">
                        <span>
                            <WithHowy text={t('Howy in CC')} />
                        </span>
                    </span>
                    <p className="text-[14px] leading-[1.55]">
                        <WithHowy
                            text={t(
                                'Howy learns. What the thread settles is proposed for your knowledge base, with a link back to the thread. It counts once someone approves it.',
                            )}
                        />
                    </p>
                </div>
                <div className="flex flex-col gap-2.5 rounded-2xl border-[1.5px] border-cc-question-fg/25 bg-cc-question-bg/40 p-4">
                    <span className="cc-tag cc-tag-question self-start">
                        <span>
                            <WithHowy text={t('Howy in To')} />
                        </span>
                    </span>
                    <p className="text-[14px] leading-[1.55]">
                        <WithHowy
                            text={t(
                                'Howy is asked something. It looks the answer up in the approved knowledge, and only writes to senders you allow.',
                            )}
                        />
                    </p>
                </div>
            </div>

            <div className="flex flex-col gap-3">
                <div className="flex flex-col gap-4 rounded-xl border-[1.5px] border-cc-ink p-[18px]">
                    <div className="flex flex-wrap items-center gap-3">
                        <HowyAvatar />
                        <div className="flex min-w-[200px] flex-1 flex-col gap-0.5">
                            <p className="flex flex-wrap items-center gap-2 text-[16px] font-bold break-all">
                                {address}
                                <span className="cc-tag cc-tag-decision">
                                    {t('Recommended')}
                                </span>
                            </p>
                            <p className="cc-caption">
                                <WithHowy
                                    text={t(
                                        'A user of its own in your Google Workspace, so Howy is on the team like any colleague.',
                                    )}
                                />
                            </p>
                        </div>
                    </div>
                    <ol className="flex flex-col gap-2.5 text-[14px] leading-[1.5]">
                        {steps.map((step, index) => (
                            <li key={index} className="flex items-start gap-3">
                                <span
                                    aria-hidden="true"
                                    className="flex size-6 shrink-0 items-center justify-center rounded-full border-[1.5px] border-cc-border-strong text-[12px] font-bold"
                                >
                                    {index + 1}
                                </span>
                                <span className="min-w-0 break-words">
                                    <WithHowy text={step} />
                                </span>
                            </li>
                        ))}
                    </ol>
                    <div className="flex flex-wrap items-center gap-3">
                        <Button asChild variant="outline" className="h-11 px-4">
                            <a
                                href="https://admin.google.com/ac/users"
                                target="_blank"
                                rel="noreferrer"
                            >
                                {t('Open Google Admin console')}
                                <ExternalLink />
                            </a>
                        </Button>
                        <span className="text-[12px] text-cc-subtle">
                            {t('A Workspace user takes a license.')}
                        </span>
                    </div>
                </div>
                <p className="cc-caption">
                    {t(
                        'Rather use a mailbox you already share, such as support@? Choose Go to Google and sign in with that one.',
                    )}
                </p>
            </div>

            <div className="flex flex-col gap-3">
                <ConnectMailboxOptions />
                <button
                    type="button"
                    onClick={() => setExplaining(true)}
                    className="inline-flex cursor-pointer items-center gap-2 self-start text-[14px] font-semibold text-cc-accent-deep underline underline-offset-[3px] hover:text-cc-ink"
                >
                    <ShieldCheck className="size-4" />
                    <span>
                        <WithHowy text={t('What does Howy do with mail?')} />
                    </span>
                </button>
            </div>

            <MailboxExplainerDialog
                open={explaining}
                onOpenChange={setExplaining}
                rules={exampleRules}
                company={company}
                facts={mailFacts}
                note={t(
                    'An example mailbox. Once a mailbox is connected, this follows its own rules.',
                )}
            />
        </SetupStep>
    );
}

function Connected({ mailbox }: { mailbox: Mailbox }) {
    const t = useTranslations();
    const looks = useAccessLooks();
    const [access, setAccess] = useState<SenderAccess>(mailbox.domainAccess);
    const [importing, setImporting] = useState(false);
    const importRunning = mailbox.import !== null;

    // Keep the progress moving while an import runs.
    const { start, stop } = usePoll(
        3000,
        { only: ['mailbox'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (importRunning) {
            start();
        } else {
            stop();
        }
    }, [importRunning, start, stop]);

    // Saved as it is chosen, through the same rules as on the settings page;
    // the exceptions stay as they are.
    const choose = (value: SenderAccess) => {
        setAccess(value);
        router.patch(
            MailboxController.updateRules.url(mailbox.id),
            { domain_access: value, sender_rules: mailbox.senderRules },
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => setAccess(mailbox.domainAccess),
            },
        );
    };

    return (
        <SetupStep
            current="mailbox"
            head={t('Mailbox')}
            eyebrow={t('Step 3 of 3')}
            title={<WithHowy text={t('Whose mail may Howy read?')} />}
            description={
                <WithHowy
                    text={t(
                        'Per mailbox you decide whom Howy learns from and whom it answers. Start with your colleagues; exceptions, such as the domain of a partner, can follow later.',
                    )}
                />
            }
            banner={
                <div
                    role="status"
                    className="flex flex-wrap items-center gap-3.5 rounded-xl bg-cc-accent-tint p-4"
                >
                    <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-cc-accent">
                        <Check className="size-[18px]" strokeWidth={3} />
                    </span>
                    <div className="flex min-w-[200px] flex-1 flex-col gap-0.5">
                        <p className="flex flex-wrap items-center gap-2 text-[15px] font-bold break-all">
                            {t(':address is connected.', {
                                address: mailbox.emailAddress,
                            })}
                            <span className="cc-tag cc-tag-decision">
                                {t('Live')}
                            </span>
                        </p>
                        <p className="text-[13px] text-cc-accent-deep">
                            {t('New emails come in right away.')}
                        </p>
                    </div>
                </div>
            }
            footer={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <span className="cc-caption">
                        {t(
                            'Saved for this mailbox. Change it anytime in Settings.',
                        )}
                    </span>
                    <Form {...SetupController.finish.form()}>
                        {({ processing }) => (
                            <Button
                                type="submit"
                                disabled={processing}
                                className="h-11 px-5"
                                data-test="setup-finish-button"
                            >
                                {processing && <Spinner />}
                                {t('Finish setup')}
                                <ArrowRight />
                            </Button>
                        )}
                    </Form>
                </div>
            }
        >
            {mailbox.publicDomain ? (
                <p className="rounded-xl bg-cc-bg px-4 py-3.5 text-[13px] leading-[1.5] text-cc-muted">
                    <WithHowy
                        text={t(
                            'Anyone can have an address on :domain, so there are no colleagues to read by domain. Put the senders Howy may read on the list, in Settings.',
                            { domain: mailbox.domain },
                        )}
                    />
                </p>
            ) : (
                <fieldset className="flex min-w-0 flex-col gap-2.5">
                    <legend className="mb-2.5 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                        <span className="text-[15px] font-semibold">
                            {t('Colleagues at :domain', {
                                domain: mailbox.domain,
                            })}
                        </span>
                        <span className="cc-caption">
                            {t(
                                'Everyone with an address on the domain of the mailbox',
                            )}
                        </span>
                    </legend>
                    <div className="grid gap-2.5 sm:grid-cols-2">
                        {ACCESSES.map((value) => (
                            <label
                                key={value}
                                className={cn(
                                    'flex cursor-pointer items-start gap-3 rounded-xl border-[1.5px] bg-cc-panel p-3.5 transition-colors',
                                    value === access
                                        ? 'border-cc-ink'
                                        : 'border-cc-border-strong hover:border-cc-subtle',
                                )}
                            >
                                <input
                                    type="radio"
                                    name="domain_access"
                                    value={value}
                                    checked={value === access}
                                    onChange={() => choose(value)}
                                    className="mt-0.5 size-[18px] shrink-0 accent-cc-ink"
                                />
                                <span className="flex min-w-0 flex-col gap-1.5">
                                    <span className="flex flex-wrap items-center gap-1.5">
                                        <span
                                            className={cn(
                                                'cc-tag',
                                                looks[value].tag,
                                            )}
                                        >
                                            {looks[value].label}
                                        </span>
                                        {value === 'learn_answer' && (
                                            <span className="text-[12px] font-semibold text-cc-subtle">
                                                {t('Default')}
                                            </span>
                                        )}
                                    </span>
                                    <span className="text-[13px] leading-[1.5] text-cc-muted">
                                        <WithHowy
                                            text={looks[value].description}
                                        />
                                    </span>
                                </span>
                            </label>
                        ))}
                    </div>
                </fieldset>
            )}

            <div className="flex flex-wrap items-center gap-3 rounded-xl bg-cc-bg px-4 py-3.5">
                <span className="text-[14px] font-semibold">
                    {t('Everyone else')}
                </span>
                <span className={cn('cc-tag', looks.ignore.tag)}>
                    {looks.ignore.label}
                </span>
                <span className="min-w-[220px] flex-1 text-[13px] leading-[1.5] text-cc-muted">
                    <WithHowy
                        text={t(
                            'Their mail is stored, Howy does nothing with it. Add exceptions from the mailbox in Settings.',
                        )}
                    />
                </span>
            </div>

            <div className="flex flex-col gap-4 rounded-xl border-[1.5px] border-dashed border-cc-border-strong p-[18px]">
                <div className="flex flex-wrap items-center gap-4">
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-[10px] bg-cc-raised">
                        <Download className="size-5" />
                    </span>
                    <div className="flex min-w-[220px] flex-1 flex-col gap-0.5">
                        <p className="flex flex-wrap items-baseline gap-2 text-[15px] font-semibold">
                            {t('Import older emails')}
                            <span className="text-[12px] text-cc-subtle">
                                {t('Optional')}
                            </span>
                        </p>
                        <p className="text-[13px] leading-[1.5] text-cc-muted">
                            <WithHowy
                                text={t(
                                    'Howy reads every new email from now on. Add older mail to Howy as well? It is kept, not processed.',
                                )}
                            />
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        className="h-11 px-[18px]"
                        onClick={() => setImporting(true)}
                    >
                        {t('Choose emails…')}
                    </Button>
                </div>
                {mailbox.import && <ImportProgress progress={mailbox.import} />}
            </div>

            <ImportMailboxDialog
                mailboxes={[mailbox]}
                mailboxId={importing ? mailbox.id : null}
                onOpenChange={(open) => !open && setImporting(false)}
            />
        </SetupStep>
    );
}
