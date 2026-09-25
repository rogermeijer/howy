<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when tenant-scoped data is touched with no current account.
 *
 * This is a programming error, not a user error, so it is deliberately not mapped
 * to an HTTP status: a 500 in development is the point. Reaching this means code
 * ran outside a request without establishing a context — see the queue and console
 * recipes in docs/multi-tenancy.md.
 */
class TenantContextMissingException extends RuntimeException
{
    public static function forQuery(): self
    {
        return new self(
            'No current account. Tenant-scoped models cannot be queried outside a request '
            .'unless the work is wrapped in Tenancy::for($account, ...).',
        );
    }

    /**
     * @param  class-string  $model
     */
    public static function forCreate(string $model): self
    {
        return new self(
            "Cannot create [{$model}] without a current account. Wrap the work in "
            .'Tenancy::for($account, ...) or set account_id explicitly inside Tenancy::withoutTenancy().',
        );
    }
}
