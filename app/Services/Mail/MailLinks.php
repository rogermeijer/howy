<?php

namespace App\Services\Mail;

use App\Enums\MailLinkPurpose;
use App\Facades\Tenancy;
use App\Mail\Content\MailUrl;
use JsonException;

/**
 * Links in Howy's mails that open a page without logging in: rating an
 * answer, looking over what a mail added, unsubscribing.
 *
 * The token is the link's whole content, encrypted and authenticated
 * (AES-256-GCM, with a key derived from the app key): the account, what it
 * opens, whom it went to, the mail's language and until when it works. Only
 * this app can make or read one, and a changed byte does not decrypt, so the
 * account in it can be trusted where an account id from a form never can (see
 * "Mail links" in docs/multi-tenancy.md). The record is still read through
 * the account's scope: one that is not the account's is not found. Short
 * enough for a link in a mail, at about 140 characters.
 */
class MailLinks
{
    private const string CIPHER = 'aes-256-gcm';

    private const int NONCE_BYTES = 12;

    private const int TAG_BYTES = 16;

    /**
     * A link for the current account.
     */
    public function url(MailLinkPurpose $purpose, int $recordId, string $recipient, string $locale): string
    {
        return MailUrl::route('mail-links.'.$purpose->value.'.show', $this->token($purpose, $recordId, $recipient, $locale));
    }

    public function token(MailLinkPurpose $purpose, int $recordId, string $recipient, string $locale, ?int $accountId = null): string
    {
        $payload = json_encode([
            'a' => $accountId ?? Tenancy::id(),
            'p' => $purpose->value,
            'r' => $recordId,
            't' => strtolower($recipient),
            'l' => $locale,
            'e' => $purpose->expires() ? now()->addHours((int) config('adoption.mail_link_hours'))->getTimestamp() : null,
        ], JSON_THROW_ON_ERROR);

        $nonce = random_bytes(self::NONCE_BYTES);
        $sealed = openssl_encrypt($payload, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $nonce, $tag);

        return rtrim(strtr(base64_encode($nonce.$tag.$sealed), '+/', '-_'), '=');
    }

    /**
     * A key of its own, derived from the app key: what it seals is no use to
     * anything else that uses that key.
     */
    private function key(): string
    {
        return hash_hkdf('sha256', app('encrypter')->getKey(), 32, 'howy-mail-links');
    }

    /**
     * What a token stands for; null for one this app did not issue.
     */
    public function decode(string $token): ?MailLink
    {
        $raw = base64_decode(strtr($token, '-_', '+/'), true);

        if ($raw === false || strlen($raw) <= self::NONCE_BYTES + self::TAG_BYTES) {
            return null;
        }

        $opened = openssl_decrypt(
            substr($raw, self::NONCE_BYTES + self::TAG_BYTES),
            self::CIPHER,
            $this->key(),
            OPENSSL_RAW_DATA,
            substr($raw, 0, self::NONCE_BYTES),
            substr($raw, self::NONCE_BYTES, self::TAG_BYTES),
        );

        try {
            $payload = $opened === false ? null : json_decode($opened, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        $purpose = is_array($payload) ? MailLinkPurpose::tryFrom((string) ($payload['p'] ?? '')) : null;

        if ($purpose === null || ! is_int($payload['a'] ?? null) || ! is_int($payload['r'] ?? null) || ! is_string($payload['t'] ?? null) || ! is_string($payload['l'] ?? null)) {
            return null;
        }

        return new MailLink(
            $payload['a'],
            $purpose,
            $payload['r'],
            $payload['t'],
            $payload['l'],
            is_int($payload['e'] ?? null) ? $payload['e'] : null,
        );
    }
}
