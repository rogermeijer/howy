import { type FormEvent, type ReactNode } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { CircleHelp, Link2, Mail, Power } from 'lucide-react';
import { HowyAvatar } from '@/components/brand/howy-avatar';
import { HowyLogo } from '@/components/brand/howy-logo';
import { HowyName, WithHowy } from '@/components/brand/howy-name';
import { type InterpretationKind, Tag } from '@/components/cc/tag';
import { LanguageSwitcher } from '@/components/language-switcher';
import { useFormatDate } from '@/hooks/use-format-date';
import { useScrolled } from '@/hooks/use-scrolled';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { dashboard, login, register } from '@/routes';
import type { Auth } from '@/types';

// Copy lives here as English source strings, translated at render time. Every
// "Howy" in them is set in the logo face by <WithHowy>.
const steps: { title: string; desc: string }[] = [
    {
        title: 'Give Howy an address',
        desc: 'Create a separate mailbox, such as howy@yourcompany.com, and connect it. Your own inbox stays out of it.',
    },
    {
        title: 'Put Howy in CC',
        desc: 'In every thread where knowledge passes by. Anyone who does not add Howy will not notice a thing.',
    },
    {
        title: 'Ask Howy',
        desc: 'Email a question, or put Howy in CC on a question to the team. You get an answer with what the team already knows.',
    },
];

const interpretations: {
    kind: InterpretationKind;
    label: string;
    desc: string;
}[] = [
    {
        kind: 'question',
        label: 'Assists',
        desc: 'Howy knows your organization and helps everyone on the thread when you put Howy in CC.',
    },
    {
        kind: 'decision',
        label: 'Answers',
        desc: 'When the question is addressed to Howy directly, Howy replies in the thread.',
    },
    {
        kind: 'knowledge',
        label: 'Knowledge',
        desc: 'Howy keeps an eye out for new knowledge, confirms it, and stores it for future use.',
    },
    {
        kind: 'action',
        label: 'Warning',
        desc: 'Howy warns when something contradicts what the team already knows.',
    },
    {
        kind: 'noise',
        label: 'Noise',
        desc: 'Howy stays quiet when it is none of its business, or when it is not sure enough to help.',
    },
];

const discretion: { icon: typeof Mail; title: string; desc: string }[] = [
    {
        icon: Mail,
        title: 'Only what is in CC',
        desc: 'Howy sees the emails Howy is on. Not the rest of your mailbox.',
    },
    {
        icon: Link2,
        title: 'Always with a source',
        desc: 'Everything in the knowledge base links back to the thread it came from.',
    },
    {
        icon: CircleHelp,
        title: 'Checks with you first',
        desc: 'Is Howy unsure? Then Howy asks you first.',
    },
    {
        icon: Power,
        title: 'Switch it off any time',
        desc: 'Disconnect the mailbox and Howy stops reading along.',
    },
];

/** Render a translated sentence with `:placeholder` swapped for a node. */
function withNode(text: string, placeholder: string, node: ReactNode) {
    const [before, ...rest] = text.split(`:${placeholder}`);

    return (
        <>
            {before}
            {node}
            {rest.join(`:${placeholder}`)}
        </>
    );
}

/**
 * A work-email field and a button. There is no sign-up endpoint behind it:
 * submitting opens the registration page, where the account is created.
 */
