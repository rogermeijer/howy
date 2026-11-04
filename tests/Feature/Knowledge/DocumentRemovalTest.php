<?php

namespace Tests\Feature\Knowledge;

use App\Enums\FactSourceKind;
use App\Enums\FactStatus;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Document;
use App\Models\DocumentSection;
use App\Models\KnowledgeFact;
use App\Models\KnowledgeFactSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Removing a document is a choice about its facts. A fact stated elsewhere
 * too always stays; one stated only in this document stays or goes as the
 * remover says. There is no default.
 */
class DocumentRemovalTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private User $admin;

    private Document $document;

    private KnowledgeFact $onlyHere;

    private KnowledgeFact $confirmed;

    private KnowledgeFact $proposed;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('knowledge');
        $this->account = Account::factory()->create();
        $this->admin = User::factory()->withAccount($this->account)->create();

        Tenancy::for($this->account, function (): void {
            $this->document = Document::factory()->withVersion()->create(['title' => 'Handboek']);
            $section = DocumentSection::factory()->create(['document_id' => $this->document->id, 'document_version_id' => $this->document->current_version_id]);

            $this->onlyHere = KnowledgeFact::factory()->create(['section_id' => $section->id, 'statement' => 'Alleen in het handboek.']);
            $this->confirmed = KnowledgeFact::factory()->create(['section_id' => $section->id, 'statement' => 'Ook in het arbobeleid.']);
            $this->proposed = KnowledgeFact::factory()->create(['section_id' => $section->id, 'status' => FactStatus::Proposed]);

            $other = DocumentSection::factory()->create(['document_id' => Document::factory()->withVersion()->create(['title' => 'Arbobeleid'])->id]);
            $this->confirmed->sources()->create(['kind' => FactSourceKind::Document, 'document_id' => $other->document_id, 'document_version_id' => $other->document_version_id, 'section_id' => $other->id, 'title' => 'Arbobeleid']);
        });
    }

    public function test_the_remover_sees_what_happens_to_its_facts(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('knowledge.documents.removal', $this->document))
            ->assertOk()
            ->assertExactJson(['only' => 1, 'elsewhere' => 1]);
    }

    public function test_removing_without_a_choice_is_refused(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('knowledge.documents.destroy', $this->document))
            ->assertSessionHasErrors('facts');

        $this->assertTrue(Tenancy::for($this->account, fn () => Document::query()->whereKey($this->document->id)->exists()));
    }

    public function test_keeping_the_facts_leaves_them_naming_the_removed_document(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('knowledge.documents.destroy', $this->document), ['facts' => 'keep'])
            ->assertRedirect(route('knowledge.documents.index'));

        Tenancy::for($this->account, function () {
            $this->assertFalse(Document::query()->whereKey($this->document->id)->exists());

            $kept = $this->onlyHere->refresh();
            $this->assertSame(FactStatus::Core, $kept->status);
            $this->assertNull($kept->document_id);
            $this->assertSame('Handboek', $kept->documentTitle());

            $source = KnowledgeFactSource::query()->where('knowledge_fact_id', $kept->id)->sole();
            $this->assertFalse($source->isLive());
            $this->assertSame('Handboek', $source->title);

            $this->assertConfirmedMovedToTheOtherDocument();
            // Not knowledge yet: goes with the document either way.
            $this->assertFalse(KnowledgeFact::query()->whereKey($this->proposed->id)->exists());
        });
    }

    public function test_removing_the_facts_only_this_document_states(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('knowledge.documents.destroy', $this->document), ['facts' => 'remove'])
            ->assertRedirect(route('knowledge.documents.index'));

        Tenancy::for($this->account, function () {
            $this->assertFalse(KnowledgeFact::query()->whereKey($this->onlyHere->id)->exists());
            $this->assertFalse(KnowledgeFact::query()->whereKey($this->proposed->id)->exists());
            $this->assertConfirmedMovedToTheOtherDocument();
        });
    }

    private function assertConfirmedMovedToTheOtherDocument(): void
    {
        $confirmed = $this->confirmed->refresh();

        $this->assertSame(FactStatus::Core, $confirmed->status);
        $this->assertSame('Arbobeleid', $confirmed->documentTitle());
        $this->assertSame(['Arbobeleid'], $confirmed->sources()->pluck('title')->all());
    }
}
