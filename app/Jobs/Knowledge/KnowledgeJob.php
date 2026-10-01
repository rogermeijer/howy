<?php

namespace App\Jobs\Knowledge;

use App\Enums\ProcessingStatus;
use App\Facades\Tenancy;
use App\Models\DocumentVersion;
use App\Tenancy\Concerns\InteractsWithTenancy;
use App\Tenancy\Contracts\TenantAware;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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
     * Runs outside the job middleware, so it sets the tenant itself.
     */
    public function failed(?Throwable $exception): void
    {
        Tenancy::for($this->tenantAccountId, function () use ($exception): void {
            DocumentVersion::query()->whereKey($this->versionId)->update([
                'status' => ProcessingStatus::Failed,
                'error' => $exception?->getMessage() ?? __('Processing failed.'),
            ]);
        });
    }
}
