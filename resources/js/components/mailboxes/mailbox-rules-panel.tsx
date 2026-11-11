import { Form } from '@inertiajs/react';
import { List, Users } from 'lucide-react';
import { type ReactNode, useEffect, useMemo, useState } from 'react';
import MailboxController from '@/actions/App/Http/Controllers/Mailboxes/MailboxController';
import { WithHowy } from '@/components/brand/howy-name';
import InputError from '@/components/input-error';
import { SenderAddField } from '@/components/mailboxes/sender-add-field';
import { AccessPicker, AccessTag } from '@/components/mailboxes/sender-access';
import { SenderGroups } from '@/components/mailboxes/sender-groups';
import { SendersSheet } from '@/components/mailboxes/senders-sheet';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { type MailboxRules, rulesOf } from '@/lib/mailbox-rules';
import type { Mailbox, SenderAccess } from '@/types';

/**
 * How many changes lie between the saved rules and the draft: the colleagues'
 * access, and every sender added, removed or set to something else.
 */
function changesBetween(saved: MailboxRules, draft: MailboxRules): number {
    const before = new Map(saved.senders.map((rule) => [rule.entry, rule]));
    let changes =
        !saved.publicDomain && saved.domainAccess !== draft.domainAccess
            ? 1
            : 0;

    for (const rule of draft.senders) {
        const was = before.get(rule.entry);

        changes +=
            was?.access === rule.access &&
            Boolean(was.createsColleagues) === Boolean(rule.createsColleagues)
                ? 0
                : 1;
    }

    return changes + removedFrom(saved, draft).length;
}

/**
 * The senders saved but no longer in the draft. Only these are removed on
 * saving: a rule added elsewhere since the page loaded stays.
 */
function removedFrom(saved: MailboxRules, draft: MailboxRules): string[] {
    return saved.senders
        .filter(
            (rule) => !draft.senders.some((kept) => kept.entry === rule.entry),
        )
        .map((rule) => rule.entry);
}

/**
 * The rules of one mailbox as they are being edited, kept by its card so that
 * folding the card away loses nothing. Saved rules that change (a save, or
 * another administrator) replace the draft; the same rules polled back while
 * an import runs do not.
 */
export function useRulesDraft(mailbox: Mailbox) {
    const saved = useMemo(() => rulesOf(mailbox), [mailbox]);
    const savedKey = JSON.stringify(saved);
    const [draft, setDraft] = useState<MailboxRules>(saved);

    useEffect(() => {
        setDraft(JSON.parse(savedKey) as MailboxRules);
    }, [savedKey]);

    return {
        draft,
        setDraft,
        changes: changesBetween(JSON.parse(savedKey) as MailboxRules, draft),
        reset: () => setDraft(JSON.parse(savedKey) as MailboxRules),
    };
}

type Props = {
    mailbox: Mailbox;
    draft: MailboxRules;
    setDraft: (rules: MailboxRules) => void;
    changes: number;
    reset: () => void;
    canManage: boolean;
    publicDomains: string[];
    id: string;
};

/**
 * Whose mail Howy reads on a mailbox, opened from its card: colleagues on its
 * own domain, and the exceptions on top of that, an address before its
 * domain. Everyone else is ignored. An administrator edits it; everyone else
 * sees it.
 */
