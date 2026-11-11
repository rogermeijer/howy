<?php

namespace App\Console\Commands;

use App\Enums\MailboxStatus;
use App\Enums\OutboundMailKind;
use App\Enums\OutboundMailStatus;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\OutboundMail;
use App\Services\Adoption\WorkingTime;
use App\Services\Mail\HowyMailer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Hourly: sends what waited for its mailbox, now that the mailbox is
 * connected again. A reply goes at once; one of Howy's own mails only in
 * working time (WorkingTime), so a mailbox fixed on Saturday night does not
 * greet anyone then.
 */
#[Signature('adoption:send')]
#[Description('Send the mails Howy held for a mailbox that is connected again')]
class SendAdoptionMails extends Command
{
    public function handle(HowyMailer $mailer, WorkingTime $time): int
    {
        $sent = 0;
        $flow = array_values(array_filter(OutboundMailKind::cases(), fn (OutboundMailKind $kind): bool => $kind->isFlow()));

        Account::query()->each(function (Account $account) use ($mailer, $time, $flow, &$sent): void {
            Tenancy::for($account, function () use ($account, $mailer, $time, $flow, &$sent): void {
                OutboundMail::query()
                    ->where('status', OutboundMailStatus::Pending)
                    ->whereHas('mailbox', fn ($query) => $query->where('status', MailboxStatus::Active))
                    ->when(! $time->isSendingTime($account), fn ($query) => $query->whereNotIn('kind', $flow))
                    ->oldest('id')
                    ->each(function (OutboundMail $mail) use ($mailer, &$sent): void {
                        if ($mailer->deliver($mail)->status === OutboundMailStatus::Sent) {
                            $sent++;
                        }
                    });
            });
        });

        $this->components->info("Sent {$sent} mails that waited for their mailbox.");

        return self::SUCCESS;
    }
}