function SignupForm({
    buttonLabel,
    variant,
}: {
    buttonLabel: ReactNode;
    variant: 'hero' | 'card';
}) {
    const t = useTranslations();

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.visit(register());
    };

    const hero = variant === 'hero';

    return (
        <form
            onSubmit={handleSubmit}
            className={
                hero
                    ? 'flex max-w-[560px] flex-col gap-2.5 lg:flex-row lg:items-end'
                    : 'flex flex-col gap-3.5'
            }
        >
            <label className="flex grow flex-col gap-1.5 text-[13px] font-semibold text-cc-ink">
                {t('Your work email')}
                <input
                    type="email"
                    name="email"
                    required
                    autoComplete="email"
                    placeholder={t('you@yourcompany.com')}
                    className="h-[52px] w-full rounded-xl border-[1.5px] border-cc-border-strong bg-cc-panel px-4 text-base font-normal text-cc-ink transition-colors outline-none placeholder:text-cc-faint focus:border-cc-ink"
                />
            </label>
            <button
                type="submit"
                className={
                    hero
                        ? 'h-[52px] cursor-pointer rounded-xl bg-cc-accent px-[22px] text-base font-semibold whitespace-nowrap text-cc-ink transition-opacity hover:opacity-88'
                        : 'h-[52px] cursor-pointer rounded-xl bg-cc-ink px-[22px] text-base font-semibold text-cc-bg transition-opacity hover:opacity-85'
                }
            >
                {buttonLabel}
            </button>
        </form>
    );
}

function SectionLabel({ children }: { children: ReactNode }) {
    return (
        <div className="text-xs font-semibold tracking-[0.08em] text-cc-subtle uppercase">
            {children}
        </div>
    );
}

const h2Class =
    'm-0 text-[32px] leading-[1.1] font-semibold tracking-[-0.025em] lg:text-[44px]';

