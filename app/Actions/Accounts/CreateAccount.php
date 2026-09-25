<?php

namespace App\Actions\Accounts;

use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates an account and makes a user its administrator.
 *
 * Extracted rather than inlined because registration, the seeder and any future
 * invite flow all need the same triple — account, membership, active pointer — and
 * getting two of the three right is a silent bug that only shows up as a 403 later.
 */
class CreateAccount
{
    public function handle(User $owner, string $name, ?string $locale = null, ?string $timezone = null): Account
    {
        return DB::transaction(function () use ($owner, $name, $locale, $timezone): Account {
            $account = Account::create(array_filter([
                'name' => $name,
                'locale' => $locale,
                'timezone' => $timezone,
            ], fn ($value) => $value !== null));

            // role stays null on purpose: is_admin already grants everything, and
            // writing a role nothing reads would lock the enum's cases in before
            // there is a consumer to validate them against.
            $account->users()->attach($owner, ['is_admin' => true]);

            $owner->active_account_id = $account->id;
            $owner->save();

            return $account;
        });
    }
}
