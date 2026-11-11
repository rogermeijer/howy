<?php

namespace Tests\Feature\Adoption;

use App\Enums\EmailSource;
use App\Enums\OutboundMailKind;
use App\Enums\OutboundMailStatus;
use App\Facades\Tenancy;
use App\Jobs\Adoption\DeliverOutboundMail;
use App\Models\Account;
use App\Models\Colleague;
use App\Models\Mailbox;
use App\Models\OutboundMail;
use App\Models\User;
use App\Services\Adoption\Welcomes;
use App\Services\Gmail\StoreGmailMessage;
use App\Services\Mail\HowyMailer;
use App\Services\Mailboxes\MailboxRuleEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\FakesGmailSending;
use Tests\Concerns\FakesKnowledgeAi;
use Tests\Fixtures\GmailFixtures;
use Tests\TestCase;

class WelcomeMailTest extends TestCase
{
    use FakesGmailSending;
    use FakesKnowledgeAi;
    use RefreshDatabase;

    private Account $account;

    private User $admin;

    private Mailbox $mailbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeGmailSend('sent-1', 'thread-1');
        Http::preventStrayRequests();
        config(['inertia.ssr.enabled' => false]);
        $this->fakeKnowledgeAi();
        $this->fakeMailAi(['intent' => 'other', 'question' => '']);

