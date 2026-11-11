import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, Check, Lock, Minus, Rocket, Upload } from 'lucide-react';
import { type ReactNode, useState } from 'react';
import { BRAND_NAME } from '@/components/brand/brand';
import { Marker } from '@/components/brand/doodles';
import { WithHowy } from '@/components/brand/howy-name';
import { useAccessLooks } from '@/components/mailboxes/sender-access';
import { GoLiveDialog } from '@/components/settings/go-live-dialog';
import { SetupStep } from '@/components/setup/setup-step';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { inbox, knowledge, settings } from '@/routes';
import documentRoutes from '@/routes/knowledge/documents';
import type { Auth, LiveState, SenderAccess, WelcomeCandidate } from '@/types';

type Props = {
    account: {
        name: string;
        language: string;
        timezone: string;
        knowledgeLanguage: string;
    };
    mailbox: {
        emailAddress: string;
        publicDomain: boolean;
        domainAccess: SenderAccess;
    } | null;
    live: LiveState;
    /** Loaded when the dialog to go live opens. */
    welcomeList?: WelcomeCandidate[];
};

/**
 * The end of setup: what was set up, and the first thing to do next. With a
 * mailbox that is CC'ing it; without one, a document, since the inbox has
 * nothing to show yet.
 */
export default function Done({ account, mailbox, live, welcomeList }: Props) {
    const t = useTranslations();
    const [goingLive, setGoingLive] = useState(false);
    const looks = useAccessLooks();
    const { auth } = usePage<{ auth: Auth }>().props;

    return (
        <SetupStep
            current="done"
            mailboxSkipped={mailbox === null}
            head={t('Howy is ready')}
            eyebrow={t('All set')}
            title={
                <Marker>
                    <WithHowy text={t('Howy is ready')} />
                </Marker>
            }
            description={
                mailbox
                    ? t(
                          'This is what you set up. All of it can change later in Settings, except the knowledge base language.',
                      )
                    : t(
                          'You skipped the mailbox, and that is fine: start with documents, and connect a mailbox once it is ready.',
                      )
            }
        >
            <ul className="flex flex-col overflow-hidden rounded-xl border border-cc-border">
                <SummaryRow
                    title={t('Workspace')}
                    detail={`${account.name} · ${account.language} · ${account.timezone}`}
                />
                <SummaryRow
                    title={t('Knowledge base language')}
                    detail={account.knowledgeLanguage}
                    aside={
                        <span className="cc-tag cc-tag-pending before:hidden">
                            <Lock className="size-3" strokeWidth={2.5} />
                            {t('Chosen once')}
                        </span>
                    }
                />
                {mailbox ? (
                    <SummaryRow
                        title={t('Mailbox')}
                        detail={
                            mailbox.publicDomain
                                ? mailbox.emailAddress
                                : `${mailbox.emailAddress} · ${t('Colleagues: :access', { access: looks[mailbox.domainAccess].label })}`
                        }
                        aside={
                            <span className="cc-tag cc-tag-decision">
                                {t('Live')}
                            </span>
                        }
                    />
                ) : (
                    <SummaryRow
                        skipped
                        title={t('Mailbox')}
                        detail={t(
                            'Not connected yet. The inbox reminds you until it is.',
                        )}
                        aside={
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                className="h-9"
                            >
                                <Link
                                    href={settings({
                                        query: { connect: 'mailbox' },
                                    })}
                                >
                                    {t('Connect mailbox')}
                                </Link>
                            </Button>
                        }
                    />
                )}
            </ul>

            {mailbox ? (
                <div className="cc-panel-dark flex flex-col gap-4 p-6 sm:p-7">
                    <p className="text-[11px] font-semibold tracking-[0.08em] text-cc-accent uppercase">
                        {t('Try it now')}
                    </p>
                    <h2 className="cc-subtitle">
                        <WithHowy text={t('CC Howy on your next thread')} />
                    </h2>
                    <dl className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-3.5 gap-y-2 rounded-xl border border-cc-dark-border bg-cc-dark-1 p-4 text-[13px] leading-[1.5]">
                        <dt className="text-cc-dark-text">{t('From')}</dt>
                        <dd className="truncate">{auth.user.email}</dd>
                        <dt className="text-cc-dark-text">{t('To')}</dt>
                        <dd>{t('A customer')}</dd>
                        <dt className="text-cc-dark-text">CC</dt>
                        <dd>
                            <span className="inline-block max-w-full truncate rounded-md bg-cc-accent px-2 py-px font-bold text-cc-ink">
                                {`${BRAND_NAME} <${mailbox.emailAddress}>`}
                            </span>
                        </dd>
                        <dt className="text-cc-dark-text">{t('Subject')}</dt>
                        <dd>{t('Re: Delivery window in March')}</dd>
                    </dl>
                    <p className="text-[14px] leading-[1.55] text-cc-dark-text">
                        <WithHowy
                            text={t(
                                'Howy proposes what the thread settles for your knowledge base, with a link back to the thread. Put Howy in To instead, and it answers the question.',
                            )}
                        />
                    </p>
                </div>
            ) : (
                <div className="cc-panel-dark flex flex-col gap-4 p-6 sm:p-7">
                    <p className="text-[11px] font-semibold tracking-[0.08em] text-cc-accent uppercase">
                        {t('Start without a mailbox')}
                    </p>
                    <h2 className="cc-subtitle">
                        {t('Upload a document to your knowledge base')}
                    </h2>
                    <p className="text-[14px] leading-[1.55] text-cc-dark-text">
                        <WithHowy
                            text={t(
                                'A manual, a price list, your terms: Howy reads it and writes the facts in :language, each one linked to the passage it came from.',
                                { language: account.knowledgeLanguage },
                            )}
                        />
                    </p>
                    <Link
                        href={documentRoutes.index({ query: { upload: '1' } })}
                        className="cc-btn-accent gap-2 self-start px-5 py-3 text-[14px]"
                    >
                        <Upload className="size-4" />
                        {t('Upload a document')}
                    </Link>
                </div>
            )}

            {mailbox && (
                <div className="flex flex-wrap items-center gap-4 rounded-xl border border-cc-border px-[18px] py-4">
                    <span className="flex min-w-[220px] flex-1 flex-col gap-0.5">
                        <span className="text-[15px] font-semibold">
                            {live.since !== null ? (
                                <WithHowy text={t('Howy is live')} />
                            ) : (
                                t('Ready for your colleagues?')
                            )}
                        </span>
                        <span className="text-[13px] leading-[1.5] text-cc-muted">
                            <WithHowy
                                text={
                                    live.since !== null
                                        ? t(
                                              'Colleagues get the welcome with their first mail to Howy, or when you add them.',
                                          )
                                        : t(
                                              'Put Howy live and it welcomes your colleagues. Until then it answers questions, but sends nothing of its own.',
                                          )
                                }
                            />
                        </span>
                    </span>
                    {live.since === null && (
                        <Button
                            variant="outline"
                            className="h-10"
                            onClick={() => setGoingLive(true)}
                        >
                            <Rocket />
                            <span>
                                <WithHowy text={t('Put Howy live')} />
                            </span>
                        </Button>
                    )}
                </div>
            )}

            <GoLiveDialog
                open={goingLive}
                onOpenChange={setGoingLive}
                candidates={welcomeList}
            />

            <div className="flex flex-wrap items-center gap-3">
                <Link
                    href={mailbox ? inbox() : knowledge()}
                    className="cc-btn-accent gap-2"
                    data-test="setup-done-primary"
                >
                    {mailbox ? t('Go to inbox') : t('Open knowledge base')}
                    <ArrowRight className="size-4" />
                </Link>
                <Button
                    asChild
                    variant="outline"
                    className="h-[50px] px-6 text-[15px]"
                >
                    <Link href={mailbox ? knowledge() : settings()}>
                        {mailbox
                            ? t('Open knowledge base')
                            : t('Open settings')}
                    </Link>
                </Button>
            </div>
        </SetupStep>
    );
}

function SummaryRow({
    title,
    detail,
    aside,
    skipped = false,
}: {
    title: string;
    detail: string;
    aside?: ReactNode;
    skipped?: boolean;
}) {
    return (
        <li
            className={
                skipped
                    ? 'flex flex-wrap items-center gap-3.5 border-b border-cc-border bg-cc-bg px-[18px] py-4 last:border-b-0'
                    : 'flex flex-wrap items-center gap-3.5 border-b border-cc-border px-[18px] py-4 last:border-b-0'
            }
        >
            {skipped ? (
                <span className="flex size-7 shrink-0 items-center justify-center rounded-full border-[1.5px] border-cc-border-strong bg-cc-panel text-cc-subtle">
                    <Minus className="size-3.5" strokeWidth={3} />
                </span>
            ) : (
                <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-cc-accent">
                    <Check className="size-[15px]" strokeWidth={3} />
                </span>
            )}
            <span className="flex min-w-[200px] flex-1 flex-col gap-0.5">
                <span className="text-[15px] font-semibold">{title}</span>
                <span className="text-[13px] break-words text-cc-muted">
                    {detail}
                </span>
            </span>
            {aside}
        </li>
    );
}
