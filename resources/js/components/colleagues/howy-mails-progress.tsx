import { Form, router } from '@inertiajs/react';
import {
    ArrowRight,
    Check,
    ChevronDown,
    Clock,
    Flag,
    Minus,
    RotateCw,
    Send,
} from 'lucide-react';
import { useSyncExternalStore } from 'react';
import { WithHowy } from '@/components/brand/howy-name';
import { ColleagueStatusTag } from '@/components/colleagues/colleague-status';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { update, welcome } from '@/routes/colleagues';
import sentRoutes from '@/routes/colleagues/sent';
import type {
    ColleagueDetail,
    ColleagueMailboxAccess,
    MailboxOption,
    SentMail,
} from '@/types';

type StepKind = 'welcome' | 'how_it_works' | 'nudge' | 'first_save';

/**
 * Sent; on its way; due on a date, or past it; held back by a pause; not
 * needed any more; or nothing yet.
 */
type StepState =
    | 'done'
    | 'sending'
    | 'planned'
    | 'due'
    | 'paused'
    | 'skipped'
    | 'open';

type Step = {
    kind: StepKind;
    label: string;
    rule: string;
    state: StepState;
    status: string;
    at: string | null;
    plannedAt: string | null;
};

type Props = {
    colleague: ColleagueDetail;
    /** Their access on the mailbox their Howy mails come from. */
    home: ColleagueMailboxAccess | null;
    mailboxes: MailboxOption[];
    planned: { how_it_works: string | null; nudge: string | null };
    onTheirWay: string[];
    resendable: Record<string, number>;
    sent: SentMail[];
    canWelcome: boolean;
    isLive: boolean;
    followUpDays: { how_it_works: number; nudge: number };
    onShowLog: () => void;
};

const NODE_LOOKS: Record<StepState, string> = {
    done: 'border-cc-ink bg-cc-accent text-cc-ink',
    sending: 'border-cc-question-fg bg-cc-question-bg text-cc-question-fg',
    planned: 'border-cc-ink bg-cc-panel text-cc-ink',
    due: 'border-cc-ink bg-cc-highlight text-cc-ink',
    paused: 'border-cc-ink bg-cc-highlight text-cc-ink',
    skipped: 'border-cc-border-strong bg-cc-raised text-cc-subtle',
    open: 'border-cc-border-strong bg-cc-panel text-cc-subtle',
};

const STATUS_LOOKS: Record<StepState, string> = {
    done: 'font-semibold text-cc-accent-deep',
    sending: 'font-semibold text-cc-question-fg',
    planned: 'font-semibold text-cc-ink',
    due: 'font-semibold text-cc-pending-fg',
    paused: 'font-semibold text-cc-pending-fg',
    skipped: 'font-medium text-cc-subtle',
    open: 'font-medium text-cc-subtle',
};

/**
 * Where someone's Howy mails stand, as a bar: welcome, how it works, the
 * reminder for someone Howy learns from, and their first contribution as the
 * goal. Above it, in one sentence, where they are and what comes next.
 */
