<?php

namespace App\Services\Adoption;

use App\Enums\OutboundMailKind;
use App\Models\Account;
use App\Models\Colleague;
use App\Models\Mailbox;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * What follows the welcome, counted in working days from the moment it went:
 *
 * - how it works (02), after config('adoption.how_it_works_after_workdays');
 * - the nudge (06), after config('adoption.nudge_after_workdays'), only for
 *   someone Howy learns from who has not mailed it since the welcome.
 *
 * Each goes once, only while the account is live, to someone who still gets
 * Howy mails, and one a day at most: after a pause the rest follows on the
 * next working days rather than all at once.
 */
class FollowUps
{
    public function __construct(
        private readonly FlowSender $sender,
        private readonly Welcomes $welcomes,
        private readonly WorkingTime $time,
    ) {}

    /**
     * Write and send whatever is due now; how many went.
     */
    public function sendDue(Account $account, ?CarbonInterface $now = null): int
    {
        $now ??= now();

        if (! $account->isLive() || ! $this->time->isSendingTime($account, $now)) {
            return 0;
        }

        $mailboxes = Mailbox::query()->with('rules.colleague')->get()->keyBy('id');
        $sent = 0;

        Colleague::query()
            ->whereNotNull('welcomed_at')
            ->where(fn ($query) => $query->whereNull('explained_at')->orWhereNull('nudged_at'))
            ->where('receives_mail', true)
            ->whereNull('opted_out_at')
            ->orderBy('id')
            ->each(function (Colleague $colleague) use ($account, $now, $mailboxes, &$sent): void {
                $home = $mailboxes->get((int) $colleague->home_mailbox_id);
                $due = $home !== null && $this->welcomes->mayReceive($colleague, $home)
                    ? $this->dueFor($colleague, $home, $account, $now)
                    : [];

                if ($due !== [] && ! $this->hadOneToday($colleague, $account, $now) && $this->sender->send($colleague, $due[0])) {
                    $sent++;
                }
            });

        return $sent;
    }

    /**
     * When each follow-up still to come is due; null for one that will not
     * come, as things stand.
     *
     * @return array{how_it_works: Carbon|null, nudge: Carbon|null}
     */
    public function plannedFor(Colleague $colleague, ?Mailbox $home, Account $account): array
    {
        $welcomed = $colleague->welcomed_at;

        if ($welcomed === null || $home === null || ! $this->welcomes->mayReceive($colleague, $home)) {
            return ['how_it_works' => null, 'nudge' => null];
        }

        return [
            'how_it_works' => $colleague->explained_at === null
                ? $this->time->addWorkingDays($welcomed, (int) config('adoption.how_it_works_after_workdays'), $account)
                : null,
            'nudge' => $colleague->nudged_at === null && $this->silentSinceWelcome($colleague) && $home->access($colleague->email)->learns()
                ? $this->time->addWorkingDays($welcomed, (int) config('adoption.nudge_after_workdays'), $account)
                : null,
        ];
    }

    /**
     * The follow-ups due for them now, how it works first.
     *
     * @return list<OutboundMailKind>
     */
    private function dueFor(Colleague $colleague, Mailbox $home, Account $account, CarbonInterface $now): array
    {
        $planned = $this->plannedFor($colleague, $home, $account);
        $due = [];

        if ($planned['how_it_works'] !== null && $planned['how_it_works']->lte($now)) {
            $due[] = OutboundMailKind::HowItWorks;
        }

        if ($planned['nudge'] !== null && $planned['nudge']->lte($now)) {
            $due[] = OutboundMailKind::Nudge;
        }

        return $due;
    }

    /**
     * Nothing came in from them after the welcome: the mail that brought it
     * came before it.
     */
    private function silentSinceWelcome(Colleague $colleague): bool
    {
        return $colleague->last_mail_at === null || $colleague->last_mail_at->lte($colleague->welcomed_at);
    }

    /**
     * Whether one of Howy's own mails to them was written today, in the
     * account's timezone.
     */
    private function hadOneToday(Colleague $colleague, Account $account, CarbonInterface $now): bool
    {
        $flow = array_values(array_filter(OutboundMailKind::cases(), fn (OutboundMailKind $kind): bool => $kind->isFlow()));

        return $colleague->outboundMails()
            ->whereIn('kind', $flow)
            ->where('created_at', '>=', Carbon::instance($now)->setTimezone($account->timezone)->startOfDay()->utc())
            ->exists();
    }
}
