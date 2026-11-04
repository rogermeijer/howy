import { Form, Link } from '@inertiajs/react';
import {
    Check,
    ChevronDown,
    ExternalLink,
    FileText,
    FolderOpen,
    Hourglass,
    Link2,
    Upload,
    X,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
import DocumentReviewController from '@/actions/App/Http/Controllers/Knowledge/DocumentReviewController';
import InputError from '@/components/input-error';
import { FactSources } from '@/components/knowledge/fact-sources';
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
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import documentRoutes from '@/routes/knowledge/documents';
import topicRoutes from '@/routes/knowledge/topics';
import type {
    ExpiringFact,
    JudgedFact,
    ReviewFact,
    VersionReview,
} from '@/types';

/** What the reviewer decided so far: per conflict what holds, and what to leave out. */
export type ReviewChoices = {
    decisions: Record<number, 'new' | 'keep'>;
    decide: (factId: number, choice: 'new' | 'keep') => void;
    omitted: number[];
    toggleOmit: (factId: number) => void;
    selected: number | null;
    select: (factId: number) => void;
};

/** A fact's place in the document, as the source endpoint returns it. */
type Passage = {
    sectionId: number | null;
    heading: string | null;
    versionNumber: number | null;
    before: string;
    quote: string | null;
    after: string;
    exact: boolean;
    statement: string;
};

export function useReviewChoices(review: VersionReview | null): ReviewChoices {
    const [decisions, setDecisions] = useState<Record<number, 'new' | 'keep'>>(
        {},
    );
    const [omitted, setOmitted] = useState<number[]>([]);
    const [selected, setSelected] = useState<number | null>(
        review?.conflicts[0]?.id ?? review?.newFacts[0]?.id ?? null,
    );

    return {
        decisions,
        decide: (factId, choice) => {
            setDecisions((current) => ({ ...current, [factId]: choice }));
            setSelected(factId);
        },
        omitted,
        toggleOmit: (factId) =>
            setOmitted((current) =>
                current.includes(factId)
                    ? current.filter((id) => id !== factId)
                    : [...current, factId],
            ),
        selected,
        select: setSelected,
    };
}

/**
 * The decision about a processed version, at the top of its page: what it
 * is, and approve or reject. Approving waits until every conflict has a
 * choice; nothing replaces what is known by default.
 */
export function ReviewDecision({
    documentId,
    versionId,
    versionNumber,
    review,
    choices,
    canManage,
}: {
    documentId: number;
    versionId: number;
    versionNumber: number;
    review: VersionReview;
    choices: ReviewChoices;
    canManage: boolean;
}) {
    const t = useTranslations();
    const [rejecting, setRejecting] = useState(false);
    const mailed = review.sources.filter((source) => source.kind === 'mail');
    const open = review.conflicts.filter(
        (fact) => !choices.decisions[fact.id],
    ).length;

    const hint =
        open === 1
            ? t('Choose what holds for the conflict first.')
            : open > 1
              ? t('Choose what holds for :count conflicts first.', {
                    count: open,
                })
              : choices.omitted.length > 0
                ? t(
                      ':count facts are left out. The rest is searched once approved.',
                      {
                          count: choices.omitted.length,
                      },
                  )
                : t('Nothing of it is searched until it is approved.');

    return (
        <section className="cc-panel flex flex-wrap items-center justify-between gap-x-8 gap-y-5 p-6">
            <div className="flex min-w-0 flex-[1_1_420px] flex-col gap-2">
                <span className="inline-flex w-fit items-center gap-1.5 rounded-full bg-cc-pending-bg px-2.5 py-1 text-[12px] font-semibold text-cc-pending-fg">
                    <Hourglass className="size-3.5" />
                    {t('Ready for approval')}
                </span>
                <h2 className="cc-subtitle">
                    {review.firstVersion
                        ? t('Version :version is new to the knowledge base', {
                              version: versionNumber,
                          })
                        : t('Version :version replaces version :old', {
                              version: versionNumber,
                              old: review.replaces ?? versionNumber - 1,
                          })}
                </h2>
                <p className="cc-caption">
                    {review.uploadedBy
                        ? t('Uploaded by :name.', { name: review.uploadedBy })
                        : mailed.length > 0
                          ? t('Shared by mail by :name.', {
                                name: mailed[0].from ?? t('unknown'),
                            })
                          : null}{' '}
                    {review.firstVersion
                        ? null
                        : t(
                              'Until you approve, version :old stays the source.',
                              {
                                  old: review.replaces ?? versionNumber - 1,
                              },
                          )}
                </p>
            </div>

            {canManage && (
                <div className="flex flex-col items-end gap-2">
                    <div className="flex flex-wrap justify-end gap-2.5">
                        <Button
                            variant="outline"
                            className="h-11 px-5"
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
                            {({ processing, errors }) => (
                                <>
                                    {Object.entries(choices.decisions).map(
                                        ([id, choice]) => (
                                            <input
                                                key={id}
                                                type="hidden"
                                                name={`conflicts[${id}]`}
                                                value={choice}
                                            />
                                        ),
                                    )}
                                    {choices.omitted.map((id) => (
                                        <input
                                            key={id}
                                            type="hidden"
                                            name="omit[]"
                                            value={id}
                                        />
                                    ))}
                                    <Button
                                        type="submit"
                                        className="h-11 px-5"
                                        disabled={processing || open > 0}
                                    >
                                        <Check strokeWidth={2.5} />
                                        {t('Approve and publish')}
                                    </Button>
                                    <InputError message={errors.conflicts} />
                                </>
                            )}
                        </Form>
                    </div>
                    <span className="cc-caption text-right">{hint}</span>
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
        </section>
    );
}

/**
 * What a version waiting for approval brings, in the order a reviewer needs
 * it: conflicts to decide, new facts to look over, then what changes
 * quietly. Picking a fact shows where the document says it.
 */
export function ReviewTab({
    documentId,
    review,
    choices,
    canManage,
    fileUrl,
    onOpenSection,
}: {
    documentId: number;
    review: VersionReview;
    choices: ReviewChoices;
    canManage: boolean;
    fileUrl: string;
    onOpenSection: (sectionId: number) => void;
}) {
    const t = useTranslations();
    const decided = review.conflicts.filter(
        (fact) => choices.decisions[fact.id],
    ).length;
    const adding = review.newFacts.length - choices.omitted.length;
    const quiet =
        review.conflicts.length === 0 &&
        review.newFacts.length === 0 &&
        review.expiring.length === 0;
    const differences = (
        [
            ['changed', ':count changed'],
            ['added', ':count added'],
            ['removed', ':count removed'],
        ] as const
    )
        .filter(([key]) => review.sections[key] > 0)
        .map(([key, label]) => t(label, { count: review.sections[key] }));

    return (
        <div className="flex flex-col gap-5">
            <div className="flex flex-wrap items-center gap-2 text-[14px]">
                <span className="mr-1 text-cc-muted">{t('On approval:')}</span>
                {review.conflicts.length > 0 && (
                    <a
                        href="#review-conflicts"
                        className="rounded-full bg-cc-action-bg px-3 py-1.5 text-[13px] font-semibold text-cc-action-fg"
                    >
                        {t(':count conflicts · :decided decided', {
                            count: review.conflicts.length,
                            decided,
                        })}
                    </a>
                )}
                {review.newFacts.length > 0 && (
                    <a
                        href="#review-new"
                        className="rounded-full bg-cc-knowledge-bg px-3 py-1.5 text-[13px] font-semibold text-cc-knowledge-fg"
                    >
                        {t('+:count new facts', { count: adding })}
                    </a>
                )}
                {review.expiring.length > 0 && (
                    <a
                        href="#review-expiring"
                        className="rounded-full bg-cc-pending-bg px-3 py-1.5 text-[13px] font-semibold text-cc-pending-fg"
                    >
                        {t(':count expire', { count: review.expiring.length })}
                    </a>
                )}
                {review.facts.duplicate > 0 && (
                    <a
                        href="#review-known"
                        className="rounded-full bg-cc-raised px-3 py-1.5 text-[13px] font-semibold text-cc-muted"
                    >
                        {t(':count already known', {
                            count: review.facts.duplicate,
                        })}
                    </a>
                )}
                {!review.firstVersion && review.expiring.length === 0 && (
                    <span className="rounded-full bg-cc-raised px-3 py-1.5 text-[13px] font-semibold text-cc-muted">
                        {t('Nothing expires')}
                    </span>
                )}
            </div>

            <div className="flex flex-wrap items-start gap-5">
                <div className="flex min-w-0 flex-[999_1_560px] flex-col gap-5">
                    {quiet && (
                        <div className="cc-panel flex flex-col gap-1 p-6">
                            <h3 className="text-[16px] font-bold">
                                {t('Nothing new to decide')}
                            </h3>
                            <p className="cc-caption">
                                {t(
                                    'Everything this version says is already known: approving makes it one more source of those facts.',
                                )}
                            </p>
                        </div>
                    )}

                    {review.conflicts.length > 0 && (
                        <Card
                            id="review-conflicts"
                            title={t('Conflicts (:count)', {
                                count: review.conflicts.length,
                            })}
                            caption={t(
                                'This document says something else than the knowledge base. Choose for each conflict what holds.',
                            )}
                        >
                            {review.conflicts.map((fact) => (
                                <ConflictCard
                                    key={fact.id}
                                    fact={fact}
                                    choice={choices.decisions[fact.id]}
                                    selected={choices.selected === fact.id}
                                    canManage={canManage}
                                    onSelect={() => choices.select(fact.id)}
                                    onChoose={(choice) =>
                                        choices.decide(fact.id, choice)
                                    }
                                />
                            ))}
                        </Card>
                    )}

                    {review.newFacts.length > 0 && (
                        <Card
                            id="review-new"
                            title={t('New facts (:count)', { count: adding })}
                            caption={
                                canManage
                                    ? t(
                                          'Added when you approve. Pick a fact to see where the document says it; leave out what is not knowledge.',
                                      )
                                    : t(
                                          'Added when this version is approved. Pick a fact to see where the document says it.',
                                      )
                            }
                        >
                            {bySection(review.newFacts).map((group) => (
                                <div
                                    key={group.facts[0].id}
                                    className="flex flex-col gap-1.5"
                                >
                                    <SectionLink
                                        sectionId={group.sectionId}
                                        heading={group.heading}
                                        onOpen={onOpenSection}
                                    />
                                    <ul className="flex flex-col gap-1.5">
                                        {group.facts.map((fact) => (
                                            <FactRow
                                                key={fact.id}
                                                fact={fact}
                                                selected={
                                                    choices.selected === fact.id
                                                }
                                                omitted={choices.omitted.includes(
                                                    fact.id,
                                                )}
                                                canManage={canManage}
                                                onSelect={() =>
                                                    choices.select(fact.id)
                                                }
                                                onToggle={() =>
                                                    choices.toggleOmit(fact.id)
                                                }
                                            />
                                        ))}
                                    </ul>
                                </div>
                            ))}
                        </Card>
                    )}

                    {review.expiring.length > 0 && (
                        <Card
                            id="review-expiring"
                            title={t('Expires on approval (:count)', {
                                count: review.expiring.length,
                            })}
                            caption={
                                review.replaces !== null
                                    ? t(
                                          'Facts of version :version that no longer hold once this version is approved. They are kept as history.',
                                          { version: review.replaces },
                                      )
                                    : t(
                                          'Facts of the previous version that no longer hold once this version is approved. They are kept as history.',
                                      )
                            }
                        >
                            <ExpiringFacts
                                facts={review.expiring}
                                documentId={documentId}
                                previousVersionId={review.previousVersionId}
                            />
                        </Card>
                    )}

                    {review.uncheckedFacts.length > 0 && (
                        <Folded
                            title={t('Not checked (:count)', {
                                count: review.uncheckedFacts.length,
                            })}
                            caption={t(
                                'These were not compared with what the knowledge base knows, because the check did not finish. Processing the document again checks them.',
                            )}
                        >
                            <PlainFacts facts={review.uncheckedFacts} />
                        </Folded>
                    )}

                    {review.facts.duplicate > 0 && (
                        <Folded
                            id="review-known"
                            title={t(
                                ':count facts are already in the knowledge base',
                                { count: review.facts.duplicate },
                            )}
                            caption={t(
                                'This document becomes one more source of them. Nothing is added twice, and nothing needs doing here.',
                            )}
                        >
                            <ul className="flex flex-col gap-1.5">
                                {review.duplicates.map((fact) => (
                                    <li
                                        key={fact.id}
                                        className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 rounded-lg bg-cc-bg px-3 py-2 text-[14px]"
                                    >
                                        <span>{fact.statement}</span>
                                        {fact.existing && (
                                            <KnownSource
                                                existing={fact.existing}
                                            />
                                        )}
                                    </li>
                                ))}
                                {review.facts.duplicate >
                                    review.duplicates.length && (
                                    <li className="cc-caption px-3 py-1">
                                        {t('and :count more', {
                                            count:
                                                review.facts.duplicate -
                                                review.duplicates.length,
                                        })}
                                    </li>
                                )}
                            </ul>
                        </Folded>
                    )}

                    {review.carried.length > 0 && (
                        <Folded
                            title={t('Carried over unchanged (:count)', {
                                count: review.carried.length,
                            })}
                            caption={t(
                                'A changed section says these word for word as before: they carry on as they are.',
                            )}
                        >
                            <PlainFacts facts={review.carried} />
                        </Folded>
                    )}

                    {review.staying.length > 0 && (
                        <Folded
                            title={t('Stays, stated elsewhere too (:count)', {
                                count: review.staying.length,
                            })}
                            caption={t(
                                'Not in this version any more, but another document or a mail states it too: the fact keeps counting, cited from there.',
                            )}
                        >
                            <ul className="flex flex-col gap-1.5">
                                {review.staying.map((fact) => (
                                    <li
                                        key={fact.id}
                                        className="flex flex-col gap-1.5 rounded-lg bg-cc-bg px-3 py-2 text-[14px] leading-[1.5]"
                                    >
                                        {fact.statement}
                                        <FactSources sources={[fact.source]} />
                                    </li>
                                ))}
                            </ul>
                        </Folded>
                    )}

                    {!review.firstVersion && (
                        <p className="cc-caption">
                            {[
                                review.replaces !== null
                                    ? t(
                                          'Sections compared with version :version:',
                                          { version: review.replaces },
                                      )
                                    : t(
                                          'Sections compared with the previous version:',
                                      ),
                                differences.length > 0
                                    ? differences.join(' · ')
                                    : t('none changed'),
                            ].join(' ')}
                        </p>
                    )}
                </div>

                <aside className="flex min-w-0 flex-[1_1_340px] flex-col gap-4 lg:sticky lg:top-24">
                    <SourcePanel
                        documentId={documentId}
                        factId={choices.selected}
                        fileUrl={fileUrl}
                        onOpenSection={onOpenSection}
                    />
                    <Origin review={review} />
                </aside>
            </div>
        </div>
    );
}

function Card({
    id,
    title,
    caption,
    children,
}: {
    id?: string;
    title: string;
    caption: string;
    children: ReactNode;
}) {
    return (
        <section
            id={id}
            className="cc-panel flex scroll-mt-24 flex-col gap-4 p-6"
        >
            <div className="flex flex-col gap-1">
                <h3 className="text-[18px] font-bold">{title}</h3>
                <p className="cc-caption">{caption}</p>
            </div>
            {children}
        </section>
    );
}

/** A quiet row that opens on demand: what needs no decision. */
function Folded({
    id,
    title,
    caption,
    children,
}: {
    id?: string;
    title: string;
    caption: string;
    children: ReactNode;
}) {
    return (
        <details id={id} className="group cc-panel scroll-mt-24 px-6 py-5">
            <summary className="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3">
                <span className="flex min-w-0 flex-col gap-0.5">
                    <span className="text-[16px] font-bold">{title}</span>
                    <span className="cc-caption">{caption}</span>
                </span>
                <span className="inline-flex h-9 items-center gap-1.5 rounded-full border-[1.5px] border-cc-border-strong px-3.5 text-[13px] font-semibold">
                    <ChevronDown className="size-4 transition-transform group-open:rotate-180" />
                </span>
            </summary>
            <div className="mt-4">{children}</div>
        </details>
    );
}

function ConflictCard({
    fact,
    choice,
    selected,
    canManage,
    onSelect,
    onChoose,
}: {
    fact: JudgedFact;
    choice: 'new' | 'keep' | undefined;
    selected: boolean;
    canManage: boolean;
    onSelect: () => void;
    onChoose: (choice: 'new' | 'keep') => void;
}) {
    const t = useTranslations();
    const heading = fact.heading?.split(' › ').at(-1);

    return (
        <article
            className={cn(
                'flex flex-col gap-3 rounded-2xl border-[1.5px] bg-cc-panel p-4',
                selected ? 'border-cc-ink' : 'border-cc-border',
            )}
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <button
                    type="button"
                    onClick={onSelect}
                    className="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-cc-border bg-cc-bg px-2.5 py-1 text-[12px] font-semibold text-cc-muted transition-colors hover:text-cc-ink"
                >
                    <FileText className="size-3.5" />
                    {heading ? `§ ${heading}` : t('Show where it says this')}
                </button>
                <span
                    className={cn(
                        'text-[12px] font-semibold',
                        choice ? 'text-cc-decision-fg' : 'text-cc-pending-fg',
                    )}
                >
                    {choice === 'new'
                        ? t('The new fact replaces the known one')
                        : choice === 'keep'
                          ? t('The known fact stays')
                          : t('Not decided yet')}
                </span>
            </div>
            <div className="grid gap-2.5 sm:grid-cols-2">
                <div className="flex flex-col gap-1.5 rounded-xl bg-cc-action-bg p-3.5">
                    <span className="text-[11px] font-bold tracking-[0.06em] text-cc-action-fg uppercase">
                        {t('This document')}
                    </span>
                    <span className="text-[15px] leading-[1.45] font-semibold">
                        {fact.statement}
                    </span>
                </div>
                <div className="flex flex-col gap-1.5 rounded-xl bg-cc-bg p-3.5">
                    <span className="text-[11px] font-bold tracking-[0.06em] text-cc-muted uppercase">
                        {t('The knowledge base')}
                    </span>
                    <span className="text-[15px] leading-[1.45]">
                        {fact.existing?.statement}
                    </span>
                    {fact.existing && <KnownSource existing={fact.existing} />}
                </div>
            </div>
            {fact.explanation && (
                <p className="cc-caption">{fact.explanation}</p>
            )}
            {canManage && (
                <div
                    role="group"
                    aria-label={t('What holds?')}
                    className="flex flex-wrap items-center gap-2"
                >
                    <span className="mr-1 text-[13px] font-semibold">
                        {t('What holds?')}
                    </span>
                    {(
                        [
                            ['new', t('The new fact')],
                            ['keep', t('The known fact')],
                        ] as const
                    ).map(([value, label]) => (
                        <button
                            key={value}
                            type="button"
                            aria-pressed={choice === value}
                            onClick={() => onChoose(value)}
                            className={cn(
                                'h-10 cursor-pointer rounded-full border-[1.5px] px-4 text-[13px] font-semibold transition-colors',
                                choice === value
                                    ? 'border-cc-ink bg-cc-ink text-cc-bg'
                                    : 'border-cc-border-strong bg-cc-panel hover:border-cc-ink',
                            )}
                        >
                            {label}
                        </button>
                    ))}
                </div>
            )}
        </article>
    );
}

function FactRow({
    fact,
    selected,
    omitted,
    canManage,
    onSelect,
    onToggle,
}: {
    fact: ReviewFact;
    selected: boolean;
    omitted: boolean;
    canManage: boolean;
    onSelect: () => void;
    onToggle: () => void;
}) {
    const t = useTranslations();

    return (
        <li
            className={cn(
                'flex items-center gap-2 rounded-xl py-1.5 pr-1.5 pl-1',
                selected
                    ? 'bg-cc-accent-tint ring-[1.5px] ring-cc-accent'
                    : 'bg-cc-bg',
            )}
        >
            <button
                type="button"
                aria-pressed={selected}
                onClick={onSelect}
                className={cn(
                    'min-w-0 flex-1 cursor-pointer px-2 py-1.5 text-left text-[14px] leading-[1.5]',
                    omitted && 'text-cc-subtle line-through',
                )}
            >
                {fact.statement}
            </button>
            {canManage && (
                <button
                    type="button"
                    onClick={onToggle}
                    className="h-8 shrink-0 cursor-pointer rounded-lg border border-cc-border bg-cc-panel px-2.5 text-[12px] font-semibold text-cc-muted transition-colors hover:text-cc-ink"
                >
                    {omitted ? t('Put back') : t('Leave out')}
                </button>
            )}
        </li>
    );
}

function SectionLink({
    sectionId,
    heading,
    onOpen,
}: {
    sectionId: number | null;
    heading: string | null;
    onOpen: (sectionId: number) => void;
}) {
    const t = useTranslations();
    const label = heading?.split(' › ').at(-1) ?? t('(introduction)');

    return sectionId === null ? (
        <span className="text-[12px] font-bold text-cc-muted">§ {label}</span>
    ) : (
        <button
            type="button"
            onClick={() => onOpen(sectionId)}
            className="cursor-pointer self-start text-[12px] font-bold text-cc-muted hover:text-cc-ink hover:underline"
        >
            § {label}
        </button>
    );
}

/**
 * Where the picked fact stands in the document: its section's passage, the
 * words that state it marked. Fetched when a fact is picked.
 */
function SourcePanel({
    documentId,
    factId,
    fileUrl,
    onOpenSection,
}: {
    documentId: number;
    factId: number | null;
    fileUrl: string;
    onOpenSection: (sectionId: number) => void;
}) {
    const t = useTranslations();
    const [passage, setPassage] = useState<Passage | null>(null);

    useEffect(() => {
        setPassage(null);

        if (factId === null) {
            return;
        }

        const controller = new AbortController();

        fetch(documentRoutes.facts.source.url([documentId, factId]), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: controller.signal,
        })
            .then((response) => (response.ok ? response.json() : null))
            .then((data: Passage | null) => setPassage(data))
            .catch(() => undefined);

        return () => controller.abort();
    }, [documentId, factId]);

    return (
        <section className="cc-panel flex flex-col gap-3 p-5">
            <span className="text-[11px] font-bold tracking-[0.06em] text-cc-muted uppercase">
                {t('Where it says this')}
            </span>
            {passage === null ? (
                <p className="cc-caption">
                    {factId === null
                        ? t('Pick a fact to see where the document says it.')
                        : t('Loading…')}
                </p>
            ) : (
                <>
                    <span className="text-[13px] font-semibold">
                        {passage.heading || t('(introduction)')}
                    </span>
                    <p className="text-[13px] leading-[1.65] whitespace-pre-line text-cc-muted">
                        {passage.before}
                        {passage.quote && (
                            <mark className="rounded-[3px] bg-cc-highlight/60 px-0.5 text-cc-ink">
                                {passage.quote}
                            </mark>
                        )}
                        {passage.after}
                    </p>
                    {passage.quote && !passage.exact && (
                        <span className="cc-caption">
                            {t('Marked by the words it shares with the fact.')}
                        </span>
                    )}
                    <div className="flex flex-col gap-1 rounded-xl bg-cc-accent-tint p-3">
                        <span className="text-[11px] font-bold tracking-[0.06em] text-cc-accent-deep uppercase">
                            {t('In the knowledge base')}
                        </span>
                        <span className="text-[14px] leading-[1.45] font-semibold">
                            {passage.statement}
                        </span>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {passage.sectionId !== null && (
                            <Button
                                variant="outline"
                                className="h-9"
                                onClick={() =>
                                    onOpenSection(passage.sectionId ?? 0)
                                }
                            >
                                {t('Whole section')}
                            </Button>
                        )}
                        <Button variant="outline" className="h-9" asChild>
                            <a href={fileUrl} target="_blank" rel="noreferrer">
                                {t('Original')}
                                <ExternalLink />
                            </a>
                        </Button>
                    </div>
                </>
            )}
        </section>
    );
}

/** Where the version came from, where it was placed and where it will be filed. */
function Origin({ review }: { review: VersionReview }) {
    const t = useTranslations();
    const placement = review.placement;

    return (
        <section className="cc-panel flex flex-col gap-3 p-5">
            <span className="text-[11px] font-bold tracking-[0.06em] text-cc-muted uppercase">
                {t('Where it came from')}
            </span>
            <ul className="flex flex-col gap-2">
                {review.sources.map((source, index) => (
                    <li key={index} className="flex flex-col gap-1">
                        {source.kind === 'mail' ? (
                            <EmailSourceChip
                                subject={source.subject || t('(no subject)')}
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
                            {[source.from, source.filename]
                                .filter(Boolean)
                                .join(' · ')}
                        </span>
                    </li>
                ))}
            </ul>

            {placement && placement.kind !== 'new' && placement.title && (
                <p className="flex items-start gap-2 text-[13px]">
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
                </p>
            )}

            {review.error && (
                <p className="rounded-lg bg-cc-action-bg px-3 py-2 text-[13px] text-cc-action-fg">
                    {review.error}
                </p>
            )}

            {review.folders.length > 0 && (
                <div className="flex flex-wrap items-center gap-1.5">
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

            {review.summary && (
                <details className="group">
                    <summary className="cc-caption cursor-pointer list-none font-semibold hover:text-cc-ink">
                        {t('Summary')}
                    </summary>
                    <p className="mt-2 text-[13px] leading-[1.6]">
                        {review.summary}
                    </p>
                </details>
            )}
        </section>
    );
}

function KnownSource({
    existing,
}: {
    existing: NonNullable<JudgedFact['existing']>;
}) {
    const t = useTranslations();

    return (
        <span className="flex flex-wrap items-center gap-1.5">
            {existing.source.type === 'email' ? (
                <EmailSourceChip
                    subject={existing.source.title || t('(no subject)')}
                    emailId={existing.source.id}
                />
            ) : (
                <SourceChip
                    documentId={existing.source.id}
                    title={existing.source.title}
                    versionNumber={existing.source.versionNumber ?? undefined}
                />
            )}
            {existing.status === 'proposed' && (
                <span className="cc-caption">
                    {t('also waiting for approval')}
                </span>
            )}
        </span>
    );
}

function PlainFacts({ facts }: { facts: ReviewFact[] }) {
    return (
        <ul className="flex flex-col gap-1.5">
            {facts.map((fact) => (
                <li
                    key={fact.id}
                    className="rounded-lg bg-cc-bg px-3 py-2 text-[14px] leading-[1.5]"
                >
                    {fact.statement}
                </li>
            ))}
        </ul>
    );
}

/**
 * The facts of the previous version that no longer hold, under the section
 * they came from: gone from the document, or changed, with the new fact
 * that takes their place.
 */
function ExpiringFacts({
    facts,
    documentId,
    previousVersionId,
}: {
    facts: ExpiringFact[];
    documentId: number;
    previousVersionId: number | null;
}) {
    const t = useTranslations();

    return (
        <div className="flex flex-col gap-3">
            {bySection(facts).map((group) => (
                <div key={group.facts[0].id} className="flex flex-col gap-1.5">
                    <span className="flex flex-wrap items-center gap-2">
                        {group.sectionId !== null &&
                        previousVersionId !== null ? (
                            <Link
                                href={documentRoutes.show(documentId, {
                                    query: {
                                        version: previousVersionId,
                                        section: group.sectionId,
                                    },
                                })}
                                className="text-[12px] font-bold text-cc-muted hover:text-cc-ink hover:underline"
                            >
                                § {group.heading?.split(' › ').at(-1)}
                            </Link>
                        ) : (
                            <span className="text-[12px] font-bold text-cc-muted">
                                § {group.heading?.split(' › ').at(-1)}
                            </span>
                        )}
                        <span
                            className={cn(
                                'rounded-md px-1.5 py-0.5 text-[11px] font-semibold',
                                group.facts[0].sectionRemoved
                                    ? 'bg-cc-action-bg text-cc-action-fg'
                                    : 'bg-cc-pending-bg text-cc-pending-fg',
                            )}
                        >
                            {group.facts[0].sectionRemoved
                                ? t('Section removed')
                                : t('Section changed')}
                        </span>
                    </span>
                    <ul className="flex flex-col gap-1.5">
                        {group.facts.map((fact) => (
                            <li
                                key={fact.id}
                                className="grid gap-2 rounded-xl bg-cc-bg p-3 sm:grid-cols-2"
                            >
                                <span className="text-[14px] leading-[1.5] text-cc-muted line-through decoration-cc-faint">
                                    {fact.statement}
                                </span>
                                {fact.replacedBy && (
                                    <span className="text-[14px] leading-[1.5]">
                                        <span className="text-cc-subtle">
                                            {t('Becomes')}:
                                        </span>{' '}
                                        {fact.replacedBy}
                                    </span>
                                )}
                            </li>
                        ))}
                    </ul>
                </div>
            ))}
        </div>
    );
}

/** Facts in document order, grouped where they share a section. */
function bySection<
    T extends { sectionId: number | null; heading: string | null },
>(
    facts: T[],
): { sectionId: number | null; heading: string | null; facts: T[] }[] {
    const groups: {
        sectionId: number | null;
        heading: string | null;
        facts: T[];
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

    return groups;
}
