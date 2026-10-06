import { Head, Link } from '@inertiajs/react';
import { FileText, FolderOpen, Plus } from 'lucide-react';
import { PageHeader } from '@/components/cc/page-header';
import { WithHowy } from '@/components/brand/howy-name';
import { DocumentTypeBadge } from '@/components/knowledge/document-type-badge';
import { StatusPipeline } from '@/components/knowledge/status-pipeline';
import { TopicFolderCard } from '@/components/knowledge/topic-folder-card';
import { Button } from '@/components/ui/button';
import { useFormatDate } from '@/hooks/use-format-date';
import { sourcesLabel } from '@/components/knowledge/labels';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import documentRoutes from '@/routes/knowledge/documents';
import topicRoutes from '@/routes/knowledge/topics';
import type { KnowledgeDocument, TopicFolder } from '@/types';

type Props = {
    topics: TopicFolder[];
    recent: KnowledgeDocument[];
    documentsCount: number;
    newTopicsCount: number;
    canManage: boolean;
};

const headerGrid =
    'hidden lg:grid grid-cols-[28px_minmax(0,1fr)_240px_120px_130px_40px] items-center gap-5';

export default function Knowledge({
    topics,
    recent,
    documentsCount,
    newTopicsCount,
    canManage,
}: Props) {
    const t = useTranslations();
    const formatDate = useFormatDate();

    return (
        <>
            <Head title={t('Knowledge base')} />

            <div className="flex flex-col gap-7">
                <PageHeader
                    title={t('Knowledge base')}
                    description={t(
                        'Everything your organisation knows, filed in folders. Every file points back to the document or email it came from.',
                    )}
                    actions={
                        <>
                            <Button variant="outline" className="h-10" asChild>
                                <Link href={documentRoutes.index()}>
                                    <FileText />
                                    {t('Documents')}
                                    <span className="text-cc-subtle">
                                        {documentsCount}
                                    </span>
                                </Link>
                            </Button>
                            {canManage && (
                                <Button className="h-10" asChild>
                                    <Link href={documentRoutes.index()}>
                                        <Plus strokeWidth={2.25} />
                                        {t('Add document')}
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                {canManage && newTopicsCount > 0 && (
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-cc-accent-tint/60 px-5 py-4">
                        <p className="text-[14px]">
                            <WithHowy
                                text={t(
                                    'Howy proposed :count new folders. Look them over and approve, rename or merge them.',
                                    { count: newTopicsCount },
                                )}
                            />
                        </p>
                        <Button
                            variant="outline"
                            className="h-9 bg-cc-panel"
                            asChild
                        >
                            <Link href={topicRoutes.index()}>
                                {t('Review folders')}
                            </Link>
                        </Button>
                    </div>
                )}

                {topics.length > 0 ? (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {topics.map((topic) => (
                            <TopicFolderCard
                                key={topic.id}
                                topic={topic}
                                href={topicRoutes.show(topic.id)}
                                meta={sourcesLabel(t, topic.sourcesCount ?? 0)}
                            />
                        ))}
                    </div>
                ) : (
                    <div className="cc-panel flex items-center gap-4 p-5">
                        <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-cc-raised text-cc-ink">
                            <FolderOpen className="size-5" strokeWidth={1.8} />
                        </div>
                        <p className="cc-caption">
                            <WithHowy
                                text={t(
                                    'Folders appear here once documents are processed: Howy proposes a structure of topics, which an administrator can rename, merge and nest.',
                                )}
                            />
                        </p>
                    </div>
                )}

                <div className="flex flex-col gap-3">
                    <h2 className="cc-label">{t('Recently updated')}</h2>

                    {recent.length === 0 ? (
                        <div className="cc-panel cc-caption px-6 py-10 text-center">
                            {t('Nothing in the knowledge base yet.')}
                        </div>
                    ) : (
                        <div className="cc-panel overflow-hidden">
                            <div
                                className={cn(
                                    headerGrid,
                                    'cc-label border-b border-cc-border px-6 py-2.5',
                                )}
                            >
                                <div />
                                <div>{t('Name')}</div>
                                <div>{t('Source')}</div>
                                <div>{t('Type')}</div>
                                <div>{t('Updated')}</div>
                                <div />
                            </div>

                            {recent.map((document) => (
                                <Link
                                    key={document.id}
                                    href={documentRoutes.show(document.id)}
                                    className="cc-row-file border-b border-cc-border px-6 py-3.5 transition-colors last:border-b-0 hover:bg-cc-bg"
                                >
                                    <FileText
                                        className="size-5 text-cc-ink [grid-area:icon]"
                                        strokeWidth={1.8}
                                    />
                                    <div className="flex min-w-0 flex-col gap-0.5 [grid-area:name]">
                                        <div className="truncate text-[15px] font-semibold">
                                            {document.title}
                                        </div>
                                        <div className="cc-caption lg:truncate">
                                            {document.version?.filename}
                                        </div>
                                    </div>
                                    <div className="cc-caption flex min-w-0 items-center gap-2 [grid-area:thread]">
                                        {document.version &&
                                        document.version.status !== 'ready' ? (
                                            <StatusPipeline
                                                status={document.version.status}
                                                error={document.version.error}
                                                className="w-full max-w-[200px]"
                                            />
                                        ) : (
                                            <span className="truncate">
                                                {t('Uploaded by :name', {
                                                    name:
                                                        document.uploadedBy ??
                                                        t('unknown'),
                                                })}
                                            </span>
                                        )}
                                    </div>
                                    <div className="[grid-area:type]">
                                        <DocumentTypeBadge
                                            type={document.type}
                                        />
                                    </div>
                                    <div className="cc-caption text-right [grid-area:updated] lg:text-left">
                                        {document.updatedAt &&
                                            formatDate(document.updatedAt, {
                                                dateStyle: undefined,
                                                day: 'numeric',
                                                month: 'short',
                                            })}
                                    </div>
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
