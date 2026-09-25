<?php

namespace App\Http\Middleware;

use App\Facades\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses routes that need account data when no account is current.
 *
 * Deliberately not applied to the settings routes: a user who somehow ends up
 * without any membership still needs their profile, their password and the logout
 * button. Registration always creates an account, so reaching this is an invariant
 * violation and should be loud.
 */
class RequireTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Tenancy::check(), 403, __('No account linked.'));

        return $next($request);
    }
}
