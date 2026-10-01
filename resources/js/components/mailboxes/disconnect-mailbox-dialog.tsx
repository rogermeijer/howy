import { Form } from '@inertiajs/react';
import MailboxController from '@/actions/App/Http/Controllers/Mailboxes/MailboxController';
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
import type { Mailbox } from '@/types';

type Props = {
    mailbox: Mailbox | null;
    onOpenChange: (open: boolean) => void;
};

export function DisconnectMailboxDialog({ mailbox, onOpenChange }: Props) {
    const t = useTranslations();

    return (
        <Dialog open={mailbox !== null} onOpenChange={onOpenChange}>
            <DialogContent className="rounded-2xl bg-cc-panel p-7 sm:max-w-[480px]">
                <DialogTitle className="cc-subtitle">
                    {t('Disconnect :address?', {
                        address: mailbox?.emailAddress ?? '',
                    })}
                </DialogTitle>
                <DialogDescription className="cc-caption">
                    {t(
                        'New emails will no longer come in. Emails already stored stay, and so do the knowledge base files that link to them.',
                    )}
                </DialogDescription>

                {mailbox && (
                    <Form
                        {...MailboxController.destroy.form(mailbox.id)}
                        options={{ preserveScroll: true }}
                        onSuccess={() => onOpenChange(false)}
                    >
                        {({ processing }) => (
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="outline" className="h-10">
                                        {t('Cancel')}
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    className="h-10"
                                    disabled={processing}
                                >
                                    {t('Disconnect')}
                                </Button>
                            </DialogFooter>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}
