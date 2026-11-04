<?php

namespace Tests\Feature\Knowledge;

use App\Enums\FactStatus;
use App\Enums\ProcessingStatus;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Document;
use App\Models\DocumentSection;
use App\Models\DocumentVersion;
use App\Models\KnowledgeFact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Another account's waiting versions can be neither approved nor rejected,
 * and are not counted in anyone else's top bar.
 */
class DocumentReviewIsolationTest extends TestCase
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

    public function test_another_accounts_version_404s_and_stays_waiting(): void
    {
        $version = Tenancy::for($this->theirs, fn () => DocumentVersion::factory()->awaitingReview()->create());

        $this->actingAs($this->admin);

        $this->post(route('knowledge.documents.versions.approve', [$version->document_id, $version->id]))->assertNotFound();
        $this->post(route('knowledge.documents.versions.reject', [$version->document_id, $version->id]))->assertNotFound();

        $fact = Tenancy::for($this->theirs, fn () => KnowledgeFact::factory()->create(['section_id' => DocumentSection::factory()->create(['document_id' => $version->document_id, 'document_version_id' => $version->id])->id, 'status' => FactStatus::Proposed]));
        $this->patch(route('knowledge.documents.versions.facts.choice', [$version->document_id, $version->id, $fact->id]), ['choice' => 'omit'])->assertNotFound();
        $this->assertNull(Tenancy::for($this->theirs, fn () => $fact->fresh()?->review_choice));

        Tenancy::for($this->theirs, function () use ($version) {
            $this->assertSame(ProcessingStatus::Review, $version->fresh()?->status);
            $this->assertNull($version->fresh()?->approved_at);
        });
    }

    public function test_a_version_cannot_be_approved_through_another_document(): void
    {
        [$mine, $waiting] = Tenancy::for($this->ours, fn () => [
            Document::factory()->withVersion()->create(),
            DocumentVersion::factory()->awaitingReview()->create(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('knowledge.documents.versions.approve', [$mine->id, $waiting->id]))
            ->assertNotFound();
    }

    public function test_only_our_own_waiting_versions_are_counted(): void
    {
        Tenancy::for($this->theirs, fn () => DocumentVersion::factory()->count(2)->awaitingReview()->create());
        Tenancy::for($this->ours, fn () => DocumentVersion::factory()->awaitingReview()->create());

        $this->actingAs($this->admin)
            ->get(route('knowledge'))
            ->assertInertia(fn (Assert $page) => $page->where('knowledge.awaitingApproval', 1));
    }
}
