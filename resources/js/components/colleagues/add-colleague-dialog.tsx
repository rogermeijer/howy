import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { WithHowy } from '@/components/brand/howy-name';
import InputError from '@/components/input-error';
import { useAccessLooks } from '@/components/mailboxes/sender-access';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { store } from '@/routes/colleagues';
import type { MailboxOption } from '@/types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mailboxes: MailboxOption[];
    /** Live, the welcome can go at once. */
    isLive: boolean;
};

/**
 * Someone who has not mailed Howy yet, added by hand: their address, the
 * mailbox their Howy mails come from, and whether a rule of their own
 * decides what Howy does with their mail or the rule for their domain.
 */
export function AddColleagueDialog({
    open,
    onOpenChange,
    mailboxes,
    isLive,
}: Props) {
    const t = useTranslations();
    const looks = useAccessLooks();
    const [receivesMail, setReceivesMail] = useState(true);
    const [sendWelcome, setSendWelcome] = useState(true);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="gap-[22px] rounded-2xl bg-cc-panel p-7 sm:max-w-[520px]">
                <div className="flex flex-col gap-1 pr-8">
                    <DialogTitle className="cc-subtitle">
                        {t('Add colleague')}
                    </DialogTitle>
                    <DialogDescription className="cc-caption">
                        <WithHowy
                            text={t(
                                'Someone who mails Howy becomes a colleague by themselves. Add someone here who has not yet.',
                            )}
                        />
                    </DialogDescription>
                </div>

                {mailboxes.length === 0 ? (
                    <p className="rounded-xl bg-cc-pending-bg px-3.5 py-2.5 text-[13px] font-medium text-cc-pending-fg">
                        {t('Connect a mailbox first.')}
                    </p>
                ) : (
                    <Form
                        {...store.form()}
                        onSuccess={() => onOpenChange(false)}
                        className="flex flex-col gap-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="colleague-email">
                                        {t('Email address')}
                                    </Label>
                                    <Input
                                        id="colleague-email"
                                        name="email"
                                        type="email"
                                        required
                                        autoComplete="off"
                                        className="h-11"
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="colleague-name">
                                        {t('Name')}
                                    </Label>
                                    <Input
                                        id="colleague-name"
                                        name="name"
                                        autoComplete="off"
                                        className="h-11"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-5">
                                    <div className="grid min-w-0 content-start gap-2">
                                        <Label htmlFor="colleague-mailbox">
                                            {t('Mailbox')}
                                        </Label>
                                        <Select
                                            name="home_mailbox_id"
                                            defaultValue={String(
                                                mailboxes[0].id,
                                            )}
                                        >
                                            <SelectTrigger
                                                id="colleague-mailbox"
                                                className="h-11 w-full"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {mailboxes.map((mailbox) => (
                                                    <SelectItem
                                                        key={mailbox.id}
                                                        value={String(
                                                            mailbox.id,
                                                        )}
                                                    >
                                                        {mailbox.emailAddress}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={errors.home_mailbox_id}
                                        />
                                    </div>

                                    <div className="grid min-w-0 content-start gap-2">
                                        <Label htmlFor="colleague-access">
                                            {t('What Howy does')}
                                        </Label>
                                        <Select
                                            name="access"
                                            defaultValue="default"
                                        >
                                            <SelectTrigger
                                                id="colleague-access"
                                                className="h-11 w-full"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="default">
                                                    {t(
                                                        'Follow the mailbox rules',
                                                    )}
                                                </SelectItem>
                                                {ACCESSES.map((access) => (
                                                    <SelectItem
                                                        key={access}
                                                        value={access}
                                                    >
                                                        {looks[access].label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.access} />
                                    </div>
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
                                                    'The welcome, how Howy works and a thank-you for their first contribution.',
                                                )}
                                            />
                                        </span>
                                    </span>
                                </label>

                                {isLive && receivesMail && (
                                    <label className="flex items-center gap-3 px-1">
                                        <input
                                            type="hidden"
                                            name="send_welcome"
                                            value={sendWelcome ? '1' : '0'}
                                        />
                                        <Checkbox
                                            checked={sendWelcome}
                                            onCheckedChange={(on) =>
                                                setSendWelcome(on === true)
                                            }
                                            className="size-[18px] border-[1.5px] border-cc-border-strong bg-cc-panel"
                                        />
                                        <span className="text-[14px] font-medium">
                                            {t('Send the welcome mail now')}
                                        </span>
                                    </label>
                                )}

                                <div className="flex justify-end gap-2.5">
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
                                        {t('Add colleague')}
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}
