<?php

namespace App\Jobs;

use App\Enums\EmailSource;
use App\Exceptions\MailboxNeedsReauthException;
use App\Models\Mailbox;
use App\Services\Gmail\GmailClient;
use App\Services\Gmail\StoreGmailMessage;
use App\Tenancy\Concerns\InteractsWithTenancy;
use App\Tenancy\Contracts\TenantAware;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Pulls every INBOX message added since the mailbox's last history id.
 *
 * A Gmail push only says "something changed up to history X", so the push and
 * the scheduled poll both land here. Runs one at a time per mailbox, so a burst
 * of pushes cannot store the same messages in parallel.
 */
class SyncGmailMailbox implements ShouldQueue, TenantAware
{
    use InteractsWithTenancy {
        middleware as tenancyMiddleware;
    }
    use Queueable;

    /** A safety stop, so a runaway history can never page forever. */
    private const int MAX_PAGES = 20;

    public function __construct(
        public int $mailboxId,
        public EmailSource $source = EmailSource::Poll,
    ) {
        $this->rememberTenant();
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            ...$this->tenancyMiddleware(),
            (new WithoutOverlapping("gmail-sync:{$this->mailboxId}"))->releaseAfter(30)->expireAfter(300),
        ];
    }

    public function handle(StoreGmailMessage $store): void
    {
        $mailbox = Mailbox::query()->find($this->mailboxId);

        if ($mailbox === null || ! $mailbox->isActive()) {
            return;
        }

        $client = GmailClient::for($mailbox);

        try {
            if ($mailbox->history_id === null) {
                // Nothing to diff against yet: start from now.
                $mailbox->forceFill(['history_id' => $client->profile()['historyId']])->save();

                return;
            }

            [$ids, $latestHistoryId] = $this->changedMessageIds($client, $mailbox->history_id);

            $new = array_values(array_diff($ids, $store->existing($mailbox, $ids)));

            foreach ($new as $id) {
                $store->store($mailbox, $client->message($id), $this->source);
            }
        } catch (MailboxNeedsReauthException) {
            return;
        }

        $mailbox->forceFill([
            'history_id' => $latestHistoryId,
            'last_synced_at' => now(),
            'last_error' => null,
        ])->save();
    }

    /**
     * @return array{0: list<string>, 1: string}
     */
    private function changedMessageIds(GmailClient $client, string $startHistoryId): array
    {
        $ids = [];
        $pageToken = null;
        $latest = $startHistoryId;

        try {
            for ($page = 0; $page < self::MAX_PAGES; $page++) {
                $response = $client->history($startHistoryId, $pageToken);
                $latest = $response['historyId'];

                foreach ($response['history'] ?? [] as $entry) {
                    /** @var list<array{message: array{id: string, labelIds?: list<string>}}> $added */
                    $added = $entry['messagesAdded'] ?? [];

                    foreach ($added as $item) {
                        if (in_array('INBOX', $item['message']['labelIds'] ?? ['INBOX'], true)) {
                            $ids[] = $item['message']['id'];
                        }
                    }
                }

                $pageToken = $response['nextPageToken'] ?? null;

                if ($pageToken === null) {
                    break;
                }
            }
        } catch (RequestException $e) {
            if ($e->response->status() !== 404) {
                throw $e;
            }

            // Gmail only keeps about a week of history. When our id has aged out,
            // catch up on the last day and restart the diff from now.
            $recent = $client->listMessages('in:inbox newer_than:1d', null, 100);
            $ids = array_map(fn (array $m): string => $m['id'], $recent['messages'] ?? []);
            $latest = $client->profile()['historyId'];
        }

        return [array_values(array_unique($ids)), $latest];
    }
}
