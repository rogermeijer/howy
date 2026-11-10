import { Head, Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { Check, Minus } from 'lucide-react';
import { Fragment, useId } from 'react';
import type { ReactNode } from 'react';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import setup from '@/routes/setup';

export type SetupStepKey = 'workspace' | 'knowledge' | 'mailbox';

type Props = {
    /** The step on screen; `done` once setup is finished. */
    current: SetupStepKey | 'done';
    /** Finished without a mailbox: its step shows as skipped. */
    mailboxSkipped?: boolean;
    /** The document title. */
    head: string;
    eyebrow: string;
    title: ReactNode;
    description?: ReactNode;
    /** Above the eyebrow, inside the card. */
    banner?: ReactNode;
    children?: ReactNode;
    footer?: ReactNode;
    /** A line under the card. */
    note?: ReactNode;
};

/**
 * One step of setup: where it stands among the three, and the step itself in
 * a single card. Steps already done link back while setup is under way.
 */
export function SetupStep({
    current,
    mailboxSkipped = false,
    head,
    eyebrow,
    title,
    description,
    banner,
    children,
    footer,
    note,
}: Props) {
    const t = useTranslations();
    const titleId = useId();

    // English source strings, translated where rendered.
    const steps: {
        key: SetupStepKey;
        label: string;
        href: InertiaLinkProps['href'];
    }[] = [
        { key: 'workspace', label: 'Workspace', href: setup.workspace() },
        {
            key: 'knowledge',
            label: 'Knowledge language',
            href: setup.knowledge(),
        },
        { key: 'mailbox', label: 'Mailbox', href: setup.mailbox() },
    ];
    const at =
        current === 'done'
            ? steps.length
            : steps.findIndex((step) => step.key === current);

    return (
        <>
            <Head title={head} />

            <nav aria-label={t('Setup steps')}>
                <ol className="flex items-center gap-3">
                    {steps.map((step, index) => {
                        const skipped =
                            step.key === 'mailbox' &&
                            current === 'done' &&
                            mailboxSkipped;
                        const state = skipped
                            ? 'skipped'
                            : index < at
                              ? 'done'
                              : index === at
                                ? 'current'
                                : 'upcoming';
                        const last = index === steps.length - 1;
                        const label = (
                            <>
                                <span
                                    className={cn(
                                        'flex size-7 shrink-0 items-center justify-center rounded-full text-[13px]',
                                        state === 'done' &&
                                            'bg-cc-accent text-cc-ink',
                                        state === 'current' &&
                                            'bg-cc-ink font-bold text-white',
                                        (state === 'upcoming' ||
                                            state === 'skipped') &&
                                            'border-[1.5px] border-cc-border-strong bg-cc-panel font-semibold text-cc-subtle',
                                    )}
                                >
                                    {state === 'done' ? (
                                        <Check
                                            className="size-4"
                                            strokeWidth={3}
                                            aria-label={t('Completed')}
                                        />
                                    ) : state === 'skipped' ? (
                                        <Minus
                                            className="size-3.5"
                                            strokeWidth={3}
                                            aria-label={t('Skipped')}
                                        />
                                    ) : (
                                        index + 1
                                    )}
                                </span>
                                <span
                                    className={cn(
                                        'text-[13px] whitespace-nowrap',
                                        state === 'current'
                                            ? 'font-bold'
                                            : 'hidden sm:inline',
                                        state === 'done' && 'font-semibold',
                                        (state === 'upcoming' ||
                                            state === 'skipped') &&
                                            'font-medium text-cc-subtle',
                                    )}
                                >
                                    {t(step.label)}
                                </span>
                            </>
                        );

                        return (
                            <li
                                key={step.key}
                                aria-current={
                                    state === 'current' ? 'step' : undefined
                                }
                                className={cn(
                                    'flex items-center gap-2.5',
                                    !last && 'flex-1',
                                )}
                            >
                                {state === 'done' && current !== 'done' ? (
                                    <Link
                                        href={step.href}
                                        className="flex items-center gap-2.5 rounded-full hover:underline"
                                    >
                                        {label}
                                    </Link>
                                ) : (
                                    <Fragment>{label}</Fragment>
                                )}
                                {!last && (
                                    <span
                                        aria-hidden="true"
                                        className={cn(
                                            'h-0.5 min-w-4 flex-1 rounded-full',
                                            index < at
                                                ? 'bg-cc-ink'
                                                : 'bg-cc-border-strong',
                                        )}
                                    />
                                )}
                            </li>
                        );
                    })}
                </ol>
            </nav>

            <section
                aria-labelledby={titleId}
                className="cc-panel flex flex-col gap-7 p-6 sm:p-10"
            >
                {banner}
                <div className="flex flex-col gap-2.5">
                    <p className="cc-label">{eyebrow}</p>
                    <h1 id={titleId} className="cc-title">
                        {title}
                    </h1>
                    {description && (
                        <p className="text-[15px] leading-[1.55] text-cc-muted">
                            {description}
                        </p>
                    )}
                </div>
                {children}
                {footer && (
                    <div className="flex flex-col gap-3 border-t border-cc-border pt-6">
                        {footer}
                    </div>
                )}
            </section>

            {note && <p className="cc-caption text-center">{note}</p>}
        </>
    );
}
