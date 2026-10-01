<?php

namespace Tests\Feature\Knowledge;

use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Another account's documents are invisible: every route 404s through the
 * scoped binding, and no listing includes them.
 */
class DocumentIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Account $ours;

    private Account $theirs;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('knowledge');
        Queue::fake();

        $this->ours = Account::factory()->create();
        $this->admin = User::factory()->withAccount($this->ours)->create();
        $this->theirs = Account::factory()->create();
    }

    public function test_another_accounts_document_404s_on_every_route(): void
    {
        $document = Tenancy::for($this->theirs, fn () => Document::factory()->withVersion()->create());
        $versionId = $document->current_version_id;

        $this->actingAs($this->admin);

        $this->patch(route('knowledge.documents.update', $document->id), ['title' => 'Mine now'])->assertNotFound();
        $this->delete(route('knowledge.documents.destroy', $document->id))->assertNotFound();
        $this->post(route('knowledge.documents.reprocess', $document->id))->assertNotFound();
        $this->post(route('knowledge.documents.versions.store', $document->id), [
            'file' => UploadedFile::fake()->createWithContent('x.pdf', '%PDF-1.4 x'),
        ])->assertNotFound();
        $this->get(route('knowledge.documents.versions.file', [$document->id, $versionId]))->assertNotFound();

        $this->assertSame($document->title, Tenancy::for($this->theirs, fn () => $document->fresh()?->title));
        Queue::assertNothingPushed();
    }

    public function test_a_version_cannot_be_reached_through_another_document(): void
    {
        [$mine, $other] = Tenancy::for($this->ours, fn () => [
            Document::factory()->withVersion()->create(),
            Document::factory()->withVersion()->create(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('knowledge.documents.versions.file', [$mine->id, $other->current_version_id]))
            ->assertNotFound();
    }

    public function test_listings_only_show_the_current_accounts_documents(): void
    {
        Tenancy::for($this->ours, fn () => Document::factory()->withVersion()->create(['title' => 'Ours']));
        Tenancy::for($this->theirs, fn () => Document::factory()->withVersion()->create(['title' => 'Theirs']));

        $this->actingAs($this->admin);

        $this->get(route('knowledge.documents.index'))->assertInertia(fn (Assert $page) => $page
            ->component('knowledge/documents/index')
            ->has('documents', 1)
            ->where('documents.0.title', 'Ours'));

        $this->get(route('knowledge'))->assertInertia(fn (Assert $page) => $page
            ->component('knowledge/index')
            ->has('recent', 1)
            ->where('recent.0.title', 'Ours')
            ->where('documentsCount', 1));
    }
}
