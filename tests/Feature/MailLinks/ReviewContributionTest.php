<?php

namespace Tests\Feature\MailLinks;

use App\Enums\InterpretationOutcome;
use App\Enums\MailLinkPurpose;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Email;
use App\Models\EmailInterpretation;
use App\Models\Mailbox;
use App\Models\User;
use App\Services\Knowledge\Review\ApprovalQueue;
use App\Services\Mail\MailLinks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * What Howy took from a mail, looked over by its sender from the button in
 * Saved! (04), and what an admin sees of it.
 */
class ReviewContributionTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private Email $email;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create(['locale' => 'nl']);
        $this->email = Tenancy::for($this->account, function (): Email {
            $email = Email::factory()->create([
                'mailbox_id' => Mailbox::factory()->create(['email_address' => 'howy@noordkade.nl'])->id,
                'from_email' => 'anna@noordkade.nl',
                'subject' => 'Afspraken thuiswerken',
            ]);

            EmailInterpretation::factory()->create([
                'email_id' => $email->id,
                'outcome' => InterpretationOutcome::Added,
                'needs_review' => true,
                'statements' => [
                    $this->statement('Thuiswerken mag drie dagen per week.', 'pending'),
                    $this->statement('Het kantoor is open van 8 tot 18 uur.', 'approved'),
                    $this->statement('Parkeren kan in garage Noord.', 'pending'),
                    [...$this->statement('De lunch begint om half een.', null), 'verdict' => 'duplicate'],
                ],
            ]);

            return $email;
        });
    }

    public function test_the_page_shows_what_howy_took_from_the_mail(): void
    {
        $this->get(route('mail-links.review.show', $this->token()))
            ->assertInertia(fn (Assert $page) => $page
                ->component('mail-links/review')
                ->where('subject', 'Afspraken thuiswerken')
                ->where('locale', 'nl')
                ->has('statements', 3)
                ->where('statements.0.review', 'pending')
                ->where('statements.1.review', 'approved'));
    }

    public function test_a_point_still_waiting_is_taken_out_one_already_in_waits_for_an_admin(): void
    {
        $this->post(route('mail-links.review.store', $this->token()), [
            'requests' => [
                0 => ['choice' => 'withdraw'],
                1 => ['choice' => 'correct', 'correction' => 'Het kantoor sluit om 17 uur.'],
                2 => ['choice' => 'keep'],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $interpretation = $this->interpretation();
        $statements = $interpretation->statements ?? [];

        // Taken back while it waited: out at once, by the sender, nothing left to do.
        $this->assertSame('rejected', $statements[0]['review']);
        $this->assertTrue($statements[0]['by_sender'] ?? false);
        $this->assertNull($statements[0]['reviewed_by']);
        // Already in the knowledge base: the request waits for an admin.
        $this->assertSame('approved', $statements[1]['review']);
        $this->assertSame('pending', $statements[2]['review']);
        $this->assertSame(
            [[0, 'withdraw', null, true], [1, 'correct', 'Het kantoor sluit om 17 uur.', false]],
            array_map(fn (array $request): array => [$request['statement'], $request['choice'], $request['correction'], $request['handled_at'] !== null], $interpretation->sender_feedback ?? []),
        );
        $this->assertTrue($interpretation->needs_review);
    }

    public function test_an_admin_sees_the_request_and_marks_it_done(): void
    {
        $admin = User::factory()->withAccount($this->account)->create();
        $this->post(route('mail-links.review.store', $this->token()), [
            'requests' => [1 => ['choice' => 'correct', 'correction' => 'Het kantoor sluit om 17 uur.']],
        ]);
        Tenancy::for($this->account, function (): void {
            $interpretation = $this->interpretation();
            $statements = $interpretation->statements ?? [];
            $statements[0]['review'] = 'approved';
            $statements[2]['review'] = 'approved';
            $interpretation->forceFill(['statements' => $statements])->save();
        });

        // Waiting for an admin: on the mail, in the approvals and in their count.
        $this->actingAs($admin)
            ->get(route('emails.show', $this->email))
            ->assertInertia(fn (Assert $page) => $page
                ->where('messages.0.interpretation.statements.1.senderRequest.choice', 'correct')
                ->where('messages.0.interpretation.statements.1.senderRequest.correction', 'Het kantoor sluit om 17 uur.'));

        $this->actingAs($admin)
            ->get(route('knowledge.approvals'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('mails', 1)
                ->has('mails.0.statements', 1)
                ->where('mails.0.statements.0.index', 1));

        $this->assertSame(1, Tenancy::for($this->account, fn () => app(ApprovalQueue::class)->count()));

        $this->actingAs($admin)
            ->post(route('emails.statements.handled', [$this->email, 1]))
            ->assertRedirect();

        $this->assertFalse($this->interpretation()->needs_review);
        $this->assertSame(0, Tenancy::for($this->account, fn () => app(ApprovalQueue::class)->count()));
    }

    public function test_a_correction_says_what_is_right(): void
    {
        $this->post(route('mail-links.review.store', $this->token()), ['requests' => [2 => ['choice' => 'correct', 'correction' => '']]])
            ->assertSessionHasErrors('requests.2.correction');

        $this->post(route('mail-links.review.store', $this->token()), ['requests' => [2 => ['choice' => 'correct', 'correction' => 'Garage Zuid.']]])
            ->assertSessionHasNoErrors();

        // Waiting still, so out at once; the correction stays for an admin.
        $interpretation = $this->interpretation();
        $this->assertSame('rejected', ($interpretation->statements ?? [])[2]['review']);
        $this->assertNull(($interpretation->sender_feedback ?? [])[0]['handled_at']);
    }

    public function test_an_expired_link_offers_a_new_one_and_changes_nothing(): void
    {
        $token = $this->token();
        $this->travel(49)->hours();

        $this->get(route('mail-links.review.show', $token))
            ->assertInertia(fn (Assert $page) => $page->component('mail-links/expired'));

        $this->post(route('mail-links.review.store', $token), ['requests' => [0 => ['choice' => 'withdraw']]]);
        $this->assertSame('pending', ($this->interpretation()->statements ?? [])[0]['review']);
    }

    public function test_a_link_reads_only_its_own_accounts_mail(): void
    {
        $theirs = Tenancy::for(Account::factory()->create(), function (): Email {
            $email = Email::factory()->create(['mailbox_id' => Mailbox::factory()->create(['email_address' => 'howy@elders.nl'])->id]);
            EmailInterpretation::factory()->create(['email_id' => $email->id, 'statements' => [$this->statement('Iets.', 'pending')]]);

            return $email;
        });
        $token = app(MailLinks::class)->token(MailLinkPurpose::Review, $theirs->id, 'anna@noordkade.nl', 'nl', $this->account->id);

        $this->get(route('mail-links.review.show', $token))->assertNotFound();
        $this->post(route('mail-links.review.store', $token), ['requests' => [0 => ['choice' => 'withdraw']]])->assertNotFound();
    }

    private function token(): string
    {
        return Tenancy::for($this->account, fn () => app(MailLinks::class)->token(MailLinkPurpose::Review, $this->email->id, 'anna@noordkade.nl', 'nl'));
    }

    private function interpretation(): EmailInterpretation
    {
        return Tenancy::for($this->account, fn () => EmailInterpretation::query()->where('email_id', $this->email->id)->sole());
    }

    /**
     * @return array<string, mixed>
     */
    private function statement(string $text, ?string $review): array
    {
        return [
            'statement' => $text, 'subject' => 'kantoor', 'valid_from' => null, 'verdict' => 'new',
            'fact_id' => null, 'existing_fact_id' => null, 'existing_statement' => null, 'explanation' => null,
            'flag' => null, 'review' => $review, 'reviewed_by' => null, 'reviewed_at' => null,
        ];
    }
}
