<?php

namespace App\Tenancy\Middleware;

use App\Facades\Tenancy;
use App\Tenancy\Contracts\TenantAware;
use Closure;

/**
 * Job middleware that restores the account a job was dispatched from.
 *
 * Applied automatically by the InteractsWithTenancy trait.
 */
class RunInTenantContext
{
    public function handle(TenantAware $job, Closure $next): mixed
    {
        return Tenancy::for($job->tenantAccountId(), fn () => $next($job));
    }
}
