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

        // A version waiting for review is held against what is known first;
        // placing it starts the rest.
        $steps = $version->isApproved()
            ? [new ExtractDocumentText($this->versionId), ...self::afterPlacement($this->versionId)]
            : [new ExtractDocumentText($this->versionId), new PlaceDocumentVersion($this->versionId)];

        Bus::chain($steps)->onQueue((string) config('knowledge.queue'))->dispatch();
    }

    /**
     * @return list<KnowledgeJob>
     */
    public static function afterPlacement(int $versionId): array
    {
        return [
            new BuildDocumentStructure($versionId),
            new ContextualizeChunks($versionId),
            new EmbedChunks($versionId),
            new EnrichDocumentVersion($versionId),
        ];
    }
}
