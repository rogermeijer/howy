import { Form } from '@inertiajs/react';
import { Pause, Rocket } from 'lucide-react';
import { useState } from 'react';
import { WithHowy } from '@/components/brand/howy-name';
import { Section } from '@/components/cc/section';
import { GoLiveDialog } from '@/components/settings/go-live-dialog';
import { Button } from '@/components/ui/button';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslations } from '@/hooks/use-translations';
import live from '@/routes/account/live';
import type { LiveState, WelcomeCandidate } from '@/types';

type Props = {
    live: LiveState;
    candidates: WelcomeCandidate[] | undefined;
    canManage: boolean;
};

/**
 * The start signal for Howy's own mails to colleagues: live, or paused.
 * Answers to questions go out either way.
 */
export function LiveSection({ live: state, candidates, canManage }: Props) {
    const t = useTranslations();
    const formatDate = useFormatDate();
    const [open, setOpen] = useState(false);

    return (
        <Section
            title={t('Howy mails')}
            description={
                <WithHowy
                    text={t(
                        'The welcome and what follows it, from Howy to your colleagues. Answers to questions go out either way.',
                    )}
                />
            }
            action={
                <span
                    className={
                        state.since !== null
                            ? 'cc-tag cc-tag-decision'
                            : 'cc-tag cc-tag-pending'
                    }
                >
                    {state.since !== null ? t('Live') : t('Not live yet')}
                </span>
            }
        >
            <div className="flex flex-wrap items-center justify-between gap-4">
                <p className="text-[14px] leading-[1.55] text-cc-muted">
                    {state.since !== null ? (
                        t('Live since :date.', {
                            date: formatDate(state.since, {
                                dateStyle: 'long',
                            }),
                        })
                    ) : (
                        <WithHowy
                            text={t(
                                'Howy sends nothing of its own until you put it live.',
                            )}
                        />
                    )}
                </p>

                {canManage &&
                    (state.since !== null ? (
                        <Form
                            {...live.destroy.form()}
                            options={{ preserveScroll: true }}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="outline"
                                    className="h-10"
                                    disabled={processing}
                                >
                                    <Pause />
                                    {t('Pause')}
                                </Button>
                            )}
                        </Form>
                    ) : (
                        <Button className="h-10" onClick={() => setOpen(true)}>
                            <Rocket />
                            <span>
                                <WithHowy text={t('Put Howy live')} />
                            </span>
                        </Button>
                    ))}
            </div>

            {canManage && (
                <GoLiveDialog
                    open={open}
                    onOpenChange={setOpen}
                    candidates={candidates}
                />
            )}
        </Section>
    );
}
