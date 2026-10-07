import { type ReactNode, useEffect } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    EyeOff,
    FileDown,
    History,
    Home,
    Lock,
    type LucideIcon,
    Search,
    ShieldCheck,
    TrendingUp,
    UserPlus,
    Zap,
    Check,
} from 'lucide-react';
import {
    Burst,
    Circled,
    CurvedArrow,
    HandNote,
    Marker,
    Sparkle,
    Squiggle,
} from '@/components/brand/doodles';
import { HowyLogo } from '@/components/brand/howy-logo';
import { LanguageSwitcher } from '@/components/language-switcher';
import { CheckItem } from '@/components/marketing/check-item';
import { Disclosure } from '@/components/marketing/disclosure';
import { PillLink } from '@/components/marketing/pill-link';
import { useActiveSection } from '@/hooks/use-active-section';
import { useScrolled } from '@/hooks/use-scrolled';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { dashboard, login, register } from '@/routes';
import type { Auth } from '@/types';
import heroTeam from '../../images/welcome/hero-team.jpg';
import kitchenTable from '../../images/welcome/kitchen-table.jpg';
import laptopsTable from '../../images/welcome/laptops-table.jpg';
import loftTeam from '../../images/welcome/loft-team.jpg';
import openOffice from '../../images/welcome/open-office.jpg';

// Copy lives here as English source strings, translated at render time.

const questions = [
    'Which discount did we give that customer?',
    "Where's the signed contract?",
    'Why do we do it this way?',
    "Who knows the supplier's contact person?",
];

const blueprintTopics = [
    'Customers',
    'Decisions',
    'Projects',
    'Processes',
    'Who does what',
];

const blueprintCards: { tag: string; title: string; meta: string }[] = [
    {
        tag: 'Agreement',
        title: 'Annual price agreement and delivery terms',
        meta: 'Verified · updated today',
    },
    {
        tag: 'Decision',
        title: 'Why we switched to monthly invoicing',
        meta: 'Verified · last week',
    },
    {
        tag: 'Contact',
        title: 'Supplier contact person and escalation route',
        meta: 'Shared in one click',
    },
    {
        tag: 'Process',
        title: 'How we onboard a new client',
        meta: 'Verified · 3 days ago',
    },
];

const steps: { label: string; title: string; desc: string }[] = [
    {
        label: 'Step 1 · 10 minutes',
        title: 'Install in 10 minutes.',
        desc: 'Connect Howy to your company tools and invite your team. Done.',
    },
    {
        label: 'Step 2 · one click',
        title: 'Your team keeps working as always.',
        desc: 'When something is worth keeping (an agreement, a decision, a customer detail), sharing it with Howy takes one click.',
    },
    {
        label: 'Step 3 · every day',
        title: 'Your blueprint grows by itself.',
        desc: 'Howy organizes and checks every piece of knowledge, and puts it where the right people can find it.',
    },
];

const photos: { src: string; alt: string; position: string }[] = [
    {
        src: kitchenTable,
        alt: 'Team chatting around the kitchen table',
        position: 'object-[55%_55%]',
    },
    {
        src: laptopsTable,
        alt: 'Team working on laptops around a wooden table',
        position: 'object-[50%_45%]',
    },
    {
        src: openOffice,
        alt: 'Open-plan office with colleagues at their desks',
        position: 'object-[40%_70%]',
    },
];

const securityPoints: { icon: LucideIcon; title: string; desc: string }[] = [
    {
        icon: ShieldCheck,
        title: '100% GDPR-compliant.',
        desc: 'Built from day one to meet European privacy law.',
    },
    {
        icon: EyeOff,
        title: 'No monitoring, ever.',
        desc: "Howy doesn't read along in the background or track what people do all day. Your people decide what they share. Nothing else.",
    },
    {
        icon: Lock,
        title: 'The right eyes only.',
        desc: "Knowledge is shared by role, so every colleague sees what they need, and nothing they shouldn't.",
    },
    {
        icon: History,
        title: 'Full history.',
        desc: 'Every change is tracked: what was added or updated, when, and by whom.',
    },
    {
        icon: Home,
        title: 'Yours, always.',
        desc: 'The knowledge belongs to your company. Not to us, and not to any one employee.',
    },
];

const fitList = [
    'are growing fast and hiring new people every month;',
    'run projects and client work, where agreements change all the time;',
    'depend on a few key people who know everything;',
    'are preparing for investors, an audit, a sale or a successor.',
];

const plans: {
    name: string;
    price: string;
    desc: string;
    features: string[];
    featured?: boolean;
}[] = [
    {
        name: 'Basic',
        price: '€4.99',
        desc: 'For small companies starting to safeguard their knowledge.',
        features: ['Email capture', 'Company blueprint', 'Onboarding'],
    },
    {
        name: 'Business',
        price: '€7.99',
        desc: 'For growing companies.',
        features: [
            'Everything in Basic',
            'Org chart with history',
            'Role-based sharing',
            'Teams and Slack integration',
        ],
        featured: true,
    },
    {
        name: 'Pro',
        price: '€10.99',
        desc: 'For companies facing investors, auditors, a takeover or succession.',
        features: [
            'Everything in Business',
            'Investor and auditor PDF exports',
            'All integrations (HR, CRM, project tools)',
            'Succession package',
        ],
    },
];

