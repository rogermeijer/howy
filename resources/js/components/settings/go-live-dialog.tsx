import { Form, router } from '@inertiajs/react';
import { Rocket } from 'lucide-react';
import { useEffect, useState } from 'react';
import { WithHowy } from '@/components/brand/howy-name';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import live from '@/routes/account/live';
import type { WelcomeCandidate } from '@/types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Loaded once the dialog opens; undefined until then. */
    candidates: WelcomeCandidate[] | undefined;
};

/**
 * Going live: who gets Howy's welcome now, every one of them ticked, and the
 * button that starts it. Whoever mails Howy later, or is added later, gets the
 * welcome then.
 */
export function GoLiveDialog({ open, onOpenChange, candidates }: Props) {
    const t = useTranslations();
    const [picked, setPicked] = useState<number[]>([]);

    useEffect(() => {
        if (open) {
            router.reload({ only: ['welcomeList'] });
        }
    }, [open]);

    useEffect(() => {
        setPicked((candidates ?? []).map((candidate) => candidate.id));
    }, [candidates]);

    const toggle = (id: number, on: boolean) =>
        setPicked(
            on ? [...picked, id] : picked.filter((other) => other !== id),
        );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="gap-[22px] rounded-2xl bg-cc-panel p-7 sm:max-w-[560px]">
                <div className="flex flex-col gap-1 pr-8">
                    <DialogTitle className="cc-subtitle">
                        <WithHowy text={t('Put Howy live')} />
                    </DialogTitle>
                    <DialogDescription className="cc-caption">
                        <WithHowy
                            text={t(
                                'From now on Howy sends its own mails to colleagues: the welcome, then how it works. Whoever mails Howy later, or is added later, gets the welcome then.',
                            )}
                        />
                    </DialogDescription>
                </div>

                {candidates === undefined ? (
                    <div className="flex justify-center py-8">
                        <Spinner />
                    </div>
                ) : candidates.length === 0 ? (
                    <p className="rounded-xl bg-cc-bg px-4 py-3 text-[14px] leading-[1.55]">
                        <WithHowy
                            text={t(
                                'Nobody is waiting for the welcome yet. Colleagues get it with their first mail to Howy, or when you add them.',
                            )}
                        />
                    </p>
                ) : (
                    <div className="flex flex-col gap-2">
                        <div className="flex items-center justify-between gap-3">
                            <span className="cc-label">
                                {t('The welcome goes to :count of :total', {
                                    count: picked.length,
                                    total: candidates.length,
                                })}
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                className="h-8 px-2.5 text-[13px] text-cc-subtle"
                                onClick={() =>
                                    setPicked(
                                        picked.length === candidates.length
                                            ? []
                                            : candidates.map(
                                                  (candidate) => candidate.id,
                                              ),
                                    )
                                }
                            >
                                {picked.length === candidates.length
                                    ? t('Select none')
                                    : t('Select all')}
                            </Button>
                        </div>
                        <ul className="flex max-h-[320px] flex-col overflow-y-auto rounded-xl border border-cc-border">
                            {candidates.map((candidate) => (
                                <li
                                    key={candidate.id}
                                    className="border-b border-cc-border last:border-b-0"
                                >
                                    <label className="flex cursor-pointer items-center gap-3 px-3.5 py-2.5 hover:bg-cc-bg">
                                        <Checkbox
                                            checked={picked.includes(
                                                candidate.id,
                                            )}
                                            onCheckedChange={(on) =>
                                                toggle(
                                                    candidate.id,
                                                    on === true,
                                                )
                                            }
                                            className="size-[18px] border-[1.5px] border-cc-border-strong bg-cc-panel"
                                        />
                                        <span className="flex min-w-0 flex-col">
                                            <span className="truncate text-[14px] font-medium">
                                                {candidate.name ??
                                                    candidate.email}
                                            </span>
                                            {candidate.name !== null && (
                                                <span className="truncate text-[12.5px] text-cc-subtle">
                                                    {candidate.email}
                                                </span>
                                            )}
                                        </span>
                                    </label>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                <Form
                    {...live.store.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="flex flex-wrap items-center justify-end gap-2.5"
                >
                    {({ processing }) => (
                        <>
                            {picked.map((id, index) => (
                                <input
                                    key={id}
                                    type="hidden"
                                    name={`colleagues[${index}]`}
                                    value={id}
                                />
                            ))}
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
                                disabled={
                                    processing || candidates === undefined
                                }
                            >
                                <Rocket />
                                {picked.length === 0
                                    ? t('Go live')
                                    : picked.length === 1
                                      ? t('Go live and send 1 welcome mail')
                                      : t(
                                            'Go live and send :count welcome mails',
                                            {
                                                count: picked.length,
                                            },
                                        )}
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
