import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { WithHowy } from '@/components/brand/howy-name';
import InputError from '@/components/input-error';
import { MailLinkShell } from '@/components/mail-links/mail-link-shell';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import review from '@/routes/mail-links/review';

type Choice = 'keep' | 'withdraw' | 'correct';

type Statement = {
    index: number;
    statement: string;
    review: 'pending' | 'approved' | 'rejected' | null;
    bySender: boolean;
    request: {
        choice: 'withdraw' | 'correct';
        correction: string | null;
    } | null;
};

type Props = {
    token: string;
    company: string;
    subject: string | null;
    statements: Statement[];
    saved: boolean;
};

/**
 * What Howy took from a mail, for the person who sent it, from the button in
 * Saved! (04): per point keep it, take it back, or say what is not right.
 */
export default function Review({
    token,
    company,
    subject,
    statements,
    saved,
}: Props) {
    const t = useTranslations();
    const open = statements.filter(
        (statement) => statement.review !== 'rejected',
    );
    const [choices, setChoices] = useState<Record<number, Choice>>(() =>
        Object.fromEntries(
            open.map((statement) => [
                statement.index,
                statement.request?.choice ?? 'keep',
            ]),
        ),
    );

    const options: { value: Choice; label: string }[] = [
        { value: 'keep', label: t('Keep') },
        { value: 'withdraw', label: t('Don’t share') },
        { value: 'correct', label: t('Not right') },
    ];

    return (
        <MailLinkShell
            title={t('What Howy took from your email')}
            company={company}
        >
            <div className="flex flex-col gap-2">
                <h1 className="cc-subtitle">
                    <WithHowy text={t('What Howy took from your email')} />
                </h1>
                {subject && (
                    <p className="text-[15px] leading-[1.6] text-cc-muted">
                        “{subject}”
                    </p>
                )}
            </div>

            {saved && (
                <p className="rounded-xl bg-cc-accent-tint px-4 py-3 text-[14px] font-medium">
                    {t(
                        'Thank you! What was still waiting is taken out; an admin at :company deals with the rest.',
                        { company },
                    )}
                </p>
            )}

            <Form
                {...review.store.form(token)}
                options={{ preserveScroll: true }}
                className="flex flex-col gap-5"
            >
                {({ processing, errors }) => (
                    <>
                        <ul className="flex flex-col gap-3">
                            {statements.map((statement) => (
                                <li
                                    key={statement.index}
                                    className="flex flex-col gap-3 rounded-xl border-[1.5px] border-cc-border bg-cc-panel px-4 py-3.5"
                                >
                                    <p className="text-[15px] leading-[1.55]">
                                        {statement.statement}
                                    </p>
                                    <p className="text-[12.5px] text-cc-subtle">
                                        {statement.review === 'rejected'
                                            ? statement.bySender
                                                ? t(
                                                      'Taken out at your request.',
                                                  )
                                                : t(
                                                      'Not taken into the knowledge base.',
                                                  )
                                            : statement.review === 'approved'
                                              ? t('In the knowledge base.')
                                              : t(
                                                    'Waiting for an admin to approve it.',
                                                )}
                                    </p>

                                    {statement.review !== 'rejected' && (
                                        <>
                                            <input
                                                type="hidden"
                                                name={`requests[${statement.index}][choice]`}
                                                value={choices[statement.index]}
                                            />
                                            <div
                                                role="radiogroup"
                                                aria-label={statement.statement}
                                                className="flex flex-wrap gap-1.5"
                                            >
                                                {options.map((option) => (
                                                    <button
                                                        key={option.value}
                                                        type="button"
                                                        role="radio"
                                                        aria-checked={
                                                            choices[
                                                                statement.index
                                                            ] === option.value
                                                        }
                                                        onClick={() =>
                                                            setChoices({
                                                                ...choices,
                                                                [statement.index]:
                                                                    option.value,
                                                            })
                                                        }
                                                        className={cn(
                                                            'h-9 rounded-full border-[1.5px] px-4 text-[13px] font-semibold transition-colors',
                                                            choices[
                                                                statement.index
                                                            ] === option.value
                                                                ? 'border-cc-ink bg-cc-ink text-cc-bg'
                                                                : 'border-cc-border-strong bg-cc-panel text-cc-ink hover:border-cc-ink',
                                                        )}
                                                    >
                                                        {option.label}
                                                    </button>
                                                ))}
                                            </div>
                                            {choices[statement.index] ===
                                                'correct' && (
                                                <div className="grid gap-1.5">
                                                    <Textarea
                                                        name={`requests[${statement.index}][correction]`}
                                                        defaultValue={
                                                            statement.request
                                                                ?.correction ??
                                                            ''
                                                        }
                                                        rows={2}
                                                        aria-label={t(
                                                            'What is right?',
                                                        )}
                                                        placeholder={t(
                                                            'What is right?',
                                                        )}
                                                    />
                                                    <InputError
                                                        message={
                                                            errors[
                                                                `requests.${statement.index}.correction`
                                                            ]
                                                        }
                                                    />
                                                </div>
                                            )}
                                        </>
                                    )}
                                </li>
                            ))}
                        </ul>

                        {open.length > 0 && (
                            <Button
                                type="submit"
                                className="h-11 self-start px-[18px]"
                                disabled={processing}
                            >
                                {t('Send')}
                            </Button>
                        )}
                    </>
                )}
            </Form>
        </MailLinkShell>
    );
}
