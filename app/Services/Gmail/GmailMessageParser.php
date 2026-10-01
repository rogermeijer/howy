<?php

namespace App\Services\Gmail;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Turns a Gmail API message resource into Email attributes.
 *
 * Gmail returns headers already decoded to UTF-8, but part bodies come as
 * base64url bytes in whatever charset the sender used, so bodies are decoded
 * and normalised here. Every timestamp leaves this class in UTC.
 */
class GmailMessageParser
{
    /**
     * @param  array<string, mixed>  $message  a messages.get resource, format=full or metadata
     * @return array{
     *     provider_message_id: string,
     *     provider_thread_id: string|null,
     *     message_id_header: string|null,
     *     in_reply_to: string|null,
     *     headers: list<array{name: string, value: string}>,
     *     subject: string|null,
     *     from_name: string|null,
     *     from_email: string|null,
     *     to: list<array{name: string|null, email: string}>,
     *     cc: list<array{name: string|null, email: string}>,
     *     sent_at: Carbon|null,
     *     received_at: Carbon|null,
     *     snippet: string|null,
     *     body_text: string|null,
     *     body_html: string|null,
     *     label_ids: list<string>,
     *     attachments: list<array{filename: string, mime_type: string, size: int, attachment_id: string|null}>,
     *     has_attachments: bool,
     * }
     */
    public function parse(array $message): array
    {
        /** @var array<string, mixed> $payload */
        $payload = is_array($message['payload'] ?? null) ? $message['payload'] : [];
        $headers = $this->headers($payload);

        $from = $this->addresses($headers['from'] ?? '')[0] ?? null;

        $text = null;
        $html = null;
        $attachments = [];
        $this->walk($payload, $text, $html, $attachments);

        $received = isset($message['internalDate']) && is_numeric($message['internalDate'])
            ? Carbon::createFromTimestampMs((int) $message['internalDate'], 'UTC')
            : null;

        /** @var list<string> $labels */
        $labels = is_array($message['labelIds'] ?? null) ? array_values($message['labelIds']) : [];

        return [
            'provider_message_id' => (string) $message['id'],
            'provider_thread_id' => isset($message['threadId']) ? (string) $message['threadId'] : null,
            'message_id_header' => $headers['message-id'] ?? null,
            'in_reply_to' => $headers['in-reply-to'] ?? null,
            'headers' => $this->allHeaders($payload),
            'subject' => $headers['subject'] ?? null,
            'from_name' => $from['name'] ?? null,
            'from_email' => $from['email'] ?? null,
            'to' => $this->addresses($headers['to'] ?? ''),
            'cc' => $this->addresses($headers['cc'] ?? ''),
            'sent_at' => $this->date($headers['date'] ?? null) ?? $received,
            'received_at' => $received,
            'snippet' => isset($message['snippet']) ? html_entity_decode((string) $message['snippet'], ENT_QUOTES | ENT_HTML5) : null,
            'body_text' => $text,
            'body_html' => $html,
            'label_ids' => $labels,
            'attachments' => $attachments,
            'has_attachments' => $attachments !== [],
        ];
    }

    /**
     * Split an address header such as `"Bakker, Tom" <tom@x.nl>, a@b.nl` into
     * name/email pairs, respecting quotes and angle brackets.
     *
     * @return list<array{name: string|null, email: string}>
     */
    public function addresses(string $header): array
    {
        $parts = [];
        $current = '';
        $quoted = false;
        $angled = false;

        foreach (mb_str_split($header) as $char) {
            if ($char === '"') {
                $quoted = ! $quoted;
            } elseif ($char === '<' && ! $quoted) {
                $angled = true;
            } elseif ($char === '>' && ! $quoted) {
                $angled = false;
            } elseif ($char === ',' && ! $quoted && ! $angled) {
                $parts[] = $current;
                $current = '';

                continue;
            }

            $current .= $char;
        }

        $parts[] = $current;

        $addresses = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            if (preg_match('/^(.*)<([^>]+)>\s*$/u', $part, $m) === 1) {
                $name = trim(trim($m[1]), '"\' ');
                $addresses[] = ['name' => $name === '' ? null : $name, 'email' => mb_strtolower(trim($m[2]))];
            } elseif (str_contains($part, '@')) {
                $addresses[] = ['name' => null, 'email' => mb_strtolower(trim($part, '"\' '))];
            }
        }

