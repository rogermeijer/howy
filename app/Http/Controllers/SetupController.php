<?php

namespace App\Http\Controllers;

use App\Actions\Accounts\ChangeKnowledgeLanguage;
use App\Enums\Locale;
use App\Enums\MailboxStatus;
use App\Facades\Tenancy;
use App\Http\Controllers\Concerns\PresentsMailboxes;
use App\Http\Requests\Settings\AccountUpdateRequest;
use App\Http\Requests\Setup\KnowledgeLanguageChoiceRequest;
use App\Models\Mailbox;
use App\Services\Gmail\MailboxImporter;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The setup wizard an administrator goes through once email is verified: the
 * workspace, the knowledge base language, and a mailbox (which can wait).
 * Each step saves what the settings page would, through the same requests.
 * Once the account is set up, the steps send everyone to the inbox.
 */
class SetupController extends Controller
{
    use PresentsMailboxes;

    public function start(): RedirectResponse
    {
        return to_route(Tenancy::account()->isSetUp() ? 'dashboard' : 'setup.workspace');
    }

    public function workspace(Request $request): Response|RedirectResponse
    {
        $account = Tenancy::account();

        if ($account->isSetUp()) {
            return to_route('dashboard');
        }

        return Inertia::render('setup/workspace', [
            'account' => [
                'name' => $account->name,
                // Until one is saved, the domain of the administrator's own address.
                'domain' => $account->domain ?? $this->companyDomainOf($request->user()->email),
                'locale' => $account->locale->value,
                'timezone' => $account->timezone,
            ],
            'locales' => $this->locales(),
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    public function updateWorkspace(AccountUpdateRequest $request, ChangeKnowledgeLanguage $change): RedirectResponse
    {
        $account = Tenancy::account();
        $account->fill($request->validated());
        $languageChanged = $account->isDirty('locale');
        $account->save();

        // Until setup is done, the knowledge base language follows the
        // workspace's, so its own step, next, suggests the language just chosen.
        if ($languageChanged && ! $account->isSetUp()) {
            $change->handle($account, $account->locale);
        }

        return to_route('setup.knowledge');
    }

    public function knowledge(): Response|RedirectResponse
    {
        $account = Tenancy::account();

        if ($account->isSetUp()) {
            return to_route('dashboard');
        }

        return Inertia::render('setup/knowledge', [
            'knowledgeLocale' => $account->knowledge_locale->value,
            'workspaceLocale' => $account->locale->value,
            // Each with a folder and a fact as Howy would write them in that
            // language, from its own catalogue whatever language the screen is in.
            'languages' => array_map(fn (Locale $locale): array => [
                'value' => $locale->value,
                'label' => $locale->label(),
                'example' => [
                    'folderLabel' => __('Folder', locale: $locale->value),
                    'folder' => __('Returns and refunds', locale: $locale->value),
                    'factLabel' => __('Fact', locale: $locale->value),
                    'fact' => __('Returns are accepted up to 30 days after delivery.', locale: $locale->value),
                ],
            ], Locale::cases()),
        ]);
    }

    public function updateKnowledge(KnowledgeLanguageChoiceRequest $request, ChangeKnowledgeLanguage $change): RedirectResponse
    {
        $change->handle(Tenancy::account(), $request->validated('knowledge_locale'));

        return to_route('setup.mailbox');
    }

    /**
     * Connecting leaves for Google and comes back here, with the mailbox: then
     * the step is about whose mail Howy reads, and older mail to import.
     */
    public function mailbox(MailboxImporter $importer): Response|RedirectResponse
    {
        $account = Tenancy::account();

        if ($account->isSetUp()) {
            return to_route('dashboard');
        }

        $mailbox = $this->withMailboxDetails(Mailbox::query())
            ->where('status', MailboxStatus::Active)
            ->latest()
            ->first();

        return Inertia::render('setup/mailbox', [
            'domain' => $account->domain,
            'company' => $account->name,
            'mailbox' => $mailbox ? $this->presentMailbox($mailbox, $importer, isAdmin: true) : null,
            'mailFacts' => $this->mailFacts(),
        ]);
    }

    /**
     * Done, with a mailbox or without one: a mailbox can be connected from
     * settings at any time, and the inbox keeps asking for one until it is.
     */
    public function finish(): RedirectResponse
    {
        $account = Tenancy::account();

        if (! $account->isSetUp()) {
            $account->forceFill(['onboarded_at' => now()])->save();
        }

        return to_route('setup.done');
    }

    public function done(): Response|RedirectResponse
    {
        $account = Tenancy::account();

        if (! $account->isSetUp()) {
            return to_route('setup.start');
        }

        $mailbox = Mailbox::query()->where('status', MailboxStatus::Active)->latest()->first();

        return Inertia::render('setup/done', [
            'account' => [
                'name' => $account->name,
                'language' => $account->locale->label(),
                'timezone' => $account->timezone,
                'knowledgeLanguage' => $account->knowledge_locale->label(),
            ],
            'mailbox' => $mailbox ? [
                'emailAddress' => $mailbox->email_address,
                'publicDomain' => $mailbox->isOnPublicDomain(),
                'domainAccess' => $mailbox->domain_access->value,
            ] : null,
        ]);
    }

    /**
     * The domain of an address, unless anyone can have an address there.
     */
    private function companyDomainOf(string $email): ?string
    {
        $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));

        return $domain === '' || Mailbox::isPublicDomain($domain) ? null : $domain;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function locales(): array
    {
        return array_map(
            fn (Locale $locale): array => ['value' => $locale->value, 'label' => $locale->label()],
            Locale::cases(),
        );
    }
}
