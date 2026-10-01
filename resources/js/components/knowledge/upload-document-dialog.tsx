import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslations } from '@/hooks/use-translations';
import documentRoutes from '@/routes/knowledge/documents';
import type { Option } from '@/types';
import { FileDropzone } from './file-dropzone';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    types: Option[];
    defaultLanguage: string;
    maxUploadMegabytes: number;
};

type UploadForm = {
    file: File | null;
    title: string;
    type: string;
    is_core: boolean;
    language: string;
    effective_date: string;
};

export function UploadDocumentDialog({
    open,
    onOpenChange,
    types,
    defaultLanguage,
    maxUploadMegabytes,
}: Props) {
    const t = useTranslations();
    const form = useForm<UploadForm>({
        file: null,
        title: '',
        type: 'handbook',
        is_core: true,
        language: defaultLanguage,
        effective_date: '',
    });

    const close = (next: boolean) => {
        if (!next) {
            form.reset();
            form.clearErrors();
        }

        onOpenChange(next);
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(documentRoutes.store().url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => close(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={close}>
            <DialogContent className="rounded-2xl bg-cc-panel p-7 sm:max-w-[560px]">
                <DialogTitle className="cc-subtitle">
                    {t('Add a document')}
                </DialogTitle>
                <DialogDescription className="cc-caption">
                    {t(
                        'The document is processed in the background: read, split into sections and made searchable. You can follow the progress in the list.',
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

                    <div className="grid gap-2">
                        <Label htmlFor="title">{t('Title')}</Label>
                        <Input
                            id="title"
                            value={form.data.title}
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                            placeholder={t('Taken from the file name if empty')}
                            className="h-11"
                        />
                        <InputError message={form.errors.title} />
                    </div>

                    <div className="grid gap-5 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="type">{t('Type')}</Label>
                            <Select
                                value={form.data.type}
                                onValueChange={(value) =>
                                    form.setData('type', value)
                                }
                            >
                                <SelectTrigger
                                    id="type"
                                    className="h-11 w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {types.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.type} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="language">{t('Language')}</Label>
                            <Select
                                value={form.data.language}
                                onValueChange={(value) =>
                                    form.setData('language', value)
                                }
                            >
                                <SelectTrigger
                                    id="language"
                                    className="h-11 w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="nl">
                                        Nederlands
                                    </SelectItem>
                                    <SelectItem value="en">English</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.language} />
                        </div>
                    </div>

                    <div className="grid gap-2 sm:max-w-[50%]">
                        <Label htmlFor="effective_date">
                            {t('Effective from')}
                        </Label>
                        <Input
                            id="effective_date"
                            type="date"
                            value={form.data.effective_date}
                            onChange={(event) =>
                                form.setData(
                                    'effective_date',
                                    event.target.value,
                                )
                            }
                            className="h-11"
                        />
                        <InputError message={form.errors.effective_date} />
                    </div>

                    <label className="flex cursor-pointer items-start gap-3 rounded-xl border-[1.5px] border-cc-border bg-cc-bg p-4">
                        <Checkbox
                            checked={form.data.is_core}
                            onCheckedChange={(checked) =>
                                form.setData('is_core', checked === true)
                            }
                            className="mt-0.5"
                        />
                        <span className="flex flex-col gap-0.5">
                            <span className="text-[14px] font-semibold">
                                {t('Core document')}
                            </span>
                            <span className="cc-caption">
                                {t(
                                    'Its rules are extracted as facts, which incoming emails can later be checked against.',
                                )}
                            </span>
                        </span>
                    </label>

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
                            {form.processing ? t('Uploading…') : t('Upload')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
