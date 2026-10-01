<?php

namespace App\Services\Google;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Verifies the OIDC token Google attaches to a Pub/Sub push.
 *
 * Uses firebase/php-jwt and OpenSSL rather than google/auth's AccessToken:
 * that helper needs phpseclib v3, and Socialite already pulls phpseclib v4.
 *
 * The push subscription is configured to sign with a service account and an
 * audience of our webhook URL, so a request is only genuine when Google signed
 * it, it was minted for us, and it came from that service account.
 */
class PubSubTokenVerifier
{
    private const string CERTS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    /** @var list<string> */
    private const array ISSUERS = [
        'accounts.google.com',
        'https://accounts.google.com',
    ];

    public function verify(?string $bearer): bool
    {
        $audience = config('services.google.pubsub_audience');
        $serviceAccount = config('services.google.pubsub_service_account');

        if ($bearer === null || $bearer === '' || ! is_string($audience) || $audience === '' || ! is_string($serviceAccount) || $serviceAccount === '') {
            Log::warning('Gmail push rejected: missing bearer token or Pub/Sub config.');

            return false;
        }

        if (substr_count($bearer, '.') !== 2) {
            Log::warning('Gmail push token rejected.', ['error' => 'malformed jwt']);

            return false;
        }

        try {
            $payload = JWT::decode($bearer, JWK::parseKeySet($this->certs()));
        } catch (Throwable $e) {
            Log::warning('Gmail push token rejected.', ['error' => $e->getMessage()]);

            return false;
        }

        $issuer = $payload->iss ?? null;
        $email = $payload->email ?? null;
        $aud = $payload->aud ?? null;
        $audienceMatches = $aud === $audience
            || (is_array($aud) && in_array($audience, $aud, true));

        if (! is_string($issuer) || ! in_array($issuer, self::ISSUERS, true)
            || ! $audienceMatches
            || $email !== $serviceAccount
            || filter_var($payload->email_verified ?? false, FILTER_VALIDATE_BOOLEAN) !== true) {
            Log::warning('Gmail push token rejected.', [
                'error' => 'unexpected identity',
                'email' => is_string($email) ? $email : null,
            ]);

            return false;
        }

        return true;
    }

    /**
     * @return array{keys: list<array<string, mixed>>}
     */
    private function certs(): array
    {
        /** @var mixed $cached */
        $cached = Cache::remember('google-oidc-certs', 3600, function (): array {
            $response = Http::acceptJson()->timeout(10)->get(self::CERTS_URL);
            $response->throw();

            $body = $response->json();

            if (! is_array($body) || ! isset($body['keys']) || ! is_array($body['keys'])) {
                throw new \RuntimeException('Google certs response was invalid.');
            }

            return $body;
        });

        if (! is_array($cached) || ! isset($cached['keys']) || ! is_array($cached['keys'])) {
            Cache::forget('google-oidc-certs');

            throw new \RuntimeException('Google certs cache was invalid.');
        }

        /** @var array{keys: list<array<string, mixed>>} $cached */
        return $cached;
    }
}
