<?php

namespace App\Tenancy\Concerns;

use App\Facades\Tenancy;
use App\Tenancy\Contracts\TenantAware;
use App\Tenancy\Middleware\RunInTenantContext;

/**
 * Carries the dispatching account into a queued job.
 *
 * Tenant context is deliberately not propagated automatically: a job that should
 * run globally would otherwise inherit whichever account happened to dispatch it,
 * invisibly. Opting in is one line, and forgetting it fails loudly — the property
 * is typed non-nullable and uninitialised, so serialisation throws.
 *
 * Call rememberTenant() from the constructor, which runs while the dispatching
 * request still has its context:
 *
 *     class SyncInbox implements ShouldQueue, TenantAware
 *     {
 *         use InteractsWithTenancy, Queueable;
 *
 *         public function __construct(public Email $email)
 *         {
 *             $this->rememberTenant();
 *         }
 *     }
 *
 * @see TenantAware
 */
trait InteractsWithTenancy
{
    public int $tenantAccountId;

    public function tenantAccountId(): int
    {
        return $this->tenantAccountId;
    }

    protected function rememberTenant(): void
    {
        $this->tenantAccountId = Tenancy::id();
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RunInTenantContext];
    }
}
