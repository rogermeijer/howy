<?php

namespace Tests\Feature\Knowledge;

use App\Enums\FactSourceKind;
use App\Enums\FactStatus;
use App\Facades\Tenancy;
use App\Jobs\Mail\InterpretEmail;
use App\Models\Account;
use App\Models\Document;
use App\Models\DocumentSection;
use App\Models\DocumentVersion;
use App\Models\Email;
use App\Models\KnowledgeFact;
use App\Models\KnowledgeFactSource;
use App\Models\Mailbox;
use App\Models\User;
use App\Services\Knowledge\Ai\Agents\SectionEnricher;
use App\Services\Knowledge\Facts\FactSources;
use App\Services\Mail\Interpretation\MailFactWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\FakesKnowledgeAi;
use Tests\TestCase;

/**
 * A fact is one claim, stated in as many places as say it: a claim found
 * again in another document or a mail is one more source of the fact, not a
 * second fact, and a fact lives as long as one of its places does.
 */
class FactSourcesTest extends TestCase
{
    use FakesKnowledgeAi, RefreshDatabase;

    private const string LEAVE = 'Medewerkers hebben recht op 25 vakantiedagen per jaar.';

    private Account $account;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('knowledge');
        Process::fake([
            '*pdfinfo*' => Process::result("Pages: 2\n"),
            '*pdftohtml*' => Process::result((string) file_get_contents(base_path('tests/Fixtures/Knowledge/personeelshandboek.pdf.xml'))),
        ]);
        $this->fakeKnowledgeAi();

