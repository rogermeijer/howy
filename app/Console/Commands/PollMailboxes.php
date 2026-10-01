<?php

namespace App\Console\Commands;

use App\Enums\EmailSource;
use App\Enums\MailboxStatus;
use App\Facades\Tenancy;
use App\Jobs\SyncGmailMailbox;
use App\Models\Account;
use App\Models\Mailbox;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The safety net under Gmail push: catches anything a missed or delayed push
 * left behind, and is the only live path in development, where Google cannot
 * reach localhost.
 */
#[Signature('mailboxes:poll')]
#[Description('Queue a sync for every active mailbox')]
class PollMailboxes extends Command
{
    public function handle(): int
    {
        $queued = 0;

        Account::query()->each(function (Account $account) use (&$queued): void {
            Tenancy::for($account, function () use (&$queued): void {
                Mailbox::query()
                    ->where('status', MailboxStatus::Active)
                    ->pluck('id')
                    ->each(function (int $id) use (&$queued): void {
                        SyncGmailMailbox::dispatch($id, EmailSource::Poll);
                        $queued++;
                    });
            });
        });

        $this->components->info("Queued {$queued} mailbox syncs.");

        return self::SUCCESS;
    }
}
