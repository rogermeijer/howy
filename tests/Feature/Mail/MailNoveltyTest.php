<?php

namespace Tests\Feature\Mail;

use App\Enums\EmailSource;
use App\Enums\FingerprintUse;
use App\Enums\InterpretationOutcome;
use App\Enums\InterpretationStatus;
use App\Enums\SenderAccess;
use App\Facades\Tenancy;
use App\Jobs\Mail\InterpretEmail;
use App\Models\Account;
use App\Models\Email;
use App\Models\EmailFingerprint;
use App\Models\EmailInterpretation;
use App\Models\KnowledgeFact;
use App\Models\Mailbox;
use App\Services\Gmail\StoreGmailMessage;
use App\Services\Knowledge\Ai\Agents\EmailClassifier;
use App\Services\Mail\Interpretation\InterpretationPresenter;
use App\Services\Mail\MailFingerprints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\Concerns\FakesGmailSending;
use Tests\Concerns\FakesKnowledgeAi;
use Tests\Fixtures\GmailFixtures;
use Tests\TestCase;

/**
 * Mail is read once. What Howy read before comes back quoted under replies,
 * forwarded, through a second mailbox or with its quote markers lost; each
 * time it is recognised and not read again.
 */
class MailNoveltyTest extends TestCase
{
    use FakesGmailSending;
    use FakesKnowledgeAi, RefreshDatabase;

    private const string POLICY = 'Vanaf 1 januari hebben medewerkers recht op 26 vakantiedagen per jaar, ook parttimers naar rato.';

    private const string NEWS = 'De kerstborrel is verplaatst naar vrijdag 18 december in de kantine op de tweede verdieping.';

    private Account $account;

    private Mailbox $mailbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create(['locale' => 'nl']);
        $this->mailbox = Tenancy::for($this->account, fn () => Mailbox::factory()->rules(senders: ['haarlem.nl' => SenderAccess::LearnAndAnswer])->create(['email_address' => 'kennis@noordkade.nl']));

