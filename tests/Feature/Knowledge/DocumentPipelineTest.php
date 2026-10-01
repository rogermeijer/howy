<?php

namespace Tests\Feature\Knowledge;

use App\Enums\ProcessingStatus;
use App\Enums\ProcessingStep;
use App\Enums\SectionChange;
use App\Enums\StepStatus;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\DocumentProcessingStep;
use App\Models\DocumentSection;
use App\Models\DocumentVersion;
use App\Models\KnowledgeChunk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Upload to sections and chunks, with the CLI tools faked: the queue runs
 * synchronously in tests, so the whole chain runs inside the request.
 */
class DocumentPipelineTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('knowledge');

        $this->account = Account::factory()->create(['locale' => 'nl']);
        $this->admin = User::factory()->withAccount($this->account)->create();
    }

    public function test_a_pdf_is_extracted_into_sections_and_chunks(): void
    {
        $this->fakePoppler((string) file_get_contents(base_path('tests/Fixtures/Knowledge/personeelshandboek.pdf.xml')));

        $this->upload('handboek v1');

        Tenancy::for($this->account, function () {
            $version = DocumentVersion::query()->sole();

            $this->assertSame(2, $version->page_count);
            $this->assertNotNull($version->extracted_path);
            Storage::disk('knowledge')->assertExists($version->extracted_path);

            $section = DocumentSection::query()->where('heading', '3 Ziekmelding')->sole();
            $this->assertSame('Personeelshandboek Noordkade › 3 Ziekmelding', $section->heading_path);
            $this->assertSame(2, $section->page_from);
            $this->assertSame(2, $section->level);
            $this->assertSame(SectionChange::Added, $section->change_type);

            $table = KnowledgeChunk::query()->where('kind', 'table')->sole();
            $this->assertStringContainsString('| 20 jaar of langer | 3 dagen |', $table->content);
            $this->assertSame('dutch', $table->search_config);

            // Without an AI provider the AI steps are skipped, not failed.
            $steps = DocumentProcessingStep::query()->orderBy('id')->get();
            $this->assertSame(
                [ProcessingStep::Extract, ProcessingStep::Structure, ProcessingStep::Contextualize, ProcessingStep::Embed, ProcessingStep::Enrich],
                $steps->pluck('step')->all(),
            );
            $this->assertSame(
                [StepStatus::Succeeded, StepStatus::Succeeded, StepStatus::Skipped, StepStatus::Skipped, StepStatus::Skipped],
                $steps->pluck('status')->all(),
            );
            $this->assertSame(10, $steps[1]->meta['chunks'] ?? null);
        });
    }

    public function test_a_new_version_reuses_what_did_not_change(): void
    {
        $xml = (string) file_get_contents(base_path('tests/Fixtures/Knowledge/personeelshandboek.pdf.xml'));
        $this->fakePoppler($xml);
        $this->upload('handboek v1');

        // Pretend v1 was embedded, so its chunks can be reused.
        Tenancy::for($this->account, fn () => KnowledgeChunk::query()->get()->each->update([
            'embedding' => array_fill(0, (int) config('knowledge.embeddings.dimensions'), 0.01),
            'embedding_model' => 'test',
            'context' => 'Uit het personeelshandboek.',
        ]));

        $this->fakePoppler(str_replace('vóór 09:00 uur telefonisch', 'vóór 08:30 uur telefonisch', $xml));

        $documentId = Tenancy::for($this->account, fn () => DocumentVersion::query()->sole()->document_id);

        $this->actingAs($this->admin)
            ->post(route('knowledge.documents.versions.store', $documentId), [
                'file' => UploadedFile::fake()->createWithContent('handboek.pdf', '%PDF-1.4 handboek v2'),
            ])
            ->assertSessionHasNoErrors();

        Tenancy::for($this->account, function () {
            $v2 = DocumentVersion::query()->where('version_number', 2)->sole();

            $changes = DocumentSection::query()->where('document_version_id', $v2->id)->pluck('change_type', 'heading')->all();
            $this->assertSame(SectionChange::Changed, $changes['3 Ziekmelding']);
            $this->assertSame(SectionChange::Unchanged, $changes['2.1 Vakantiedagen']);

            $step = DocumentProcessingStep::query()->where('document_version_id', $v2->id)->where('step', ProcessingStep::Structure)->sole();
            $this->assertSame(9, $step->meta['reused_chunks'] ?? null);

            $changed = KnowledgeChunk::query()->where('source_id', $v2->id)->where('content', 'like', '%08:30%')->sole();
            $this->assertNull($changed->embedding);
            $this->assertNull($changed->context);
        });
    }

    public function test_a_protected_pdf_fails_with_a_clear_message_and_is_not_retried(): void
    {
        Process::fake([
            '*pdfinfo*' => Process::result("Pages: 3\nEncrypted: yes\n"),
            '*pdftohtml*' => Process::result('', 'Command Line Error: Incorrect password', 1),
        ]);

        $this->upload('protected');

        Tenancy::for($this->account, function () {
            $version = DocumentVersion::query()->sole();

            $this->assertSame(ProcessingStatus::Failed, $version->status);
            $this->assertSame(__('This PDF is protected. Save it without a password and upload it again.'), $version->error);
            $this->assertSame(StepStatus::Failed, DocumentProcessingStep::query()->sole()->status);
        });

        Process::assertRanTimes(fn ($process) => str_contains(implode(' ', (array) $process->command), 'pdftohtml'), 1);
    }

    public function test_a_document_over_the_page_limit_is_refused(): void
    {
        config(['knowledge.upload.max_pages' => 10]);
        Process::fake(['*pdfinfo*' => Process::result("Pages: 11\n")]);

        $this->upload('too long');

        Tenancy::for($this->account, fn () => $this->assertSame(
            __('This document has :pages pages; the maximum is :max.', ['pages' => 11, 'max' => 10]),
            DocumentVersion::query()->sole()->error,
        ));
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
        $this->actingAs($this->admin)
            ->post(route('knowledge.documents.store'), [
                'file' => UploadedFile::fake()->createWithContent('handboek.pdf', '%PDF-1.4 '.$body),
                'type' => 'handbook',
                'is_core' => true,
                'language' => 'nl',
            ])
            ->assertSessionHasNoErrors();
    }
}
