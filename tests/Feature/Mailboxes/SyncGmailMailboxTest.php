<?php

namespace Tests\Feature\Mailboxes;

use App\Enums\EmailSource;
use App\Enums\MailboxStatus;
use App\Facades\Tenancy;
use App\Jobs\StartGmailWatch;
use App\Jobs\SyncGmailMailbox;
use App\Models\Account;
use App\Models\Email;
use App\Models\Mailbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\GmailFixtures;
use Tests\TestCase;

class SyncGmailMailboxTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create();
    }

    public function test_it_stores_new_inbox_messages_and_advances_the_history_id(): void
    {
        $this->fakeHistory(['m1', 'm2'], latest: '1005');
        $mailbox = $this->mailbox();

        $this->sync($mailbox, EmailSource::Push);

        $emails = Tenancy::for($this->account, fn () => Email::query()->orderBy('provider_message_id')->get());

        $this->assertSame(['m1', 'm2'], $emails->pluck('provider_message_id')->all());
        $this->assertSame(EmailSource::Push, $emails->first()?->source);
        $this->assertSame('Body of m1', $emails->first()?->body_text);
        $this->assertSame('<p>Body of m1</p>', $emails->first()?->content_html);
        $this->assertSame('Body of m1', $emails->first()?->content_text);
        $this->assertSame([], $emails->first()?->quotes);
        $this->assertSame(
            ['From', 'To', 'Subject', 'Date', 'Message-ID'],
            array_column($emails->first()?->headers ?? [], 'name'),
        );
        $this->assertSame('1005', $mailbox->refresh()->history_id);
        $this->assertNotNull($mailbox->last_synced_at);
        $this->assertNotNull($mailbox->last_message_at);
    }

    public function test_a_message_delivered_twice_is_stored_once(): void
    {
        $this->fakeHistory(['m1'], latest: '1005');
        $mailbox = $this->mailbox();

        $this->sync($mailbox);
        $mailbox->forceFill(['history_id' => '1000'])->save();
        $this->sync($mailbox);

        $this->assertSame(1, Tenancy::for($this->account, fn () => Email::query()->count()));
    }

    public function test_a_refused_refresh_token_flags_the_mailbox_for_reconnecting(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $mailbox = $this->mailbox(['token_expires_at' => now()->subMinute()]);

        $this->sync($mailbox);

        $this->assertSame(MailboxStatus::NeedsReauth, $mailbox->refresh()->status);
        $this->assertSame('invalid_grant', $mailbox->last_error);
    }

    public function test_an_expired_token_is_refreshed_before_calling_gmail(): void
    {
        $this->fakeHistory([], latest: '1001', extra: [
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh', 'expires_in' => 3599]),
        ]);

        $mailbox = $this->mailbox(['token_expires_at' => now()->subMinute()]);

        $this->sync($mailbox);

        $this->assertSame('fresh', $mailbox->refresh()->access_token);
        $this->assertSame('1001', $mailbox->history_id);
    }

    public function test_a_token_revoked_before_its_expiry_is_refreshed_and_retried(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh', 'expires_in' => 3599]),
            GmailFixtures::API.'/history*' => Http::sequence()
                ->push(['error' => ['code' => 401]], 401)
                ->push(['historyId' => '1002']),
        ]);

        $mailbox = $this->mailbox();

        $this->sync($mailbox);

        $this->assertSame('fresh', $mailbox->refresh()->access_token);
        $this->assertSame('1002', $mailbox->history_id);
        $this->assertSame(MailboxStatus::Active, $mailbox->status);
    }

    public function test_an_aged_out_history_id_falls_back_to_the_last_day(): void
    {
        Http::fake([
            GmailFixtures::API.'/history*' => Http::response(['error' => ['code' => 404]], 404),
            GmailFixtures::API.'/messages?*' => Http::response(['messages' => [['id' => 'm9', 'threadId' => 't9']]]),
            GmailFixtures::API.'/messages/m9*' => Http::response(GmailFixtures::message('m9')),
            GmailFixtures::API.'/profile' => Http::response(['emailAddress' => 'support@noordkade.nl', 'historyId' => '5000']),
        ]);

        $mailbox = $this->mailbox();

        $this->sync($mailbox);

        $this->assertSame(['m9'], Tenancy::for($this->account, fn () => Email::query()->pluck('provider_message_id')->all()));
        $this->assertSame('5000', $mailbox->refresh()->history_id);
    }

    public function test_watching_records_the_expiry_and_keeps_an_existing_history_id(): void
    {
        config(['services.google.pubsub_topic' => 'projects/cc/topics/gmail']);

        Http::fake([
            GmailFixtures::API.'/watch' => Http::response(['historyId' => '9999', 'expiration' => '1791360000000']),
        ]);

        $mailbox = $this->mailbox(['history_id' => '1000', 'watch_expires_at' => null]);

        Tenancy::for($this->account, fn () => StartGmailWatch::dispatch($mailbox->id));

        $mailbox->refresh();

        $this->assertSame('1000', $mailbox->history_id);
        $this->assertSame(1791360000, $mailbox->watch_expires_at?->getTimestamp());
    }

    public function test_without_a_topic_watching_only_records_where_to_start_polling(): void
    {
        config(['services.google.pubsub_topic' => null]);

        Http::fake([
            GmailFixtures::API.'/profile' => Http::response(['emailAddress' => 'support@noordkade.nl', 'historyId' => '4242']),
        ]);

        $mailbox = $this->mailbox(['history_id' => null]);

        Tenancy::for($this->account, fn () => StartGmailWatch::dispatch($mailbox->id));

        $this->assertSame('4242', $mailbox->refresh()->history_id);
        Http::assertSentCount(1);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function mailbox(array $attributes = []): Mailbox
    {
        return Tenancy::for($this->account, fn () => Mailbox::factory()->create([
            'email_address' => 'support@noordkade.nl',
            ...$attributes,
        ]));
    }

    private function sync(Mailbox $mailbox, EmailSource $source = EmailSource::Poll): void
    {
        // Built inside the account, run through the queue like the real thing.
        Tenancy::for($this->account, fn () => SyncGmailMailbox::dispatch($mailbox->id, $source));
    }

    /**
     * @param  list<string>  $ids
     * @param  array<string, mixed>  $extra
     */
    private function fakeHistory(array $ids, string $latest, array $extra = []): void
    {
        $fakes = [
            GmailFixtures::API.'/history*' => Http::response([
                'history' => [[
                    'id' => $latest,
                    'messagesAdded' => array_map(
                        fn (string $id): array => ['message' => ['id' => $id, 'threadId' => "thread-{$id}", 'labelIds' => ['INBOX']]],
                        $ids,
                    ),
                ]],
                'historyId' => $latest,
            ]),
        ];

        foreach ($ids as $id) {
            $fakes[GmailFixtures::API."/messages/{$id}*"] = Http::response(GmailFixtures::message($id));
        }

        Http::fake([...$extra, ...$fakes]);
    }
}
