import { Form, Link, usePage } from '@inertiajs/react';
import { Check, Copy, Plus, Send, Trash2 } from 'lucide-react';
import { AdminShell, Chip } from '@/components/admin/admin-shell';
import { Flag } from '@/components/flag';
import { Pager } from '@/components/admin/pager';
import { Section } from '@/components/cc/section';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useClipboard } from '@/hooks/use-clipboard';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { destroy, index, send, store } from '@/routes/admin/beta';
import type {
    BetaInviteRow,
    BetaStatus,
    LocaleOption,
    Paginated,
} from '@/types';

type Props = {
    invites: Paginated<BetaInviteRow>;
    counts: Record<'all' | BetaStatus, number>;
    filters: { status: BetaStatus | null };
    locales: LocaleOption[];
};

const row =
    'grid grid-cols-[minmax(0,1fr)_auto] grid-rows-[auto_auto] items-center gap-x-3 gap-y-2.5 border-b border-cc-border px-6 py-4 last:border-b-0 md:grid-cols-[minmax(0,1.4fr)_10rem_minmax(0,7rem)_5.5rem] md:grid-rows-1 md:gap-x-5 md:gap-y-0';

const headerRow =
    'hidden md:grid md:grid-cols-[minmax(0,1.4fr)_10rem_minmax(0,7rem)_5.5rem] md:items-center md:gap-x-5 border-b border-cc-border px-6 py-3';

function BetaStatusChip({
    invite,
    label,
    tone,
}: {
    invite: BetaInviteRow;
    label: string;
    tone: 'neutral' | 'good' | 'info' | 'warn' | 'bad';
}) {
    const t = useTranslations();
    const formatDate = useFormatDate();

    const history: string[] = [];
    if (invite.created_at) {
        history.push(t('Asked :date', { date: formatDate(invite.created_at) }));
    }
    if (invite.invited_at) {
        history.push(
            t('Invited :date', { date: formatDate(invite.invited_at) }),
        );
    }
    if (invite.user) {
        history.push(t('Registered as :name', { name: invite.user.name }));
    }

    const chip = <Chip tone={tone}>{label}</Chip>;

    if (history.length === 0) {
        return chip;
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <span className="inline-flex w-fit cursor-default">{chip}</span>
            </TooltipTrigger>
            <TooltipContent className="flex max-w-[280px] flex-col gap-1 text-left">
                {history.map((line) => (
                    <span key={line}>{line}</span>
                ))}
            </TooltipContent>
        </Tooltip>
    );
}

