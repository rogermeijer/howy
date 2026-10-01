<?php

namespace Tests\Feature\Mail;

use App\Enums\FactStatus;
use App\Enums\InterpretationOutcome;
use App\Enums\InterpretationStatus;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Email;
use App\Models\EmailInterpretation;
use App\Models\KnowledgeFact;
use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\FakesKnowledgeAi;
use Tests\TestCase;

/**
 * Nothing from mail reaches the knowledge base until an administrator of the
 * account approves it; until then the mail counts as needing action.
 */
class StatementReviewTest extends TestCase
{
    use FakesKnowledgeAi, RefreshDatabase;

    private Account $account;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeKnowledgeAi();
        $this->account = Account::factory()->create(['locale' => 'nl']);
        $this->admin = User::factory()->withAccount($this->account)->create();
    }

    public function test_approving_a_new_statement_makes_its_fact_count(): void
    {
        [$email, $interpretation, $proposed] = $this->waiting();

        $this->actingAs($this->admin)
            ->post(route('emails.statements.approve', ['email' => $email, 'statement' => 0]))
            ->assertRedirect()
            ->assertInertiaFlash('toast.message', 'Toegevoegd aan de kennisbank.');

        $interpretation->refresh();
        $this->assertSame(FactStatus::Supplementary, $proposed->refresh()->status);
        $this->assertSame('approved', $interpretation->statements[0]['review'] ?? null);
        $this->assertSame($this->admin->id, $interpretation->statements[0]['reviewed_by'] ?? null);
        $this->assertFalse($interpretation->needs_review);

        // Decided once: a second decision is refused.
        $this->actingAs($this->admin)
            ->post(route('emails.statements.reject', ['email' => $email, 'statement' => 0]))
            ->assertStatus(409);
    }

    public function test_approving_a_conflict_replaces_the_fact_it_contradicts(): void
    {
        $old = Tenancy::for($this->account, fn () => KnowledgeFact::factory()->create(['statement' => 'Medewerkers hebben recht op 25 vakantiedagen.']));
        [$email, $interpretation] = $this->waiting([
            'verdict' => 'conflict',
            'statement' => 'Medewerkers hebben recht op 30 vakantiedagen.',
            'fact_id' => null,
            'existing_fact_id' => $old->id,
            'existing_statement' => $old->statement,
        ]);

        $this->actingAs($this->admin)
            ->post(route('emails.statements.approve', ['email' => $email, 'statement' => 0]))
            ->assertRedirect();

        $new = Tenancy::for($this->account, fn () => KnowledgeFact::query()->where('source_type', 'email')->sole());
        $this->assertSame('Medewerkers hebben recht op 30 vakantiedagen.', $new->statement);
        $this->assertSame(FactStatus::Supplementary, $new->status);
        $this->assertSame($new->id, $interpretation->refresh()->statements[0]['fact_id'] ?? null);

        $old->refresh();
        $this->assertSame(FactStatus::Expired, $old->status);
        $this->assertSame($new->id, $old->superseded_by_id);
        $this->assertNotNull($old->valid_until);
    }

    public function test_rejecting_keeps_it_out_of_the_knowledge_base(): void
    {
        [$email, $interpretation, $proposed] = $this->waiting();

        $this->actingAs($this->admin)
            ->post(route('emails.statements.reject', ['email' => $email, 'statement' => 0]))
            ->assertRedirect();

        $this->assertFalse(Tenancy::for($this->account, fn () => KnowledgeFact::query()->whereKey($proposed->id)->exists()));
        $interpretation->refresh();
        $this->assertSame('rejected', $interpretation->statements[0]['review'] ?? null);
        $this->assertNull($interpretation->statements[0]['fact_id'] ?? null);
        $this->assertFalse($interpretation->needs_review);
    }

    public function test_only_an_admin_of_the_account_decides(): void
    {
        [$email, $interpretation] = $this->waiting();

        $member = User::factory()->withAccount($this->account, isAdmin: false)->create();
        $this->actingAs($member)
            ->post(route('emails.statements.approve', ['email' => $email, 'statement' => 0]))
            ->assertForbidden();

        $outsider = User::factory()->withAccount()->create();
        $this->actingAs($outsider)
            ->post(route('emails.statements.approve', ['email' => $email, 'statement' => 0]))
            ->assertNotFound();

        $this->assertTrue($interpretation->refresh()->needs_review);
    }

    public function test_the_top_bar_counts_the_mails_waiting_for_review(): void
    {
        $this->waiting();
        $this->waiting();
        // Another account's mail never counts here.
        Tenancy::for(Account::factory()->create(), fn () => EmailInterpretation::factory()->create(['needs_review' => true]));

        $this->actingAs($this->admin)
            ->get(route('inbox'))
            ->assertInertia(fn (Assert $page) => $page->where('inbox.needsReview', 2));

        // Those who cannot act on it see no count.
        $member = User::factory()->withAccount($this->account, isAdmin: false)->create();
        $this->actingAs($member)
            ->get(route('inbox'))
            ->assertInertia(fn (Assert $page) => $page->where('inbox.needsReview', 0));
    }

    public function test_the_inbox_opens_a_thread_at_the_mail_waiting_for_review(): void
    {
        [$email] = $this->waiting();

        // A later message in the same thread, already dealt with.
        Tenancy::for($this->account, function () use ($email): void {
            $later = Email::factory()->create([
                'mailbox_id' => $email->mailbox_id,
                'provider_thread_id' => $email->provider_thread_id,
                'received_at' => $email->received_at?->addHour(),
            ]);
            EmailInterpretation::factory()->create(['email_id' => $later->id, 'status' => InterpretationStatus::Done, 'outcome' => InterpretationOutcome::Answered]);
        });

        $this->actingAs($this->admin)
            ->get(route('inbox'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('threads.data.0.id', $email->id)
                ->where('threads.data.0.interpretation.needsReview', true));

        $this->actingAs($this->admin)
            ->get(route('emails.show', $email))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canReview', true)
                ->where('messages.1.interpretation.statements.0.review', 'pending'));
    }

    /**
     * A mail with one statement waiting for review, and its proposed fact.
     *
     * @param  array<string, mixed>  $statement
     * @return array{0: Email, 1: EmailInterpretation, 2: KnowledgeFact|null}
     */
    private function waiting(array $statement = []): array
    {
        return Tenancy::for($this->account, function () use ($statement): array {
            $email = Email::factory()->create(['mailbox_id' => Mailbox::factory()->create()->id, 'received_at' => now()->subDay()]);
            $text = $statement['statement'] ?? 'Het kantoor is op 5 december gesloten.';

            $fact = array_key_exists('fact_id', $statement) ? null : KnowledgeFact::factory()->create([
                'section_id' => null,
                'source_type' => 'email',
                'source_id' => $email->id,
                'document_id' => null,
                'document_version_id' => null,
                'statement' => $text,
                'status' => FactStatus::Proposed,
            ]);

            $interpretation = EmailInterpretation::factory()->create([
                'email_id' => $email->id,
                'status' => InterpretationStatus::Done,
                'outcome' => InterpretationOutcome::Added,
                'language' => 'nl',
                'needs_review' => true,
                'statements' => [[
                    'statement' => $text,
                    'subject' => null,
                    'valid_from' => null,
                    'verdict' => 'new',
                    'fact_id' => $fact?->id,
                    'existing_fact_id' => null,
                    'existing_statement' => null,
                    'explanation' => null,
                    'flag' => null,
                    'review' => 'pending',
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    ...$statement,
                ]],
            ]);

            return [$email, $interpretation, $fact];
        });
    }
}
