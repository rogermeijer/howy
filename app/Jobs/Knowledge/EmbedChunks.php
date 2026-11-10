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
use Throwable;

/**
 * Step 4: embed the chunks that have no embedding yet, then, for a published
 * version, make it the searchable one. Without an AI provider the chunks
 * still become current, so the document is at least findable by full-text
 * search. A version waiting for review stays out of search until approved.
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

        $error = null;

        if ($ai->enabled()) {
            try {
                $steps->run($version, ProcessingStep::Embed, $embed);
            } catch (Throwable $e) {
                if (! $this->isLastAttempt()) {
                    throw $e;
                }

                // Without (all) embeddings the document is still found by text.
                report($e);
                $error = (string) __('Indexing for meaning failed; the document is searchable by text.');
            }
        } else {
            $steps->skip($version, ProcessingStep::Embed, 'No AI provider configured; searchable by text only.');
        }

        DB::transaction(function () use ($version, $error): void {
            if ($version->isApproved()) {
                $version->makeCurrent();
            }

            $version->update([
                'status' => $version->isApproved() ? ProcessingStatus::Searchable : ProcessingStatus::Embedding,
                'processed_at' => now(),
                'error' => $error,
            ]);
            $version->document->touch();
        });
    }
}
