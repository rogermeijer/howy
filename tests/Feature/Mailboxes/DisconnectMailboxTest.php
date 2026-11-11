<?php

namespace Tests\Feature\Mailboxes;

use App\Enums\MailboxStatus;
use App\Enums\OutboundMailStatus;
use App\Enums\OutboundSkipReason;
use App\Facades\Tenancy;
use App\Jobs\SyncGmailMailbox;
use App\Models\Account;
use App\Models\Colleague;
use App\Models\Email;
use App\Models\Mailbox;
use App\Models\OutboundMail;
use App\Models\User;
use App\Services\Google\PubSubTokenVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\GmailFixtures;
use Tests\TestCase;

class DisconnectMailboxTest extends TestCase
{
    use RefreshDatabase;

    private const string MIGRATION = 'database/migrations/2026_11_15_100000_let_go_of_disconnected_mailboxes.php';

    public function test_disconnecting_stops_the_watch_wipes_tokens_and_keeps_emails(): void
    {
        Http::fake([
            GmailFixtures::API.'/stop' => Http::response(),
            'https://oauth2.googleapis.com/revoke' => Http::response(),
        ]);

        $account = Account::factory()->create();
        $admin = User::factory()->withAccount($account)->create();

        [$mailbox, $email] = Tenancy::for($account, function () {
            $mailbox = Mailbox::factory()->create();

            return [$mailbox, Email::factory()->create(['mailbox_id' => $mailbox->id])];
        });

        $this->actingAs($admin)
            ->delete(route('mailboxes.destroy', $mailbox))
            ->assertRedirect();

        $mailbox->refresh();

        $this->assertSame(MailboxStatus::Disconnected, $mailbox->status);
        $this->assertNull($mailbox->access_token);
        $this->assertNull($mailbox->refresh_token);
        $this->assertTrue(Tenancy::for($account, fn () => Email::query()->whereKey($email->id)->exists()));

        Http::assertSent(fn (Request $request) => $request->url() === GmailFixtures::API.'/stop');
        Http::assertSent(fn (Request $request) => $request->url() === 'https://oauth2.googleapis.com/revoke');
    }

    public function test_disconnecting_lets_go_of_the_colleagues_and_mails_that_leaned_on_it(): void
    {
        Http::fake([
            GmailFixtures::API.'/stop' => Http::response(),
            'https://oauth2.googleapis.com/revoke' => Http::response(),
        ]);

        $account = Account::factory()->create();
        $admin = User::factory()->withAccount($account)->create();

        [$mailbox, $anna, $bert, $waiting, $sent, $elsewhere] = Tenancy::for($account, function (): array {
            $mailbox = Mailbox::factory()->create(['email_address' => 'kennis@noordkade.nl']);
            $support = Mailbox::factory()->create(['email_address' => 'support@noordkade.nl']);
            $anna = Colleague::factory()->create(['home_mailbox_id' => $mailbox->id]);
            $bert = Colleague::factory()->create(['home_mailbox_id' => $support->id]);

            return [
                $mailbox,
                $anna,
                $bert,
                OutboundMail::factory()->create(['mailbox_id' => $mailbox->id, 'colleague_id' => $anna->id]),
                OutboundMail::factory()->sent()->create(['mailbox_id' => $mailbox->id, 'colleague_id' => $anna->id]),
                OutboundMail::factory()->create(['mailbox_id' => $support->id, 'colleague_id' => $bert->id]),
            ];
        });

        $this->actingAs($admin)
            ->delete(route('mailboxes.destroy', $mailbox))
            ->assertRedirect();

        Tenancy::for($account, function () use ($anna, $bert, $waiting, $sent, $elsewhere): void {
            // No mailbox for Anna's Howy mails, until an admin chooses one or
            // she mails another. Bert's is still there.
            $this->assertNull($anna->refresh()->home_mailbox_id);
            $this->assertNotNull($bert->refresh()->home_mailbox_id);

            // What waited on it will not go; what went, went.
            $this->assertSame(OutboundMailStatus::Skipped, $waiting->refresh()->status);
            $this->assertSame(OutboundSkipReason::NoMailbox, $waiting->skip_reason);
            $this->assertSame(OutboundMailStatus::Sent, $sent->refresh()->status);
            $this->assertSame(OutboundMailStatus::Pending, $elsewhere->refresh()->status);
        });
    }

    public function test_a_mailbox_disconnected_before_lets_go_too_once_migrated(): void
    {
        $account = Account::factory()->create();

        [$anna, $bert, $waiting, $elsewhere] = Tenancy::for($account, function (): array {
            $old = Mailbox::factory()->disconnected()->create(['email_address' => 'oud@noordkade.nl']);
            $current = Mailbox::factory()->create(['email_address' => 'kennis@noordkade.nl']);
            $anna = Colleague::factory()->create(['home_mailbox_id' => $old->id]);
            $bert = Colleague::factory()->create(['home_mailbox_id' => $current->id]);

            return [
                $anna,
                $bert,
                OutboundMail::factory()->create(['mailbox_id' => $old->id, 'colleague_id' => $anna->id]),
                OutboundMail::factory()->create(['mailbox_id' => $current->id, 'colleague_id' => $bert->id]),
            ];
        });

        $this->artisan('migrate:rollback', ['--path' => self::MIGRATION])->assertSuccessful();
        $this->artisan('migrate', ['--path' => self::MIGRATION])->assertSuccessful();

        Tenancy::for($account, function () use ($anna, $bert, $waiting, $elsewhere): void {
            $this->assertNull($anna->refresh()->home_mailbox_id);
            $this->assertNotNull($bert->refresh()->home_mailbox_id);
            $this->assertSame(OutboundMailStatus::Skipped, $waiting->refresh()->status);
            $this->assertSame(OutboundSkipReason::NoMailbox, $waiting->skip_reason);
            $this->assertSame(OutboundMailStatus::Pending, $elsewhere->refresh()->status);
        });
    }

    public function test_a_disconnected_mailbox_no_longer_receives_pushes(): void
    {
        Queue::fake();
        $this->mock(PubSubTokenVerifier::class)->shouldReceive('verify')->andReturn(true);

        $account = Account::factory()->create();
        Tenancy::for($account, fn () => Mailbox::factory()->disconnected()->create(['email_address' => 'support@noordkade.nl']));

        $this->postJson(route('webhooks.gmail'), GmailFixtures::push('support@noordkade.nl'))->assertNoContent();

        Queue::assertNotPushed(SyncGmailMailbox::class);
    }
}
