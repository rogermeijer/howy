import { Head, Link, router, usePoll } from '@inertiajs/react';
import { ExternalLink, FileText, FolderOpen } from 'lucide-react';
import { useEffect, useState } from 'react';
import { PageHeader } from '@/components/cc/page-header';
import { ChunkCard } from '@/components/knowledge/chunk-card';
import { DocumentTypeBadge } from '@/components/knowledge/document-type-badge';
import { isProcessing } from '@/components/knowledge/labels';
import { ProcessingTimeline } from '@/components/knowledge/processing-timeline';
import { SectionTree, changeLabels } from '@/components/knowledge/section-tree';
import { StatusPipeline } from '@/components/knowledge/status-pipeline';
import {
    ReviewDecision,
    ReviewTab,
    useReviewChoices,
} from '@/components/knowledge/version-review';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useFormatDate } from '@/hooks/use-format-date';
import { FactSources } from '@/components/knowledge/fact-sources';
import {
    EmailSourceChip,
    SourceChip,
} from '@/components/knowledge/source-chip';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { knowledge } from '@/routes';
import documentRoutes from '@/routes/knowledge/documents';
import topicRoutes from '@/routes/knowledge/topics';
import type {
    FactView,
    KnowledgeDocument,
    ProcessingStepView,
    SectionDetail,
    SectionNode,
    VersionOption,
    VersionReview,
} from '@/types';

type Props = {
    document: KnowledgeDocument;
    versions: VersionOption[];
    version: { id: number; version_number: number } | null;
    sections: SectionNode[];
    section: SectionDetail | null;
    steps: ProcessingStepView[];
    review: VersionReview | null;
    canManage: boolean;
};

type Tab = 'review' | 'structure' | 'processing';

const factStatusStyles = {
    core: 'bg-cc-knowledge-bg text-cc-knowledge-fg',
    supplementary: 'bg-cc-question-bg text-cc-question-fg',
    expired: 'bg-cc-raised text-cc-muted line-through',
    proposed: 'bg-cc-pending-bg text-cc-pending-fg',
} as const;

const factStatusLabels = {
    core: 'Core',
    supplementary: 'Supplementary',
    expired: 'Expired',
    proposed: 'Proposed',
} as const;

// A fact waiting for approval is told by how it compares with what is known.
const verdictStyles = {
    new: 'bg-cc-knowledge-bg text-cc-knowledge-fg',
    duplicate: 'bg-cc-raised text-cc-muted',
    conflict: 'bg-cc-action-bg text-cc-action-fg',
    unchecked: 'bg-cc-pending-bg text-cc-pending-fg',
    carried: 'bg-cc-raised text-cc-muted',
} as const;

const verdictLabels = {
    new: 'New fact',
    duplicate: 'Already known',
    conflict: 'Conflict',
    unchecked: 'Not checked',
    carried: 'Unchanged',
} as const;

