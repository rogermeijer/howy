<?php

namespace Tests\Feature\Knowledge;

use App\Enums\FactStatus;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Document;
use App\Models\DocumentSection;
use App\Models\DocumentVersion;
use App\Models\Email;
use App\Models\KnowledgeFact;
use App\Models\User;
use App\Services\Knowledge\Ai\Agents\FactConflictChecker;
use App\Services\Knowledge\Ai\Agents\SectionEnricher;
use App\Services\Mail\Interpretation\MailFactWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\FakesKnowledgeAi;
use Tests\TestCase;

/**
 * Before a document is approved, each of its new facts is held against what
 * the knowledge base knows, in other documents and in mail, published or
 * still waiting: the same claim is known, a contradicting one is shown side
 * by side, and the reviewer decides which holds.
 */
class FactReconciliationTest extends TestCase
{
    use FakesKnowledgeAi, RefreshDatabase;

    private const string LEAVE = 'Medewerkers hebben recht op 25 vakantiedagen per jaar.';

    private const string SICK_OLD = 'Ziekmelden gebeurt vóór 09:00 uur telefonisch bij de leidinggevende.';

    private const string SICK_NEW = 'Ziekmelden gebeurt vóór 08:00 uur telefonisch bij de leidinggevende.';

    private Account $account;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('knowledge');
        Process::fake([
            '*pdfinfo*' => Process::result("Pages: 2\n"),
            '*pdftohtml*' => Process::result((string) file_get_contents(base_path('tests/Fixtures/Knowledge/personeelshandboek.pdf.xml'))),
        ]);
        $this->fakeKnowledgeAi();

        // The new handbook says two things: one known word for word, one that
        // contradicts what another document says.
        SectionEnricher::fake(function (string $prompt): array {
            preg_match('/Section: ([^\n]+)/', $prompt, $section);
            $heading = trim((string) last(explode(' › ', $section[1] ?? '')));

            $facts = match ($heading) {
                '2.1 Vakantiedagen' => [['statement' => self::LEAVE, 'subject' => 'verlof', 'valid_from' => null, 'confidence' => 0.9]],
                '3 Ziekmelding' => [['statement' => self::SICK_NEW, 'subject' => 'ziekmelding', 'valid_from' => null, 'confidence' => 0.9]],
                default => [],
            };

            return ['summary' => 'Samenvatting.', 'facts' => $facts];
        })->preventStrayPrompts();

