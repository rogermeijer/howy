<?php

namespace App\Tenancy\Contracts;

/**
 * A queued job that remembers which account dispatched it.
 *
 * Implemented by the InteractsWithTenancy trait. The contract exists so the job
 * middleware can be typed rather than reaching into an untyped object.
 */
interface TenantAware
{
    public function tenantAccountId(): int;
}
