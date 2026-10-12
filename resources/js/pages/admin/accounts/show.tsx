import { Form, Link } from '@inertiajs/react';
import { AdminShell, BackLink } from '@/components/admin/admin-shell';
import { DeleteDialog } from '@/components/admin/delete-dialog';
import {
    formatCompact,
    formatCost,
    formatLatency,
    formatNumber,
} from '@/components/admin/format';
import { AddMemberForm, MembershipRows } from '@/components/admin/members-list';
import { Stat } from '@/components/admin/stat';
import { Section } from '@/components/cc/section';
import InputError from '@/components/input-error';
import { LocaleFields } from '@/components/locale-fields';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslations } from '@/hooks/use-translations';
import { destroy, index, update } from '@/routes/admin/accounts';
import { index as aiIndex } from '@/routes/admin/ai';
import { show as showUser } from '@/routes/admin/users';
import type {
    AdminMember,
    AiBreakdownRow,
    AiTotals,
    LocaleOption,
} from '@/types';

type Props = {
    account: {
        id: number;
        name: string;
        locale: string;
        timezone: string;
        created_at: string | null;
    };
    members: AdminMember[];
    usage: { totals: AiTotals; steps: AiBreakdownRow[] };
    locales: LocaleOption[];
    timezones: string[];
};

export default function AdminAccount({
    account,
    members,
    usage,
    locales,
    timezones,
}: Props) {
    const t = useTranslations();
    const formatDate = useFormatDate();

    return (
        <AdminShell
            title={account.name}
            description={
                account.created_at
                    ? t('Created :date', {
                          date: formatDate(account.created_at),
                      })
                    : undefined
            }
            breadcrumb={<BackLink href={index()}>{t('Accounts')}</BackLink>}
        >
            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <div className="flex flex-col gap-6">
                    <Section title={t('Account details')}>
                        <Form
                            {...update.form(account.id)}
                            options={{ preserveScroll: true }}
                            className="flex flex-col gap-5"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">
                                            {t('Account name')}
                                        </Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            defaultValue={account.name}
                                            required
                                            className="h-11"
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <LocaleFields
                                        locales={locales}
                                        timezones={timezones}
                                        locale={account.locale}
                                        timezone={account.timezone}
                                        errors={errors}
                                    />

                                    <Button
                                        disabled={processing}
                                        className="h-10 self-start"
                                    >
                                        {t('Save')}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </Section>

                    <Section
                        title={t('Members')}
                        description={t(
                            'Administrators manage the account, its mailboxes and its knowledge base.',
                        )}
                    >
                        <MembershipRows
                            empty={t('This account has no members.')}
                            rows={members.map((member) => ({
                                accountId: account.id,
                                userId: member.id,
                                title: member.name,
                                subtitle: member.email,
                                href: showUser(member.id),
                                isAdmin: member.is_admin,
                                isSuperAdmin: member.is_super_admin,
                            }))}
                        />
                        <AddMemberForm accountId={account.id} />
                    </Section>
                </div>

                <div className="flex flex-col gap-6">
                    <Section
                        title={t('AI usage, last 30 days')}
                        action={
                            <Button asChild variant="outline" size="sm">
                                <Link
                                    href={aiIndex({
                                        query: { account: account.id },
                                    })}
                                >
                                    {t('Open the AI log')}
                                </Link>
                            </Button>
                        }
                    >
                        <div className="grid grid-cols-2 gap-3">
                            <Stat
                                label={t('Cost')}
                                value={formatCost(usage.totals.cost_micros)}
                            />
                            <Stat
                                label={t('Calls')}
                                value={formatNumber(usage.totals.calls)}
                                hint={
                                    usage.totals.failed > 0
                                        ? t(':count failed', {
                                              count: usage.totals.failed,
                                          })
                                        : undefined
                                }
                            />
                            <Stat
                                label={t('Tokens')}
                                value={formatCompact(
                                    usage.totals.input_tokens +
                                        usage.totals.output_tokens,
                                )}
                                hint={t(':input in · :output out', {
                                    input: formatCompact(
                                        usage.totals.input_tokens,
                                    ),
                                    output: formatCompact(
                                        usage.totals.output_tokens,
                                    ),
                                })}
                            />
                            <Stat
                                label={t('Average latency')}
                                value={formatLatency(
                                    usage.totals.avg_latency_ms,
                                )}
                            />
                        </div>

                        {usage.steps.length > 0 && (
                            <div className="flex flex-col divide-y divide-cc-border rounded-xl border border-cc-border">
                                {usage.steps.map((step) => (
                                    <Link
                                        key={step.key}
                                        href={aiIndex({
                                            query: {
                                                account: account.id,
                                                ...step.filter,
                                            },
                                        })}
                                        className="flex items-center justify-between gap-3 px-4 py-2.5 text-sm hover:bg-cc-bg"
                                    >
                                        <span className="font-mono text-[13px]">
                                            {step.label ?? step.key}
                                        </span>
                                        <span className="tabular-nums">
                                            <span className="text-cc-subtle">
                                                {t(':count calls', {
                                                    count: formatNumber(
                                                        step.calls,
                                                    ),
                                                })}
                                                {' · '}
                                            </span>
                                            <span className="font-semibold">
                                                {formatCost(step.cost_micros)}
                                            </span>
                                        </span>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </Section>

                    <Section
                        tone="danger"
                        title={t('Delete account')}
                        description={t(
                            'Deletes the account with all of its mail, knowledge base and files, and disconnects its mailboxes. Its users stay.',
                        )}
                    >
                        <DeleteDialog
                            action={destroy.form(account.id)}
                            trigger={t('Delete account')}
                            title={t('Delete :name?', { name: account.name })}
                            description={t(
                                'This cannot be undone. Everything the account owns is deleted for good.',
                            )}
                            confirmName={account.name}
                        />
                    </Section>
                </div>
            </div>
        </AdminShell>
    );
}
