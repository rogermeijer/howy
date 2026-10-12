import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { AdminShell, Chip } from '@/components/admin/admin-shell';
import { Pager } from '@/components/admin/pager';
import { SearchForm } from '@/components/admin/search-form';
import { Button } from '@/components/ui/button';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslations } from '@/hooks/use-translations';
import { create, index, show } from '@/routes/admin/users';
import type { AdminUserRow, Paginated } from '@/types';

type Props = {
    users: Paginated<AdminUserRow>;
    filters: { q: string };
};

const grid =
    'lg:grid lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1.2fr)_140px_120px] lg:items-center lg:gap-5';

export default function AdminUsers({ users, filters }: Props) {
    const t = useTranslations();
    const formatDate = useFormatDate();

    return (
        <AdminShell
            title={t('Users')}
            description={t(':count users on the platform.', {
                count: users.total,
            })}
            actions={
                <Button asChild className="h-10">
                    <Link href={create()}>
                        <Plus />
                        {t('New user')}
                    </Link>
                </Button>
            }
        >
            <SearchForm
                action={index.form()}
                value={filters.q}
                placeholder={t('Search by name or email address')}
            />

            <div className="cc-panel overflow-hidden">
                <div
                    className={`${grid} hidden border-b border-cc-border px-6 py-3`}
                >
                    <span className="cc-label">{t('User')}</span>
                    <span className="cc-label">{t('Accounts')}</span>
                    <span className="cc-label">{t('Invite code')}</span>
                    <span className="cc-label text-right">{t('Joined')}</span>
                </div>

                {users.data.length === 0 && (
                    <p className="cc-caption px-6 py-10 text-center">
                        {t('No users found.')}
                    </p>
                )}

                {users.data.map((user) => (
                    <Link
                        key={user.id}
                        href={show(user.id)}
                        className={`${grid} flex flex-col gap-1 border-b border-cc-border px-6 py-4 transition-colors last:border-b-0 hover:bg-cc-bg`}
                    >
                        <span className="flex min-w-0 flex-col">
                            <span className="flex items-center gap-2">
                                <span className="cc-row-title truncate">
                                    {user.name}
                                </span>
                                {user.is_super_admin && (
                                    <Chip tone="info">{t('Super-admin')}</Chip>
                                )}
                                {!user.email_verified && (
                                    <Chip tone="warn">{t('Unverified')}</Chip>
                                )}
                            </span>
                            <span className="cc-caption truncate">
                                {user.email}
                            </span>
                        </span>
                        <span className="cc-caption truncate">
                            {user.accounts.length > 0
                                ? user.accounts
                                      .map((account) =>
                                          account.is_admin
                                              ? `${account.name} (${t('admin')})`
                                              : account.name,
                                      )
                                      .join(', ')
                                : t('No account')}
                        </span>
                        <span className="font-mono text-[12px] text-cc-subtle">
                            {user.beta_code ?? '—'}
                        </span>
                        <span className="cc-caption lg:text-right">
                            {user.created_at
                                ? formatDate(user.created_at)
                                : '—'}
                        </span>
                    </Link>
                ))}
            </div>

            <Pager page={users} />
        </AdminShell>
    );
}
