<?php

namespace Tests\Feature\Mail;

use App\Enums\InterpretationMode;
use App\Enums\MailboxStatus;
use App\Enums\OutboundMailKind;
use App\Enums\OutboundMailStatus;
use App\Enums\OutboundSkipReason;
use App\Enums\ReplyStatus;
use App\Enums\SenderAccess;
use App\Facades\Tenancy;
use App\Mail\Content\RenderedMail;
use App\Models\Account;
use App\Models\Colleague;
use App\Models\Email;
use App\Models\EmailInterpretation;
use App\Models\Mailbox;
use App\Models\OutboundMail;
use App\Services\Gmail\GmailClient;
use App\Services\Mail\HowyMailer;
use App\Services\Mail\Interpretation\ComposedReply;
use App\Services\Mail\Interpretation\ThreadReplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesGmailSending;
use Tests\TestCase;

class HowyMailerTest extends TestCase
{
    use FakesGmailSending;
    use RefreshDatabase;

    private Account $account;

    private Mailbox $mailbox;

    private Colleague $anna;

    protected function setUp(): void
    {
        parent::setUp();

        // A request nothing fakes fails here, rather than reaching Google.
        Http::preventStrayRequests();

        $this->account = Account::factory()->create(['timezone' => 'Europe/Amsterdam']);
        // Live: Howy's own mails go.
        $this->account->forceFill(['live_at' => now()])->save();
        $this->mailbox = Tenancy::for($this->account, fn () => Mailbox::factory()->rules(senders: [
            'hr@noordkade.nl' => SenderAccess::Ignore,
        ])->create(['email_address' => 'howy@noordkade.nl']));
        $this->anna = $this->colleague('anna@noordkade.nl');
    }

    public function test_a_mail_to_a_colleague_is_logged_and_sent_with_a_signed_reference(): void
    {
        $this->fakeGmailSend('sent-1', 'thread-9');

        $mail = $this->toColleague($this->anna, unsubscribeUrl: 'https://howy.test/unsubscribe/abc');

        $this->assertSame(OutboundMailStatus::Sent, $mail->status);
        $this->assertSame('sent-1', $mail->provider_message_id);
        $this->assertSame('thread-9', $mail->provider_thread_id);
        $this->assertMatchesRegularExpression('/^<howy\.'.$mail->id.'\.[a-z0-9]{10}\.[a-f0-9]{16}@noordkade\.nl>$/', (string) $mail->reference);
        // Gmail kept ours.
        $this->assertSame($mail->reference, $mail->message_id_header);
        $this->assertSame(1, $this->gmailSendCount());

        Http::assertSent(function (Request $request) use ($mail): bool {
            $raw = $this->raw($request);

            return str_contains($raw, 'Message-ID: '.$mail->reference)
                && str_contains($raw, 'To: anna@noordkade.nl')
                && str_contains($raw, 'From: howy@noordkade.nl')
                && str_contains($raw, 'Subject: Welkom bij Howy')
                && str_contains($raw, 'Auto-Submitted: auto-generated')
                && str_contains($raw, 'List-Unsubscribe: <https://howy.test/unsubscribe/abc>')
                && str_contains($raw, 'List-Unsubscribe-Post: List-Unsubscribe=One-Click')
                && ! isset($request['threadId']);
        });

        // The step it is in the flow is the colleague's.
        $this->assertNotNull(Tenancy::for($this->account, fn () => $this->anna->refresh()->welcomed_at));
    }

    public function test_the_message_id_gmail_sent_it_with_is_kept_when_gmail_put_its_own_in(): void
    {
        $this->fakeGmailSend('sent-1', 'thread-9', '<CAgmail123@mail.gmail.com>');

        $mail = $this->toColleague($this->anna);

        $this->assertSame('<CAgmail123@mail.gmail.com>', $mail->message_id_header);
        $this->assertStringStartsWith('<howy.', (string) $mail->reference);
    }

