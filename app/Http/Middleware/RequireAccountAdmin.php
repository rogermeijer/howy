<?php

namespace App\Http\Middleware;

use App\Facades\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Limits a route to administrators of the current account.
 *
 * Uses isAdminOf() rather than the inert Role/Permission enums, matching how the
 * account settings form is already gated.
 */
class RequireAccountAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isAdminOf(Tenancy::account()), 403);

        return $next($request);
    }
}
