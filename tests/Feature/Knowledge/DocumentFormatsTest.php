<?php

namespace Tests\Feature\Knowledge;

use App\Enums\ProcessingStatus;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\DocumentProcessingStep;
use App\Models\DocumentSection;
use App\Models\DocumentVersion;
use App\Models\KnowledgeChunk;
use App\Models\User;
use App\Services\Knowledge\Ai\Agents\ImageInterpreter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\Concerns\FakesKnowledgeAi;
use Tests\TestCase;

/**
 * Beyond PDF and Word: slides, spreadsheets and text through pandoc or as
 * they are, images through the vision model. Each ends up as sections and
 * chunks like any document.
 */
class DocumentFormatsTest extends TestCase
{
    use FakesKnowledgeAi, RefreshDatabase;

    private Account $account;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('knowledge');

        $this->account = Account::factory()->create(['locale' => 'nl']);
        $this->admin = User::factory()->withAccount($this->account)->create();
    }

    public function test_a_presentation_is_read_with_pandocs_pptx_reader(): void
    {
        Process::fake(['*pandoc*' => Process::result((string) file_get_contents(base_path('tests/Fixtures/Knowledge/verlofregeling.pptx.json')))]);

        $this->upload(UploadedFile::fake()->create('verlofregeling.pptx', 20, 'application/vnd.openxmlformats-officedocument.presentationml.presentation'));

        Process::assertRan(fn ($process) => in_array('--from=pptx', (array) $process->command, true));

        Tenancy::for($this->account, function () {
            $this->assertSame(ProcessingStatus::Review, DocumentVersion::query()->sole()->status);
            $this->assertTrue(DocumentSection::query()->where('heading', 'Vakantiedagen')->exists());
            $this->assertTrue(KnowledgeChunk::query()->where('content', 'like', '%26 vakantiedagen%')->exists());
        });
    }

    public function test_plain_text_needs_no_tool(): void
    {
        Process::fake();

        $this->upload(UploadedFile::fake()->createWithContent('afspraken.txt', "Sleutels liggen bij de receptie.\n\nDe alarmcode verandert elke maand."));

        Process::assertNothingRan();

        Tenancy::for($this->account, function () {
            $content = KnowledgeChunk::query()->pluck('content')->implode("\n");
            $this->assertStringContainsString('Sleutels liggen bij de receptie.', $content);
            $this->assertStringContainsString('De alarmcode verandert elke maand.', $content);
        });
    }

    public function test_an_image_is_read_by_the_vision_model(): void
    {
        $this->fakeKnowledgeAi();
        Process::fake();

        $this->upload(UploadedFile::fake()->image('whiteboard.jpg', 800, 600));

        ImageInterpreter::assertPrompted(fn (AgentPrompt $prompt) => str_contains($prompt->prompt, 'whiteboard.jpg') && count($prompt->attachments) === 1);

        Tenancy::for($this->account, function () {
            $version = DocumentVersion::query()->sole();
            $this->assertSame(ProcessingStatus::Review, $version->status);

            $chunks = KnowledgeChunk::query()->pluck('content')->implode("\n");
            $this->assertStringContainsString('whiteboard met de planning', $chunks);
            $this->assertStringContainsString('Jan bij Bakker', $chunks);

            $extract = DocumentProcessingStep::query()->where('document_version_id', $version->id)->where('step', 'extract')->sole();
            $this->assertSame('photo', $extract->meta['image_kind'] ?? null);
            $this->assertTrue($extract->meta['worth_keeping'] ?? false);
        });
    }

    public function test_without_an_ai_provider_an_image_cannot_be_read(): void
    {
        Process::fake();

        $this->upload(UploadedFile::fake()->image('whiteboard.png'));

        Tenancy::for($this->account, function () {
            $version = DocumentVersion::query()->sole();
            $this->assertSame(ProcessingStatus::Failed, $version->status);
            $this->assertSame(__('This image could not be read. Images are read by the AI provider, so one has to be configured.'), $version->error);
        });
    }

    private function upload(UploadedFile $file): void
    {
        $this->actingAs($this->admin)->post(route('knowledge.documents.store'), [
            'file' => $file,
            'type' => 'other',
            'is_core' => false,
            'language' => 'nl',
        ])->assertSessionHasNoErrors();
    }
}