    public function test_a_colleague_who_may_not_get_it_is_skipped_with_why(): void
    {
        Http::fake();

        $optedOut = $this->colleague('tom@noordkade.nl');
        Tenancy::for($this->account, fn () => $optedOut->forceFill(['opted_out_at' => now()])->save());
        $off = $this->colleague('eva@noordkade.nl', receivesMail: false);
        $ignored = $this->colleague('hr@noordkade.nl');

        $this->assertSame(OutboundSkipReason::OptedOut, $this->toColleague($optedOut)->skip_reason);
        $this->assertSame(OutboundSkipReason::MailOff, $this->toColleague($off)->skip_reason);
        $this->assertSame(OutboundSkipReason::Ignored, $this->toColleague($ignored)->skip_reason);

        $this->assertSame(3, Tenancy::for($this->account, fn () => OutboundMail::query()->where('status', OutboundMailStatus::Skipped)->count()));
        $this->assertNull(Tenancy::for($this->account, fn () => $ignored->refresh()->welcomed_at));
        Http::assertNothingSent();
    }

    public function test_a_mailbox_that_needs_reconnecting_holds_the_mail_until_it_is_back(): void
    {
        $this->fakeGmailSend('sent-1');
        $this->mailbox->forceFill(['status' => MailboxStatus::NeedsReauth])->save();

        $mail = $this->toColleague($this->anna);

        $this->assertSame(OutboundMailStatus::Pending, $mail->status);
        $this->assertNull(Tenancy::for($this->account, fn () => $this->anna->refresh()->welcomed_at));
        Http::assertNothingSent();

        // Reconnected on Saturday: Howy's own mails wait for a working day.
        $this->reconnect();
        $this->travelTo(Carbon::parse('2026-11-14 11:00', 'Europe/Amsterdam'));
        $this->artisan('adoption:send')->assertSuccessful();

        $this->assertSame(OutboundMailStatus::Pending, Tenancy::for($this->account, fn () => $mail->refresh()->status));

        // Monday morning, in the account's working hours.
        $this->travelTo(Carbon::parse('2026-11-16 10:00', 'Europe/Amsterdam'));
        $this->artisan('adoption:send')->assertSuccessful();

        $this->assertSame(OutboundMailStatus::Sent, Tenancy::for($this->account, fn () => $mail->refresh()->status));
        $this->assertSame('2026-11-16', Tenancy::for($this->account, fn () => $this->anna->refresh()->welcomed_at?->toDateString()));
        $this->assertSame(1, $this->gmailSendCount());
    }

    public function test_one_who_unsubscribed_while_their_mail_waited_does_not_get_it(): void
    {
        $this->mailbox->forceFill(['status' => MailboxStatus::NeedsReauth])->save();
        $mail = $this->toColleague($this->anna);

        Tenancy::for($this->account, fn () => $this->anna->forceFill(['opted_out_at' => now()])->save());
        $this->reconnect();
        Http::fake();
        $this->travelTo(Carbon::parse('2026-11-16 10:00', 'Europe/Amsterdam'));
        $this->artisan('adoption:send')->assertSuccessful();

        $mail = Tenancy::for($this->account, fn () => $mail->refresh());
        $this->assertSame(OutboundMailStatus::Skipped, $mail->status);
        $this->assertSame(OutboundSkipReason::OptedOut, $mail->skip_reason);
        Http::assertNothingSent();
    }

    public function test_a_disconnected_mailbox_sends_nothing_again(): void
    {
        Http::fake();
        $this->mailbox->forceFill(['status' => MailboxStatus::Disconnected])->save();

        $mail = $this->toColleague($this->anna);

        $this->assertSame(OutboundMailStatus::Skipped, $mail->status);
        $this->assertSame(OutboundSkipReason::NoMailbox, $mail->skip_reason);
        Http::assertNothingSent();
    }

    public function test_a_send_gmail_refuses_is_logged_as_failed(): void
    {
        Http::fake([GmailClient::BASE_URL.'/messages/send' => Http::response(['error' => ['message' => 'Bad request']], 400)]);

        $mail = $this->toColleague($this->anna);

        $this->assertSame(OutboundMailStatus::Failed, $mail->status);
        $this->assertNotNull($mail->error);
        $this->assertNull(Tenancy::for($this->account, fn () => $this->anna->refresh()->welcomed_at));
    }

