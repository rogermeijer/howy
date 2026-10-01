<?php

namespace Tests\Feature\Mailboxes;

use App\Enums\EmailSource;
use App\Facades\Tenancy;
use App\Jobs\ImportGmailMessages;
use App\Models\Account;
use App\Models\Email;
use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\GmailFixtures;
use Tests\TestCase;

class ImportMailboxTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private User $admin;

    private Mailbox $mailbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create();
        $this->admin = User::factory()->withAccount($this->account)->create();
        $this->mailbox = Tenancy::for($this->account, fn () => Mailbox::factory()->create());
    }

    public function test_search_lists_messages_live_and_marks_the_ones_already_imported(): void
    {
        Tenancy::for($this->account, fn () => Email::factory()->create([
            'mailbox_id' => $this->mailbox->id,
            'provider_message_id' => 'm1',
        ]));

        Http::fake([
            GmailFixtures::API.'/messages?*' => Http::response([
                'messages' => [['id' => 'm1', 'threadId' => 't1'], ['id' => 'm2', 'threadId' => 't2']],
                'nextPageToken' => 'page-2',
                'resultSizeEstimate' => 24,
            ]),
            GmailFixtures::API.'/messages/m1*' => Http::response(GmailFixtures::message('m1')),
            GmailFixtures::API.'/messages/m2*' => Http::response(GmailFixtures::message('m2', ['subject' => 'Vergunning terras'])),
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('mailboxes.import.search', ['mailbox' => $this->mailbox, 'q' => 'from:haarlem.nl', 'range' => '30d']))
            ->assertOk()
            ->assertJsonPath('estimate', 24)
            ->assertJsonPath('nextPageToken', 'page-2')
            ->assertJsonPath('messages.0.id', 'm1')
            ->assertJsonPath('messages.0.alreadyImported', true)
            ->assertJsonPath('messages.1.subject', 'Vergunning terras')
            ->assertJsonPath('messages.1.fromName', 'Tom Bakker')
            ->assertJsonPath('messages.1.alreadyImported', false);

        Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'q=from:haarlem.nl newer_than:30d'));

        // Searching stores nothing.
        $this->assertSame(1, Tenancy::for($this->account, fn () => Email::query()->count()));
    }

    public function test_importing_queues_a_batch_for_messages_not_yet_stored(): void
    {
        Bus::fake();

        Tenancy::for($this->account, fn () => Email::factory()->create([
            'mailbox_id' => $this->mailbox->id,
            'provider_message_id' => 'm1',
        ]));

        $this->actingAs($this->admin)
            ->post(route('mailboxes.import.store', $this->mailbox), ['message_ids' => ['m1', 'm2', 'm3']])
            ->assertRedirect();

        Bus::assertBatched(fn (PendingBatch $batch) => $batch->jobs->count() === 1
            && $batch->jobs->first()->messageIds === ['m2', 'm3']
            && $batch->jobs->first()->tenantAccountId === $this->account->id
            && $batch->options['messages'] === 2);

        $this->assertNotNull($this->mailbox->refresh()->import_batch_id);
    }

    public function test_whole_threads_expands_to_every_message_in_them(): void
    {
        Bus::fake();

        Http::fake([
            GmailFixtures::API.'/messages/m2*' => Http::response(GmailFixtures::message('m2', ['threadId' => 't2'])),
            GmailFixtures::API.'/threads/t2*' => Http::response(['id' => 't2', 'messages' => [['id' => 'm0'], ['id' => 'm2']]]),
        ]);

        $this->actingAs($this->admin)
            ->post(route('mailboxes.import.store', $this->mailbox), ['message_ids' => ['m2'], 'whole_threads' => true]);

        Bus::assertBatched(fn (PendingBatch $batch) => $batch->jobs->first()->messageIds === ['m2', 'm0']);
    }

    public function test_the_import_job_turns_messages_into_emails(): void
    {
        Http::fake([
            GmailFixtures::API.'/messages/m1*' => Http::response(GmailFixtures::message('m1')),
            GmailFixtures::API.'/messages/m2*' => Http::response(GmailFixtures::message('m2')),
        ]);

        Tenancy::for($this->account, fn () => ImportGmailMessages::dispatch($this->mailbox->id, ['m1', 'm2'], $this->admin->id));

        $emails = Tenancy::for($this->account, fn () => Email::query()->get());

        $this->assertCount(2, $emails);
        $this->assertTrue($emails->every(fn (Email $email) => $email->source === EmailSource::Import
            && $email->imported_by_user_id === $this->admin->id));
    }

    public function test_members_who_are_not_admins_cannot_search_or_import(): void
    {
        $member = User::factory()->withAccount($this->account, isAdmin: false)->create();

        $this->actingAs($member)
            ->getJson(route('mailboxes.import.search', $this->mailbox))
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('mailboxes.import.store', $this->mailbox), ['message_ids' => ['m1']])
            ->assertForbidden();
    }
}
