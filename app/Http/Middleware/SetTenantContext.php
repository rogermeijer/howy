<?php

namespace App\Http\Middleware;

use App\Facades\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Establishes the current account for the request.
 *
 * Lives in the `web` group rather than on a route group so that a new route group
 * cannot forget it, and so it always runs before HandleInertiaRequests, which
 * shares the resolved account with the frontend.
 *
 * It never fails: guests and users without a membership pass straight through, and
 * RequireTenantContext decides separately which routes insist on a context.
 */
class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        // Resolve through the membership relation, not activeAccount(), so a
        // pointer at an account the user was removed from comes back null.
        $account = $user->active_account_id === null
            ? null
            : $user->accounts()->whereKey($user->active_account_id)->first();

        // Being removed from an account is a normal event, so heal to whatever
        // membership is left rather than locking the session out.
        $account ??= $user->accounts()->oldest('accounts.id')->first();

        if ($account === null) {
            return $next($request);
        }

        if ($user->active_account_id !== $account->id) {
            $user->active_account_id = $account->id;
            $user->save();
        }

        Tenancy::set($account);

        return $next($request);
    }
}
