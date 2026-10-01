import { Head, Link, router } from '@inertiajs/react';
import { Bug, Search as SearchIcon } from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/cc/page-header';
import { RichText } from '@/components/knowledge/rich-text';
import { SourceChip } from '@/components/knowledge/source-chip';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { knowledge } from '@/routes';
import knowledgeRoutes from '@/routes/knowledge';
import type { FactStatus, Option } from '@/types';

type PassageView = {
    documentId: number;
    versionId: number;
    versionNumber: number;
    documentTitle: string;
    sectionId: number | null;
    headingPath: string;
    pageFrom: number | null;
    pageTo: number | null;
    text: string;
    score: number;
    tokenCount: number;
    ranks: { vector: number | null; text: number | null };
    wholeSection: boolean;
};

type FactHitView = {
    id: number;
    statement: string;
    status: FactStatus;
    validFrom: string | null;
    documentId: number | null;
    documentTitle: string | null;
    sectionId: number | null;
    headingPath: string | null;
    pageFrom: number | null;
};

type Props = {
    query: { q: string; type: string | null };
    types: Option[];
    result: {
        tokenCount: number;
        usedVectors: boolean;
        passages: PassageView[];
        facts: FactHitView[];
    } | null;
    canDebug: boolean;
};

const ALL = '__all__';

export default function KnowledgeSearchPage({
    query,
    types,
    result,
    canDebug,
}: Props) {
    const t = useTranslations();
    const [q, setQ] = useState(query.q);
    const [type, setType] = useState(query.type ?? ALL);
    const [debug, setDebug] = useState(false);

    const submit = (event?: React.FormEvent) => {
        event?.preventDefault();
        router.get(
            knowledgeRoutes.search().url,
            { q, type: type === ALL ? undefined : type },
            { preserveState: true },
        );
    };

    return (
        <>
            <Head
                title={query.q ? `${query.q} · ${t('Search')}` : t('Search')}
            />

            <div className="flex flex-col gap-7">
                <PageHeader
                    title={t('Search the knowledge base')}
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
                                {t('Search')}
                            </span>
                        </div>
                    }
                    description={t(
                        'Ask a question in your own words. You get the passages an answer would be built from, each with its exact source.',
                    )}
                />

                <form
                    onSubmit={submit}
                    className="flex flex-col gap-3 sm:flex-row"
                >
                    <label className="flex h-12 flex-1 items-center gap-3 rounded-xl border-[1.5px] border-cc-border-strong bg-cc-panel px-4 focus-within:border-cc-ink">
                        <SearchIcon className="size-5 shrink-0 text-cc-subtle" />
                        <input
                            type="search"
                            value={q}
                            onChange={(event) => setQ(event.target.value)}
                            placeholder={t(
                                'e.g. How many days of leave do I get after ten years?',
                            )}
                            aria-label={t('Question')}
                            autoFocus
                            className="min-w-0 flex-1 border-none bg-transparent text-[15px] text-cc-ink outline-none placeholder:text-cc-subtle"
                        />
                    </label>
                    <Select value={type} onValueChange={setType}>
                        <SelectTrigger
                            className="h-12 w-full sm:w-[190px]"
                            aria-label={t('Type')}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>
                                {t('All types')}
                            </SelectItem>
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
                    <Button type="submit" className="h-12 px-6">
                        {t('Search')}
                    </Button>
                </form>

                {result && (
                    <div className="flex flex-col gap-5">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <p className="cc-caption">
                                {result.passages.length === 0 &&
                                result.facts.length === 0
                                    ? t(
                                          'Nothing in the knowledge base matches this question.',
                                      )
                                    : t(
                                          ':passages passages and :facts facts · ~:tokens tokens',
                                          {
                                              passages: result.passages.length,
                                              facts: result.facts.length,
                                              tokens: result.tokenCount,
                                          },
                                      )}
                                {!result.usedVectors &&
                                    ` · ${t('text search only')}`}
                            </p>
                            {canDebug && (
                                <button
                                    type="button"
                                    onClick={() => setDebug(!debug)}
                                    className={cn(
                                        'cc-pill inline-flex items-center gap-1.5',
                                        debug && 'cc-pill-active',
                                    )}
                                >
                                    <Bug className="size-3.5" />
                                    {t('Ranking details')}
                                </button>
                            )}
                        </div>

                        {result.facts.length > 0 && (
                            <section className="cc-panel flex flex-col gap-3 p-5">
                                <h2 className="cc-label">{t('Facts')}</h2>
                                <ul className="flex flex-col gap-2.5">
                                    {result.facts.map((fact) => (
                                        <li
                                            key={fact.id}
                                            className="flex flex-col gap-1.5 rounded-lg bg-cc-bg px-3.5 py-3"
                                        >
                                            <span className="cc-body">
                                                {fact.statement}
                                            </span>
                                            <SourceChip
                                                documentId={fact.documentId}
                                                title={fact.documentTitle}
                                                headingPath={fact.headingPath}
                                                pageFrom={fact.pageFrom}
                                                sectionId={fact.sectionId}
                                            />
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        )}

                        {result.passages.map((passage, index) => (
                            <article
                                key={`${passage.sectionId}-${index}`}
                                className="cc-panel flex flex-col gap-3 p-5"
                            >
                                <header className="flex flex-wrap items-center justify-between gap-2">
                                    <SourceChip
                                        documentId={passage.documentId}
                                        title={passage.documentTitle}
                                        versionNumber={passage.versionNumber}
                                        headingPath={passage.headingPath}
                                        pageFrom={passage.pageFrom}
                                        sectionId={passage.sectionId}
                                    />
                                    {debug && (
                                        <span className="font-mono text-[11px] text-cc-subtle">
                                            rrf {passage.score} · vec #
                                            {passage.ranks.vector ?? '–'} · fts
                                            #{passage.ranks.text ?? '–'} ·{' '}
                                            {passage.tokenCount} tok
                                            {passage.wholeSection
                                                ? ' · section'
                                                : ' · window'}
                                        </span>
                                    )}
                                </header>
                                <p className="cc-caption">
                                    {passage.headingPath}
                                </p>
                                <RichText text={passage.text} />
                            </article>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
