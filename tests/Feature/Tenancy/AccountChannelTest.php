<?php

namespace Tests\Feature\Tenancy;

use App\Enums\AccountTopic;
use App\Enums\EmailSource;
use App\Enums\InterpretationStatus;
use App\Enums\OutboundMailStatus;
use App\Enums\ProcessingStatus;
use App\Events\AccountChanged;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\DocumentVersion;
use App\Models\Email;
use App\Models\EmailInterpretation;
use App\Models\Mailbox;
use App\Models\OutboundMail;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Broadcasting\UniqueBroadcastEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The one broadcast channel: an account's, for its members only, carrying a
 * topic and never data. See "Broadcasting" in docs/multi-tenancy.md.
 */
class AccountChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_a_member_of_the_account_may_listen(): void
    {
        // A broadcaster that signs, with the channels registered on it.
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => '1',
        ]);
        require base_path('routes/channels.php');

        $ours = Account::factory()->create();
        $theirs = Account::factory()->create();
        $member = User::factory()->withAccount($ours)->create();
        $auth = action([BroadcastController::class, 'authenticate']);
        $listen = fn (string $channel): array => ['socket_id' => '1234.5678', 'channel_name' => $channel];

        $this->post($auth, $listen("private-account.{$ours->id}"))->assertForbidden();

        $this->actingAs($member)->post($auth, $listen("private-account.{$ours->id}"))
            ->assertOk()
            ->assertJsonStructure(['auth']);
        $this->actingAs($member)->post($auth, $listen("private-account.{$theirs->id}"))->assertForbidden();
        $this->actingAs($member)->post($auth, $listen("private-App.Models.User.{$member->id}"))->assertForbidden();
    }

    public function test_the_account_channel_is_the_only_one(): void
    {
        preg_match_all("/Broadcast::channel\(\s*'([^']+)'/", (string) file_get_contents(base_path('routes/channels.php')), $matches);

        $this->assertSame(['account.{account}'], $matches[1]);
    }

    public function test_a_mail_logged_or_moved_on_is_heard_as_a_topic_only(): void
    {
        Event::fake([AccountChanged::class]);
        $account = Account::factory()->create();

        $mail = Tenancy::for($account, fn () => OutboundMail::factory()->create());

        $this->assertSame(1, $this->heard(AccountTopic::SentMails));
        Event::assertDispatched(AccountChanged::class, fn (AccountChanged $event): bool => $event->accountId === $account->id
            && $event->topic === AccountTopic::SentMails
            && $event->broadcastOn()->name === "private-account.{$account->id}"
            && $event->broadcastWith() === ['topic' => 'sent-mails']);

        // Only a change of status is news; anything else stays quiet.
        Tenancy::for($account, function () use ($mail): void {
            OutboundMail::query()->findOrFail($mail->id)->forceFill(['subject' => 'Anders'])->save();
            OutboundMail::query()->findOrFail($mail->id)->forceFill(['status' => OutboundMailStatus::Sent])->save();
        });

        $this->assertSame(2, $this->heard(AccountTopic::SentMails));
    }

    public function test_mail_that_comes_in_and_howy_reading_it_are_heard_but_an_import_is_not(): void
    {
        Event::fake([AccountChanged::class]);
        $account = Account::factory()->create();

        Tenancy::for($account, function (): void {
            $mailbox = Mailbox::factory()->create();
            Email::factory()->count(3)->create(['mailbox_id' => $mailbox->id, 'source' => EmailSource::Import]);
        });
        $this->assertSame(0, $this->heard(AccountTopic::Mail));

        $interpretation = Tenancy::for($account, function (): EmailInterpretation {
            $email = Email::factory()->create(['mailbox_id' => Mailbox::factory()->create()->id, 'source' => EmailSource::Push]);

            return EmailInterpretation::factory()->create(['email_id' => $email->id, 'status' => InterpretationStatus::Queued]);
        });
        $this->assertSame(2, $this->heard(AccountTopic::Mail));
        $this->assertSame(0, $this->heard(AccountTopic::Approvals));

        // Read, and something now waits for an admin: the count moves too.
        Tenancy::for($account, fn () => EmailInterpretation::query()->findOrFail($interpretation->id)
            ->forceFill(['status' => InterpretationStatus::Done, 'needs_review' => true])->save());

        $this->assertSame(3, $this->heard(AccountTopic::Mail));
        $this->assertSame(1, $this->heard(AccountTopic::Approvals));
    }

    public function test_a_document_is_heard_when_it_comes_to_wait_for_approval_not_on_the_way(): void
    {
        Event::fake([AccountChanged::class]);
        $account = Account::factory()->create();

        $version = Tenancy::for($account, fn () => DocumentVersion::factory()->create(['status' => ProcessingStatus::Queued, 'approved_at' => null]));
        Tenancy::for($account, fn () => DocumentVersion::query()->findOrFail($version->id)->forceFill(['status' => ProcessingStatus::Extracting])->save());
        $this->assertSame(0, $this->heard(AccountTopic::Approvals));

        Tenancy::for($account, fn () => DocumentVersion::query()->findOrFail($version->id)->forceFill(['status' => ProcessingStatus::Review])->save());
        $this->assertSame(1, $this->heard(AccountTopic::Approvals));

        Tenancy::for($account, fn () => DocumentVersion::query()->findOrFail($version->id)->forceFill(['approved_at' => now()])->save());
        $this->assertSame(2, $this->heard(AccountTopic::Approvals));
    }

    public function test_a_burst_of_changes_goes_out_once_per_topic(): void
    {
        Queue::fake();
        $account = Account::factory()->create();

        foreach (range(1, 5) as $ignored) {
            AccountChanged::dispatch($account->id, AccountTopic::Mail);
        }
        AccountChanged::dispatch($account->id, AccountTopic::Approvals);
        AccountChanged::dispatch(Account::factory()->create()->id, AccountTopic::Mail);

        // One per account and topic while it waits in the queue.
        Queue::assertPushed(UniqueBroadcastEvent::class, 3);
    }

    /**
     * How often a topic was heard.
     */
    private function heard(AccountTopic $topic): int
    {
        return Event::dispatched(AccountChanged::class, fn (AccountChanged $event): bool => $event->topic === $topic)->count();
    }
}