export default function DocumentInspector({
    document,
    versions,
    version,
    sections,
    section,
    steps,
    review,
    canManage,
}: Props) {
    const t = useTranslations();
    const formatDate = useFormatDate();
    // A version waiting for approval opens on what there is to decide.
    const [chosenTab, setTab] = useState<Tab>(review ? 'review' : 'structure');
    const tab: Tab =
        chosenTab === 'review' && !review ? 'structure' : chosenTab;
    const choices = useReviewChoices(
        review,
        document.id,
        review ? (version?.id ?? null) : null,
    );

    const current = versions.find((item) => item.id === version?.id);
    const processing = isProcessing(current?.status);

    const { start, stop } = usePoll(
        3000,
        {
            only: [
                'document',
                'versions',
                'sections',
                'section',
                'steps',
                'review',
            ],
        },
        { autoStart: false },
    );

    useEffect(() => {
        if (processing) {
            start();
        } else {
            stop();
        }
    }, [processing, start, stop]);

    const visit = (params: { version?: number; section?: number }) =>
        router.get(
            documentRoutes.show(document.id).url,
            {
                version: params.version ?? version?.id,
                section: params.section,
            },
            {
                only: params.version ? undefined : ['section'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );

    const fileUrl = (page?: number | null) =>
        current
            ? documentRoutes.versions.file([document.id, current.id]).url +
              (page ? `#page=${page}` : '')
            : '#';

    return (
        <>
            <Head title={document.title} />

            <div className="flex flex-col gap-7">
                <PageHeader
                    title={document.title}
                    breadcrumb={
                        <div className="cc-caption flex flex-wrap items-center gap-2">
                            <Link
                                href={knowledge()}
                                className="text-cc-subtle transition-colors hover:text-cc-ink"
                            >
                                {t('Knowledge base')}
                            </Link>
                            <span>/</span>
                            <Link
                                href={documentRoutes.index()}
                                className="text-cc-subtle transition-colors hover:text-cc-ink"
                            >
                                {t('Documents')}
                            </Link>
                            <span>/</span>
                            <span className="font-medium text-cc-ink">
                                {document.title}
                            </span>
                        </div>
                    }
                    description={
                        <span className="flex flex-wrap items-center gap-3">
                            <DocumentTypeBadge
                                type={document.type}
                                isCore={document.isCore}
                            />
                            {current && (
                                <span>
                                    {current.filename}
                                    {current.createdAt &&
                                        ` · ${t('uploaded :date', { date: formatDate(current.createdAt) })}`}
                                </span>
                            )}
                        </span>
                    }
                    actions={
                        <>
                            {versions.length > 1 && (
                                <Select
                                    value={String(version?.id ?? '')}
                                    onValueChange={(value) =>
                                        visit({ version: Number(value) })
                                    }
                                >
                                    <SelectTrigger
                                        className="h-10 w-[300px]"
                                        aria-label={t('Version')}
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {[...versions].reverse().map((item) => (
                                            <SelectItem
                                                key={item.id}
                                                value={String(item.id)}
                                            >
                                                {t('Version :number', {
                                                    number: item.number,
                                                })}
                                                {item.isCurrent
                                                    ? ` · ${t('live')}`
                                                    : item.status === 'review'
                                                      ? ` · ${t('ready for approval')}`
                                                      : item.status ===
                                                          'rejected'
                                                        ? ` · ${t('rejected')}`
                                                        : ''}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                            {current && (
                                <Button
                                    variant="outline"
                                    className="h-10"
                                    asChild
                                >
                                    <a
                                        href={fileUrl()}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <ExternalLink />
                                        {t('Open original')}
                                    </a>
                                </Button>
                            )}
                        </>
                    }
                />

                {review && current && (
                    <ReviewDecision
                        documentId={document.id}
                        versionId={current.id}
                        versionNumber={current.number}
                        review={review}
                        choices={choices}
                        canManage={canManage}
                    />
                )}

                <div className="flex flex-wrap items-end justify-between gap-3 border-b border-cc-border">
                    <div className="flex flex-wrap gap-1.5" role="tablist">
                        {(
                            [
                                ...(review
                                    ? [
                                          [
                                              'review',
                                              t('Review'),
                                              t(':count to look at', {
                                                  count:
                                                      review.conflicts.length +
                                                      review.newFacts.length +
                                                      review.expiring.length,
                                              }),
                                          ] as const,
                                      ]
                                    : []),
                                [
                                    'structure',
                                    t('Document'),
                                    t(':count sections', {
                                        count: sections.length,
                                    }),
                                ] as const,
                                ['processing', t('Processing'), ''] as const,
                            ] as const
                        ).map(([item, label, meta]) => (
                            <button
                                key={item}
                                type="button"
                                role="tab"
                                aria-selected={tab === item}
                                onClick={() => setTab(item)}
                                className={cn(
                                    '-mb-px inline-flex h-11 cursor-pointer items-center gap-2 border-b-2 px-3.5 text-[14px] transition-colors',
                                    tab === item
                                        ? 'border-cc-ink font-bold text-cc-ink'
                                        : 'border-transparent font-medium text-cc-subtle hover:text-cc-ink',
                                )}
                            >
                                {label}
                                {meta && (
                                    <span className="text-[12px] font-medium text-cc-subtle">
                                        {meta}
                                    </span>
                                )}
                            </button>
                        ))}
                    </div>
                    {current && (
                        <StatusPipeline
                            status={current.status}
                            error={
                                document.version?.id === current.id
                                    ? document.version.error
                                    : null
                            }
                            className="w-[220px]"
                        />
                    )}
                </div>

                {tab === 'review' && review ? (
                    <ReviewTab
                        documentId={document.id}
                        review={review}
                        choices={choices}
                        canManage={canManage}
                        fileUrl={fileUrl()}
                        onOpenSection={(id) => {
                            setTab('structure');
                            visit({ section: id });
                        }}
                    />
                ) : tab === 'processing' ? (
                    <div className="cc-panel px-6 py-2">
                        <ProcessingTimeline steps={steps} />
                    </div>
                ) : sections.length === 0 ? (
                    <div className="cc-panel flex flex-col items-center gap-3 px-6 py-14 text-center">
                        <FileText className="size-7 text-cc-subtle" />
                        <p className="cc-caption max-w-[420px]">
                            {processing
                                ? t(
                                      'The structure appears here as soon as the document has been read.',
                                  )
                                : t('No sections were found in this version.')}
                        </p>
                    </div>
                ) : (
                    <div className="grid items-start gap-5 lg:grid-cols-[320px_minmax(0,1fr)]">
                        <div className="cc-panel overflow-hidden lg:sticky lg:top-24 lg:max-h-[calc(100vh-8rem)] lg:overflow-y-auto">
                            <div className="cc-label border-b border-cc-border px-4 py-2.5">
                                {t(':count sections', {
                                    count: sections.length,
                                })}
                            </div>
                            <SectionTree
                                sections={sections}
                                selectedId={section?.id ?? null}
                                onSelect={(id) => visit({ section: id })}
                                showChanges={(current?.number ?? 1) > 1}
                            />
                        </div>

                        {section && (
                            <div className="flex min-w-0 flex-col gap-5">
                                <div className="cc-panel flex flex-col gap-4 p-6">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="flex min-w-0 flex-col gap-1">
                                            <span className="cc-caption">
                                                {section.headingPath ||
                                                    t('Introduction')}
                                            </span>
                                            <h2 className="cc-subtitle">
                                                {section.heading ??
                                                    t('Introduction')}
                                            </h2>
                                        </div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            {(current?.number ?? 1) > 1 && (
                                                <span className="rounded-md bg-cc-raised px-2 py-1 text-[12px] font-semibold text-cc-muted">
                                                    {t(
                                                        changeLabels[
                                                            section.change
                                                        ],
                                                    )}
                                                </span>
                                            )}
                                            {current?.isPdf &&
                                                section.pageFrom !== null && (
                                                    <Button
                                                        variant="outline"
                                                        className="h-9"
                                                        asChild
                                                    >
                                                        <a
                                                            href={fileUrl(
                                                                section.pageFrom,
                                                            )}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                        >
                                                            <ExternalLink />
                                                            {t('Page :page', {
                                                                page: section.pageFrom,
                                                            })}
                                                        </a>
                                                    </Button>
                                                )}
                                        </div>
                                    </div>

                                    {section.summary && (
                                        <div className="flex flex-col gap-1.5">
                                            <span className="cc-label">
                                                {t('Summary')}
                                            </span>
                                            <p className="cc-body">
                                                {section.summary}
                                            </p>
                                        </div>
                                    )}

                                    {section.topics.length > 0 && (
                                        <div className="flex flex-wrap items-center gap-2">
                                            <FolderOpen className="size-4 text-cc-subtle" />
                                            {section.topics.map((topic) => (
                                                <Link
                                                    key={topic.id}
                                                    href={topicRoutes.show(
                                                        topic.id,
                                                    )}
                                                    className="rounded-md bg-cc-raised px-2 py-1 text-[12px] font-semibold transition-colors hover:bg-cc-border"
                                                >
                                                    {topic.name}
                                                </Link>
                                            ))}
                                        </div>
                                    )}

                                    {section.facts.length > 0 && (
                                        <div className="flex flex-col gap-2">
                                            <span className="cc-label">
                                                {t('Facts')}
                                            </span>
                                            {section.facts.some(
                                                (fact) => fact.verdict,
                                            ) && (
                                                <p className="cc-caption">
                                                    {t(
                                                        'Waiting for approval. On approval a new fact is added, an already known one becomes one more source of the known fact, and an unchanged one carries on as it is.',
                                                    )}
                                                </p>
                                            )}
                                            <ul className="flex flex-col gap-2">
                                                {section.facts.map((fact) => (
                                                    <li
                                                        key={fact.id}
                                                        className="flex flex-wrap items-start gap-x-3 gap-y-2 rounded-lg bg-cc-bg px-3 py-2.5"
                                                    >
                                                        <span
                                                            className={cn(
                                                                'mt-0.5 shrink-0 rounded-md px-1.5 py-0.5 text-[11px] font-semibold',
                                                                fact.verdict
                                                                    ? verdictStyles[
                                                                          fact
                                                                              .verdict
                                                                      ]
                                                                    : factStatusStyles[
                                                                          fact
                                                                              .status
                                                                      ],
                                                            )}
                                                        >
                                                            {t(
                                                                fact.verdict
                                                                    ? verdictLabels[
                                                                          fact
                                                                              .verdict
                                                                      ]
                                                                    : factStatusLabels[
                                                                          fact
                                                                              .status
                                                                      ],
                                                            )}
                                                        </span>
                                                        <span className="cc-body flex-1">
                                                            {fact.statement}
                                                        </span>
                                                        {fact.validFrom && (
                                                            <span className="cc-caption shrink-0">
                                                                {t(
                                                                    'from :date',
                                                                    {
                                                                        date: formatDate(
                                                                            fact.validFrom,
                                                                        ),
                                                                    },
                                                                )}
                                                            </span>
                                                        )}
                                                        {fact.known && (
                                                            <KnownFact
                                                                known={
                                                                    fact.known
                                                                }
                                                                conflict={
                                                                    fact.verdict ===
                                                                    'conflict'
                                                                }
                                                                explanation={
                                                                    fact.explanation
                                                                }
                                                            />
                                                        )}
                                                        <span className="basis-full">
                                                            <FactSources
                                                                sources={
                                                                    fact.sources
                                                                }
                                                                except={
                                                                    section.id
                                                                }
                                                                label={t(
                                                                    'Also in',
                                                                )}
                                                            />
                                                        </span>
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    )}
                                </div>

                                {section.chunks.length === 0 ? (
                                    <p className="cc-caption px-1">
                                        {t(
                                            'This section has no text of its own; its content is in its subsections.',
                                        )}
                                    </p>
                                ) : (
                                    section.chunks.map((chunk, index) => (
                                        <ChunkCard
                                            key={chunk.id}
                                            chunk={chunk}
                                            index={index}
                                        />
                                    ))
                                )}
                            </div>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

/**
 * The fact the knowledge base already holds: where a repeated fact is known
 * from, or what a contradicted one says.
 */
function KnownFact({
    known,
    conflict,
    explanation,
}: {
    known: NonNullable<FactView['known']>;
    conflict: boolean;
    explanation: string | null;
}) {
    const t = useTranslations();
    const source =
        known.source.type === 'email' ? (
            <EmailSourceChip
                subject={known.source.title || t('(no subject)')}
                emailId={known.source.id}
            />
        ) : (
            <SourceChip
                documentId={known.source.id}
                title={known.source.title}
                versionNumber={known.source.versionNumber ?? undefined}
            />
        );

    if (!conflict) {
        return (
            <span className="flex basis-full flex-wrap items-center gap-1.5">
                <span className="cc-caption">{t('Known from')}</span>
                {source}
            </span>
        );
    }

    return (
        <span className="flex basis-full flex-col gap-1.5 border-l-2 border-cc-accent pl-3">
            <span className="cc-caption">{t('The knowledge base says')}</span>
            <span className="text-[13px] leading-[1.5] text-cc-muted">
                {known.statement}
            </span>
            {explanation && (
                <span className="text-[13px] leading-[1.5]">{explanation}</span>
            )}
            <span className="self-start">{source}</span>
        </span>
    );
}