        $this->fakeGmailSend();
        $this->fakeKnowledgeAi();
        $this->fakeMailAi(['intent' => 'information', 'statements' => []]);
    }

    public function test_a_reply_that_quotes_everything_gives_the_reader_only_the_new_line(): void
    {
        $first = $this->email(['content_text' => "Hoi allemaal,\n\n".self::POLICY]);
        $this->interpret($first);

        // The reply's client did not mark its quote: the old paragraph is in its text.
        $reply = $this->email([
            'provider_message_id' => 'reply',
            'message_id_header' => '<reply@mail.gmail.com>',
            'in_reply_to' => '<original@mail.gmail.com>',
            'content_text' => self::NEWS."\n\n".self::POLICY,
        ]);
        $this->interpret($reply);

        EmailClassifier::assertPrompted(fn (AgentPrompt $prompt) => str_contains($prompt->prompt, self::NEWS)
            && str_contains($prompt->prompt, '<mail>')
            && ! str_contains($this->mailPart($prompt->prompt), '26 vakantiedagen'));
    }

    public function test_a_forward_without_a_note_is_not_read_again(): void
    {
        $this->interpret($this->email(['content_text' => self::POLICY]));

        $forward = $this->email([
            'provider_message_id' => 'fwd',
            'provider_thread_id' => 'thread-fwd',
            'message_id_header' => '<fwd@mail.gmail.com>',
            'subject' => 'Fwd: Vakantiedagen',
            'content_text' => null,
            'body_text' => null,
            'quotes' => [['name' => 'Tom Bakker', 'email' => 'tom@haarlem.nl', 'date' => null, 'date_text' => null, 'text' => '> '.self::POLICY]],
        ]);
        $interpretation = $this->interpret($forward);

        $this->assertSame(InterpretationStatus::Done, $interpretation->status);
        $this->assertSame(InterpretationOutcome::Duplicate, $interpretation->outcome);
        $this->assertSame(__('Nothing new: this mail only repeats what is already known.'), app(InterpretationPresenter::class)->full($interpretation)['summary']);
        $this->assertSame('email', $interpretation->citations[0]['type'] ?? null);
        EmailClassifier::assertPromptedTimes(1);
    }

    public function test_a_mail_forwarded_as_an_attachment_is_known_by_its_message_id(): void
    {
        $this->interpret($this->email(['content_text' => self::POLICY]));

        $message = GmailFixtures::message('fwd-att', ['subject' => 'Fwd: Vakantiedagen', 'threadId' => 'thread-att']);
        $message['payload']['headers'][1] = ['name' => 'To', 'value' => 'kennis@noordkade.nl'];
        $message['payload'] = [
            'mimeType' => 'multipart/mixed',
            'headers' => $message['payload']['headers'],
            'parts' => [
                ['mimeType' => 'text/plain', 'body' => ['size' => 0, 'data' => GmailFixtures::encode('')]],
                [
                    'mimeType' => 'message/rfc822',
                    'filename' => 'Vakantiedagen.eml',
                    'headers' => [['name' => 'Content-Type', 'value' => 'message/rfc822']],
                    'body' => ['size' => 900, 'attachmentId' => 'att-eml'],
                    'parts' => [[
                        'mimeType' => 'text/plain',
                        'headers' => [
                            ['name' => 'From', 'value' => 'Tom Bakker <tom@haarlem.nl>'],
                            ['name' => 'Message-ID', 'value' => '<original@mail.gmail.com>'],
                            ['name' => 'Subject', 'value' => 'Vakantiedagen'],
                            ['name' => 'Content-Type', 'value' => 'text/plain; charset="UTF-8"'],
                        ],
                        'body' => ['size' => 100, 'data' => GmailFixtures::encode('Iets anders geformuleerd, maar hetzelfde bericht.')],
                    ]],
                ],
            ],
        ];

        // Stored, it is queued and (synchronously in tests) read right away.
        $email = Tenancy::for($this->account, fn () => app(StoreGmailMessage::class)->store($this->mailbox, $message, EmailSource::Push));

        $this->assertSame('<original@mail.gmail.com>', $email->quotes[0]['message_id'] ?? null);
        $this->assertTrue($email->quotes[0]['forwarded'] ?? false);

        $interpretation = Tenancy::for($this->account, fn () => $email->interpretation()->sole());
        $this->assertSame(InterpretationOutcome::Duplicate, $interpretation->outcome);
        EmailClassifier::assertPromptedTimes(1);
    }

    public function test_the_same_mail_in_a_second_mailbox_is_read_once(): void
    {
        $this->interpret($this->email(['content_text' => self::POLICY]));

        $second = Tenancy::for($this->account, fn () => Mailbox::factory()->create(['email_address' => 'hr@noordkade.nl']));
        $copy = $this->email(['mailbox_id' => $second->id, 'provider_message_id' => 'copy', 'content_text' => self::POLICY]);
        $interpretation = $this->interpret($copy);

        $this->assertSame(InterpretationStatus::Skipped, $interpretation->status);
        $this->assertSame(__('This mail was already read through :mailbox.', ['mailbox' => 'kennis@noordkade.nl']), $interpretation->error);
        EmailClassifier::assertPromptedTimes(1);
        $this->assertSame(0, Tenancy::for($this->account, fn () => KnowledgeFact::query()->count()));
    }

    public function test_a_mail_only_answered_through_one_mailbox_is_still_learned_from_through_another(): void
    {
        $first = $this->email(['content_text' => self::POLICY]);
        Tenancy::for($this->account, fn () => EmailInterpretation::query()->create(['email_id' => $first->id, 'sender_access' => SenderAccess::Answer]));
        $this->interpret($first);

        $second = Tenancy::for($this->account, fn () => Mailbox::factory()->create(['email_address' => 'hr@noordkade.nl']));
        $copy = $this->email(['mailbox_id' => $second->id, 'provider_message_id' => 'copy', 'content_text' => self::POLICY]);
        $interpretation = $this->interpret($copy);

        $this->assertSame(InterpretationStatus::Done, $interpretation->status);
        EmailClassifier::assertPromptedTimes(2);
    }

    public function test_howys_own_reply_quoted_back_is_not_read_as_information(): void
    {
        $this->fakeMailAi(['intent' => 'question', 'question' => 'Hoeveel vakantiedagen?'], answer: fn (): array => [
            'answered' => true, 'answer' => 'Je hebt recht op 26 vakantiedagen per jaar, ook als parttimer naar rato.', 'sources' => [],
        ]);
        $question = $this->email(['content_text' => 'Hoeveel vakantiedagen krijg ik volgend jaar eigenlijk precies?']);
        $answered = $this->interpret($question);

        $this->assertTrue(Tenancy::for($this->account, fn () => EmailFingerprint::query()->where('handled_as', FingerprintUse::Reply)->exists()));

        $thanks = $this->email([
            'provider_message_id' => 'thanks',
            'message_id_header' => '<thanks@mail.gmail.com>',
            'in_reply_to' => '<reply-1@mail.gmail.com>',
            'content_text' => null,
            'body_text' => null,
            'quotes' => [['name' => 'Howy', 'email' => 'kennis@noordkade.nl', 'date' => null, 'date_text' => null, 'text' => (string) $answered->reply_text]],
        ]);

        $this->assertSame(InterpretationOutcome::Duplicate, $this->interpret($thanks)->outcome);
    }

    public function test_reading_a_mail_again_starts_from_a_clean_reading(): void
    {
        $email = $this->email(['content_text' => self::POLICY]);
        $this->interpret($email);
        $again = $this->interpret($email, force: true);

        // Its own fingerprints do not make it old news to itself.
        $this->assertNotSame(InterpretationOutcome::Duplicate, $again->outcome);
        EmailClassifier::assertPromptedTimes(2);
    }

    public function test_a_new_mail_written_straight_to_howy_is_always_read(): void
    {
        $this->interpret($this->email(['content_text' => self::POLICY]));

        $again = $this->email(['provider_message_id' => 'again', 'provider_thread_id' => 'thread-2', 'message_id_header' => '<again@mail.gmail.com>', 'content_text' => self::POLICY]);
        $this->interpret($again);

        EmailClassifier::assertPromptedTimes(2);
    }

    public function test_the_backfill_fingerprints_mail_read_before(): void
    {
        $email = $this->email(['content_text' => self::POLICY]);
        Tenancy::for($this->account, fn () => EmailInterpretation::query()->create(['email_id' => $email->id, 'mode' => 'copied', 'status' => InterpretationStatus::Done]));

        $this->artisan('emails:fingerprint')->assertSuccessful();

        Tenancy::for($this->account, function () use ($email) {
            $this->assertSame(MailFingerprints::hash(self::POLICY), $email->fresh()?->content_hash);
            $this->assertTrue(EmailFingerprint::query()->where('email_id', $email->id)->where('kind', 'message')->exists());
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function email(array $attributes = []): Email
    {
        return Tenancy::for($this->account, fn () => Email::factory()->create([
            'mailbox_id' => $this->mailbox->id,
            'provider_thread_id' => 'thread-1',
            'message_id_header' => '<original@mail.gmail.com>',
            'subject' => 'Vakantiedagen',
            'from_name' => 'Tom Bakker',
            'from_email' => 'tom@haarlem.nl',
            'to' => [['name' => null, 'email' => 'kennis@noordkade.nl']],
            ...$attributes,
        ]));
    }

    private function interpret(Email $email, bool $force = false): EmailInterpretation
    {
        Tenancy::for($this->account, fn () => InterpretEmail::dispatchSync($email->id, $force));

        return Tenancy::for($this->account, fn () => $email->interpretation()->sole());
    }

    private function mailPart(string $prompt): string
    {
        return preg_match('/<mail>(.*?)<\/mail>/s', $prompt, $m) === 1 ? $m[1] : '';
    }
}
