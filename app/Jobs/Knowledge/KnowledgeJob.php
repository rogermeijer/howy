<?php

namespace App\Jobs\Knowledge;

use App\Enums\ProcessingStatus;
use App\Facades\Tenancy;
use App\Models\DocumentVersion;
use App\Tenancy\Concerns\InteractsWithTenancy;
use App\Tenancy\Contracts\TenantAware;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Jobs\SyncJob;
use Throwable;

/**
 * A step of processing one document version. Steps run as a chain on the
 * knowledge queue; each carries the version id, never the model, because
 * SerializesModels would re-fetch it before the tenant context exists.
 */
abstract class KnowledgeJob implements ShouldQueue, TenantAware
{
    use InteractsWithTenancy, Queueable;

    public int $tries = 3;

    /** Large documents take a while to extract and contextualise. */
    public int $timeout = 900;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $versionId)
    {
        $this->rememberTenant();
        $this->onQueue((string) config('knowledge.queue'));
    }

    /**
     * On the last attempt an optional step gives up instead of failing the
     * chain: the document stays findable without what that step adds.
     */
    protected function isLastAttempt(): bool
    {
        return $this->job === null || $this->job instanceof SyncJob || $this->attempts() >= $this->tries;
    }

    /**
     * Runs outside the job middleware, so it sets the tenant itself.
     */
    public function failed(?Throwable $exception): void
    {
        Tenancy::for($this->tenantAccountId, function () use ($exception): void {
            $version = DocumentVersion::query()->find($this->versionId);

            if ($version === null) {
                return;
            }

            // Once searchable, a later step failing (summaries, folders) leaves
            // the document usable: it keeps its status and shows the error.
            $version->update([
                'status' => $version->status->isSearchable() ? ProcessingStatus::Searchable : ProcessingStatus::Failed,
                'error' => $exception?->getMessage() ?? __('Processing failed.'),
            ]);
        });
    }
}
