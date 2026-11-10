import { ExternalLink } from 'lucide-react';
import { WithHowy } from '@/components/brand/howy-name';
import { ConnectMailboxOptions } from '@/components/mailboxes/connect-mailbox-options';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslations } from '@/hooks/use-translations';
import mailboxes from '@/routes/mailboxes';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function ConnectMailboxDialog({ open, onOpenChange }: Props) {
    const t = useTranslations();

    // A full navigation, not an Inertia visit: this leaves the app for Google.
    // Older mail is imported afterwards, from the mailbox's card.
    const href = mailboxes.gmail.redirect.url();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="gap-[22px] rounded-2xl bg-cc-panel p-7 sm:max-w-[560px]">
                <div className="flex flex-col gap-1 pr-8">
                    <DialogTitle className="cc-subtitle">
                        {t('Connect mailbox')}
                    </DialogTitle>
                    <DialogDescription className="cc-caption">
                        <WithHowy
                            text={t(
                                'Connect a shared mailbox, such as support@ or knowledge@. Howy then processes every new email automatically.',
                            )}
                        />
                    </DialogDescription>
                </div>

                <ConnectMailboxOptions />

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <span className="text-[12px] text-cc-subtle">
                        {t('You will go to Google to grant access.')}
                    </span>
                    <div className="flex gap-2.5">
                        <DialogClose asChild>
                            <Button
                                variant="outline"
                                className="h-11 px-[18px]"
                            >
                                {t('Cancel')}
                            </Button>
                        </DialogClose>
                        <Button
                            asChild
                            className="h-11 px-[18px] whitespace-nowrap"
                        >
                            <a href={href}>
                                {t('Go to Google')}
                                <ExternalLink />
                            </a>
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
