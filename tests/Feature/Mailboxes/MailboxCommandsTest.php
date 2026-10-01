<?php

namespace Tests\Feature\Mailboxes;

use App\Facades\Tenancy;
use App\Jobs\StartGmailWatch;
use App\Jobs\SyncGmailMailbox;
use App\Models\Account;
use App\Models\Mailbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MailboxCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_polling_queues_every_active_mailbox_in_its_own_account(): void
    {
        Queue::fake();

        $acme = Account::factory()->create();
        $globex = Account::factory()->create();

        $acmeMailbox = Tenancy::for($acme, fn () => Mailbox::factory()->create());
        $globexMailbox = Tenancy::for($globex, fn () => Mailbox::factory()->create());
        Tenancy::for($globex, fn () => Mailbox::factory()->needsReauth()->create());

        $this->artisan('mailboxes:poll')->assertSuccessful();

        Queue::assertPushed(SyncGmailMailbox::class, 2);
        Queue::assertPushed(SyncGmailMailbox::class, fn (SyncGmailMailbox $job) => $job->mailboxId === $acmeMailbox->id
            && $job->tenantAccountId === $acme->id);
        Queue::assertPushed(SyncGmailMailbox::class, fn (SyncGmailMailbox $job) => $job->mailboxId === $globexMailbox->id
            && $job->tenantAccountId === $globex->id);
        $this->assertFalse(Tenancy::check());
    }

    public function test_only_watches_close_to_expiring_are_renewed(): void
    {
        Queue::fake();

        $account = Account::factory()->create();

        $expiring = Tenancy::for($account, fn () => Mailbox::factory()->create(['watch_expires_at' => now()->addDay()]));
        Tenancy::for($account, fn () => Mailbox::factory()->create(['watch_expires_at' => now()->addDays(6)]));

        $this->artisan('mailboxes:renew-watches')->assertSuccessful();

        Queue::assertPushed(StartGmailWatch::class, 1);
        Queue::assertPushed(StartGmailWatch::class, fn (StartGmailWatch $job) => $job->mailboxId === $expiring->id);
    }
}
