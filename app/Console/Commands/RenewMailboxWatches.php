<?php

namespace App\Console\Commands;

use App\Enums\MailboxStatus;
use App\Facades\Tenancy;
use App\Jobs\StartGmailWatch;
use App\Models\Account;
use App\Models\Mailbox;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * A Gmail watch lapses after about seven days. Renewing daily, two days ahead,
 * leaves room for a failed run without ever losing push.
 */
#[Signature('mailboxes:renew-watches')]
#[Description('Renew Gmail push watches that are about to expire')]
class RenewMailboxWatches extends Command
{
    public function handle(): int
    {
        $renewed = 0;

        Account::query()->each(function (Account $account) use (&$renewed): void {
            Tenancy::for($account, function () use (&$renewed): void {
                Mailbox::query()
                    ->where('status', MailboxStatus::Active)
                    ->where(fn ($query) => $query
                        ->whereNull('watch_expires_at')
                        ->orWhere('watch_expires_at', '<', now()->addDays(2)))
                    ->pluck('id')
                    ->each(function (int $id) use (&$renewed): void {
                        StartGmailWatch::dispatch($id);
                        $renewed++;
                    });
            });
        });

        $this->components->info("Renewing {$renewed} mailbox watches.");

        return self::SUCCESS;
    }
}
