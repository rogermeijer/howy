<?php

namespace App\Services\Knowledge\Search;

use App\Enums\DocumentType;
use App\Enums\FactStatus;
use App\Facades\Tenancy;
use App\Models\DocumentVersion;
use App\Models\Email;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeFact;
use App\Models\KnowledgeTopic;
use App\Models\KnowledgeTopicLink;
use App\Services\Knowledge\Ai\AiGateway;
use App\Services\Knowledge\TokenEstimator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Hybrid retrieval in Postgres: pgvector similarity and full-text search each
 * rank the account's current chunks, and reciprocal rank fusion combines the
 * two lists. Results are widened from chunk to section ("small to big") and
 * cut off at a hard token budget.
 *
 * Every query is built from KnowledgeChunk / KnowledgeFact Eloquent builders,
 * so the account scope is part of every subquery; only the fusion step reads
 * from those subqueries, never from a table directly.
 */
class HybridKnowledgeSearch implements KnowledgeSearch
{
    /** Chunks considered for passages after fusion. */
    private const int FUSED_LIMIT = 20;

    private const int FACT_LIMIT = 8;

    public function __construct(
        private readonly AiGateway $ai,
        private readonly TokenEstimator $tokens,
    ) {}

    public function search(SearchQuery $query): SearchResult
    {
        $text = trim($query->text);

        if ($text === '') {
            return new SearchResult([], [], 0, false);
        }

        $language = $query->language ?? Tenancy::account()->locale->searchConfiguration();
        $vector = $this->queryVector($text);
        $budget = $query->maxTokens ?? (int) config('knowledge.search.max_tokens');

        return DB::transaction(function () use ($query, $text, $language, $vector, $budget): SearchResult {
            if ($vector !== null) {
                // Keep scanning the HNSW graph until enough rows pass the
                // account filter, instead of returning a short list.
                DB::statement("SET LOCAL hnsw.iterative_scan = 'relaxed_order'");
                DB::statement('SET LOCAL hnsw.ef_search = 100');
            }

            $facts = $query->includeFacts ? $this->facts($query, $text, $language, $vector) : [];
            $factTokens = array_sum(array_map(fn (FactHit $fact): int => $this->tokens->count($fact->statement), $facts));

            $ranked = $this->rank(
                KnowledgeChunk::query()->where('knowledge_chunks.is_current', true)->tap(fn (Builder $builder) => $this->filterChunks($builder, $query)),
                $text,
                $language,
                $vector,
                $query->candidateLimit ?? (int) config('knowledge.search.candidate_limit'),
                self::FUSED_LIMIT,
            );

            $passages = $this->passages($ranked, max(0, $budget - $factTokens));
            $tokens = $factTokens + array_sum(array_map(fn (Passage $passage): int => $passage->tokenCount, $passages));

            return new SearchResult($passages, $facts, $tokens, $vector !== null);
        });
    }

    /**
     * Rank with both methods and fuse: score = Σ 1 / (k + rank).
     *
     * @template TModel of KnowledgeChunk|KnowledgeFact
     *
     * @param  Builder<TModel>  $base
     * @param  list<float>|null  $vector
     * @return array<int, array{score: float, vector: int|null, text: int|null}> by id, best first
     */
    private function rank(Builder $base, string $text, string $language, ?array $vector, int $candidates, int $limit): array
    {
        // Literal names: these are interpolated into raw SQL.
        $table = $base->getModel() instanceof KnowledgeFact ? 'knowledge_facts' : 'knowledge_chunks';
        $tsquery = 'websearch_to_tsquery(?::regconfig, ?)';
        $text = $this->anyOf($text);

        $byText = (clone $base)
            ->select("{$table}.id")
            ->selectRaw("row_number() over (order by ts_rank_cd({$table}.search_vector, {$tsquery}) desc) as rnk", [$language, $text])
            ->selectRaw("'t' as method")
            ->selectRaw('null::float as similarity')
            ->whereRaw("{$table}.search_vector @@ {$tsquery}", [$language, $text])
            ->orderByRaw("ts_rank_cd({$table}.search_vector, {$tsquery}) desc", [$language, $text])
            ->limit($candidates);

        $union = $byText;

        if ($vector !== null) {
            $literal = '['.implode(',', $vector).']';

            $union = (clone $base)
                ->select("{$table}.id")
                ->selectRaw("row_number() over (order by {$table}.embedding <=> ?::vector) as rnk", [$literal])
                ->selectRaw("'v' as method")
                ->selectRaw("1 - ({$table}.embedding <=> ?::vector) as similarity", [$literal])
                ->whereNotNull("{$table}.embedding")
                ->whereRaw("{$table}.embedding <=> ?::vector <= ?", [$literal, 1 - (float) config('knowledge.search.min_similarity')])
                ->orderByRaw("{$table}.embedding <=> ?::vector", [$literal])
                ->limit($candidates)
                ->unionAll($byText);
        }

        $rows = DB::query()
            ->fromSub($union->toBase(), 'ranked')
            ->select('id')
            ->selectRaw('sum(1.0 / (? + rnk)) as score', [(int) config('knowledge.search.rrf_k')])
            ->selectRaw("min(rnk) filter (where method = 'v') as vector_rank")
            ->selectRaw("min(rnk) filter (where method = 't') as text_rank")
            ->selectRaw('max(similarity) as similarity')
            ->groupBy('id')
            ->orderByDesc('score')
            ->limit($limit * 2)
            ->get();

        $ranked = [];
        $vectorOnly = (float) config('knowledge.search.min_similarity_vector_only');

        foreach ($rows as $row) {
            /** @var object{id: int, score: float|string, vector_rank: int|null, text_rank: int|null, similarity: float|string|null} $row */

            // Found by meaning alone and only loosely related: not an answer.
            if ($row->text_rank === null && (float) $row->similarity < $vectorOnly) {
                continue;
            }

            if (count($ranked) >= $limit) {
                break;
            }

            $ranked[(int) $row->id] = [
                'score' => (float) $row->score,
                'vector' => $row->vector_rank === null ? null : (int) $row->vector_rank,
                'text' => $row->text_rank === null ? null : (int) $row->text_rank,
            ];
        }

        return $ranked;
    }