export function HowyMailsProgress({
    colleague,
    home,
    mailboxes,
    planned,
    onTheirWay,
    resendable,
    sent,
    canWelcome,
    isLive,
    followUpDays,
    onShowLog,
}: Props) {
    const t = useTranslations();
    const formatDate = useFormatDate();
    const clock = useMinute();
    // Whether a date has passed, the same on the server as in the browser.
    const now = clock ?? Date.now();

    const day = (at: string) =>
        formatDate(at, {
            dateStyle: undefined,
            weekday: 'short',
            day: 'numeric',
            month: 'short',
        });

    const learns =
        colleague.access === 'learn' || colleague.access === 'learn_answer';
    const mailboxDown = home !== null && home.status !== 'active';
    // Nothing of Howy's own goes out, and nothing will until an admin or
    // they themselves change that.
    const off =
        colleague.optedOutAt !== null ||
        !colleague.receivesMail ||
        colleague.access === 'ignore';
    // Held back for now: what is due goes out once this is over.
    const paused = !off && (!isLive || mailboxDown);

    const afterWelcome = (count: number) =>
        count === 1
            ? t('1 working day after the welcome')
            : t(':count working days after the welcome', { count });

    const definitions: {
        kind: StepKind;
        label: string;
        rule: string;
        at: string | null;
    }[] = [
        {
            kind: 'welcome',
            label: t('Welcome'),
            rule: t('On going live, or with their first mail to Howy'),
            at: colleague.welcomedAt,
        },
        {
            kind: 'how_it_works',
            label: t('How Howy works'),
            rule: afterWelcome(followUpDays.how_it_works),
            at: colleague.explainedAt,
        },
        // Only for someone Howy learns from: someone it only answers has
        // nothing to share.
        ...(learns ||
        colleague.nudgedAt !== null ||
        colleague.firstSavedAt !== null
            ? [
                  {
                      kind: 'nudge' as const,
                      label: t('Reminder to share'),
                      rule:
                          followUpDays.nudge === 1
                              ? t(
                                    '1 working day after the welcome, if they have not mailed Howy since',
                                )
                              : t(
                                    ':count working days after the welcome, if they have not mailed Howy since',
                                    { count: followUpDays.nudge },
                                ),
                      at: colleague.nudgedAt,
                  },
                  {
                      kind: 'first_save' as const,
                      label: t('First contribution'),
                      rule: t(
                          'Howy thanks them for the first thing they share',
                      ),
                      at: colleague.firstSavedAt,
                  },
              ]
            : []),
    ];

    const steps: Step[] = definitions.map((step) => {
        const plannedAt =
            step.kind === 'how_it_works' || step.kind === 'nudge'
                ? planned[step.kind]
                : null;
        const held =
            paused &&
            (plannedAt !== null ||
                step.kind === 'welcome' ||
                (step.kind === 'how_it_works' &&
                    colleague.welcomedAt !== null));

        const [state, status]: [StepState, string] =
            step.at !== null
                ? ['done', t('Sent :date', { date: day(step.at) })]
                : onTheirWay.includes(step.kind)
                  ? ['sending', t('On its way')]
                  : held
                    ? [
                          'paused',
                          isLive
                              ? t('Waits for the mailbox')
                              : t('Waits for going live'),
                      ]
                    : plannedAt !== null
                      ? new Date(plannedAt).getTime() <= now
                          ? ['due', t('Due')]
                          : [
                                'planned',
                                t('Planned :date', { date: day(plannedAt) }),
                            ]
                      : step.kind === 'nudge' &&
                          colleague.welcomedAt !== null &&
                          !off &&
                          !paused
                        ? ['skipped', t('Not needed')]
                        : ['open', t('Not yet')];

        return { ...step, state, status, plannedAt };
    });

    // The bar fills to the last mail that went; towards a planned next one it
    // shows how much of the wait is behind them.
    const count = steps.length;
    const column = 100 / count;
    let lastDone = -1;
    steps.forEach((step, index) => {
        if (step.state === 'done') {
            lastDone = index;
        }
    });
    const fill = Math.max(lastDone, 0) * column;
    const next = steps[lastDone + 1];
    const from = lastDone >= 0 ? steps[lastDone].at : null;
    let partial = 0;
    let today: number | null = null;

    if (next?.state === 'sending') {
        partial = column / 2;
    } else if (
        next?.state === 'planned' &&
        next.plannedAt !== null &&
        from !== null &&
        clock !== null
    ) {
        // Only in the browser: the server renders the page a moment earlier.
        const share =
            (clock - Date.parse(from)) /
            (Date.parse(next.plannedAt) - Date.parse(from));
        partial =
            Math.round(Math.min(1, Math.max(0, share)) * column * 100) / 100;
        today = fill + partial;
    }

    const sentCount = steps.filter((step) => step.state === 'done').length;
    const mailbox = home?.emailAddress ?? colleague.homeMailbox ?? '';
    const summary = useSummary({
        colleague,
        planned,
        onTheirWay,
        canWelcome,
        isLive,
        hasMailboxes: mailboxes.length > 0,
        mailboxDown,
        learns,
        mailbox,
        followUpDays,
        day,
        now,
    });

    // The latest of each kind of Howy's own mail, for the replies to it.
    const repliesTo = (kind: StepKind) =>
        sent.find((mail) => mail.kind === kind)?.replies.length ?? 0;

    return (
        <section
            aria-labelledby="howy-mails-title"
            className="cc-panel flex flex-col gap-6 p-6 lg:px-8 lg:pt-7 lg:pb-5"
        >
            <div className="flex flex-col gap-2">
                <div className="flex flex-wrap items-center gap-2.5">
                    <h2
                        id="howy-mails-title"
                        className="text-[17px] leading-tight font-semibold tracking-[-0.01em]"
                    >
                        {t('Howy mails')}
                    </h2>
                    <ColleagueStatusTag status={colleague.status} />
                    <span className="cc-caption">
                        {t(':sent of :total sent', {
                            sent: sentCount,
                            total: count,
                        })}
                    </span>
                </div>
                <p className="text-[22px] leading-[1.25] font-bold tracking-[-0.025em]">
                    <WithHowy text={summary.title} />
                </p>
                <p className="max-w-[640px] text-[14.5px] leading-[1.55] text-cc-muted">
                    <WithHowy text={summary.text} />
                </p>
                {canWelcome && (
                    <Form
                        {...welcome.form(colleague.id)}
                        options={{ preserveScroll: true, preserveState: true }}
                        className="mt-2"
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                className="h-11 px-[18px]"
                                disabled={processing}
                            >
                                {t('Send the welcome mail now')}
                            </Button>
                        )}
                    </Form>
                )}
            </div>

            <div
                className={cn(
                    'relative',
                    today !== null && 'md:pt-[26px]',
                    off && 'opacity-40 grayscale',
                )}
            >
                <div
                    aria-hidden="true"
                    className={cn(
                        'absolute left-[18px] hidden h-2 rounded bg-cc-border md:block',
                        today !== null ? 'top-[40px]' : 'top-[14px]',
                    )}
                    style={{ width: `${(count - 1) * column}%` }}
                />
                {fill > 0 && (
                    <div
                        aria-hidden="true"
                        className={cn(
                            'absolute left-[18px] hidden h-2 rounded-l bg-cc-accent shadow-[inset_0_0_0_1px_rgb(13_27_30/0.28)] md:block',
                            today !== null ? 'top-[40px]' : 'top-[14px]',
                        )}
                        style={{ width: `${fill}%` }}
                    />
                )}
                {partial > 0 && (
                    <div
                        aria-hidden="true"
                        className={cn(
                            'absolute hidden h-2 rounded-r bg-[repeating-linear-gradient(-45deg,var(--color-cc-accent)_0_5px,var(--color-cc-accent-tint)_5px_10px)] md:block',
                            today !== null ? 'top-[40px]' : 'top-[14px]',
                        )}
                        style={{
                            left: `calc(${fill}% + 18px)`,
                            width: `${partial}%`,
                        }}
                    />
                )}
                {today !== null && (
                    <div
                        aria-hidden="true"
                        className="absolute top-0 hidden -translate-x-1/2 flex-col items-center md:flex"
                        style={{ left: `calc(${today}% + 18px)` }}
                    >
                        <span className="flex h-5 items-center rounded-full bg-cc-ink px-2 text-[11px] font-semibold whitespace-nowrap text-white">
                            {t('Today')}
                        </span>
                        <span className="h-[34px] w-0.5 bg-cc-ink" />
                    </div>
                )}

                <ol
                    className="flex flex-col md:grid"
                    style={{
                        gridTemplateColumns: `repeat(${count}, minmax(0, 1fr))`,
                    }}
                >
                    {steps.map((step, index) => {
                        const replies = repliesTo(step.kind);
                        const resendId =
                            onTheirWay.includes(step.kind) || off
                                ? undefined
                                : resendable[step.kind];

                        return (
                            <li
                                key={step.kind}
                                className="relative flex gap-3.5 pb-5 last:pb-0 md:flex-col md:pr-7 md:pb-0"
                            >
                                {index < count - 1 && (
                                    <span
                                        aria-hidden="true"
                                        className={cn(
                                            'absolute top-[30px] -bottom-1.5 left-[14px] w-2 md:hidden',
                                            index < lastDone
                                                ? 'bg-cc-accent'
                                                : 'bg-cc-border',
                                        )}
                                    />
                                )}
                                <StepNode step={step} number={index + 1} />
                                <div className="flex min-w-0 flex-1 flex-col gap-[3px] pt-0.5 md:pt-0">
                                    <span
                                        className={cn(
                                            'text-[15px] font-semibold',
                                            (step.state === 'open' ||
                                                step.state === 'skipped') &&
                                                'text-cc-muted',
                                        )}
                                    >
                                        {step.label}
                                    </span>
                                    <span className="text-[12.5px] leading-[1.45] text-cc-subtle">
                                        <WithHowy text={step.rule} />
                                    </span>
                                    <span
                                        className={cn(
                                            'mt-1 text-[13px]',
                                            STATUS_LOOKS[step.state],
                                        )}
                                    >
                                        {step.status}
                                    </span>
                                    {(replies > 0 ||
                                        resendId !== undefined) && (
                                        <div className="mt-2.5 flex flex-wrap items-center gap-2">
                                            {replies > 0 && (
                                                <span className="cc-tag cc-tag-question">
                                                    {replies === 1
                                                        ? t('1 reply')
                                                        : t(':count replies', {
                                                              count: replies,
                                                          })}
                                                </span>
                                            )}
                                            {resendId !== undefined && (
                                                <Form
                                                    {...sentRoutes.resend.form(
                                                        resendId,
                                                    )}
                                                    options={{
                                                        preserveScroll: true,
                                                        preserveState: true,
                                                    }}
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            type="submit"
                                                            variant="outline"
                                                            disabled={
                                                                processing
                                                            }
                                                            aria-label={t(
                                                                'Send again: :mail',
                                                                {
                                                                    mail: step.label,
                                                                },
                                                            )}
                                                            className="h-9 rounded-full border-[1.5px] border-cc-border-strong px-3 text-[13px] font-semibold"
                                                        >
                                                            <RotateCw className="size-3.5" />
                                                            {t('Resend')}
                                                        </Button>
                                                    )}
                                                </Form>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </li>
                        );
                    })}
                </ol>
            </div>

            <div className="cc-caption flex flex-wrap items-center justify-between gap-x-4 gap-y-1 border-t border-cc-border pt-3">
                <div className="flex flex-wrap items-center gap-x-1.5">
                    <span>{t('Sent from')}</span>
                    <HomeMailboxPicker
                        colleague={colleague}
                        mailboxes={mailboxes}
                    />
                    <span aria-hidden="true">·</span>
                    <span>
                        <WithHowy
                            text={
                                colleague.firstSeenAt !== null
                                    ? t('First mail to Howy on :date.', {
                                          date: formatDate(
                                              colleague.firstSeenAt,
                                          ),
                                      })
                                    : t('Has not mailed Howy yet.')
                            }
                        />
                    </span>
                </div>
                {sent.length > 0 && (
                    <button
                        type="button"
                        onClick={onShowLog}
                        className="inline-flex h-9 cursor-pointer items-center gap-1.5 font-semibold text-cc-ink"
                    >
                        <span>
                            <WithHowy
                                text={t(
                                    'Everything Howy sent, in the mail log',
                                )}
                            />
                        </span>
                        <ArrowRight className="size-[15px]" />
                    </button>
                )}
            </div>
        </section>
    );
}

