<?php

namespace Tests\Feature\Colleagues;

use App\Enums\OutboundMailKind;
use App\Enums\OutboundMailStatus;
use App\Enums\OutboundSkipReason;
use App\Facades\Tenancy;
use App\Jobs\Adoption\DeliverOutboundMail;
use App\Models\Account;
use App\Models\Colleague;
use App\Models\Email;
use App\Models\Mailbox;
use App\Models\OutboundMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SentMailPageTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private User $admin;

    private Mailbox $mailbox;

    private Colleague $anna;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create(['timezone' => 'Europe/Amsterdam']);
        $this->admin = User::factory()->withAccount($this->account)->create();

        Tenancy::for($this->account, function (): void {
            $this->mailbox = Mailbox::factory()->create(['email_address' => 'howy@noordkade.nl']);
            $this->anna = Colleague::factory()->create(['email' => 'anna@noordkade.nl', 'name' => 'Anna de Boer', 'home_mailbox_id' => $this->mailbox->id]);
        });
    }

    public function test_only_an_admin_of_the_account_sees_the_mail_log(): void
    {
        $member = User::factory()->withAccount($this->account, isAdmin: false)->create();

        $this->get(route('colleagues.sent'))->assertRedirect(route('login'));
        $this->actingAs($member)->get(route('colleagues.sent'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('colleagues.sent'))->assertOk();
    }

    public function test_the_log_shows_whether_a_mail_went_why_not_and_who_wrote_back(): void
    {
        $welcome = $this->mail(['kind' => OutboundMailKind::Welcome, 'subject' => 'Welkom bij Howy'], sent: true);
        $this->mail(['kind' => OutboundMailKind::HowItWorks, 'status' => OutboundMailStatus::Skipped, 'skip_reason' => OutboundSkipReason::OptedOut]);
        $reply = Tenancy::for($this->account, fn () => Email::factory()->create(['mailbox_id' => $this->mailbox->id, 'from_email' => 'anna@noordkade.nl']));
        Tenancy::for($this->account, fn () => $reply->outboundMail()->associate($welcome)->save());

        $this->actingAs($this->admin)
            ->get(route('colleagues.sent'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('colleagues/sent')
                ->has('mails.data', 2)
                ->where('mails.data.0.kind', 'how_it_works')
                ->where('mails.data.0.status', 'skipped')
                ->where('mails.data.0.reason', 'They unsubscribed')
                ->where('mails.data.1.subject', 'Welkom bij Howy')
                ->where('mails.data.1.status', 'sent')
                ->where('mails.data.1.colleague', ['id' => $this->anna->id, 'name' => 'Anna de Boer'])
                ->where('mails.data.1.mailbox', 'howy@noordkade.nl')
                ->where('mails.data.1.replyIds', [$reply->id]));

        // A colleague's page lists what Howy sent them.
        $this->actingAs($this->admin)
            ->get(route('colleagues.show', $this->anna))
            ->assertInertia(fn (Assert $page) => $page->has('sent', 2)->where('sent.1.id', $welcome->id));
    }

    public function test_the_log_filters_by_recipient_kind_status_and_period(): void
    {
        $this->travelTo(Carbon::parse('2026-11-02 10:00', 'Europe/Amsterdam'));
        $this->mail(['kind' => OutboundMailKind::Welcome], sent: true);
        $this->travelTo(Carbon::parse('2026-11-09 10:00', 'Europe/Amsterdam'));
        $this->mail(['kind' => OutboundMailKind::Answer, 'recipient' => 'piet@klant.nl', 'colleague_id' => null]);
        $this->mail(['kind' => OutboundMailKind::Nudge, 'status' => OutboundMailStatus::Failed, 'error' => 'Gmail said no']);

        $kinds = fn (array $query): array => collect($this->actingAs($this->admin)
            ->get(route('colleagues.sent', $query))
            ->viewData('page')['props']['mails']['data'])->pluck('kind')->sort()->values()->all();

        $this->assertSame(['answer'], $kinds(['q' => 'klant']));
        $this->assertSame(['nudge', 'welcome'], $kinds(['q' => 'Boer']));
        $this->assertSame(['welcome'], $kinds(['kind' => 'welcome']));
        $this->assertSame(['nudge'], $kinds(['status' => 'failed']));
        $this->assertSame(['welcome'], $kinds(['until' => '2026-11-05']));
        $this->assertSame(['answer', 'nudge'], $kinds(['from' => '2026-11-09', 'until' => '2026-11-09']));
        // What is not a filter is no filter.
        $this->assertCount(3, $kinds(['kind' => 'whatever', 'from' => 'yesterday']));
    }

    public function test_a_mail_shows_as_its_recipient_got_it(): void
    {
        $mail = $this->mail(['kind' => OutboundMailKind::Welcome, 'html' => '<p>Hoi <strong>Anna</strong></p>'], sent: true);
        $member = User::factory()->withAccount($this->account, isAdmin: false)->create();

        $this->actingAs($this->admin)
            ->get(route('colleagues.sent.mail', $mail))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src data:; frame-ancestors 'self'")
            ->assertSee('<p>Hoi <strong>Anna</strong></p>', escape: false);

        $this->actingAs($member)->get(route('colleagues.sent.mail', $mail))->assertForbidden();

        $theirs = Tenancy::for(Account::factory()->create(), fn () => OutboundMail::factory()->create());
        $this->actingAs($this->admin)->get(route('colleagues.sent.mail', $theirs))->assertNotFound();
    }

    public function test_one_of_howys_own_mails_can_be_sent_again_as_it_was(): void
    {
        Queue::fake();
        $welcome = $this->mail(['kind' => OutboundMailKind::Welcome, 'subject' => 'Welkom bij Howy', 'html' => '<p>Hoi Anna</p>'], sent: true);

        $this->actingAs($this->admin)
            ->get(route('colleagues.show', $this->anna))
            ->assertInertia(fn (Assert $page) => $page->where('sent.0.canResend', true));

        $this->actingAs($this->admin)->post(route('colleagues.sent.resend', $welcome))->assertRedirect();
        // Clicked twice: the first is still on its way.
        $this->actingAs($this->admin)->post(route('colleagues.sent.resend', $welcome))->assertRedirect();

        Queue::assertPushed(DeliverOutboundMail::class, 1);
        $again = Tenancy::for($this->account, fn () => OutboundMail::query()->whereKeyNot($welcome->id)->sole());
        $this->assertSame(OutboundMailStatus::Pending, $again->status);
        $this->assertSame(OutboundMailKind::Welcome, $again->kind);
        $this->assertSame('Welkom bij Howy', $again->subject);
        $this->assertSame('<p>Hoi Anna</p>', $again->html);
        $this->assertSame($this->anna->id, $again->colleague_id);
        $this->assertNull($again->reference);
        $this->assertNull($again->provider_message_id);
    }

    public function test_a_reply_or_a_mail_not_sent_is_not_sent_again(): void
    {
        Queue::fake();
        $answer = $this->mail(['kind' => OutboundMailKind::Answer], sent: true);
        $skipped = $this->mail(['kind' => OutboundMailKind::Welcome, 'status' => OutboundMailStatus::Skipped, 'skip_reason' => OutboundSkipReason::OptedOut]);
        $theirs = Tenancy::for(Account::factory()->create(), fn () => OutboundMail::factory()->sent()->create());

        $this->actingAs($this->admin)->post(route('colleagues.sent.resend', $answer))->assertRedirect();
        $this->actingAs($this->admin)->post(route('colleagues.sent.resend', $skipped))->assertRedirect();
        $this->actingAs($this->admin)->post(route('colleagues.sent.resend', $theirs))->assertNotFound();

        Queue::assertNothingPushed();
        $this->assertSame(2, Tenancy::for($this->account, fn () => OutboundMail::query()->count()));
    }

    public function test_another_accounts_mail_is_out_of_sight(): void
    {
        $this->mail(['kind' => OutboundMailKind::Welcome]);

        Tenancy::for(Account::factory()->create(), function (): void {
            $mailbox = Mailbox::factory()->create(['email_address' => 'howy@elders.nl']);
            OutboundMail::factory()->create(['mailbox_id' => $mailbox->id, 'recipient' => 'anna@noordkade.nl', 'subject' => 'Not yours']);
        });

        $this->actingAs($this->admin)
            ->get(route('colleagues.sent'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('mails.data', 1)
                ->where('mails.data.0.mailbox', 'howy@noordkade.nl'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function mail(array $attributes, bool $sent = false): OutboundMail
    {
        return Tenancy::for($this->account, function () use ($attributes, $sent): OutboundMail {
            $factory = OutboundMail::factory();

            $mail = ($sent ? $factory->sent() : $factory)->make([
                'mailbox_id' => $this->mailbox->id,
                'colleague_id' => $this->anna->id,
                'recipient' => 'anna@noordkade.nl',
                ...array_diff_key($attributes, ['error' => true]),
            ]);
            $mail->forceFill(array_intersect_key($attributes, ['error' => true]))->save();

            return $mail;
        });
    }
}
