<?php

namespace Tests\Feature\Knowledge;

use App\Enums\DocumentType;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Document;
use App\Models\User;
use App\Services\Knowledge\DocumentUploader;
use App\Services\Knowledge\Search\KnowledgeSearch;
use App\Services\Knowledge\Search\Passage;
use App\Services\Knowledge\Search\SearchQuery;
use App\Services\Knowledge\Search\SearchResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\FakesKnowledgeAi;
use Tests\TestCase;

class KnowledgeSearchTest extends TestCase
{
    use FakesKnowledgeAi, RefreshDatabase;

    private Account $ours;

    private Account $theirs;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('knowledge');
        Process::fake([
            '*pdfinfo*' => Process::result("Pages: 2\n"),
            '*pdftohtml*' => Process::result((string) file_get_contents(base_path('tests/Fixtures/Knowledge/personeelshandboek.pdf.xml'))),
        ]);

        $this->ours = Account::factory()->create(['locale' => 'nl']);
        $this->theirs = Account::factory()->create(['locale' => 'nl']);
        $this->admin = User::factory()->withAccount($this->ours)->create();
    }

    public function test_full_text_search_finds_the_section_with_its_source(): void
    {
        $document = $this->index($this->ours, 'ours');

        $result = $this->search($this->ours, new SearchQuery('Mag ik me via WhatsApp ziek melden?'));

        $this->assertFalse($result->usedVectors);
        $this->assertNotEmpty($result->passages);

        $first = $result->passages[0];
        $this->assertSame($document->id, $first->documentId);
        $this->assertSame('Personeelshandboek Noordkade › 3 Ziekmelding', $first->headingPath);
        $this->assertSame(2, $first->pageFrom);
        $this->assertStringContainsString('WhatsApp is niet voldoende', $first->text);
        $this->assertTrue($first->wholeSection);
        $this->assertSame('Handboek v1 · Personeelshandboek Noordkade › 3 Ziekmelding · p. 2', $first->citation());
    }

    public function test_vector_and_text_ranks_are_fused(): void
    {
        $this->fakeKnowledgeAi();
        config(['knowledge.search.min_similarity' => 0.05]);
        $this->index($this->ours, 'ours');

        $result = $this->search($this->ours, new SearchQuery('vakantiedagen dienstjaren'));

        $this->assertTrue($result->usedVectors);
        $this->assertSame('Personeelshandboek Noordkade › 2 Verlof › 2.1 Vakantiedagen', $result->passages[0]->headingPath);
        $this->assertNotNull($result->passages[0]->ranks['vector']);
        $this->assertNotNull($result->passages[0]->ranks['text']);
        $this->assertStringContainsString('| 20 jaar of langer | 3 dagen |', $result->passages[0]->text);
    }

    public function test_loosely_related_hits_found_by_meaning_alone_are_dropped(): void
    {
        $this->fakeKnowledgeAi();
        $this->index($this->ours, 'ours');

        // Shares no word with the handbook: only the vector search could find
        // anything, and the fake vectors are far apart.
        config(['knowledge.search.min_similarity' => 0.0, 'knowledge.search.min_similarity_vector_only' => 0.45]);
        $this->assertSame([], $this->search($this->ours, new SearchQuery('huisdieren kantoorhond', includeFacts: false))->passages);

        config(['knowledge.search.min_similarity_vector_only' => 0.0]);
        $this->assertNotEmpty($this->search($this->ours, new SearchQuery('huisdieren kantoorhond', includeFacts: false))->passages);
    }

    public function test_the_token_budget_is_a_hard_limit(): void
    {
        $this->index($this->ours, 'ours');

        $result = $this->search($this->ours, new SearchQuery('verlof ziekte werktijden onkosten overwerk', maxTokens: 120, includeFacts: false));

        $this->assertLessThanOrEqual(120, $result->tokenCount);
        $this->assertSame($result->tokenCount, array_sum(array_map(fn (Passage $passage): int => $passage->tokenCount, $result->passages)));
    }

    public function test_filters_by_document_type(): void
    {
        $this->index($this->ours, 'ours');

        $this->assertNotEmpty($this->search($this->ours, new SearchQuery('ziekmelding', documentTypes: [DocumentType::Handbook]))->passages);
        $this->assertSame([], $this->search($this->ours, new SearchQuery('ziekmelding', documentTypes: [DocumentType::Policy]))->passages);
    }

    public function test_another_accounts_knowledge_is_never_returned(): void
    {
        $this->fakeKnowledgeAi();
        config(['knowledge.search.min_similarity' => 0.0]);

        $theirs = $this->index($this->theirs, 'theirs');

        $this->assertSame([], $this->search($this->ours, new SearchQuery('ziekmelding WhatsApp vakantiedagen'))->passages);

        $ours = $this->index($this->ours, 'ours');
        $result = $this->search($this->ours, new SearchQuery('ziekmelding WhatsApp vakantiedagen'));

        $this->assertNotEmpty($result->passages);
        $this->assertSame([$ours->id], array_values(array_unique(array_map(fn (Passage $passage): int => $passage->documentId, $result->passages))));
        $this->assertNotSame($theirs->id, $ours->id);
    }

    public function test_the_search_page_shows_passages_with_sources(): void
    {
        $this->index($this->ours, 'ours');

        $this->actingAs($this->admin)
            ->get(route('knowledge.search', ['q' => 'ziekmelding whatsapp']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('knowledge/search')
                ->where('query.q', 'ziekmelding whatsapp')
                ->where('result.passages.0.headingPath', 'Personeelshandboek Noordkade › 3 Ziekmelding')
                ->where('result.passages.0.documentTitle', 'Handboek')
                ->where('canDebug', true));
    }

    private function index(Account $account, string $body): Document
    {
        return Tenancy::for($account, function () use ($account, $body) {
            $user = User::factory()->withAccount($account)->create();
            $file = UploadedFile::fake()->createWithContent('handboek.pdf', '%PDF-1.4 '.$body);

            return app(DocumentUploader::class)->create($file, ['type' => 'handbook', 'is_core' => true, 'language' => 'nl'], $user);
        });
    }

    private function search(Account $account, SearchQuery $query): SearchResult
    {
        return Tenancy::for($account, fn () => app(KnowledgeSearch::class)->search($query));
    }
}
