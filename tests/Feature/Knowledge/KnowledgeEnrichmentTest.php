<?php

namespace Tests\Feature\Knowledge;

use App\Enums\FactStatus;
use App\Enums\KnowledgeOrigin;
use App\Enums\ProcessingStatus;
use App\Enums\ProcessingStep;
use App\Enums\StepStatus;
use App\Enums\TopicReview;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\DocumentProcessingStep;
use App\Models\DocumentSection;
use App\Models\DocumentVersion;
use App\Models\KnowledgeFact;
use App\Models\KnowledgeSummary;
use App\Models\KnowledgeTopic;
use App\Models\KnowledgeTopicLink;
use App\Models\User;
use App\Services\Knowledge\Ai\Agents\SectionEnricher;
use App\Services\Knowledge\Ai\Agents\TopicTreeProposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\FakesKnowledgeAi;
use Tests\TestCase;

class KnowledgeEnrichmentTest extends TestCase
{
    use FakesKnowledgeAi, RefreshDatabase;

    private Account $account;

    private User $admin;

    private string $xml;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('knowledge');
        $this->fakeKnowledgeAi();

        $this->account = Account::factory()->create(['locale' => 'nl']);
        $this->admin = User::factory()->withAccount($this->account)->create();
        $this->xml = (string) file_get_contents(base_path('tests/Fixtures/Knowledge/personeelshandboek.pdf.xml'));
    }

    public function test_sections_get_summaries_and_facts_and_land_in_folders(): void
    {
        $this->upload($this->xml, 'v1');

        Tenancy::for($this->account, function () {
            $version = DocumentVersion::query()->sole();
            $this->assertSame(ProcessingStatus::Ready, $version->status);

            $section = DocumentSection::query()->where('heading', '3 Ziekmelding')->sole();
            $this->assertSame('Samenvatting van 3 Ziekmelding.', $section->summary?->text);

            $fact = KnowledgeFact::query()->where('section_id', $section->id)->sole();
            $this->assertSame('Regel uit 3 Ziekmelding.', $fact->statement);
            $this->assertSame('ziekmelding', $fact->subject);
            $this->assertSame(FactStatus::Core, $fact->status);
            $this->assertSame(2, $fact->page_from);
            $this->assertNotNull($fact->chunk_id);
            $this->assertCount(1024, $fact->embedding ?? []);

            $this->assertSame('Samenvatting van het document.', KnowledgeSummary::query()->where('summarizable_type', 'document_version')->sole()->text);

            // The tree was proposed (and marked new), and sections are filed in it.
            $personeel = KnowledgeTopic::query()->whereNull('parent_id')->where('name', 'Personeel')->sole();
            $ziekte = KnowledgeTopic::query()->where('name', 'Ziekte')->sole();
            $this->assertSame($personeel->id, $ziekte->parent_id);
            $this->assertSame(2, $ziekte->depth);
            $this->assertSame("{$personeel->id}.{$ziekte->id}", $ziekte->path);
            $this->assertSame(TopicReview::New, $ziekte->review_status);
            $this->assertTrue(KnowledgeTopicLink::query()->where('topic_id', $ziekte->id)->where('linkable_id', $section->id)->exists());

            // Overviews were written for every folder, bottom-up.
            $this->assertSame(0, KnowledgeTopic::query()->where('summary_stale', true)->count());
            $this->assertStringStartsWith('Overzicht:', (string) $ziekte->fresh()?->summary);

            $this->assertSame(StepStatus::Succeeded, DocumentProcessingStep::query()->where('step', ProcessingStep::Topics)->sole()->status);
        });
    }

    public function test_a_new_version_carries_over_what_did_not_change_and_expires_the_rest(): void
    {
        $this->upload($this->xml, 'v1');

        [$oldFact, $unchangedFact] = Tenancy::for($this->account, fn () => [
            KnowledgeFact::query()->whereHas('section', fn ($q) => $q->where('heading', '3 Ziekmelding'))->sole(),
            KnowledgeFact::query()->whereHas('section', fn ($q) => $q->where('heading', '2.1 Vakantiedagen'))->sole(),
        ]);

        $documentId = Tenancy::for($this->account, fn () => DocumentVersion::query()->sole()->document_id);
        $this->fakePoppler(str_replace('vóór 09:00 uur telefonisch', 'vóór 08:30 uur telefonisch', $this->xml));

        $this->actingAs($this->admin)->post(route('knowledge.documents.versions.store', $documentId), [
            'file' => UploadedFile::fake()->createWithContent('handboek.pdf', '%PDF-1.4 v2'),
        ])->assertSessionHasNoErrors();

        // Only the changed section was sent for enrichment the second time.
        SectionEnricher::assertPrompted(fn ($prompt) => $prompt->contains('Section: Personeelshandboek Noordkade › 3 Ziekmelding') && $prompt->contains('08:30'));
        TopicTreeProposer::assertPromptedTimes(1);

        Tenancy::for($this->account, function () use ($oldFact, $unchangedFact) {
            $v2 = DocumentVersion::query()->where('version_number', 2)->sole();

            // Unchanged: same fact, moved to the new version.
            $unchangedFact->refresh();
            $this->assertSame($v2->id, $unchangedFact->document_version_id);
            $this->assertSame(FactStatus::Core, $unchangedFact->status);

            // Changed: the old fact expired and points to its successor.
            $oldFact->refresh();
            $this->assertSame(FactStatus::Expired, $oldFact->status);
            $this->assertNotNull($oldFact->valid_until);
            $successor = KnowledgeFact::query()->findOrFail($oldFact->superseded_by_id);
            $this->assertSame($v2->id, $successor->document_version_id);

            // Folder links came along for unchanged sections.
            $vacation = DocumentSection::query()->where('document_version_id', $v2->id)->where('heading', '2.1 Vakantiedagen')->sole();
            $this->assertTrue(KnowledgeTopicLink::query()->where('linkable_id', $vacation->id)->exists());
            $this->assertSame('Samenvatting van 2.1 Vakantiedagen.', $vacation->summary?->text);

            $enrich = DocumentProcessingStep::query()->where('document_version_id', $v2->id)->where('step', ProcessingStep::Enrich)->sole();
            $this->assertSame(1, $enrich->meta['requests'] ?? null);
        });
    }

    public function test_non_core_documents_get_summaries_but_no_facts(): void
    {
        $this->upload($this->xml, 'manual', isCore: false);

        Tenancy::for($this->account, function () {
            $this->assertSame(0, KnowledgeFact::query()->count());
            $this->assertGreaterThan(0, KnowledgeSummary::query()->where('summarizable_type', 'document_section')->count());
        });
    }

    public function test_a_manual_folder_link_is_never_removed_by_ai(): void
    {
        $this->upload($this->xml, 'v1');

        Tenancy::for($this->account, function () {
            $manual = KnowledgeTopic::factory()->create(['name' => 'Handmatig', 'origin' => KnowledgeOrigin::Manual]);
            $section = DocumentSection::query()->where('heading', '4 Onkosten')->sole();
            KnowledgeTopicLink::create(['topic_id' => $manual->id, 'linkable_type' => 'document_section', 'linkable_id' => $section->id, 'origin' => KnowledgeOrigin::Manual]);
        });

        $documentId = Tenancy::for($this->account, fn () => DocumentVersion::query()->sole()->document_id);
        $this->fakePoppler(str_replace('€ 0,23 per kilometer', '€ 0,25 per kilometer', $this->xml));
        $this->actingAs($this->admin)->post(route('knowledge.documents.versions.store', $documentId), [
            'file' => UploadedFile::fake()->createWithContent('handboek.pdf', '%PDF-1.4 v2'),
        ]);

        Tenancy::for($this->account, fn () => $this->assertTrue(
            KnowledgeTopicLink::query()->where('origin', KnowledgeOrigin::Manual)->exists(),
        ));
    }

    private function fakePoppler(string $xml): void
    {
        Process::fake([
            '*pdfinfo*' => Process::result("Pages: 2\n"),
            '*pdftohtml*' => Process::result($xml),
        ]);
    }

    private function upload(string $xml, string $body, bool $isCore = true): void
    {
        $this->fakePoppler($xml);

        $this->actingAs($this->admin)->post(route('knowledge.documents.store'), [
            'file' => UploadedFile::fake()->createWithContent('handboek.pdf', '%PDF-1.4 '.$body),
            'type' => 'handbook',
            'is_core' => $isCore,
            'language' => 'nl',
        ])->assertSessionHasNoErrors();
    }
}
