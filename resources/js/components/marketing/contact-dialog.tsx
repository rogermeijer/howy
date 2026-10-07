import { type ReactNode, useState } from 'react';
import { Check, Copy, Mail } from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useTranslations } from '@/hooks/use-translations';

/** Where questions go. An address, not copy: the same in every language. */
export const CONTACT_EMAIL = 'hello@howyknows.com';

/**
 * The modal behind every "contact us" link: the address to write to, as a
 * mailto link and one click to copy. `children` is the trigger.
 */
export function ContactDialog({ children }: { children: ReactNode }) {
    const t = useTranslations();
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(CONTACT_EMAIL);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            // No clipboard access (an insecure origin, a denied permission):
            // the address is on screen and the mailto link still works.
        }
    };

    return (
        <Dialog onOpenChange={() => setCopied(false)}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent className="flex flex-col gap-6 rounded-[20px] border-cc-border bg-cc-panel p-[clamp(24px,5vw,36px)] text-cc-ink sm:max-w-[460px]">
                <div className="flex flex-col gap-3 pr-6">
                    <DialogTitle className="text-[22px] leading-tight font-extrabold tracking-[-0.03em]">
                        {t('Get in touch')}
                    </DialogTitle>
                    <DialogDescription className="text-[15px] leading-[1.55] text-cc-muted">
                        {t(
                            'Questions about Howy, the private beta or your team? Email us and a person will get back to you.',
                        )}
                    </DialogDescription>
                </div>

                <div className="flex items-center gap-2 rounded-[14px] border-[1.5px] border-cc-border-strong bg-white p-2 pl-4">
                    <Mail
                        aria-hidden="true"
                        className="size-5 shrink-0 text-cc-accent-deep"
                        strokeWidth={2}
                    />
                    <a
                        href={`mailto:${CONTACT_EMAIL}`}
                        className="min-w-0 flex-1 truncate text-[16px] font-semibold text-cc-ink no-underline transition-colors hover:text-cc-accent-deep"
                    >
                        {CONTACT_EMAIL}
                    </a>
                    <button
                        type="button"
                        onClick={copy}
                        className="inline-flex h-10 shrink-0 cursor-pointer items-center gap-1.5 rounded-[10px] px-3 text-[13px] font-semibold text-cc-muted transition-colors hover:bg-cc-raised hover:text-cc-ink"
                    >
                        {copied ? (
                            <Check
                                aria-hidden="true"
                                className="size-4 text-cc-accent-deep"
                                strokeWidth={2.6}
                            />
                        ) : (
                            <Copy aria-hidden="true" className="size-4" />
                        )}
                        <span aria-live="polite">
                            {copied ? t('Copied') : t('Copy address')}
                        </span>
                    </button>
                </div>

                <a
                    href={`mailto:${CONTACT_EMAIL}`}
                    className="inline-flex h-12 w-full items-center justify-center gap-2 rounded-full bg-cc-accent text-[15px] font-bold text-cc-ink no-underline transition-colors hover:bg-cc-accent-hover"
                >
                    {t('Write us an email')}
                </a>
            </DialogContent>
        </Dialog>
    );
}