        $this->account = Account::factory()->create(['locale' => 'nl']);
        $this->admin = User::factory()->withAccount($this->account)->create();
    }

    public function test_new_facts_are_marked_known_or_conflicting_before_approval(): void
    {
        [$leave, $sick] = $this->knownFacts();
        $this->fakeConflictChecker($sick);

        $document = $this->upload();

        Tenancy::for($this->account, function () use ($leave, $sick) {
            $duplicate = KnowledgeFact::query()->where('statement', self::LEAVE)->where('status', FactStatus::Proposed)->sole();
            $this->assertSame('duplicate', $duplicate->review_verdict);
            $this->assertSame($leave->id, $duplicate->related_fact_id);

            $conflict = KnowledgeFact::query()->where('statement', self::SICK_NEW)->sole();
            $this->assertSame('conflict', $conflict->review_verdict);
            $this->assertSame($sick->id, $conflict->related_fact_id);
            $this->assertSame('Het tijdstip is vervroegd van 09:00 naar 08:00.', $conflict->review_explanation);
        });

        // Only the claim with a candidate went to the model.
        FactConflictChecker::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, self::SICK_NEW) && ! str_contains($prompt->prompt, self::LEAVE));

        $this->actingAs($this->admin)
            ->get(route('knowledge.documents.show', $document))
            ->assertInertia(fn (Assert $page) => $page
                ->where('review.facts.duplicate', 1)
                ->where('review.facts.conflict', 1)
                ->where('review.conflicts.0.statement', self::SICK_NEW)
                ->where('review.conflicts.0.existing.statement', self::SICK_OLD)
                ->where('review.conflicts.0.existing.source.type', 'document'));

        // Per section too, each waiting fact says how it compares.
        $section = Tenancy::for($this->account, fn () => KnowledgeFact::query()->where('statement', self::SICK_NEW)->value('section_id'));
        $this->actingAs($this->admin)
            ->get(route('knowledge.documents.show', ['document' => $document, 'section' => $section]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('section.facts.0.verdict', 'conflict')
                ->where('section.facts.0.known.statement', self::SICK_OLD)
                ->where('section.facts.0.known.source.title', 'Arbobeleid')
                ->where('section.facts.0.explanation', 'Het tijdstip is vervroegd van 09:00 naar 08:00.'));
    }

    public function test_approving_without_choosing_for_every_conflict_is_refused(): void
    {
        [, $sick] = $this->knownFacts();
        $this->fakeConflictChecker($sick);
        $document = $this->upload();

        $version = Tenancy::for($this->account, fn () => $document->latestVersion()->value('id'));
        $this->actingAs($this->admin)
            ->post(route('knowledge.documents.versions.approve', [$document, $version]))
            ->assertSessionHasErrors('conflicts');

        Tenancy::for($this->account, fn () => $this->assertNull(DocumentVersion::query()->whereKey($version)->value('approved_at')));
    }

    public function test_choosing_the_new_fact_replaces_the_contradicted_one(): void
    {
        [, $sick] = $this->knownFacts();
        $this->fakeConflictChecker($sick);
        $document = $this->upload();
        $conflictId = Tenancy::for($this->account, fn () => KnowledgeFact::query()->where('statement', self::SICK_NEW)->value('id'));

        $this->choose($document, $conflictId, 'new');
        $this->approve($document);

        Tenancy::for($this->account, function () use ($sick) {
            $new = KnowledgeFact::query()->where('statement', self::SICK_NEW)->sole();
            $this->assertSame(FactStatus::Core, $new->status);

            $sick->refresh();
            $this->assertSame(FactStatus::Expired, $sick->status);
            $this->assertSame($new->id, $sick->superseded_by_id);
        });
    }

    public function test_the_reviewer_can_keep_the_known_fact(): void
    {
        [, $sick] = $this->knownFacts();
        $this->fakeConflictChecker($sick);
        $document = $this->upload();
        $conflictId = Tenancy::for($this->account, fn () => KnowledgeFact::query()->where('statement', self::SICK_NEW)->value('id'));

        $this->choose($document, $conflictId, 'keep');
        $this->approve($document);

        Tenancy::for($this->account, function () use ($sick) {
            $this->assertFalse(KnowledgeFact::query()->where('statement', self::SICK_NEW)->exists());
            $this->assertSame(FactStatus::Core, $sick->refresh()->status);
        });
    }

    public function test_a_claim_still_waiting_in_a_mail_counts_as_known(): void
    {
        $this->fakeConflictChecker(null);

        // Proposed from a mail, not approved yet.
        $pending = Tenancy::for($this->account, function () {
            $email = Email::factory()->create(['subject' => 'Verlof 2027']);

            return app(MailFactWriter::class)->write($email, [['statement' => self::LEAVE, 'subject' => 'verlof', 'valid_from' => null, 'flag' => null]], FactStatus::Proposed)[0];
        });

        $document = $this->upload();

        Tenancy::for($this->account, function () use ($pending) {
            $duplicate = KnowledgeFact::query()->where('statement', self::LEAVE)->where('source_type', 'document_version')->sole();
            $this->assertSame('duplicate', $duplicate->review_verdict);
            $this->assertSame($pending->id, $duplicate->related_fact_id);
        });

        $this->actingAs($this->admin)
            ->get(route('knowledge.documents.show', $document))
            ->assertInertia(fn (Assert $page) => $page
                ->where('review.duplicates.0.existing.status', 'proposed')
                ->where('review.duplicates.0.existing.source.type', 'email'));
    }

    /**
     * Published facts in another document.
     *
     * @return array{0: KnowledgeFact, 1: KnowledgeFact}
     */
    private function knownFacts(): array
    {
        return Tenancy::for($this->account, function () {
            $other = Document::factory()->withVersion()->create(['title' => 'Arbobeleid']);
            $section = DocumentSection::factory()->create(['document_id' => $other->id, 'document_version_id' => $other->current_version_id]);

            return array_map(fn (array $fact): KnowledgeFact => KnowledgeFact::factory()->create([
                'section_id' => $section->id,
                'statement' => $fact[0],
                'subject' => $fact[1],
                'content_hash' => MailFactWriter::hash($fact[0]),
                'embedding' => $this->fakeVector($fact[1].': '.$fact[0], (int) config('knowledge.embeddings.dimensions')),
                'embedding_model' => config('knowledge.embeddings.model'),
            ]), [[self::LEAVE, 'verlof'], [self::SICK_OLD, 'ziekmelding']]);
        });
    }

    private function fakeConflictChecker(?KnowledgeFact $contradicted): void
    {
        FactConflictChecker::fake(function (string $prompt) use ($contradicted): array {
            preg_match_all('/<statement id="(N\d+)"/', $prompt, $ids);

            return ['verdicts' => array_map(fn (string $id): array => $contradicted !== null && str_contains($prompt, '08:00')
                ? ['id' => $id, 'verdict' => 'contradicts', 'existing_fact_id' => $contradicted->id, 'explanation' => 'Het tijdstip is vervroegd van 09:00 naar 08:00.']
                : ['id' => $id, 'verdict' => 'new', 'existing_fact_id' => null, 'explanation' => ''], $ids[1])];
        })->preventStrayPrompts();
    }

    private function upload(): Document
    {
        $this->actingAs($this->admin)->post(route('knowledge.documents.store'), [
            'file' => UploadedFile::fake()->createWithContent('handboek.pdf', '%PDF-1.4 nieuw handboek'),
            'title' => 'Personeelshandboek',
            'type' => 'handbook',
            'is_core' => true,
            'language' => 'nl',
        ])->assertSessionHasNoErrors();

        return Tenancy::for($this->account, fn () => Document::query()->where('title', 'Personeelshandboek')->sole());
    }

    public function test_choices_are_kept_until_approval(): void
    {
        [, $sick] = $this->knownFacts();
        $this->fakeConflictChecker($sick);
        $document = $this->upload();
        $conflictId = Tenancy::for($this->account, fn () => KnowledgeFact::query()->where('statement', self::SICK_NEW)->value('id'));

        $this->choose($document, $conflictId, 'keep');

        // Picked up later: the choice is there, nothing is approved yet.
        $this->actingAs($this->admin)
            ->get(route('knowledge.documents.show', $document))
            ->assertInertia(fn (Assert $page) => $page
                ->where('review.conflicts.0.choice', 'keep'));

        $this->assertSame(FactStatus::Proposed, Tenancy::for($this->account, fn () => KnowledgeFact::query()->whereKey($conflictId)->value('status')));

        // A choice that does not fit the fact is refused.
        $version = Tenancy::for($this->account, fn () => $document->latestVersion()->value('id'));
        $this->actingAs($this->admin)
            ->patch(route('knowledge.documents.versions.facts.choice', [$document, $version, $conflictId]), ['choice' => 'omit'])
            ->assertSessionHasErrors('choice');

        // Only an administrator chooses.
        $member = User::factory()->withAccount($this->account, isAdmin: false)->create();
        $this->actingAs($member)
            ->patch(route('knowledge.documents.versions.facts.choice', [$document, $version, $conflictId]), ['choice' => 'new'])
            ->assertForbidden();
    }

    private function choose(Document $document, int $factId, ?string $choice): void
    {
        $version = Tenancy::for($this->account, fn () => $document->latestVersion()->value('id'));

        $this->actingAs($this->admin)
            ->patch(route('knowledge.documents.versions.facts.choice', [$document, $version, $factId]), ['choice' => $choice])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    }

    private function approve(Document $document): void
    {
        $version = Tenancy::for($this->account, fn () => $document->latestVersion()->value('id'));

        $this->actingAs($this->admin)
            ->post(route('knowledge.documents.versions.approve', [$document, $version]))
            ->assertRedirect();
    }
}
