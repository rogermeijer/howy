import { Form } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import DocumentController from '@/actions/App/Http/Controllers/Knowledge/DocumentController';
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
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import type { KnowledgeDocument } from '@/types';

type Props = {
    document: KnowledgeDocument | null;
    onOpenChange: (open: boolean) => void;
};

type Choice = 'keep' | 'remove';

/** How many of its facts are stated only in this document, and how many elsewhere too. */
type Impact = { only: number; elsewhere: number };

/**
 * Removing a document is a choice about its facts, so nothing is chosen for
 * the remover: keep them, or remove the ones nothing else states. Facts that
 * another document or a mail states too always stay.
 */
export function DeleteDocumentDialog({ document, onOpenChange }: Props) {
    const t = useTranslations();
    const [choice, setChoice] = useState<Choice | null>(null);
    const [impact, setImpact] = useState<Impact | null>(null);

    useEffect(() => {
        setChoice(null);
        setImpact(null);

        if (document === null) {
            return;
        }

        const controller = new AbortController();

        fetch(DocumentController.removal.url(document.id), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: controller.signal,
        })
            .then((response) => (response.ok ? response.json() : null))
            .then((data: Impact | null) => setImpact(data))
            .catch(() => undefined);

        return () => controller.abort();
    }, [document]);

    return (
        <Dialog open={document !== null} onOpenChange={onOpenChange}>
            <DialogContent className="rounded-2xl bg-cc-panel p-7 sm:max-w-[520px]">
                <DialogTitle className="cc-subtitle">
                    {t('Remove :title?', { title: document?.title ?? '' })}
                </DialogTitle>
                <DialogDescription className="cc-caption">
                    {t(
                        'All versions, the original files, sections and summaries are removed. This cannot be undone. What happens to its facts is up to you.',
                    )}
                </DialogDescription>

                {document && (
                    <Form
                        {...DocumentController.destroy.form(document.id)}
                        onSuccess={() => onOpenChange(false)}
                        className="flex flex-col gap-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                {impact !== null && (
                                    <p className="rounded-lg bg-cc-bg px-3.5 py-2.5 text-[13px]">
                                        {t(
                                            ':only facts are stated only in this document; :elsewhere also in another document or a mail. Those always stay.',
                                            {
                                                only: impact.only,
                                                elsewhere: impact.elsewhere,
                                            },
                                        )}
                                    </p>
                                )}

                                <fieldset className="flex flex-col gap-2.5">
                                    <legend className="cc-label mb-2.5">
                                        {t('Its facts')}
                                    </legend>
                                    <ChoiceOption
                                        value="keep"
                                        current={choice}
                                        onChange={setChoice}
                                        title={t('Keep its facts')}
                                        detail={t(
                                            'They keep counting and are cited as from the removed document.',
                                        )}
                                    />
                                    <ChoiceOption
                                        value="remove"
                                        current={choice}
                                        onChange={setChoice}
                                        title={t(
                                            'Remove the facts only this document states',
                                        )}
                                        detail={t(
                                            'Facts that something else states too stay, cited from there.',
                                        )}
                                    />
                                    <InputError message={errors.facts} />
                                </fieldset>

                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button
                                            variant="outline"
                                            className="h-10"
                                        >
                                            {t('Cancel')}
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        className="h-10"
                                        disabled={processing || choice === null}
                                    >
                                        {t('Remove')}
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

function ChoiceOption({
    value,
    current,
    onChange,
    title,
    detail,
}: {
    value: Choice;
    current: Choice | null;
    onChange: (value: Choice) => void;
    title: string;
    detail: string;
}) {
    return (
        <label
            className={cn(
                'flex cursor-pointer items-start gap-3 rounded-xl border-[1.5px] px-4 py-3',
                current === value ? 'border-cc-ink' : 'border-cc-border-strong',
            )}
        >
            <input
                type="radio"
                name="facts"
                value={value}
                checked={current === value}
                onChange={() => onChange(value)}
                className="mt-px size-[18px] accent-cc-ink"
            />
            <span className="flex flex-col gap-0.5">
                <span className="text-[14px] font-semibold">{title}</span>
                <span className="text-[12px] text-cc-subtle">{detail}</span>
            </span>
        </label>
    );
}
