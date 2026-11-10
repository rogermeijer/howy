import { Fragment, type ReactNode } from 'react';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Marker } from '@/components/brand/doodles';
import { HowyLogo } from '@/components/brand/howy-logo';
import { LanguageSwitcher } from '@/components/language-switcher';
import {
    CONTACT_EMAIL,
    ContactDialog,
} from '@/components/marketing/contact-dialog';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { home, privacyPolicy, termsOfService } from '@/routes';

/**
 * A block of a legal section: a paragraph, or a list of items. Both are
 * English source strings, translated at render time.
 */
export type LegalBlock = string | string[];

export type LegalSection = {
    /** The anchor the table of contents links to. */
    id: string;
    title: string;
    body: LegalBlock[];
};

/**
 * Who offers Howy, swapped in for `:company`, `:kvk` and `:address`.
 * Placeholders until the company details are final; names, not copy.
 */
const LEGAL_ENTITY = {
    company: '[Company name]',
    kvk: '[KvK number]',
    address: '[Address]',
};

const legalLinks = [
    { href: privacyPolicy(), label: 'Privacy policy' },
    { href: termsOfService(), label: 'Terms of service' },
];

/** Page width and side gutter, as on the welcome page. */
const container = 'mx-auto w-full max-w-[1320px] px-[clamp(16px,4vw,40px)]';

const inlineLink =
    'font-semibold text-cc-ink underline underline-offset-[3px] transition-colors hover:text-cc-accent-deep';

/**
 * A legal document — the privacy policy, the terms of service — as one
 * readable page: title, date of the last change, contents and sections.
 */
export default function LegalLayout({
    title,
    intro,
    updatedAt,
    sections,
}: {
    title: string;
    intro: string;
    /** When the text last changed, as an ISO date. */
    updatedAt: string;
    sections: LegalSection[];
}) {
    const t = useTranslations();
    const formatDate = useFormatDate();

    // Translate, fill in the company details, and make `:email` a link.
    const text = (key: string): ReactNode => {
        const [before, ...rest] = t(key, LEGAL_ENTITY).split(':email');

        return (
            <>
                {before}
                {rest.map((after, index) => (
                    <Fragment key={index}>
                        <a
                            href={`mailto:${CONTACT_EMAIL}`}
                            className={inlineLink}
                        >
                            {CONTACT_EMAIL}
                        </a>
                        {after}
                    </Fragment>
                ))}
            </>
        );
    };

    return (
        <>
            <Head title={t(title)} />

            <div className="min-h-screen bg-white text-base leading-[1.65] text-cc-ink antialiased">
                <header className="sticky top-0 z-40 border-b border-cc-border bg-white/90 backdrop-blur-md">
                    <nav
                        aria-label={t('Main')}
                        className={cn(
                            container,
                            'flex items-center gap-4 py-3',
                        )}
                    >
                        <Link
                            href={home()}
                            aria-label={t('Howy, back to the home page')}
                            className="block no-underline"
                        >
                            <HowyLogo size={24} />
                        </Link>
                        <div className="ml-auto flex items-center gap-2">
                            <Link
                                href={home()}
                                className="hidden h-10 items-center gap-1.5 rounded-full px-3 text-sm font-medium text-cc-muted no-underline transition-colors hover:bg-cc-raised hover:text-cc-ink sm:inline-flex"
                            >
                                <ArrowLeft
                                    aria-hidden="true"
                                    className="size-4"
                                />
                                {t('Back to home')}
                            </Link>
                            <LanguageSwitcher />
                        </div>
                    </nav>
                </header>

                <main
                    className={cn(container, 'pt-[clamp(40px,7vw,88px)] pb-24')}
                >
                    <div className="flex max-w-[760px] flex-col gap-5">
                        <h1 className="m-0 text-[clamp(36px,5vw,60px)] leading-[1.05] font-extrabold tracking-[-0.04em]">
                            <Marker>{t(title)}</Marker>
                        </h1>
                        <p className="m-0 text-sm font-semibold text-cc-muted">
                            {t('Last updated :date', {
                                date: formatDate(updatedAt, {
                                    dateStyle: 'long',
                                }),
                            })}
                        </p>
                        <p className="m-0 text-[19px] leading-[1.6]">
                            {text(intro)}
                        </p>
                    </div>

                    <div className="mt-[clamp(40px,6vw,72px)] grid gap-12 lg:grid-cols-[240px_minmax(0,760px)] lg:gap-16">
                        <aside className="lg:sticky lg:top-24 lg:self-start">
                            <nav
                                aria-label={t('Contents')}
                                className="rounded-[18px] border-[1.5px] border-cc-border bg-cc-bg p-5"
                            >
                                <p className="m-0 mb-3 text-xs font-bold tracking-[0.08em] text-cc-muted uppercase">
                                    {t('Contents')}
                                </p>
                                <ol className="m-0 flex list-none flex-col gap-2 p-0 text-[15px]">
                                    {sections.map((section, index) => (
                                        <li
                                            key={section.id}
                                            className="flex gap-2"
                                        >
                                            <span className="w-5 shrink-0 text-cc-faint tabular-nums">
                                                {index + 1}.
                                            </span>
                                            <a
                                                href={`#${section.id}`}
                                                className="text-cc-muted no-underline transition-colors hover:text-cc-ink"
                                            >
                                                {t(section.title)}
                                            </a>
                                        </li>
                                    ))}
                                </ol>
                            </nav>
                        </aside>

                        <article className="flex min-w-0 flex-col gap-12">
                            {sections.map((section, index) => (
                                <section
                                    key={section.id}
                                    id={section.id}
                                    className="flex scroll-mt-24 flex-col gap-4"
                                >
                                    <h2 className="m-0 flex gap-3 text-[26px] leading-[1.2] font-extrabold tracking-[-0.03em]">
                                        <span className="text-cc-accent-deep tabular-nums">
                                            {index + 1}.
                                        </span>
                                        {t(section.title)}
                                    </h2>
                                    {section.body.map((block, blockIndex) =>
                                        typeof block === 'string' ? (
                                            <p key={blockIndex} className="m-0">
                                                {text(block)}
                                            </p>
                                        ) : (
                                            <ul
                                                key={blockIndex}
                                                className="m-0 flex flex-col gap-2.5 pl-5 marker:text-cc-accent-deep"
                                            >
                                                {block.map((item) => (
                                                    <li
                                                        key={item}
                                                        className="pl-1"
                                                    >
                                                        {text(item)}
                                                    </li>
                                                ))}
                                            </ul>
                                        ),
                                    )}
                                </section>
                            ))}
                        </article>
                    </div>
                </main>

                <footer
                    className={cn(
                        container,
                        'flex flex-wrap items-center justify-between gap-5 border-t border-cc-border pt-8 pb-12 text-sm text-cc-muted',
                    )}
                >
                    <HowyLogo size={22} />
                    <div className="flex flex-wrap gap-x-7 gap-y-2">
                        {legalLinks.map(({ href, label }) => (
                            <Link
                                key={label}
                                href={href}
                                className="text-cc-muted no-underline transition-colors hover:text-cc-accent-deep"
                            >
                                {t(label)}
                            </Link>
                        ))}
                        <ContactDialog>
                            <button
                                type="button"
                                className="cursor-pointer text-cc-muted transition-colors hover:text-cc-accent-deep"
                            >
                                {t('Contact')}
                            </button>
                        </ContactDialog>
                    </div>
                    <span>
                        {t('© :year Howy', { year: new Date().getFullYear() })}
                    </span>
                </footer>
            </div>
        </>
    );
}
