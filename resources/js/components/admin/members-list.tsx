import { Form, Link } from '@inertiajs/react';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Chip } from '@/components/admin/admin-shell';
import { useTranslations } from '@/hooks/use-translations';
import { destroy, store, update } from '@/routes/admin/accounts/members';

type Row = {
    accountId: number;
    userId: number;
    title: string;
    subtitle?: string;
    href: Parameters<typeof Link>[0]['href'];
    isAdmin: boolean;
    isSuperAdmin?: boolean;
};

/**
 * Memberships with their admin toggle and a remove button, shared by the
 * account page (its members) and the user page (their accounts).
 */
export function MembershipRows({
    rows,
    empty,
}: {
    rows: Row[];
    empty: string;
}) {
    const t = useTranslations();

    if (rows.length === 0) {
        return <p className="cc-caption">{empty}</p>;
    }

    return (
        <div className="flex flex-col divide-y divide-cc-border rounded-xl border border-cc-border">
            {rows.map((row) => (
                <div
                    key={`${row.accountId}-${row.userId}`}
                    className="flex flex-wrap items-center gap-3 px-4 py-3"
                >
                    <Link
                        href={row.href}
                        className="flex min-w-0 flex-1 flex-col hover:text-cc-accent-deep"
                    >
                        <span className="truncate font-medium">
                            {row.title}
                        </span>
                        {row.subtitle && (
                            <span className="cc-caption truncate">
                                {row.subtitle}
                            </span>
                        )}
                    </Link>

                    {row.isSuperAdmin && (
                        <Chip tone="info">{t('Super-admin')}</Chip>
                    )}
                    {row.isAdmin ? (
                        <Chip tone="good">{t('Administrator')}</Chip>
                    ) : (
                        <Chip>{t('Member')}</Chip>
                    )}

                    <Form
                        {...update.form([row.accountId, row.userId])}
                        options={{ preserveScroll: true }}
                    >
                        {({ processing }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="is_admin"
                                    value={row.isAdmin ? '0' : '1'}
                                />
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={processing}
                                >
                                    {row.isAdmin
                                        ? t('Make member')
                                        : t('Make administrator')}
                                </Button>
                            </>
                        )}
                    </Form>

                    <Form
                        {...destroy.form([row.accountId, row.userId])}
                        options={{ preserveScroll: true }}
                        onBefore={() =>
                            confirm(
                                t('Remove :name from this account?', {
                                    name: row.title,
                                }),
                            )
                        }
                    >
                        {({ processing }) => (
                            <Button
                                variant="ghost"
                                size="sm"
                                disabled={processing}
                                className="text-cc-pending-fg hover:text-cc-pending-fg"
                            >
                                {t('Remove')}
                            </Button>
                        )}
                    </Form>
                </div>
            ))}
        </div>
    );
}

/**
 * Add an existing user to an account by email address.
 */
export function AddMemberForm({ accountId }: { accountId: number }) {
    const t = useTranslations();

    return (
        <Form
            {...store.form(accountId)}
            options={{ preserveScroll: true }}
            resetOnSuccess
            className="flex flex-col gap-3"
        >
            {({ processing, errors }) => (
                <>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div className="grid flex-1 gap-2">
                            <Label htmlFor="member-email">
                                {t('Add a user by email address')}
                            </Label>
                            <Input
                                id="member-email"
                                name="email"
                                type="email"
                                required
                                placeholder={t('name@company.com')}
                                className="h-11"
                            />
                        </div>
                        <label className="flex h-11 items-center gap-2 text-sm">
                            <Checkbox name="is_admin" value="1" />
                            {t('Administrator')}
                        </label>
                        <Button disabled={processing} className="h-11">
                            {t('Add')}
                        </Button>
                    </div>
                    <InputError message={errors.email} />
                </>
            )}
        </Form>
    );
}
