import { router, usePoll } from '@inertiajs/react';
import {
    AlertTriangle,
    ChevronDown,
    Download,
    Mail,
    MoreHorizontal,
    Plus,
    RefreshCw,
    ShieldCheck,
    X,
} from 'lucide-react';
import { type MouseEvent, useEffect, useId, useRef, useState } from 'react';
import MailboxController from '@/actions/App/Http/Controllers/Mailboxes/MailboxController';
import { WithHowy } from '@/components/brand/howy-name';
import { Section } from '@/components/cc/section';
import { ConnectMailboxDialog } from '@/components/mailboxes/connect-mailbox-dialog';
import { DisconnectMailboxDialog } from '@/components/mailboxes/disconnect-mailbox-dialog';
import { ImportMailboxDialog } from '@/components/mailboxes/import-mailbox-dialog';
import { ImportProgress } from '@/components/mailboxes/import-progress';
import { MailboxExplainerDialog } from '@/components/mailboxes/mailbox-explainer-dialog';
import {
    MailboxRulesPanel,
    useRulesDraft,
} from '@/components/mailboxes/mailbox-rules-panel';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslations } from '@/hooks/use-translations';
import { type MailboxRules, rulesOf } from '@/lib/mailbox-rules';
import { cn } from '@/lib/utils';
import { settings } from '@/routes';
import mailboxRoutes from '@/routes/mailboxes';
import type { Mailbox, MailFacts } from '@/types';

type Translate = ReturnType<typeof useTranslations>;

type Props = {
    mailboxes: Mailbox[];
    canManage: boolean;
    openConnect: boolean;
    /** Domains anyone can have an address on: never a rule of their own. */
    publicDomains: string[];
    /** The account's name, as the explanation of a mailbox says whose it is. */
    company: string;
    facts: MailFacts;
};

