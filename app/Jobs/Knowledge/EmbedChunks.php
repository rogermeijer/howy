<?php

namespace App\Jobs\Knowledge;

use App\Enums\ProcessingStatus;
use App\Enums\ProcessingStep;
use App\Models\DocumentVersion;
use App\Models\KnowledgeChunk;
use App\Services\Knowledge\Ai\AiGateway;
use App\Services\Knowledge\ChunkText;
use App\Services\Knowledge\StepRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Step 4: embed the chunks that have no embedding yet, then make this version
 * the searchable one. Without an AI provider the chunks still become current,
 * so the document is at least findable by full-text search.
 */
class EmbedChunks extends KnowledgeJob
{
    public function handle(AiGateway $ai, StepRecorder $steps): void
    {
        $version = DocumentVersion::query()->with('document')->findOrFail($this->versionId);
        $version->update(['status' => ProcessingStatus::Embedding]);

        $model = (string) config('knowledge.embeddings.model');

        $embed = function () use ($version, $ai, $model): array {
            $chunks = KnowledgeChunk::query()
                ->with('section')
                ->where('source_type', 'document_version')
                ->where('source_id', $version->id)
                ->where(fn ($query) => $query->whereNull('embedding')->orWhere('embedding_model', '!=', $model))
                ->orderBy('ordinal')
                ->get();

            $texts = $chunks->map(fn (KnowledgeChunk $chunk): string => ChunkText::forEmbedding(
                $chunk,
                $version->document->title,
                $chunk->section->heading_path ?? '',
            ))->all();

            foreach ($ai->embed(array_values($texts), 'embed', $version) as $index => $vector) {
                $chunks[$index]->update(['embedding' => $vector, 'embedding_model' => $model]);
            }

            return ['embedded' => count($texts)];
        };

        if ($ai->enabled()) {
            $steps->run($version, ProcessingStep::Embed, $embed);
        } else {
            $steps->skip($version, ProcessingStep::Embed, 'No AI provider configured; searchable by text only.');
        }

        DB::transaction(function () use ($version): void {
            // Swap in one go: this version becomes the one that answers.
            $chunks = KnowledgeChunk::query()->where('document_id', $version->document_id)->where('source_type', 'document_version');

            (clone $chunks)->where('source_id', '!=', $version->id)->update(['is_current' => false]);
            (clone $chunks)->where('source_id', $version->id)->update(['is_current' => true]);

            $version->update(['status' => ProcessingStatus::Searchable, 'processed_at' => now()]);
            $version->document->touch();
        });
    }
}