function everyMinute(callback: () => void) {
    const timer = window.setInterval(callback, 60_000);

    return () => window.clearInterval(timer);
}

function getServerMinute(): null {
    return null;
}

/**
 * The time to the minute in the browser, and none while rendering on the
 * server, so what is drawn from it never differs between the two.
 */
function useMinute(): number | null {
    return useSyncExternalStore(
        everyMinute,
        () => Math.floor(Date.now() / 60_000) * 60_000,
        getServerMinute,
    );
}

/**
 * Where one step stands, as its dot on the bar.
 */
function StepNode({ step, number }: { step: Step; number: number }) {
    const goal = step.kind === 'first_save';

    return (
        <span
            aria-hidden="true"
            className={cn(
                'relative z-[1] flex size-9 shrink-0 items-center justify-center rounded-full border-[1.5px] text-[13px] font-bold',
                NODE_LOOKS[step.state],
                goal &&
                    step.state === 'open' &&
                    'border-dashed border-cc-faint',
            )}
        >
            {step.state === 'done' ? (
                <Check className="size-4" strokeWidth={2.6} />
            ) : step.state === 'sending' ? (
                <Send className="size-4" />
            ) : step.state === 'skipped' ? (
                <Minus className="size-4" />
            ) : goal ? (
                <Flag className="size-4" />
            ) : step.state === 'open' ? (
                number
            ) : (
                <Clock className="size-[17px]" />
            )}
        </span>
    );
}

