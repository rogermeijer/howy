<?php

namespace App\Http\Controllers;

use App\Http\Requests\CurrentAccountUpdateRequest;
use Illuminate\Http\RedirectResponse;

class CurrentAccountController extends Controller
{
    /**
     * Switch the account the user is working in.
     */
    public function update(CurrentAccountUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->active_account_id = (int) $request->validated('account_id');
        $user->save();

        // Not back(): the page they came from may show a record belonging to the
        // previous account, which would now correctly 404 — a dead end right after
        // a successful action.
        return to_route('dashboard');
    }
}
