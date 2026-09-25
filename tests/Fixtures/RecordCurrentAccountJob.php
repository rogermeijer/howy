<?php

namespace Tests\Fixtures;

use App\Facades\Tenancy;
use App\Tenancy\Concerns\InteractsWithTenancy;
use App\Tenancy\Contracts\TenantAware;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * A job that records which account was current while it ran.
 *
 * Exists to prove that InteractsWithTenancy actually restores context on the
 * worker, where nothing sets it up.
 */
class RecordCurrentAccountJob implements ShouldQueue, TenantAware
{
    use InteractsWithTenancy, Queueable;

    public static ?int $seenAccountId = null;

    public function __construct()
    {
        $this->rememberTenant();
    }

    public function handle(): void
    {
        self::$seenAccountId = Tenancy::id();
    }
}
