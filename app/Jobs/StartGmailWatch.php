<?php

namespace App\Jobs;

use App\Exceptions\MailboxNeedsReauthException;
use App\Models\Mailbox;
use App\Services\Gmail\GmailClient;
use App\Tenancy\Concerns\InteractsWithTenancy;
use App\Tenancy\Contracts\TenantAware;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

/**
 * Tells Gmail to push INBOX changes for a mailbox to our Pub/Sub topic.
 *
 * Run on connect and re-run by mailboxes:renew-watches, since a watch lapses
 * after about a week. Without a topic configured (local development) it only
 * records a starting history id, so the scheduled poll has somewhere to start.
 *
 * Carries the mailbox id rather than the model: SerializesModels would restore
 * a tenant model before the tenancy middleware runs, and throw.
 */
class StartGmailWatch implements ShouldQueue, TenantAware
{
    use InteractsWithTenancy, Queueable;

    public function __construct(public int $mailboxId)
    {
        $this->rememberTenant();
    }

    public function handle(): void
    {
        $mailbox = Mailbox::query()->find($this->mailboxId);

        if ($mailbox === null || ! $mailbox->isActive()) {
            return;
        }

        $client = GmailClient::for($mailbox);
        $topic = config('services.google.pubsub_topic');

        try {
            if (is_string($topic) && $topic !== '') {
                $watch = $client->watch($topic);

                // Keep an existing history id: replacing it with the newer one
                // from the watch would skip whatever arrived in between.
                $mailbox->history_id ??= $watch['historyId'];
                $mailbox->watch_expires_at = Carbon::createFromTimestampMs((int) $watch['expiration'], 'UTC');
            } else {
                $mailbox->history_id ??= $client->profile()['historyId'];
            }
        } catch (MailboxNeedsReauthException) {
            return;
        }

        $mailbox->save();
    }
}
