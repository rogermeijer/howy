import { Form } from '@inertiajs/react';
import { Info } from 'lucide-react';
import { useEffect, useState } from 'react';
import MailboxController from '@/actions/App/Http/Controllers/Mailboxes/MailboxController';
import { WithHowy } from '@/components/brand/howy-name';
import InputError from '@/components/input-error';
import { MailboxExplainerDialog } from '@/components/mailboxes/mailbox-explainer-dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { parseList } from '@/lib/mailbox-rules';
import type { LocaleOption, Mailbox, MailFacts, SendPolicy } from '@/types';

type Props = {
    mailbox: Mailbox | null;
    policies: LocaleOption[];
    company: string;
    facts: MailFacts;
    onOpenChange: (open: boolean) => void;
};

const fieldClass = 'border-[1.5px] border-cc-border-strong bg-cc-panel';

/**
 * The rules of one mailbox: whose mail Howy reads, and whether it replies to
 * them. The list shown follows the policy: the whitelist for "Whitelist", the
 * blacklist for "Same domain" and "Everyone". The list that is not shown
 * still travels along, so switching policies never throws a list away. On a
 * public domain such as gmail.com "Same domain" would mean everyone, so it is
 * not offered. The explanation it opens follows the rules as they are being
 * set, so the effect of a choice shows before it is saved.
 */
export function SendPolicyDialog({
    mailbox,
    policies,
    company,
    facts,
    onOpenChange,
}: Props) {
    const t = useTranslations();
    const [explaining, setExplaining] = useState(false);
    const [policy, setPolicy] = useState<SendPolicy>('domain');
    const [whitelist, setWhitelist] = useState('');
    const [blacklist, setBlacklist] = useState('');
    const [repliesEnabled, setRepliesEnabled] = useState(true);

    useEffect(() => {
        if (mailbox) {
            setPolicy(mailbox.sendPolicy);
            setWhitelist(mailbox.sendWhitelist.join('\n'));
            setBlacklist(mailbox.sendBlacklist.join('\n'));
            setRepliesEnabled(mailbox.repliesEnabled);
        }
    }, [mailbox]);

    const domain = mailbox?.domain ?? '';
    const options = policies.filter(
        (option) => !(mailbox?.publicDomain && option.value === 'domain'),
    );
    const help: Record<SendPolicy, string> = {
        domain: t('Only mail from addresses at @:domain is read.', { domain }),
        whitelist: t('Only mail from the addresses and domains below is read.'),
        always: t('Mail from everyone is read, except from the blacklist.'),
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
                    {t('Mailbox rules')}
                </DialogTitle>
                <DialogDescription className="cc-caption">
                    <WithHowy
                        text={t(
                            'Whose mail to :address Howy reads, and whether it replies. Mail from anyone else is stored, but ignored.',
                            { address: mailbox?.emailAddress ?? '' },
                        )}
                    />
                </DialogDescription>

                <Button
                    type="button"
                    variant="outline"
                    className="h-10 w-fit justify-self-start border-[1.5px] border-cc-border-strong"
                    onClick={() => setExplaining(true)}
                >
                    <Info />
                    <span>
                        <WithHowy
                            text={t(
                                'See exactly what Howy does with these rules',
                            )}
                        />
                    </span>
                </Button>

                {mailbox && (
                    <MailboxExplainerDialog
                        open={explaining}
                        onOpenChange={setExplaining}
                        rules={{
                            address: mailbox.emailAddress,
                            domain: mailbox.domain,
                            policy,
                            whitelist: parseList(whitelist),
                            blacklist: parseList(blacklist),
                            repliesEnabled,
                        }}
                        company={company}
                        facts={facts}
                        note={t(
                            'This follows the rules as they are set now in the dialog, saved or not.',
                        )}
                    />
                )}

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
                                        {t('Read mail from')}
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
                                            className={`h-11 rounded-full ${fieldClass}`}
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {options.map((option) => (
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
                                        <WithHowy text={help[policy]} />
                                    </p>
                                    {mailbox.publicDomain && (
                                        <p className="cc-caption">
                                            {t(
                                                'Anyone can have an address at @:domain, so this mailbox has no "same domain".',
                                                { domain },
                                            )}
                                        </p>
                                    )}
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
                                            className={`min-h-28 rounded-2xl px-4 py-3 ${fieldClass}`}
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
                                            className={`min-h-28 rounded-2xl px-4 py-3 ${fieldClass}`}
                                        />
                                        <p className="cc-caption">
                                            {t(
                                                'Mail from these addresses and domains is ignored.',
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

                                <div className="grid gap-2">
                                    <label className="flex items-center gap-2.5 text-[15px] font-medium">
                                        <Checkbox
                                            name="replies_enabled"
                                            value="1"
                                            checked={repliesEnabled}
                                            onCheckedChange={(checked) =>
                                                setRepliesEnabled(
                                                    checked === true,
                                                )
                                            }
                                            className="size-5 border-[1.5px] border-cc-border-strong bg-cc-panel"
                                        />
                                        <span>
                                            <WithHowy
                                                text={t('Howy replies')}
                                            />
                                        </span>
                                    </label>
                                    <p className="cc-caption">
                                        <WithHowy
                                            text={t(
                                                'When the mailbox is in To, Howy answers a question it is sure enough of, and says when information contradicts the knowledge base. Only to people these rules let in, and never when the mailbox is in CC.',
                                            )}
                                        />
                                    </p>
                                    <InputError
                                        message={errors.replies_enabled}
                                    />
                                </div>

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
