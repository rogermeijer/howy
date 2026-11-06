<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Locale;
use App\Enums\MailboxStatus;
use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AccountUpdateRequest;
use App\Http\Requests\Settings\KnowledgeLanguageUpdateRequest;
use App\Jobs\Knowledge\TranslateKnowledge;
use App\Models\Mailbox;
use App\Models\MailboxSender;
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
            ->with(['senders' => fn ($query) => $query->oldest('id')])
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
                'domain' => $mailbox->domain(),
                'publicDomain' => $mailbox->isOnPublicDomain(),
                'domainAccess' => $mailbox->domain_access->value,
                'senderRules' => $mailbox->senders->map(fn (MailboxSender $sender): array => [
                    'entry' => $sender->entry,
                    'access' => $sender->access->value,
                ])->all(),
                'importOffered' => $isAdmin && $mailbox->offersImport(),
            ]);

        return Inertia::render('settings', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
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
            // The numbers the explanation of a mailbox quotes, from where they are set.
            'mailFacts' => [
                'answerConfidence' => (int) round((float) config('knowledge.mail.answer_min_confidence') * 100),
                'aiLogDays' => config('knowledge.ai_log.payloads') ? (int) config('knowledge.ai_log.payload_retention_days') : 0,
            ],
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

    /**
     * The language the knowledge base is built in. Changed, what it holds is
     * put into the new language in the background.
     */
    public function updateKnowledgeLanguage(KnowledgeLanguageUpdateRequest $request): RedirectResponse
    {
        $account = Tenancy::account();
        $before = $account->knowledge_locale;
        $account->forceFill(['knowledge_locale' => $request->validated('knowledge_locale')])->save();

        if ($account->knowledge_locale !== $before) {
            TranslateKnowledge::dispatch(folders: true);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $account->knowledge_locale !== $before
            ? __('The knowledge base is now built in :language. Howy is translating what it already holds.', ['language' => $account->knowledge_locale->label()])
            : __('Account updated.')]);

        return to_route('settings');
    }
}