        return $addresses;
    }

    /**
     * Every top-level header exactly as Gmail returned it: original order and
     * casing, repeated headers kept.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array{name: string, value: string}>
     */
    private function allHeaders(array $payload): array
    {
        /** @var list<array{name: string, value: string}> $list */
        $list = is_array($payload['headers'] ?? null) ? $payload['headers'] : [];

        return array_map(
            fn (array $header): array => ['name' => (string) $header['name'], 'value' => (string) $header['value']],
            $list,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string> lower-cased header name => first value
     */
    private function headers(array $payload): array
    {
        $headers = [];

        /** @var list<array{name: string, value: string}> $list */
        $list = is_array($payload['headers'] ?? null) ? $payload['headers'] : [];

        foreach ($list as $header) {
            $name = strtolower($header['name']);
            $headers[$name] ??= trim($header['value']);
        }

        return $headers;
    }

    /**
     * @param  array<string, mixed>  $part
     * @param  list<array{filename: string, mime_type: string, size: int, attachment_id: string|null}>  $attachments
     */
    private function walk(array $part, ?string &$text, ?string &$html, array &$attachments): void
    {
        $mime = strtolower((string) ($part['mimeType'] ?? ''));
        $filename = (string) ($part['filename'] ?? '');
        /** @var array{data?: string, size?: int, attachmentId?: string} $body */
        $body = is_array($part['body'] ?? null) ? $part['body'] : [];

        if ($filename !== '') {
            $attachments[] = [
                'filename' => $filename,
                'mime_type' => $mime,
                'size' => (int) ($body['size'] ?? 0),
                'attachment_id' => $body['attachmentId'] ?? null,
            ];
        } elseif (isset($body['data']) && ($mime === 'text/plain' || $mime === 'text/html')) {
            $decoded = $this->decode($body['data'], $this->charset($part));

            // The first part of each kind wins: later ones are usually forwarded
            // or quoted copies inside nested multiparts.
            if ($mime === 'text/plain') {
                $text ??= $decoded;
            } else {
                $html ??= $decoded;
            }
        }

        /** @var list<array<string, mixed>> $children */
        $children = is_array($part['parts'] ?? null) ? $part['parts'] : [];

        foreach ($children as $child) {
            $this->walk($child, $text, $html, $attachments);
        }
    }

    /**
     * @param  array<string, mixed>  $part
     */
    private function charset(array $part): ?string
    {
        $contentType = $this->headers($part)['content-type'] ?? '';

        return preg_match('/charset="?([\w\-]+)"?/i', $contentType, $m) === 1 ? $m[1] : null;
    }

    private function decode(string $data, ?string $charset): string
    {
        $raw = (string) base64_decode(strtr($data, '-_', '+/'), true);

        if (mb_check_encoding($raw, 'UTF-8')) {
            return $raw;
        }

        try {
            $converted = mb_convert_encoding($raw, 'UTF-8', $charset ?? 'ISO-8859-1');
        } catch (Throwable) {
            // An unknown charset name: Latin-1 maps every byte, so it never fails.
            $converted = mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
        }

        return $converted === false ? mb_scrub($raw, 'UTF-8') : $converted;
    }

    private function date(?string $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            // Drop trailing comments like "(CEST)", which the parser rejects.
            return Carbon::parse(preg_replace('/\s*\([^)]*\)\s*$/', '', $value))->utc();
        } catch (Throwable) {
            return null;
        }
    }
}
