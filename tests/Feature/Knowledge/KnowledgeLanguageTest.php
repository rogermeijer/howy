<?php

namespace Tests\Feature\Knowledge;

use App\Enums\Locale;
use App\Facades\Tenancy;
use App\Jobs\Mail\InterpretEmail;
use App\Models\Account;
use App\Models\Document;
use App\Models\Email;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeFact;
use App\Models\Mailbox;
use App\Models\User;
use App\Services\Gmail\GmailClient;
use App\Services\Knowledge\Ai\Agents\DocumentSummarizer;
use App\Services\Knowledge\Ai\Agents\EmailClassifier;
use App\Services\Knowledge\Ai\Agents\SectionEnricher;
use App\Services\Knowledge\Ai\Agents\TopicTreeProposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\Concerns\FakesKnowledgeAi;
use Tests\Fixtures\GmailFixtures;
use Tests\TestCase;

/**
 * The knowledge base is built in one language, set per account: what Howy
 * writes itself is in it, whatever language a document or mail is in. The
 * document's own text keeps its language.
 */
class KnowledgeLanguageTest extends TestCase
{
    use FakesKnowledgeAi, RefreshDatabase;

    private Account $account;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('knowledge');
        $this->fakeKnowledgeAi();

        // English for the people, Dutch for the knowledge base.
        $this->account = Account::factory()->create(['locale' => 'en', 'knowledge_locale' => 'nl']);
        $this->admin = User::factory()->withAccount($this->account)->create();
    }

    public function test_an_account_builds_its_knowledge_base_in_its_own_language_until_told_otherwise(): void
    {
        $this->assertSame(Locale::Dutch, Account::factory()->create(['locale' => 'nl'])->knowledge_locale);
        $this->assertSame(Locale::English, Account::factory()->create(['locale' => 'en'])->knowledge_locale);
    }

    public function test_changing_the_account_language_never_changes_the_knowledge_language(): void
    {
        // Both Dutch, as an account starts out; only the account moves to English.
        $account = Account::factory()->create(['locale' => 'nl']);
        $admin = User::factory()->withAccount($account)->create();

        $this->actingAs($admin)
            ->patch(route('account.update'), ['name' => $account->name, 'locale' => 'en', 'timezone' => 'UTC', 'knowledge_locale' => 'en'])
            ->assertRedirect(route('settings'));

        $this->assertSame(Locale::English, $account->refresh()->locale);
        $this->assertSame(Locale::Dutch, $account->knowledge_locale);

        // Nor does anything else that fills an account from an array.
        $account->update(['knowledge_locale' => 'en']);
        $this->assertSame(Locale::Dutch, $account->refresh()->knowledge_locale);
    }

    public function test_a_super_admin_changing_the_account_language_leaves_the_knowledge_language_alone(): void
    {
        $account = Account::factory()->create(['locale' => 'nl']);
        $superAdmin = User::factory()->superAdmin()->withAccount()->create();

        $this->actingAs($superAdmin)
            ->patch(route('admin.accounts.update', $account), ['name' => $account->name, 'locale' => 'en', 'timezone' => 'UTC', 'knowledge_locale' => 'en'])
            ->assertRedirect(route('admin.accounts.show', $account));

        $this->assertSame(Locale::English, $account->refresh()->locale);
        $this->assertSame(Locale::Dutch, $account->knowledge_locale);
    }

    public function test_an_english_document_is_summarised_and_its_facts_written_in_the_knowledge_language(): void
    {
        $this->actingAs($this->admin)->post(route('knowledge.documents.store'), [
            'file' => UploadedFile::fake()->createWithContent('handbook.txt', "Leave\n\nEmployees are entitled to twenty-five days of leave per calendar year.\n\nSick leave\n\nReport sick before nine in the morning by phone to your manager."),
            'type' => 'handbook',
            'is_core' => true,
            'language' => 'en',
        ])->assertSessionHasNoErrors();

        $asked = fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, 'Write in: Nederlands (nl)');
        SectionEnricher::assertPrompted($asked);
        DocumentSummarizer::assertPrompted($asked);
        TopicTreeProposer::assertPrompted($asked);

        Tenancy::for($this->account, function () {
            // Facts are searched as Dutch; the text itself stays English.
            $this->assertNotEmpty(KnowledgeFact::query()->pluck('search_config')->all());
            $this->assertSame(['dutch'], KnowledgeFact::query()->distinct()->pluck('search_config')->all());
            $this->assertSame(['english'], KnowledgeChunk::query()->distinct()->pluck('search_config')->all());
        });
    }

    public function test_statements_from_mail_are_written_in_the_knowledge_language(): void
    {
        $this->fakeMailAi(['intent' => 'information', 'language' => 'en', 'statements' => [[
            'statement' => 'Het kantoor is op 5 december gesloten.',
            'subject' => 'kantoor',
            'valid_from' => null,
            'flag' => '',
        ]]]);

        $email = Tenancy::for($this->account, fn () => Email::factory()->create([
            'mailbox_id' => Mailbox::factory()->create(['email_address' => 'kennis@noordkade.nl'])->id,
            'to' => [['name' => null, 'email' => 'kennis@noordkade.nl']],
            'content_text' => 'The office is closed on December 5th, please plan your work around it.',
        ]));

        Tenancy::for($this->account, fn () => InterpretEmail::dispatchSync($email->id));

        EmailClassifier::assertPrompted(fn (AgentPrompt $prompt): bool => str_starts_with($prompt->prompt, 'Knowledge base language: Nederlands (nl)'));
        $this->assertSame('dutch', Tenancy::for($this->account, fn () => KnowledgeFact::query()->where('source_type', 'email')->sole()->search_config));
    }

    public function test_a_mailed_file_is_searched_in_its_own_language_not_the_mails(): void
    {
        $text = str_repeat("Employees are entitled to twenty-five days of leave per year, and they can ask their manager for more when that is needed.\n\n", 6);
        Http::fake([GmailClient::BASE_URL.'/messages/*/attachments/*' => Http::response(['data' => GmailFixtures::encode($text)])]);
        // A Dutch mail carrying an English file.
        $this->fakeMailAi(['intent' => 'information', 'language' => 'nl', 'statements' => [], 'attachments' => [['id' => 'A1', 'use' => 'knowledge', 'reason' => '']]]);

        $email = Tenancy::for($this->account, fn () => Email::factory()->create([
            'mailbox_id' => Mailbox::factory()->create(['email_address' => 'kennis@noordkade.nl'])->id,
            'to' => [['name' => null, 'email' => 'kennis@noordkade.nl']],
            'content_text' => 'Hierbij de Engelse versie van de verlofregeling, voor de kennisbank.',
            'attachments' => [['filename' => 'Leave policy.txt', 'mime_type' => 'text/plain', 'size' => strlen($text), 'attachment_id' => 'att-1', 'part_id' => '1', 'content_id' => null, 'is_inline' => false]],
            'has_attachments' => true,
        ]));

        Tenancy::for($this->account, fn () => InterpretEmail::dispatchSync($email->id));

        Tenancy::for($this->account, function () {
            $this->assertSame(Locale::English, Document::query()->sole()->language);
            $this->assertSame(['english'], KnowledgeChunk::query()->distinct()->pluck('search_config')->all());
            $this->assertSame(['dutch'], KnowledgeFact::query()->distinct()->pluck('search_config')->all());
        });
    }
}