export function MailboxRulesPanel({
    mailbox,
    draft,
    setDraft,
    changes,
    reset,
    canManage,
    publicDomains,
    id,
}: Props) {
    const t = useTranslations();
    const [sheet, setSheet] = useState<SenderAccess | 'all' | null>(null);

    const set = (rules: Partial<MailboxRules>) =>
        setDraft({ ...draft, ...rules });
    const readers = draft.senders.filter((rule) => rule.access !== 'ignore');

    const body = (errors: Record<string, string>, processing: boolean) => (
        <>
            <Group title={t('Whose mail Howy reads')}>
                {mailbox.publicDomain ? (
                    <p className="cc-caption">
                        <WithHowy
                            text={t(
                                'Anyone can have an address at @:domain, so Howy reads only the senders among the exceptions.',
                                { domain: mailbox.domain },
                            )}
                        />
                    </p>
                ) : (
                    <>
                        <div className="flex flex-wrap items-center gap-3 rounded-xl border border-cc-border py-3 pr-3 pl-3.5">
                            <Users className="size-[18px] shrink-0 text-cc-subtle" />
                            <div className="flex min-w-0 flex-1 flex-col gap-px">
                                <span className="text-[14px] font-semibold">
                                    {t('Colleagues')}
                                </span>
                                <span className="text-[12.5px] leading-[1.45] text-cc-subtle">
                                    {t('Everyone with an address at @:domain', {
                                        domain: mailbox.domain,
                                    })}
                                </span>
                            </div>
                            {canManage ? (
                                <AccessPicker
                                    value={draft.domainAccess}
                                    onChange={(access) =>
                                        set({ domainAccess: access })
                                    }
                                    label={t('Colleagues')}
                                />
                            ) : (
                                <AccessTag access={draft.domainAccess} />
                            )}
                        </div>
                        <p className="text-[12.5px] leading-[1.45] text-cc-subtle">
                            <WithHowy
                                text={t(
                                    'Howy ignores everyone else. To have someone else read after all, add them to the exceptions.',
                                )}
                            />
                        </p>
                    </>
                )}
                <InputError message={errors.domain_access} />
            </Group>

            <Group
                title={t('Exceptions · :count', {
                    count: draft.senders.length,
                })}
                caption={
                    mailbox.publicDomain
                        ? t(
                              'Whom Howy reads, or explicitly not. An address comes before its domain.',
                          )
                        : t(
                              'Whom Howy reads besides colleagues, or explicitly not. An address comes before its domain.',
                          )
                }
                action={
                    // Beyond what the groups show at once, a way to all of them.
                    draft.senders.length > 3 && (
                        <Button
                            type="button"
                            variant="ghost"
                            className="h-8 px-2.5 text-[13px] text-cc-subtle"
                            onClick={() => setSheet('all')}
                        >
                            <List />
                            {t('Show all :count', {
                                count: draft.senders.length,
                            })}
                        </Button>
                    )
                }
            >
                {canManage && (
                    <SenderAddField
                        rules={draft}
                        onChange={(senders) => set({ senders })}
                        publicDomains={publicDomains}
                    />
                )}
                {mailbox.publicDomain && readers.length === 0 && (
                    <p className="rounded-xl bg-cc-pending-bg px-3.5 py-2.5 text-[13px] font-medium text-cc-pending-fg">
                        <WithHowy
                            text={t(
                                'Without exceptions, Howy reads no one at this address.',
                            )}
                        />
                    </p>
                )}
                <SenderGroups
                    senders={draft.senders}
                    onChange={
                        canManage ? (senders) => set({ senders }) : undefined
                    }
                    errors={errors}
                    onShowAll={setSheet}
                />
                <InputError
                    message={
                        errors.sender_rules ??
                        Object.entries(errors).find(([key]) =>
                            key.startsWith('sender_rules.'),
                        )?.[1]
                    }
                />
            </Group>

            {canManage && changes > 0 && (
                <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-2 rounded-2xl bg-cc-ink py-2.5 pr-2.5 pl-4 text-cc-bg sm:rounded-full">
                    <span className="text-[13px] font-semibold">
                        {changes === 1
                            ? t('1 change, not saved yet')
                            : t(':count changes, not saved yet', {
                                  count: changes,
                              })}
                    </span>
                    <div className="flex items-center gap-1.5">
                        <Button
                            type="button"
                            variant="ghost"
                            className="h-9 px-3.5 text-[13px] text-cc-bg hover:bg-cc-bg/10 hover:text-cc-bg"
                            disabled={processing}
                            onClick={reset}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button
                            type="submit"
                            className="h-9 px-[18px] text-[13px] font-bold"
                            disabled={processing}
                        >
                            {t('Save')}
                        </Button>
                    </div>
                </div>
            )}

            <SendersSheet
                open={sheet !== null}
                onOpenChange={(open) => !open && setSheet(null)}
                address={mailbox.emailAddress}
                senders={draft.senders}
                onChange={canManage ? (senders) => set({ senders }) : undefined}
                filter={sheet ?? 'all'}
            />
        </>
    );

    return (
        <div id={id} className="border-t border-cc-border px-5 py-6">
            {canManage ? (
                <Form
                    {...MailboxController.updateRules.form(mailbox.id)}
                    options={{ preserveScroll: true }}
                    className="flex flex-col gap-7"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="domain_access"
                                value={draft.domainAccess}
                            />
                            {draft.senders.map((rule, index) => (
                                <span key={rule.entry} hidden>
                                    <input
                                        type="hidden"
                                        name={`sender_rules[${index}][entry]`}
                                        value={rule.entry}
                                    />
                                    <input
                                        type="hidden"
                                        name={`sender_rules[${index}][access]`}
                                        value={rule.access}
                                    />
                                    {rule.createsColleagues !== undefined &&
                                        !rule.entry.includes('@') && (
                                            <input
                                                type="hidden"
                                                name={`sender_rules[${index}][creates_colleagues]`}
                                                value={
                                                    rule.createsColleagues
                                                        ? '1'
                                                        : '0'
                                                }
                                            />
                                        )}
                                </span>
                            ))}
                            {removedFrom(rulesOf(mailbox), draft).map(
                                (entry, index) => (
                                    <input
                                        key={entry}
                                        type="hidden"
                                        name={`removed_rules[${index}]`}
                                        value={entry}
                                    />
                                ),
                            )}
                            {body(errors, processing)}
                        </>
                    )}
                </Form>
            ) : (
                <div className="flex flex-col gap-7">{body({}, false)}</div>
            )}
        </div>
    );
}

function Group({
    title,
    caption,
    action,
    children,
}: {
    title: string;
    caption?: string;
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="flex flex-col gap-3">
            <div className="flex flex-col gap-0.5">
                <div className="flex min-h-8 items-center justify-between gap-3">
                    <h4 className="cc-label">{title}</h4>
                    {action}
                </div>
                {caption && (
                    <p className="text-[12.5px] leading-[1.45] text-cc-subtle">
                        <WithHowy text={caption} />
                    </p>
                )}
            </div>
            {children}
        </section>
    );
}
