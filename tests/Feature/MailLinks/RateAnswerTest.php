<?php

namespace Tests\Feature\MailLinks;

use App\Enums\AnswerRating;
use App\Enums\InterpretationOutcome;
use App\Enums\MailboxStatus;
use App\Enums\MailLinkPurpose;
use App\Enums\OutboundMailKind;
use App\Facades\Tenancy;
use App\Jobs\Adoption\DeliverOutboundMail;
use App\Models\Account;
use App\Models\Email;
use App\Models\EmailInterpretation;
use App\Models\Mailbox;
use App\Models\OutboundMail;
use App\Models\User;
use App\Services\Mail\Interpretation\ReplyComposer;
use App\Services\Mail\MailLinks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Rating an answer (05) from the links under it.
 */
class RateAnswerTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private Mailbox $mailbox;

    private EmailInterpretation $answer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create(['name' => 'Noordkade', 'locale' => 'nl']);
        [$this->mailbox, $this->answer] = Tenancy::for($this->account, function (): array {
            $mailbox = Mailbox::factory()->create(['email_address' => 'howy@noordkade.nl']);
            $email = Email::factory()->create(['mailbox_id' => $mailbox->id, 'from_email' => 'tom@haarlem.nl', 'subject' => 'Vakantiedagen']);

            return [$mailbox, EmailInterpretation::factory()->create([
                'email_id' => $email->id,
                'outcome' => InterpretationOutcome::Answered,
                'question' => 'Hoeveel vakantiedagen heb ik?',
                'answer' => 'Je hebt recht op 25 vakantiedagen per jaar.',
            ])];
        });
    }

    public function test_an_answer_carries_three_links_to_rate_it(): void
    {
        $links = Tenancy::for($this->account, fn () => [
            'valuable' => app(MailLinks::class)->url(MailLinkPurpose::Rate, $this->answer->id, 'tom@haarlem.nl', 'nl', ['choice' => 'valuable']),
            'partly' => 'https://howy.test/partly',
            'outdated' => 'https://howy.test/outdated',
        ]);

        $reply = app(ReplyComposer::class)->answer('Hoeveel vakantiedagen heb ik?', 'Je hebt recht op 25 vakantiedagen per jaar.', [], null, 'nl', $links);

        $this->assertStringContainsString('Was dit waardevol?', $reply->text);
        $this->assertStringContainsString($links['valuable'], $reply->text);
        $this->assertStringContainsString(e($links['valuable']), $reply->html);
        $this->assertStringContainsString('choice=valuable', $links['valuable']);
    }

    public function test_opening_a_link_saves_nothing_sending_the_page_does(): void
    {
        $token = $this->token();

        $this->get(route('mail-links.rate.show', ['token' => $token, 'choice' => 'partly']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('mail-links/rate')
                ->where('choice', 'partly')
                ->where('question', 'Hoeveel vakantiedagen heb ik?')
                ->where('rated', false)
                ->where('locale', 'nl'));

        // A link scanner opening every link changes nothing.
        $this->assertNull(Tenancy::for($this->account, fn () => $this->answer->refresh()->rated_at));

        $this->post(route('mail-links.rate.store', $token), ['rating' => 'partly', 'note' => '  Het aantal voor parttimers ontbrak.  '])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $answer = Tenancy::for($this->account, fn () => $this->answer->refresh());
        $this->assertSame(AnswerRating::Partly, $answer->rating);
        $this->assertSame('Het aantal voor parttimers ontbrak.', $answer->rating_note);
        $this->assertNotNull($answer->rated_at);

        $this->post(route('mail-links.rate.store', $token), ['rating' => 'sometimes'])->assertSessionHasErrors('rating');
    }

    public function test_the_mail_page_and_the_inbox_show_the_rating(): void
    {
        Tenancy::for($this->account, fn () => $this->answer->forceFill(['rating' => AnswerRating::Valuable, 'rating_note' => 'Precies wat ik zocht.', 'rated_at' => now()])->save());
        $user = User::factory()->withAccount($this->account)->create();

        $this->actingAs($user)
            ->get(route('emails.show', $this->answer->email_id))
            ->assertInertia(fn (Assert $page) => $page
                ->where('messages.0.interpretation.rating', 'valuable')
                ->where('messages.0.interpretation.ratingNote', 'Precies wat ik zocht.'));

        $this->actingAs($user)
            ->get(route('inbox'))
            ->assertInertia(fn (Assert $page) => $page->where('threads.data.0.interpretation.rating', 'valuable'));
    }

    public function test_an_expired_link_offers_a_new_one_to_whom_it_went_a_few_times_an_hour(): void
    {
        Queue::fake();
        $token = $this->token();
        $this->travel(49)->hours();

        $this->get(route('mail-links.rate.show', $token))
            ->assertInertia(fn (Assert $page) => $page
                ->component('mail-links/expired')
                ->where('recipient', 't•••@haarlem.nl')
                ->where('canRenew', true));

        // Expired, sending the page saves nothing.
        $this->post(route('mail-links.rate.store', $token), ['rating' => 'valuable']);
        $this->assertNull(Tenancy::for($this->account, fn () => $this->answer->refresh()->rated_at));

        foreach (range(1, 4) as $ignored) {
            $this->post(route('mail-links.renew', $token))->assertRedirect();
        }

        // Three, to the address the link went to, from the mailbox the answer came from.
        Queue::assertPushed(DeliverOutboundMail::class, 3);
        Tenancy::for($this->account, function (): void {
            $links = OutboundMail::query()->where('kind', OutboundMailKind::Link)->get();

            $this->assertCount(3, $links);
            $this->assertSame(['tom@haarlem.nl'], $links->pluck('recipient')->unique()->values()->all());
            $this->assertSame([$this->mailbox->id], $links->pluck('mailbox_id')->unique()->values()->all());
            $this->assertStringContainsString('/m/rate/', $links->first()->text);
        });
    }

    public function test_with_its_mailbox_out_no_new_link_can_be_sent(): void
    {
        Queue::fake();
        $token = $this->token();
        $this->mailbox->forceFill(['status' => MailboxStatus::NeedsReauth])->save();
        $this->travel(49)->hours();

        $this->get(route('mail-links.rate.show', $token))
            ->assertInertia(fn (Assert $page) => $page->component('mail-links/expired')->where('canRenew', false));

        $this->post(route('mail-links.renew', $token))->assertRedirect();

        Queue::assertNothingPushed();
    }

    public function test_a_link_rates_only_its_own_accounts_answers(): void
    {
        $theirs = Tenancy::for(Account::factory()->create(), function (): EmailInterpretation {
            $email = Email::factory()->create(['mailbox_id' => Mailbox::factory()->create(['email_address' => 'howy@elders.nl'])->id]);

            return EmailInterpretation::factory()->create(['email_id' => $email->id, 'answer' => 'Ja.']);
        });
        $token = app(MailLinks::class)->token(MailLinkPurpose::Rate, $theirs->id, 'tom@haarlem.nl', 'nl', $this->account->id);

        $this->get(route('mail-links.rate.show', $token))->assertNotFound();
        $this->post(route('mail-links.rate.store', $token), ['rating' => 'outdated'])->assertNotFound();

        $this->assertNull(Tenancy::withoutTenancy(fn () => $theirs->refresh()->rating));
    }

    private function token(): string
    {
        return Tenancy::for($this->account, fn () => app(MailLinks::class)->token(MailLinkPurpose::Rate, $this->answer->id, 'tom@haarlem.nl', 'nl'));
    }
}
