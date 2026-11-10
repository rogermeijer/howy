<?php

namespace App\Http\Middleware;

use App\Facades\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends the administrators of an account that is not set up yet to the setup
 * wizard first. Members who are not administrators cannot set anything up, so
 * they go where they were going.
 */
class RequireSetUpAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $account = Tenancy::account();

        if (! $account->isSetUp() && $request->user()?->isAdminOf($account)) {
            return to_route('setup.start');
        }

        return $next($request);
    }
}
