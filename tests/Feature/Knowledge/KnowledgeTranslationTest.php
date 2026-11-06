<?php

namespace Tests\Feature\Knowledge;

use App\Enums\Locale;
use App\Facades\Tenancy;
use App\Jobs\Knowledge\TranslateKnowledge;
use App\Models\Account;
use App\Models\Document;
use App\Models\DocumentSection;
use App\Models\KnowledgeFact;
use App\Models\KnowledgeTopic;
use App\Models\User;
use App\Services\Knowledge\Ai\Agents\KnowledgeTranslator;
use App\Services\Knowledge\Translation\KnowledgeTranslation;
use App\Services\Mail\Interpretation\MailFactWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\Concerns\FakesKnowledgeAi;
use Tests\TestCase;

/**
 * What Howy wrote in another language is put into the knowledge base's
 * language; after that the same claim from two documents is one fact.
 */
class KnowledgeTranslationTest extends TestCase
{
    use FakesKnowledgeAi, RefreshDatabase;

    private const array ENGLISH = [
        'Employees are entitled to 25 days of leave per year.' => 'Medewerkers hebben recht op 25 vakantiedagen per jaar.',
        'If you are sick, you report it to your manager before nine in the morning.' => 'Als je ziek bent, meld je dat vóór negen uur bij je leidinggevende.',
        'The company pays for the travel costs of all employees who come to the office by train.' => 'Het bedrijf vergoedt de reiskosten van alle medewerkers die met de trein naar kantoor komen.',
        'You can work from home for up to three days a week, and you have to be at the office on Tuesday.' => 'Je kunt maximaal drie dagen per week thuiswerken, en op dinsdag ben je op kantoor.',
        'Leave that is not taken by the end of the year will be carried over for six months.' => 'Verlof dat aan het einde van het jaar niet is opgenomen, blijft zes maanden staan.',
        'leave' => 'verlof',
        'Staff' => 'Personeel',
        'Everything about the people who work at the company.' => 'Alles over de mensen die bij het bedrijf werken.',
    ];

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeKnowledgeAi();
        $this->account = Account::factory()->create(['locale' => 'nl']);

        KnowledgeTranslator::fake(function (string $prompt): array {
            preg_match_all('/<entry id="([^"]+)">(.*?)<\/entry>/s', $prompt, $entries, PREG_SET_ORDER);

            return ['translations' => array_map(fn (array $entry): array => ['id' => $entry[1], 'text' => self::ENGLISH[$entry[2]] ?? $entry[2]], $entries)];
        })->preventStrayPrompts();
    }

    public function test_facts_in_another_language_are_translated_and_the_same_claim_becomes_one_fact(): void
    {
        $known = $this->fact('Arbobeleid', 'Medewerkers hebben recht op 25 vakantiedagen per jaar.', 'dutch');
        $english = Tenancy::for($this->account, fn () => array_map(fn (string $statement): KnowledgeFact => $this->fact('Personnel Handbook', $statement, 'dutch'), array_slice(array_keys(self::ENGLISH), 0, 5)));

        $result = Tenancy::for($this->account, fn () => app(KnowledgeTranslation::class)->run());

        $this->assertSame(5, $result['facts']);
        $this->assertSame(1, $result['merged']);

        // Only what was in another language went out.
        KnowledgeTranslator::assertNotPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, '<entry id="F'.$known->id.'">'));

        Tenancy::for($this->account, function () use ($known, $english) {
            $this->assertSame(1, KnowledgeFact::query()->where('statement', 'Medewerkers hebben recht op 25 vakantiedagen per jaar.')->count());
            $this->assertSame(['Arbobeleid', 'Personnel Handbook'], $known->sources()->orderBy('id')->pluck('title')->all());

            $sick = $english[1]->refresh();
            $this->assertSame('Als je ziek bent, meld je dat vóór negen uur bij je leidinggevende.', $sick->statement);
            $this->assertSame('verlof', $sick->subject);
            $this->assertSame(MailFactWriter::hash($sick->statement), $sick->content_hash);
            $this->assertSame('dutch', $sick->search_config);
            $this->assertNotNull($sick->embedding);
        });
    }

    public function test_folders_are_translated_when_the_language_changes(): void
    {
        Tenancy::for($this->account, function () {
            KnowledgeTopic::factory()->create(['name' => 'Staff', 'description' => 'Everything about the people who work at the company.']);
        });

        Tenancy::for($this->account, fn () => app(KnowledgeTranslation::class)->run(folders: true));

        Tenancy::for($this->account, function () {
            $topic = KnowledgeTopic::query()->sole();
            $this->assertSame('Personeel', $topic->name);
            $this->assertSame('personeel', $topic->slug);
            $this->assertSame('Alles over de mensen die bij het bedrijf werken.', $topic->description);
        });
    }

    public function test_an_administrator_changes_the_language_and_the_translation_runs_in_the_background(): void
    {
        Queue::fake();
        $admin = User::factory()->withAccount($this->account)->create();

        $this->actingAs($admin)
            ->patch(route('account.knowledge-language.update'), ['knowledge_locale' => 'en'])
            ->assertRedirect(route('settings'));

        $this->assertSame(Locale::English, $this->account->refresh()->knowledge_locale);
        Queue::assertPushed(TranslateKnowledge::class, fn (TranslateKnowledge $job): bool => $job->folders);

        // The same language again changes nothing.
        $this->actingAs($admin)->patch(route('account.knowledge-language.update'), ['knowledge_locale' => 'en']);
        Queue::assertPushed(TranslateKnowledge::class, 1);

        $member = User::factory()->withAccount($this->account, isAdmin: false)->create();
        $this->actingAs($member)->patch(route('account.knowledge-language.update'), ['knowledge_locale' => 'nl'])->assertForbidden();
    }

    private function fact(string $title, string $statement, string $searchConfig): KnowledgeFact
    {
        return Tenancy::for($this->account, function () use ($title, $statement, $searchConfig): KnowledgeFact {
            $document = Document::query()->firstWhere('title', $title) ?? Document::factory()->withVersion()->create(['title' => $title]);
            $section = DocumentSection::factory()->create(['document_id' => $document->id, 'document_version_id' => $document->current_version_id]);

            return KnowledgeFact::factory()->create([
                'section_id' => $section->id,
                'statement' => $statement,
                'subject' => 'leave',
                'content_hash' => MailFactWriter::hash($statement),
                'search_config' => $searchConfig,
            ]);
        });
    }
}
