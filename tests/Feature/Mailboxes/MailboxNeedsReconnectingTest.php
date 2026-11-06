<?php

namespace Tests\Feature\Mailboxes;

use App\Enums\Locale;
use App\Enums\MailboxStatus;
use App\Exceptions\MailboxNeedsReauthException;
use App\Facades\Tenancy;
use App\Mail\MailboxNeedsReconnecting;
use App\Models\Account;
use App\Models\Mailbox;
use App\Models\User;
use App\Services\Gmail\GmailClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * When Google kills a mailbox's grant, the account's admins hear about it once,
 * and nobody outside the account does.
 */
class MailboxNeedsReconnectingTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Http::fake([
            GmailClient::TOKEN_URL => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $this->account = Account::factory()->create(['name' => 'Noordkade']);
    }

    public function test_a_dead_grant_mails_each_admin_of_the_account_in_their_language(): void
    {
        $admin = User::factory()->withAccount($this->account)->create(['locale' => Locale::Dutch]);
        User::factory()->withAccount($this->account, isAdmin: false)->create();

        $this->refuse($this->mailbox());

        Mail::assertQueuedCount(1);
        Mail::assertQueued(MailboxNeedsReconnecting::class, fn (MailboxNeedsReconnecting $mail): bool => $mail->hasTo($admin->email)
            && $mail->locale === 'nl'
            && $mail->address === 'support@noordkade.nl'
            && $mail->accountName === 'Noordkade');
    }

    public function test_jobs_hitting_the_same_dead_grant_send_one_mail(): void
    {
        User::factory()->withAccount($this->account)->create();
        $mailbox = $this->mailbox();

        // Two copies loaded while the mailbox was still active, as parallel jobs would.
        [$first, $second] = Tenancy::for($this->account, fn () => [
            Mailbox::query()->findOrFail($mailbox->id),
            Mailbox::query()->findOrFail($mailbox->id),
        ]);

        $this->refuse($first);
        $this->refuse($second);

        Mail::assertQueuedCount(1);
        $this->assertSame(MailboxStatus::NeedsReauth, $mailbox->refresh()->status);
    }

    public function test_admins_of_another_account_are_not_mailed(): void
    {
        $theirAdmin = User::factory()->withAccount()->create();

        $this->refuse($this->mailbox());

        Mail::assertNotQueued(MailboxNeedsReconnecting::class, fn (MailboxNeedsReconnecting $mail): bool => $mail->hasTo($theirAdmin->email));
    }

    public function test_the_mail_names_the_mailbox_and_links_to_the_settings(): void
    {
        (new MailboxNeedsReconnecting('support@noordkade.nl', 'Noordkade'))
            ->assertSeeInHtml('support@noordkade.nl')
            ->assertSeeInHtml('Noordkade')
            ->assertSeeInHtml(route('settings'));
    }

    private function mailbox(): Mailbox
    {
        return Tenancy::for($this->account, fn () => Mailbox::factory()->create([
            'email_address' => 'support@noordkade.nl',
            'token_expires_at' => now()->subMinute(),
        ]));
    }

    private function refuse(Mailbox $mailbox): void
    {
        Tenancy::for($this->account, function () use ($mailbox): void {
            try {
                GmailClient::for($mailbox)->profile();
            } catch (MailboxNeedsReauthException) {
                // Expected: the grant is dead.
            }
        });
    }
}
