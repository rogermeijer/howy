<?php

namespace App\Http\Controllers\Settings;

use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\GoLiveRequest;
use App\Models\Colleague;
use App\Services\Adoption\Welcomes;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * The account's start signal. Live, Howy sends its own mails to colleagues,
 * starting with the welcome to those the admin kept on the list; paused, it
 * sends none of them. Answers to questions go out either way.
 */
class LiveController extends Controller
{
    public function store(GoLiveRequest $request, Welcomes $welcomes): RedirectResponse
    {
        $account = Tenancy::account();

        if (! $account->isLive()) {
            $account->forceFill(['live_at' => now()])->save();
        }

        $welcomed = Colleague::query()
            ->with('homeMailbox')
            ->whereKey($request->colleagueIds())
            ->get()
            ->filter(fn (Colleague $colleague): bool => $welcomes->send($colleague))
            ->count();

        Inertia::flash('toast', ['type' => 'success', 'message' => match ($welcomed) {
            0 => __('Howy is live.'),
            1 => __('Howy is live. 1 welcome mail is on its way.'),
            default => __('Howy is live. :count welcome mails are on their way.', ['count' => $welcomed]),
        }]);

        return back();
    }

    public function destroy(): RedirectResponse
    {
        Tenancy::account()->forceFill(['live_at' => null])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Howy is paused: its own mails wait until it is live again. Answers still go out.')]);

        return back();
    }
}
