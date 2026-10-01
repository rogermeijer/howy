<?php

namespace App\Jobs\Knowledge;

use App\Enums\ProcessingStatus;
use App\Enums\ProcessingStep;
use App\Enums\SectionChange;
use App\Models\DocumentSection;
use App\Models\DocumentVersion;
use App\Models\KnowledgeChunk;
use App\Services\Knowledge\Chunking\StructuralChunker;
use App\Services\Knowledge\StepRecorder;
use App\Services\Knowledge\Structure\SectionMatcher;
use App\Services\Knowledge\Structure\StructureBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Step 2: build the section tree, match it against the previous version and
 * split it into chunks.
 *
 * A chunk whose heading path and text are identical to one in the previous
 * version takes over its context line and embedding: that work was paid for
 * once and is not repeated. New chunks stay out of search (is_current = false)
 * until they are embedded, so the previous version keeps answering meanwhile;
 * a version that is processed again keeps answering throughout.
 */
class BuildDocumentStructure extends KnowledgeJob
{
    public function handle(StructureBuilder $builder, SectionMatcher $matcher, StructuralChunker $chunker, StepRecorder $steps): void
    {
        $version = DocumentVersion::query()->with('document')->findOrFail($this->versionId);
        $version->update(['status' => ProcessingStatus::Structuring]);

        $steps->run($version, ProcessingStep::Structure, function () use ($version, $builder, $matcher, $chunker): array {
            $drafts = $builder->build(ExtractDocumentText::load($version));

            $previous = $version->previous();
            $previousSections = $previous === null
                ? collect()
                : DocumentSection::query()->with('chunks')->where('document_version_id', $previous->id)->get();

            $matcher->match($drafts, $previousSections);

            // Reuse from the previous version and, when this version is processed
            // again, from its own earlier run.
            /** @var array<string, KnowledgeChunk> $reusable */
            $reusable = KnowledgeChunk::query()
                ->where('source_type', 'document_version')
                ->whereIn('source_id', array_filter([$previous?->id, $version->id]))
                ->where(fn ($query) => $query->whereNotNull('embedding')->orWhereNotNull('context'))
                ->orderBy('source_id')
                ->get()
                ->keyBy('content_hash')
                ->all();

            // A version that already answers keeps answering while it is rebuilt.
            $wasCurrent = KnowledgeChunk::query()->where('source_type', 'document_version')->where('source_id', $version->id)->where('is_current', true)->exists();

            $searchConfig = $version->document->language->searchConfiguration();

            return DB::transaction(function () use ($version, $drafts, $chunker, $reusable, $searchConfig, $wasCurrent): array {
                // Idempotent: a retry or "process again" starts from scratch.
                KnowledgeChunk::query()->where('source_type', 'document_version')->where('source_id', $version->id)->delete();
                DocumentSection::query()->where('document_version_id', $version->id)->delete();

                $ids = [];
                $chunks = 0;
                $reused = 0;
                $ordinal = 0;

                foreach ($drafts as $draft) {
                    $section = DocumentSection::create([
                        'document_id' => $version->document_id,
                        'document_version_id' => $version->id,
                        'parent_id' => $draft->parentOrdinal === null ? null : $ids[$draft->parentOrdinal],
                        'level' => $draft->level,
                        'ordinal' => $draft->ordinal,
                        'heading' => $draft->heading,
                        'heading_path' => $draft->headingPath,
                        'page_from' => $draft->pageFrom,
                        'page_to' => $draft->pageTo,
                        'content_hash' => $draft->contentHash,
                        'token_count' => $draft->tokenCount,
                        'previous_section_id' => $draft->previousSectionId,
                        'change_type' => $draft->change,
                    ]);
                    $ids[$draft->ordinal] = $section->id;

                    foreach ($chunker->chunk($draft) as $chunk) {
                        $hash = hash('sha256', $draft->headingPath."\n".$chunk->content);
                        $previous = $reusable[$hash] ?? null;

                        KnowledgeChunk::create([
                            'source_type' => 'document_version',
                            'source_id' => $version->id,
                            'document_id' => $version->document_id,
                            'section_id' => $section->id,
                            'ordinal' => $ordinal++,
                            'kind' => $chunk->kind,
                            'content' => $chunk->content,
                            'context' => $previous?->context,
                            'page_from' => $chunk->pageFrom,
                            'page_to' => $chunk->pageTo,
                            'token_count' => $chunk->tokenCount,
                            'content_hash' => $hash,
                            'embedding' => $previous?->embedding,
                            'embedding_model' => $previous?->embedding_model,
                            'is_current' => $wasCurrent,
                            'search_config' => $searchConfig,
                        ]);

                        $chunks++;
                        $reused += $previous === null ? 0 : 1;
                    }
                }

                return [
                    'sections' => count($drafts),
                    'unchanged_sections' => count(array_filter($drafts, fn ($draft): bool => $draft->change === SectionChange::Unchanged)),
                    'chunks' => $chunks,
                    'reused_chunks' => $reused,
                ];
            });
        });
    }
}