/**
 * The mailbox their Howy mails come from, changed in place when the account
 * has more than one.
 */
function HomeMailboxPicker({
    colleague,
    mailboxes,
}: {
    colleague: ColleagueDetail;
    mailboxes: MailboxOption[];
}) {
    const t = useTranslations();
    const current = colleague.homeMailbox ?? t('Choose a mailbox');

    if (mailboxes.length === 0) {
        return (
            <span className="font-semibold text-cc-ink">
                {colleague.homeMailbox ?? t('No mailbox')}
            </span>
        );
    }

    if (mailboxes.length < 2 && colleague.homeMailbox !== null) {
        return <span className="font-semibold text-cc-ink">{current}</span>;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className="inline-flex h-9 cursor-pointer items-center gap-1 px-0.5 text-[13px] font-semibold text-cc-ink"
                >
                    <span className="underline decoration-cc-faint decoration-dotted decoration-[1.5px] underline-offset-4">
                        {current}
                    </span>
                    <ChevronDown className="size-3.5" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-72">
                <DropdownMenuLabel>
                    <WithHowy text={t('Howy mails come from')} />
                </DropdownMenuLabel>
                <DropdownMenuRadioGroup
                    value={
                        colleague.homeMailboxId !== null
                            ? String(colleague.homeMailboxId)
                            : ''
                    }
                    onValueChange={(value) =>
                        router.patch(
                            update.url(colleague.id),
                            { home_mailbox_id: Number(value) },
                            { preserveScroll: true, preserveState: true },
                        )
                    }
                >
                    {mailboxes.map((mailbox) => (
                        <DropdownMenuRadioItem
                            key={mailbox.id}
                            value={String(mailbox.id)}
                        >
                            {mailbox.emailAddress}
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/**
 * Where they stand in one sentence, and what comes next: what stops Howy
 * first, then how far the mails have come.
 */
function useSummary({
    colleague,
    planned,
    onTheirWay,
    canWelcome,
    isLive,
    hasMailboxes,
    mailboxDown,
    learns,
    mailbox,
    followUpDays,
    day,
    now,
}: {
    colleague: ColleagueDetail;
    planned: Props['planned'];
    onTheirWay: string[];
    canWelcome: boolean;
    isLive: boolean;
    hasMailboxes: boolean;
    mailboxDown: boolean;
    learns: boolean;
    mailbox: string;
    followUpDays: Props['followUpDays'];
    day: (at: string) => string;
    now: number;
}): { title: string; text: string } {
    const t = useTranslations();

    if (colleague.optedOutAt !== null) {
        return {
            title: t('Unsubscribed from Howy mails'),
            text: t(
                'On :date, through the link under a Howy mail. Answers to their own questions still go out.',
                { date: day(colleague.optedOutAt) },
            ),
        };
    }

    if (!colleague.receivesMail) {
        return {
            title: t('Howy mails are off'),
            text: t(
                'Howy sends them nothing of its own. Switch it back on and Howy picks up where it left off.',
            ),
        };
    }

    if (colleague.homeMailbox === null) {
        return {
            title: t('No mailbox for their Howy mails'),
            text: hasMailboxes
                ? t(
                      'The mailbox their Howy mails came from was disconnected, so Howy sends them nothing of its own. Choose another below, or Howy takes the one they mail next.',
                  )
                : t(
                      'The mailbox their Howy mails came from was disconnected, so Howy sends them nothing of its own. Connect a mailbox in settings, and Howy takes the one they mail next.',
                  ),
        };
    }

    if (colleague.access === 'ignore') {
        return {
            title: t('Howy ignores their mail'),
            text: t(
                'No rule lets Howy use their mail on :mailbox, so it sends them nothing of its own.',
                { mailbox },
            ),
        };
    }

    if (!isLive) {
        return colleague.welcomedAt !== null
            ? {
                  title: t('Howy mails are paused'),
                  text: t(
                      'Howy is not live, so nothing goes out until it is again.',
                  ),
              }
            : {
                  title: t('Howy is not live yet'),
                  text: t('They get the welcome once you go live.'),
              };
    }

    if (mailboxDown) {
        return {
            title: t('Howy mails wait for the mailbox'),
            text: t(
                ':mailbox is not connected. What is due goes out once it is again.',
                { mailbox },
            ),
        };
    }

    if (colleague.firstSavedAt !== null) {
        return {
            title: t('Shares what they know'),
            text: t('Their first contribution came in on :date.', {
                date: day(colleague.firstSavedAt),
            }),
        };
    }

    if (colleague.welcomedAt === null) {
        if (onTheirWay.includes('welcome')) {
            return {
                title: t('The welcome is on its way'),
                text:
                    followUpDays.how_it_works === 1
                        ? t('The explanation follows a working day later.')
                        : t(
                              'The explanation follows :count working days later.',
                              { count: followUpDays.how_it_works },
                          ),
            };
        }

        return {
            title: t('Not welcomed yet'),
            text: canWelcome
                ? t(
                      'Howy sends the welcome with their first mail. You can also send it now.',
                  )
                : t('Howy sends the welcome with their first mail.'),
        };
    }

    if (colleague.nudgedAt !== null) {
        return {
            title: t('Reminder sent, nothing shared yet'),
            text: t(
                'Howy has sent everything it sends. The next step is theirs.',
            ),
        };
    }

    if (colleague.explainedAt === null && planned.how_it_works !== null) {
        return {
            title: t('Welcomed'),
            text:
                new Date(planned.how_it_works).getTime() <= now
                    ? t('The explanation goes out with the next round.')
                    : t('The explanation follows on :date.', {
                          date: day(planned.how_it_works),
                      }),
        };
    }

    if (planned.nudge !== null) {
        return {
            title: t('Waiting for the first contribution'),
            text: t(
                'If they have not mailed Howy by :date, Howy sends a reminder.',
                { date: day(planned.nudge) },
            ),
        };
    }

    if (learns) {
        return {
            title: t('Waiting for the first contribution'),
            text: t(
                'They have mailed Howy since the welcome, so no reminder goes out.',
            ),
        };
    }

    return {
        title: t('Welcomed'),
        text: t('Howy only answers their questions, so this is all it sends.'),
    };
}
