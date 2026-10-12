import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { AdminShell } from '@/components/admin/admin-shell';
import { formatCost, formatNumber } from '@/components/admin/format';
import { Pager } from '@/components/admin/pager';
import { SearchForm } from '@/components/admin/search-form';
import { Button } from '@/components/ui/button';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslations } from '@/hooks/use-translations';
import { create, index, show } from '@/routes/admin/accounts';
import type { AdminAccountRow, Paginated } from '@/types';

type Props = {
    accounts: Paginated<AdminAccountRow>;
    filters: { q: string };
};

const grid =
    'lg:grid lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_90px_130px_120px] lg:items-center lg:gap-5';

export default function AdminAccounts({ accounts, filters }: Props) {
    const t = useTranslations();
    const formatDate = useFormatDate();

    return (
        <AdminShell
            title={t('Accounts')}
            description={t(':count accounts on the platform.', {
                count: accounts.total,
            })}
            actions={
                <Button asChild className="h-10">
                    <Link href={create()}>
                        <Plus />
                        {t('New account')}
                    </Link>
                </Button>
            }
        >
            <SearchForm
                action={index.form()}
                value={filters.q}
                placeholder={t('Search accounts')}
            />

            <div className="cc-panel overflow-hidden">
                <div
                    className={`${grid} hidden border-b border-cc-border px-6 py-3`}
                >
                    <span className="cc-label">{t('Account')}</span>
                    <span className="cc-label">{t('Administrators')}</span>
                    <span className="cc-label">{t('Members')}</span>
                    <span className="cc-label text-right">
                        {t('AI, 30 days')}
                    </span>
                    <span className="cc-label text-right">{t('Created')}</span>
                </div>

                {accounts.data.length === 0 && (
                    <p className="cc-caption px-6 py-10 text-center">
                        {t('No accounts found.')}
                    </p>
                )}

                {accounts.data.map((account) => (
                    <Link
                        key={account.id}
                        href={show(account.id)}
                        className={`${grid} flex flex-col gap-1 border-b border-cc-border px-6 py-4 transition-colors last:border-b-0 hover:bg-cc-bg`}
                    >
                        <span className="cc-row-title truncate">
                            {account.name}
                            <span className="ml-2 text-[12px] font-semibold text-cc-subtle uppercase">
                                {account.locale}
                            </span>
                        </span>
                        <span className="cc-caption truncate">
                            {account.admins.join(', ') || '—'}
                        </span>
                        <span className="cc-caption tabular-nums">
                            <span className="lg:hidden">{t('Members')}: </span>
                            {account.members_count}
                        </span>
                        <span className="text-[13px] tabular-nums lg:text-right">
                            <span className="font-semibold">
                                {formatCost(account.ai_cost_micros)}
                            </span>
                            <span className="text-cc-subtle">
                                {' · '}
                                {t(':count calls', {
                                    count: formatNumber(account.ai_calls),
                                })}
                            </span>
                        </span>
                        <span className="cc-caption lg:text-right">
                            {account.created_at
                                ? formatDate(account.created_at)
                                : '—'}
                        </span>
                    </Link>
                ))}
            </div>

            <Pager page={accounts} />
        </AdminShell>
    );
}
