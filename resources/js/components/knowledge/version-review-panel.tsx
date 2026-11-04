import { Form, Link } from '@inertiajs/react';
import { Check, FolderOpen, Hourglass, X } from 'lucide-react';
import { useState } from 'react';
import DocumentReviewController from '@/actions/App/Http/Controllers/Knowledge/DocumentReviewController';
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
import topicRoutes from '@/routes/knowledge/topics';
import type { VersionReview } from '@/types';

type Props = {
    documentId: number;
    versionId: number;
    versionNumber: number;
    review: VersionReview;
    canManage: boolean;
};

/**
 * A processed version waiting for a decision: what it replaces, what changes
 * and where it will be filed. Approving publishes it; rejecting keeps it out
 * and remembers the file so it is not proposed again.
 */
export function VersionReviewPanel({
    documentId,
    versionId,
    versionNumber,
    review,
    canManage,
}: Props) {
    const t = useTranslations();
    const [rejecting, setRejecting] = useState(false);

    // A first version has nothing to compare with: every section is new.
    const counts = (
        [
            ['unchanged', t('Unchanged sections')],
            ['changed', t('Changed sections')],
            ['added', t('New sections')],
            ['removed', t('Removed sections')],
        ] as const
    )
        .filter(([key]) => review.replaces !== null || key === 'added')
        .map(([key, label]) => ({ key, label, value: review.sections[key] }));

    return (
        <div className="cc-panel flex flex-col gap-5 border-[1.5px] border-cc-pending-fg/30 p-6">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="flex min-w-0 flex-col gap-1.5">
                    <span className="inline-flex w-fit items-center gap-1.5 rounded-full bg-cc-pending-bg px-2.5 py-1 text-[12px] font-semibold text-cc-pending-fg">
                        <Hourglass className="size-3.5" />
                        {t('Ready for approval')}
                    </span>
                    <h2 className="cc-subtitle">
                        {review.replaces === null
                            ? t(
                                  'Version :version is new to the knowledge base',
                                  {
                                      version: versionNumber,
                                  },
                              )
                            : t('Version :version replaces version :old', {
                                  version: versionNumber,
                                  old: review.replaces,
                              })}
                    </h2>
                    <p className="cc-caption">
                        {review.uploadedBy
                            ? t(
                                  'Uploaded by :name. Nothing of it is searched until it is approved.',
                                  { name: review.uploadedBy },
                              )
                            : t(
                                  'Nothing of it is searched until it is approved.',
                              )}
                    </p>
                </div>

                {canManage && (
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            className="h-10"
                            onClick={() => setRejecting(true)}
                        >
                            <X />
                            {t('Reject')}
                        </Button>
                        <Form
                            {...DocumentReviewController.approve.form([
                                documentId,
                                versionId,
                            ])}
                            options={{ preserveScroll: true }}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    className="h-10"
                                    disabled={processing}
                                >
                                    <Check strokeWidth={2.5} />
                                    {t('Approve')}
                                </Button>
                            )}
                        </Form>
                    </div>
                )}
            </div>

            {review.error && (
                <p className="rounded-lg bg-cc-action-bg px-3 py-2 text-[13px] text-cc-action-fg">
                    {review.error}
                </p>
            )}

            <dl className="grid grid-cols-2 gap-3 sm:grid-cols-5">
                {counts.map((count) => (
                    <div
                        key={count.key}
                        className="flex flex-col gap-0.5 rounded-xl bg-cc-bg px-3 py-2.5"
                    >
                        <dt className="cc-caption">{count.label}</dt>
                        <dd className="text-[18px] font-semibold">
                            {count.value}
                        </dd>
                    </div>
                ))}
                <div className="flex flex-col gap-0.5 rounded-xl bg-cc-bg px-3 py-2.5">
                    <dt className="cc-caption">{t('New facts')}</dt>
                    <dd className="text-[18px] font-semibold">
                        {review.facts}
                    </dd>
                </div>
            </dl>

            {review.summary && (
                <div className="flex flex-col gap-1.5">
                    <span className="cc-label">{t('Summary')}</span>
                    <p className="cc-body">{review.summary}</p>
                </div>
            )}

            {review.folders.length > 0 && (
                <div className="flex flex-wrap items-center gap-2">
                    <FolderOpen className="size-4 text-cc-subtle" />
                    <span className="cc-caption">{t('Filed under')}</span>
                    {review.folders.map((folder) => (
                        <Link
                            key={folder.id}
                            href={topicRoutes.show(folder.id)}
                            className="rounded-md bg-cc-raised px-2 py-1 text-[12px] font-semibold transition-colors hover:bg-cc-border"
                        >
                            {folder.name}
                            {folder.isNew && (
                                <span className="ml-1.5 text-cc-accent-deep">
                                    {t('new')}
                                </span>
                            )}
                        </Link>
                    ))}
                </div>
            )}

            <Dialog open={rejecting} onOpenChange={setRejecting}>
                <DialogContent className="rounded-2xl bg-cc-panel p-7 sm:max-w-[480px]">
                    <DialogTitle className="cc-subtitle">
                        {t('Reject version :version?', {
                            version: versionNumber,
                        })}
                    </DialogTitle>
                    <DialogDescription className="cc-caption">
                        {t(
                            'Everything it would add is removed. The file is remembered, so the same file sent again is not proposed a second time.',
                        )}
                    </DialogDescription>
                    <Form
                        {...DocumentReviewController.reject.form([
                            documentId,
                            versionId,
                        ])}
                        onSuccess={() => setRejecting(false)}
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
                                    {t('Reject')}
                                </Button>
                            </DialogFooter>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
