import { Form, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    Check,
    ChevronDown,
    FolderOpen,
    Hourglass,
    Link2,
    Upload,
    X,
} from 'lucide-react';
import { useState } from 'react';
import DocumentReviewController from '@/actions/App/Http/Controllers/Knowledge/DocumentReviewController';
import {
    EmailSourceChip,
    SourceChip,
} from '@/components/knowledge/source-chip';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import documentRoutes from '@/routes/knowledge/documents';
import topicRoutes from '@/routes/knowledge/topics';
import type { JudgedFact, ReviewFact, VersionReview } from '@/types';

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
    const formatDate = useFormatDate();
    const [rejecting, setRejecting] = useState(false);
    // Conflicts settled in favour of what is known: those new facts go.
    const [keep, setKeep] = useState<number[]>([]);
    const mailed = review.sources.filter((source) => source.kind === 'mail');
    const placement = review.placement;

    // A first version has nothing to compare with: its sections are simply
    // there, not new.
    const counts = review.firstVersion
        ? [
              {
                  key: 'added',
                  label: t('Sections, first version'),
                  value: review.sections.added,
              },
          ]
        : (
              [
                  ['unchanged', t('Unchanged sections')],
                  ['changed', t('Changed sections')],
                  ['added', t('New sections')],
                  ['removed', t('Removed sections')],
              ] as const
          ).map(([key, label]) => ({
              key,
              label,
              value: review.sections[key],
          }));

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
                            : mailed.length > 0
                              ? t(
                                    'Shared by mail. Nothing of it is searched until it is approved.',
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
                                <>
                                    {keep.map((id) => (
                                        <input
                                            key={id}
                                            type="hidden"
                                            name="keep_existing[]"
                                            value={id}
                                        />
                                    ))}
                                    <Button
                                        type="submit"
                                        className="h-10"
                                        disabled={processing}
                                    >
                                        <Check strokeWidth={2.5} />
                                        {t('Approve')}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </div>
                )}
            </div>

            {placement && placement.kind !== 'new' && placement.title && (
                <div className="flex items-start gap-2.5 rounded-lg bg-cc-bg px-3 py-2.5 text-[13px]">
                    <Link2 className="mt-0.5 size-4 shrink-0 text-cc-subtle" />
                    <span>
                        {placement.kind === 'version'
                            ? t('Recognised as the next version of')
                            : placement.kind === 'same_content'
                              ? t('The same text as')
                              : t('This looks like a new version of')}{' '}
                        {placement.document_id ? (
                            <Link
                                href={documentRoutes.show(
                                    placement.document_id,
                                )}
                                className="font-semibold underline underline-offset-[3px]"
                            >
                                {placement.title}
                            </Link>
                        ) : (
                            <span className="font-semibold">
                                {placement.title}
                            </span>
                        )}
                        {placement.reason && (
                            <span className="text-cc-muted">
                                {' '}
                                · {placement.reason}
                            </span>
                        )}
                        {placement.kind === 'similar' && (
                            <span className="text-cc-muted">
                                {' '}
                                ·{' '}
                                {t(
                                    'To keep its history together, upload it there as a new version instead.',
                                )}
                            </span>
                        )}
                    </span>
                </div>
            )}

            {review.error && (
                <p className="rounded-lg bg-cc-action-bg px-3 py-2 text-[13px] text-cc-action-fg">
                    {review.error}
                </p>
            )}

            <dl className="grid grid-cols-2 gap-3 sm:grid-cols-4">
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
            </dl>

            <dl className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                {(
                    [
                        ['new', t('New facts')],
                        ['duplicate', t('Already known')],
                        ['conflict', t('Contradict what is known')],
                        ['unchecked', t('Not checked')],
                    ] as const
                )
                    .filter(
                        ([key]) =>
                            key !== 'unchecked' || review.facts.unchecked > 0,
                    )
                    .map(([key, label]) => (
                        <div
                            key={key}
                            className={cn(
                                'flex flex-col gap-0.5 rounded-xl px-3 py-2.5',
                                key === 'conflict' && review.facts.conflict > 0
                                    ? 'bg-cc-action-bg text-cc-action-fg'
                                    : 'bg-cc-bg',
                            )}
                        >
                            <dt className="cc-caption">{label}</dt>
                            <dd className="text-[18px] font-semibold">
                                {review.facts[key]}
                            </dd>
                        </div>
                    ))}
            </dl>

            {review.conflicts.length > 0 && (
                <div className="flex flex-col gap-2">
                    <span className="cc-label">
                        {t('Contradicts what is known')}
                    </span>
                    <p className="cc-caption">
                        {t(
                            'Approving replaces the known fact with the new one, unless you keep the known one.',
                        )}
                    </p>
                    <ul className="flex flex-col gap-2">
                        {review.conflicts.map((fact) => (
                            <JudgedFactItem
                                key={fact.id}
                                fact={fact}
                                kept={keep.includes(fact.id)}
                                onKeep={
                                    canManage
                                        ? (kept) =>
                                              setKeep((current) =>
                                                  kept
                                                      ? [...current, fact.id]
                                                      : current.filter(
                                                            (id) =>
                                                                id !== fact.id,
                                                        ),
                                              )
                                        : undefined
                                }
                            />
                        ))}
                    </ul>
                </div>
            )}

            {review.newFacts.length > 0 && (
                <FactGroups
                    title={t('New facts (:count)', {
                        count: review.newFacts.length,
                    })}
                    caption={t(
                        'What approving adds to the knowledge base, by section.',
                    )}
                    facts={review.newFacts}
                    documentId={documentId}
                    open={review.newFacts.length <= 40}
                />
            )}

            {review.uncheckedFacts.length > 0 && (
                <FactGroups
                    title={t('Not checked (:count)', {
                        count: review.uncheckedFacts.length,
                    })}
                    caption={t(
                        'These were not compared with what the knowledge base knows, because the check did not finish. Processing the document again checks them.',
                    )}
                    facts={review.uncheckedFacts}
                    documentId={documentId}
                    open={false}
                />
            )}

            {review.duplicates.length > 0 && (
                <details className="group flex flex-col gap-2">
                    <summary className="cc-label cursor-pointer list-none">
                        {t('Already known (:count)', {
                            count: review.facts.duplicate,
                        })}
                    </summary>
                    <ul className="mt-2 flex flex-col gap-2">
                        {review.duplicates.map((fact) => (
                            <JudgedFactItem key={fact.id} fact={fact} />
                        ))}
                    </ul>
                </details>
            )}

            {review.sources.length > 0 && (
                <div className="flex flex-col gap-2">
                    <span className="cc-label">
                        {mailed.length > 1
                            ? t('Shared in :count threads', {
                                  count: mailed.length,
                              })
                            : t('Where it came from')}
                    </span>
                    <ul className="flex flex-col gap-2">
                        {review.sources.map((source, index) => (
                            <li
                                key={index}
                                className="flex flex-col gap-1 rounded-lg bg-cc-bg px-3 py-2.5"
                            >
                                <div className="flex flex-wrap items-center gap-2">
                                    {source.kind === 'mail' ? (
                                        <EmailSourceChip
                                            subject={
                                                source.subject ||
                                                t('(no subject)')
                                            }
                                            emailId={source.emailId}
                                        />
                                    ) : (
                                        <span className="inline-flex items-center gap-1.5 text-[13px] font-medium">
                                            <Upload className="size-3.5 text-cc-subtle" />
                                            {source.user
                                                ? t('Uploaded by :name', {
                                                      name: source.user,
                                                  })
                                                : t('Uploaded')}
                                        </span>
                                    )}
                                    <span className="cc-caption">
                                        {[
                                            source.from,
                                            source.filename,
                                            source.createdAt
                                                ? formatDate(source.createdAt)
                                                : null,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </span>
                                </div>
                                {source.context && (
                                    <p className="cc-caption">
                                        {source.context}
                                    </p>
                                )}
                            </li>
                        ))}
                    </ul>
                </div>
            )}

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

function JudgedFactItem({
    fact,
    kept,
    onKeep,
}: {
    fact: JudgedFact;
    kept?: boolean;
    onKeep?: (kept: boolean) => void;
}) {
    const t = useTranslations();
    const existing = fact.existing;

    return (
        <li className="flex flex-col gap-2 rounded-lg bg-cc-bg px-3 py-2.5">
            <div className="flex items-start gap-2">
                {onKeep && (
                    <AlertTriangle className="mt-0.5 size-4 shrink-0 text-cc-action-fg" />
                )}
                <span
                    className={cn(
                        'cc-body flex-1',
                        kept && 'text-cc-muted line-through',
                    )}
                >
                    {fact.statement}
                </span>
            </div>
            {existing && (
                <div className="flex flex-col gap-1.5 border-l-2 border-cc-border pl-3">
                    <span
                        className={cn(
                            'text-[13px]',
                            onKeep && !kept && 'text-cc-muted line-through',
                        )}
                    >
                        {existing.statement}
                    </span>
                    <div className="flex flex-wrap items-center gap-1.5">
                        {existing.source.type === 'email' ? (
                            <EmailSourceChip
                                subject={existing.source.title}
                                emailId={existing.source.id}
                            />
                        ) : (
                            existing.source.id !== null && (
                                <SourceChip
                                    documentId={existing.source.id}
                                    title={existing.source.title}
                                    versionNumber={
                                        existing.source.versionNumber ??
                                        undefined
                                    }
                                    headingPath={null}
                                    pageFrom={null}
                                    sectionId={null}
                                />
                            )
                        )}
                        {existing.status === 'proposed' && (
                            <span className="cc-caption">
                                {t('also waiting for approval')}
                            </span>
                        )}
                    </div>
                </div>
            )}
            {fact.explanation && (
                <p className="cc-caption">{fact.explanation}</p>
            )}
            {onKeep && (
                <label className="flex w-fit cursor-pointer items-center gap-2 text-[13px] font-medium">
                    <input
                        type="checkbox"
                        checked={kept}
                        onChange={(event) => onKeep(event.target.checked)}
                        className="size-4 accent-cc-ink"
                    />
                    {t('Keep the known fact')}
                </label>
            )}
        </li>
    );
}

/**
 * Facts under the section they come from, each heading a link to it.
 */
function FactGroups({
    title,
    caption,
    facts,
    documentId,
    open,
}: {
    title: string;
    caption: string;
    facts: ReviewFact[];
    documentId: number;
    /** Open from the start; a long list starts folded. */
    open: boolean;
}) {
    const t = useTranslations();
    const groups: {
        sectionId: number | null;
        heading: string | null;
        facts: ReviewFact[];
    }[] = [];

    for (const fact of facts) {
        const last = groups.at(-1);

        if (last && last.sectionId === fact.sectionId) {
            last.facts.push(fact);
        } else {
            groups.push({
                sectionId: fact.sectionId,
                heading: fact.heading,
                facts: [fact],
            });
        }
    }

    return (
        <details open={open} className="group flex flex-col gap-2">
            <summary className="flex min-h-10 cursor-pointer list-none items-center justify-between gap-3">
                <span className="cc-label">{title}</span>
                <ChevronDown className="size-4 text-cc-subtle transition-transform group-open:rotate-180" />
            </summary>
            <p className="cc-caption">{caption}</p>
            <div className="mt-2 flex flex-col gap-3">
                {groups.map((group, index) => (
                    <div key={index} className="flex flex-col gap-1.5">
                        {group.sectionId !== null ? (
                            <Link
                                href={documentRoutes.show(documentId, {
                                    query: { section: group.sectionId },
                                })}
                                className="cc-caption self-start font-semibold text-cc-muted hover:text-cc-ink hover:underline"
                            >
                                {group.heading || t('(introduction)')}
                            </Link>
                        ) : (
                            <span className="cc-caption font-semibold">
                                {t('(introduction)')}
                            </span>
                        )}
                        <ul className="flex flex-col gap-1.5">
                            {group.facts.map((fact) => (
                                <li
                                    key={fact.id}
                                    className="rounded-lg bg-cc-bg px-3 py-2 text-[14px] leading-[1.5]"
                                >
                                    {fact.statement}
                                </li>
                            ))}
                        </ul>
                    </div>
                ))}
            </div>
        </details>
    );
}
