<?php

namespace App\Events;

use App\Enums\AccountTopic;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBeUnique;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Something an open page of an account may show has changed. Only the topic
 * goes out, on the account's private channel; a page that hears it reloads
 * its own props through the usual scoped requests, so no data of an account
 * ever travels over the socket.
 *
 * Queued, so a socket server that is down never holds up what changed, and
 * sent after the change is committed. Unique per account and topic while it
 * waits in the queue: a burst of changes (a sync that stores twenty mails)
 * goes out as one, and a change after it went out sends another, so the last
 * one is never lost.
 */
class AccountChanged implements ShouldBeUnique, ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * Should a worker never pick it up, the next change may go after this.
     */
    public int $uniqueFor = 60;

    public function __construct(
        public readonly int $accountId,
        public readonly AccountTopic $topic,
    ) {}

    public function uniqueId(): string
    {
        return "{$this->accountId}:{$this->topic->value}";
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("account.{$this->accountId}");
    }

    public function broadcastAs(): string
    {
        return 'changed';
    }

    /**
     * @return array{topic: string}
     */
    public function broadcastWith(): array
    {
        return ['topic' => $this->topic->value];
    }
}
