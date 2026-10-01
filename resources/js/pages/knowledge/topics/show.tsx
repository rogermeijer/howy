import { Head, Link, router } from '@inertiajs/react';
import {
    Check,
    FolderPlus,
    MoreHorizontal,
    Search as SearchIcon,
    Sparkles,
    X,
} from 'lucide-react';
import { useState } from 'react';
import KnowledgeTopicController from '@/actions/App/Http/Controllers/Knowledge/KnowledgeTopicController';
import { PageHeader } from '@/components/cc/page-header';
import { FactList } from '@/components/knowledge/fact-list';
import { SourceChip } from '@/components/knowledge/source-chip';
import {
    DeleteTopicDialog,
    TopicFormDialog,
    TopicTargetDialog,
} from '@/components/knowledge/topic-dialogs';
import { TopicFolderCard } from '@/components/knowledge/topic-folder-card';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { knowledge } from '@/routes';
import knowledgeRoutes from '@/routes/knowledge';
import topicRoutes from '@/routes/knowledge/topics';
import type {
    FolderOption,
    TopicDetail,
    TopicFact,
    TopicFolder,
    TopicSource,
} from '@/types';

type Props = {
    topic: TopicDetail;
    ancestors: { id: number; name: string }[];
    children: TopicFolder[];
    facts: TopicFact[];
    sources: TopicSource[];
    includeSubfolders: boolean;
    folders: FolderOption[];
    maxDepth: number;
    canManage: boolean;
};

type Dialog = 'rename' | 'move' | 'merge' | 'delete' | 'subfolder' | null;

const kindLabels = {
    theme: 'Theme',
    project: 'Project',
    party: 'Relation',
} as const;