const faqs: { q: string; a: string }[] = [
    {
        q: 'Does Howy watch what my employees do?',
        a: 'No. Howy is not a monitoring tool. It never reads along in the background. Your people decide what they share with Howy, and only that becomes company knowledge.',
    },
    {
        q: 'Do my employees need to learn a new system?',
        a: 'No. Howy works with the tools your team already uses. Sharing knowledge takes one click, not a training course.',
    },
    {
        q: 'How long does it take to get started?',
        a: 'About 10 minutes. Install, invite your team, and your blueprint starts growing the same day.',
    },
    {
        q: 'What happens when someone leaves?',
        a: 'Their knowledge stays where it belongs: in your company. Their successor can pick up where they left off.',
    },
    {
        q: 'We already have SharePoint, a wiki or Copilot. Why Howy?',
        a: 'Those tools store or search documents, and only work if someone keeps them up to date. Howy builds and maintains your knowledge as your team works, so it is always current, verified and organized.',
    },
    {
        q: 'Who owns the data?',
        a: 'You do. All knowledge in Howy belongs to your company, is stored securely and handled 100% in line with GDPR.',
    },
];

const navLinks: { href: string; label: string }[] = [
    { href: '#how', label: 'How it works' },
    { href: '#benefits', label: 'Benefits' },
    { href: '#security', label: 'Security' },
    { href: '#pricing', label: 'Pricing' },
    { href: '#faq', label: 'FAQ' },
];

/** The sections the nav follows, for the active-link marker. */
const navSections = navLinks.map(({ href }) => href.slice(1));

const footerLinks: { href: string; label: string }[] = [
    { href: '#security', label: 'Privacy & GDPR' },
    { href: '#pricing', label: 'Pricing' },
    { href: '#faq', label: 'FAQ' },
    { href: '#demo', label: 'Contact' },
];

/** Page width and side gutter, shared by every band: 1240px of content. */
const container = 'mx-auto w-full max-w-[1320px] px-[clamp(16px,4vw,40px)]';

/** The vertical rhythm of a full section. */
const sectionY = 'py-[clamp(72px,9vw,128px)]';

const inlineLink =
    'font-semibold text-cc-ink underline underline-offset-[3px] transition-colors hover:text-cc-accent-deep';

/**
 * Render a translated sentence with `:mark` swapped for a node — the word
 * that carries a hand-drawn mark. Translators keep the placeholder where
 * the marked words fall in their language.
 */
function withMark(text: string, mark: ReactNode) {
    const [before, ...rest] = text.split(':mark');

    return (
        <>
            {before}
            {mark}
            {rest.join(':mark')}
        </>
    );
}

function SectionHeading({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <h2
            className={cn(
                'm-0 text-[clamp(36px,4.4vw,56px)] leading-[1.05] font-extrabold tracking-[-0.04em]',
                className,
            )}
        >
            {children}
        </h2>
    );
}

function Stat({
    value,
    label,
    className,
}: {
    value: ReactNode;
    label: string;
    className?: string;
}) {
    return (
        <div
            className={cn('flex flex-col gap-2.5 px-6 py-8 sm:px-9', className)}
        >
            <div className="self-start text-[40px] leading-none font-extrabold tracking-[-0.04em] sm:text-5xl">
                {value}
            </div>
            <div className="text-cc-muted">{label}</div>
        </div>
    );
}

function Step({
    label,
    title,
    desc,
}: {
    label: string;
    title: string;
    desc: string;
}) {
    return (
        <li className="flex flex-col gap-3.5 border-t-2 border-cc-ink pt-6">
            <span className="text-sm font-bold text-cc-accent-deep">
                {label}
            </span>
            <h3 className="m-0 text-2xl leading-[1.25] font-bold tracking-[-0.025em]">
                {title}
            </h3>
            <p className="m-0 text-cc-muted">{desc}</p>
        </li>
    );
}

function Benefit({
    icon: Icon,
    title,
    desc,
}: {
    icon: LucideIcon;
    title: ReactNode;
    desc: string;
}) {
    return (
        <div className="flex items-start gap-[18px]">
            <span className="flex size-15 shrink-0 items-center justify-center rounded-2xl bg-cc-accent-tint">
                <Icon
                    aria-hidden="true"
                    className="size-7 text-cc-ink"
                    strokeWidth={1.8}
                />
            </span>
            <div className="flex min-w-0 flex-col gap-2 pt-1.5">
                <h3 className="m-0 text-xl leading-[1.3] font-bold tracking-[-0.02em]">
                    {title}
                </h3>
                <p className="m-0 text-cc-muted">{desc}</p>
            </div>
        </div>
    );
}

function SecurityPoint({
    icon: Icon,
    title,
    desc,
}: {
    icon: LucideIcon;
    title: string;
    desc: string;
}) {
    return (
        <div className="flex flex-col gap-2.5">
            <Icon
                aria-hidden="true"
                className="size-[26px] text-cc-accent"
                strokeWidth={1.8}
            />
            <h3 className="m-0 text-lg font-bold">{title}</h3>
            <p className="m-0 text-[15px] text-cc-dark-text">{desc}</p>
        </div>
    );
}

