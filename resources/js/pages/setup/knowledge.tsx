import { Form, Link } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, ArrowRight, Lock } from 'lucide-react';
import { useState } from 'react';
import SetupController from '@/actions/App/Http/Controllers/SetupController';
import { WithHowy } from '@/components/brand/howy-name';
import InputError from '@/components/input-error';
import { SetupStep } from '@/components/setup/setup-step';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import setup from '@/routes/setup';

type Language = {
    value: string;
    label: string;
    /** A folder and a fact as Howy writes them in this language. */
    example: {
        folderLabel: string;
        folder: string;
        factLabel: string;
        fact: string;
    };
};

type Props = {
    knowledgeLocale: string;
    workspaceLocale: string;
    languages: Language[];
};

/**
 * Setup, step two: the one language the knowledge base is built in. Changing
 * it once knowledge is processed lowers its quality, so the choice is
 * confirmed before it counts (and the server checks that it was).
 */
export default function Knowledge({
    knowledgeLocale,
    workspaceLocale,
    languages,
}: Props) {
    const t = useTranslations();
    const [selected, setSelected] = useState(knowledgeLocale);
    const [understood, setUnderstood] = useState(false);
    const label =
        languages.find((language) => language.value === selected)?.label ??
        selected;

    // The confirmation is for one language: choosing another asks again.
    const choose = (value: string) => {
        setSelected(value);
        setUnderstood(false);
    };

    return (
        <Form
            {...SetupController.updateKnowledge.form()}
            disableWhileProcessing
            className="flex flex-col gap-6"
        >
            {({ processing, errors }) => (
                <SetupStep
                    current="knowledge"
                    head={t('Knowledge base language')}
                    eyebrow={t('Step 2 of 3')}
                    title={
                        <WithHowy
                            text={t(
                                'In which language should Howy build your knowledge base?',
                            )}
                        />
                    }
                    description={
                        <WithHowy
                            text={t(
                                'Howy writes facts, summaries, folder names and statements from mail in this language, whatever language a document or mail is in. What comes in and what goes out can still be in any language.',
                            )}
                        />
                    }
                    footer={
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <Button
                                asChild
                                variant="outline"
                                className="h-11 px-[18px]"
                            >
                                <Link href={setup.workspace()}>
                                    <ArrowLeft />
                                    {t('Back')}
                                </Link>
                            </Button>
                            <div className="flex flex-wrap items-center justify-end gap-3">
                                {!understood && (
                                    <span className="cc-caption">
                                        {t('Tick the box above to continue')}
                                    </span>
                                )}
                                <Button
                                    type="submit"
                                    disabled={!understood}
                                    className="h-11 px-5"
                                    data-test="setup-knowledge-button"
                                >
                                    {processing && <Spinner />}
                                    {t('Continue in :language', {
                                        language: label,
                                    })}
                                    <ArrowRight />
                                </Button>
                            </div>
                        </div>
                    }
                    note={
                        <span className="inline-flex items-center gap-2">
                            <Lock className="size-3.5 shrink-0" />
                            {t(
                                'The one setting to get right now. Everything else can change later in Settings.',
                            )}
                        </span>
                    }
                >
                    <fieldset className="flex min-w-0 flex-col gap-3">
                        <legend className="cc-label mb-3">
                            {t('Knowledge base language')}
                        </legend>
                        {languages.map((language) => {
                            const checked = language.value === selected;

                            return (
                                <label
                                    key={language.value}
                                    className={cn(
                                        'flex cursor-pointer items-start gap-3.5 rounded-xl border-[1.5px] bg-cc-panel p-4 transition-colors',
                                        checked
                                            ? 'border-cc-ink'
                                            : 'border-cc-border-strong hover:border-cc-subtle',
                                    )}
                                >
                                    <input
                                        type="radio"
                                        name="knowledge_locale"
                                        value={language.value}
                                        checked={checked}
                                        onChange={() => choose(language.value)}
                                        className="mt-[9px] size-[18px] shrink-0 accent-cc-ink"
                                    />
                                    <span
                                        aria-hidden="true"
                                        className="flex size-9 shrink-0 items-center justify-center rounded-[9px] bg-cc-raised text-[12px] font-bold tracking-[0.04em] uppercase"
                                    >
                                        {language.value}
                                    </span>
                                    <span className="flex min-w-0 flex-1 flex-col gap-2.5">
                                        <span className="flex min-h-9 flex-wrap items-center gap-2">
                                            <span
                                                lang={language.value}
                                                className="text-[15px] font-semibold"
                                            >
                                                {language.label}
                                            </span>
                                            {language.value ===
                                                workspaceLocale && (
                                                <span className="cc-tag cc-tag-noise before:hidden">
                                                    {t(
                                                        'Same as your workspace',
                                                    )}
                                                </span>
                                            )}
                                        </span>
                                        <span className="flex flex-col gap-1 rounded-[10px] bg-cc-bg px-3 py-2.5 text-[13px] leading-[1.5] text-cc-muted">
                                            <span className="cc-label">
                                                <WithHowy
                                                    text={t(
                                                        'How Howy writes it',
                                                    )}
                                                />
                                            </span>
                                            <span lang={language.value}>
                                                <span className="font-semibold text-cc-ink">
                                                    {
                                                        language.example
                                                            .folderLabel
                                                    }
                                                </span>{' '}
                                                · {language.example.folder}
                                            </span>
                                            <span lang={language.value}>
                                                <span className="font-semibold text-cc-ink">
                                                    {language.example.factLabel}
                                                </span>{' '}
                                                · {language.example.fact}
                                            </span>
                                        </span>
                                    </span>
                                </label>
                            );
                        })}
                        <InputError message={errors.knowledge_locale} />
                    </fieldset>

                    <div
                        role="note"
                        className="flex gap-3.5 rounded-xl bg-cc-pending-bg p-[18px] text-cc-pending-fg"
                    >
                        <AlertTriangle className="mt-0.5 size-5 shrink-0" />
                        <div className="flex min-w-0 flex-col gap-2">
                            <p className="flex flex-wrap items-center gap-2 text-[15px] leading-[1.35] font-bold">
                                <span className="rounded-full bg-cc-pending-fg px-2.5 py-0.5 text-[11px] tracking-[0.08em] text-cc-pending-bg uppercase">
                                    {t('Important')}
                                </span>
                                <span>
                                    <WithHowy
                                        text={t(
                                            'Do not change this once Howy has processed knowledge',
                                        )}
                                    />
                                </span>
                            </p>
                            <p className="text-[14px] leading-[1.55]">
                                <WithHowy
                                    text={t(
                                        'Changing it later reads nothing again: Howy only machine-translates what it already wrote. That loses nuance and lowers the quality of your knowledge base.',
                                    )}
                                />
                            </p>
                            <p className="text-[13px] leading-[1.5]">
                                {t(
                                    'Not affected: the language of the screens, and the language of mail that comes in or goes out. Replies are written in the language of the question.',
                                )}
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-col gap-2">
                        <label
                            className={cn(
                                'flex cursor-pointer items-start gap-3 rounded-xl border-[1.5px] px-4 py-3.5 text-[14px] leading-[1.45] font-semibold',
                                understood
                                    ? 'border-cc-ink'
                                    : 'border-cc-border-strong',
                            )}
                        >
                            <Checkbox
                                name="understood"
                                value="1"
                                checked={understood}
                                onCheckedChange={(value) =>
                                    setUnderstood(value === true)
                                }
                                className="mt-px size-[18px] border-cc-border-strong"
                            />
                            <span>
                                <WithHowy
                                    text={t(
                                        'I understand the knowledge base stays in :language once Howy has processed knowledge.',
                                        { language: label },
                                    )}
                                />
                            </span>
                        </label>
                        <InputError message={errors.understood} />
                    </div>
                </SetupStep>
            )}
        </Form>
    );
}