export default function TopicPage({
    topic,
    ancestors,
    children,
    facts,
    sources,
    includeSubfolders,
    folders,
    maxDepth,
    canManage,
}: Props) {
    const t = useTranslations();
    const [dialog, setDialog] = useState<Dialog>(null);
    const [q, setQ] = useState('');

    const close = (open: boolean) => !open && setDialog(null);

    return (
        <>
            <Head title={topic.name} />

            <div className="flex flex-col gap-7">
                <PageHeader
                    title={topic.name}
                    breadcrumb={
                        <div className="cc-caption flex flex-wrap items-center gap-2">
                            <Link
                                href={knowledge()}
                                className="text-cc-subtle transition-colors hover:text-cc-ink"
                            >
                                {t('Knowledge base')}
                            </Link>
                            {ancestors.map((ancestor) => (
                                <span
                                    key={ancestor.id}
                                    className="flex items-center gap-2"
                                >
                                    <span>/</span>
                                    <Link
                                        href={topicRoutes.show(ancestor.id)}
                                        className="text-cc-subtle transition-colors hover:text-cc-ink"
                                    >
                                        {ancestor.name}
                                    </Link>
                                </span>
                            ))}
                            <span>/</span>
                            <span className="font-medium text-cc-ink">
                                {topic.name}
                            </span>
                        </div>
                    }
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            {topic.kind !== 'theme' && (
                                <span className="rounded-md bg-cc-raised px-2 py-0.5 text-[12px] font-semibold text-cc-muted">
                                    {t(kindLabels[topic.kind])}
                                </span>
                            )}
                            {topic.isNew && (
                                <span className="rounded-md bg-cc-accent-tint px-2 py-0.5 text-[12px] font-semibold text-cc-accent-deep">
                                    {t('new (AI)')}
                                </span>
                            )}
                            {topic.description && (
                                <span>{topic.description}</span>
                            )}
                        </span>
                    }
                    actions={
                        canManage && (
                            <>
                                {topic.isNew && (
                                    <Button
                                        className="h-10"
                                        onClick={() =>
                                            router.post(
                                                KnowledgeTopicController.approve.url(
                                                    topic.id,
                                                ),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <Check strokeWidth={2.5} />
                                        {t('Approve')}
                                    </Button>
                                )}
                                {topic.depth < maxDepth && (
                                    <Button
                                        variant="outline"
                                        className="h-10"
                                        onClick={() => setDialog('subfolder')}
                                    >
                                        <FolderPlus />
                                        {t('Subfolder')}
                                    </Button>
                                )}
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button
                                            variant="outline"
                                            className="h-10 w-10 p-0"
                                            aria-label={t('Actions')}
                                        >
                                            <MoreHorizontal />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem
                                            onSelect={() => setDialog('rename')}
                                        >
                                            {t('Rename')}
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            onSelect={() => setDialog('move')}
                                        >
                                            {t('Move to…')}
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            onSelect={() => setDialog('merge')}
                                        >
                                            {t('Merge into…')}
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            variant="destructive"
                                            onSelect={() => setDialog('delete')}
                                        >
                                            {t('Remove')}
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </>
                        )
                    }
                />

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (q.trim() !== '') {
                            router.get(knowledgeRoutes.search().url, {
                                q,
                                topic: topic.id,
                            });
                        }
                    }}
                    className="flex h-11 items-center gap-3 rounded-xl border-[1.5px] border-cc-border-strong bg-cc-panel px-4 focus-within:border-cc-ink"
                >
                    <SearchIcon className="size-4 shrink-0 text-cc-subtle" />
                    <input
                        type="search"
                        value={q}
                        onChange={(event) => setQ(event.target.value)}
                        placeholder={t('Search in :name', { name: topic.name })}
                        aria-label={t('Search in :name', { name: topic.name })}
                        className="min-w-0 flex-1 border-none bg-transparent text-[14px] text-cc-ink outline-none placeholder:text-cc-subtle"
                    />
                </form>

                {children.length > 0 && (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {children.map((child) => (
                            <TopicFolderCard
                                key={child.id}
                                topic={child}
                                href={topicRoutes.show(child.id)}
                                meta={t(':count sources', {
                                    count: child.sourcesCount ?? 0,
                                })}
                            />
                        ))}
                    </div>
                )}

                <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_380px]">
                    <div className="flex min-w-0 flex-col gap-5">
                        <section className="cc-panel flex flex-col gap-3 p-6">
                            <h2 className="cc-label flex items-center gap-1.5">
                                <Sparkles className="size-3.5" />
                                {t('Overview')}
                            </h2>
                            {topic.summary ? (
                                <p className="text-[15px] leading-relaxed">
                                    {topic.summary}
                                </p>
                            ) : (
                                <p className="cc-caption">
                                    {topic.summaryStale
                                        ? t(
                                              'The overview is being written from the content of this folder.',
                                          )
                                        : t(
                                              'There is nothing in this folder yet.',
                                          )}
                                </p>
                            )}
                        </section>

                        <section className="cc-panel flex flex-col gap-4 p-6">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <h2 className="cc-label">{t('Facts')}</h2>
                                {children.length > 0 && (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            router.get(
                                                topicRoutes.show(topic.id).url,
                                                includeSubfolders
                                                    ? { direct: 1 }
                                                    : {},
                                                {
                                                    preserveScroll: true,
                                                    preserveState: true,
                                                },
                                            )
                                        }
                                        className={cn(
                                            'cc-pill',
                                            includeSubfolders &&
                                                'cc-pill-active',
                                        )}
                                        aria-pressed={includeSubfolders}
                                    >
                                        {t('Including subfolders')}
                                    </button>
                                )}
                            </div>
                            {facts.length === 0 ? (
                                <p className="cc-caption">
                                    {t(
                                        'No facts in this folder yet. Facts come from core documents.',
                                    )}
                                </p>
                            ) : (
                                <FactList facts={facts} />
                            )}
                        </section>
                    </div>

                    <section className="cc-panel flex flex-col">
                        <h2 className="cc-label border-b border-cc-border px-5 py-3">
                            {t('Sources')} · {sources.length}
                        </h2>
                        {sources.length === 0 ? (
                            <p className="cc-caption px-5 py-6">
                                {t('No sources filed here yet.')}
                            </p>
                        ) : (
                            <ul className="flex flex-col">
                                {sources.map((source) => (
                                    <li
                                        key={source.linkId}
                                        className="flex flex-col gap-2 border-b border-cc-border px-5 py-4 last:border-b-0"
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <SourceChip
                                                documentId={source.documentId}
                                                title={source.documentTitle}
                                                versionNumber={
                                                    source.versionNumber
                                                }
                                                headingPath={source.headingPath}
                                                pageFrom={source.pageFrom}
                                                sectionId={source.sectionId}
                                            />
                                            {canManage &&
                                                source.via === null && (
                                                    <button
                                                        type="button"
                                                        aria-label={t(
                                                            'Remove from this folder',
                                                        )}
                                                        title={t(
                                                            'Remove from this folder',
                                                        )}
                                                        onClick={() =>
                                                            router.delete(
                                                                KnowledgeTopicController.unlink.url(
                                                                    [
                                                                        topic.id,
                                                                        source.linkId,
                                                                    ],
                                                                ),
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                        className="flex size-7 shrink-0 cursor-pointer items-center justify-center rounded-md text-cc-subtle transition-colors hover:bg-cc-raised hover:text-cc-ink"
                                                    >
                                                        <X className="size-3.5" />
                                                    </button>
                                                )}
                                        </div>
                                        {source.summary && (
                                            <p className="cc-caption line-clamp-3">
                                                {source.summary}
                                            </p>
                                        )}
                                        {source.via && (
                                            <span className="text-[12px] text-cc-faint">
                                                {t('via :folder', {
                                                    folder: source.via,
                                                })}
                                            </span>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>
            </div>

            {canManage && (
                <>
                    <TopicFormDialog
                        key={`rename-${topic.id}-${dialog === 'rename'}`}
                        open={dialog === 'rename'}
                        onOpenChange={close}
                        topic={topic}
                        folders={folders}
                        maxDepth={maxDepth}
                    />
                    <TopicFormDialog
                        key={`sub-${topic.id}-${dialog === 'subfolder'}`}
                        open={dialog === 'subfolder'}
                        onOpenChange={close}
                        parentId={topic.id}
                        folders={folders}
                        maxDepth={maxDepth}
                    />
                    <TopicTargetDialog
                        key={`move-${topic.id}`}
                        open={dialog === 'move'}
                        onOpenChange={close}
                        topic={topic}
                        folders={folders}
                        mode="move"
                    />
                    <TopicTargetDialog
                        key={`merge-${topic.id}`}
                        open={dialog === 'merge'}
                        onOpenChange={close}
                        topic={topic}
                        folders={folders}
                        mode="merge"
                    />
                    <DeleteTopicDialog
                        open={dialog === 'delete'}
                        onOpenChange={close}
                        topic={topic}
                    />
                </>
            )}
        </>
    );
}
