<?php

namespace App\Actions\Fortify;

use App\Actions\Accounts\CreateAccount;
use App\Concerns\AccountValidationRules;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
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

        $code = session(Voucher::SESSION_KEY);

        $user = DB::transaction(function () use ($input, $code): User {
            // Private beta: the code is checked again under a row lock, so two
            // people racing for the last use of a code cannot both get in.
            $voucher = is_string($code)
                ? Voucher::query()->where('code', $code)->lockForUpdate()->first()
                : null;

            if ($voucher === null || ! $voucher->isRedeemable()) {
                throw ValidationException::withMessages([
                    'voucher' => __('Your invite code is no longer valid. Enter another one to continue.'),
                ]);
            }

            $voucher->increment('uses');

            $user = new User([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);
            $user->voucher()->associate($voucher);
            $user->save();

            // The account inherits whatever locale the registration page was being
            // read in, so someone who switched to English lands in an English account.
            app(CreateAccount::class)->handle($user, $input['account_name'], app()->getLocale());

            return $user->refresh();
        });

        session()->forget(Voucher::SESSION_KEY);

        return $user;
    }
}
