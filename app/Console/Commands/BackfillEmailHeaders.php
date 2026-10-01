<?php

namespace App\Console\Commands;

use App\Enums\MailboxStatus;
use App\Exceptions\MailboxNeedsReauthException;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Email;
use App\Models\Mailbox;
use App\Services\Gmail\GmailClient;
use App\Services\Gmail\GmailMessageParser;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;

/**
 * One-off: emails stored before the headers column existed get their full
 * headers fetched from Gmail. Safe to run again; it only touches rows without.
 */
#[Signature('emails:backfill-headers')]
#[Description('Fetch the full headers for stored emails that do not have them yet')]
class BackfillEmailHeaders extends Command
{
    public function handle(GmailMessageParser $parser): int
    {
        $filled = 0;
        $skipped = 0;

        Account::query()->each(function (Account $account) use ($parser, &$filled, &$skipped): void {
            Tenancy::for($account, function () use ($parser, &$filled, &$skipped): void {
                Mailbox::query()->where('status', MailboxStatus::Active)->each(
                    function (Mailbox $mailbox) use ($parser, &$filled, &$skipped): void {
                        $client = GmailClient::for($mailbox);

                        try {
                            $mailbox->emails()->whereNull('headers')->each(
                                function (Email $email) use ($client, $parser, &$filled, &$skipped): void {
                                    try {
                                        $parsed = $parser->parse($client->message($email->provider_message_id));
                                    } catch (RequestException) {
                                        // Deleted from Gmail since; nothing to fetch.
                                        $skipped++;

                                        return;
                                    }

                                    $email->forceFill(['headers' => $parsed['headers']])->save();
                                    $filled++;
                                },
                            );
                        } catch (MailboxNeedsReauthException) {
                            $this->components->warn("{$mailbox->email_address} needs reconnecting; skipped.");
                        }
                    },
                );
            });
        });

        $this->components->info("Stored headers for {$filled} emails, skipped {$skipped}.");

        return self::SUCCESS;
    }
}
