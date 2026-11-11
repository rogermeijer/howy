<?php

namespace App\Console\Commands;

use App\Enums\MailboxStatus;
use App\Enums\OutboundMailKind;
use App\Enums\OutboundMailStatus;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\OutboundMail;
use App\Services\Adoption\FollowUps;
use App\Services\Adoption\WorkingTime;
use App\Services\Mail\HowyMailer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Hourly: sends what waited for its mailbox, now that the mailbox is
 * connected again, and writes the follow-ups to the welcome that are due
 * (FollowUps). A reply goes at once; one of Howy's own mails only in working
 * time (WorkingTime), so a mailbox fixed on Saturday night does not greet
 * anyone then.
 */
#[Signature('adoption:send')]
#[Description('Send what waited for its mailbox, and the follow-ups to the welcome that are due')]
class SendAdoptionMails extends Command
{
    public function handle(HowyMailer $mailer, WorkingTime $time, FollowUps $followUps): int
    {
        $sent = 0;
        $written = 0;
        $flow = array_values(array_filter(OutboundMailKind::cases(), fn (OutboundMailKind $kind): bool => $kind->isFlow()));

        Account::query()->each(function (Account $account) use ($mailer, $time, $followUps, $flow, &$sent, &$written): void {
            Tenancy::for($account, function () use ($account, $mailer, $time, $followUps, $flow, &$sent, &$written): void {
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

                // After the waiting ones: what this writes, its own job delivers.
                $written += $followUps->sendDue($account);
            });
        });

        $this->components->info("Sent {$sent} mails that waited for their mailbox, and wrote {$written} follow-ups.");

        return self::SUCCESS;
    }
}