        $this->account = Account::factory()->create(['locale' => 'nl']);
        $this->account->forceFill(['live_at' => now()])->save();
        $this->admin = User::factory()->withAccount($this->account)->create();
        $this->mailbox = Tenancy::for($this->account, fn () => Mailbox::factory()->create(['email_address' => 'howy@noordkade.nl']));
    }

    public function test_a_colleagues_first_mail_after_going_live_brings_the_welcome_once(): void
    {
        $this->arrives('m1', 'Anna de Boer <anna@noordkade.nl>');
        $this->arrives('m2', 'Anna de Boer <anna@noordkade.nl>');

        Tenancy::for($this->account, function (): void {
            $anna = Colleague::query()->where('email', 'anna@noordkade.nl')->sole();
            $welcome = OutboundMail::query()->sole();

            $this->assertSame(OutboundMailKind::Welcome, $welcome->kind);
            $this->assertSame($anna->id, $welcome->colleague_id);
            $this->assertNotNull($anna->welcomed_at);
        });
        $this->assertSame(1, $this->gmailSendCount());
    }

    public function test_no_welcome_before_going_live_or_from_an_import(): void
    {
        $this->account->forceFill(['live_at' => null])->save();
        $this->arrives('m1', 'Anna <anna@noordkade.nl>');

        $this->account->forceFill(['live_at' => now()])->save();
        // Jan is a colleague already, but an import is no mail of today.
        Tenancy::for($this->account, fn () => Colleague::factory()->create(['email' => 'jan@noordkade.nl', 'home_mailbox_id' => $this->mailbox->id]));
        $this->arrives('m2', 'Jan <jan@noordkade.nl>', EmailSource::Import);

        $this->assertSame(0, Tenancy::for($this->account, fn () => OutboundMail::query()->count()));
        $this->assertSame(0, $this->gmailSendCount());
    }

    public function test_someone_who_is_no_colleague_gets_no_welcome(): void
    {
        $this->arrives('m1', 'Piet <piet@klant.nl>');

        $this->assertSame(0, Tenancy::for($this->account, fn () => OutboundMail::query()->count()));
    }

    public function test_the_welcome_asked_for_twice_goes_once(): void
    {
        $anna = Tenancy::for($this->account, fn () => Colleague::factory()->create(['email' => 'anna@noordkade.nl', 'home_mailbox_id' => $this->mailbox->id]));

        Tenancy::for($this->account, function () use ($anna): void {
            $this->assertTrue(app(Welcomes::class)->send($anna));
            $this->assertFalse(app(Welcomes::class)->send($anna));
        });

        $this->assertSame(1, Tenancy::for($this->account, fn () => OutboundMail::query()->count()));
        $this->assertSame(1, $this->gmailSendCount());
    }

    public function test_a_welcome_on_its_way_shows_at_once_before_the_queue_sends_it(): void
    {
        Queue::fake();
        $anna = Tenancy::for($this->account, fn () => Colleague::factory()->create(['email' => 'anna@noordkade.nl', 'home_mailbox_id' => $this->mailbox->id]));

        $this->actingAs($this->admin)->post(route('colleagues.welcome', $anna))->assertRedirect();

        // Not sent yet, but logged: the button is gone and the step says so.
        $this->actingAs($this->admin)
            ->get(route('colleagues.show', $anna))
            ->assertInertia(fn ($page) => $page
                ->where('canWelcome', false)
                ->where('onTheirWay', ['welcome'])
                ->where('sent.0.status', 'pending')
                ->where('sent.0.reason', null));

        // A second click adds nothing.
        $this->actingAs($this->admin)->post(route('colleagues.welcome', $anna))->assertRedirect();

        Queue::assertPushed(DeliverOutboundMail::class, 1);
        $mail = Tenancy::for($this->account, fn () => OutboundMail::query()->sole());
        $this->assertSame(OutboundMailStatus::Pending, $mail->status);
        $this->assertSame(0, $this->gmailSendCount());

        // The queue sends it.
        Tenancy::for($this->account, fn () => (new DeliverOutboundMail($mail->id))->handle(app(HowyMailer::class)));

        $this->assertSame(OutboundMailStatus::Sent, Tenancy::for($this->account, fn () => $mail->refresh()->status));
        $this->actingAs($this->admin)
            ->get(route('colleagues.show', $anna))
            ->assertInertia(fn ($page) => $page->where('onTheirWay', [])->whereNot('colleague.welcomedAt', null));
    }

    public function test_adding_a_colleague_can_send_the_welcome_at_once(): void
    {
        $this->actingAs($this->admin)
            ->post(route('colleagues.store'), ['email' => 'eva@noordkade.nl', 'name' => 'Eva', 'home_mailbox_id' => $this->mailbox->id, 'access' => 'default', 'send_welcome' => '1'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->post(route('colleagues.store'), ['email' => 'tom@noordkade.nl', 'home_mailbox_id' => $this->mailbox->id, 'access' => 'default', 'send_welcome' => '0'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['eva@noordkade.nl'], Tenancy::for($this->account, fn () => OutboundMail::query()->pluck('recipient')->all()));
    }

    public function test_ticked_whoever_is_new_on_the_list_gets_howy_mails_and_the_welcome(): void
    {
        Tenancy::for($this->account, function (): void {
            // Known already, with Howy mails off: not at the company's domain.
            Colleague::factory()->create(['email' => 'kees@gmail.com', 'home_mailbox_id' => $this->mailbox->id, 'receives_mail' => false]);
            app(MailboxRuleEditor::class)->apply($this->mailbox, 'learn_answer', [['entry' => 'eva@gmail.com', 'access' => 'learn_answer']]);
        });

        $this->actingAs($this->admin)
            ->patch(route('mailboxes.rules.update', $this->mailbox), [
                'domain_access' => 'learn_answer',
                'sender_rules' => [
                    ['entry' => 'eva@gmail.com', 'access' => 'learn_answer'],
                    ['entry' => 'jan@noordkade.nl', 'access' => 'learn_answer'],
                    ['entry' => 'roger@gmail.com', 'access' => 'learn_answer'],
                    ['entry' => 'kees@gmail.com', 'access' => 'learn'],
                ],
                'welcome_new' => '1',
            ])
            ->assertSessionHasNoErrors();

        // A colleague at gmail.com too, but not Eva: she was on the list already.
        Tenancy::for($this->account, function (): void {
            $this->assertEqualsCanonicalizing(
                ['jan@noordkade.nl', 'roger@gmail.com', 'kees@gmail.com'],
                OutboundMail::query()->where('kind', OutboundMailKind::Welcome)->pluck('recipient')->all(),
            );
            $this->assertSame(
                ['eva@gmail.com' => false, 'jan@noordkade.nl' => true, 'kees@gmail.com' => true, 'roger@gmail.com' => true],
                Colleague::query()->orderBy('email')->pluck('receives_mail', 'email')->all(),
            );
        });
    }

    public function test_unticked_a_customer_on_the_list_gets_no_howy_mails(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('mailboxes.rules.update', $this->mailbox), [
                'domain_access' => 'learn_answer',
                'sender_rules' => [
                    ['entry' => 'jan@noordkade.nl', 'access' => 'learn_answer'],
                    ['entry' => 'piet@klant.nl', 'access' => 'answer'],
                ],
                'welcome_new' => '0',
            ])
            ->assertSessionHasNoErrors();

        Tenancy::for($this->account, function (): void {
            $this->assertSame(0, OutboundMail::query()->count());
            $this->assertSame(
                ['jan@noordkade.nl' => true, 'piet@klant.nl' => false],
                Colleague::query()->orderBy('email')->pluck('receives_mail', 'email')->all(),
            );
        });
    }

    public function test_the_welcome_can_be_sent_by_hand_once(): void
    {
        $anna = Tenancy::for($this->account, fn () => Colleague::factory()->create(['email' => 'anna@noordkade.nl', 'home_mailbox_id' => $this->mailbox->id]));

        $this->actingAs($this->admin)
            ->get(route('colleagues.show', $anna))
            ->assertInertia(fn ($page) => $page->where('canWelcome', true));

        $this->actingAs($this->admin)->post(route('colleagues.welcome', $anna))->assertRedirect();
        $this->actingAs($this->admin)->post(route('colleagues.welcome', $anna))->assertRedirect();

        $this->assertSame(1, $this->gmailSendCount());
        $this->actingAs($this->admin)
            ->get(route('colleagues.show', $anna))
            ->assertInertia(fn ($page) => $page->where('canWelcome', false));

        // Another account's colleague is not found.
        $theirs = Tenancy::for(Account::factory()->create(), fn () => Colleague::factory()->create());
        $this->actingAs($this->admin)->post(route('colleagues.welcome', $theirs))->assertNotFound();
    }

    private function arrives(string $id, string $from, EmailSource $source = EmailSource::Push): void
    {
        Tenancy::for($this->account, fn () => app(StoreGmailMessage::class)->store(
            $this->mailbox,
            GmailFixtures::message($id, ['from' => $from]),
            $source,
        ));
    }
}