    /**
     * A question as an OR of its words: "Hoeveel vakantiedagen krijg ik"
     * should match a text with only "vakantiedagen" in it. Ranking puts texts
     * with more of the words first; stop words are dropped by the stemmer.
     */
    private function anyOf(string $text): string
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', $text, flags: PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' or ', array_unique($words));
    }

    /**
     * Widen each hit to its whole section when that fits, or to the hit and
     * its neighbours, until the token budget is spent.
     *
     * @param  array<int, array{score: float, vector: int|null, text: int|null}>  $ranked
     * @return list<Passage>
     */
    private function passages(array $ranked, int $budget): array
    {
        if ($ranked === []) {
            return [];
        }

        /** @var Collection<int, KnowledgeChunk> $hits */
        $hits = KnowledgeChunk::query()->with(['section', 'document'])->whereKey(array_keys($ranked))->get()->keyBy('id');

        $sectionIds = $hits->pluck('section_id')->filter()->unique()->values();
        /** @var \Illuminate\Support\Collection<int, Collection<int, KnowledgeChunk>> $siblings */
        $siblings = KnowledgeChunk::query()->whereIn('section_id', $sectionIds)->orderBy('ordinal')->get()->groupBy('section_id');

        $versions = DocumentVersion::query()
            ->whereKey($hits->where('source_type', 'document_version')->pluck('source_id')->unique()->values())
            ->pluck('version_number', 'id');

        $passages = [];
        $used = 0;
        $seen = [];

        foreach ($ranked as $id => $rank) {
            $chunk = $hits->get($id);

            if ($chunk === null || isset($seen[$chunk->section_id ?? 'c'.$chunk->id])) {
                continue;
            }

            $remaining = $budget - $used;
            $section = $chunk->section;
            /** @var Collection<int, KnowledgeChunk> $family */
            $family = $chunk->section_id === null ? new Collection([$chunk]) : $siblings->get($chunk->section_id, new Collection([$chunk]));
            $sectionTokens = (int) $family->sum('token_count');

            if ($sectionTokens <= $remaining && $sectionTokens <= $budget / 2) {
                $parts = $family;
                $whole = true;
            } else {
                $parts = $family->filter(fn (KnowledgeChunk $sibling): bool => abs($sibling->ordinal - $chunk->ordinal) <= 1)->values();
                $whole = false;

                if ((int) $parts->sum('token_count') > $remaining) {
                    $parts = new Collection([$chunk]);
                }
            }

            $tokens = (int) $parts->sum('token_count');

            if ($tokens > $remaining) {
                continue;
            }

            $seen[$chunk->section_id ?? 'c'.$chunk->id] = true;
            $used += $tokens;

            $pages = $parts->pluck('page_from')->merge($parts->pluck('page_to'))->filter(fn ($page) => $page !== null);

            $passages[] = new Passage(
                documentId: (int) $chunk->document_id,
                versionId: $chunk->source_id,
                versionNumber: (int) ($versions[$chunk->source_id] ?? 1),
                documentTitle: $chunk->document->title ?? '',
                sectionId: $chunk->section_id,
                headingPath: $section->heading_path ?? '',
                pageFrom: $pages->isEmpty() ? null : (int) $pages->min(),
                pageTo: $pages->isEmpty() ? null : (int) $pages->max(),
                text: $parts->pluck('content')->implode("\n\n"),
                score: $rank['score'],
                tokenCount: $tokens,
                chunkIds: array_values($parts->pluck('id')->map(fn ($chunkId): int => (int) $chunkId)->all()),
                ranks: ['vector' => $rank['vector'], 'text' => $rank['text']],
                wholeSection: $whole,
            );

            if ($used >= $budget) {
                break;
            }
        }

        return $passages;
    }

    /**
     * @param  list<float>|null  $vector
     * @return list<FactHit>
     */
    private function facts(SearchQuery $query, string $text, string $language, ?array $vector): array
    {
        $statuses = array_map(fn (FactStatus $status): string => $status->value, $query->factStatuses);

        $base = KnowledgeFact::query()
            ->whereIn('knowledge_facts.status', $statuses)
            ->when($query->effectiveFrom, fn (Builder $builder, $date) => $builder->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', $date)))
            ->when($query->effectiveUntil, fn (Builder $builder, $date) => $builder->where(fn (Builder $q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $date)))
            ->when($query->topicId, fn (Builder $builder, int $topicId) => $this->inTopic($builder, $topicId, 'knowledge_facts.section_id'));

        $ranked = $this->rank($base, $text, $language, $vector, (int) config('knowledge.search.candidate_limit'), self::FACT_LIMIT);

        if ($ranked === []) {
            return [];
        }

        $facts = KnowledgeFact::query()->with(['document', 'section'])->whereKey(array_keys($ranked))->get()->keyBy('id');

        // Facts taken from mail cite the mail; everything else cites a document.
        $emails = Email::query()
            ->whereKey($facts->where('source_type', 'email')->pluck('source_id')->all())
            ->pluck('subject', 'id');

        $hits = [];

        foreach ($ranked as $id => $rank) {
            $fact = $facts->get($id);

            if ($fact !== null) {
                $hits[] = new FactHit(
                    $fact->id,
                    $fact->statement,
                    $fact->status,
                    $fact->valid_from?->toDateString(),
                    $fact->document_id,
                    $fact->document?->title,
                    $fact->section_id,
                    $fact->section?->heading_path,
                    $fact->page_from,
                    $rank['score'],
                    $fact->source_type === 'email' ? $fact->source_id : null,
                    $fact->source_type === 'email' ? (string) ($emails[$fact->source_id] ?? '') : null,
                );
            }
        }

        return $hits;
    }

    /**
     * @param  Builder<KnowledgeChunk>  $builder
     */
    private function filterChunks(Builder $builder, SearchQuery $query): void
    {
        if ($query->documentTypes !== [] || $query->effectiveFrom !== null || $query->effectiveUntil !== null) {
            $builder->whereHas('document', function (Builder $document) use ($query): void {
                if ($query->documentTypes !== []) {
                    $document->whereIn('type', array_map(fn (DocumentType $type): string => $type->value, $query->documentTypes));
                }
                if ($query->effectiveFrom !== null) {
                    $document->where('effective_date', '>=', $query->effectiveFrom);
                }
                if ($query->effectiveUntil !== null) {
                    $document->where('effective_date', '<=', $query->effectiveUntil);
                }
            });
        }

        if ($query->topicId !== null) {
            $this->inTopic($builder, $query->topicId, 'knowledge_chunks.section_id');
        }
    }

    /**
     * Only rows whose section is filed in the topic or one of its subfolders.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $builder
     */
    private function inTopic(Builder $builder, int $topicId, string $sectionColumn): void
    {
        $topic = KnowledgeTopic::query()->findOrFail($topicId);

        $builder->whereIn($sectionColumn, KnowledgeTopicLink::query()
            ->select('linkable_id')
            ->where('linkable_type', 'document_section')
            ->whereIn('topic_id', KnowledgeTopic::query()->subtreeOf($topic)->select('id')));
    }

    /**
     * The question's embedding, cached briefly: the same question is often
     * asked again (and evaluated) without paying for it twice.
     *
     * @return list<float>|null
     */
    private function queryVector(string $text): ?array
    {
        if (! $this->ai->enabled()) {
            return null;
        }

        $key = 'knowledge:query-vector:'.Tenancy::id().':'.sha1(config('knowledge.embeddings.model').'|'.config('knowledge.embeddings.dimensions').'|'.$text);

        try {
            /** @var list<float> $vector */
            $vector = Cache::remember($key, now()->addHours((int) config('knowledge.search.query_embedding_cache_hours')), fn (): array => $this->ai->embed([$text], 'search')[0]);

            return $vector;
        } catch (Throwable $e) {
            // Full-text search still answers.
            report($e);

            return null;
        }
    }
}
