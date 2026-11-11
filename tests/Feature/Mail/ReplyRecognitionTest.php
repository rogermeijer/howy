<?php

namespace Tests\Feature\Mail;

use App\Enums\EmailSource;
use App\Enums\OutboundMailKind;
use App\Facades\Tenancy;
use App\Mail\Content\RenderedMail;
use App\Models\Account;
use App\Models\Colleague;
use App\Models\Email;
use App\Models\Mailbox;
use App\Models\OutboundMail;
use App\Models\User;
use App\Services\Gmail\StoreGmailMessage;
use App\Services\Knowledge\Ai\Agents\EmailClassifier;
use App\Services\Mail\HowyMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\Concerns\FakesGmailSending;
use Tests\Concerns\FakesKnowledgeAi;
use Tests\Fixtures\GmailFixtures;
use Tests\TestCase;

class ReplyRecognitionTest extends TestCase
{
    use FakesGmailSending;
    use FakesKnowledgeAi;
    use RefreshDatabase;

    private Account $account;

    private Mailbox $mailbox;

    private OutboundMail $welcome;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create(['locale' => 'nl']);
        // Live: Howy's own mails go.
        $this->account->forceFill(['live_at' => now()])->save();
        $this->mailbox = Tenancy::for($this->account, fn () => Mailbox::factory()->create(['email_address' => 'howy@noordkade.nl']));

        $this->fakeGmailSend('sent-1', 'thread-welcome');
        $this->welcome = Tenancy::for($this->account, fn () => app(HowyMailer::class)->toColleague(
            Colleague::factory()->create(['email' => 'anna@noordkade.nl', 'home_mailbox_id' => $this->mailbox->id]),
            OutboundMailKind::Welcome,
            new RenderedMail('Welkom bij Howy', '<p>Zet me in de Cc.</p>', 'Zet me in de Cc, dan leer ik mee.'),
        ));
    }

    public function test_a_reply_naming_howys_reference_is_recognised(): void
    {
        $inReplyTo = $this->store('m1', ['In-Reply-To' => (string) $this->welcome->reference]);
        $inReferences = $this->store('m2', ['References' => '<other@mail.gmail.com> '.$this->welcome->reference]);

        $this->assertSame($this->welcome->id, $inReplyTo->outbound_mail_id);
        $this->assertSame($this->welcome->id, $inReferences->outbound_mail_id);
    }

    public function test_a_made_up_reference_is_not_recognised(): void
    {
        $forged = preg_replace('/\.[a-f0-9]{16}@/', '.0123456789abcdef@', (string) $this->welcome->reference);

        $this->assertNull($this->store('m1', ['In-Reply-To' => (string) $forged])->outbound_mail_id);
    }

    public function test_another_accounts_reference_is_not_recognised_here(): void
    {
        $other = Account::factory()->create();
        $email = Tenancy::for($other, function (): Email {
            $mailbox = Mailbox::factory()->create(['email_address' => 'howy@elders.nl']);
            $message = GmailFixtures::message('m1', ['from' => 'Anna <anna@noordkade.nl>', 'threadId' => 'thread-welcome']);
            $message['payload']['headers'][] = ['name' => 'In-Reply-To', 'value' => (string) $this->welcome->reference];

            return app(StoreGmailMessage::class)->store($mailbox, $message, EmailSource::Push);
        });

        $this->assertNull($email->outbound_mail_id);
    }

    public function test_the_message_id_gmail_sent_it_with_is_recognised(): void
    {
        Tenancy::for($this->account, fn () => $this->welcome->forceFill(['message_id_header' => '<CAgmail@mail.gmail.com>'])->save());

        $email = $this->store('m1', ['In-Reply-To' => '<CAgmail@mail.gmail.com>'], threadId: 'thread-elsewhere');

        $this->assertSame($this->welcome->id, $email->outbound_mail_id);
    }

    public function test_a_mail_in_the_thread_of_howys_own_mail_answers_it(): void
    {
        $this->assertSame($this->welcome->id, $this->store('m1', [], threadId: 'thread-welcome')->outbound_mail_id);
        $this->assertNull($this->store('m2', [], threadId: 'thread-other')->outbound_mail_id);
    }

    public function test_howys_own_mail_is_no_reply_to_itself(): void
    {
        $email = $this->store('m1', ['In-Reply-To' => (string) $this->welcome->reference], from: 'Howy <howy@noordkade.nl>');

        $this->assertNull($email->outbound_mail_id);
    }

    public function test_a_reply_is_read_with_what_howy_wrote_and_shown_as_a_reply(): void
    {
        $this->fakeKnowledgeAi();
        $this->fakeMailAi(['intent' => 'other', 'question' => '']);

        // Read as it arrives: the queue runs in line here.
        $email = $this->store('m1', ['In-Reply-To' => (string) $this->welcome->reference], queued: false);

        EmailClassifier::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, '<earlier>')
            && str_contains($prompt->prompt, 'Zet me in de Cc, dan leer ik mee.'));

        $user = User::factory()->withAccount($this->account)->create();
        // No server-side rendering: its request is one nothing fakes.
        config(['inertia.ssr.enabled' => false]);

        $this->actingAs($user)
            ->get(route('emails.show', $email))
            ->assertInertia(fn (Assert $page) => $page
                ->where('messages.0.repliesTo.kind', 'welcome')
                ->where('messages.0.repliesTo.label', 'Welkom'));
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function store(string $id, array $headers, string $threadId = 'thread-new', string $from = 'Anna <anna@noordkade.nl>', bool $queued = true): Email
    {
        if ($queued) {
            Queue::fake();
        }

        Http::preventStrayRequests();

        $message = GmailFixtures::message($id, ['from' => $from, 'threadId' => $threadId]);

        foreach ($headers as $name => $value) {
            $message['payload']['headers'][] = ['name' => $name, 'value' => $value];
        }

        return Tenancy::for($this->account, fn () => app(StoreGmailMessage::class)->store($this->mailbox, $message, EmailSource::Push));
    }
}
