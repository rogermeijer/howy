<?php

namespace Tests\Feature\Adoption;

use App\Enums\MailboxStatus;
use App\Enums\OutboundMailKind;
use App\Enums\OutboundMailStatus;
use App\Enums\SenderAccess;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Colleague;
use App\Models\Mailbox;
use App\Models\OutboundMail;
use App\Models\User;
use App\Services\Adoption\Welcomes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\FakesGmailSending;
use Tests\TestCase;

class GoLiveTest extends TestCase
{
    use FakesGmailSending;
    use RefreshDatabase;

    private Account $account;

    private User $admin;

    private Mailbox $mailbox;

    protected function setUp(): void
    {
        parent::setUp();

        // Nothing here reaches Google, also not by mistake; pages render without SSR.
        $this->fakeGmailSend('sent-1', 'thread-1');
        Http::preventStrayRequests();
        config(['inertia.ssr.enabled' => false]);

        $this->account = Account::factory()->create(['locale' => 'nl', 'timezone' => 'Europe/Amsterdam']);
        $this->admin = User::factory()->withAccount($this->account)->create();
        $this->mailbox = Tenancy::for($this->account, fn () => Mailbox::factory()->rules(senders: [
            'hr@noordkade.nl' => SenderAccess::Ignore,
        ])->create(['email_address' => 'howy@noordkade.nl', 'token_expires_at' => now()->addMonth()]));
    }

