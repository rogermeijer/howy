<?php

namespace Tests\Feature\Mail;

use App\Enums\SourceKind;
use App\Facades\Tenancy;
use App\Jobs\Mail\AddAttachmentToKnowledge;
use App\Models\Account;
use App\Models\DocumentSource;
use App\Models\DocumentVersion;
use App\Models\Email;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Another account's mail cannot have its attachments added, and the sources
 * of a document are only ever its own account's.
 */
class DocumentSourceIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_another_accounts_attachment_cannot_be_added(): void
    {
        Queue::fake();
        $theirs = Account::factory()->create();
        $email = Tenancy::for($theirs, fn () => Email::factory()->create([
            'attachments' => [['filename' => 'beleid.pdf', 'mime_type' => 'application/pdf', 'size' => 80_000, 'attachment_id' => 'att-1']],
        ]));

        $ours = Account::factory()->create();
        $admin = User::factory()->withAccount($ours)->create();

        $this->actingAs($admin)->post(route('emails.attachments.store', [$email->id, 0]))->assertNotFound();
        Queue::assertNotPushed(AddAttachmentToKnowledge::class);
    }

    public function test_sources_are_scoped_to_their_account(): void
    {
        $theirs = Account::factory()->create();
        Tenancy::for($theirs, function () {
            $version = DocumentVersion::factory()->create();
            DocumentSource::create(['document_id' => $version->document_id, 'document_version_id' => $version->id, 'kind' => SourceKind::Upload, 'filename' => 'x.pdf']);
        });

        $ours = Account::factory()->create();

        $this->assertSame(0, Tenancy::for($ours, fn () => DocumentSource::query()->count()));
        $this->assertSame(1, Tenancy::for($theirs, fn () => DocumentSource::query()->count()));
    }
}
