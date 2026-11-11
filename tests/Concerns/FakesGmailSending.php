<?php

namespace Tests\Concerns;

use App\Services\Gmail\GmailClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Gmail sending, faked: the send itself, and the lookup of the Message-ID the
 * sent message carries (HowyMailer asks for it). Without a Message-ID given,
 * Gmail keeps the one Howy set.
 */
trait FakesGmailSending
{
    protected function fakeGmailSend(string $id = 'reply-1', string $threadId = 'thread-1', ?string $messageId = null): void
    {
        Http::fake([
            GmailClient::BASE_URL.'/messages/send' => Http::response(['id' => $id, 'threadId' => $threadId]),
            GmailClient::BASE_URL.'/messages/'.$id.'*' => Http::response([
                'id' => $id,
                'payload' => ['headers' => $messageId !== null ? [['name' => 'Message-ID', 'value' => $messageId]] : []],
            ]),
        ]);
    }

    /**
     * How many messages were sent through Gmail, lookups not counted.
     */
    protected function gmailSendCount(): int
    {
        return Http::recorded(fn (Request $request): bool => $request->url() === GmailClient::BASE_URL.'/messages/send')->count();
    }
}
