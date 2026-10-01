import { Head, Link, router, usePoll } from '@inertiajs/react';
import {
    FilePlus2,
    FileText,
    MoreHorizontal,
    Plus,
    RefreshCw,
    Trash2,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { PageHeader } from '@/components/cc/page-header';
import { DeleteDocumentDialog } from '@/components/knowledge/delete-document-dialog';
import { DocumentTypeBadge } from '@/components/knowledge/document-type-badge';
import { formatBytes, isProcessing } from '@/components/knowledge/labels';
import { NewVersionDialog } from '@/components/knowledge/new-version-dialog';
import { StatusPipeline } from '@/components/knowledge/status-pipeline';
import { UploadDocumentDialog } from '@/components/knowledge/upload-document-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { knowledge } from '@/routes';
import documentRoutes from '@/routes/knowledge/documents';
import type { KnowledgeDocument, Option } from '@/types';

type Props = {
    documents: KnowledgeDocument[];
    types: Option[];
    defaultLanguage: string;
    maxUploadMegabytes: number;
    canManage: boolean;
};

const rowGrid =
    'grid grid-cols-[20px_minmax(0,1fr)_auto] gap-x-3 gap-y-2 lg:grid-cols-[28px_minmax(0,1fr)_150px_210px_110px_40px] lg:gap-5 items-center';

export default function Documents({
    documents,
    types,
    defaultLanguage,
    maxUploadMegabytes,
    canManage,
}: Props) {
    const t = useTranslations();
    const formatDate = useFormatDate();
    const [uploading, setUploading] = useState(false);
    const [versioning, setVersioning] = useState<KnowledgeDocument | null>(
        null,
    );
    const [deleting, setDeleting] = useState<KnowledgeDocument | null>(null);

    const processing = documents.some((document) =>
        isProcessing(document.version?.status),
    );

    // Follow the pipeline while anything is still being processed.
    const { start, stop } = usePoll(
        3000,
        { only: ['documents'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (processing) {
            start();
        } else {
            stop();
        }
    }, [processing, start, stop]);

    return (
        <>
            <Head title={t('Documents')} />

            <div className="flex flex-col gap-7">
                <PageHeader
                    title={t('Documents')}
                    breadcrumb={
                        <div className="cc-caption flex items-center gap-2">
                            <Link
                                href={knowledge()}
                                className="text-cc-subtle transition-colors hover:text-cc-ink"
                            >
                                {t('Knowledge base')}
                            </Link>
                            <span>/</span>
                            <span className="font-medium text-cc-ink">
                                {t('Documents')}
                            </span>
                        </div>
                    }
                    description={t(
                        'Handbooks, policies and manuals. Every answer from the knowledge base points back to the document, version, section and page it came from.',
                    )}
                    actions={
                        canManage && (
                            <Button
                                className="h-10"
                                onClick={() => setUploading(true)}
                            >
                                <Plus strokeWidth={2.25} />
                                {t('Add document')}
                            </Button>
                        )
                    }
                />

                {documents.length === 0 ? (
                    <div className="cc-panel flex flex-col items-center gap-3 px-6 py-14 text-center">
                        <div className="flex size-14 items-center justify-center rounded-2xl bg-cc-accent-tint text-cc-accent-deep">
                            <FileText className="size-[26px]" />
                        </div>
                        <p className="text-[15px] font-semibold">
                            {t('No documents yet')}
                        </p>
                        <p className="cc-caption max-w-[420px]">
                            {t(
                                'Upload a staff handbook or manual as PDF or Word. It becomes searchable, and its rules can later be checked against incoming emails.',
                            )}
                        </p>
                        {canManage ? (
                            <Button
                                className="mt-1 h-10"
                                onClick={() => setUploading(true)}
                            >
                                <Plus strokeWidth={2.25} />
                                {t('Add document')}
                            </Button>
                        ) : (
                            <p className="cc-caption">
                                {t(
                                    'An administrator of this account can add documents.',
                                )}
                            </p>
                        )}
                    </div>
                ) : (
                    <div className="cc-panel overflow-hidden">
                        <div
                            className={cn(
                                rowGrid,
                                'cc-label hidden border-b border-cc-border px-6 py-2.5 lg:grid',
                            )}
                        >
                            <div />
                            <div>{t('Name')}</div>
                            <div>{t('Type')}</div>
                            <div>{t('Status')}</div>
                            <div>{t('Updated')}</div>
                            <div />
                        </div>

                        {documents.map((document) => (
                            <div
                                key={document.id}
                                className={cn(
                                    rowGrid,
                                    'border-b border-cc-border px-6 py-3.5 last:border-b-0',
                                )}
                            >
                                <FileText
                                    className="size-5 text-cc-ink"
                                    strokeWidth={1.8}
                                />
                                <div className="flex min-w-0 flex-col gap-0.5">
                                    <Link
                                        href={documentRoutes.show(document.id)}
                                        className="truncate text-[15px] font-semibold hover:underline hover:underline-offset-[3px]"
                                    >
                                        {document.title}
                                    </Link>
                                    {document.version && (
                                        <div className="cc-caption truncate">
                                            {[
                                                document.version.filename,
                                                t('version :number', {
                                                    number: document.version
                                                        .number,
                                                }),
                                                formatBytes(
                                                    document.version.sizeBytes,
                                                ),
                                                document.version.pageCount
                                                    ? t(':count pages', {
                                                          count: document
                                                              .version
                                                              .pageCount,
                                                      })
                                                    : null,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </div>
                                    )}
                                </div>
                                <div className="col-start-2 lg:col-start-auto">
                                    <DocumentTypeBadge
                                        type={document.type}
                                        isCore={document.isCore}
                                    />
                                </div>
                                <div className="col-start-2 lg:col-start-auto">
                                    {document.version && (
                                        <StatusPipeline
                                            status={document.version.status}
                                            error={document.version.error}
                                            className="max-w-[210px]"
                                        />
                                    )}
                                </div>
                                <div className="cc-caption col-start-2 lg:col-start-auto">
                                    {document.updatedAt &&
                                        formatDate(document.updatedAt, {
                                            dateStyle: undefined,
                                            day: 'numeric',
                                            month: 'short',
                                        })}
                                </div>
                                <div className="col-start-3 row-start-1 flex justify-end lg:col-start-auto lg:row-start-auto">
                                    {canManage && (
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <button
                                                    type="button"
                                                    aria-label={t('Actions')}
                                                    className="flex size-8 cursor-pointer items-center justify-center rounded-lg text-cc-subtle transition-colors hover:bg-cc-raised hover:text-cc-ink"
                                                >
                                                    <MoreHorizontal className="size-4" />
                                                </button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        setVersioning(document)
                                                    }
                                                >
                                                    <FilePlus2 />
                                                    {t('New version')}
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        router.post(
                                                            documentRoutes.reprocess(
                                                                document.id,
                                                            ).url,
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <RefreshCw />
                                                    {t('Process again')}
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem
                                                    variant="destructive"
                                                    onSelect={() =>
                                                        setDeleting(document)
                                                    }
                                                >
                                                    <Trash2 />
                                                    {t('Remove')}
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {canManage && (
                <>
                    <UploadDocumentDialog
                        open={uploading}
                        onOpenChange={setUploading}
                        types={types}
                        defaultLanguage={defaultLanguage}
                        maxUploadMegabytes={maxUploadMegabytes}
                    />
                    <NewVersionDialog
                        document={versioning}
                        onOpenChange={(open) => !open && setVersioning(null)}
                        maxUploadMegabytes={maxUploadMegabytes}
                    />
                    <DeleteDocumentDialog
                        document={deleting}
                        onOpenChange={(open) => !open && setDeleting(null)}
                    />
                </>
            )}
        </>
    );
}
