import { Form } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import MailboxController from '@/actions/App/Http/Controllers/Mailboxes/MailboxController';
import { WithCcLogo } from '@/components/cc/with-cc-logo';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/hooks/use-translations';
import type { LocaleOption, Mailbox, SendPolicy } from '@/types';

type Props = {
    mailbox: Mailbox | null;
    policies: LocaleOption[];
    onOpenChange: (open: boolean) => void;
};

const fieldClass =
    'rounded-[10px] border-[1.5px] border-cc-border-strong bg-cc-panel';

/**
 * Who cc: may reply to from one mailbox. The list shown follows the policy:
 * the whitelist for "Whitelist", the blacklist for "Same domain" and "Always".
 * The list that is not shown still travels along, so switching policies never
 * throws a list away.
 */
export function SendPolicyDialog({ mailbox, policies, onOpenChange }: Props) {
    const t = useTranslations();
    const [policy, setPolicy] = useState<SendPolicy>('always');
    const [whitelist, setWhitelist] = useState('');
    const [blacklist, setBlacklist] = useState('');

    useEffect(() => {
        if (mailbox) {
            setPolicy(mailbox.sendPolicy);
            setWhitelist(mailbox.sendWhitelist.join('\n'));
            setBlacklist(mailbox.sendBlacklist.join('\n'));
        }
    }, [mailbox]);

    const domain = mailbox?.domain ?? '';
    const help: Record<SendPolicy, string> = {
        off: t('[cc]: never replies from this mailbox.'),
        domain: t('Only addresses at @:domain get a reply.', { domain }),
        whitelist: t('Only the addresses and domains below get a reply.'),
        always: t('Everyone who writes to this mailbox gets a reply.'),
    };

    const showsWhitelist = policy === 'whitelist';
    const showsBlacklist = policy === 'domain' || policy === 'always';

    const listError = (errors: Record<string, string>, name: string) =>
        Object.entries(errors).find(
            ([key]) => key === name || key.startsWith(`${name}.`),
        )?.[1];

    return (
        <Dialog open={mailbox !== null} onOpenChange={onOpenChange}>
            <DialogContent className="rounded-2xl bg-cc-panel p-7 sm:max-w-[520px]">
                <DialogTitle className="cc-subtitle">
                    {t('Reply settings')}
                </DialogTitle>
                <DialogDescription className="cc-caption">
                    {t(
                        'Who gets an answer from :address when they ask a question or send information that conflicts with the knowledge base.',
                        { address: mailbox?.emailAddress ?? '' },
                    )}
                </DialogDescription>

                {mailbox && (
                    <Form
                        {...MailboxController.updateSendPolicy.form(mailbox.id)}
                        options={{ preserveScroll: true }}
                        onSuccess={() => onOpenChange(false)}
                        className="flex flex-col gap-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="send_policy">
                                        {t('Reply to')}
                                    </Label>
                                    <Select
                                        name="send_policy"
                                        value={policy}
                                        onValueChange={(value) =>
                                            setPolicy(value as SendPolicy)
                                        }
                                    >
                                        <SelectTrigger
                                            id="send_policy"
                                            className={`h-11 ${fieldClass}`}
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {policies.map((option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <p className="cc-caption">
                                        <WithCcLogo text={help[policy]} />
                                    </p>
                                    <InputError message={errors.send_policy} />
                                </div>

                                {showsWhitelist ? (
                                    <div className="grid gap-2">
                                        <Label htmlFor="send_whitelist">
                                            {t('Whitelist')}
                                        </Label>
                                        <Textarea
                                            id="send_whitelist"
                                            name="send_whitelist"
                                            value={whitelist}
                                            onChange={(event) =>
                                                setWhitelist(event.target.value)
                                            }
                                            rows={5}
                                            placeholder={t(
                                                'One address or domain per line, e.g. jan@example.com or example.com',
                                            )}
                                            className={`min-h-28 ${fieldClass}`}
                                        />
                                        <InputError
                                            message={listError(
                                                errors,
                                                'send_whitelist',
                                            )}
                                        />
                                    </div>
                                ) : (
                                    <input
                                        type="hidden"
                                        name="send_whitelist"
                                        value={whitelist}
                                    />
                                )}

                                {showsBlacklist ? (
                                    <div className="grid gap-2">
                                        <Label htmlFor="send_blacklist">
                                            {t('Blacklist')}
                                        </Label>
                                        <Textarea
                                            id="send_blacklist"
                                            name="send_blacklist"
                                            value={blacklist}
                                            onChange={(event) =>
                                                setBlacklist(event.target.value)
                                            }
                                            rows={5}
                                            placeholder={t(
                                                'One address or domain per line, e.g. jan@example.com or example.com',
                                            )}
                                            className={`min-h-28 ${fieldClass}`}
                                        />
                                        <p className="cc-caption">
                                            {t(
                                                'These addresses and domains never get a reply.',
                                            )}
                                        </p>
                                        <InputError
                                            message={listError(
                                                errors,
                                                'send_blacklist',
                                            )}
                                        />
                                    </div>
                                ) : (
                                    <input
                                        type="hidden"
                                        name="send_blacklist"
                                        value={blacklist}
                                    />
                                )}

                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="h-10"
                                        >
                                            {t('Cancel')}
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        type="submit"
                                        className="h-10"
                                        disabled={processing}
                                    >
                                        {t('Save')}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}
