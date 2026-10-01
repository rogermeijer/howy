<?php

namespace Tests\Feature\Mailboxes;

use App\Enums\EmailSource;
use App\Facades\Tenancy;
use App\Jobs\SyncGmailMailbox;
use App\Models\Account;
use App\Models\Mailbox;
use App\Services\Google\PubSubTokenVerifier;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\GmailFixtures;
use Tests\TestCase;

class GmailWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_a_push_without_a_valid_google_token_is_rejected(): void
    {
        config([
            'services.google.pubsub_audience' => 'https://cc.test/webhooks/gmail',
            'services.google.pubsub_service_account' => 'push@cc.iam.gserviceaccount.com',
        ]);

        $this->postJson(route('webhooks.gmail'), GmailFixtures::push('support@noordkade.nl'))
            ->assertUnauthorized();

        $this->withToken('not-a-jwt')
            ->postJson(route('webhooks.gmail'), GmailFixtures::push('support@noordkade.nl'))
            ->assertUnauthorized();

        Queue::assertNothingPushed();
    }

    public function test_a_push_syncs_the_mailbox_inside_its_own_account(): void
    {
        $this->trustPushes();

        $account = Account::factory()->create();
        $mailbox = Tenancy::for($account, fn () => Mailbox::factory()->create(['email_address' => 'support@noordkade.nl']));

        $this->postJson(route('webhooks.gmail'), GmailFixtures::push('Support@Noordkade.nl'))->assertNoContent();

        Queue::assertPushed(SyncGmailMailbox::class, fn (SyncGmailMailbox $job) => $job->mailboxId === $mailbox->id
            && $job->tenantAccountId === $account->id
            && $job->source === EmailSource::Push);

        // The one sanctioned bypass must not leave the request without scoping.
        $this->assertFalse(Tenancy::disabled());
    }

    public function test_an_address_connected_in_two_accounts_syncs_both_separately(): void
    {
        $this->trustPushes();

        $acme = Account::factory()->create();
        $globex = Account::factory()->create();

        $acmeMailbox = Tenancy::for($acme, fn () => Mailbox::factory()->create(['email_address' => 'shared@noordkade.nl']));
        $globexMailbox = Tenancy::for($globex, fn () => Mailbox::factory()->create(['email_address' => 'shared@noordkade.nl']));

        $this->postJson(route('webhooks.gmail'), GmailFixtures::push('shared@noordkade.nl'))->assertNoContent();

        Queue::assertPushed(SyncGmailMailbox::class, 2);
        Queue::assertPushed(SyncGmailMailbox::class, fn (SyncGmailMailbox $job) => $job->mailboxId === $acmeMailbox->id
            && $job->tenantAccountId === $acme->id);
        Queue::assertPushed(SyncGmailMailbox::class, fn (SyncGmailMailbox $job) => $job->mailboxId === $globexMailbox->id
            && $job->tenantAccountId === $globex->id);
    }

    public function test_the_webhook_skips_csrf_so_pubsub_posts_are_not_rejected(): void
    {
        $route = Route::getRoutes()->getByName('webhooks.gmail');

        $this->assertNotNull($route);
        $this->assertContains(PreventRequestForgery::class, $route->excludedMiddleware());
    }

    public function test_an_unknown_address_is_acknowledged_so_pubsub_stops_retrying(): void
    {
        $this->trustPushes();

        $this->postJson(route('webhooks.gmail'), GmailFixtures::push('nobody@example.com'))->assertNoContent();
        $this->postJson(route('webhooks.gmail'), ['message' => ['data' => 'not-base64-json']])->assertNoContent();

        Queue::assertNothingPushed();
    }

    private function trustPushes(): void
    {
        $this->mock(PubSubTokenVerifier::class)->shouldReceive('verify')->andReturn(true);
    }
}
