import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { WithHowy } from '@/components/brand/howy-name';
import InputError from '@/components/input-error';
import { MailLinkShell } from '@/components/mail-links/mail-link-shell';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import rate from '@/routes/mail-links/rate';
import type { AnswerRating } from '@/types';

type Props = {
    token: string;
    company: string;
    question: string | null;
    answer: string | null;
    /** The choice the link opened with, else the one saved. */
    choice: AnswerRating | null;
    note: string | null;
    rated: boolean;
};

const CHOICES: { value: AnswerRating; label: string }[] = [
    { value: 'valuable', label: 'Yes' },
    { value: 'partly', label: 'Not quite' },
    { value: 'outdated', label: 'Outdated' },
];

/**
 * Rating an answer of Howy's, opened from a link under it with that choice
 * made. Nothing is saved until it is sent.
 */
export default function Rate({
    token,
    company,
    question,
    answer,
    choice,
    note,
    rated,
}: Props) {
    const t = useTranslations();
    const [picked, setPicked] = useState<AnswerRating | null>(choice);

    return (
        <MailLinkShell title={t('Was this valuable?')} company={company}>
            <div className="flex flex-col gap-2">
                <h1 className="cc-subtitle">{t('Was this valuable?')}</h1>
                {question && (
                    <p className="text-[15px] leading-[1.6] text-cc-muted">
                        <WithHowy
                            text={t('You asked Howy: “:question”', {
                                question,
                            })}
                        />
                    </p>
                )}
            </div>

            {answer && (
                <div className="max-h-[220px] overflow-y-auto rounded-xl border-[1.5px] border-cc-border bg-cc-bg px-4 py-3 text-[14px] leading-[1.6] whitespace-pre-line">
                    {answer}
                </div>
            )}

            {rated && (
                <p className="rounded-xl bg-cc-accent-tint px-4 py-3 text-[14px] font-medium">
                    {t(
                        'Thank you! Your rating is saved. You can still change it.',
                    )}
                </p>
            )}

            <Form
                {...rate.store.form(token)}
                options={{ preserveScroll: true }}
                className="flex flex-col gap-5"
            >
                {({ processing, errors }) => (
                    <>
                        <input
                            type="hidden"
                            name="rating"
                            value={picked ?? ''}
                        />
                        <div
                            role="radiogroup"
                            aria-label={t('Was this valuable?')}
                            className="flex flex-wrap gap-2"
                        >
                            {CHOICES.map((option) => (
                                <button
                                    key={option.value}
                                    type="button"
                                    role="radio"
                                    aria-checked={picked === option.value}
                                    onClick={() => setPicked(option.value)}
                                    className={cn(
                                        'h-11 rounded-full border-[1.5px] px-5 text-[14px] font-semibold transition-colors',
                                        picked === option.value
                                            ? 'border-cc-ink bg-cc-ink text-cc-bg'
                                            : 'border-cc-border-strong bg-cc-panel text-cc-ink hover:border-cc-ink',
                                    )}
                                >
                                    {t(option.label)}
                                </button>
                            ))}
                        </div>
                        <InputError message={errors.rating} />

                        <div className="grid gap-2">
                            <Label htmlFor="note">
                                {t('Anything to add? (optional)')}
                            </Label>
                            <Textarea
                                id="note"
                                name="note"
                                defaultValue={note ?? ''}
                                rows={3}
                                placeholder={t(
                                    'What was missing, or what has changed?',
                                )}
                            />
                            <InputError message={errors.note} />
                        </div>

                        <Button
                            type="submit"
                            className="h-11 self-start px-[18px]"
                            disabled={processing || picked === null}
                        >
                            {t('Send')}
                        </Button>
                    </>
                )}
            </Form>
        </MailLinkShell>
    );
}
