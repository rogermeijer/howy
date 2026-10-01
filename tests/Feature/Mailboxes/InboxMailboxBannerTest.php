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

class InboxMailboxBannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_inbox_asks_for_a_mailbox_until_one_is_connected(): void
    {
        $account = Account::factory()->create();
        $admin = User::factory()->withAccount($account)->create();

        $this->actingAs($admin)->get(route('inbox'))->assertInertia(fn (Assert $page) => $page
            ->component('inbox')
            ->where('hasMailbox', false)
            ->where('canManageMailboxes', true));

        Tenancy::for($account, fn () => Mailbox::factory()->create());

        $this->get(route('inbox'))->assertInertia(fn (Assert $page) => $page->where('hasMailbox', true));
    }

    public function test_a_disconnected_mailbox_brings_the_banner_back(): void
    {
        $account = Account::factory()->create();
        $admin = User::factory()->withAccount($account)->create();

        Tenancy::for($account, fn () => Mailbox::factory()->disconnected()->create());

        $this->actingAs($admin)->get(route('inbox'))->assertInertia(fn (Assert $page) => $page
            ->where('hasMailbox', false));
    }

    public function test_members_see_the_banner_without_the_connect_action(): void
    {
        $member = User::factory()->withAccount(isAdmin: false)->create();

        $this->actingAs($member)->get(route('inbox'))->assertInertia(fn (Assert $page) => $page
            ->where('hasMailbox', false)
            ->where('canManageMailboxes', false));
    }

    public function test_the_banner_link_opens_the_connect_dialog_on_settings(): void
    {
        $admin = User::factory()->withAccount()->create();

        $this->actingAs($admin)
            ->get(route('settings', ['connect' => 'mailbox']))
            ->assertInertia(fn (Assert $page) => $page->where('openConnect', true));

        $this->get(route('settings'))->assertInertia(fn (Assert $page) => $page->where('openConnect', false));
    }

    public function test_the_inbox_lists_one_row_per_conversation(): void
    {
        $account = Account::factory()->create();
        $admin = User::factory()->withAccount($account)->create();

        [$latest] = Tenancy::for($account, function () {
            $mailbox = Mailbox::factory()->create();

            Email::factory()->create([
                'mailbox_id' => $mailbox->id, 'provider_thread_id' => 't1', 'subject' => 'Vergunning terras',
                'from_name' => 'Tom Bakker', 'received_at' => now()->subDays(2), 'has_attachments' => true,
            ]);
            $latest = Email::factory()->create([
                'mailbox_id' => $mailbox->id, 'provider_thread_id' => 't1', 'subject' => 'RE: Vergunning terras',
                'from_name' => 'Sanne de Vries', 'snippet' => 'Ik stuur ze vrijdag.', 'received_at' => now(),
            ]);
            Email::factory()->create([
                'mailbox_id' => $mailbox->id, 'provider_thread_id' => 't1',
                'from_name' => 'Tom Bakker', 'received_at' => now()->subDay(),
            ]);
            Email::factory()->create([
                'mailbox_id' => $mailbox->id, 'provider_thread_id' => 't2', 'subject' => 'Kleurstaal gevel',
                'received_at' => now()->subHour(),
            ]);

            return [$latest];
        });

        $this->actingAs($admin)->get(route('inbox'))->assertInertia(fn (Assert $page) => $page
            ->has('threads.data', 2)
            ->where('threads.total', 2)
            ->where('threads.data.0.id', $latest->id)
            ->where('threads.data.0.subject', 'Vergunning terras')
            ->where('threads.data.0.participants', ['Tom Bakker', 'Sanne de Vries'])
            ->where('threads.data.0.snippet', 'Ik stuur ze vrijdag.')
            ->where('threads.data.0.messagesCount', 3)
            ->where('threads.data.0.hasAttachments', true)
            ->where('threads.data.1.subject', 'Kleurstaal gevel')
            ->where('threads.data.1.messagesCount', 1));
    }

    public function test_an_email_opens_within_its_whole_conversation_newest_first(): void
    {
        $account = Account::factory()->create();
        $admin = User::factory()->withAccount($account)->create();

        [$first, $reply] = Tenancy::for($account, function () {
            $mailbox = Mailbox::factory()->create();

            return [
                Email::factory()->create([
                    'mailbox_id' => $mailbox->id, 'provider_thread_id' => 't1', 'subject' => 'Planning',
                    'from_name' => 'Tom Bakker', 'from_email' => 'tom@haarlem.nl',
                    'content_text' => 'Kan het vrijdag?', 'received_at' => now()->subDay(),
                ]),
                Email::factory()->create([
                    'mailbox_id' => $mailbox->id, 'provider_thread_id' => 't1', 'subject' => 'RE: Planning',
                    'from_name' => 'Sanne de Vries', 'from_email' => 'sanne@noordkade.nl',
                    'content_html' => '<p>Prima, tot vrijdag.</p>', 'content_text' => 'Prima, tot vrijdag.',
                    'quotes' => [
                        ['name' => 'Tom Bakker', 'email' => 'tom@haarlem.nl', 'date' => null, 'date_text' => '30 Sep', 'text' => 'Kan het vrijdag?'],
                        ['name' => 'Iemand', 'email' => 'elders@example.com', 'date' => null, 'date_text' => null, 'text' => 'Niet in dit gesprek.'],
                    ],
                    'received_at' => now(),
                ]),
                Email::factory()->create(['mailbox_id' => $mailbox->id, 'provider_thread_id' => 'other']),
            ];
        });

        $this->actingAs($admin)->get(route('emails.show', $first))->assertInertia(fn (Assert $page) => $page
            ->component('emails/show')
            ->where('currentId', $first->id)
            ->where('subject', 'Planning')
            ->where('participants', ['Tom Bakker', 'Sanne de Vries'])
            ->has('messages', 2)
            ->where('messages.0.id', $reply->id)
            ->where('messages.0.contentHtml', '<p>Prima, tot vrijdag.</p>')
            ->where('messages.0.excerpt', 'Prima, tot vrijdag.')
            ->where('messages.0.quotes.0.messageId', $first->id)
            ->where('messages.0.quotes.1.messageId', null)
            ->where('messages.1.id', $first->id));
    }
}
