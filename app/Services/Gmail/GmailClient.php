<?php

namespace App\Services\Gmail;

use App\Enums\MailboxStatus;
use App\Exceptions\MailboxNeedsReauthException;
use App\Models\Mailbox;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * A thin wrapper over the Gmail REST API for one mailbox.
 *
 * Laravel's Http client rather than google/apiclient: the surface we need is a
 * handful of endpoints, and Http::fake() keeps the tests honest and simple.
 *
 * The access token is refreshed on demand. A refused refresh flags the mailbox
 * NeedsReauth and throws, so callers never loop on a dead grant.
 */
class GmailClient
{
    public const string BASE_URL = 'https://gmail.googleapis.com/gmail/v1/users/me';

    public const string TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public const string REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    /** Headers the import list needs, fetched with format=metadata. */
    private const array LIST_HEADERS = ['From', 'Subject', 'Date'];

    public function __construct(private readonly Mailbox $mailbox) {}

    public static function for(Mailbox $mailbox): self
    {
        return new self($mailbox);
    }

    /**
     * @return array{emailAddress: string, historyId: string}
     */
    public function profile(): array
    {
        /** @var array{emailAddress: string, historyId: string} */
        return $this->get('/profile')->json();
    }

    /**
     * Ask Gmail to push INBOX changes to our Pub/Sub topic. Lasts about a week.
     *
     * @return array{historyId: string, expiration: string}
     */
    public function watch(string $topic): array
    {
        /** @var array{historyId: string, expiration: string} */
        return $this->send(fn (PendingRequest $http) => $http->post(self::BASE_URL.'/watch', [
            'topicName' => $topic,
            'labelIds' => ['INBOX'],
            'labelFilterBehavior' => 'include',
        ]))->json();
    }

    public function stop(): void
    {
        $this->send(fn (PendingRequest $http) => $http->post(self::BASE_URL.'/stop'));
    }

    /**
     * Messages added to INBOX since a history id.
     *
     * @return array{history?: list<array<string, mixed>>, nextPageToken?: string, historyId: string}
     *
     * @throws RequestException with status 404 when the start id is too old.
     */
    public function history(string $startHistoryId, ?string $pageToken = null): array
    {
        /** @var array{history?: list<array<string, mixed>>, nextPageToken?: string, historyId: string} */
        return $this->get('/history', array_filter([
            'startHistoryId' => $startHistoryId,
            'historyTypes' => 'messageAdded',
            'labelId' => 'INBOX',
            'pageToken' => $pageToken,
        ]))->json();
    }

    /**
     * @return array{messages?: list<array{id: string, threadId: string}>, nextPageToken?: string, resultSizeEstimate?: int}
     */
    public function listMessages(string $query, ?string $pageToken = null, int $max = 25): array
    {
        /** @var array{messages?: list<array{id: string, threadId: string}>, nextPageToken?: string, resultSizeEstimate?: int} */
        return $this->get('/messages', array_filter([
            'q' => $query,
            'pageToken' => $pageToken,
            'maxResults' => $max,
        ]))->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function message(string $id): array
    {
        /** @var array<string, mixed> */
        return $this->get('/messages/'.rawurlencode($id), ['format' => 'full'])->json();
    }

    /**
     * Headers and snippet for several messages at once, for the import list.
     *
     * @param  list<string>  $ids
     * @return array<string, array<string, mixed>> keyed by message id
     */
    public function messageSummaries(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $token = $this->accessToken();

        // Gmail wants metadataHeaders repeated, which Http's array encoding does not do.
        $headers = implode('&', array_map(fn (string $h): string => 'metadataHeaders='.$h, self::LIST_HEADERS));

        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn (string $id) => $pool->as($id)->withToken($token)->acceptJson()
                ->get(self::BASE_URL.'/messages/'.rawurlencode($id).'?format=metadata&'.$headers),
            $ids,
        ));

        $summaries = [];

        foreach ($ids as $id) {
            $response = $responses[$id] ?? null;

            if ($response instanceof Response && $response->successful()) {
                /** @var array<string, mixed> $json */
                $json = $response->json();
                $summaries[$id] = $json;
            }
        }

        return $summaries;
    }

    /**
     * @return list<string> the ids of every message in the thread
     */
    public function threadMessageIds(string $threadId): array
    {
        /** @var array{messages?: list<array{id: string}>} $thread */
        $thread = $this->get('/threads/'.rawurlencode($threadId), ['format' => 'minimal'])->json();

        return array_map(fn (array $message): string => $message['id'], $thread['messages'] ?? []);
    }

    /**
     * Send a raw RFC 2822 message from the mailbox. With a thread id Gmail files
     * it in that thread; the In-Reply-To and References headers in the message
     * thread it for the recipient.
     *
     * @return array{id: string, threadId: string}
     */
    public function sendMessage(string $raw, ?string $threadId = null): array
    {
        $body = array_filter([
            'raw' => rtrim(strtr(base64_encode($raw), '+/', '-_'), '='),
            'threadId' => $threadId,
        ], fn (?string $value): bool => $value !== null);

        /** @var array{id: string, threadId: string} */
        return $this->send(fn (PendingRequest $http) => $http->post(self::BASE_URL.'/messages/send', $body))->json();
    }

    /**
     * Revoke the grant at Google. Best effort: a token that is already dead is fine.
     */
    public function revoke(): void
    {
        $token = $this->mailbox->refresh_token ?? $this->mailbox->access_token;

        if ($token !== null) {
            Http::asForm()->post(self::REVOKE_URL, ['token' => $token]);
        }
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function get(string $path, array $query = []): Response
    {
        return $this->send(fn (PendingRequest $http) => $http->get(self::BASE_URL.$path, $query));
    }

    /**
     * Send a request, and on a 401 refresh the token and try once more: a token
     * can be revoked before its expiry. A refresh Google refuses flags the
     * mailbox and throws, so this never loops.
     *
     * @param  callable(PendingRequest): Response  $call
     */
    private function send(callable $call): Response
    {
        $response = $call($this->request($this->accessToken()));

        if ($response->status() === 401) {
            $response = $call($this->request($this->refresh()));
        }

        return $response->throw();
    }

    private function request(string $token): PendingRequest
    {
        return Http::withToken($token)->acceptJson()->timeout(30);
    }

    private function accessToken(): string
    {
        $expires = $this->mailbox->token_expires_at;

        if ($this->mailbox->access_token !== null && $expires !== null && $expires->isAfter(now()->addMinute())) {
            return $this->mailbox->access_token;
        }

        return $this->refresh();
    }

    private function refresh(): string
    {
        if ($this->mailbox->refresh_token === null) {
            $this->flagNeedsReauth('No refresh token stored.');
        }

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->mailbox->refresh_token,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
        ]);

        if ($response->status() === 400 || $response->status() === 401) {
            $this->flagNeedsReauth((string) $response->json('error', 'invalid_grant'));
        }

        $response->throw();

        $this->mailbox->forceFill([
            'access_token' => $response->json('access_token'),
            'token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
        ])->save();

        return (string) $this->mailbox->access_token;
    }

    private function flagNeedsReauth(string $reason): never
    {
        $this->mailbox->forceFill([
            'status' => MailboxStatus::NeedsReauth,
            'last_error' => $reason,
        ])->save();

        throw MailboxNeedsReauthException::for($this->mailbox);
    }
}
