<?php

namespace App\Facades;

use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void set(\App\Models\Account $account)
 * @method static void forget()
 * @method static bool check()
 * @method static \App\Models\Account account()
 * @method static int id()
 * @method static int|null idOrNull()
 * @method static bool disabled()
 * @method static mixed for(\App\Models\Account|int $account, \Closure $callback)
 * @method static mixed withoutTenancy(\Closure $callback)
 *
 * @see TenantContext
 */
class Tenancy extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TenantContext::class;
    }
}
