<?php

namespace App\Jobs\Knowledge;

use App\Enums\ProcessingStatus;
use App\Models\DocumentVersion;
use Illuminate\Support\Facades\Bus;

/**
 * Starts (or restarts) the pipeline for a document version: every step is a
 * job in one chain, so a failed step stops the rest and can be retried on its
 * own with "Process again".
 */
class ProcessDocumentVersion extends KnowledgeJob
{
    public int $tries = 1;

    public function handle(): void
    {
        $version = DocumentVersion::query()->find($this->versionId);

        if ($version === null) {
            return;
        }

        $version->update(['status' => ProcessingStatus::Queued, 'error' => null]);

        $steps = $this->steps();

        if ($steps !== []) {
            Bus::chain($steps)->onQueue((string) config('knowledge.queue'))->dispatch();
        }
    }

    /**
     * @return list<KnowledgeJob>
     */
    private function steps(): array
    {
        return [
            new ExtractDocumentText($this->versionId),
            new BuildDocumentStructure($this->versionId),
        ];
    }
}
