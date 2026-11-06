<?php

namespace Tests\Feature\Knowledge;

use App\Enums\FactStatus;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Document;
use App\Models\DocumentSection;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeFact;
use App\Models\User;
use App\Services\Knowledge\Ai\Agents\SectionEnricher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\FakesKnowledgeAi;
use Tests\TestCase;

/**
 * A reviewer sees where a document states a fact: the passage of its
 * section, the words marked. Exactly, from the quote kept at extraction;
 * else by the words fact and passage share.
 */
class FactPassageTest extends TestCase
{
    use FakesKnowledgeAi, RefreshDatabase;

    private const string MT = 'The MT consists of six people. The MT meets weekly, sets the quarterly company OKRs, approves expenses above EUR 500, runs the calibration of performance ratings and decides on promotions. Minutes are shared on Notion.';

    private Account $account;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('knowledge');
        $this->account = Account::factory()->create(['locale' => 'nl']);
        $this->admin = User::factory()->withAccount($this->account)->create();
    }

    public function test_the_quote_is_marked_exactly(): void
    {
        [$document, $fact] = $this->fact('Het MT keurt uitgaven van meer dan EUR 500 goed.', 'approves expenses above EUR 500');

        $this->actingAs($this->admin)
            ->getJson(route('knowledge.documents.facts.source', [$document, $fact]))
            ->assertOk()
            ->assertJson([
                'heading' => '3. Organisation › 3.2 The Management Team (MT)',
                'before' => 'The MT consists of six people. The MT meets weekly, sets the quarterly company OKRs, ',
                'quote' => 'approves expenses above EUR 500',
                'after' => ', runs the calibration of performance ratings and decides on promotions. Minutes are shared on Notion.',
                'exact' => true,
                'statement' => 'Het MT keurt uitgaven van meer dan EUR 500 goed.',
            ]);
    }

    public function test_without_a_quote_the_sentence_sharing_names_and_numbers_is_marked(): void
    {
        [$document, $fact] = $this->fact('Het MT keurt uitgaven van meer dan EUR 500 goed.', null);

        $this->actingAs($this->admin)
            ->getJson(route('knowledge.documents.facts.source', [$document, $fact]))
            ->assertOk()
            ->assertJson([
                'quote' => 'The MT meets weekly, sets the quarterly company OKRs, approves expenses above EUR 500, runs the calibration of performance ratings and decides on promotions.',
                'exact' => false,
            ]);
    }

    public function test_a_fact_not_stated_in_the_document_404s(): void
    {
        [, $fact] = $this->fact('Het MT keurt uitgaven van meer dan EUR 500 goed.', null);
        $other = Tenancy::for($this->account, fn () => Document::factory()->withVersion()->create());

        $this->actingAs($this->admin)
            ->getJson(route('knowledge.documents.facts.source', [$other, $fact]))
            ->assertNotFound();

        // Nor from another account.
        $outsider = User::factory()->withAccount()->create();
        $this->actingAs($outsider)
            ->getJson(route('knowledge.documents.facts.source', [$other, $fact]))
            ->assertNotFound();
    }

    public function test_extraction_keeps_a_quote_only_when_it_stands_in_the_section(): void
    {
        $this->fakeKnowledgeAi();
        SectionEnricher::fake(fn (): array => ['summary' => 'Verlof.', 'facts' => [
            ['statement' => 'Medewerkers hebben recht op vijfentwintig vakantiedagen per jaar.', 'quote' => "entitled to twenty-five\ndays of leave per year", 'subject' => 'verlof', 'valid_from' => null, 'confidence' => 0.9],
            ['statement' => 'Medewerkers hebben recht op dertig vakantiedagen.', 'quote' => 'entitled to thirty days', 'subject' => 'verlof', 'valid_from' => null, 'confidence' => 0.9],
        ]]);

        $this->actingAs($this->admin)->post(route('knowledge.documents.store'), [
            'file' => UploadedFile::fake()->createWithContent('leave.txt', 'Employees are entitled to twenty-five days of leave per year. Leave that is not taken expires after six months.'),
            'type' => 'handbook',
            'is_core' => true,
            'language' => 'en',
        ])->assertSessionHasNoErrors();

        Tenancy::for($this->account, function () {
            $this->assertSame("entitled to twenty-five\ndays of leave per year", KnowledgeFact::query()->where('statement', 'like', '%vijfentwintig%')->value('quote'));
            $this->assertNull(KnowledgeFact::query()->where('statement', 'like', '%dertig%')->value('quote'));
        });
    }

    /**
     * @return array{0: Document, 1: KnowledgeFact}
     */
    private function fact(string $statement, ?string $quote): array
    {
        return Tenancy::for($this->account, function () use ($statement, $quote): array {
            $document = Document::factory()->withVersion()->create(['title' => 'Personnel Handbook']);
            $section = DocumentSection::factory()->create([
                'document_id' => $document->id,
                'document_version_id' => $document->current_version_id,
                'heading' => '3.2 The Management Team (MT)',
                'heading_path' => '3. Organisation › 3.2 The Management Team (MT)',
            ]);
            $chunk = KnowledgeChunk::factory()->create(['section_id' => $section->id, 'content' => self::MT]);

            return [$document, KnowledgeFact::factory()->create([
                'section_id' => $section->id,
                'chunk_id' => $chunk->id,
                'statement' => $statement,
                'quote' => $quote,
                'status' => FactStatus::Proposed,
            ])];
        });
    }
}
