<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Locale;
use App\Enums\MailboxStatus;
use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AccountUpdateRequest;
use App\Models\Mailbox;
use App\Services\Gmail\MailboxImporter;
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
    public function edit(Request $request, MailboxImporter $importer): Response
    {
        $account = Tenancy::account();
        $isAdmin = $request->user()->isAdminOf($account);

        $mailboxes = Mailbox::query()
            ->where('status', '!=', MailboxStatus::Disconnected)
            ->withCount('emails')
            ->oldest()
            ->get()
            ->map(fn (Mailbox $mailbox): array => [
                'id' => $mailbox->id,
                'emailAddress' => $mailbox->email_address,
                'provider' => $mailbox->provider->value,
                'status' => $mailbox->status->value,
                'lastMessageAt' => $mailbox->last_message_at?->toIso8601String(),
                'emailsCount' => $mailbox->emails_count,
                'import' => $importer->progress($mailbox),
            ]);

        return Inertia::render('settings', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'locale' => $account->locale->value,
                'timezone' => $account->timezone,
            ],
            'canManageAccount' => $isAdmin,
            'canManageMailboxes' => $isAdmin,
            'mailboxes' => $mailboxes,
            // ?connect=mailbox opens the connect dialog, so the inbox banner can
            // link straight to it.
            'openConnect' => $isAdmin && $request->query('connect') === 'mailbox',
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
