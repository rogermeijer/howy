import { Link, router } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import { WithHowy } from '@/components/brand/howy-name';
import { EditColleagueDialog } from '@/components/colleagues/edit-colleague-dialog';
import { shownEntry } from '@/components/mailboxes/sender-badge';
import { AccessTag } from '@/components/mailboxes/sender-access';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { index, update } from '@/routes/colleagues';
import type { ColleagueDetail, ColleagueMailboxAccess } from '@/types';

/**
 * Who they are and what Howy does with their mail: their name, or their
 * address while Howy knows no name, the access on their mailbox with where it
 * comes from on hover, and whether they get Howy's own mails. Name and access
 * change together in a dialog.
 */
export function ColleagueHeader({
    colleague,
    access,
}: {
    colleague: ColleagueDetail;
    access: ColleagueMailboxAccess[];
}) {
    const t = useTranslations();
    const [editing, setEditing] = useState(false);
    const title = colleague.name ?? colleague.email;

    const badges = (
        <>
            <AccessBadge colleague={colleague} access={access} />
            {colleague.user !== null && (
                <span className="cc-tag cc-tag-action">{t('Member')}</span>
            )}
        </>
    );

    return (
        <div className="flex flex-wrap items-end justify-between gap-x-6 gap-y-4">
            <div className="flex min-w-0 flex-[1_1_480px] flex-col gap-2">
                <div className="cc-caption flex min-w-0 items-center gap-2">
                    <Link
                        href={index()}
                        className="text-cc-subtle transition-colors hover:text-cc-ink"
                    >
                        {t('Colleagues')}
                    </Link>
                    <span>/</span>
                    <span className="truncate font-medium text-cc-ink">
                        {title}
                    </span>
                </div>

                <h1 className="cc-title min-w-0 [overflow-wrap:anywhere]">
                    {title}
                </h1>

                {colleague.name !== null ? (
                    <div className="cc-body flex flex-wrap items-center gap-x-2 gap-y-1 text-cc-subtle">
                        {colleague.email}
                        {badges}
                        {/* Rarely needed once there is a name: quiet, on hover. */}
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => setEditing(true)}
                                    aria-label={t('Edit colleague')}
                                    className="size-7 rounded-full text-cc-faint hover:bg-cc-raised hover:text-cc-ink"
                                >
                                    <Pencil className="size-3.5" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                {t('Edit colleague')}
                            </TooltipContent>
                        </Tooltip>
                    </div>
                ) : (
                    <div className="cc-body flex flex-wrap items-center gap-x-2 gap-y-1 text-cc-subtle">
                        {badges}
                        {/* Where the edit sits once there is a name, with words. */}
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setEditing(true)}
                            className="h-7 gap-1.5 rounded-full px-2.5 text-[13px] font-medium text-cc-subtle hover:bg-cc-raised hover:text-cc-ink has-[>svg]:px-2.5"
                        >
                            <Plus className="size-3.5" />
                            {t('Add a name')}
                        </Button>
                    </div>
                )}
            </div>

            <ReceivesMailSwitch colleague={colleague} />

            <EditColleagueDialog
                colleague={colleague}
                access={access}
                open={editing}
                onOpenChange={setEditing}
            />
        </div>
    );
}

/**
 * What Howy does with their mail on their home mailbox, and on hover where
 * that comes from: per mailbox, when there is more than one.
 */
function AccessBadge({
    colleague,
    access,
}: {
    colleague: ColleagueDetail;
    access: ColleagueMailboxAccess[];
}) {
    const t = useTranslations();

    const because = (item: ColleagueMailboxAccess) =>
        item.own !== null
            ? t('Their own rule')
            : item.domain !== null
              ? t('Through the rule for :domain', {
                    domain: shownEntry(item.domain.domain),
                })
              : t('No rule covers them, so Howy ignores their mail');

    if (access.length === 0) {
        return <AccessTag access={colleague.access} />;
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <AccessTag access={colleague.access} />
            </TooltipTrigger>
            <TooltipContent>
                {access.length === 1 ? (
                    <WithHowy text={because(access[0])} />
                ) : (
                    <ul className="flex flex-col gap-0.5">
                        {access.map((item) => (
                            <li key={item.mailboxId}>
                                {item.emailAddress}:{' '}
                                <WithHowy text={because(item)} />
                            </li>
                        ))}
                    </ul>
                )}
            </TooltipContent>
        </Tooltip>
    );
}

/**
 * Whether Howy sends them its own mails, saved as it is switched. Answers to
 * their own questions go out either way.
 */
function ReceivesMailSwitch({ colleague }: { colleague: ColleagueDetail }) {
    const t = useTranslations();
    const [saving, setSaving] = useState(false);
    const on = colleague.receivesMail;

    const toggle = () =>
        router.patch(
            update.url(colleague.id),
            { receives_mail: !on },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
            },
        );

    return (
        <div className="flex flex-[0_1_340px] items-start gap-3 rounded-[14px] border border-cc-border bg-cc-panel px-4 py-3.5">
            <button
                type="button"
                role="switch"
                aria-checked={on}
                aria-labelledby="receives-mail-label"
                onClick={toggle}
                disabled={saving}
                className={cn(
                    'mt-px flex h-7 w-[46px] shrink-0 cursor-pointer items-center rounded-full border-[1.5px] px-[3px] transition-colors disabled:cursor-wait',
                    on
                        ? 'justify-end border-cc-ink bg-cc-accent'
                        : 'justify-start border-cc-border-strong bg-cc-raised',
                )}
            >
                <span
                    className={cn(
                        'size-[18px] rounded-full',
                        on ? 'bg-cc-ink' : 'bg-cc-faint',
                    )}
                />
            </button>
            <span className="flex flex-col gap-0.5">
                <span
                    id="receives-mail-label"
                    className="text-[14px] font-semibold"
                >
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
        </div>
    );
}
