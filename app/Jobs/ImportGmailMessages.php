<?php

namespace App\Jobs;

use App\Enums\EmailSource;
use App\Exceptions\MailboxNeedsReauthException;
use App\Models\Mailbox;
use App\Services\Gmail\GmailClient;
use App\Services\Gmail\StoreGmailMessage;
use App\Tenancy\Concerns\InteractsWithTenancy;
use App\Tenancy\Contracts\TenantAware;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Imports one chunk of hand-picked messages. Always part of a batch, whose
 * progress the settings page shows; see MailboxImporter.
 *
 * There are deliberately no batch then/finally callbacks: those run outside any
 * tenant context. The page reads batch state instead.
 */
class ImportGmailMessages implements ShouldQueue, TenantAware
{
    use Batchable, InteractsWithTenancy, Queueable;

    /**
     * @param  list<string>  $messageIds
     */
    public function __construct(
        public int $mailboxId,
        public array $messageIds,
        public ?int $userId = null,
    ) {
        $this->rememberTenant();
    }

    public function handle(StoreGmailMessage $store): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $mailbox = Mailbox::query()->find($this->mailboxId);

        if ($mailbox === null || ! $mailbox->isActive()) {
            return;
        }

        $client = GmailClient::for($mailbox);

        try {
            foreach ($this->messageIds as $id) {
                $store->store($mailbox, $client->message($id), EmailSource::Import, $this->userId);
            }
        } catch (MailboxNeedsReauthException) {
            $this->batch()?->cancel();
        }
    }
}
