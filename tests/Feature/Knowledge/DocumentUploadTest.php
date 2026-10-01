<?php

namespace Tests\Feature\Knowledge;

use App\Enums\ProcessingStatus;
use App\Facades\Tenancy;
use App\Jobs\Knowledge\ProcessDocumentVersion;
use App\Models\Account;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('knowledge');
        Queue::fake();

        $this->account = Account::factory()->create();
        $this->admin = User::factory()->withAccount($this->account)->create();
    }

    public function test_an_admin_uploads_a_pdf_and_processing_is_queued(): void
    {
        $this->actingAs($this->admin)
            ->post(route('knowledge.documents.store'), [
                'file' => $this->pdf('personeelshandboek_2026.pdf', 'handboek v1'),
                'type' => 'handbook',
                'is_core' => true,
                'language' => 'nl',
            ])
            ->assertRedirect(route('knowledge.documents.index'))
            ->assertSessionHasNoErrors();

        Tenancy::for($this->account, function () {
            $document = Document::query()->with('currentVersion')->sole();
            $version = $document->currentVersion;

            $this->assertSame('Personeelshandboek 2026', $document->title);
            $this->assertTrue($document->is_core);
            $this->assertNotNull($version);
            $this->assertSame(1, $version->version_number);
            $this->assertSame(ProcessingStatus::Queued, $version->status);
            $this->assertSame(DocumentVersion::PDF, $version->mime_type);
            $this->assertSame(hash('sha256', '%PDF-1.4 handboek v1'), $version->sha256);
            $this->assertSame($this->admin->id, $version->uploaded_by_user_id);
            Storage::disk('knowledge')->assertExists($version->path);
            $this->assertStringStartsWith("accounts/{$this->account->id}/documents/{$document->id}/v1/", $version->path);
        });

        Queue::assertPushed(ProcessDocumentVersion::class, fn (ProcessDocumentVersion $job) => $job->tenantAccountId === $this->account->id);
    }

    public function test_the_same_file_is_refused_a_second_time(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('knowledge.documents.store'), $this->payload($this->pdf('a.pdf', 'same')))->assertSessionHasNoErrors();

        $this->post(route('knowledge.documents.store'), $this->payload($this->pdf('b.pdf', 'same')))
            ->assertSessionHasErrors(['file' => __('This file has already been uploaded as :title (version :version).', ['title' => 'A', 'version' => 1])]);

        $this->assertSame(1, Tenancy::for($this->account, fn () => DocumentVersion::query()->count()));
        Queue::assertPushed(ProcessDocumentVersion::class, 1);
    }

    public function test_the_same_file_may_exist_in_another_account(): void
    {
        $other = Account::factory()->create();
        Tenancy::for($other, fn () => DocumentVersion::factory()->create(['sha256' => hash('sha256', '%PDF-1.4 shared')]));

        $this->actingAs($this->admin)
            ->post(route('knowledge.documents.store'), $this->payload($this->pdf('shared.pdf', 'shared')))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Tenancy::for($this->account, fn () => DocumentVersion::query()->count()));
    }

    public function test_only_pdf_and_docx_are_accepted(): void
    {
        $this->actingAs($this->admin)
            ->post(route('knowledge.documents.store'), $this->payload(UploadedFile::fake()->createWithContent('notes.txt', 'plain text')))
            ->assertSessionHasErrors('file');

        $this->actingAs($this->admin)
            ->post(route('knowledge.documents.store'), $this->payload(UploadedFile::fake()->create('big.pdf', 60_000, 'application/pdf')))
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Tenancy::for($this->account, fn () => Document::query()->count()));
    }

    public function test_a_new_version_becomes_current_and_is_processed(): void
    {
        $document = Tenancy::for($this->account, fn () => Document::factory()->withVersion()->create());

        $this->actingAs($this->admin)
            ->post(route('knowledge.documents.versions.store', $document), [
                'file' => $this->pdf('handboek-v2.pdf', 'handboek v2'),
            ])
            ->assertSessionHasNoErrors();

        Tenancy::for($this->account, function () use ($document) {
            $document->refresh();

            $this->assertSame(2, $document->currentVersion?->version_number);
            $this->assertSame(2, $document->versions()->count());
        });

        Queue::assertPushed(ProcessDocumentVersion::class, 1);
    }

    public function test_members_who_are_not_admins_cannot_upload(): void
    {
        $member = User::factory()->withAccount($this->account, isAdmin: false)->create();

        $this->actingAs($member)
            ->post(route('knowledge.documents.store'), $this->payload($this->pdf('a.pdf', 'member')))
            ->assertForbidden();

        $this->actingAs($member)->get(route('knowledge.documents.index'))->assertOk();
    }

    public function test_removing_a_document_deletes_its_files(): void
    {
        $this->actingAs($this->admin)->post(route('knowledge.documents.store'), $this->payload($this->pdf('a.pdf', 'remove me')));

        $version = Tenancy::for($this->account, fn () => DocumentVersion::query()->sole());

        $this->delete(route('knowledge.documents.destroy', $version->document_id))
            ->assertRedirect(route('knowledge.documents.index'));

        Storage::disk('knowledge')->assertMissing($version->path);
        $this->assertSame(0, Tenancy::for($this->account, fn () => Document::query()->count()));
    }

    public function test_the_original_is_served_inline(): void
    {
        $this->actingAs($this->admin)->post(route('knowledge.documents.store'), $this->payload($this->pdf('a.pdf', 'inline')));

        $version = Tenancy::for($this->account, fn () => DocumentVersion::query()->sole());

        $this->get(route('knowledge.documents.versions.file', [$version->document_id, $version->id]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(UploadedFile $file): array
    {
        return ['file' => $file, 'type' => 'handbook', 'is_core' => true, 'language' => 'nl'];
    }

    private function pdf(string $name, string $body): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, '%PDF-1.4 '.$body);
    }
}
