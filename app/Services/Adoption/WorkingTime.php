<?php

namespace App\Services\Adoption;

use App\Models\Account;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * When Howy's own mails may go: on working days (Monday to Friday; public
 * holidays are not counted yet), between config('adoption.send_from_hour')
 * and send_until_hour, in the account's timezone. And how far apart they are,
 * counted in working days.
 */
class WorkingTime
{
    /**
     * The same local time, so many working days later: welcomed on Friday at
     * ten, two working days on is Tuesday at ten.
     */
    public function addWorkingDays(CarbonInterface $from, int $days, Account $account): Carbon
    {
        return Carbon::instance($from)->setTimezone($account->timezone)->addWeekdays($days);
    }

    public function isSendingTime(Account $account, ?CarbonInterface $at = null): bool
    {
        $local = Carbon::instance($at ?? now())->setTimezone($account->timezone);

        return $local->isWeekday()
            && $local->hour >= (int) config('adoption.send_from_hour')
            && $local->hour < (int) config('adoption.send_until_hour');
    }
}
