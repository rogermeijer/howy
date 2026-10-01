<?php

namespace App\Jobs;

use App\Exceptions\MailboxNeedsReauthException;
use App\Models\Mailbox;
use App\Services\Gmail\GmailClient;
use App\Services\Gmail\MailboxImporter;
use App\Tenancy\Concerns\InteractsWithTenancy;
use App\Tenancy\Contracts\TenantAware;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Imports everything matching a Gmail search, up to a limit. Used for the
 * "also the last 30 days" choice when a mailbox is connected.
 */
class ImportGmailQuery implements ShouldQueue, TenantAware
{
    use InteractsWithTenancy, Queueable;

    public function __construct(
        public int $mailboxId,
        public string $query,
        public ?int $userId = null,
        public int $limit = 500,
    ) {
        $this->rememberTenant();
    }

    public function handle(MailboxImporter $importer): void
    {
        $mailbox = Mailbox::query()->find($this->mailboxId);

        if ($mailbox === null || ! $mailbox->isActive()) {
            return;
        }

        $client = GmailClient::for($mailbox);
        $ids = [];
        $pageToken = null;

        try {
            do {
                $page = $client->listMessages($this->query, $pageToken, 100);

                foreach ($page['messages'] ?? [] as $message) {
                    $ids[] = $message['id'];
                }

                $pageToken = $page['nextPageToken'] ?? null;
            } while ($pageToken !== null && count($ids) < $this->limit);
        } catch (MailboxNeedsReauthException) {
            return;
        }

        $importer->import($mailbox, array_slice($ids, 0, $this->limit), $this->userId);
    }
}
