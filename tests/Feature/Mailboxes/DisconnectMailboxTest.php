<?php

namespace Tests\Feature\Mailboxes;

use App\Enums\MailboxStatus;
use App\Facades\Tenancy;
use App\Jobs\SyncGmailMailbox;
use App\Models\Account;
use App\Models\Email;
use App\Models\Mailbox;
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
