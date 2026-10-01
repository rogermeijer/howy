<?php

namespace Tests\Feature\Mailboxes;

use App\Enums\MailboxStatus;
use App\Enums\SendPolicy;
use App\Facades\Tenancy;
use App\Jobs\ImportGmailQuery;
use App\Jobs\StartGmailWatch;
use App\Models\Account;
use App\Models\Mailbox;
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
            ->get(route('mailboxes.gmail.redirect', ['backfill' => '30d']));

        $location = urldecode((string) $response->headers->get('Location'));

        $this->assertStringStartsWith('https://accounts.google.com/', $location);
        $this->assertStringContainsString('https://www.googleapis.com/auth/gmail.readonly', $location);
        $this->assertStringContainsString('https://www.googleapis.com/auth/gmail.send', $location);
        $this->assertStringNotContainsString('gmail.modify', $location);
        $this->assertStringContainsString('access_type=offline', $location);
        $response->assertSessionHas('mailbox_connect', ['account_id' => $this->account->id, 'backfill' => true]);
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
            ->withSession(['mailbox_connect' => ['account_id' => $this->account->id, 'backfill' => false]])
            ->get(route('mailboxes.gmail.callback', ['code' => 'abc', 'state' => 'xyz']))
            ->assertRedirect(route('settings'));

        $mailbox = Tenancy::for($this->account, fn () => Mailbox::query()->sole());

        $this->assertSame('support@noordkade.nl', $mailbox->email_address);
        $this->assertSame(MailboxStatus::Active, $mailbox->status);
        $this->assertSame(SendPolicy::Off, $mailbox->send_policy);
        $this->assertSame('access-token', $mailbox->access_token);
        $this->assertSame('refresh-token', $mailbox->refresh_token);
        $this->assertSame($this->admin->id, $mailbox->connected_by_user_id);

        Queue::assertPushed(StartGmailWatch::class, fn (StartGmailWatch $job) => $job->mailboxId === $mailbox->id
            && $job->tenantAccountId === $this->account->id);
        Queue::assertNotPushed(ImportGmailQuery::class);
    }

    public function test_choosing_the_30_day_backfill_starts_an_import(): void
    {
        Queue::fake();
        $this->fakeGoogleUser();

        $this->actingAs($this->admin)
            ->withSession(['mailbox_connect' => ['account_id' => $this->account->id, 'backfill' => true]])
            ->get(route('mailboxes.gmail.callback', ['code' => 'abc']));

        Queue::assertPushed(ImportGmailQuery::class, fn (ImportGmailQuery $job) => $job->query === 'in:inbox newer_than:30d');
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
            ->withSession(['mailbox_connect' => ['account_id' => $this->account->id, 'backfill' => false]])
            ->get(route('mailboxes.gmail.callback', ['code' => 'abc']));

        $mailbox = Tenancy::for($this->account, fn () => Mailbox::query()->sole());

        $this->assertTrue($mailbox->is($existing));
        $this->assertSame(MailboxStatus::Active, $mailbox->status);
        $this->assertSame('old-refresh-token', $mailbox->refresh_token);
    }

    public function test_a_grant_without_read_access_does_not_connect(): void
    {
        Queue::fake();
        $this->fakeGoogleUser(scopes: ['openid', 'email']);

        $this->actingAs($this->admin)
            ->withSession(['mailbox_connect' => ['account_id' => $this->account->id, 'backfill' => false]])
            ->get(route('mailboxes.gmail.callback', ['code' => 'abc']))
            ->assertRedirect(route('settings'));

        $this->assertSame(0, Tenancy::for($this->account, fn () => Mailbox::query()->count()));
    }

    public function test_the_callback_refuses_when_the_account_changed_since_the_redirect(): void
    {
        $other = Account::factory()->create();

        $this->actingAs($this->admin)
            ->withSession(['mailbox_connect' => ['account_id' => $other->id, 'backfill' => false]])
            ->get(route('mailboxes.gmail.callback', ['code' => 'abc']))
            ->assertForbidden();
    }

    /**
     * @param  list<string>|null  $scopes
     */
    private function fakeGoogleUser(?string $refreshToken = 'refresh-token', ?array $scopes = null): void
    {
        $user = (new GoogleUser)
            ->setRaw(['sub' => '123'])
            ->map(['id' => '123', 'email' => 'Support@Noordkade.nl'])
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
