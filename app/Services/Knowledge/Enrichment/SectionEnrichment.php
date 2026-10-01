<?php

namespace App\Services\Knowledge\Enrichment;

use App\Enums\FactStatus;
use App\Enums\SectionChange;
use App\Models\DocumentSection;
use App\Models\DocumentVersion;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeFact;
use App\Models\KnowledgeSummary;
use App\Models\KnowledgeTopicLink;
use App\Services\Knowledge\Ai\Agents\SectionEnricher;
use App\Services\Knowledge\Ai\Batch\BatchRequest;
use App\Services\Knowledge\TokenEstimator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Summaries and facts per section.
 *
 * What did not change since the previous version is carried over without a
 * single token: its summary is copied, its facts move to the new version and
 * keep their identity, its folder links come along. Facts of sections that
 * changed or disappeared expire, and are linked to their successor when a new
 * fact about the same subject replaces them.
 */
class SectionEnrichment
{
    public function __construct(private readonly TokenEstimator $tokens) {}

    /**
     * @return array{summaries: int, facts: int, links: int, expired: int}
     */
    public function carryOver(DocumentVersion $version): array
    {
        $previous = $version->previous();
        $counts = ['summaries' => 0, 'facts' => 0, 'links' => 0, 'expired' => 0];

        if ($previous === null) {
            return $counts;
        }

        return DB::transaction(function () use ($version, $previous, $counts): array {
            $sections = DocumentSection::query()
                ->where('document_version_id', $version->id)
                ->where('change_type', SectionChange::Unchanged)
                ->whereNotNull('previous_section_id')
                ->get();

            foreach ($sections as $section) {
                $old = (int) $section->previous_section_id;

                $summary = KnowledgeSummary::query()->where('summarizable_type', 'document_section')->where('summarizable_id', $old)->first();

                if ($summary !== null) {
                    $summary->replicate(['account_id'])->fill(['summarizable_id' => $section->id])->save();
                    $counts['summaries']++;
                }

                $counts['facts'] += KnowledgeFact::query()
                    ->where('section_id', $old)
                    ->where('status', '!=', FactStatus::Expired)
                    ->update([
                        'section_id' => $section->id,
                        'document_version_id' => $version->id,
                        'source_id' => $version->id,
                        'chunk_id' => null,
                    ]);

                foreach (KnowledgeTopicLink::query()->where('linkable_type', 'document_section')->where('linkable_id', $old)->get() as $link) {
                    $link->replicate(['account_id'])->fill(['linkable_id' => $section->id])->save();
                    $counts['links']++;
                }
            }

            // Whatever is still attached to the previous version no longer holds.
            $counts['expired'] = KnowledgeFact::query()
                ->where('document_version_id', $previous->id)
                ->where('status', '!=', FactStatus::Expired)
                ->update(['status' => FactStatus::Expired, 'valid_until' => now()->toDateString()]);

            return $counts;
        });
    }

    /**
     * Sections with text of their own and no summary yet.
     *
     * @return Collection<int, DocumentSection>
     */
    public function pending(DocumentVersion $version): Collection
    {
        return DocumentSection::query()
            ->where('document_version_id', $version->id)
            ->whereHas('chunks')
            ->whereDoesntHave('summary')
            ->with('chunks')
            ->orderBy('ordinal')
            ->get();
    }

    public function request(DocumentSection $section, DocumentVersion $version): BatchRequest
    {
        $document = $version->document;
        $text = $section->chunks->pluck('content')->implode("\n\n");

        $prompt = implode("\n", array_filter([
            "Document: {$document->title}",
            'Section: '.($section->heading_path ?: '(introduction)'),
            $section->page_from ? "Pages: {$section->page_from}–{$section->page_to}" : null,
            $document->is_core
                ? 'Extract the facts of this section.'
                : 'This is not a core document: do not extract facts, return an empty list.',
            '',
            "<section>\n{$text}\n</section>",
        ], fn (?string $line): bool => $line !== null));

        return new BatchRequest('section:'.$section->id, new SectionEnricher, $prompt, 'summary');
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function apply(DocumentSection $section, DocumentVersion $version, array $result, string $model): int
    {
        $summary = trim((string) ($result['summary'] ?? ''));

        if ($summary !== '') {
            KnowledgeSummary::query()->updateOrCreate(
                ['summarizable_type' => 'document_section', 'summarizable_id' => $section->id],
                ['text' => $summary, 'token_count' => $this->tokens->count($summary), 'input_hash' => $section->content_hash, 'model' => $model],
            );
        }

        if (! $version->document->is_core) {
            return 0;
        }

        /** @var list<array{statement?: string, subject?: string, valid_from?: string|null, confidence?: float|int}> $facts */
        $facts = is_array($result['facts'] ?? null) ? $result['facts'] : [];
        $created = 0;

        foreach ($facts as $fact) {
            $statement = trim((string) ($fact['statement'] ?? ''));

            if ($statement === '') {
                continue;
            }

            $subject = mb_strtolower(trim((string) ($fact['subject'] ?? ''))) ?: null;
            $hash = hash('sha256', mb_strtolower((string) preg_replace('/\s+/u', ' ', $statement)));

            // The same claim twice in one version is one fact.
            if (KnowledgeFact::query()->where('document_version_id', $version->id)->where('content_hash', $hash)->exists()) {
                continue;
            }

            $new = KnowledgeFact::create([
                'source_type' => 'document_version',
                'source_id' => $version->id,
                'document_id' => $version->document_id,
                'document_version_id' => $version->id,
                'section_id' => $section->id,
                'chunk_id' => $this->bestChunk($section->chunks, $statement)?->id,
                'statement' => $statement,
                'subject' => $subject === null ? null : mb_substr($subject, 0, 120),
                'status' => FactStatus::Core,
                'valid_from' => $this->date($fact['valid_from'] ?? null) ?? $version->document->effective_date?->toDateString(),
                'confidence' => isset($fact['confidence']) ? max(0, min(1, (float) $fact['confidence'])) : null,
                'page_from' => $section->page_from,
                'page_to' => $section->page_to,
                'content_hash' => $hash,
                'search_config' => $version->document->language->searchConfiguration(),
            ]);
            $created++;

            // An expired fact about the same subject in the section this one
            // replaces is superseded by it.
            if ($subject !== null && $section->previous_section_id !== null) {
                KnowledgeFact::query()
                    ->where('section_id', $section->previous_section_id)
                    ->where('status', FactStatus::Expired)
                    ->where('subject', $subject)
                    ->whereNull('superseded_by_id')
                    ->update(['superseded_by_id' => $new->id]);
            }
        }

        return $created;
    }

    /**
     * The chunk that shares the most words with the fact: where to point a
     * reader who wants to see it in context.
     *
     * @param  Collection<int, KnowledgeChunk>  $chunks
     */
    private function bestChunk(Collection $chunks, string $statement): ?KnowledgeChunk
    {
        $words = fn (string $text): array => array_flip(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), flags: PREG_SPLIT_NO_EMPTY) ?: []);
        $wanted = $words($statement);

        return $chunks->sortByDesc(fn (KnowledgeChunk $chunk): int => count(array_intersect_key($wanted, $words($chunk->content))))->first();
    }

    private function date(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && strtotime($value) !== false ? $value : null;
    }
}
