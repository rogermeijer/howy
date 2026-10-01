<?php

namespace Tests\Feature\Knowledge;

use App\Enums\ProcessingStatus;
use App\Enums\ProcessingStep;
use App\Enums\StepStatus;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\DocumentProcessingStep;
use App\Models\DocumentVersion;
use App\Models\KnowledgeChunk;
use App\Models\User;
use App\Services\Knowledge\Ai\Agents\ContextLineWriter;
use App\Services\Knowledge\Search\KnowledgeSearch;
use App\Services\Knowledge\Search\SearchQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Embeddings;
use RuntimeException;
use Tests\Concerns\FakesKnowledgeAi;
use Tests\TestCase;

/**
 * An AI provider that is down, rate-limited or refusing the key must never
 * make a document unfindable.
 */
class PipelineResilienceTest extends TestCase
{
    use FakesKnowledgeAi, RefreshDatabase;

    private Account $account;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('knowledge');
        $this->fakeKnowledgeAi();
        Process::fake([
            '*pdfinfo*' => Process::result("Pages: 2\n"),
            '*pdftohtml*' => Process::result((string) file_get_contents(base_path('tests/Fixtures/Knowledge/personeelshandboek.pdf.xml'))),
        ]);

        $this->account = Account::factory()->create(['locale' => 'nl']);
        $this->admin = User::factory()->withAccount($this->account)->create();
    }

    public function test_failing_context_lines_do_not_stop_indexing(): void
    {
        ContextLineWriter::fake(fn () => throw new RuntimeException('HTTP request returned status code 401'));

        $this->upload();

        Tenancy::for($this->account, function () {
            $this->assertSame(ProcessingStatus::Ready, DocumentVersion::query()->sole()->status);
            $this->assertSame(StepStatus::Failed, DocumentProcessingStep::query()->where('step', ProcessingStep::Contextualize)->sole()->status);
            $this->assertTrue(KnowledgeChunk::query()->get()->every(fn (KnowledgeChunk $chunk) => $chunk->is_current && $chunk->embedding !== null));
        });
    }

    public function test_failing_embeddings_leave_the_document_searchable_by_text(): void
    {
        Embeddings::fake(fn () => throw new RuntimeException('HTTP request returned status code 429'));

        $this->upload();

        Tenancy::for($this->account, function () {
            $version = DocumentVersion::query()->sole();
            $this->assertSame(ProcessingStatus::Searchable, $version->status);
            $this->assertNotNull($version->error);
            $this->assertTrue(KnowledgeChunk::query()->get()->every(fn (KnowledgeChunk $chunk) => $chunk->is_current));

            $result = app(KnowledgeSearch::class)->search(new SearchQuery('ziekmelding WhatsApp'));
            $this->assertFalse($result->usedVectors);
            $this->assertNotEmpty($result->passages);
        });
    }

    public function test_processing_a_searchable_version_again_keeps_it_searchable_and_reuses_its_work(): void
    {
        $this->upload();

        $versionId = Tenancy::for($this->account, fn () => DocumentVersion::query()->sole()->id);
        ContextLineWriter::fake(fn () => throw new RuntimeException('down'));
        Embeddings::fake(fn () => throw new RuntimeException('down'));

        $documentId = Tenancy::for($this->account, fn () => DocumentVersion::query()->sole()->document_id);
        $this->actingAs($this->admin)->post(route('knowledge.documents.reprocess', $documentId))->assertRedirect();

        Tenancy::for($this->account, function () use ($versionId) {
            $chunks = KnowledgeChunk::query()->where('source_id', $versionId)->get();
            $this->assertCount(10, $chunks);
            // Nothing was lost although the provider is down: all reused.
            $this->assertTrue($chunks->every(fn (KnowledgeChunk $chunk) => $chunk->is_current && $chunk->embedding !== null && $chunk->context !== null));
        });
    }

    private function upload(): void
    {
        $this->actingAs($this->admin)->post(route('knowledge.documents.store'), [
            'file' => UploadedFile::fake()->createWithContent('handboek.pdf', '%PDF-1.4 resilience'),
            'type' => 'handbook',
            'is_core' => true,
            'language' => 'nl',
        ])->assertSessionHasNoErrors();
    }
}
