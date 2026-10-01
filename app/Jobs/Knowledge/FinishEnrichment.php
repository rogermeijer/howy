<?php

namespace App\Jobs\Knowledge;

use App\Enums\ProcessingStatus;
use App\Enums\ProcessingStep;
use App\Models\DocumentSection;
use App\Models\DocumentVersion;
use App\Models\KnowledgeFact;
use App\Models\KnowledgeSummary;
use App\Services\Knowledge\Ai\Agents\DocumentSummarizer;
use App\Services\Knowledge\Ai\AiGateway;
use App\Services\Knowledge\Enrichment\TopicOrganizer;
use App\Services\Knowledge\StepRecorder;
use App\Services\Knowledge\TokenEstimator;

/**
 * Step 6: the document summary (from the section summaries), filing the
 * sections into folders, and embedding the new facts. Then the version is
 * ready, and the overviews of the folders it touched are refreshed.
 */
class FinishEnrichment extends KnowledgeJob
{
    public function handle(AiGateway $ai, TopicOrganizer $topics, StepRecorder $steps, TokenEstimator $tokens): void
    {
        $version = DocumentVersion::query()->with('document')->findOrFail($this->versionId);

        $this->summarize($version, $ai, $tokens);

        $steps->run($version, ProcessingStep::Topics, fn (): array => $topics->organize($version));

        $steps->run($version, ProcessingStep::EmbedFacts, function () use ($version, $ai): array {
            $facts = KnowledgeFact::query()->where('document_version_id', $version->id)->whereNull('embedding')->get();

            $vectors = $ai->embed(
                array_values($facts->map(fn (KnowledgeFact $fact): string => trim(($fact->subject ? $fact->subject.': ' : '').$fact->statement))->all()),
                'embed',
                $version,
            );

            foreach ($vectors as $index => $vector) {
                $facts[$index]->update(['embedding' => $vector, 'embedding_model' => config('knowledge.embeddings.model')]);
            }

            return ['embedded' => count($vectors)];
        });

        $version->update(['status' => ProcessingStatus::Ready, 'error' => null]);
        $version->document->touch();

        RefreshTopicSummaries::dispatch();
    }

    /**
     * Built from the section summaries; skipped when they did not change.
     */
    private function summarize(DocumentVersion $version, AiGateway $ai, TokenEstimator $tokens): void
    {
        $sections = DocumentSection::query()->with('summary')->where('document_version_id', $version->id)->orderBy('ordinal')->get();

        $outline = $sections->map(fn (DocumentSection $section): string => str_repeat('  ', max(0, $section->level - 1)).'- '.($section->heading ?? '(introduction)').($section->summary ? ': '.$section->summary->text : ''))->implode("\n");
        $hash = hash('sha256', $outline);

        $previous = $version->previous();
        $carried = $previous === null ? null : KnowledgeSummary::query()
            ->where('summarizable_type', 'document_version')
            ->where('summarizable_id', $previous->id)
            ->where('input_hash', $hash)
            ->first();

        if ($carried !== null) {
            $carried->replicate(['account_id'])->fill(['summarizable_id' => $version->id])->save();

            return;
        }

        $summary = trim((string) ($ai->structured(new DocumentSummarizer, "Document: {$version->document->title}\n\n{$outline}", 'summary', $version)['summary'] ?? ''));

        if ($summary !== '') {
            KnowledgeSummary::query()->updateOrCreate(
                ['summarizable_type' => 'document_version', 'summarizable_id' => $version->id],
                ['text' => $summary, 'token_count' => $tokens->count($summary), 'input_hash' => $hash, 'model' => $ai->modelFor('summary')],
            );
        }
    }
}
