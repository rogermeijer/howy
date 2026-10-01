<?php

namespace App\Jobs\Knowledge;

use App\Enums\ProcessingStatus;
use App\Enums\ProcessingStep;
use App\Enums\StepStatus;
use App\Models\DocumentProcessingStep;
use App\Models\DocumentSection;
use App\Models\DocumentVersion;
use App\Services\Knowledge\Ai\AiGateway;
use App\Services\Knowledge\Ai\Batch\BatchRunner;
use App\Services\Knowledge\Ai\Batch\BatchStatus;
use App\Services\Knowledge\Enrichment\SectionEnrichment;
use App\Services\Knowledge\StepRecorder;
use Illuminate\Contracts\Queue\ShouldBeUnique;

/**
 * Checks a version's enrichment batch and, once the provider is done, stores
 * its summaries and facts and finishes the pipeline.
 */
class CollectEnrichmentBatch extends KnowledgeJob implements ShouldBeUnique
{
    public function uniqueId(): string
    {
        return (string) $this->versionId;
    }

    public function handle(BatchRunner $batches, SectionEnrichment $enrichment, AiGateway $ai, StepRecorder $steps): void
    {
        $version = DocumentVersion::query()->with('document')->findOrFail($this->versionId);

        $record = DocumentProcessingStep::query()
            ->where('document_version_id', $version->id)
            ->where('step', ProcessingStep::Enrich)
            ->where('status', StepStatus::Running)
            ->whereNotNull('provider_batch_id')
            ->first();

        if ($record === null) {
            return;
        }

        $status = $batches->status((string) $record->provider_batch_id);

        if ($status->state === BatchStatus::PENDING) {
            return;
        }

        if ($status->state === BatchStatus::FAILED) {
            $steps->finish($record, $status->error ?? __('The batch failed.'));
            // Still searchable: only the summaries and facts are missing.
            $version->update(['status' => ProcessingStatus::Searchable, 'error' => $status->error]);

            return;
        }

        $sections = DocumentSection::query()->with('chunks')->where('document_version_id', $version->id)->get()->keyBy('id');
        $facts = 0;
        $failed = 0;

        foreach ($status->results as $result) {
            $section = $sections->get((int) str_replace('section:', '', $result->customId));

            if ($section === null || $result->structured === null) {
                $failed++;

                continue;
            }

            $ai->record('summary', $version, $ai->provider(), $result->model, $result->inputTokens, $result->cachedInputTokens, $result->outputTokens, batch: true);
            $facts += $enrichment->apply($section, $version, $result->structured, $result->model);
        }

        $steps->finish($record, meta: ['summaries' => count($status->results) - $failed, 'facts' => $facts, 'failed_requests' => $failed]);

        FinishEnrichment::dispatch($version->id);
    }
}
