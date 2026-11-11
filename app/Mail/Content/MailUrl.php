<?php

namespace App\Mail\Content;

use DateInterval;
use DateTimeInterface;
use Illuminate\Routing\UrlGenerator;

/**
 * Links in a mail, on an address its recipient can reach: APP_PUBLIC_URL when
 * it names a host, else APP_URL. Locally APP_URL is a .test host nobody else
 * can open, and a spam filter silently drops a mail that links to a domain
 * that does not exist, the more so one that looks like the recipient's own
 * (howyknows.test beside howyknows.com).
 *
 * Built by a copy of the app's URL generator, so the rest of the request, or
 * the next job on a worker, keeps its own root. A signed link is signed for
 * the public address, which is where it is opened.
 */
final class MailUrl
{
    /**
     * @param  mixed  $parameters
     */
    public static function route(string $name, $parameters = []): string
    {
        return self::generator()->route($name, $parameters);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function temporarySignedRoute(string $name, DateTimeInterface|DateInterval|int $expiration, array $parameters = []): string
    {
        return self::generator()->temporarySignedRoute($name, $expiration, $parameters);
    }

    /**
     * APP_PUBLIC_URL, when it names a host.
     */
    public static function root(): ?string
    {
        $public = config('app.public_url');
        $host = is_string($public) ? parse_url($public, PHP_URL_HOST) : null;

        return is_string($public) && is_string($host) && $host !== '' ? rtrim($public, '/') : null;
    }

    private static function generator(): UrlGenerator
    {
        $url = app(UrlGenerator::class);
        $root = self::root();

        if ($root === null) {
            return $url;
        }

        // A fresh request on the copy drops the route generator it shares
        // with the original, which would otherwise build on the old root.
        $generator = clone $url;
        $generator->setRequest($url->getRequest());
        $generator->forceRootUrl($root);
        $generator->forceScheme((string) parse_url($root, PHP_URL_SCHEME));

        return $generator;
    }
}
