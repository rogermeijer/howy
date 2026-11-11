import { Form, Link, router } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import { WithHowy } from '@/components/brand/howy-name';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { index, update } from '@/routes/colleagues';
import type { ColleagueDetail } from '@/types';

/**
 * Who they are: their name, or their address while Howy knows no name, with
 * the name to add or change in place, and whether they get Howy's own mails.
 */
export function ColleagueHeader({ colleague }: { colleague: ColleagueDetail }) {
    const t = useTranslations();
    const [editing, setEditing] = useState(false);
    const title = colleague.name ?? colleague.email;

    const member = colleague.user !== null && (
        <span className="cc-tag cc-tag-action">{t('Member')}</span>
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

                {editing ? (
                    <Form
                        {...update.form(colleague.id)}
                        options={{ preserveScroll: true, preserveState: true }}
                        onSuccess={() => setEditing(false)}
                        className="flex flex-col gap-2.5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Input
                                        name="name"
                                        defaultValue={colleague.name ?? ''}
                                        aria-label={t('Name')}
                                        placeholder={t('First and last name')}
                                        autoFocus
                                        className="h-[52px] w-[360px] max-w-full border-cc-ink text-[22px] font-bold tracking-[-0.02em] md:text-[22px]"
                                    />
                                    <Button
                                        type="submit"
                                        className="h-11 px-[18px]"
                                        disabled={processing}
                                    >
                                        {t('Save')}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        className="h-11 text-cc-subtle"
                                        onClick={() => setEditing(false)}
                                    >
                                        {t('Cancel')}
                                    </Button>
                                </div>
                                <InputError message={errors.name} />
                                <p className="cc-body text-cc-subtle">
                                    {colleague.email}
                                </p>
                            </>
                        )}
                    </Form>
                ) : (
                    <>
                        <div className="flex flex-wrap items-center gap-2.5">
                            <h1 className="cc-title min-w-0 [overflow-wrap:anywhere]">
                                {title}
                            </h1>
                            {colleague.name !== null && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    onClick={() => setEditing(true)}
                                    title={t('Change the name')}
                                    aria-label={t('Change the name')}
                                    className="size-9 rounded-full border-[1.5px] border-cc-border-strong text-cc-subtle hover:text-cc-ink"
                                >
                                    <Pencil className="size-[15px]" />
                                </Button>
                            )}
                        </div>
                        {colleague.name !== null ? (
                            <div className="cc-body flex flex-wrap items-center gap-2 text-cc-subtle">
                                {colleague.email}
                                {member}
                            </div>
                        ) : (
                            <div className="flex flex-wrap items-center gap-x-3.5 gap-y-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setEditing(true)}
                                    className="h-10 rounded-full border-[1.5px] border-cc-border-strong pr-3.5 pl-3 text-[13.5px] font-semibold"
                                >
                                    <Plus className="size-[15px]" />
                                    {t('Add a name')}
                                </Button>
                                <span className="cc-caption">
                                    {t('No name known yet.')}
                                </span>
                                {member}
                            </div>
                        )}
                    </>
                )}
            </div>

            <ReceivesMailSwitch colleague={colleague} />
        </div>
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
