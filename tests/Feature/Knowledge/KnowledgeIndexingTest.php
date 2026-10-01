<?php

namespace Tests\Feature\Knowledge;

use App\Enums\ProcessingStatus;
use App\Enums\ProcessingStep;
use App\Enums\StepStatus;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\AiUsageRecord;
use App\Models\DocumentProcessingStep;
use App\Models\DocumentVersion;
use App\Models\KnowledgeChunk;
use App\Models\User;
use App\Services\Knowledge\Ai\Agents\ContextLineWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;
use Tests\Concerns\FakesKnowledgeAi;
use Tests\TestCase;

/**
 * Context lines and embeddings: the AI half of the pipeline, against a fake
 * provider.
 */
class KnowledgeIndexingTest extends TestCase
{
    use FakesKnowledgeAi, RefreshDatabase;

    private Account $account;

    private User $admin;

    private string $xml;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('knowledge');

        $this->account = Account::factory()->create(['locale' => 'nl']);
        $this->admin = User::factory()->withAccount($this->account)->create();
        $this->xml = (string) file_get_contents(base_path('tests/Fixtures/Knowledge/personeelshandboek.pdf.xml'));
    }

    public function test_chunks_get_context_lines_and_embeddings_and_become_searchable(): void
    {
        $this->fakeKnowledgeAi();
        $this->fakePoppler($this->xml);

        $this->upload('v1');

        Tenancy::for($this->account, function () {
            $version = DocumentVersion::query()->sole();
            $this->assertSame(ProcessingStatus::Searchable, $version->status);
            $this->assertNotNull($version->processed_at);

            $chunks = KnowledgeChunk::query()->get();
            $this->assertCount(10, $chunks);
            $this->assertTrue($chunks->every(fn (KnowledgeChunk $chunk) => $chunk->is_current));
            $this->assertTrue($chunks->every(fn (KnowledgeChunk $chunk) => $chunk->context === "Context voor fragment {$chunk->id}."));
            $this->assertTrue($chunks->every(fn (KnowledgeChunk $chunk) => count($chunk->embedding ?? []) === 1024));
            $this->assertSame('text-embedding-3-large', $chunks->first()?->embedding_model);

            $steps = DocumentProcessingStep::query()->pluck('status', 'step');
            $this->assertSame(StepStatus::Succeeded, $steps[ProcessingStep::Contextualize->value]);
            $this->assertSame(StepStatus::Succeeded, $steps[ProcessingStep::Embed->value]);

            // Usage is accounted per step and to this version.
            $this->assertSame(['context', 'embed'], AiUsageRecord::query()->distinct()->orderBy('step')->pluck('step')->all());
            $this->assertTrue(AiUsageRecord::query()->get()->every(fn (AiUsageRecord $record) => $record->subject_id === $version->id));
        });

        // The embedded text carries the document title and heading path.
        Embeddings::assertGenerated(fn (EmbeddingsPrompt $prompt) => $prompt->dimensions === 1024
            && $prompt->contains("Personeelshandboek Noordkade › 3 Ziekmelding\nContext voor fragment"));

        // The whole document is the prefix of every context request.
        ContextLineWriter::assertPrompted(fn ($prompt) => str_starts_with($prompt->prompt, '<document title=')
            && str_contains($prompt->prompt, 'Een ziekmelding per e-mail of WhatsApp is niet voldoende.'));
    }

    public function test_a_new_version_takes_over_search_and_only_new_chunks_are_paid_for(): void
    {
        $this->fakeKnowledgeAi();
        $this->fakePoppler($this->xml);
        $this->upload('v1');

        $this->fakePoppler(str_replace('vóór 09:00 uur telefonisch', 'vóór 08:30 uur telefonisch', $this->xml));
        $documentId = Tenancy::for($this->account, fn () => DocumentVersion::query()->sole()->document_id);

        $this->actingAs($this->admin)->post(route('knowledge.documents.versions.store', $documentId), [
            'file' => UploadedFile::fake()->createWithContent('handboek.pdf', '%PDF-1.4 v2'),
        ])->assertSessionHasNoErrors();

        Tenancy::for($this->account, function () {
            [$v1, $v2] = DocumentVersion::query()->orderBy('version_number')->get()->all();

            $this->assertSame(0, KnowledgeChunk::query()->where('source_id', $v1->id)->where('is_current', true)->count());
            $this->assertSame(10, KnowledgeChunk::query()->where('source_id', $v2->id)->where('is_current', true)->count());

            $embed = DocumentProcessingStep::query()->where('document_version_id', $v2->id)->where('step', ProcessingStep::Embed)->sole();
            $this->assertSame(1, $embed->meta['embedded'] ?? null);
            $context = DocumentProcessingStep::query()->where('document_version_id', $v2->id)->where('step', ProcessingStep::Contextualize)->sole();
            $this->assertSame(1, $context->meta['contextualized'] ?? null);
        });
    }

    public function test_without_an_ai_provider_documents_are_still_searchable_by_text(): void
    {
        $this->fakePoppler($this->xml);

        $this->upload('no ai');

        Tenancy::for($this->account, function () {
            $this->assertSame(ProcessingStatus::Searchable, DocumentVersion::query()->sole()->status);
            $this->assertTrue(KnowledgeChunk::query()->get()->every(fn (KnowledgeChunk $chunk) => $chunk->is_current && $chunk->embedding === null));
            $this->assertSame(
                [StepStatus::Skipped, StepStatus::Skipped],
                DocumentProcessingStep::query()->whereIn('step', ['contextualize', 'embed'])->pluck('status')->all(),
            );
            $this->assertSame(0, AiUsageRecord::query()->count());
        });
    }

    private function fakePoppler(string $xml): void
    {
        Process::fake([
            '*pdfinfo*' => Process::result("Pages: 2\n"),
            '*pdftohtml*' => Process::result($xml),
        ]);
    }

    private function upload(string $body): void
    {
        $this->actingAs($this->admin)->post(route('knowledge.documents.store'), [
            'file' => UploadedFile::fake()->createWithContent('handboek.pdf', '%PDF-1.4 '.$body),
            'type' => 'handbook',
            'is_core' => true,
            'language' => 'nl',
        ])->assertSessionHasNoErrors();
    }
}
