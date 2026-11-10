<?php

namespace Tests\Feature\Mailboxes;

use App\Enums\MailboxStatus;
use App\Enums\SenderAccess;
use App\Facades\Tenancy;
use App\Jobs\StartGmailWatch;
use App\Models\Account;
use App\Models\Mailbox;
use App\Models\MailboxSender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class ConnectGmailTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'client-id',
            'services.google.client_secret' => 'client-secret',
        ]);

        $this->account = Account::factory()->create();
        $this->admin = User::factory()->withAccount($this->account)->create();
    }

    public function test_the_redirect_asks_for_read_and_send_access_offline(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('mailboxes.gmail.redirect'));

        $location = urldecode((string) $response->headers->get('Location'));

        $this->assertStringStartsWith('https://accounts.google.com/', $location);
        $this->assertStringContainsString('https://www.googleapis.com/auth/gmail.readonly', $location);
        $this->assertStringContainsString('https://www.googleapis.com/auth/gmail.send', $location);
        $this->assertStringNotContainsString('gmail.modify', $location);
        $this->assertStringContainsString('access_type=offline', $location);
        $response->assertSessionHas('mailbox_connect', ['account_id' => $this->account->id]);
    }

    public function test_members_who_are_not_admins_cannot_connect_a_mailbox(): void
    {
        $member = User::factory()->withAccount($this->account, isAdmin: false)->create();

        $this->actingAs($member)->get(route('mailboxes.gmail.redirect'))->assertForbidden();
    }

    public function test_the_callback_creates_the_mailbox_and_starts_watching_it(): void
    {
        Queue::fake();
        $this->fakeGoogleUser();

        $this->actingAs($this->admin)
            ->withSession(['mailbox_connect' => ['account_id' => $this->account->id]])
            ->get(route('mailboxes.gmail.callback', ['code' => 'abc', 'state' => 'xyz']))
            ->assertRedirect(route('settings'));

        $mailbox = Tenancy::for($this->account, fn () => Mailbox::query()->sole());

        $this->assertSame('support@noordkade.nl', $mailbox->email_address);
        $this->assertSame(MailboxStatus::Active, $mailbox->status);
        $this->assertSame(SenderAccess::LearnAndAnswer, $mailbox->domain_access);
        $this->assertCount(0, Tenancy::for($this->account, fn () => $mailbox->senders));
        $this->assertSame('access-token', $mailbox->access_token);
        $this->assertSame('refresh-token', $mailbox->refresh_token);
        $this->assertSame($this->admin->id, $mailbox->connected_by_user_id);

        Queue::assertPushed(StartGmailWatch::class, fn (StartGmailWatch $job) => $job->mailboxId === $mailbox->id
            && $job->tenantAccountId === $this->account->id);
    }

    public function test_a_mailbox_on_a_public_domain_starts_with_only_whoever_connected_it(): void
    {
        Queue::fake();
        $this->fakeGoogleUser(email: 'Howy.Test@Gmail.com');

        $this->actingAs($this->admin)
            ->withSession(['mailbox_connect' => ['account_id' => $this->account->id]])
            ->get(route('mailboxes.gmail.callback', ['code' => 'abc']));

        $mailbox = Tenancy::for($this->account, fn () => Mailbox::query()->sole());

        Tenancy::for($this->account, function () use ($mailbox): void {
            $this->assertSame([['entry' => mb_strtolower($this->admin->email), 'access' => 'learn_answer']], $this->senders($mailbox));
            $this->assertTrue($mailbox->accepts($this->admin->email));
            $this->assertFalse($mailbox->accepts('someone.else@gmail.com'));
        });
    }

    public function test_a_mailbox_connected_again_keeps_its_rules(): void
    {
        Queue::fake();

        Tenancy::for($this->account, fn () => Mailbox::factory()->needsReauth()
            ->rules(senders: ['anna@gmail.com' => SenderAccess::Learn, 'klant.nl' => SenderAccess::Answer])
            ->create(['email_address' => 'howy.test@gmail.com']));

        $this->fakeGoogleUser(email: 'Howy.Test@Gmail.com');

        $this->actingAs($this->admin)
            ->withSession(['mailbox_connect' => ['account_id' => $this->account->id]])
            ->get(route('mailboxes.gmail.callback', ['code' => 'abc']));

        $mailbox = Tenancy::for($this->account, fn () => Mailbox::query()->sole());

        $this->assertSame(MailboxStatus::Active, $mailbox->status);
        $this->assertSame([
            ['entry' => 'anna@gmail.com', 'access' => 'learn'],
            ['entry' => 'klant.nl', 'access' => 'answer'],
        ], Tenancy::for($this->account, fn () => $this->senders($mailbox)));
    }

    public function test_reconnecting_revives_the_existing_mailbox_and_keeps_its_refresh_token(): void
    {
        Queue::fake();

        $existing = Tenancy::for($this->account, fn () => Mailbox::factory()->needsReauth()->create([
            'email_address' => 'support@noordkade.nl',
            'refresh_token' => 'old-refresh-token',
        ]));

        $this->fakeGoogleUser(refreshToken: null);

        $this->actingAs($this->admin)
            ->withSession(['mailbox_connect' => ['account_id' => $this->account->id]])
            ->get(route('mailboxes.gmail.callback', ['code' => 'abc']));

        $mailbox = Tenancy::for($this->account, fn () => Mailbox::query()->sole());

        $this->assertTrue($mailbox->is($existing));
        $this->assertSame(MailboxStatus::Active, $mailbox->status);
        $this->assertSame('old-refresh-token', $mailbox->refresh_token);
    }

    public function test_an_address_active_in_another_account_is_not_connected_here(): void
    {
        Queue::fake();

        $other = Account::factory()->create();
        $theirs = Tenancy::for($other, fn () => Mailbox::factory()->create([
            'email_address' => 'support@noordkade.nl',
            'refresh_token' => 'their-refresh-token',
        ]));

        $this->fakeGoogleUser();

        $this->actingAs($this->admin)
            ->withSession(['mailbox_connect' => ['account_id' => $this->account->id]])
            ->get(route('mailboxes.gmail.callback', ['code' => 'abc']))
            ->assertRedirect(route('settings'))
            ->assertInertiaFlash('toast.type', 'error');

        $this->assertSame(0, Tenancy::for($this->account, fn () => Mailbox::query()->count()));
        $this->assertSame(MailboxStatus::Active, $theirs->refresh()->status);
        $this->assertSame('their-refresh-token', $theirs->refresh_token);
        Queue::assertNothingPushed();
    }

    public function test_an_address_that_needs_reconnecting_here_is_refused_while_active_elsewhere(): void
    {
        Queue::fake();

        $ours = Tenancy::for($this->account, fn () => Mailbox::factory()->needsReauth()->create(['email_address' => 'support@noordkade.nl']));
        Tenancy::for(Account::factory()->create(), fn () => Mailbox::factory()->create(['email_address' => 'support@noordkade.nl']));

        $this->fakeGoogleUser();

        $this->actingAs($this->admin)
            ->withSession(['mailbox_connect' => ['account_id' => $this->account->id]])
            ->get(route('mailboxes.gmail.callback', ['code' => 'abc']))
            ->assertInertiaFlash('toast.type', 'error');

        $this->assertSame(MailboxStatus::NeedsReauth, $ours->refresh()->status);
    }

    public function test_an_address_no_longer_active_elsewhere_can_be_connected_here(): void
    {
        Queue::fake();

        Tenancy::for(Account::factory()->create(), fn () => Mailbox::factory()->disconnected()->create(['email_address' => 'support@noordkade.nl']));
        Tenancy::for(Account::factory()->create(), fn () => Mailbox::factory()->needsReauth()->create(['email_address' => 'support@noordkade.nl']));

        $this->fakeGoogleUser();

        $this->actingAs($this->admin)
            ->withSession(['mailbox_connect' => ['account_id' => $this->account->id]])
            ->get(route('mailboxes.gmail.callback', ['code' => 'abc']))
            ->assertInertiaFlash('toast.type', 'success');

        $this->assertSame(MailboxStatus::Active, Tenancy::for($this->account, fn () => Mailbox::query()->sole())->status);
    }

    public function test_a_grant_without_read_access_does_not_connect(): void
    {
        Queue::fake();
        $this->fakeGoogleUser(scopes: ['openid', 'email']);

        $this->actingAs($this->admin)
            ->withSession(['mailbox_connect' => ['account_id' => $this->account->id]])
            ->get(route('mailboxes.gmail.callback', ['code' => 'abc']))
            ->assertRedirect(route('settings'));

        $this->assertSame(0, Tenancy::for($this->account, fn () => Mailbox::query()->count()));
    }

    public function test_connecting_from_setup_comes_back_to_setup(): void
    {
        Queue::fake();
        $this->fakeGoogleUser();

        $this->actingAs($this->admin)
            ->get(route('mailboxes.gmail.redirect', ['from' => 'setup']))
            ->assertSessionHas('mailbox_connect', ['account_id' => $this->account->id, 'return' => 'setup.mailbox']);

        $this->actingAs($this->admin)
            ->withSession(['mailbox_connect' => ['account_id' => $this->account->id, 'return' => 'setup.mailbox']])
            ->get(route('mailboxes.gmail.callback', ['code' => 'abc', 'state' => 'xyz']))
            ->assertRedirect(route('setup.mailbox'));

        $this->assertSame(1, Tenancy::for($this->account, fn () => Mailbox::query()->count()));
    }

    public function test_a_connection_refused_from_setup_comes_back_to_setup_too(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['mailbox_connect' => ['account_id' => $this->account->id, 'return' => 'setup.mailbox']])
            ->get(route('mailboxes.gmail.callback', ['error' => 'access_denied']))
            ->assertRedirect(route('setup.mailbox'))
            ->assertInertiaFlash('toast.type', 'error');
    }

    public function test_the_callback_refuses_when_the_account_changed_since_the_redirect(): void
    {
        $other = Account::factory()->create();

        $this->actingAs($this->admin)
            ->withSession(['mailbox_connect' => ['account_id' => $other->id]])
            ->get(route('mailboxes.gmail.callback', ['code' => 'abc']))
            ->assertForbidden();
    }

    /**
     * @param  list<string>|null  $scopes
     */
    /**
     * @return list<array{entry: string, access: string}>
     */
    private function senders(Mailbox $mailbox): array
    {
        return $mailbox->senders()->oldest('id')->get()
            ->map(fn (MailboxSender $sender): array => ['entry' => $sender->entry, 'access' => $sender->access->value])
            ->values()
            ->all();
    }

    private function fakeGoogleUser(?string $refreshToken = 'refresh-token', ?array $scopes = null, string $email = 'Support@Noordkade.nl'): void
    {
        $user = (new GoogleUser)
            ->setRaw(['sub' => '123'])
            ->map(['id' => '123', 'email' => $email])
            ->setToken('access-token')
            ->setRefreshToken($refreshToken)
            ->setExpiresIn(3599)
            ->setApprovedScopes($scopes ?? [
                'openid',
                'email',
                'https://www.googleapis.com/auth/gmail.readonly',
                'https://www.googleapis.com/auth/gmail.send',
            ]);

        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('user')->andReturn($user);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }
}
