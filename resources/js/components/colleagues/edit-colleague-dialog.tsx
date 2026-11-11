import { Form } from '@inertiajs/react';
import { WithHowy } from '@/components/brand/howy-name';
import InputError from '@/components/input-error';
import { shownEntry } from '@/components/mailboxes/sender-badge';
import { useAccessLooks } from '@/components/mailboxes/sender-access';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslations } from '@/hooks/use-translations';
import { ACCESSES } from '@/lib/mailbox-rules';
import { update } from '@/routes/colleagues';
import type { ColleagueDetail, ColleagueMailboxAccess } from '@/types';

/**
 * A colleague's name and what Howy does with their mail, per mailbox: the
 * rule for their domain, or one of their own. Saved together.
 */
export function EditColleagueDialog({
    colleague,
    access,
    open,
    onOpenChange,
}: {
    colleague: ColleagueDetail;
    access: ColleagueMailboxAccess[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const t = useTranslations();
    const looks = useAccessLooks();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="gap-[22px] rounded-2xl bg-cc-panel p-7 sm:max-w-[520px]">
                <div className="flex flex-col gap-1 pr-8">
                    <DialogTitle className="cc-subtitle">
                        {t('Edit colleague')}
                    </DialogTitle>
                    <DialogDescription className="cc-caption">
                        {colleague.email}
                    </DialogDescription>
                </div>

                <Form
                    {...update.form(colleague.id)}
                    options={{ preserveScroll: true, preserveState: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="flex flex-col gap-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="colleague-name">
                                    {t('Name')}
                                </Label>
                                <Input
                                    id="colleague-name"
                                    name="name"
                                    defaultValue={colleague.name ?? ''}
                                    placeholder={t('First and last name')}
                                    autoFocus
                                    className="h-11"
                                />
                                <InputError message={errors.name} />
                            </div>

                            {access.length > 0 && (
                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`colleague-access-${access[0].mailboxId}`}
                                    >
                                        <WithHowy
                                            text={t(
                                                'What Howy does with their mail',
                                            )}
                                        />
                                    </Label>
                                    {access.map((item) => (
                                        <div
                                            key={item.mailboxId}
                                            className="grid gap-1.5"
                                        >
                                            {access.length > 1 && (
                                                <span className="text-[13px] text-cc-subtle">
                                                    {item.emailAddress}
                                                </span>
                                            )}
                                            <Select
                                                name={`access[${item.mailboxId}]`}
                                                defaultValue={
                                                    item.own ?? 'default'
                                                }
                                            >
                                                <SelectTrigger
                                                    id={`colleague-access-${item.mailboxId}`}
                                                    aria-label={t(
                                                        'What Howy does on :mailbox',
                                                        {
                                                            mailbox:
                                                                item.emailAddress,
                                                        },
                                                    )}
                                                    className="h-11 w-full"
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="default">
                                                        {item.domain !== null
                                                            ? t(
                                                                  'Follow :domain',
                                                                  {
                                                                      domain: shownEntry(
                                                                          item
                                                                              .domain
                                                                              .domain,
                                                                      ),
                                                                  },
                                                              )
                                                            : t(
                                                                  'Follow the mailbox rules',
                                                              )}
                                                    </SelectItem>
                                                    {ACCESSES.map((value) => (
                                                        <SelectItem
                                                            key={value}
                                                            value={value}
                                                        >
                                                            {looks[value].label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={
                                                    errors[
                                                        `access.${item.mailboxId}`
                                                    ]
                                                }
                                            />
                                        </div>
                                    ))}
                                    <p className="cc-caption">
                                        <WithHowy
                                            text={t(
                                                'Per mailbox: a rule of their own, or the rule for their domain.',
                                            )}
                                        />
                                    </p>
                                </div>
                            )}

                            <div className="flex flex-wrap items-center justify-end gap-2.5">
                                <DialogClose asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="h-11 px-[18px]"
                                    >
                                        {t('Cancel')}
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    className="h-11 px-[18px]"
                                    disabled={processing}
                                >
                                    {t('Save')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
