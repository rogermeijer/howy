<?php

namespace App\Actions\Fortify;

use App\Actions\Accounts\CreateAccount;
use App\Concerns\AccountValidationRules;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use AccountValidationRules, PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user, along with the account they own.
     *
     * The two are created together in a transaction: a user without an account
     * cannot reach any tenant route, so a half-finished registration would leave
     * someone permanently staring at a 403.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            ...$this->accountRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            // The account inherits whatever locale the registration page was being
            // read in, so someone who switched to English lands in an English account.
            app(CreateAccount::class)->handle($user, $input['account_name'], app()->getLocale());

            return $user->refresh();
        });
    }
}
