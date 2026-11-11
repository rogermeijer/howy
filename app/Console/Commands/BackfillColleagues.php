<?php

namespace App\Console\Commands;

use App\Enums\EmailSource;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Email;
use App\Models\Mailbox;
use App\Services\Colleagues\Colleagues;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * One-off, after colleagues came in: the mail stored before makes colleagues
 * the way new mail does, and is linked to who sent it. Oldest first, so the
 * first mailbox someone wrote to is their home. Safe to run again: it only
 * reads mail not linked to anyone yet.
 */
#[Signature('colleagues:backfill')]
#[Description('Make colleagues from the mail stored so far and link it to them')]
class BackfillColleagues extends Command
{
    public function handle(Colleagues $colleagues): int
    {
        $linked = 0;

        Account::query()->each(function (Account $account) use ($colleagues, &$linked): void {
            Tenancy::for($account, function () use ($colleagues, &$linked): void {
                $mailboxes = Mailbox::query()->with('rules.colleague')->get()->keyBy('id');

                // Live mail first, which makes colleagues; then the rest, which
                // an import only links to someone already one.
                foreach ([true, false] as $live) {
                    Email::query()
                        ->whereNull('colleague_id')
                        ->whereNotNull('mailbox_id')
                        ->when($live, fn ($query) => $query->where('source', '!=', EmailSource::Import))
                        ->orderBy('received_at')
                        ->orderBy('id')
                        ->pluck('id')
                        ->chunk(500)
                        ->each(function ($ids) use ($colleagues, $mailboxes, &$linked): void {
                            $emails = Email::query()->whereKey($ids->all())->get()->keyBy('id');

                            foreach ($ids as $id) {
                                $email = $emails->get($id);
                                $mailbox = $email !== null ? $mailboxes->get($email->mailbox_id) : null;

                                if ($email !== null && $mailbox !== null && $colleagues->record($email, $mailbox) !== null) {
                                    $linked++;
                                }
                            }
                        });
                }
            });
        });

        $this->components->info("Linked {$linked} emails to colleagues.");

        return self::SUCCESS;
    }
}
