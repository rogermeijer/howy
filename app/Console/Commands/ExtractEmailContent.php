<?php

namespace App\Console\Commands;

use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Email;
use App\Services\Mail\EmailContentExtractor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Re-splits stored emails into their own content and quoted history.
 *
 * Works from the stored bodies only, no Gmail calls, so it is safe to rerun
 * whenever the extractor learns a new mail client's quote markers.
 */
#[Signature('emails:extract-content')]
#[Description('Recompute the content and quoted history of every stored email')]
class ExtractEmailContent extends Command
{
    public function handle(EmailContentExtractor $extractor): int
    {
        $count = 0;

        Account::query()->each(function (Account $account) use ($extractor, &$count): void {
            Tenancy::for($account, function () use ($extractor, &$count): void {
                Email::query()->chunkById(200, function ($emails) use ($extractor, &$count): void {
                    foreach ($emails as $email) {
                        $email->forceFill($extractor->extract($email->body_html, $email->body_text))->save();
                        $count++;
                    }
                });
            });
        });

        $this->components->info("Extracted content for {$count} emails.");

        return self::SUCCESS;
    }
}
