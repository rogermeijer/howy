<?php

namespace Tests\Feature\Mailboxes;

use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Email;
use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Another account's mailboxes and emails must be invisible: every route 404s
 * through the scoped binding, and no listing includes them.
 */
class MailboxIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Account $theirs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->withAccount()->create();
        $this->theirs = Account::factory()->create();
    }

    public function test_another_accounts_mailbox_404s_on_every_route(): void
    {
        $mailbox = Tenancy::for($this->theirs, fn () => Mailbox::factory()->create());

        $this->actingAs($this->admin);

        $this->post(route('mailboxes.sync', $mailbox->id))->assertNotFound();
        $this->delete(route('mailboxes.destroy', $mailbox->id))->assertNotFound();
        $this->getJson(route('mailboxes.import.search', $mailbox->id))->assertNotFound();
        $this->post(route('mailboxes.import.store', $mailbox->id), ['message_ids' => ['m1']])->assertNotFound();

        $this->assertTrue($mailbox->refresh()->isActive());
    }

    public function test_another_accounts_email_404s(): void
    {
        $email = Tenancy::for($this->theirs, fn () => Email::factory()->create([
            'mailbox_id' => Mailbox::factory()->create()->id,
        ]));

        $this->actingAs($this->admin)->get(route('emails.show', $email->id))->assertNotFound();
    }

    public function test_listings_only_show_the_current_accounts_mailboxes_and_emails(): void
    {
        $ours = $this->admin->accounts()->sole();

        Tenancy::for($ours, function () {
            $mailbox = Mailbox::factory()->create(['email_address' => 'ours@noordkade.nl']);
            Email::factory()->create(['mailbox_id' => $mailbox->id, 'subject' => 'Ours']);
        });

        Tenancy::for($this->theirs, function () {
            $mailbox = Mailbox::factory()->create(['email_address' => 'theirs@elders.nl']);
            Email::factory()->create(['mailbox_id' => $mailbox->id, 'subject' => 'Theirs']);
        });

        $this->actingAs($this->admin);

        $this->get(route('inbox'))->assertInertia(fn (Assert $page) => $page
            ->has('threads.data', 1)
            ->where('threads.data.0.subject', 'Ours'));

        $this->get(route('settings'))->assertInertia(fn (Assert $page) => $page
            ->has('mailboxes', 1)
            ->where('mailboxes.0.emailAddress', 'ours@noordkade.nl'));
    }
}
