<?php

namespace Tests\Feature\Knowledge;

use App\Enums\ProcessingStatus;
use App\Enums\ProcessingStep;
use App\Enums\StepStatus;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\AiUsageRecord;
use App\Models\DocumentProcessingStep;
use App\Models\DocumentSection;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\FakesKnowledgeAi;
use Tests\TestCase;

/**
 * Summaries and facts through the OpenAI Batch API: submitted after indexing,
 * collected by knowledge:poll-batches once the provider is done.
 */
class EnrichmentBatchTest extends TestCase
{
    use FakesKnowledgeAi, RefreshDatabase;

    /** @var list<array{custom_id: string, body: array<string, mixed>}> */
    private array $submitted = [];

    private string $batchStatus = 'in_progress';

    public function test_enrichment_is_submitted_as_a_batch_and_collected_when_done(): void
    {
        Storage::fake('knowledge');
        $this->fakeKnowledgeAi();
        config(['knowledge.enrichment.mode' => 'batch']);

        Process::fake([
            '*pdfinfo*' => Process::result("Pages: 2\n"),
            '*pdftohtml*' => Process::result((string) file_get_contents(base_path('tests/Fixtures/Knowledge/personeelshandboek.pdf.xml'))),
        ]);

        Http::fake([
            'api.openai.com/v1/files' => function (Request $request) {
                foreach ($request->data() as $part) {
                    if (($part['name'] ?? null) === 'file') {
                        foreach (array_filter(explode("\n", (string) $part['contents'])) as $line) {
                            $this->submitted[] = json_decode($line, true);
                        }
                    }
                }

                return Http::response(['id' => 'file-in']);
            },
            'api.openai.com/v1/batches' => Http::response(['id' => 'batch_1', 'status' => 'validating']),
            'api.openai.com/v1/batches/batch_1' => fn () => Http::response(['id' => 'batch_1', 'status' => $this->batchStatus, 'output_file_id' => 'file-out']),
            'api.openai.com/v1/files/file-out/content' => fn () => Http::response(implode("\n", array_map(fn (array $line): string => (string) json_encode([
                'custom_id' => $line['custom_id'],
                'response' => ['status_code' => 200, 'body' => [
                    'model' => 'gpt-6-luna',
                    'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode([
                        'summary' => 'Batchsamenvatting.',
                        'facts' => [['statement' => 'Een feit uit '.$line['custom_id'].'.', 'subject' => 'test', 'valid_from' => '2026-01-01', 'confidence' => 1]],
                    ])]]]],
                    'usage' => ['input_tokens' => 300, 'input_tokens_details' => ['cached_tokens' => 0], 'output_tokens' => 80],
                ]],
            ]), $this->submitted))),
        ]);

        $account = Account::factory()->create(['locale' => 'nl']);
        $admin = User::factory()->withAccount($account)->create();

        $this->actingAs($admin)->post(route('knowledge.documents.store'), [
            'file' => UploadedFile::fake()->createWithContent('handboek.pdf', '%PDF-1.4 batch'),
            'type' => 'handbook',
            'is_core' => true,
            'language' => 'nl',
        ])->assertSessionHasNoErrors();

        // Submitted: one Responses request per section with text, with schema.
        $this->assertCount(9, $this->submitted);
        $this->assertSame('/v1/responses', $this->submitted[0]['url'] ?? null);
        $this->assertSame('gpt-6-luna', $this->submitted[0]['body']['model']);
        $this->assertSame('json_schema', $this->submitted[0]['body']['text']['format']['type']);
        $this->assertFalse($this->submitted[0]['body']['store']);

        Tenancy::for($account, function () {
            $this->assertSame(ProcessingStatus::Enriching, DocumentVersion::query()->sole()->status);
            $step = DocumentProcessingStep::query()->where('step', ProcessingStep::Enrich)->sole();
            $this->assertSame(StepStatus::Running, $step->status);
            $this->assertSame('batch_1', $step->provider_batch_id);
        });

        // Not done yet: nothing changes.
        $this->artisan('knowledge:poll-batches')->assertSuccessful();
        Tenancy::for($account, fn () => $this->assertSame(ProcessingStatus::Enriching, DocumentVersion::query()->sole()->status));

        $this->batchStatus = 'completed';
        $this->artisan('knowledge:poll-batches')->assertSuccessful();

        Tenancy::for($account, function () {
            $this->assertSame(ProcessingStatus::Ready, DocumentVersion::query()->sole()->status);

            $section = DocumentSection::query()->where('heading', '3 Ziekmelding')->sole();
            $this->assertSame('Batchsamenvatting.', $section->summary?->text);
            $this->assertSame('2026-01-01', $section->facts()->sole()->valid_from?->toDateString());

            $this->assertSame(StepStatus::Succeeded, DocumentProcessingStep::query()->where('step', ProcessingStep::Enrich)->sole()->status);

            // Batch usage is recorded at half price.
            $batch = AiUsageRecord::query()->where('is_batch', true)->get();
            $this->assertCount(9, $batch);
            $this->assertSame((int) round((300 * 0.10 + 80 * 0.50) / 2), $batch->first()?->estimated_cost_micros);
        });
    }
}
