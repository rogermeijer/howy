<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decides which language and timezone the request is rendered in.
 *
 * Runs after SetTenantContext, because a signed-in user's fallback is their
 * account, and before HandleInertiaRequests, which ships the resolved values and
 * the matching catalogue to the frontend.
 *
 * Resolution, first match wins:
 *
 *   signed in   user column -> account column -> config default
 *   guest       locale cookie -> Accept-Language -> config default
 *
 * A null user column means "follow the account", which is why those columns are
 * nullable and there is no separate "inherit" sentinel.
 */
class SetLocale
{
    /**
     * The name of the cookie holding a guest's language choice.
     */
    public const string COOKIE = 'locale';

    /**
     * How long a language choice is remembered, in minutes.
     */
    public const int COOKIE_LIFETIME = 60 * 24 * 365;

    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->resolveLocale($request));

        // The timezone is deliberately NOT applied with date_default_timezone_set():
        // that would make now() local and Eloquent would then write local times into
        // UTC columns. Storage stays UTC and the frontend formats for display.
        $request->attributes->set('timezone', $this->resolveTimezone($request));

        return $next($request);
    }

    protected function resolveLocale(Request $request): string
    {
        $user = $request->user();

        if ($user !== null) {
            return $user->resolvedLocale();
        }

        $cookie = $request->cookie(self::COOKIE);

        if (is_string($cookie) && Locale::tryFrom($cookie) !== null) {
            return $cookie;
        }

        return $request->getPreferredLanguage(Locale::values())
            ?? config()->string('app.locale');
    }

    protected function resolveTimezone(Request $request): string
    {
        return $request->user()?->resolvedTimezone()
            ?? config()->string('app.timezone');
    }
}
