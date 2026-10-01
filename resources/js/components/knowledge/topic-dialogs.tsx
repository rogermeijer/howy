import { Form, useForm } from '@inertiajs/react';
import KnowledgeTopicController from '@/actions/App/Http/Controllers/Knowledge/KnowledgeTopicController';
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
import type { FolderOption } from '@/types';

const ROOT = '__root__';

function folderLabel(folder: FolderOption) {
    return `${'— '.repeat(folder.depth - 1)}${folder.name}`;
}

type Base = { open: boolean; onOpenChange: (open: boolean) => void };

/**
 * Create a folder, or rename one (with `topic`).
 */
export function TopicFormDialog({
    open,
    onOpenChange,
    topic,
    parentId,
    folders,
    maxDepth,
}: Base & {
    topic?: { id: number; name: string; description: string | null };
    parentId?: number | null;
    folders: FolderOption[];
    maxDepth: number;
}) {
    const t = useTranslations();
    const form = useForm({
        name: topic?.name ?? '',
        description: topic?.description ?? '',
        parent_id: parentId ? String(parentId) : ROOT,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (topic) {
            form.transform(({ name, description }) => ({ name, description }));
            form.submit(KnowledgeTopicController.update(topic.id), options);
        } else {
            form.transform((data) => ({
                ...data,
                parent_id:
                    data.parent_id === ROOT ? null : Number(data.parent_id),
            }));
            form.submit(KnowledgeTopicController.store(), options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="rounded-2xl bg-cc-panel p-7 sm:max-w-[500px]">
                <DialogTitle className="cc-subtitle">
                    {topic ? t('Rename folder') : t('New folder')}
                </DialogTitle>
                <DialogDescription className="cc-caption">
                    {topic
                        ? t(
                              'A renamed folder counts as yours: AI will not change it again.',
                          )
                        : t('Folders go at most :depth levels deep.', {
                              depth: maxDepth,
                          })}
                </DialogDescription>
                <form onSubmit={submit} className="flex flex-col gap-5">
                    <div className="grid gap-2">
                        <Label htmlFor="topic-name">{t('Name')}</Label>
                        <Input
                            id="topic-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            className="h-11"
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="topic-description">
                            {t('Description')}
                        </Label>
                        <Input
                            id="topic-description"
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                            className="h-11"
                        />
                        <InputError message={form.errors.description} />
                    </div>
                    {!topic && (
                        <div className="grid gap-2">
                            <Label htmlFor="topic-parent">
                                {t('In folder')}
                            </Label>
                            <Select
                                value={form.data.parent_id}
                                onValueChange={(value) =>
                                    form.setData('parent_id', value)
                                }
                            >
                                <SelectTrigger
                                    id="topic-parent"
                                    className="h-11 w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ROOT}>
                                        {t('Top level')}
                                    </SelectItem>
                                    {folders
                                        .filter(
                                            (folder) => folder.depth < maxDepth,
                                        )
                                        .map((folder) => (
                                            <SelectItem
                                                key={folder.id}
                                                value={String(folder.id)}
                                            >
                                                {folderLabel(folder)}
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.parent_id} />
                        </div>
                    )}
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
                            disabled={form.processing}
                        >
                            {topic ? t('Save') : t('Create')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Move a folder, or merge it into another (mode "merge").
 */
export function TopicTargetDialog({
    open,
    onOpenChange,
    topic,
    folders,
    mode,
}: Base & {
    topic: { id: number; name: string; path?: string };
    folders: FolderOption[];
    mode: 'move' | 'merge';
}) {
    const t = useTranslations();
    const form = useForm({ target: mode === 'move' ? ROOT : '' });

    // A folder cannot go into itself or below itself.
    const own = folders.find((folder) => folder.id === topic.id)?.path;
    const options = folders.filter(
        (folder) =>
            !own || !(folder.path === own || folder.path.startsWith(`${own}.`)),
    );

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const target =
            form.data.target === ROOT ? null : Number(form.data.target);

        if (mode === 'move') {
            form.transform(() => ({ parent_id: target }));
            form.submit(KnowledgeTopicController.move(topic.id), {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });
        } else {
            form.transform(() => ({ target_id: target }));
            form.submit(KnowledgeTopicController.merge(topic.id), {
                onSuccess: () => onOpenChange(false),
            });
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="rounded-2xl bg-cc-panel p-7 sm:max-w-[500px]">
                <DialogTitle className="cc-subtitle">
                    {mode === 'move'
                        ? t('Move :name', { name: topic.name })
                        : t('Merge :name', { name: topic.name })}
                </DialogTitle>
                <DialogDescription className="cc-caption">
                    {mode === 'move'
                        ? t('The folder moves with everything in it.')
                        : t(
                              'Everything in this folder, subfolders included, moves to the folder you choose. Then this folder is removed.',
                          )}
                </DialogDescription>
                <form onSubmit={submit} className="flex flex-col gap-5">
                    <div className="grid gap-2">
                        <Label htmlFor="topic-target">
                            {mode === 'move' ? t('Into') : t('Merge into')}
                        </Label>
                        <Select
                            value={form.data.target}
                            onValueChange={(value) =>
                                form.setData('target', value)
                            }
                        >
                            <SelectTrigger
                                id="topic-target"
                                className="h-11 w-full"
                            >
                                <SelectValue
                                    placeholder={t('Choose a folder')}
                                />
                            </SelectTrigger>
                            <SelectContent>
                                {mode === 'move' && (
                                    <SelectItem value={ROOT}>
                                        {t('Top level')}
                                    </SelectItem>
                                )}
                                {options.map((folder) => (
                                    <SelectItem
                                        key={folder.id}
                                        value={String(folder.id)}
                                    >
                                        {folderLabel(folder)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError
                            message={
                                (
                                    form.errors as Record<
                                        string,
                                        string | undefined
                                    >
                                ).parent_id ??
                                (
                                    form.errors as Record<
                                        string,
                                        string | undefined
                                    >
                                ).target_id
                            }
                        />
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
                                form.processing || form.data.target === ''
                            }
                        >
                            {mode === 'move' ? t('Move') : t('Merge')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function DeleteTopicDialog({
    open,
    onOpenChange,
    topic,
}: Base & { topic: { id: number; name: string } }) {
    const t = useTranslations();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="rounded-2xl bg-cc-panel p-7 sm:max-w-[480px]">
                <DialogTitle className="cc-subtitle">
                    {t('Remove :name?', { name: topic.name })}
                </DialogTitle>
                <DialogDescription className="cc-caption">
                    {t(
                        'Its content and subfolders move to the folder above it. Documents and facts are not removed.',
                    )}
                </DialogDescription>
                <Form
                    {...KnowledgeTopicController.destroy.form(topic.id)}
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
            </DialogContent>
        </Dialog>
    );
}