export function MailboxesSection({
    mailboxes,
    canManage,
    openConnect,
    publicDomains,
    company,
    facts,
}: Props) {
    const t = useTranslations();
    const [connecting, setConnecting] = useState(openConnect);
    const [explaining, setExplaining] = useState(false);
    const [explainedId, setExplainedId] = useState<number | null>(null);
    const [importing, setImporting] = useState<number | null>(null);
    const [disconnecting, setDisconnecting] = useState<Mailbox | null>(null);

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

    // Explained for everyone, not only for who manages the mailboxes: it says
    // what happens to their mail. Without a mailbox, an example one.
    const explained =
        mailboxes.find((mailbox) => mailbox.id === explainedId) ?? mailboxes[0];
    const example = t('howy@yourcompany.com');
    const explainedRules: MailboxRules = explained
        ? rulesOf(explained)
        : {
              address: example,
              domain: example.slice(example.indexOf('@') + 1),
              publicDomain: false,
              domainAccess: 'learn_answer',
              senders: [],
          };

    return (
        <Section
            title={t('Mailboxes')}
            description={
                <>
                    <WithHowy
                        text={t(
                            'New emails come in right away. Per mailbox you decide whom Howy learns from and whom it answers.',
                        )}
                    />{' '}
                    <button
                        type="button"
                        onClick={() => setExplaining(true)}
                        className="font-semibold text-cc-accent-deep hover:text-cc-ink hover:underline"
                    >
                        <WithHowy text={t('What does Howy do with mail?')} />
                    </button>
                </>
            }
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
                        <WithHowy
                            text={t(
                                'Connect a shared support or knowledge mailbox, so Howy processes every new email automatically.',
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
                <ul className="flex flex-col overflow-hidden rounded-xl border border-cc-border">
                    {mailboxes.map((mailbox) => (
                        <MailboxRow
                            key={mailbox.id}
                            mailbox={mailbox}
                            canManage={canManage}
                            publicDomains={publicDomains}
                            onImport={() => setImporting(mailbox.id)}
                            onDisconnect={() => setDisconnecting(mailbox)}
                        />
                    ))}
                </ul>
            )}

            <MailboxExplainerDialog
                open={explaining}
                onOpenChange={setExplaining}
                rules={explainedRules}
                company={company}
                facts={facts}
                note={
                    explained
                        ? undefined
                        : t(
                              'An example mailbox. Once a mailbox is connected, this follows its own rules.',
                          )
                }
                picker={
                    mailboxes.length > 1 && (
                        <Select
                            value={String(explained?.id)}
                            onValueChange={(value) =>
                                setExplainedId(Number(value))
                            }
                        >
                            <SelectTrigger
                                aria-label={t('Mailbox')}
                                className="h-10 w-full border-[1.5px] border-cc-border-strong bg-cc-panel sm:w-fit"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {mailboxes.map((mailbox) => (
                                    <SelectItem
                                        key={mailbox.id}
                                        value={String(mailbox.id)}
                                    >
                                        {mailbox.emailAddress}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )
                }
            />

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
                    <DisconnectMailboxDialog
                        mailbox={disconnecting}
                        onOpenChange={(open) => !open && setDisconnecting(null)}
                    />
                </>
            )}
        </Section>
    );
}

/**
 * Whose mail Howy reads, in one line: colleagues and how many exceptions,
 * or only the senders on the list.
 */
function readsSummary(mailbox: Mailbox, t: Translate): string {
    const colleagues =
        !mailbox.publicDomain && mailbox.domainAccess !== 'ignore';
    const exceptions = mailbox.senderRules.length;
    const readers = mailbox.senderRules.filter(
        (rule) => rule.access !== 'ignore',
    ).length;

    if (colleagues) {
        return exceptions === 0
            ? t('Reads colleagues')
            : exceptions === 1
              ? t('Reads colleagues and 1 exception')
              : t('Reads colleagues and :count exceptions', {
                    count: exceptions,
                });
    }

    if (readers === 0) {
        return t('Reads no one yet: put senders on the list');
    }

    return readers === 1
        ? t('Reads only the 1 sender on the list')
        : t('Reads only the :count senders on the list', { count: readers });
}

function MailboxRow({
    mailbox,
    canManage,
    publicDomains,
    onImport,
    onDisconnect,
}: {
    mailbox: Mailbox;
    canManage: boolean;
    publicDomains: string[];
    onImport: () => void;
    onDisconnect: () => void;
}) {
    const t = useTranslations();
    const formatDate = useFormatDate();
    const panelId = useId();
    const row = useRef<HTMLLIElement>(null);
    const [open, setOpen] = useState(false);
    const { draft, setDraft, changes, reset } = useRulesDraft(mailbox);
    const needsReauth = mailbox.status === 'needs_reauth';
    const reconnectUrl = mailboxRoutes.gmail.redirect.url();

    const details = [
        'Gmail',
        mailbox.lastMessageAt
            ? t('last email :date', {
                  date: formatDate(mailbox.lastMessageAt, {
                      dateStyle: 'medium',
                      timeStyle: 'short',
                  }),
              })
            : t('no emails yet'),
        t(':count emails stored', { count: mailbox.emailsCount }),
    ];

    // Anywhere on the card opens it, except its own buttons and menus (a menu
    // is portalled, but its clicks still bubble here) and selecting text.
    const toggleFromCard = (event: MouseEvent<HTMLDivElement>) => {
        const target = event.target as HTMLElement;

        if (
            !event.currentTarget.contains(target) ||
            target.closest('button, a, [role="menuitem"]') !== null ||
            (window.getSelection()?.toString() ?? '') !== ''
        ) {
            return;
        }

        setOpen(!open);
    };

    const dismissImportOffer = () =>
        router.delete(MailboxController.dismissImportOffer.url(mailbox.id), {
            preserveScroll: true,
        });

    return (
        <li
            ref={row}
            className="flex scroll-mt-20 flex-col border-b border-cc-border last:border-b-0"
        >
            <div
                onClick={toggleFromCard}
                className={cn(
                    'flex cursor-pointer flex-wrap items-center gap-4 py-[18px] pr-4 pl-5 transition-colors hover:bg-cc-bg/60',
                    open && 'bg-cc-bg',
                )}
            >
                <div
                    className={cn(
                        'flex size-10 shrink-0 items-center justify-center rounded-full',
                        needsReauth
                            ? 'bg-cc-pending-bg text-cc-pending-fg'
                            : open
                              ? 'bg-cc-panel text-cc-ink'
                              : 'bg-cc-raised text-cc-ink',
                    )}
                >
                    {needsReauth ? (
                        <AlertTriangle className="size-5" />
                    ) : (
                        <Mail className="size-5" />
                    )}
                </div>

                <div className="flex min-w-0 flex-1 flex-col gap-[3px]">
                    <div className="flex flex-wrap items-center gap-2.5">
                        <span className="truncate text-[15px] font-semibold">
                            {mailbox.emailAddress}
                        </span>
                        {!needsReauth && (
                            <span className="cc-tag cc-tag-decision">
                                {t('Live')}
                            </span>
                        )}
                        {changes > 0 && !open && (
                            <span className="cc-tag cc-tag-pending">
                                {t('Not saved')}
                            </span>
                        )}
                    </div>
                    {needsReauth ? (
                        <span className="text-[13px] leading-[1.5] font-semibold text-cc-pending-fg">
                            {t(
                                'Connection expired · new emails are not coming in',
                            )}
                        </span>
                    ) : (
                        <span className="cc-caption">
                            {details.join(' · ')}
                        </span>
                    )}
                    {!open && (
                        <span className="text-[13px] leading-[1.5]">
                            {readsSummary(mailbox, t)}
                        </span>
                    )}
                </div>

                <div className="flex items-center gap-1">
                    {canManage && needsReauth && (
                        <Button
                            asChild
                            className="mr-1.5 h-10 bg-cc-ink text-cc-bg hover:bg-cc-ink/90"
                        >
                            <a href={reconnectUrl}>{t('Reconnect')}</a>
                        </Button>
                    )}

                    {canManage && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('More actions')}
                                    className="size-10 text-cc-subtle"
                                >
                                    <MoreHorizontal className="size-[18px]" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                align="end"
                                className="w-64 rounded-[14px] border-cc-border p-1.5 shadow-[0_12px_32px_rgba(13,27,30,0.12)]"
                            >
                                {!needsReauth && (
                                    <>
                                        <DropdownMenuItem
                                            onSelect={onImport}
                                            className="rounded-[10px] px-2.5 py-2 font-semibold"
                                        >
                                            <Download />
                                            {t('Import older mail…')}
                                        </DropdownMenuItem>
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
                                            className="rounded-[10px] px-2.5 py-2 font-semibold"
                                        >
                                            <RefreshCw />
                                            {t('Fetch new mail')}
                                        </DropdownMenuItem>
                                    </>
                                )}
                                <DropdownMenuItem
                                    asChild
                                    className="rounded-[10px] px-2.5 py-2 font-semibold"
                                >
                                    <a href={reconnectUrl}>
                                        <ShieldCheck />
                                        {t('Sign in to Google again')}
                                    </a>
                                </DropdownMenuItem>
                                <DropdownMenuSeparator className="mx-1.5 my-1 bg-cc-border" />
                                {/* Not variant="destructive": in this theme
                                    --destructive-foreground is white, meant for
                                    filled buttons, so the item would vanish. */}
                                <DropdownMenuItem
                                    onSelect={onDisconnect}
                                    className="rounded-[10px] px-2.5 py-2 font-semibold text-cc-pending-fg focus:bg-cc-pending-bg focus:text-cc-pending-fg [&_svg]:!text-cc-pending-fg"
                                >
                                    <X />
                                    {t('Disconnect')}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}

                    <Button
                        variant="ghost"
                        size="icon"
                        aria-expanded={open}
                        aria-controls={panelId}
                        aria-label={
                            open ? t('Hide the rules') : t('Show the rules')
                        }
                        className="size-10"
                        onClick={() => setOpen(!open)}
                    >
                        <ChevronDown
                            className={cn(
                                'size-5 transition-transform',
                                open && 'rotate-180',
                            )}
                        />
                    </Button>
                </div>
            </div>

            {/* Full width, under the header: the card's footer when folded,
                a section of its own above the rules when open. Icon and text
                line up with the mailbox's own. */}
            {mailbox.importOffered && (
                <div className="flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-cc-border bg-cc-wash py-3 pr-4 pl-5">
                    <span className="flex w-10 shrink-0 justify-center">
                        <Download className="size-[18px] text-cc-accent-deep" />
                    </span>
                    <span className="min-w-[200px] flex-1 text-[13px] leading-[1.5]">
                        <WithHowy
                            text={t(
                                'Howy reads every new email from now on. Add older mail to Howy as well? It is kept, not processed.',
                            )}
                        />
                    </span>
                    <div className="flex items-center gap-1">
                        <Button
                            variant="outline"
                            className="h-[34px] border-[1.5px] border-cc-accent-deep bg-cc-panel px-3.5 text-[13px] font-bold text-cc-accent-deep hover:bg-cc-accent-tint hover:text-cc-accent-deep"
                            onClick={onImport}
                        >
                            {t('Import…')}
                        </Button>
                        <Button
                            variant="ghost"
                            className="h-[34px] px-2.5 text-[13px] text-cc-subtle"
                            onClick={dismissImportOffer}
                        >
                            {t('No need')}
                        </Button>
                    </div>
                </div>
            )}

            {mailbox.import && (
                <ImportProgress
                    progress={mailbox.import}
                    className="border-t border-cc-border bg-cc-bg py-3 pr-5 pl-[76px]"
                />
            )}

            {open && (
                <MailboxRulesPanel
                    id={panelId}
                    mailbox={mailbox}
                    draft={draft}
                    setDraft={setDraft}
                    changes={changes}
                    reset={reset}
                    canManage={canManage}
                    publicDomains={publicDomains}
                />
            )}
        </li>
    );
}
