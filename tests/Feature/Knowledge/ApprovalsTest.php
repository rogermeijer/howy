<?php

namespace Tests\Feature\Knowledge;

use App\Enums\FactStatus;
use App\Enums\InterpretationOutcome;
use App\Enums\InterpretationStatus;
use App\Enums\SourceKind;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Document;
use App\Models\DocumentSource;
use App\Models\DocumentVersion;
use App\Models\Email;
use App\Models\EmailInterpretation;
use App\Models\KnowledgeFact;
use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Approval happens in one place, the knowledge base: processed documents,
 * uploaded or mailed, and what mail would add or change. A mail itself never
 * waits in the inbox.
 */
class ApprovalsTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create(['locale' => 'nl']);
        $this->admin = User::factory()->withAccount($this->account)->create();
    }

    public function test_documents_and_mail_statements_wait_in_one_place(): void
    {
        $version = $this->mailedDocument();
        $email = $this->mailWaiting('Het kantoor is op 5 december gesloten.');

        $this->actingAs($this->admin)
            ->get(route('knowledge.approvals'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('knowledge/approvals')
                ->has('documents', 1)
                ->where('documents.0.versionId', $version->id)
                ->where('documents.0.title', 'Verlofregeling 2027')
                ->where('documents.0.replaces', null)
                ->where('documents.0.sources.0.kind', 'mail')
                ->where('documents.0.sources.0.subject', 'Verlof')
                ->has('mails', 1)
                ->where('mails.0.emailId', $email->id)
                ->where('mails.0.statements.0.index', 0)
                ->where('mails.0.statements.0.statement', 'Het kantoor is op 5 december gesloten.')
                ->where('knowledge.awaitingApproval', 2));
    }

    public function test_a_new_version_says_what_it_replaces(): void
    {
        $document = Tenancy::for($this->account, fn () => Document::factory()->withVersion()->create());
        Tenancy::for($this->account, fn () => DocumentVersion::factory()->awaitingReview()->for($document)->create(['version_number' => 2]));

        $this->actingAs($this->admin)
            ->get(route('knowledge.approvals'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('documents.0.versionNumber', 2)
                ->where('documents.0.replaces', 1));
    }

    public function test_only_what_still_waits_is_listed(): void
    {
        Tenancy::for($this->account, fn () => Document::factory()->withVersion()->create());
        $this->mailWaiting('Al besloten.', review: 'approved');

        $this->actingAs($this->admin)
            ->get(route('knowledge.approvals'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('documents', 0)
                ->has('mails', 0)
                ->where('knowledge.awaitingApproval', 0));
    }

    public function test_deciding_a_statement_takes_the_mail_off_the_list(): void
    {
        $email = $this->mailWaiting('Het kantoor is op 5 december gesloten.');

        $this->actingAs($this->admin)
            ->from(route('knowledge.approvals'))
            ->post(route('emails.statements.approve', ['email' => $email, 'statement' => 0]))
            ->assertRedirect(route('knowledge.approvals'));

        $this->actingAs($this->admin)
            ->get(route('knowledge.approvals'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('mails', 0)
                ->where('knowledge.awaitingApproval', 0));
    }

    public function test_the_inbox_has_no_review_state(): void
    {
        $email = $this->mailWaiting('Het kantoor is op 5 december gesloten.');

        // A later message in the same thread, already dealt with.
        $later = Tenancy::for($this->account, function () use ($email): Email {
            $later = Email::factory()->create([
                'mailbox_id' => $email->mailbox_id,
                'provider_thread_id' => $email->provider_thread_id,
                'received_at' => $email->received_at?->addHour(),
            ]);
            EmailInterpretation::factory()->create(['email_id' => $later->id, 'status' => InterpretationStatus::Done, 'outcome' => InterpretationOutcome::Answered]);

            return $later;
        });

        $this->actingAs($this->admin)
            ->get(route('inbox'))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('inbox')
                ->where('threads.data.0.id', $later->id)
                ->missing('threads.data.0.interpretation.needsReview'));
    }

    public function test_only_administrators_approve(): void
    {
        $this->mailWaiting('Het kantoor is op 5 december gesloten.');
        $member = User::factory()->withAccount($this->account, isAdmin: false)->create();

        $this->actingAs($member)->get(route('knowledge.approvals'))->assertForbidden();

        $this->actingAs($member)
            ->get(route('knowledge'))
            ->assertInertia(fn (Assert $page) => $page->where('knowledge.awaitingApproval', 0));
    }

    /**
     * A processed attachment, new to the knowledge base.
     */
    private function mailedDocument(): DocumentVersion
    {
        return Tenancy::for($this->account, function (): DocumentVersion {
            $email = Email::factory()->create(['mailbox_id' => Mailbox::factory()->create()->id, 'subject' => 'Verlof']);
            $document = Document::factory()->create(['title' => 'Verlofregeling 2027']);
            $version = DocumentVersion::factory()->awaitingReview()->for($document)->create();

            DocumentSource::query()->create([
                'document_id' => $document->id,
                'document_version_id' => $version->id,
                'kind' => SourceKind::Mail,
                'email_id' => $email->id,
                'filename' => $version->original_filename,
            ]);

            return $version;
        });
    }

    /**
     * A mail with one statement, its proposed fact and its review state.
     */
    private function mailWaiting(string $text, string $review = 'pending'): Email
    {
        return Tenancy::for($this->account, function () use ($text, $review): Email {
            $email = Email::factory()->create(['mailbox_id' => Mailbox::factory()->create()->id, 'received_at' => now()->subDay()]);
            $fact = KnowledgeFact::factory()->create([
                'section_id' => null,
                'source_type' => 'email',
                'source_id' => $email->id,
                'document_id' => null,
                'document_version_id' => null,
                'statement' => $text,
                'status' => $review === 'pending' ? FactStatus::Proposed : FactStatus::Supplementary,
            ]);

            EmailInterpretation::factory()->create([
                'email_id' => $email->id,
                'status' => InterpretationStatus::Done,
                'outcome' => InterpretationOutcome::Added,
                'language' => 'nl',
                'needs_review' => $review === 'pending',
                'statements' => [[
                    'statement' => $text,
                    'subject' => null,
                    'valid_from' => null,
                    'verdict' => 'new',
                    'fact_id' => $fact->id,
                    'existing_fact_id' => null,
                    'existing_statement' => null,
                    'explanation' => null,
                    'flag' => null,
                    'review' => $review,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ]],
            ]);

            return $email;
        });
    }
}
