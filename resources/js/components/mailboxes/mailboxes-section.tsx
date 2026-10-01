import { router, usePoll } from '@inertiajs/react';
import {
    AlertTriangle,
    Download,
    Mail,
    MessageSquareReply,
    MoreHorizontal,
    Plus,
    RefreshCw,
    ShieldCheck,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import MailboxController from '@/actions/App/Http/Controllers/Mailboxes/MailboxController';
import { Section } from '@/components/cc/section';
import { WithCcLogo } from '@/components/cc/with-cc-logo';
import { ConnectMailboxDialog } from '@/components/mailboxes/connect-mailbox-dialog';
import { DisconnectMailboxDialog } from '@/components/mailboxes/disconnect-mailbox-dialog';
import { ImportMailboxDialog } from '@/components/mailboxes/import-mailbox-dialog';
import { SendPolicyDialog } from '@/components/mailboxes/send-policy-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslations } from '@/hooks/use-translations';
import { settings } from '@/routes';
import mailboxRoutes from '@/routes/mailboxes';
import type { LocaleOption, Mailbox } from '@/types';

type Props = {
    mailboxes: Mailbox[];
    canManage: boolean;
    openConnect: boolean;
    sendPolicies: LocaleOption[];
};

export function MailboxesSection({
    mailboxes,
    canManage,
    openConnect,
    sendPolicies,
}: Props) {
    const t = useTranslations();
    const [connecting, setConnecting] = useState(openConnect);
    const [importing, setImporting] = useState<number | null>(null);
    const [disconnecting, setDisconnecting] = useState<Mailbox | null>(null);
    const [replySettings, setReplySettings] = useState<Mailbox | null>(null);

    // Opened from the inbox banner via ?connect=mailbox: drop the query so a
    // reload or a later visit does not reopen it.
    useEffect(() => {
        if (openConnect) {
            router.replace({
                url: settings.url(),
                preserveState: true,
                preserveScroll: true,
            });
        }
    }, [openConnect]);

    const importRunning = mailboxes.some((mailbox) => mailbox.import !== null);

    // Keep the progress strip moving while an import runs.
    const { start, stop } = usePoll(
        3000,
        { only: ['mailboxes'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (importRunning) {
            start();
        } else {
            stop();
        }
    }, [importRunning, start, stop]);

    const active = mailboxes.filter((mailbox) => mailbox.status === 'active');

    return (
        <Section
            title={t('Mailboxes')}
            description={t(
                'New emails come in right away through Gmail push notifications. You import existing emails per mailbox.',
            )}
            action={
                canManage &&
                mailboxes.length > 0 && (
                    <Button
                        className="h-10"
                        onClick={() => setConnecting(true)}
                    >
                        <Plus strokeWidth={2.25} />
                        {t('Mailbox')}
                    </Button>
                )
            }
        >
            {mailboxes.length === 0 ? (
                <div className="flex flex-col items-center gap-3 rounded-xl border-[1.5px] border-dashed border-cc-border-strong px-6 py-10 text-center">
                    <div className="flex size-14 items-center justify-center rounded-2xl bg-cc-accent-tint text-cc-accent-deep">
                        <Mail className="size-[26px]" />
                    </div>
                    <p className="text-[15px] font-semibold">
                        {t('No mailbox connected yet')}
                    </p>
                    <p className="cc-caption max-w-[400px]">
                        <WithCcLogo
                            text={t(
                                'Connect a shared support or knowledge mailbox, so [cc]: processes every new email automatically.',
                            )}
                        />
                    </p>
                    {canManage ? (
                        <Button
                            className="mt-1 h-10"
                            onClick={() => setConnecting(true)}
                        >
                            <Plus strokeWidth={2.25} />
                            {t('Mailbox')}
                        </Button>
                    ) : (
                        <p className="cc-caption">
                            {t(
                                'Only an administrator of this account can connect a mailbox.',
                            )}
                        </p>
                    )}
                </div>
            ) : (
                <ul className="flex flex-col rounded-xl border border-cc-border">
                    {mailboxes.map((mailbox) => (
                        <MailboxRow
                            key={mailbox.id}
                            mailbox={mailbox}
                            canManage={canManage}
                            sendPolicies={sendPolicies}
                            onImport={() => setImporting(mailbox.id)}
                            onReplySettings={() => setReplySettings(mailbox)}
                            onDisconnect={() => setDisconnecting(mailbox)}
                        />
                    ))}
                </ul>
            )}

            <p className="flex items-center gap-2.5 text-[13px] text-cc-subtle">
                <ShieldCheck className="size-4 shrink-0" />
                <span>
                    <WithCcLogo
                        text={t(
                            '[cc]: only replies as the reply settings of a mailbox allow, and never changes or deletes anything in the mailbox.',
                        )}
                    />
                </span>
            </p>

            {canManage && (
                <>
                    <ConnectMailboxDialog
                        open={connecting}
                        onOpenChange={setConnecting}
                    />
                    <ImportMailboxDialog
                        mailboxes={active}
                        mailboxId={importing}
                        onOpenChange={(open) => !open && setImporting(null)}
                    />
                    <SendPolicyDialog
                        mailbox={replySettings}
                        policies={sendPolicies}
                        onOpenChange={(open) => !open && setReplySettings(null)}
                    />
                    <DisconnectMailboxDialog
                        mailbox={disconnecting}
                        onOpenChange={(open) => !open && setDisconnecting(null)}
                    />
                </>
            )}
        </Section>
    );
}

function MailboxRow({
    mailbox,
    canManage,
    sendPolicies,
    onImport,
    onReplySettings,
    onDisconnect,
}: {
    mailbox: Mailbox;
    canManage: boolean;
    sendPolicies: LocaleOption[];
    onImport: () => void;
    onReplySettings: () => void;
    onDisconnect: () => void;
}) {
    const t = useTranslations();
    const formatDate = useFormatDate();
    const needsReauth = mailbox.status === 'needs_reauth';
    const reconnectUrl = mailboxRoutes.gmail.redirect.url();

    const details = [
        'Gmail',
        needsReauth
            ? t('new emails are not coming in')
            : mailbox.lastMessageAt
              ? t('last email :date', {
                    date: formatDate(mailbox.lastMessageAt, {
                        dateStyle: 'medium',
                        timeStyle: 'short',
                    }),
                })
              : t('no emails yet'),
        t(':count emails stored', { count: mailbox.emailsCount }),
        t('replies: :policy', {
            policy:
                sendPolicies
                    .find((policy) => policy.value === mailbox.sendPolicy)
                    ?.label.toLowerCase() ?? mailbox.sendPolicy,
        }),
    ];

    return (
        <li className="flex flex-col border-b border-cc-border last:border-b-0">
            <div className="flex flex-wrap items-center gap-4 px-5 py-[18px]">
                <div
                    className={
                        needsReauth
                            ? 'flex size-10 shrink-0 items-center justify-center rounded-[10px] bg-cc-pending-bg text-cc-pending-fg'
                            : 'flex size-10 shrink-0 items-center justify-center rounded-[10px] bg-cc-raised text-cc-ink'
                    }
                >
                    {needsReauth ? (
                        <AlertTriangle className="size-5" />
                    ) : (
                        <Mail className="size-5" />
                    )}
                </div>

                <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                    <div className="flex flex-wrap items-center gap-2.5">
                        <span className="truncate text-[15px] font-semibold">
                            {mailbox.emailAddress}
                        </span>
                        {needsReauth ? (
                            <span className="cc-tag cc-tag-pending">
                                {t('Needs reconnecting')}
                            </span>
                        ) : (
                            <span className="cc-tag cc-tag-decision">
                                {t('Live')}
                            </span>
                        )}
                    </div>
                    <div className="cc-caption">{details.join(' · ')}</div>
                </div>

                {canManage && (
                    <div className="flex items-center gap-2.5">
                        {needsReauth ? (
                            <Button asChild className="h-10">
                                <a href={reconnectUrl}>{t('Reconnect')}</a>
                            </Button>
                        ) : (
                            <Button
                                variant="outline"
                                className="h-10 border-[1.5px] border-cc-border-strong"
                                onClick={onImport}
                            >
                                <Download />
                                {t('Import')}
                            </Button>
                        )}

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    aria-label={t('More actions')}
                                    className="size-10 border-[1.5px] border-cc-border-strong"
                                >
                                    <MoreHorizontal className="size-[18px]" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                align="end"
                                className="w-56 rounded-xl p-1.5"
                            >
                                {!needsReauth && (
                                    <DropdownMenuItem
                                        onSelect={() =>
                                            router.post(
                                                MailboxController.sync.url(
                                                    mailbox.id,
                                                ),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <RefreshCw />
                                        {t('Sync now')}
                                    </DropdownMenuItem>
                                )}
                                <DropdownMenuItem onSelect={onReplySettings}>
                                    <MessageSquareReply />
                                    {t('Reply settings')}
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <a href={reconnectUrl}>
                                        <ShieldCheck />
                                        {t('Sign in again')}
                                    </a>
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                {/* Not variant="destructive": in this theme
                                    --destructive-foreground is white, meant for
                                    filled buttons, so the item would vanish. */}
                                <DropdownMenuItem
                                    onSelect={onDisconnect}
                                    className="font-semibold text-cc-action-fg focus:bg-cc-action-bg focus:text-cc-action-fg [&_svg]:!text-cc-action-fg"
                                >
                                    <X />
                                    {t('Disconnect')}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                )}
            </div>

            {mailbox.import && (
                <div className="mx-5 mb-[18px] flex flex-col gap-2 rounded-[10px] bg-cc-bg px-3.5 py-3 sm:ml-[76px]">
                    <div className="flex justify-between text-[13px]">
                        <span className="font-semibold">
                            {t('Import running')}
                        </span>
                        <span className="text-cc-subtle">
                            {t(':processed of :total emails', {
                                processed: mailbox.import.processed,
                                total: mailbox.import.total,
                            })}
                        </span>
                    </div>
                    <div
                        role="progressbar"
                        aria-label={t('Import progress')}
                        aria-valuemin={0}
                        aria-valuemax={mailbox.import.total}
                        aria-valuenow={mailbox.import.processed}
                        className="h-1.5 overflow-hidden rounded-full bg-cc-raised"
                    >
                        <div
                            className="h-1.5 rounded-full bg-cc-accent transition-[width]"
                            style={{
                                width: `${Math.round((mailbox.import.processed / Math.max(1, mailbox.import.total)) * 100)}%`,
                            }}
                        />
                    </div>
                </div>
            )}
        </li>
    );
}
