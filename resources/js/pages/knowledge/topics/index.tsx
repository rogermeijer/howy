import { Head, Link, router } from '@inertiajs/react';
import { Check, Folder, FolderPlus } from 'lucide-react';
import { useState } from 'react';
import KnowledgeTopicController from '@/actions/App/Http/Controllers/Knowledge/KnowledgeTopicController';
import { PageHeader } from '@/components/cc/page-header';
import { TopicFormDialog } from '@/components/knowledge/topic-dialogs';
import { Button } from '@/components/ui/button';
import { sourcesLabel } from '@/components/knowledge/labels';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { knowledge } from '@/routes';
import topicRoutes from '@/routes/knowledge/topics';
import type { TopicTreeNode } from '@/types';

type Props = { topics: TopicTreeNode[]; maxDepth: number; canManage: boolean };

/**
 * The whole folder tree on one page: the place to review what AI proposed.
 */
export default function TopicTreePage({ topics, maxDepth, canManage }: Props) {
    const t = useTranslations();
    const [creating, setCreating] = useState(false);
    const newCount = topics.filter((topic) => topic.isNew).length;

    return (
        <>
            <Head title={t('Folders')} />

            <div className="flex flex-col gap-7">
                <PageHeader
                    title={t('Folders')}
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
                                {t('Folders')}
                            </span>
                        </div>
                    }
                    description={t(
                        'Folders are proposed by AI from your documents; whatever you rename, move or create yourself stays as you made it.',
                    )}
                    actions={
                        canManage && (
                            <>
                                {newCount > 0 && (
                                    <Button
                                        variant="outline"
                                        className="h-10"
                                        onClick={() =>
                                            router.post(
                                                KnowledgeTopicController.approveAll.url(),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <Check strokeWidth={2.5} />
                                        {t('Approve all :count new', {
                                            count: newCount,
                                        })}
                                    </Button>
                                )}
                                <Button
                                    className="h-10"
                                    onClick={() => setCreating(true)}
                                >
                                    <FolderPlus />
                                    {t('New folder')}
                                </Button>
                            </>
                        )
                    }
                />

                <div className="cc-panel overflow-hidden">
                    {topics.length === 0 ? (
                        <p className="cc-caption px-6 py-10 text-center">
                            {t('No folders yet.')}
                        </p>
                    ) : (
                        <ul>
                            {topics.map((topic) => (
                                <li
                                    key={topic.id}
                                    className={cn(
                                        'flex items-center gap-3 border-b border-cc-border py-3 pr-5 last:border-b-0',
                                        topic.depth === 1 && 'bg-cc-bg/60',
                                    )}
                                    style={{
                                        paddingLeft:
                                            20 + (topic.depth - 1) * 28,
                                    }}
                                >
                                    <Folder
                                        className="size-[18px] shrink-0 text-cc-ink"
                                        strokeWidth={1.8}
                                        fill={
                                            topic.isNew ? '#ffe4ec' : '#ecebe6'
                                        }
                                    />
                                    <div className="flex min-w-0 flex-1 flex-col">
                                        <Link
                                            href={topicRoutes.show(topic.id)}
                                            className={cn(
                                                'truncate text-[14px] hover:underline',
                                                topic.depth === 1
                                                    ? 'font-semibold'
                                                    : 'font-medium',
                                            )}
                                        >
                                            {topic.name}
                                        </Link>
                                        {topic.description && (
                                            <span className="cc-caption truncate">
                                                {topic.description}
                                            </span>
                                        )}
                                    </div>
                                    <span className="cc-caption shrink-0">
                                        {sourcesLabel(
                                            t,
                                            topic.sourcesCount ?? 0,
                                        )}
                                    </span>
                                    {topic.isNew ? (
                                        canManage ? (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    router.post(
                                                        KnowledgeTopicController.approve.url(
                                                            topic.id,
                                                        ),
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                                className="flex h-8 shrink-0 cursor-pointer items-center gap-1.5 rounded-lg border-[1.5px] border-cc-border-strong bg-cc-panel px-2.5 text-[12px] font-semibold transition-colors hover:border-cc-ink"
                                            >
                                                <Check
                                                    className="size-3.5"
                                                    strokeWidth={2.5}
                                                />
                                                {t('Approve')}
                                            </button>
                                        ) : (
                                            <span className="rounded-md bg-cc-accent-tint px-1.5 py-0.5 text-[11px] font-semibold text-cc-accent-deep">
                                                {t('new (AI)')}
                                            </span>
                                        )
                                    ) : (
                                        <span className="w-[88px] shrink-0" />
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>

            {canManage && (
                <TopicFormDialog
                    key={String(creating)}
                    open={creating}
                    onOpenChange={setCreating}
                    folders={topics.map((topic) => ({
                        id: topic.id,
                        name: topic.name,
                        depth: topic.depth,
                        path: '',
                    }))}
                    maxDepth={maxDepth}
                />
            )}
        </>
    );
}
