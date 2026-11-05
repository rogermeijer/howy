import { router } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { useState } from 'react';
import AccountController from '@/actions/App/Http/Controllers/Settings/AccountController';
import { WithHowy } from '@/components/brand/howy-name';
import { Section } from '@/components/cc/section';
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
import { useTranslations } from '@/hooks/use-translations';
import type { LocaleOption } from '@/types';

type Props = {
    current: string;
    locales: LocaleOption[];
    canManage: boolean;
};

/**
 * The one language the knowledge base is built in. Changing it does not read
 * anything again: what Howy wrote is machine-translated, which loses nuance
 * and costs AI calls. Mail comes in and goes out in any language either way,
 * so it is discouraged, and confirmed only once that is acknowledged.
 */
export function KnowledgeLanguageSection({
    current,
    locales,
    canManage,
}: Props) {
    const t = useTranslations();
    const [selected, setSelected] = useState(current);
    const [confirming, setConfirming] = useState(false);
    const [understood, setUnderstood] = useState(false);
    const [processing, setProcessing] = useState(false);
    const label =
        locales.find((locale) => locale.value === selected)?.label ?? selected;

    const confirm = (open: boolean) => {
        setConfirming(open);
        setUnderstood(false);
    };

    const save = () =>
        router.patch(
            AccountController.updateKnowledgeLanguage.url(),
            { knowledge_locale: selected },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false);
                    confirm(false);
                },
            },
        );

    return (
        <Section
            title={t('Knowledge base language')}
            description={
                <WithHowy
                    text={t(
                        'Howy writes facts, summaries, folder names and statements from mail in this language, whatever language a document or mail is in. What comes in and what goes out can still be in any language.',
                    )}
                />
            }
        >
            {canManage ? (
                <div className="flex flex-col gap-4">
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="grid min-w-[220px] gap-2">
                            <Label htmlFor="knowledge_locale">
                                {t('Language')}
                            </Label>
                            <Select
                                value={selected}
                                onValueChange={setSelected}
                            >
                                <SelectTrigger
                                    id="knowledge_locale"
                                    className="h-11 w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {locales.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <Button
                            className="h-11"
                            disabled={selected === current}
                            onClick={() => confirm(true)}
                        >
                            {t('Save')}
                        </Button>
                    </div>
                    <p className="flex gap-2 rounded-[8px] bg-cc-pending-bg px-3 py-2 text-[13px] leading-[1.5] text-cc-pending-fg">
                        <AlertTriangle className="mt-0.5 size-3.5 shrink-0" />
                        <span>
                            {t(
                                'Choose this once. Changing it later is strongly discouraged: it lowers the quality of the knowledge base.',
                            )}
                        </span>
                    </p>
                </div>
            ) : (
                <p className="cc-caption">
                    {t(
                        'Only an administrator of this account can change these settings.',
                    )}
                </p>
            )}

            <Dialog open={confirming} onOpenChange={confirm}>
                <DialogContent className="rounded-2xl bg-cc-panel p-7 sm:max-w-[520px]">
                    <div className="flex size-10 items-center justify-center rounded-full bg-cc-pending-bg text-cc-pending-fg">
                        <AlertTriangle className="size-5" />
                    </div>
                    <DialogTitle className="cc-subtitle">
                        {t('Build the knowledge base in :language?', {
                            language: label,
                        })}
                    </DialogTitle>
                    <DialogDescription className="font-semibold text-cc-pending-fg">
                        {t(
                            'We strongly discourage this. It lowers the quality of the knowledge base.',
                        )}
                    </DialogDescription>
                    <ul className="cc-caption flex list-disc flex-col gap-2 pl-5">
                        <li>
                            <WithHowy
                                text={t(
                                    'Nothing is read again. Howy machine-translates what it already wrote (facts, summaries and folder names) into :language, in the background, using your AI provider.',
                                    { language: label },
                                )}
                            />
                        </li>
                        <li>
                            {t(
                                'Translation loses nuance. Facts no longer use the words of the passages they cite, which stay in their own language, and facts that come out of translation alike are merged into one.',
                            )}
                        </li>
                        <li>
                            {t(
                                'It does not change what comes in or goes out: mail and documents can still be in any language, and replies are written in the language of the question.',
                            )}
                        </li>
                    </ul>
                    <label className="flex cursor-pointer items-start gap-2.5 rounded-xl border border-cc-border-strong px-3.5 py-3 text-[13px] font-semibold">
                        <Checkbox
                            checked={understood}
                            onCheckedChange={(value) =>
                                setUnderstood(value === true)
                            }
                            className="mt-px size-[18px] border-cc-border-strong"
                        />
                        {t(
                            'I understand this lowers the quality of the knowledge base.',
                        )}
                    </label>
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="outline" className="h-10">
                                {t('Cancel')}
                            </Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            className="h-10"
                            disabled={!understood || processing}
                            onClick={save}
                        >
                            {t('Change anyway')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Section>
    );
}
