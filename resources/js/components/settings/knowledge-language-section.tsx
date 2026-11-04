import { router } from '@inertiajs/react';
import { useState } from 'react';
import AccountController from '@/actions/App/Http/Controllers/Settings/AccountController';
import { WithHowy } from '@/components/brand/howy-name';
import { Section } from '@/components/cc/section';
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
import { useTranslations } from '@/hooks/use-translations';
import type { LocaleOption } from '@/types';

type Props = {
    current: string;
    locales: LocaleOption[];
    canManage: boolean;
};

/**
 * The one language the knowledge base is built in. Changing it puts what is
 * there into the new language, which costs AI calls: so it is confirmed.
 */
export function KnowledgeLanguageSection({
    current,
    locales,
    canManage,
}: Props) {
    const t = useTranslations();
    const [selected, setSelected] = useState(current);
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);
    const label =
        locales.find((locale) => locale.value === selected)?.label ?? selected;

    const save = () =>
        router.patch(
            AccountController.updateKnowledgeLanguage.url(),
            { knowledge_locale: selected },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false);
                    setConfirming(false);
                },
            },
        );

    return (
        <Section
            title={t('Knowledge base language')}
            description={
                <WithHowy
                    text={t(
                        'Howy writes facts, summaries, folder names and statements from mail in this language, whatever language a document or mail is in. The text of documents stays as it is.',
                    )}
                />
            }
        >
            {canManage ? (
                <div className="flex flex-wrap items-end gap-3">
                    <div className="grid min-w-[220px] gap-2">
                        <Label htmlFor="knowledge_locale">
                            {t('Language')}
                        </Label>
                        <Select value={selected} onValueChange={setSelected}>
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
                        onClick={() => setConfirming(true)}
                    >
                        {t('Save')}
                    </Button>
                </div>
            ) : (
                <p className="cc-caption">
                    {t(
                        'Only an administrator of this account can change these settings.',
                    )}
                </p>
            )}

            <Dialog open={confirming} onOpenChange={setConfirming}>
                <DialogContent className="rounded-2xl bg-cc-panel p-7 sm:max-w-[480px]">
                    <DialogTitle className="cc-subtitle">
                        {t('Build the knowledge base in :language?', {
                            language: label,
                        })}
                    </DialogTitle>
                    <DialogDescription className="cc-caption">
                        <WithHowy
                            text={t(
                                'Howy translates what it already wrote (facts, summaries and folder names) into :language, in the background. This uses your AI provider. The text of documents stays as it is.',
                                { language: label },
                            )}
                        />
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="outline" className="h-10">
                                {t('Cancel')}
                            </Button>
                        </DialogClose>
                        <Button
                            className="h-10"
                            disabled={processing}
                            onClick={save}
                        >
                            {t('Change and translate')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Section>
    );
}