function PlanCard({
    name,
    price,
    desc,
    features,
    featured = false,
    badge,
    priceSuffix,
    cta,
}: {
    name: string;
    price: string;
    desc: string;
    features: string[];
    featured?: boolean;
    badge?: string;
    priceSuffix: string;
    cta: string;
}) {
    return (
        <div
            className={cn(
                'flex flex-col gap-3.5 p-9',
                featured && 'bg-cc-accent-tint/25',
            )}
        >
            <div className="flex items-center gap-2.5">
                <h3 className="m-0 text-xl font-bold">{name}</h3>
                {badge && (
                    <span className="rounded-full bg-cc-accent px-2.5 py-0.5 text-xs font-bold">
                        {badge}
                    </span>
                )}
            </div>
            <div className="flex flex-wrap items-baseline gap-x-1.5">
                <span className="text-[44px] font-extrabold tracking-[-0.04em]">
                    {price}
                </span>
                <span className="text-sm text-cc-muted">{priceSuffix}</span>
            </div>
            <p className="m-0 text-cc-muted">{desc}</p>
            <ul className="m-0 mt-1 mb-4 flex list-none flex-col gap-2 border-t border-cc-border p-0 pt-4 text-[15px]">
                {features.map((feature) => (
                    <CheckItem key={feature}>{feature}</CheckItem>
                ))}
            </ul>
            <PillLink
                href={register()}
                variant={featured ? 'lime' : 'outline'}
                block
                className="mt-auto"
            >
                {cta}
            </PillLink>
        </div>
    );
}

