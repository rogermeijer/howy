<?php

namespace Tests\Feature\Knowledge;

use App\Enums\FactStatus;
use App\Enums\KnowledgeOrigin;
use App\Enums\TopicReview;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Document;
use App\Models\DocumentSection;
use App\Models\KnowledgeFact;
use App\Models\KnowledgeTopic;
use App\Models\KnowledgeTopicLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TopicCurationTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create();
        $this->admin = User::factory()->withAccount($this->account)->create();
    }

    public function test_the_folder_page_shows_subfolders_facts_and_sources_including_subfolders(): void
    {
        [$personeel, $verlof] = Tenancy::for($this->account, function () {
            $personeel = KnowledgeTopic::factory()->create(['name' => 'Personeel']);
            $verlof = KnowledgeTopic::factory()->childOf($personeel)->create(['name' => 'Verlof']);

            $document = Document::factory()->withVersion()->create(['title' => 'Handboek']);
            $section = DocumentSection::factory()->create(['document_version_id' => $document->current_version_id, 'heading_path' => '2 Verlof', 'page_from' => 4]);
            KnowledgeFact::factory()->create(['section_id' => $section->id, 'statement' => 'Medewerkers hebben recht op 25 vakantiedagen.']);
            KnowledgeFact::factory()->create(['section_id' => $section->id, 'statement' => 'Oude regel.', 'status' => FactStatus::Expired]);
            KnowledgeTopicLink::create(['topic_id' => $verlof->id, 'linkable_type' => 'document_section', 'linkable_id' => $section->id]);

            return [$personeel, $verlof];
        });

        $this->actingAs($this->admin)
            ->get(route('knowledge.topics.show', $personeel))
            ->assertInertia(fn (Assert $page) => $page
                ->component('knowledge/topics/show')
                ->where('children.0.name', 'Verlof')
                ->where('children.0.sourcesCount', 1)
                ->has('facts', 2)
                ->where('facts.0.statement', 'Medewerkers hebben recht op 25 vakantiedagen.')
                ->where('sources.0.documentTitle', 'Handboek')
                ->where('sources.0.via', 'Verlof'));

        $this->get(route('knowledge.topics.show', [$personeel, 'direct' => 1]))
            ->assertInertia(fn (Assert $page) => $page->has('facts', 0)->has('sources', 0));

        $this->get(route('knowledge.topics.show', $verlof))
            ->assertInertia(fn (Assert $page) => $page->where('ancestors.0.name', 'Personeel')->where('sources.0.via', null));
    }

    public function test_moving_rewrites_paths_and_depths_and_respects_the_maximum(): void
    {
        [$a, $b, $c, $d] = Tenancy::for($this->account, function () {
            $a = KnowledgeTopic::factory()->create(['name' => 'A']);
            $b = KnowledgeTopic::factory()->childOf($a)->create(['name' => 'B']);
            $c = KnowledgeTopic::factory()->childOf($b)->create(['name' => 'C']);
            $d = KnowledgeTopic::factory()->create(['name' => 'D']);

            return [$a, $b, $c, $d];
        });

        $this->actingAs($this->admin);

        // B (with C below it) under D: depth 2 and 3, fine.
        $this->post(route('knowledge.topics.move', $b), ['parent_id' => $d->id])->assertSessionHasNoErrors();

        Tenancy::for($this->account, function () use ($b, $c, $d) {
            $this->assertSame("{$d->id}.{$b->id}", $b->fresh()?->path);
            $this->assertSame("{$d->id}.{$b->id}.{$c->id}", $c->fresh()?->path);
            $this->assertSame(3, $c->fresh()?->depth);
            $this->assertSame(KnowledgeOrigin::Manual, $b->fresh()?->origin);
        });

        // A under C would make A's subtree four levels deep... and C is not below A any more, but D under C is too deep.
        $this->post(route('knowledge.topics.move', $d), ['parent_id' => $c->id])->assertSessionHasErrors('parent_id');
        // Into itself.
        $this->post(route('knowledge.topics.move', $b), ['parent_id' => $c->id])->assertSessionHasErrors('parent_id');
        // To the top level.
        $this->post(route('knowledge.topics.move', $c), ['parent_id' => null])->assertSessionHasNoErrors();
        Tenancy::for($this->account, fn () => $this->assertSame([1, (string) $c->id], [$c->fresh()?->depth, $c->fresh()?->path]));
        $this->assertNotNull($a);
    }

    public function test_merging_moves_links_and_subfolders_and_removes_the_source(): void
    {
        [$source, $target, $child, $section] = Tenancy::for($this->account, function () {
            $source = KnowledgeTopic::factory()->create(['name' => 'Verlofregeling']);
            $target = KnowledgeTopic::factory()->create(['name' => 'Verlof']);
            $child = KnowledgeTopic::factory()->childOf($source)->create(['name' => 'Zwangerschapsverlof']);
            $section = DocumentSection::factory()->create();
            KnowledgeTopicLink::create(['topic_id' => $source->id, 'linkable_type' => 'document_section', 'linkable_id' => $section->id]);

            return [$source, $target, $child, $section];
        });

        $this->actingAs($this->admin)
            ->post(route('knowledge.topics.merge', $source), ['target_id' => $target->id])
            ->assertRedirect(route('knowledge.topics.show', $target));

        Tenancy::for($this->account, function () use ($source, $target, $child, $section) {
            $this->assertNull($source->fresh());
            $this->assertSame($target->id, $child->fresh()?->parent_id);
            $this->assertTrue(KnowledgeTopicLink::query()->where('topic_id', $target->id)->where('linkable_id', $section->id)->exists());
        });
    }

    public function test_removing_a_folder_hands_its_content_to_the_parent(): void
    {
        [$parent, $topic, $section] = Tenancy::for($this->account, function () {
            $parent = KnowledgeTopic::factory()->create(['name' => 'Personeel']);
            $topic = KnowledgeTopic::factory()->childOf($parent)->create(['name' => 'Overig']);
            $section = DocumentSection::factory()->create();
            KnowledgeTopicLink::create(['topic_id' => $topic->id, 'linkable_type' => 'document_section', 'linkable_id' => $section->id]);

            return [$parent, $topic, $section];
        });

        $this->actingAs($this->admin)->delete(route('knowledge.topics.destroy', $topic))->assertRedirect(route('knowledge.topics.show', $parent));

        Tenancy::for($this->account, fn () => $this->assertTrue(KnowledgeTopicLink::query()->where('topic_id', $parent->id)->where('linkable_id', $section->id)->exists()));
    }

    public function test_approving_and_creating_folders(): void
    {
        Tenancy::for($this->account, fn () => KnowledgeTopic::factory()->count(2)->create(['review_status' => TopicReview::New]));

        $this->actingAs($this->admin)->post(route('knowledge.topics.approve-all'))->assertRedirect();
        Tenancy::for($this->account, fn () => $this->assertSame(0, KnowledgeTopic::query()->where('review_status', TopicReview::New)->count()));

        $this->post(route('knowledge.topics.store'), ['name' => 'Veiligheid', 'description' => 'VCA en werkplekken'])->assertRedirect();
        Tenancy::for($this->account, function () {
            $topic = KnowledgeTopic::query()->where('name', 'Veiligheid')->sole();
            $this->assertSame(KnowledgeOrigin::Manual, $topic->origin);
            $this->assertSame(TopicReview::Approved, $topic->review_status);
            $this->assertSame(1, $topic->depth);
        });
    }

    public function test_members_can_browse_but_not_curate(): void
    {
        $member = User::factory()->withAccount($this->account, isAdmin: false)->create();
        $topic = Tenancy::for($this->account, fn () => KnowledgeTopic::factory()->create());

        $this->actingAs($member);
        $this->get(route('knowledge.topics.show', $topic))->assertOk();
        $this->patch(route('knowledge.topics.update', $topic), ['name' => 'X'])->assertForbidden();
        $this->delete(route('knowledge.topics.destroy', $topic))->assertForbidden();
    }

    public function test_another_accounts_folders_404_and_cannot_be_used_as_target(): void
    {
        $theirs = Account::factory()->create();
        [$foreign, $foreignSection] = Tenancy::for($theirs, fn () => [KnowledgeTopic::factory()->create(), DocumentSection::factory()->create()]);
        $mine = Tenancy::for($this->account, fn () => KnowledgeTopic::factory()->create());

        $this->actingAs($this->admin);

        $this->get(route('knowledge.topics.show', $foreign))->assertNotFound();
        $this->patch(route('knowledge.topics.update', $foreign), ['name' => 'X'])->assertNotFound();
        $this->post(route('knowledge.topics.move', $mine), ['parent_id' => $foreign->id])->assertNotFound();
        $this->post(route('knowledge.topics.merge', $mine), ['target_id' => $foreign->id])->assertNotFound();
        $this->post(route('knowledge.topics.store'), ['name' => 'Sub', 'parent_id' => $foreign->id])->assertNotFound();
        $this->post(route('knowledge.topics.links.store', $mine), ['section_id' => $foreignSection->id])->assertNotFound();

        $this->get(route('knowledge.topics.index'))->assertInertia(fn (Assert $page) => $page->has('topics', 1));
    }
}
