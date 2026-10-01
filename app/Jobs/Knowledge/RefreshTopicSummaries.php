<?php

namespace App\Jobs\Knowledge;

use App\Services\Knowledge\Ai\AiGateway;
use App\Services\Knowledge\Enrichment\TopicSummaryWriter;
use App\Tenancy\Concerns\InteractsWithTenancy;
use App\Tenancy\Contracts\TenantAware;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Rewrites the overviews of the account's stale folders. Unique per account:
 * several documents finishing at once lead to one refresh, not several.
 */
class RefreshTopicSummaries implements ShouldBeUnique, ShouldQueue, TenantAware
{
    use InteractsWithTenancy, Queueable;

    public int $timeout = 900;

    public function __construct()
    {
        $this->rememberTenant();
        $this->onQueue((string) config('knowledge.queue'));
    }

    public function uniqueId(): string
    {
        return (string) $this->tenantAccountId;
    }

    public function handle(AiGateway $ai, TopicSummaryWriter $writer): void
    {
        if ($ai->enabled()) {
            $writer->refreshStale();
        }
    }
}
