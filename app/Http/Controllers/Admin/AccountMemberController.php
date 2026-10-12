<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MemberRequest;
use App\Models\Account;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Who belongs to an account, and who administers it. account_user is the one
 * table carrying account_id without being scoped, so it is managed directly.
 */
class AccountMemberController extends Controller
{
    public function store(MemberRequest $request, Account $account): RedirectResponse
    {
        $user = User::query()->where('email', $request->string('email'))->sole();

        if ($user->belongsToAccount($account)) {
            return back()->withErrors(['email' => __('This user is already a member of this account.')]);
        }

        // role stays null on purpose, as in CreateAccount.
        $account->users()->attach($user, ['is_admin' => $request->boolean('is_admin')]);

        if ($user->active_account_id === null) {
            $user->forceFill(['active_account_id' => $account->id])->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name added to :account.', ['name' => $user->name, 'account' => $account->name])]);

        return back();
    }

    public function update(Request $request, Account $account, User $user): RedirectResponse
    {
        abort_unless($user->belongsToAccount($account), 404);

        $validated = $request->validate(['is_admin' => ['required', 'boolean']]);

        $account->users()->updateExistingPivot($user->id, ['is_admin' => (bool) $validated['is_admin']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Membership updated.')]);

        return back();
    }

    public function destroy(Account $account, User $user): RedirectResponse
    {
        abort_unless($user->belongsToAccount($account), 404);

        $account->users()->detach($user->id);

        // SetTenantContext heals the pointer onto another membership next request.
        if ($user->active_account_id === $account->id) {
            $user->forceFill(['active_account_id' => null])->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name removed from :account.', ['name' => $user->name, 'account' => $account->name])]);

        return back();
    }
}