export default function AdminBeta({
    invites,
    counts,
    filters,
    locales,
}: Props) {
    const t = useTranslations();
    const [copied, copy] = useClipboard();
    const { errors } = usePage().props;

    const statusLabel = (status: BetaStatus) =>
        ({
            pending: t('Waiting'),
            invited: t('Invited'),
            registered: t('Registered'),
        })[status];

    const statusTone = (status: BetaStatus) =>
        (({ pending: 'warn', invited: 'info', registered: 'good' }) as const)[
            status
        ];

    const tabs: { status: BetaStatus | null; label: string; count: number }[] =
        [
            { status: null, label: t('All'), count: counts.all },
            { status: 'pending', label: t('Waiting'), count: counts.pending },
            { status: 'invited', label: t('Invited'), count: counts.invited },
            {
                status: 'registered',
                label: t('Registered'),
                count: counts.registered,
            },
        ];

    return (
        <AdminShell
            title={t('Private beta')}
            description={t(
                'Everyone who asks for access gets a single-use invite code straight away. Nothing is sent until you send the invite.',
            )}
        >
            <Section title={t('Add someone')}>
                <Form
                    {...store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess={['email']}
                    className="flex flex-col gap-3 sm:flex-row sm:items-end"
                >
                    {({ processing, errors: formErrors }) => (
                        <>
                            <div className="grid flex-1 gap-2">
                                <Label htmlFor="email">
                                    {t('Email address')}
                                </Label>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    required
                                    placeholder={t('name@company.com')}
                                    className="h-11"
                                />
                                <InputError message={formErrors.email} />
                            </div>
                            <div className="grid gap-2 sm:w-44">
                                <Label htmlFor="locale">{t('Language')}</Label>
                                <Select
                                    name="locale"
                                    defaultValue={locales[0]?.value}
                                >
                                    <SelectTrigger
                                        id="locale"
                                        className="h-11 w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {locales.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button disabled={processing} className="h-11">
                                <Plus />
                                {t('Add')}
                            </Button>
                        </>
                    )}
                </Form>
            </Section>

            <nav className="flex flex-wrap gap-2" aria-label={t('Status')}>
                {tabs.map((tab) => (
                    <Link
                        key={tab.label}
                        href={index({
                            query: tab.status ? { status: tab.status } : {},
                        })}
                        preserveScroll
                        className={cn(
                            'cc-pill inline-flex items-center gap-2',
                            filters.status === tab.status && 'cc-pill-active',
                        )}
                    >
                        {tab.label}
                        <span className="tabular-nums opacity-70">
                            {tab.count}
                        </span>
                    </Link>
                ))}
            </nav>

            <InputError message={errors.invite} />

            <div className="cc-panel overflow-hidden">
                <div className={headerRow}>
                    <span className="cc-label">{t('Email address')}</span>
                    <span className="cc-label">{t('Invite code')}</span>
                    <span className="cc-label">{t('Status')}</span>
                    <span />
                </div>

                {invites.data.length === 0 && (
                    <p className="cc-caption px-6 py-10 text-center">
                        {t('Nobody here yet.')}
                    </p>
                )}

                {invites.data.map((invite) => {
                    const localeLabel = invite.locale
                        ? (locales.find(
                              (option) => option.value === invite.locale,
                          )?.label ?? invite.locale)
                        : null;

                    return (
                        <div key={invite.id} className={row}>
                            <div className="col-start-1 row-start-1 flex min-w-0 items-center gap-2 overflow-hidden md:col-auto md:row-auto">
                                <span className="cc-row-title min-w-0 flex-1 truncate">
                                    {invite.email}
                                </span>
                                {invite.locale ? (
                                    <span
                                        className="inline-flex shrink-0 items-center"
                                        title={localeLabel ?? undefined}
                                    >
                                        <span className="sr-only">
                                            {localeLabel}
                                        </span>
                                        <Flag
                                            locale={invite.locale}
                                            className="h-3 w-[18px] rounded-[2px]"
                                        />
                                    </span>
                                ) : (
                                    <span
                                        aria-hidden="true"
                                        className="shrink-0 text-[12px] font-semibold text-cc-subtle"
                                    >
                                        —
                                    </span>
                                )}
                            </div>

                            <button
                                type="button"
                                onClick={() => copy(invite.code)}
                                className="col-start-1 row-start-2 flex w-fit max-w-full cursor-pointer items-center gap-1.5 justify-self-start truncate font-mono text-[13px] tracking-[0.04em] hover:text-cc-accent-deep md:col-auto md:row-auto md:justify-self-auto"
                                title={t('Copy')}
                            >
                                <span className="truncate">{invite.code}</span>
                                {copied === invite.code ? (
                                    <Check className="size-3.5 text-cc-accent-deep" />
                                ) : (
                                    <Copy className="size-3.5 text-cc-subtle" />
                                )}
                            </button>

                            <span className="col-start-2 row-start-2 justify-self-end md:col-auto md:row-auto md:justify-self-auto">
                                <BetaStatusChip
                                    invite={invite}
                                    label={statusLabel(invite.status)}
                                    tone={statusTone(invite.status)}
                                />
                            </span>

                            <span className="col-start-2 row-start-1 flex items-center justify-self-end md:col-auto md:row-auto">
                                {invite.status !== 'registered' && (
                                    <Form
                                        {...send.form(invite.id)}
                                        options={{ preserveScroll: true }}
                                        onBefore={() =>
                                            invite.status === 'pending' ||
                                            confirm(
                                                t(
                                                    'Send the invite to :email again?',
                                                    {
                                                        email: invite.email,
                                                    },
                                                ),
                                            )
                                        }
                                    >
                                        {({ processing }) => {
                                            const sendLabel =
                                                invite.status === 'pending'
                                                    ? t('Send invite')
                                                    : t('Resend');

                                            return (
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Button
                                                            type="submit"
                                                            size="icon"
                                                            variant={
                                                                invite.status ===
                                                                'pending'
                                                                    ? 'default'
                                                                    : 'outline'
                                                            }
                                                            disabled={
                                                                processing
                                                            }
                                                            aria-label={
                                                                sendLabel
                                                            }
                                                        >
                                                            <Send />
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        {sendLabel}
                                                    </TooltipContent>
                                                </Tooltip>
                                            );
                                        }}
                                    </Form>
                                )}
                                <Form
                                    {...destroy.form(invite.id)}
                                    options={{ preserveScroll: true }}
                                    onBefore={() =>
                                        confirm(
                                            t(
                                                'Delete the request from :email? Its code stops working.',
                                                {
                                                    email: invite.email,
                                                },
                                            ),
                                        )
                                    }
                                >
                                    {({ processing }) => (
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button
                                                    type="submit"
                                                    size="icon"
                                                    variant="ghost"
                                                    disabled={processing}
                                                    aria-label={t('Delete')}
                                                    className="text-cc-pending-fg hover:bg-cc-pending-bg hover:text-cc-pending-fg"
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                {t('Delete')}
                                            </TooltipContent>
                                        </Tooltip>
                                    )}
                                </Form>
                            </span>
                        </div>
                    );
                })}
            </div>

            <Pager page={invites} />
        </AdminShell>
    );
}
