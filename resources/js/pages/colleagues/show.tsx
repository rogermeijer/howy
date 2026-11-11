import { Form, Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { WithHowy } from '@/components/brand/howy-name';
import { PageHeader } from '@/components/cc/page-header';
import { Section } from '@/components/cc/section';
import {
    ColleagueStatusTag,
    useActivityLine,
} from '@/components/colleagues/colleague-status';
import { SentMailList } from '@/components/colleagues/sent-mail-list';
import InputError from '@/components/input-error';
import { InterpretationTag } from '@/components/mail/interpretation-tag';
import { shownEntry } from '@/components/mailboxes/sender-badge';
import {
    AccessTag,
    useAccessLooks,
} from '@/components/mailboxes/sender-access';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslations } from '@/hooks/use-translations';
import { ACCESSES } from '@/lib/mailbox-rules';
import { index, update, welcome } from '@/routes/colleagues';
import colleagueAccess from '@/routes/colleagues/access';
import { show as showEmail } from '@/routes/emails';
import type {
    ColleagueDetail,
    ColleagueEmail,
    ColleagueMailboxAccess,
    MailboxOption,
    SentMail,
} from '@/types';

type Props = {
    colleague: ColleagueDetail;
    access: ColleagueMailboxAccess[];
    emails: ColleagueEmail[];
    sent: SentMail[];
    mailboxes: MailboxOption[];
    /** Live, and they may get the welcome but have not had it. */
    canWelcome: boolean;
    /** The kinds of Howy's own mails to them that are on their way. */
    onTheirWay: string[];
};