    public function test_an_account_starts_not_live_and_sends_nothing_of_its_own(): void
    {
        $anna = $this->colleague('anna@noordkade.nl', 'Anna de Boer');

        $this->assertFalse($this->account->isLive());
        $this->assertFalse(Tenancy::for($this->account, fn () => app(Welcomes::class)->send($anna)));
        $this->assertSame(0, $this->gmailSendCount());

        $this->actingAs($this->admin)
            ->get(route('settings'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('live.since', null)
                ->missing('welcomeList'));
    }

    public function test_the_list_holds_whoever_the_welcome_would_go_to(): void
    {
        $anna = $this->colleague('anna@noordkade.nl', 'Anna de Boer');
        $jan = $this->colleague('jan@noordkade.nl', 'Jan Smit');
        $this->colleague('piet@klant.nl', receivesMail: false);
        $this->colleague('tom@noordkade.nl', attributes: ['opted_out_at' => now()]);
        $this->colleague('eva@noordkade.nl', attributes: ['welcomed_at' => now()]);
        $sanne = $this->colleague('sanne@noordkade.nl');
        Tenancy::for($this->account, fn () => OutboundMail::factory()->create([
            'mailbox_id' => $this->mailbox->id, 'colleague_id' => $sanne->id, 'kind' => OutboundMailKind::Welcome, 'status' => OutboundMailStatus::Pending,
        ]));

        // hr@ is ignored by its own rule; piet@ gets no Howy mails; tom@
        // unsubscribed; eva@ and sanne@ have had it, or it is on its way.
        $this->actingAs($this->admin)
            ->get(route('settings'))
            ->assertInertia(fn (Assert $page) => $page->reloadOnly('welcomeList', fn (Assert $page) => $page
                ->where('welcomeList', [
                    ['id' => $anna->id, 'name' => 'Anna de Boer', 'email' => 'anna@noordkade.nl', 'mailbox' => 'howy@noordkade.nl'],
                    ['id' => $jan->id, 'name' => 'Jan Smit', 'email' => 'jan@noordkade.nl', 'mailbox' => 'howy@noordkade.nl'],
                ])));
    }

    public function test_going_live_sends_the_welcome_to_those_kept_on_the_list(): void
    {
        $this->travelTo(Carbon::parse('2026-11-14 22:00', 'Europe/Amsterdam'));
        $anna = $this->colleague('anna@noordkade.nl', 'Anna de Boer');
        $jan = $this->colleague('jan@noordkade.nl', 'Jan Smit');
        $eva = $this->colleague('eva@noordkade.nl');

        $this->actingAs($this->admin)
            ->post(route('account.live.store'), ['colleagues' => [$anna->id, $jan->id]])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertTrue($this->account->refresh()->isLive());

        // Going live sends at once, Saturday night or not.
        Tenancy::for($this->account, function () use ($anna, $jan, $eva): void {
            $this->assertNotNull($anna->refresh()->welcomed_at);
            $this->assertNotNull($jan->refresh()->welcomed_at);
            $this->assertNull($eva->refresh()->welcomed_at);

            $welcome = OutboundMail::query()->where('colleague_id', $anna->id)->sole();
            $this->assertSame(OutboundMailKind::Welcome, $welcome->kind);
            $this->assertSame(OutboundMailStatus::Sent, $welcome->status);
            // Written for Anna, in the account's language, from her mailbox.
            $this->assertStringContainsString('Hoi Anna', $welcome->text);
            $this->assertStringContainsString('howy@noordkade.nl', $welcome->text);
        });
        $this->assertSame(2, $this->gmailSendCount());

        // Going live again sends nobody a second welcome.
        $this->actingAs($this->admin)->post(route('account.live.store'), ['colleagues' => [$anna->id]]);
        $this->assertSame(2, $this->gmailSendCount());
    }

    public function test_only_this_accounts_colleagues_who_may_get_it_are_welcomed(): void
    {
        $hr = Tenancy::for($this->account, fn () => Colleague::query()->where('email', 'hr@noordkade.nl')->sole());
        $piet = $this->colleague('piet@klant.nl', receivesMail: false);
        $theirs = Tenancy::for(Account::factory()->create(), function (): Colleague {
            $mailbox = Mailbox::factory()->create(['email_address' => 'howy@elders.nl']);

            return Colleague::factory()->create(['email' => 'anna@elders.nl', 'home_mailbox_id' => $mailbox->id]);
        });

        $this->actingAs($this->admin)
            ->post(route('account.live.store'), ['colleagues' => [$hr->id, $piet->id, $theirs->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $this->gmailSendCount());
        $this->assertSame(0, Tenancy::withoutTenancy(fn () => OutboundMail::query()->count()));
    }

    public function test_only_an_admin_puts_howy_live_or_pauses_it(): void
    {
        $member = User::factory()->withAccount($this->account, isAdmin: false)->create();

        $this->actingAs($member)->post(route('account.live.store'), ['colleagues' => []])->assertForbidden();
        $this->actingAs($member)->delete(route('account.live.destroy'))->assertForbidden();

        $this->assertFalse($this->account->refresh()->isLive());
    }

    public function test_pausing_holds_howys_own_mails_until_it_is_live_again(): void
    {
        $anna = $this->colleague('anna@noordkade.nl');
        $this->mailbox->forceFill(['status' => MailboxStatus::NeedsReauth])->save();
        $this->actingAs($this->admin)->post(route('account.live.store'), ['colleagues' => [$anna->id]]);
        $welcome = Tenancy::for($this->account, fn () => OutboundMail::query()->sole());

        $this->actingAs($this->admin)->delete(route('account.live.destroy'))->assertRedirect();
        $this->assertFalse($this->account->refresh()->isLive());

        // The mailbox is back, but Howy is paused: the welcome waits.
        $this->mailbox->forceFill(['status' => MailboxStatus::Active, 'token_expires_at' => now()->addMonth()])->save();
        $this->travelTo(Carbon::parse('2026-11-16 10:00', 'Europe/Amsterdam'));
        $this->artisan('adoption:send')->assertSuccessful();

        $this->assertSame(OutboundMailStatus::Pending, Tenancy::for($this->account, fn () => $welcome->refresh()->status));

        // Live again, it goes.
        $this->actingAs($this->admin)->post(route('account.live.store'), ['colleagues' => []]);
        $this->artisan('adoption:send')->assertSuccessful();

        $this->assertSame(OutboundMailStatus::Sent, Tenancy::for($this->account, fn () => $welcome->refresh()->status));
        $this->assertSame(1, $this->gmailSendCount());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function colleague(string $email, ?string $name = null, bool $receivesMail = true, array $attributes = []): Colleague
    {
        return Tenancy::for($this->account, function () use ($email, $name, $receivesMail, $attributes): Colleague {
            $colleague = Colleague::factory()->create(['email' => $email, 'name' => $name, 'home_mailbox_id' => $this->mailbox->id, 'receives_mail' => $receivesMail]);
            $colleague->forceFill($attributes)->save();

            return $colleague;
        });
    }
}
