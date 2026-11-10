<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Accounts\ChangeKnowledgeLanguage;
use App\Enums\Locale;
use App\Enums\MailboxStatus;
use App\Facades\Tenancy;
use App\Http\Controllers\Concerns\PresentsMailboxes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AccountUpdateRequest;
use App\Http\Requests\Settings\KnowledgeLanguageUpdateRequest;
use App\Models\Mailbox;
use App\Services\Gmail\MailboxImporter;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    use PresentsMailboxes;

    /**
     * Show the settings for the account the user is currently working in.
     */
    public function edit(Request $request, MailboxImporter $importer): Response
    {
        $account = Tenancy::account();
        $isAdmin = $request->user()->isAdminOf($account);

        $mailboxes = $this->withMailboxDetails(Mailbox::query())
            ->where('status', '!=', MailboxStatus::Disconnected)
            ->oldest()
            ->get()
            ->map(fn (Mailbox $mailbox): array => $this->presentMailbox($mailbox, $importer, $isAdmin));

        return Inertia::render('settings', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'domain' => $account->domain,
                'locale' => $account->locale->value,
                'knowledgeLocale' => $account->knowledge_locale->value,
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
            // A rule for one of these would let in everyone on it.
            'publicDomains' => config('mailboxes.public_domains'),
            'mailFacts' => $this->mailFacts(),
        ]);
    }

    /**
     * Update the current account's name, domain, language and timezone.
     */
    public function update(AccountUpdateRequest $request): RedirectResponse
    {
        Tenancy::account()->fill($request->validated())->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account updated.')]);

        return to_route('settings');
    }

    /**
     * The language the knowledge base is built in.
     */
    public function updateKnowledgeLanguage(KnowledgeLanguageUpdateRequest $request, ChangeKnowledgeLanguage $change): RedirectResponse
    {
        $account = Tenancy::account();
        $changed = $change->handle($account, $request->validated('knowledge_locale'));

        Inertia::flash('toast', ['type' => 'success', 'message' => $changed
            ? __('The knowledge base is now built in :language. Howy is translating what it already holds.', ['language' => $account->knowledge_locale->label()])
            : __('Account updated.')]);

        return to_route('settings');
    }
}
