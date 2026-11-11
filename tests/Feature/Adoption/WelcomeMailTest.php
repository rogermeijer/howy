<?php

namespace Tests\Feature\Adoption;

use App\Enums\EmailSource;
use App\Enums\OutboundMailKind;
use App\Facades\Tenancy;
use App\Jobs\Adoption\SendFlowMail;
use App\Models\Account;
use App\Models\Colleague;
use App\Models\Mailbox;
use App\Models\OutboundMail;
use App\Models\User;
use App\Services\Gmail\StoreGmailMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    public function test_two_jobs_for_the_same_welcome_send_it_once(): void
    {
        $anna = Tenancy::for($this->account, fn () => Colleague::factory()->create(['email' => 'anna@noordkade.nl', 'home_mailbox_id' => $this->mailbox->id]));

        Tenancy::for($this->account, function () use ($anna): void {
            SendFlowMail::dispatchSync($anna->id, OutboundMailKind::Welcome);
            SendFlowMail::dispatchSync($anna->id, OutboundMailKind::Welcome);
        });

        $this->assertSame(1, Tenancy::for($this->account, fn () => OutboundMail::query()->count()));
        $this->assertSame(1, $this->gmailSendCount());
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

    public function test_new_addresses_on_the_list_can_get_the_welcome(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('mailboxes.rules.update', $this->mailbox), [
                'domain_access' => 'learn_answer',
                'sender_rules' => [
                    ['entry' => 'jan@noordkade.nl', 'access' => 'learn_answer'],
                    ['entry' => 'piet@klant.nl', 'access' => 'answer'],
                ],
                'welcome_new' => '1',
            ])
            ->assertSessionHasNoErrors();

        // A customer on the list gets no Howy mails, so no welcome.
        $this->assertSame(['jan@noordkade.nl'], Tenancy::for($this->account, fn () => OutboundMail::query()->pluck('recipient')->all()));
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