export default function Welcome() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const t = useTranslations();
    const scrolled = useScrolled();
    const activeSection = useActiveSection(navSections);

    // In-page links glide to their section while this page is open. Only
    // here: Inertia's own scroll resets elsewhere should stay instant.
    useEffect(() => {
        const root = document.documentElement;
        root.classList.add('motion-safe:scroll-smooth');

        return () => root.classList.remove('motion-safe:scroll-smooth');
    }, []);

    return (
        <>
            <Head title={t('Your company blueprint')} />

            <div className="min-h-screen overflow-x-clip bg-white text-base leading-[1.65] text-cc-ink antialiased">
                {/* Nav — sticky: once the page scrolls it tightens and lifts. */}
                <header
                    className={cn(
                        'sticky top-0 z-40 border-b bg-white/90 backdrop-blur-md transition-[border-color,box-shadow] duration-300 ease-out motion-reduce:transition-none',
                        scrolled
                            ? 'border-cc-border shadow-[0_10px_30px_-22px_color-mix(in_oklab,var(--color-cc-ink)_45%,transparent)]'
                            : 'border-transparent',
                    )}
                >
                    <nav
                        aria-label={t('Main')}
                        className={cn(
                            container,
                            'flex items-center gap-x-10 gap-y-3 transition-[padding] duration-300 ease-out motion-reduce:transition-none',
                            scrolled ? 'py-2.5' : 'py-4',
                        )}
                    >
                        <a
                            href="#top"
                            aria-label={t('Howy, back to top')}
                            className={cn(
                                'block origin-left no-underline transition-transform duration-300 ease-out motion-reduce:transition-none',
                                scrolled && 'scale-90',
                            )}
                        >
                            <HowyLogo size={26} />
                        </a>
                        <div className="hidden flex-1 flex-wrap gap-x-7 gap-y-1 text-[15px] font-medium lg:flex">
                            {navLinks.map(({ href, label }) => {
                                const active = activeSection === href.slice(1);

                                return (
                                    <a
                                        key={href}
                                        href={href}
                                        aria-current={
                                            active ? 'location' : undefined
                                        }
                                        className={cn(
                                            'relative no-underline transition-[color,scale] duration-300 ease-out active:scale-95 motion-reduce:transition-none',
                                            active
                                                ? 'text-cc-ink'
                                                : 'text-cc-muted hover:text-cc-ink',
                                        )}
                                    >
                                        {t(label)}
                                        <span
                                            aria-hidden="true"
                                            className={cn(
                                                'absolute inset-x-0 -bottom-1 h-[3px] origin-left rounded-full bg-cc-accent transition-transform duration-300 ease-out motion-reduce:transition-none',
                                                active
                                                    ? 'scale-x-100'
                                                    : 'scale-x-0',
                                            )}
                                        />
                                    </a>
                                );
                            })}
                        </div>
                        <div className="ml-auto flex items-center gap-2 lg:ml-0">
                            {auth.user ? (
                                <PillLink href={dashboard()} size="md">
                                    {t('Dashboard')}
                                </PillLink>
                            ) : (
                                <>
                                    <Link
                                        href={login()}
                                        className="px-3 py-2.5 text-[15px] font-semibold text-cc-ink no-underline transition-colors hover:text-cc-accent-deep"
                                    >
                                        {t('Log in')}
                                    </Link>
                                    <PillLink href="#demo" size="md">
                                        {t('Book a demo')}
                                    </PillLink>
                                </>
                            )}
                        </div>
                    </nav>
                </header>

                {/* 1. Hero */}
                <section
                    id="top"
                    className={cn(
                        container,
                        'flex scroll-mt-20 flex-wrap items-center gap-[clamp(40px,6vw,80px)] pt-[clamp(40px,6vw,88px)] pb-[clamp(72px,9vw,128px)] lg:flex-nowrap lg:items-start',
                    )}
                >
                    {/* The first heading line runs on over the image column,
                        above the photo, which starts lower (lg:mt-28). */}
                    <div className="relative z-10 flex min-w-0 flex-[1_1_500px] flex-col gap-7">
                        <div className="relative">
                            <Burst className="absolute -top-10 -left-2 size-9 sm:-top-[34px] sm:-left-10 sm:size-12" />
                            {/* Only the first line may run on over the photo
                                column (it sits above the photo); the rest wraps
                                inside the text column, so it never covers it. */}
                            <h1 className="m-0 text-[clamp(46px,6.2vw,80px)] leading-none font-extrabold tracking-[-0.045em]">
                                <span className="lg:whitespace-nowrap">
                                    {t('Your people move on.')}
                                </span>
                                <br />
                                {withMark(
                                    t('The knowledge :mark'),
                                    <Marker>{t('stays.')}</Marker>,
                                )}
                            </h1>
                        </div>
                        <p className="m-0 max-w-[540px] text-xl leading-[1.6] text-cc-muted">
                            {t(
                                'Howy turns what your team knows into knowledge your company owns: always up to date, always at hand, never walking out the door.',
                            )}
                        </p>
                        <div className="flex flex-wrap gap-3">
                            <PillLink href={register()} arrow>
                                {t('Start your free build-up phase')}
                            </PillLink>
                            <PillLink href="#demo" variant="outline">
                                {t('Book a 15-minute demo')}
                            </PillLink>
                        </div>
                        <ul className="m-0 mt-9 flex list-none flex-col gap-3 p-0 font-semibold">
                            <CheckItem tone="lime">
                                {t('Up and running in 10 minutes')}
                            </CheckItem>
                            <CheckItem tone="lime">
                                {t('No new tools to learn')}
                            </CheckItem>
                            <CheckItem tone="lime">
                                {t('100% GDPR-compliant')}
                            </CheckItem>
                        </ul>
                    </div>

                    <div className="relative w-full min-w-0 lg:mt-28 lg:-mb-28 lg:w-[44%] lg:max-w-[540px] lg:shrink-0">
                        <img
                            src={heroTeam}
                            alt={t(
                                'Three colleagues working together on laptops in a bright office',
                            )}
                            fetchPriority="high"
                            className="block aspect-square w-full rounded-[32px] object-cover object-[42%_50%]"
                        />
                        <div
                            aria-hidden="true"
                            className="absolute top-[14%] left-[18px] flex max-w-[calc(100%-2rem)] -rotate-5 flex-col items-start"
                        >
                            <HandNote
                                boxed
                                className="text-[22px] sm:text-[26px]"
                            >
                                {t('company knowledge, kept forever')}
                            </HandNote>
                            <CurvedArrow className="ml-[18px]" />
                        </div>
                        <div className="absolute -bottom-8 left-4 flex max-w-[300px] items-center gap-3.5 rounded-[20px] bg-white px-[18px] py-4 shadow-[0_12px_40px_color-mix(in_oklab,var(--color-cc-ink)_12%,transparent)] sm:-left-6 lg:top-[72%] lg:bottom-auto lg:-left-16">
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-cc-accent">
                                <Check
                                    aria-hidden="true"
                                    className="size-5 text-cc-ink"
                                    strokeWidth={2.2}
                                />
                            </span>
                            <span className="flex flex-col leading-[1.35]">
                                <span className="text-[15px] font-bold">
                                    {t('Saved to your blueprint')}
                                </span>
                                <span className="text-[13px] text-cc-muted">
                                    {t('Customer agreement · shared by Sales')}
                                </span>
                            </span>
                        </div>
                    </div>
                </section>

                {/* 2. Recognition */}
                <section className="bg-cc-bg">
                    <div
                        className={cn(
                            container,
                            sectionY,
                            'flex flex-col gap-[clamp(48px,6vw,80px)]',
                        )}
                    >
                        <div className="flex flex-wrap gap-x-20 gap-y-12">
                            <div className="flex min-w-0 flex-[1_1_420px] flex-col gap-5">
                                <SectionHeading>
                                    {withMark(
                                        t('You know :mark'),
                                        <Marker color="yellow" shape="wave">
                                            {t('the moment.')}
                                        </Marker>,
                                    )}
                                </SectionHeading>
                                <p className="m-0 text-xl leading-[1.6]">
                                    {t('A key employee hands in their notice.')}
                                    <br />
                                    {t(
                                        "Four weeks later, they're gone. And then the questions start.",
                                    )}
                                </p>
                                <p className="m-0 max-w-[480px] text-xl leading-[1.6]">
                                    {t(
                                        "Nobody knows. Because the answers were never the company's. They lived in one person's head and one person's inbox, and they left with them.",
                                    )}
                                </p>
                            </div>
                            <ul className="m-0 flex min-w-0 flex-[1_1_420px] list-none flex-col border-t border-cc-border p-0">
                                {questions.map((question) => (
                                    <li
                                        key={question}
                                        className="flex items-center gap-4 border-b border-cc-border py-[18px]"
                                    >
                                        <span
                                            aria-hidden="true"
                                            className="flex size-8 shrink-0 items-center justify-center rounded-full bg-cc-accent-tint text-[15px] font-extrabold"
                                        >
                                            ?
                                        </span>
                                        <span className="text-[19px] font-semibold tracking-[-0.015em]">
                                            {t(question)}
                                        </span>
                                    </li>
                                ))}
                                <li className="pt-5 pl-12 text-cc-muted">
                                    {withMark(
                                        t('The answer :mark'),
                                        <Marker
                                            color="yellow"
                                            shape="wave"
                                            className="font-semibold text-cc-ink"
                                        >
                                            {t('left with them.')}
                                        </Marker>,
                                    )}
                                </li>
                            </ul>
                        </div>

                        <div className="grid grid-cols-1 rounded-[32px] bg-white p-2 lg:grid-cols-3">
                            <Stat
                                value={<Circled>{t('Almost half')}</Circled>}
                                label={t(
                                    'of what a company knows is known by just one person.',
                                )}
                            />
                            <Stat
                                value={t('300,000')}
                                label={t(
                                    'people in the Netherlands change jobs every quarter.',
                                )}
                                className="border-t border-cc-border lg:border-t-0 lg:border-l"
                            />
                            <Stat
                                value={t('Every exit')}
                                label={t(
                                    'hits harder the smaller your company is.',
                                )}
                                className="border-t border-cc-border lg:border-t-0 lg:border-l"
                            />
                        </div>

                        <p className="m-0 text-[clamp(34px,5.2vw,75px)] leading-[1.08] font-extrabold tracking-[-0.045em]">
                            {t('People leaving is normal.')}
                            <br />
                            <span className="text-cc-muted">
                                {withMark(
                                    t('Losing what they know :mark'),
                                    <Squiggle className="text-cc-ink">
                                        {t("shouldn't be.")}
                                    </Squiggle>,
                                )}
                            </span>
                        </p>
                    </div>
                </section>

                {/* 3. What Howy is */}
                <section
                    className={cn(container, sectionY, 'flex flex-col gap-14')}
                >
                    <div className="flex flex-wrap items-end gap-x-20 gap-y-6">
                        <SectionHeading className="min-w-0 flex-[1_1_460px]">
                            {t('Meet Howy: your company blueprint.')}
                        </SectionHeading>
                        <p className="m-0 min-w-0 flex-[1_1_420px] text-lg text-cc-muted">
                            {t(
                                "One central place where your company's knowledge lives: customer agreements, decisions, projects, processes, who does what. Not in people's heads. Not scattered across inboxes and folders. In your company.",
                            )}
                        </p>
                    </div>

                    {/* Product panel */}
                    <div className="rounded-[32px] bg-cc-bg p-[clamp(16px,4vw,56px)]">
                        <div className="grid grid-cols-1 overflow-hidden rounded-[20px] border border-cc-border bg-white md:grid-cols-[200px_minmax(0,1fr)]">
                            <div className="flex flex-wrap gap-0.5 border-b border-cc-border px-[18px] py-6 text-sm font-medium md:flex-col md:flex-nowrap md:border-r md:border-b-0">
                                <div className="mx-2.5 mb-3.5 w-full text-[17px] font-extrabold tracking-[-0.03em] md:w-auto">
                                    {t('Your blueprint')}
                                </div>
                                {blueprintTopics.map((topic, index) => (
                                    <div
                                        key={topic}
                                        className={cn(
                                            'rounded-[10px] px-3 py-[9px]',
                                            index === 0
                                                ? 'bg-cc-accent-tint font-bold'
                                                : 'text-cc-muted',
                                        )}
                                    >
                                        {t(topic)}
                                    </div>
                                ))}
                            </div>
                            <div className="flex min-w-0 flex-col gap-3.5 p-6">
                                <div className="flex items-center gap-2.5 rounded-full bg-cc-bg px-4 py-3 text-sm text-cc-muted">
                                    <Search
                                        aria-hidden="true"
                                        className="size-4 shrink-0"
                                        strokeWidth={2}
                                    />
                                    {t('Ask anything your company knows')}
                                </div>
                                <div className="grid grid-cols-[repeat(auto-fit,minmax(min(220px,100%),1fr))] gap-3">
                                    {blueprintCards.map(
                                        ({ tag, title, meta }, index) => (
                                            <div
                                                key={tag}
                                                className="flex flex-col gap-1.5 rounded-[14px] border border-cc-border p-[18px]"
                                            >
                                                <span
                                                    className={cn(
                                                        'self-start rounded-full px-2.5 py-0.5 text-xs font-bold',
                                                        index === 0
                                                            ? 'bg-cc-accent-tint'
                                                            : 'bg-cc-bg',
                                                    )}
                                                >
                                                    {t(tag)}
                                                </span>
                                                <span className="leading-[1.35] font-bold">
                                                    {t(title)}
                                                </span>
                                                <span className="text-[13px] text-cc-muted">
                                                    {t(meta)}
                                                </span>
                                            </div>
                                        ),
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-x-20 gap-y-6">
                        <p className="m-0 min-w-0 flex-[1_1_460px] text-lg text-cc-muted">
                            {t(
                                "Unlike a wiki or a SharePoint that's outdated the day after it's written, Howy grows every day as your team works. No documents to write. No systems to maintain. It simply stays up to date, because your work keeps it up to date.",
                            )}
                        </p>
                        <p className="m-0 min-w-0 flex-[1_1_420px] text-[clamp(22px,2.4vw,28px)] leading-[1.3] font-bold tracking-[-0.03em]">
                            {withMark(
                                t('Howy turns team knowledge into :mark'),
                                <Marker>{t('company value.')}</Marker>,
                            )}
                        </p>
                    </div>
                </section>

                {/* 4. How easy */}
                <section id="how" className="scroll-mt-20 bg-cc-wash">
                    <div
                        className={cn(
                            container,
                            sectionY,
                            'flex flex-col gap-14',
                        )}
                    >
                        <div className="flex flex-col gap-5">
                            <SectionHeading>
                                {t('Plug & play.')}
                                <br />
                                {/* One line from sm up, as in the design. */}
                                <span className="sm:whitespace-nowrap">
                                    {withMark(
                                        t('Up and running in :mark'),
                                        <Circled
                                            color="green"
                                            className="mx-[0.3em]"
                                        >
                                            {t('10 minutes.')}
                                        </Circled>,
                                    )}
                                </span>
                            </SectionHeading>
                            <p className="m-0 max-w-[760px] text-[19px] text-cc-muted">
                                {t(
                                    'No IT project. No training days. No new app your team has to get used to. Howy works with the tools you already use.',
                                )}
                            </p>
                        </div>
                        <ol className="m-0 grid list-none grid-cols-[repeat(auto-fit,minmax(min(260px,100%),1fr))] gap-10 p-0">
                            {steps.map(({ label, title, desc }) => (
                                <Step
                                    key={label}
                                    label={t(label)}
                                    title={t(title)}
                                    desc={t(desc)}
                                />
                            ))}
                        </ol>
                        <div className="flex items-start gap-x-5 gap-y-3 self-start rounded-3xl bg-white px-7 py-5 sm:items-center sm:rounded-full">
                            <Zap
                                aria-hidden="true"
                                className="mt-0.5 size-[22px] shrink-0 text-cc-ink sm:mt-0"
                                strokeWidth={1.9}
                            />
                            <p className="m-0">
                                <strong>{t('Want a head start?')}</strong>{' '}
                                {t(
                                    'Import knowledge from the past months on day one, and your blueprint is useful from the very first week.',
                                )}
                            </p>
                        </div>
                    </div>
                </section>

                {/* Photo band — always one row: three photos on wide
                    screens, two from sm, one on phones. */}
                <div
                    className={cn(
                        container,
                        'grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3',
                    )}
                >
                    {photos.map(({ src, alt, position }, index) => (
                        <img
                            key={src}
                            src={src}
                            alt={t(alt)}
                            loading="lazy"
                            className={cn(
                                'aspect-[4/3] w-full rounded-[32px] object-cover',
                                index === 0 && 'block',
                                index === 1 && 'hidden sm:block',
                                index === 2 && 'hidden lg:block',
                                position,
                            )}
                        />
                    ))}
                </div>

                {/* 5. What you get */}
                <section
                    id="benefits"
                    className={cn(
                        container,
                        sectionY,
                        'flex scroll-mt-20 flex-col gap-14',
                    )}
                >
                    <SectionHeading className="max-w-[760px]">
                        {withMark(
                            t('Less dependence. More control. A :mark'),
                            <Marker>{t('stronger company.')}</Marker>,
                        )}
                    </SectionHeading>
                    <div className="grid grid-cols-[repeat(auto-fit,minmax(min(300px,100%),1fr))] gap-x-10 gap-y-12">
                        <Benefit
                            icon={Home}
                            title={t('Knowledge that stays.')}
                            desc={t(
                                "When someone leaves, their knowledge doesn't. Their successor starts on day one with everything the company knows.",
                            )}
                        />
                        <Benefit
                            icon={UserPlus}
                            title={t('New colleagues up to speed, fast.')}
                            desc={t(
                                'Every new hire gets the company basics plus the need-to-knows for their role, ready on their first day.',
                            )}
                        />
                        <Benefit
                            icon={Search}
                            title={withMark(
                                t('Answers in :mark not hours.'),
                                <Marker color="yellow" shape="wave">
                                    {t('seconds,')}
                                </Marker>,
                            )}
                            desc={t(
                                "Teams lose up to a quarter of their week searching for information. With Howy, it's one place, always current.",
                            )}
                        />
                        <Benefit
                            icon={FileDown}
                            title={t('Ready when someone asks "show me".')}
                            desc={t(
                                'An investor, auditor, bank or buyer wants proof? Export a clear overview per topic in a few clicks.',
                            )}
                        />
                        <Benefit
                            icon={BarChart3}
                            title={t("Your company's story, on record.")}
                            desc={t(
                                'See how your company has grown: teams, people, key numbers and structure, then and now.',
                            )}
                        />
                        <Benefit
                            icon={TrendingUp}
                            title={t('A company worth more.')}
                            desc={t(
                                "A business that doesn't depend on a few people is easier to grow, to hand over and to sell.",
                            )}
                        />
                    </div>
                </section>

                {/* 6. Safe by design */}
                <section
                    id="security"
                    className="scroll-mt-24 px-[clamp(16px,4vw,40px)]"
                >
                    <div className="mx-auto flex max-w-[1400px] flex-col gap-14 rounded-[32px] bg-cc-dark px-[clamp(24px,6vw,80px)] py-[clamp(40px,7vw,96px)] text-white">
                        <div className="flex flex-wrap items-end gap-x-20 gap-y-6">
                            <SectionHeading className="min-w-0 flex-[1_1_460px]">
                                {t('Your knowledge.')}
                                <br />
                                {t('Your rules.')}{' '}
                                <Squiggle color="lime">
                                    {t('Fully protected.')}
                                </Squiggle>
                            </SectionHeading>
                            <p className="m-0 min-w-0 flex-[1_1_360px] text-lg text-cc-dark-text">
                                {t(
                                    'Company knowledge is valuable, so we treat it that way.',
                                )}
                                <br />
                                {t(
                                    'Howy is a knowledge base, not a monitoring tool.',
                                )}
                                <br />
                                {t(
                                    "Your team stays in control, and that's exactly why they'll use it.",
                                )}
                            </p>
                        </div>
                        <div className="grid grid-cols-[repeat(auto-fit,minmax(min(200px,100%),1fr))] gap-8 border-t border-white/14 pt-10">
                            {securityPoints.map(({ icon, title, desc }) => (
                                <SecurityPoint
                                    key={title}
                                    icon={icon}
                                    title={t(title)}
                                    desc={t(desc)}
                                />
                            ))}
                        </div>
                    </div>
                </section>

                {/* 7. Built for growing companies */}
                <section
                    className={cn(
                        container,
                        sectionY,
                        'flex flex-col gap-[clamp(56px,7vw,96px)]',
                    )}
                >
                    <div className="flex flex-wrap items-center gap-x-20 gap-y-12">
                        <div className="min-w-0 flex-[1_1_460px]">
                            <img
                                src={loftTeam}
                                alt={t(
                                    'Scale-up team at work in a loft office',
                                )}
                                loading="lazy"
                                className="block aspect-[5/4] w-full rounded-[32px] object-cover object-[38%_55%]"
                            />
                        </div>
                        <div className="flex min-w-0 flex-[1_1_440px] flex-col gap-5">
                            <SectionHeading>
                                {withMark(
                                    t('Made for businesses of :mark'),
                                    <Marker color="yellow" shape="wave">
                                        {t('5 to 250 people.')}
                                    </Marker>,
                                )}
                            </SectionHeading>
                            <p className="m-0 text-[17px] text-cc-muted">
                                {t(
                                    'Big corporations have knowledge teams, intranets and IT departments. You have a business to run. Howy gives growing companies the same grip on their knowledge, without the cost or the complexity.',
                                )}
                            </p>
                            <p className="m-0 mt-2 font-bold">
                                {t('Especially valuable if you:')}
                            </p>
                            <ul className="m-0 flex list-none flex-col p-0">
                                {fitList.map((item) => (
                                    <CheckItem
                                        key={item}
                                        className="gap-3 border-t border-cc-border py-3 last:border-b"
                                    >
                                        {t(item)}
                                    </CheckItem>
                                ))}
                            </ul>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-x-20 gap-y-10">
                        <div className="flex min-w-0 flex-[1_1_460px] flex-col gap-4">
                            <h3 className="m-0 text-[clamp(28px,3vw,40px)] leading-[1.1] font-extrabold tracking-[-0.035em]">
                                {t('Your team will want to join in.')}
                            </h3>
                            <p className="m-0 text-[17px] text-cc-muted">
                                {t(
                                    "Howy comes with a simple rollout for your employees, so everyone knows why and how to use it. Contributors earn points and recognition, and Howy shows who helps build the company's knowledge most. Good news for their next performance review.",
                                )}
                            </p>
                        </div>
                        <div className="relative min-w-0 flex-[1_1_400px]">
                            <blockquote className="m-0 rounded-[32px] bg-cc-accent px-9 py-8 text-[clamp(22px,2.4vw,28px)] leading-[1.3] font-bold tracking-[-0.03em]">
                                {t(
                                    '"You\'re one of our top contributors to company knowledge."',
                                )}
                            </blockquote>
                            <Sparkle className="absolute -top-[26px] -right-2 sm:-right-3.5" />
                            <span
                                aria-hidden="true"
                                className="mt-4 ml-7 block -rotate-3 sm:absolute sm:-bottom-10 sm:left-7 sm:m-0"
                            >
                                <HandNote>
                                    ↑ {t('your next performance review')}
                                </HandNote>
                            </span>
                        </div>
                    </div>
                </section>

                {/* 8. Pricing */}
                <section id="pricing" className="scroll-mt-20 bg-cc-bg">
                    <div
                        className={cn(
                            container,
                            sectionY,
                            'flex flex-col gap-12',
                        )}
                    >
                        <div className="flex flex-wrap items-end gap-x-20 gap-y-6">
                            <SectionHeading className="min-w-0 flex-[1_1_460px]">
                                {withMark(
                                    t('From :mark per user per month.'),
                                    <Circled className="mx-[0.2em]">
                                        {t('€4.99')}
                                    </Circled>,
                                )}
                            </SectionHeading>
                            <p className="m-0 min-w-0 flex-[1_1_380px] text-lg text-cc-muted">
                                {t(
                                    'Less than a coffee a week. Billed yearly, and the bigger your team, the lower the price per user. More than 150 users? Enterprise, with custom pricing.',
                                )}
                            </p>
                        </div>

                        <div className="grid grid-cols-1 divide-y divide-cc-border overflow-hidden rounded-[32px] bg-white lg:grid-cols-3 lg:divide-x lg:divide-y-0">
                            {plans.map(
                                ({ name, price, desc, features, featured }) => (
                                    <PlanCard
                                        key={name}
                                        name={t(name)}
                                        price={t(price)}
                                        priceSuffix={t('per user / month')}
                                        desc={t(desc)}
                                        features={features.map((feature) =>
                                            t(feature),
                                        )}
                                        featured={featured}
                                        badge={
                                            featured
                                                ? t('Most chosen')
                                                : undefined
                                        }
                                        cta={t('Start free')}
                                    />
                                ),
                            )}
                        </div>

                        <div className="flex flex-wrap items-center gap-x-12 gap-y-6">
                            <div className="flex min-w-0 flex-[1_1_480px] flex-col items-start gap-4 sm:flex-row sm:gap-5">
                                <span className="shrink-0 rounded-full bg-cc-accent px-4 py-2 text-[15px] font-extrabold">
                                    {t('4 months free')}
                                </span>
                                <p className="m-0 text-cc-muted">
                                    <strong className="text-cc-ink">
                                        {t('Your first 4 months are on us.')}
                                    </strong>{' '}
                                    {t(
                                        'Every company blueprint needs a build-up phase, so with a 12-month contract the first 4 months are free. We can also import past email, so your blueprint is useful from week one.',
                                    )}
                                </p>
                            </div>
                            <div className="flex flex-wrap items-center gap-x-6 gap-y-3">
                                <PillLink href={register()}>
                                    {t('Start your free build-up phase')}
                                </PillLink>
                                <a
                                    href="#pricing"
                                    className={cn(inlineLink, 'text-[15px]')}
                                >
                                    {t('Compare all plans')} →
                                </a>
                            </div>
                        </div>
                    </div>
                </section>

                {/* 9. FAQ */}
                <section
                    id="faq"
                    className={cn(
                        container,
                        sectionY,
                        'flex scroll-mt-20 flex-wrap gap-x-20 gap-y-12',
                    )}
                >
                    <div className="flex min-w-0 flex-[1_1_320px] flex-col gap-4">
                        <SectionHeading className="text-[clamp(36px,4.4vw,52px)]">
                            {withMark(
                                t('Questions, :mark'),
                                <Marker color="yellow" shape="wave">
                                    {t('answered.')}
                                </Marker>,
                            )}
                        </SectionHeading>
                        <p className="m-0 text-cc-muted">
                            {withMark(
                                t(
                                    'Something else on your mind? :mark and ask us directly.',
                                ),
                                <a href="#demo" className={inlineLink}>
                                    {t('Book a 15-minute demo')}
                                </a>,
                            )}
                        </p>
                    </div>
                    <div className="flex min-w-0 flex-[2_1_560px] flex-col">
                        {faqs.map(({ q, a }, index) => (
                            <Disclosure
                                key={q}
                                question={t(q)}
                                defaultOpen={index === 0}
                            >
                                {t(a)}
                            </Disclosure>
                        ))}
                    </div>
                </section>

                {/* 10. Final CTA */}
                <section
                    id="demo"
                    className="scroll-mt-24 px-[clamp(16px,4vw,40px)] pb-[clamp(48px,6vw,80px)]"
                >
                    <div className="mx-auto flex max-w-[1400px] flex-wrap items-end gap-x-20 gap-y-8 rounded-[32px] bg-cc-accent px-[clamp(24px,6vw,80px)] py-[clamp(40px,7vw,96px)]">
                        <div className="flex min-w-0 flex-[2_1_520px] flex-col gap-5">
                            <h2 className="m-0 text-[clamp(38px,5vw,64px)] leading-[1.02] font-extrabold tracking-[-0.045em]">
                                {withMark(
                                    t(
                                        "Don't let your next :mark cost you your knowledge.",
                                    ),
                                    <Squiggle>{t('goodbye')}</Squiggle>,
                                )}
                            </h2>
                            <p className="m-0 max-w-[560px] text-[19px]">
                                {t(
                                    'Start building your company blueprint today. Up and running in 10 minutes, free for your first 4 months.',
                                )}
                            </p>
                        </div>
                        <div className="flex min-w-0 flex-[1_1_280px] flex-col gap-3">
                            <PillLink href={register()} variant="ink" block>
                                {t('Start your free build-up phase')}
                            </PillLink>
                            <PillLink href="#demo" variant="white" block>
                                {t('Book a 15-minute demo')}
                            </PillLink>
                        </div>
                    </div>
                </section>

                {/* Footer */}
                <footer
                    className={cn(
                        container,
                        'flex flex-wrap items-center justify-between gap-5 pt-8 pb-12 text-sm text-cc-muted',
                    )}
                >
                    <HowyLogo size={22} />
                    <div className="flex flex-wrap gap-x-7 gap-y-2">
                        {footerLinks.map(({ href, label }) => (
                            <a
                                key={label}
                                href={href}
                                className="text-cc-muted no-underline transition-colors hover:text-cc-accent-deep"
                            >
                                {t(label)}
                            </a>
                        ))}
                    </div>
                    <div className="flex items-center gap-5">
                        <LanguageSwitcher variant="flag" side="top" />
                        <span>
                            {t('© :year Howy', {
                                year: new Date().getFullYear(),
                            })}
                        </span>
                    </div>
                </footer>
            </div>
        </>
    );
}