    public function test_a_reply_waiting_for_its_mailbox_goes_once_the_mailbox_is_back(): void
    {
        $this->fakeGmailSend('reply-7', 'thread-1');
        $this->mailbox->forceFill(['status' => MailboxStatus::NeedsReauth])->save();
        [$email, $interpretation] = $this->question();

        Tenancy::for($this->account, fn () => app(ThreadReplier::class)->reply($interpretation, $email, new ComposedReply('Je hebt 25 dagen.', '<p>Je hebt 25 dagen.</p>')));

        $this->assertSame(ReplyStatus::Waiting, $interpretation->reply_status);
        $mail = Tenancy::for($this->account, fn () => OutboundMail::query()->sole());
        $this->assertSame(OutboundMailKind::Answer, $mail->kind);
        $this->assertSame(OutboundMailStatus::Pending, $mail->status);
        $this->assertSame('Re: Vakantiedagen', $mail->subject);

        // Interpreted again meanwhile: the waiting reply is not doubled.
        Tenancy::for($this->account, fn () => app(ThreadReplier::class)->reply($interpretation, $email, new ComposedReply('Iets anders.', '<p>Iets anders.</p>')));
        $this->assertSame(1, Tenancy::for($this->account, fn () => OutboundMail::query()->count()));

        // A reply does not wait for working hours: Sunday night will do.
        Http::assertNothingSent();
        $this->reconnect();
        $this->travelTo(Carbon::parse('2026-11-15 23:00', 'Europe/Amsterdam'));
        $this->artisan('adoption:send')->assertSuccessful();

        $interpretation = Tenancy::for($this->account, fn () => $interpretation->refresh());
        $this->assertSame(ReplyStatus::Sent, $interpretation->reply_status);
        $this->assertSame('reply-7', $interpretation->reply_provider_message_id);

        Http::assertSent(fn (Request $request): bool => $request->url() === GmailClient::BASE_URL.'/messages/send'
            && $request['threadId'] === 'thread-1'
            && str_contains($this->raw($request), 'In-Reply-To: <question@mail.gmail.com>')
            && str_contains($this->raw($request), 'Auto-Submitted: auto-replied'));
    }

    public function test_another_accounts_waiting_mail_is_not_sent_from_here(): void
    {
        $this->mailbox->forceFill(['status' => MailboxStatus::NeedsReauth])->save();
        $this->toColleague($this->anna);

        $other = Account::factory()->create();
        Tenancy::for($other, fn () => Mailbox::factory()->create(['email_address' => 'howy@elders.nl']));

        // Only this account's mails are in sight, and each goes from its own mailbox.
        Tenancy::for($other, fn () => $this->assertSame(0, OutboundMail::query()->count()));
        $this->assertSame(1, Tenancy::for($this->account, fn () => OutboundMail::query()->count()));
    }

    /**
     * Connected again, with a token that outlasts the days these tests skip.
     */
    private function reconnect(): void
    {
        $this->mailbox->forceFill(['status' => MailboxStatus::Active, 'token_expires_at' => now()->addMonth()])->save();
    }

    private function toColleague(Colleague $colleague, ?string $unsubscribeUrl = null): OutboundMail
    {
        return Tenancy::for($this->account, fn () => app(HowyMailer::class)->toColleague(
            $colleague,
            OutboundMailKind::Welcome,
            new RenderedMail('Welkom bij Howy', '<p>Hoi!</p>', 'Hoi!'),
            unsubscribeUrl: $unsubscribeUrl,
        ));
    }

    private function colleague(string $email, bool $receivesMail = true): Colleague
    {
        // hr@ is one already: a rule of their own hangs on them.
        return Tenancy::for($this->account, fn () => tap(
            Colleague::query()->where('email', $email)->first() ?? Colleague::factory()->make(['email' => $email]),
            fn (Colleague $colleague) => $colleague->forceFill(['home_mailbox_id' => $this->mailbox->id, 'receives_mail' => $receivesMail])->save(),
        ));
    }

    /**
     * @return array{Email, EmailInterpretation}
     */
    private function question(): array
    {
        return Tenancy::for($this->account, function (): array {
            $email = Email::factory()->create([
                'mailbox_id' => $this->mailbox->id,
                'provider_thread_id' => 'thread-1',
                'message_id_header' => '<question@mail.gmail.com>',
                'subject' => 'Vakantiedagen',
                'from_email' => 'anna@noordkade.nl',
                'to' => [['name' => null, 'email' => 'howy@noordkade.nl']],
            ]);

            return [$email, EmailInterpretation::query()->create([
                'email_id' => $email->id,
                'mode' => InterpretationMode::Addressed,
                'sender_access' => SenderAccess::LearnAndAnswer,
            ])];
        });
    }

    private function raw(Request $request): string
    {
        return isset($request['raw']) ? (string) base64_decode(strtr((string) $request['raw'], '-_', '+/')) : '';
    }
}
