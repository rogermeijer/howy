import { Form } from '@inertiajs/react';
import DocumentController from '@/actions/App/Http/Controllers/Knowledge/DocumentController';
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
import type { KnowledgeDocument } from '@/types';

type Props = {
    document: KnowledgeDocument | null;
    onOpenChange: (open: boolean) => void;
};

export function DeleteDocumentDialog({ document, onOpenChange }: Props) {
    const t = useTranslations();

    return (
        <Dialog open={document !== null} onOpenChange={onOpenChange}>
            <DialogContent className="rounded-2xl bg-cc-panel p-7 sm:max-w-[480px]">
                <DialogTitle className="cc-subtitle">
                    {t('Remove :title?', { title: document?.title ?? '' })}
                </DialogTitle>
                <DialogDescription className="cc-caption">
                    {t(
                        'All versions, the original files and everything derived from them — sections, summaries and facts — are removed. This cannot be undone.',
                    )}
                </DialogDescription>

                {document && (
                    <Form
                        {...DocumentController.destroy.form(document.id)}
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
                                    {t('Remove')}
                                </Button>
                            </DialogFooter>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}
