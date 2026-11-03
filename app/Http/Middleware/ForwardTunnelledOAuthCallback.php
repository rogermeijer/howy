<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Google only accepts a public redirect URI, so in development the Gmail OAuth
 * callback comes in over the ngrok tunnel, while the session that started the
 * connect flow lives on APP_URL (e.g. howyknows.test). This sends that one
 * callback on to APP_URL with its query intact, where the login, the OAuth state
 * and the account checks all run as usual.
 *
 * Global, so it runs before `auth` would bounce the sessionless tunnel request to
 * a login page. The target is always APP_URL and the path is fixed, so it is no
 * open redirect; every other request over the tunnel (the Pub/Sub webhook) is
 * left alone.
 */
class ForwardTunnelledOAuthCallback
{
    public function handle(Request $request, Closure $next): Response
    {
        $tunnel = config('services.ngrok.domain');
        $appUrl = config('app.url');

        if (! is_string($tunnel) || $tunnel === '' || ! is_string($appUrl)
            || ! $request->isMethod('GET') || $request->getHost() !== $tunnel
            || parse_url($appUrl, PHP_URL_HOST) === $tunnel) {
            return $next($request);
        }

        $path = route('mailboxes.gmail.callback', absolute: false);

        if ('/'.$request->path() !== $path) {
            return $next($request);
        }

        $query = $request->getQueryString();

        return redirect()->away(rtrim($appUrl, '/').$path.($query !== null ? '?'.$query : ''));
    }
}