        $this->account = Account::factory()->create(['locale' => 'nl']);
        $this->admin = User::factory()->withAccount($this->account)->create();
    }

    public function test_every_fact_records_where_it_is_stated(): void
    {
        $fact = Tenancy::for($this->account, fn () => KnowledgeFact::factory()->create());

        Tenancy::for($this->account, function () use ($fact) {
            $source = KnowledgeFactSource::query()->sole();
            $this->assertSame($fact->id, $source->knowledge_fact_id);
            $this->assertSame(FactSourceKind::Document, $source->kind);
            $this->assertSame($fact->section_id, $source->section_id);
            $this->assertSame($fact->document?->title, $source->title);
        });
    }

    public function test_approving_a_claim_already_known_adds_a_source_not_a_fact(): void
    {
        $known = $this->publishedFact(self::LEAVE, 'Arbobeleid');
        $this->enricherSays(self::LEAVE);

        $handbook = $this->upload();
        $this->approve($handbook);

        Tenancy::for($this->account, function () use ($known, $handbook) {
            // One fact, stated in two documents.
            $this->assertSame(1, KnowledgeFact::query()->where('statement', self::LEAVE)->count());
            $this->assertSame(['Arbobeleid', 'Personeelshandboek'], $known->sources()->orderBy('id')->pluck('title')->all());
            $this->assertTrue($known->sources()->where('document_id', $handbook->id)->exists());
            $this->assertSame(FactStatus::Core, $known->refresh()->status);
        });

        // The handbook's section shows the fact, cited from where it was known first.
        $section = Tenancy::for($this->account, fn () => KnowledgeFactSource::query()->where('document_id', $handbook->id)->value('section_id'));
        $this->actingAs($this->admin)
            ->get(route('knowledge.documents.show', ['document' => $handbook, 'section' => $section]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('section.facts.0.statement', self::LEAVE)
                ->where('section.facts.0.sources.0.title', 'Arbobeleid')
                ->where('section.facts.0.sources.1.title', 'Personeelshandboek'));
    }

    public function test_a_mail_that_says_what_is_known_becomes_one_of_its_sources(): void
    {
        $known = $this->publishedFact(self::LEAVE, 'Arbobeleid');
        $this->fakeMailAi(['intent' => 'information', 'statements' => [['statement' => self::LEAVE, 'subject' => 'verlof', 'valid_from' => null, 'flag' => '']]]);

        $email = Tenancy::for($this->account, fn () => Email::factory()->create([
            'mailbox_id' => Mailbox::factory()->create(['email_address' => 'kennis@noordkade.nl'])->id,
            'to' => [['name' => null, 'email' => 'kennis@noordkade.nl']],
            'subject' => 'Verlof 2027',
            'content_text' => 'Even ter herinnering: iedereen heeft 25 vakantiedagen per jaar.',
        ]));
        Tenancy::for($this->account, fn () => InterpretEmail::dispatchSync($email->id));

        Tenancy::for($this->account, function () use ($known, $email) {
            $this->assertSame(1, KnowledgeFact::query()->count());
            $this->assertTrue($known->sources()->where('kind', FactSourceKind::Mail)->where('email_id', $email->id)->where('title', 'Verlof 2027')->exists());
        });
    }

    public function test_a_claim_still_waiting_elsewhere_is_not_relied_on(): void
    {
        // Proposed in a mail nobody approved yet: the document keeps its own fact.
        Tenancy::for($this->account, function () {
            $email = Email::factory()->create(['subject' => 'Verlof']);
            app(MailFactWriter::class)->write($email, [['statement' => self::LEAVE, 'subject' => 'verlof', 'valid_from' => null, 'flag' => null]], FactStatus::Proposed);
        });
        $this->enricherSays(self::LEAVE);

        $this->approve($this->upload());

        Tenancy::for($this->account, fn () => $this->assertSame(1, KnowledgeFact::query()->where('statement', self::LEAVE)->where('status', FactStatus::Core)->count()));
    }

    public function test_a_new_version_keeps_a_fact_that_is_stated_elsewhere_too(): void
    {
        [$v1, $v2, $both, $onlyHere, $elsewhere] = Tenancy::for($this->account, function (): array {
            $document = Document::factory()->withVersion()->create(['title' => 'Handboek']);
            $v1 = DocumentVersion::query()->findOrFail($document->current_version_id);
            $v2 = DocumentVersion::factory()->for($document)->create(['version_number' => 2]);
            $section = DocumentSection::factory()->create(['document_id' => $document->id, 'document_version_id' => $v1->id]);

            $both = KnowledgeFact::factory()->create(['section_id' => $section->id]);
            $onlyHere = KnowledgeFact::factory()->create(['section_id' => $section->id]);

            // Another document says the first one too.
            $elsewhere = DocumentSection::factory()->create(['document_id' => Document::factory()->withVersion()->create(['title' => 'Arbobeleid'])->id]);
            $both->sources()->create(['kind' => FactSourceKind::Document, 'document_id' => $elsewhere->document_id, 'document_version_id' => $elsewhere->document_version_id, 'section_id' => $elsewhere->id, 'title' => 'Arbobeleid']);

            return [$v1, $v2, $both, $onlyHere, $elsewhere];
        });

        // The section changed in version 2: nothing moves along.
        $result = Tenancy::for($this->account, fn () => app(FactSources::class)->handOver($v1, $v2, []));

        Tenancy::for($this->account, function () use ($result, $both, $onlyHere, $elsewhere) {
            $this->assertSame(1, $result['expired']);
            $this->assertSame(FactStatus::Expired, $onlyHere->refresh()->status);

            $both->refresh();
            $this->assertSame(FactStatus::Core, $both->status);
            $this->assertSame($elsewhere->id, $both->section_id);
            $this->assertSame(['Arbobeleid'], $both->sources()->pluck('title')->all());
        });
    }

    private function publishedFact(string $statement, string $title): KnowledgeFact
    {
        return Tenancy::for($this->account, function () use ($statement, $title): KnowledgeFact {
            $document = Document::factory()->withVersion()->create(['title' => $title]);
            $section = DocumentSection::factory()->create(['document_id' => $document->id, 'document_version_id' => $document->current_version_id]);

            return KnowledgeFact::factory()->create([
                'section_id' => $section->id,
                'statement' => $statement,
                'content_hash' => MailFactWriter::hash($statement),
                'embedding' => $this->fakeVector('verlof: '.$statement, (int) config('knowledge.embeddings.dimensions')),
                'embedding_model' => config('knowledge.embeddings.model'),
            ]);
        });
    }

    private function enricherSays(string $statement): void
    {
        SectionEnricher::fake(function (string $prompt) use ($statement): array {
            preg_match('/Section: ([^\n]+)/', $prompt, $section);
            $facts = str_ends_with(trim($section[1] ?? ''), '2.1 Vakantiedagen') ? [['statement' => $statement, 'subject' => 'verlof', 'valid_from' => null, 'confidence' => 0.9]] : [];

            return ['summary' => 'Samenvatting.', 'facts' => $facts];
        });
    }

    private function upload(): Document
    {
        $this->actingAs($this->admin)->post(route('knowledge.documents.store'), [
            'file' => UploadedFile::fake()->createWithContent('handboek.pdf', '%PDF-1.4 handboek'),
            'title' => 'Personeelshandboek',
            'type' => 'handbook',
            'is_core' => true,
            'language' => 'nl',
        ])->assertSessionHasNoErrors();

        return Tenancy::for($this->account, fn () => Document::query()->where('title', 'Personeelshandboek')->sole());
    }

    private function approve(Document $document): void
    {
        $version = Tenancy::for($this->account, fn () => $document->latestVersion()->value('id'));

        $this->actingAs($this->admin)
            ->post(route('knowledge.documents.versions.approve', [$document, $version]))
            ->assertRedirect();
    }
}