export default function Colleague({
    colleague,
    access,
    emails,
    sent,
    mailboxes,
    canWelcome,
    onTheirWay,
}: Props) {
    const t = useTranslations();
    const formatDate = useFormatDate();
    const activity = useActivityLine();
    const [receivesMail, setReceivesMail] = useState(colleague.receivesMail);

    const date = (value: string | null) =>
        value === null ? null : formatDate(value, { dateStyle: 'medium' });

    // What their Howy mails have done so far, in order.
    const steps = [
        { kind: 'welcome', label: t('Welcome'), at: colleague.welcomedAt },
        {
            kind: 'how_it_works',
            label: t('How Howy works'),
            at: colleague.explainedAt,
        },
        {
            kind: 'first_save',
            label: t('Thanks for the first contribution'),
            at: colleague.firstSavedAt,
        },
        {
            kind: 'nudge',
            label: t('Reminder to share'),
            at: colleague.nudgedAt,
        },
    ];

    return (
        <>
            <Head title={colleague.name ?? colleague.email} />

            <div className="flex flex-col gap-7">
                <PageHeader
                    title={colleague.name ?? colleague.email}
                    breadcrumb={
                        <div className="cc-caption flex items-center gap-2">
                            <Link
                                href={index()}
                                className="text-cc-subtle transition-colors hover:text-cc-ink"
                            >
                                {t('Colleagues')}
                            </Link>
                            <span>/</span>
                            <span className="truncate font-medium text-cc-ink">
                                {colleague.name ?? colleague.email}
                            </span>
                        </div>
                    }
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            {colleague.email}
                            {colleague.user !== null && (
                                <span className="cc-tag cc-tag-action">
                                    {t('Member')}
                                </span>
                            )}
                        </span>
                    }
                />

                <div className="grid gap-7 lg:grid-cols-[minmax(0,1fr)_340px]">
                    <div className="flex flex-col gap-7">
                        <Section
                            title={t('What Howy does with their mail')}
                            description={
                                <WithHowy
                                    text={t(
                                        'Per mailbox: a rule of their own, or the rule for their domain.',
                                    )}
                                />
                            }
                        >
                            {access.length === 0 ? (
                                <p className="cc-caption">
                                    {t('No mailbox is connected yet.')}
                                </p>
                            ) : (
                                <ul className="flex flex-col divide-y divide-cc-border">
                                    {access.map((item) => (
                                        <MailboxAccessRow
                                            key={item.mailboxId}
                                            colleagueId={colleague.id}
                                            item={item}
                                        />
                                    ))}
                                </ul>
                            )}
                        </Section>

                        <Section
                            title={t('Activity')}
                            description={activity(colleague)}
                        >
                            {emails.length === 0 ? (
                                <p className="cc-caption">
                                    <WithHowy
                                        text={t(
                                            'No mail from them has reached Howy yet.',
                                        )}
                                    />
                                </p>
                            ) : (
                                <ul className="-mx-2 flex flex-col">
                                    {emails.map((email) => (
                                        <li key={email.id}>
                                            <Link
                                                href={showEmail(email.id)}
                                                className="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-[10px] px-2 py-2.5 transition-colors hover:bg-cc-bg"
                                            >
                                                <span className="min-w-0 flex-1 truncate text-[14.5px] font-medium">
                                                    {email.subject ||
                                                        t('(no subject)')}
                                                </span>
                                                <InterpretationTag
                                                    interpretation={
                                                        email.interpretation
                                                    }
                                                />
                                                <span className="cc-caption w-[100px] text-right">
                                                    {date(email.receivedAt)}
                                                </span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Section>

                        <Section
                            title={t('Mails from Howy')}
                            description={
                                <WithHowy
                                    text={t(
                                        'What Howy sent them, or held back, newest first.',
                                    )}
                                />
                            }
                            className="pb-2 lg:pb-3"
                        >
                            {sent.length === 0 ? (
                                <p className="cc-caption pb-4">
                                    <WithHowy
                                        text={t(
                                            'Howy has not sent them anything yet.',
                                        )}
                                    />
                                </p>
                            ) : (
                                <div className="-mx-6 lg:-mx-8">
                                    <SentMailList
                                        mails={sent}
                                        showRecipient={false}
                                    />
                                </div>
                            )}
                        </Section>
                    </div>

                    <div className="flex flex-col gap-7">
                        <Section title={t('Details')}>
                            <Form
                                {...update.form(colleague.id)}
                                options={{ preserveScroll: true }}
                                className="flex flex-col gap-5"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="name">
                                                {t('Name')}
                                            </Label>
                                            <Input
                                                id="name"
                                                name="name"
                                                defaultValue={
                                                    colleague.name ?? ''
                                                }
                                                className="h-11"
                                            />
                                            <InputError message={errors.name} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="home-mailbox">
                                                {t('Howy mails come from')}
                                            </Label>
                                            <Select
                                                name="home_mailbox_id"
                                                defaultValue={
                                                    colleague.homeMailboxId !==
                                                    null
                                                        ? String(
                                                              colleague.homeMailboxId,
                                                          )
                                                        : undefined
                                                }
                                            >
                                                <SelectTrigger
                                                    id="home-mailbox"
                                                    className="h-11 w-full"
                                                >
                                                    <SelectValue
                                                        placeholder={t(
                                                            'Choose a mailbox',
                                                        )}
                                                    />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {mailboxes.map(
                                                        (mailbox) => (
                                                            <SelectItem
                                                                key={mailbox.id}
                                                                value={String(
                                                                    mailbox.id,
                                                                )}
                                                            >
                                                                {
                                                                    mailbox.emailAddress
                                                                }
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={errors.home_mailbox_id}
                                            />
                                        </div>

                                        <label className="flex items-start gap-3 rounded-xl border border-cc-border px-3.5 py-3">
                                            <input
                                                type="hidden"
                                                name="receives_mail"
                                                value={receivesMail ? '1' : '0'}
                                            />
                                            <Checkbox
                                                checked={receivesMail}
                                                onCheckedChange={(on) =>
                                                    setReceivesMail(on === true)
                                                }
                                                className="mt-0.5 size-[18px] border-[1.5px] border-cc-border-strong bg-cc-panel"
                                            />
                                            <span className="flex flex-col gap-0.5">
                                                <span className="text-[14px] font-semibold">
                                                    {t('Receives Howy mails')}
                                                </span>
                                                <span className="cc-caption">
                                                    <WithHowy
                                                        text={t(
                                                            'Answers to their own questions always go out.',
                                                        )}
                                                    />
                                                </span>
                                            </span>
                                        </label>

                                        <Button
                                            type="submit"
                                            className="h-11 self-start px-[18px]"
                                            disabled={processing}
                                        >
                                            {t('Save')}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </Section>

                        <Section
                            title={t('Howy mails')}
                            action={
                                <ColleagueStatusTag status={colleague.status} />
                            }
                        >
                            <ol className="flex flex-col gap-2.5">
                                {steps.map((step) => (
                                    <li
                                        key={step.label}
                                        className="flex items-center justify-between gap-3 text-[14px]"
                                    >
                                        <span
                                            className={
                                                step.at === null
                                                    ? 'text-cc-subtle'
                                                    : 'font-medium'
                                            }
                                        >
                                            {step.label}
                                        </span>
                                        <span className="cc-caption shrink-0 whitespace-nowrap">
                                            {date(step.at) ??
                                                (onTheirWay.includes(step.kind)
                                                    ? t('On its way')
                                                    : t('Not yet'))}
                                        </span>
                                    </li>
                                ))}
                            </ol>
                            {canWelcome && (
                                <Form
                                    {...welcome.form(colleague.id)}
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            className="h-10"
                                            disabled={processing}
                                        >
                                            {t('Send the welcome mail now')}
                                        </Button>
                                    )}
                                </Form>
                            )}
                            {colleague.optedOutAt !== null && (
                                <p className="cc-caption">
                                    {t('Unsubscribed on :date.', {
                                        date: date(colleague.optedOutAt) ?? '',
                                    })}
                                </p>
                            )}
                            <p className="cc-caption">
                                {colleague.firstSeenAt !== null
                                    ? t('First mail to Howy on :date.', {
                                          date:
                                              date(colleague.firstSeenAt) ?? '',
                                      })
                                    : t('Has not mailed Howy yet.')}
                            </p>
                        </Section>
                    </div>
                </div>
            </div>
        </>
    );
}

/**
 * Their access on one mailbox: what it comes to, why, and a choice between
 * following the domain's rule and one of their own.
 */
function MailboxAccessRow({
    colleagueId,
    item,
}: {
    colleagueId: number;
    item: ColleagueMailboxAccess;
}) {
    const t = useTranslations();
    const looks = useAccessLooks();

    const because =
        item.own !== null
            ? t('Their own rule')
            : item.domain !== null
              ? t('Through the rule for :domain', {
                    domain: shownEntry(item.domain.domain),
                })
              : t('No rule covers them, so Howy ignores their mail');

    const set = (value: string) =>
        router.put(
            colleagueAccess.update.url({
                colleague: colleagueId,
                mailbox: item.mailboxId,
            }),
            { access: value },
            { preserveScroll: true },
        );

    return (
        <li className="flex flex-wrap items-center gap-x-4 gap-y-2 py-3.5 first:pt-0 last:pb-0">
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <span className="flex min-w-0 items-center gap-2">
                    <span className="truncate text-[14.5px] font-medium">
                        {item.emailAddress}
                    </span>
                    {item.status !== 'active' && (
                        <span className="cc-tag cc-tag-noise shrink-0">
                            {t('Not connected')}
                        </span>
                    )}
                </span>
                <span className="flex flex-wrap items-center gap-2">
                    <AccessTag access={item.access} />
                    <span className="text-[12.5px] text-cc-subtle">
                        {because}
                    </span>
                </span>
            </div>
            <Select value={item.own ?? 'default'} onValueChange={set}>
                <SelectTrigger
                    aria-label={t('What Howy does on :mailbox', {
                        mailbox: item.emailAddress,
                    })}
                    className="h-10 w-full sm:w-[230px]"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="default">
                        {item.domain !== null
                            ? t('Follow :domain', {
                                  domain: shownEntry(item.domain.domain),
                              })
                            : t('Follow the mailbox rules')}
                    </SelectItem>
                    {ACCESSES.map((access) => (
                        <SelectItem key={access} value={access}>
                            {looks[access].label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </li>
    );
}