export default function Welcome() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const t = useTranslations();
    const formatDate = useFormatDate();
    const scrolled = useScrolled();

    const sourceDate = formatDate('2026-03-14T12:00:00Z', {
        dateStyle: undefined,
        day: 'numeric',
        month: 'short',
    });

    return (
        <>
            <Head title={t('Your new colleague in CC')} />

            <div className="min-h-screen bg-cc-bg text-base leading-[1.55] text-cc-ink antialiased">
                {/* Nav */}
                {/* Sticky: once the page scrolls it tightens, frosts and lifts. */}
                <header
                    className={cn(
                        'sticky top-0 z-40 border-b px-4 transition-[background-color,border-color,box-shadow,padding] duration-300 ease-out motion-reduce:transition-none lg:px-12',
                        scrolled
                            ? 'border-cc-border bg-cc-bg/85 py-2.5 shadow-[0_10px_30px_-22px_rgba(23,20,15,0.45)] backdrop-blur-md'
                            : 'border-transparent bg-cc-bg py-[18px]',
                    )}
                >
                    <div className="mx-auto flex max-w-[1200px] items-center justify-between gap-6">
                        <a
                            href="#top"
                            aria-label={t('Howy, back to top')}
                            className={cn(
                                'block origin-left no-underline transition-transform duration-300 ease-out motion-reduce:transition-none',
                                scrolled && 'scale-90',
                            )}
                        >
                            <HowyLogo size={25} />
                        </a>
                        <nav className="flex items-center gap-5 text-sm font-medium lg:gap-7">
                            <a
                                href="#how"
                                className="hidden text-cc-muted no-underline transition-colors hover:text-cc-ink lg:inline"
                            >
                                {t('How it works')}
                            </a>
                            <a
                                href="#discreet"
                                className="hidden text-cc-muted no-underline transition-colors hover:text-cc-ink lg:inline"
                            >
                                {t('Privacy')}
                            </a>
                            <LanguageSwitcher />
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="inline-flex min-h-10 items-center rounded-full bg-cc-ink px-[18px] font-semibold text-cc-bg no-underline transition-opacity hover:opacity-85"
                                >
                                    {t('Dashboard')}
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={login()}
                                        className="text-cc-ink no-underline transition-colors hover:text-cc-accent-deep"
                                    >
                                        {t('Log in')}
                                    </Link>
                                    <Link
                                        href={register()}
                                        className="hidden min-h-10 items-center rounded-full bg-cc-ink px-[18px] font-semibold whitespace-nowrap text-cc-bg no-underline transition-opacity hover:opacity-85 sm:inline-flex"
                                    >
                                        {t('Create account')}
                                    </Link>
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                {/* Hero */}
                <section
                    id="top"
                    className="scroll-mt-20 px-4 pt-14 pb-20 lg:px-12 lg:pt-[88px] lg:pb-24"
                >
                    <div className="mx-auto grid max-w-[1200px] grid-cols-1 items-center gap-14 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)] lg:gap-[72px]">
                        <div className="flex flex-col gap-7">
                            <div className="inline-flex items-center gap-2.5 self-start rounded-full border border-cc-border bg-cc-panel py-1.5 pr-3.5 pl-1.5 text-[13px] font-medium text-cc-muted">
                                <HowyAvatar size={22} />
                                {t(
                                    'Your new colleague that remembers everything',
                                )}
                            </div>
                            <h1 className="m-0 text-[46px] leading-[1.02] font-semibold tracking-[-0.035em] lg:text-[72px]">
                                <WithHowy text={t('Put Howy in CC.')} accent />
                                <br />
                                {t('The team forgets nothing.')}
                            </h1>
                            <p className="m-0 max-w-[540px] text-[19px] leading-[1.55] text-cc-muted">
                                <WithHowy
                                    text={t(
                                        'Howy reads along in the threads you add Howy to. Does someone ask a question that was answered before? Then Howy replies, with a link to the email it came from. New knowledge goes neatly into the knowledge base.',
                                    )}
                                />
                            </p>
                            <div className="mt-2">
                                <SignupForm
                                    variant="hero"
                                    buttonLabel={
                                        <WithHowy
                                            text={t(
                                                'Introduce Howy to your team',
                                            )}
                                        />
                                    }
                                />
                            </div>
                            <div className="text-[13px] text-cc-subtle">
                                {t(
                                    'Try it free · ready in 2 minutes · no access to the rest of your mailbox',
                                )}
                            </div>
                        </div>

                        {/* Mail thread */}
                        <div className="relative">
                            <div className="pointer-events-none absolute right-3 -bottom-[74px] hidden -rotate-3 items-end gap-1.5 text-cc-accent-deep lg:flex">
                                <svg
                                    aria-hidden="true"
                                    width="56"
                                    height="52"
                                    viewBox="0 0 56 52"
                                    fill="none"
                                    className="text-cc-accent"
                                >
                                    <path
                                        d="M50 48 C 26 46, 12 32, 10 6"
                                        stroke="currentColor"
                                        strokeWidth="2.5"
                                        strokeLinecap="round"
                                    />
                                    <path
                                        d="M3 14 L 10 4 L 18 12"
                                        stroke="currentColor"
                                        strokeWidth="2.5"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    />
                                </svg>
                                <div className="font-logo text-lg leading-none">
                                    {t('replies to all, with the source')}
                                </div>
                            </div>

                            <div className="overflow-hidden rounded-[20px] border border-cc-border bg-cc-panel shadow-[0_1px_0_var(--color-cc-border),0_24px_48px_-28px_rgb(23_20_15/0.25)]">
                                <div className="flex items-center justify-between gap-3 border-b border-cc-border px-[22px] py-4">
                                    <div className="text-[15px] font-semibold">
                                        {t(
                                            'Discount on the Verhoeven annual delivery?',
                                        )}
                                    </div>
                                    <div className="shrink-0 text-xs text-cc-subtle">
                                        {t(':count messages', { count: 2 })}
                                    </div>
                                </div>

                                <div className="flex flex-col gap-3 border-b border-cc-border px-[22px] py-5">
                                    <div className="flex items-center gap-3">
                                        <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-cc-raised text-[13px] font-semibold text-cc-muted">
                                            PV
                                        </div>
                                        <div className="flex min-w-0 flex-col gap-1 text-[13px]">
                                            <div>
                                                <strong className="font-semibold">
                                                    Pieter de Vries
                                                </strong>{' '}
                                                <span className="text-cc-subtle">
                                                    {t(
                                                        'to sales@yourcompany.com',
                                                    )}
                                                </span>
                                            </div>
                                            <div className="flex flex-wrap items-center gap-1.5 text-cc-subtle">
                                                {t('cc')}
                                                <span className="inline-flex items-center gap-1.5 rounded-full bg-cc-accent-tint py-0.5 pr-2.5 pl-[3px] font-semibold text-cc-accent-deep">
                                                    <HowyAvatar size={18} />
                                                    <HowyName className="text-xs leading-none" />
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <p className="m-0 text-sm leading-[1.6]">
                                        {t(
                                            'Does anyone remember what discount we gave Verhoeven on the annual delivery last year? They are asking for a new quote.',
                                        )}
                                    </p>
                                </div>

                                <div className="flex flex-col gap-3 bg-cc-accent-tint/25 px-[22px] pt-5 pb-6">
                                    <div className="flex items-center gap-3">
                                        <HowyAvatar size={36} />
                                        <div className="text-[13px]">
                                            <HowyName className="text-sm leading-none" />{' '}
                                            <span className="text-cc-subtle">
                                                {t(
                                                    'to Pieter, sales@ · reply all',
                                                )}
                                            </span>
                                        </div>
                                    </div>
                                    <p className="m-0 text-sm leading-[1.6]">
                                        {withNode(
                                            t(
                                                'Hi Pieter, I remember that. Verhoeven got :discount on the annual delivery, approved by Marieke on 14 March.',
                                            ),
                                            'discount',
                                            <strong className="font-semibold">
                                                {t('an 8% discount')}
                                            </strong>,
                                        )}
                                    </p>
                                    <div className="flex items-center gap-2.5 rounded-[10px] border border-cc-border bg-cc-panel px-3 py-2.5 text-[13px]">
                                        <Mail
                                            aria-hidden="true"
                                            className="size-4 shrink-0 text-cc-muted"
                                            strokeWidth={1.8}
                                        />
                                        <span className="grow">
                                            {withNode(
                                                t('Source: :subject'),
                                                'subject',
                                                <strong className="font-semibold">
                                                    {t(
                                                        'Re: Quote Verhoeven 2026',
                                                    )}
                                                </strong>,
                                            )}
                                        </span>
                                        <span className="shrink-0 text-cc-subtle">
                                            {sourceDate}
                                        </span>
                                    </div>
                                    <div className="text-sm leading-none text-cc-muted">
                                        — <HowyName />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* How it works */}
                <section
                    id="how"
                    className="scroll-mt-20 border-y border-cc-border bg-cc-panel px-4 py-20 lg:px-12 lg:py-24"
                >
                    <div className="mx-auto flex max-w-[1200px] flex-col gap-12">
                        <div className="flex max-w-[680px] flex-col gap-3">
                            <SectionLabel>{t('How it works')}</SectionLabel>
                            <h2 className={h2Class}>
                                <WithHowy
                                    text={t('This is how Howy joins your team')}
                                    accent
                                />
                            </h2>
                            <p className="m-0 text-[17px] text-cc-muted">
                                {t(
                                    'Nobody has to log in, learn anything or keep a tab open. One address in cc is the whole action.',
                                )}
                            </p>
                        </div>
                        <ol className="m-0 grid list-none grid-cols-1 gap-5 p-0 lg:grid-cols-3">
                            {steps.map(({ title, desc }, index) => (
                                <li
                                    key={title}
                                    className="flex flex-col gap-3 rounded-[20px] border border-cc-border bg-cc-bg p-7"
                                >
                                    <div className="font-logo text-[42px] leading-[0.8] text-cc-accent">
                                        {index + 1}
                                    </div>
                                    <h3 className="m-0 text-xl font-semibold tracking-[-0.015em]">
                                        <WithHowy text={t(title)} />
                                    </h3>
                                    <p className="m-0 text-[15px] text-cc-muted">
                                        <WithHowy text={t(desc)} />
                                    </p>
                                </li>
                            ))}
                        </ol>
                    </div>
                </section>

                {/* What happens to an email */}
                <section className="px-4 py-20 lg:px-12 lg:py-[104px]">
                    <div className="mx-auto grid max-w-[1200px] grid-cols-1 items-start gap-12 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:gap-[72px]">
                        <div className="flex flex-col gap-4">
                            <SectionLabel>
                                {t('What happens to an email')}
                            </SectionLabel>
                            <h2 className={h2Class}>
                                <WithHowy
                                    text={t(
                                        'Howy reads along and knows what matters',
                                    )}
                                    accent
                                />
                            </h2>
                            <p className="m-0 text-[17px] text-cc-muted">
                                <WithHowy
                                    text={t(
                                        'Not every email is knowledge. Howy assists, answers, captures knowledge, warns — and leaves the rest alone.',
                                    )}
                                />
                            </p>
                        </div>
                        <ul className="m-0 flex list-none flex-col overflow-hidden rounded-[20px] border border-cc-border bg-cc-panel p-0">
                            {interpretations.map(({ kind, label, desc }) => (
                                <li
                                    key={kind}
                                    className="grid grid-cols-1 items-center gap-2 border-b border-cc-border px-6 py-5 last:border-b-0 sm:grid-cols-[120px_minmax(0,1fr)] sm:gap-5"
                                >
                                    <Tag
                                        kind={kind}
                                        label={t(label)}
                                        className="justify-self-start"
                                    />
                                    <div
                                        className={
                                            kind === 'noise'
                                                ? 'text-[15px] text-cc-muted'
                                                : 'text-[15px]'
                                        }
                                    >
                                        <WithHowy text={t(desc)} />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>
                </section>

                {/* Discretion */}
                <section
                    id="discreet"
                    className="scroll-mt-20 px-4 pb-20 lg:px-12 lg:pb-[104px]"
                >
                    <div className="mx-auto flex max-w-[1200px] flex-col gap-10">
                        <h2 className={`${h2Class} max-w-[720px]`}>
                            <WithHowy text={t('Howy is discreet')} accent />
                        </h2>
                        <div className="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4 lg:gap-5">
                            {discretion.map(({ icon: Icon, title, desc }) => (
                                <div
                                    key={title}
                                    className="flex flex-col gap-2.5 border-t-2 border-cc-ink pt-5"
                                >
                                    <Icon
                                        aria-hidden="true"
                                        className="size-6 text-cc-ink"
                                        strokeWidth={1.8}
                                    />
                                    <h3 className="m-0 text-[17px] font-semibold">
                                        {t(title)}
                                    </h3>
                                    <p className="m-0 text-[15px] text-cc-muted">
                                        <WithHowy text={t(desc)} />
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Sign up */}
                <section id="start" className="px-4 pb-20 lg:px-12 lg:pb-24">
                    <div className="mx-auto grid max-w-[1200px] grid-cols-1 items-center gap-10 rounded-[28px] bg-cc-dark p-7 text-cc-bg lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)] lg:gap-14 lg:p-16">
                        <div className="flex flex-col gap-4">
                            <h2 className="m-0 font-logo text-[40px] leading-[0.95] font-normal lg:text-[50px]">
                                <WithHowy text={t("Hi, I'm Howy.")} accent />
                            </h2>
                            <p className="m-0 max-w-[460px] text-[19px] leading-[1.5] text-cc-dark-text">
                                {t(
                                    'Put me in CC on your next thread. Then I will remember it for you, and still know it when someone asks.',
                                )}
                            </p>
                        </div>
                        <div className="flex flex-col gap-3.5 rounded-[20px] bg-cc-bg p-7 text-cc-ink">
                            <SignupForm
                                variant="card"
                                buttonLabel={t('Create account')}
                            />
                            <div className="text-center text-[13px] text-cc-muted">
                                {t('Already have an account?')}{' '}
                                <Link
                                    href={login()}
                                    className="font-medium text-cc-accent-deep underline-offset-[3px] hover:text-cc-ink hover:underline"
                                >
                                    {t('Log in')}
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Footer */}
                <footer className="border-t border-cc-border px-4 py-7 lg:px-12">
                    <div className="mx-auto flex max-w-[1200px] flex-wrap items-center justify-between gap-4 text-[13px] text-cc-subtle">
                        <HowyLogo size={18} />
                        <div className="flex gap-6">
                            <a
                                href="#discreet"
                                className="text-cc-subtle hover:text-cc-ink"
                            >
                                {t('Privacy')}
                            </a>
                            <span>
                                {t('© :year Howy', {
                                    year: new Date().getFullYear(),
                                })}
                            </span>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
