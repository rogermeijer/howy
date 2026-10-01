<?php

namespace App\Jobs\Knowledge;

use App\Enums\ProcessingStatus;
use App\Enums\ProcessingStep;
use App\Models\DocumentSection;
use App\Models\DocumentVersion;
use App\Services\Knowledge\Ai\AiGateway;
use App\Services\Knowledge\Ai\Batch\BatchRunner;
use App\Services\Knowledge\Enrichment\SectionEnrichment;
use App\Services\Knowledge\StepRecorder;

/**
 * Step 5: summaries and facts per section. Unchanged sections are carried
 * over for free; the rest goes to the batch API (or runs right away in sync
 * mode). The document stays searchable meanwhile.
 */
class EnrichDocumentVersion extends KnowledgeJob
{
    public function handle(AiGateway $ai, SectionEnrichment $enrichment, StepRecorder $steps, BatchRunner $batches): void
    {
        $version = DocumentVersion::query()->with('document')->findOrFail($this->versionId);

        if (! $ai->enabled()) {
            $steps->skip($version, ProcessingStep::Enrich, 'No AI provider configured.');
            $version->update(['status' => ProcessingStatus::Ready]);

            return;
        }

        $version->update(['status' => ProcessingStatus::Enriching]);

        $carried = $enrichment->carryOver($version);
        $pending = $enrichment->pending($version);
        $meta = ['carried_summaries' => $carried['summaries'], 'carried_facts' => $carried['facts'], 'expired_facts' => $carried['expired'], 'requests' => $pending->count()];

        if ($pending->isEmpty()) {
            $steps->finish($steps->start($version, ProcessingStep::Enrich, meta: $meta));
            FinishEnrichment::dispatch($version->id);

            return;
        }

        $requests = array_values($pending->map(fn (DocumentSection $section) => $enrichment->request($section, $version))->all());

        if (config('knowledge.enrichment.mode') === 'batch') {
            // Collected by knowledge:poll-batches when the provider is done.
            $steps->start($version, ProcessingStep::Enrich, $batches->submit($requests), $meta);

            return;
        }

        $record = $steps->start($version, ProcessingStep::Enrich, meta: $meta);
        $facts = 0;

        foreach ($requests as $index => $request) {
            $result = $ai->structured($request->agent, $request->prompt, $request->step, $version);
            $facts += $enrichment->apply($pending[$index], $version, $result, $ai->modelFor($request->step));
        }

        $steps->finish($record, meta: ['summaries' => count($requests), 'facts' => $facts]);

        FinishEnrichment::dispatch($version->id);
    }
}
