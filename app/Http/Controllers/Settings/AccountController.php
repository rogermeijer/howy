<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Locale;
use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AccountUpdateRequest;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /**
     * Show the settings for the account the user is currently working in.
     */
    public function edit(Request $request): Response
    {
        $account = Tenancy::account();

        return Inertia::render('settings', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'locale' => $account->locale->value,
                'timezone' => $account->timezone,
            ],
            'canManageAccount' => $request->user()->isAdminOf($account),
            'locales' => array_map(
                fn (Locale $locale): array => ['value' => $locale->value, 'label' => $locale->label()],
                Locale::cases(),
            ),
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    /**
     * Update the current account's name, language and timezone.
     */
    public function update(AccountUpdateRequest $request): RedirectResponse
    {
        Tenancy::account()->fill($request->validated())->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account updated.')]);

        return to_route('settings');
    }
}
