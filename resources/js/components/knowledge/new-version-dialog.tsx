import { useForm } from '@inertiajs/react';
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
import documentRoutes from '@/routes/knowledge/documents';
import type { KnowledgeDocument } from '@/types';
import { FileDropzone } from './file-dropzone';

type Props = {
    document: KnowledgeDocument | null;
    onOpenChange: (open: boolean) => void;
    maxUploadMegabytes: number;
};

export function NewVersionDialog({
    document,
    onOpenChange,
    maxUploadMegabytes,
}: Props) {
    const t = useTranslations();
    const form = useForm<{ file: File | null }>({ file: null });

    const close = (next: boolean) => {
        if (!next) {
            form.reset();
            form.clearErrors();
        }

        onOpenChange(next);
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (document === null) {
            return;
        }

        form.post(documentRoutes.versions.store(document.id).url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => close(false),
        });
    };

    return (
        <Dialog open={document !== null} onOpenChange={close}>
            <DialogContent className="rounded-2xl bg-cc-panel p-7 sm:max-w-[520px]">
                <DialogTitle className="cc-subtitle">
                    {t('New version of :title', {
                        title: document?.title ?? '',
                    })}
                </DialogTitle>
                <DialogDescription className="cc-caption">
                    {t(
                        'Sections that did not change keep their summaries, facts and index. Only what changed is processed again.',
                    )}
                </DialogDescription>

                <form onSubmit={submit} className="flex flex-col gap-5">
                    <div className="grid gap-2">
                        <FileDropzone
                            file={form.data.file}
                            onChange={(file) => form.setData('file', file)}
                            maxMegabytes={maxUploadMegabytes}
                            invalid={Boolean(form.errors.file)}
                        />
                        <InputError message={form.errors.file} />
                    </div>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button
                                type="button"
                                variant="outline"
                                className="h-10"
                            >
                                {t('Cancel')}
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            className="h-10"
                            disabled={
                                form.processing || form.data.file === null
                            }
                        >
                            {form.processing
                                ? t('Uploading…')
                                : t('Upload version')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
