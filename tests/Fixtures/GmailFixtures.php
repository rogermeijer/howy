<?php

namespace Tests\Fixtures;

/**
 * Gmail API resources shaped like the real responses, for Http::fake().
 */
class GmailFixtures
{
    public const string API = 'https://gmail.googleapis.com/gmail/v1/users/me';

    /**
     * A messages.get resource with format=full: multipart/alternative with a
     * plain-text and an HTML part.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function message(string $id, array $overrides = []): array
    {
        $subject = $overrides['subject'] ?? "Subject of {$id}";
        $from = $overrides['from'] ?? 'Tom Bakker <tom@haarlem.nl>';
        $threadId = $overrides['threadId'] ?? "thread-{$id}";

        return [
            'id' => $id,
            'threadId' => $threadId,
            'labelIds' => ['INBOX'],
            'snippet' => "Snippet of {$id}",
            'internalDate' => (string) ($overrides['internalDate'] ?? 1790755200000),
            'payload' => [
                'mimeType' => 'multipart/alternative',
                'headers' => [
                    ['name' => 'From', 'value' => $from],
                    ['name' => 'To', 'value' => 'support@noordkade.nl'],
                    ['name' => 'Subject', 'value' => $subject],
                    ['name' => 'Date', 'value' => 'Tue, 29 Sep 2026 10:00:00 +0200'],
                    ['name' => 'Message-ID', 'value' => "<{$id}@mail.gmail.com>"],
                ],
                'parts' => [
                    [
                        'mimeType' => 'text/plain',
                        'headers' => [['name' => 'Content-Type', 'value' => 'text/plain; charset="UTF-8"']],
                        'body' => ['size' => 11, 'data' => self::encode("Body of {$id}")],
                    ],
                    [
                        'mimeType' => 'text/html',
                        'headers' => [['name' => 'Content-Type', 'value' => 'text/html; charset="UTF-8"']],
                        'body' => ['size' => 20, 'data' => self::encode("<p>Body of {$id}</p>")],
                    ],
                ],
            ],
        ];
    }

    public static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * A Pub/Sub push body as Gmail sends it.
     *
     * @return array<string, mixed>
     */
    public static function push(string $emailAddress, string $historyId = '2000'): array
    {
        return [
            'message' => [
                'data' => base64_encode((string) json_encode(['emailAddress' => $emailAddress, 'historyId' => $historyId])),
                'messageId' => '123',
            ],
            'subscription' => 'projects/cc/subscriptions/gmail-push',
        ];
    }
}
