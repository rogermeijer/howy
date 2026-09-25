<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a write would put a record in an account other than the current one.
 *
 * The usual cause is an account_id arriving from user input. Deliberate
 * cross-account work goes through Tenancy::for($account, ...) instead.
 */
class CrossTenantWriteException extends RuntimeException
{
    /**
     * @param  class-string  $model
     */
    public static function forCreate(string $model, int $given, int $current): self
    {
        return new self(
            "Refusing to create [{$model}] for account [{$given}] while account [{$current}] is current. "
            .'Use Tenancy::for() if this is intentional.',
        );
    }

    /**
     * @param  class-string  $model
     */
    public static function forMove(string $model): self
    {
        return new self(
            "Refusing to move [{$model}] to a different account. A record never changes owner.",
        );
    }
}
