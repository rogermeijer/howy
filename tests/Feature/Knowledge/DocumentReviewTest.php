<?php

namespace Tests\Feature\Knowledge;

use App\Enums\FactStatus;
use App\Enums\ProcessingStatus;
use App\Enums\ProcessingStep;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Document;
use App\Models\DocumentProcessingStep;
use App\Models\DocumentVersion;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeFact;
use App\Models\User;
use App\Services\Knowledge\Ai\Agents\SectionEnricher;
use App\Services\Knowledge\Search\KnowledgeSearch;
use App\Services\Knowledge\Search\SearchQuery;
use App\Services\Knowledge\Search\SearchResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\FakesKnowledgeAi;
use Tests\TestCase;

/**
 * Every new version is processed in full and then waits: nothing of it is
 * searched until an administrator approves it, and a rejected one leaves no
 * trace.
 */
class DocumentReviewTest extends TestCase
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
        $this->xml = (string) file_get_contents(base_path('tests/Fixtures/Knowledge/personeelshandboek.pdf.xml'));
        $this->fakePoppler($this->xml);

        $this->account = Account::factory()->create(['locale' => 'nl']);
        $this->admin = User::factory()->withAccount($this->account)->create();
    }

    public function test_an_upload_is_processed_but_not_searched_until_it_is_approved(): void
    {
        $document = $this->upload('v1');

        Tenancy::for($this->account, function () use ($document) {
            $version = DocumentVersion::query()->sole();

            $this->assertSame(ProcessingStatus::Review, $version->status);
            $this->assertNull($document->fresh()?->current_version_id);
            $this->assertTrue(KnowledgeChunk::query()->get()->every(fn (KnowledgeChunk $chunk) => ! $chunk->is_current && $chunk->embedding !== null));
            $this->assertTrue(KnowledgeFact::query()->get()->every(fn (KnowledgeFact $fact) => $fact->status === FactStatus::Proposed));
            $this->assertEmpty($this->search()->passages);
        });

        $this->actingAs($this->admin)
            ->get(route('knowledge.documents.show', $document))
            ->assertInertia(fn (Assert $page) => $page
                ->where('review.replaces', null)
                ->where('review.firstVersion', true)
                ->where('review.uploadedBy', $this->admin->name)
                ->where('review.sections.added', 11)
                // Every new fact is listed under its section, in document order.
                ->where('review.newFacts.0.statement', 'Regel uit Personeelshandboek Noordkade.')
                ->where('review.newFacts.0.heading', 'Personeelshandboek Noordkade')
                ->has('review.uncheckedFacts', 0)
                ->has('review.folders'));

        $this->approve($document)->assertRedirect(route('knowledge.documents.show', $document));

        Tenancy::for($this->account, function () use ($document) {
            $version = DocumentVersion::query()->sole();

            $this->assertSame(ProcessingStatus::Ready, $version->status);
            $this->assertSame($this->admin->id, $version->approved_by_user_id);
            $this->assertSame($version->id, $document->fresh()?->current_version_id);
            $this->assertTrue(KnowledgeFact::query()->get()->every(fn (KnowledgeFact $fact) => $fact->status === FactStatus::Core));
            $this->assertNotEmpty($this->search()->passages);
        });
    }

    public function test_the_old_version_keeps_answering_until_the_new_one_is_approved(): void
    {
        $document = $this->upload('v1');
        $this->approve($document);

        $this->fakePoppler(str_replace('vóór 09:00 uur telefonisch', 'vóór 08:30 uur telefonisch', $this->xml));
        $this->actingAs($this->admin)->post(route('knowledge.documents.versions.store', $document), [
            'file' => UploadedFile::fake()->createWithContent('handboek.pdf', '%PDF-1.4 v2'),
        ])->assertSessionHasNoErrors();

        Tenancy::for($this->account, function () use ($document) {
            [$v1, $v2] = DocumentVersion::query()->orderBy('version_number')->get()->all();

            $this->assertSame(ProcessingStatus::Review, $v2->status);
            $this->assertSame($v1->id, $document->fresh()?->current_version_id);
            $this->assertStringContainsString('09:00', $this->search()->passages[0]->text ?? '');
        });

        $this->actingAs($this->admin)
            ->get(route('knowledge.documents.show', $document))
            ->assertInertia(fn (Assert $page) => $page
                ->where('version.version_number', 2)
                ->where('review.replaces', 1)
                ->where('review.firstVersion', false)
                ->where('review.sections.changed', 1)
                ->where('review.sections.unchanged', 10)
                // The changed section states its rule word for word as before:
                // that fact carries on, it is neither new nor expiring.
                ->has('review.newFacts', 0)
                ->where('review.facts.expire', 0)
                ->has('review.carried', 1)
                ->where('review.carried.0.statement', 'Regel uit 3 Ziekmelding.')
                ->where('review.carried.0.heading', 'Personeelshandboek Noordkade › 3 Ziekmelding'));

        $carried = Tenancy::for($this->account, fn () => KnowledgeFact::query()->where('statement', 'Regel uit 3 Ziekmelding.')->where('status', FactStatus::Core)->sole());

        // Nothing changed about it, so nothing was sent to the fact check.
        $check = Tenancy::for($this->account, fn () => DocumentProcessingStep::query()->whereHas('version', fn ($query) => $query->where('version_number', 2))->where('step', ProcessingStep::Reconcile)->sole());
        $this->assertSame(0, $check->meta['checked'] ?? null);

        $this->approve($document);

        Tenancy::for($this->account, function () use ($carried) {
            $this->assertStringContainsString('08:30', $this->search()->passages[0]->text ?? '');

            // The same fact, now in version 2's section; its repeat is gone.
            $this->assertSame(1, KnowledgeFact::query()->where('statement', 'Regel uit 3 Ziekmelding.')->count());
            $carried->refresh();
            $this->assertSame(FactStatus::Core, $carried->status);
            $this->assertSame(2, $carried->version?->version_number);
            $this->assertSame('Personeelshandboek Noordkade › 3 Ziekmelding', $carried->section?->heading_path);
        });
    }

    public function test_facts_the_reviewer_leaves_out_are_not_added(): void
    {
        $document = $this->upload('v1');
        [$left, $kept] = Tenancy::for($this->account, fn () => KnowledgeFact::query()->orderBy('id')->take(2)->get()->all());

        $this->actingAs($this->admin)
            ->patch(route('knowledge.documents.versions.facts.choice', [$document, $this->latest($document), $left->id]), ['choice' => 'omit'])
            ->assertRedirect();

        // Kept while the review goes on.
        $this->actingAs($this->admin)
            ->get(route('knowledge.documents.show', $document))
            ->assertInertia(fn (Assert $page) => $page->where('review.newFacts.0.omitted', true));

        $this->approve($document)->assertRedirect();

        Tenancy::for($this->account, function () use ($left, $kept) {
            $this->assertFalse(KnowledgeFact::query()->whereKey($left->id)->exists());
            $this->assertSame(FactStatus::Core, $kept->refresh()->status);
        });
    }

    public function test_processing_a_waiting_version_again_replaces_what_it_proposed(): void
    {
        $document = $this->upload('v1');
        $count = Tenancy::for($this->account, fn () => KnowledgeFact::query()->count());

        // Read again, the same sections come out worded differently.
        SectionEnricher::fake(function (string $prompt): array {
            preg_match('/Section: ([^\n]+)/', $prompt, $section);

            return ['summary' => 'Opnieuw.', 'facts' => [['statement' => 'Opnieuw gelezen: '.trim($section[1] ?? '').'.', 'subject' => 'x', 'valid_from' => null, 'confidence' => 0.9]]];
        });

        $this->actingAs($this->admin)->post(route('knowledge.documents.reprocess', $document))->assertRedirect();

        Tenancy::for($this->account, function () use ($count) {
            $this->assertSame($count, KnowledgeFact::query()->count());
            $this->assertTrue(KnowledgeFact::query()->get()->every(fn (KnowledgeFact $fact) => str_starts_with($fact->statement, 'Opnieuw gelezen: ') && $fact->section_id !== null));
        });
    }

    public function test_rejecting_the_first_version_hides_the_document_but_remembers_the_file(): void
    {
        $document = $this->upload('v1');
        $path = Tenancy::for($this->account, fn () => DocumentVersion::query()->sole()->path);

        $this->actingAs($this->admin)
            ->post(route('knowledge.documents.versions.reject', [$document, $this->latest($document)]))
            ->assertRedirect(route('knowledge.documents.index'));

        Tenancy::for($this->account, function () {
            // Only the row is left: no content, no file, not listed.
            $this->assertSame(ProcessingStatus::Rejected, DocumentVersion::query()->sole()->status);
            $this->assertSame(0, Document::query()->listed()->count());
            $this->assertSame(0, KnowledgeChunk::query()->count());
            $this->assertSame(0, KnowledgeFact::query()->count());
        });
        Storage::disk('knowledge')->assertMissing($path);

        $this->actingAs($this->admin)
            ->get(route('knowledge.documents.index'))
            ->assertInertia(fn (Assert $page) => $page->has('documents', 0));
    }

    public function test_uploading_a_rejected_file_again_is_a_new_decision(): void
    {
        $document = $this->upload('v1');
        $this->actingAs($this->admin)->post(route('knowledge.documents.versions.reject', [$document, $this->latest($document)]));

        $this->upload('v1');

        Tenancy::for($this->account, function () {
            $version = DocumentVersion::query()->sole();
            $this->assertSame(ProcessingStatus::Review, $version->status);
            $this->assertSame(1, Document::query()->listed()->count());
        });
    }

    public function test_rejecting_a_new_version_leaves_the_published_one_untouched(): void
    {
        $document = $this->upload('v1');
        $this->approve($document);
        $facts = Tenancy::for($this->account, fn () => KnowledgeFact::query()->count());

        $this->fakePoppler(str_replace('vóór 09:00 uur telefonisch', 'vóór 08:30 uur telefonisch', $this->xml));
        $this->actingAs($this->admin)->post(route('knowledge.documents.versions.store', $document), [
            'file' => UploadedFile::fake()->createWithContent('handboek.pdf', '%PDF-1.4 v2'),
        ]);

        $this->actingAs($this->admin)
            ->post(route('knowledge.documents.versions.reject', [$document, $this->latest($document)]))
            ->assertRedirect(route('knowledge.documents.show', $document));

        Tenancy::for($this->account, function () use ($document, $facts) {
            $this->assertSame(ProcessingStatus::Rejected, DocumentVersion::query()->where('version_number', 2)->sole()->status);
            $this->assertSame(1, $document->fresh()?->currentVersion?->version_number);
            $this->assertSame($facts, KnowledgeFact::query()->where('status', FactStatus::Core)->count());
            $this->assertStringContainsString('09:00', $this->search()->passages[0]->text ?? '');
        });
    }

    public function test_a_decision_is_made_once(): void
    {
        $document = $this->upload('v1');
        $version = $this->latest($document);

        $this->approve($document)->assertRedirect();
        $this->actingAs($this->admin)->post(route('knowledge.documents.versions.approve', [$document, $version]))->assertStatus(409);
        $this->actingAs($this->admin)->post(route('knowledge.documents.versions.reject', [$document, $version]))->assertStatus(409);
    }

    public function test_members_who_are_not_admins_cannot_decide(): void
    {
        $document = $this->upload('v1');
        $member = User::factory()->withAccount($this->account, isAdmin: false)->create();

        $this->actingAs($member)->post(route('knowledge.documents.versions.approve', [$document, $this->latest($document)]))->assertForbidden();
        $this->actingAs($member)->post(route('knowledge.documents.versions.reject', [$document, $this->latest($document)]))->assertForbidden();

        $this->assertSame(ProcessingStatus::Review, Tenancy::for($this->account, fn () => DocumentVersion::query()->sole()->status));
    }

    public function test_the_top_bar_counts_versions_waiting_for_approval_for_admins(): void
    {
        $this->upload('v1');

        $this->actingAs($this->admin)
            ->get(route('knowledge'))
            ->assertInertia(fn (Assert $page) => $page->where('knowledge.awaitingApproval', 1));

        $member = User::factory()->withAccount($this->account, isAdmin: false)->create();
        $this->actingAs($member)
            ->get(route('knowledge'))
            ->assertInertia(fn (Assert $page) => $page->where('knowledge.awaitingApproval', 0));
    }

    private function upload(string $body): Document
    {
        $this->actingAs($this->admin)->post(route('knowledge.documents.store'), [
            'file' => UploadedFile::fake()->createWithContent('handboek.pdf', '%PDF-1.4 '.$body),
            'type' => 'handbook',
            'is_core' => true,
            'language' => 'nl',
        ])->assertSessionHasNoErrors();

        return Tenancy::for($this->account, fn () => Document::query()->latest('id')->firstOrFail());
    }

    private function approve(Document $document): TestResponse
    {
        return $this->actingAs($this->admin)->post(route('knowledge.documents.versions.approve', [$document, $this->latest($document)]));
    }

    private function latest(Document $document): int
    {
        return (int) Tenancy::for($this->account, fn () => $document->latestVersion()->value('id'));
    }

    private function search(): SearchResult
    {
        return app(KnowledgeSearch::class)->search(new SearchQuery('ziekmelding telefonisch'));
    }

    private function fakePoppler(string $xml): void
    {
        Process::fake([
            '*pdfinfo*' => Process::result("Pages: 2\n"),
            '*pdftohtml*' => Process::result($xml),
        ]);
    }
}
