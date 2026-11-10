<?php

namespace Tests\Feature\Mail;

use App\Enums\DocumentType;
use App\Enums\FingerprintUse;
use App\Enums\InterpretationOutcome;
use App\Enums\ProcessingStatus;
use App\Enums\SenderAccess;
use App\Facades\Tenancy;
use App\Jobs\Mail\InterpretEmail;
use App\Models\Account;
use App\Models\Document;
use App\Models\DocumentSource;
use App\Models\Email;
use App\Models\EmailFingerprint;
use App\Models\EmailInterpretation;
use App\Models\Mailbox;
use App\Services\Gmail\GmailClient;
use App\Services\Knowledge\Ai\Agents\EmailClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\Concerns\FakesKnowledgeAi;
use Tests\TestCase;

/**
 * History Howy never read (a forward, a thread it was added to later) is a
 * source, read once: short, with the mail; long, filed as a document of its
 * own. The next reply quoting the same history brings nothing new.
 */
class LongThreadTest extends TestCase
{
    use FakesKnowledgeAi, RefreshDatabase;

    private const string DECISION = 'We hebben besloten dat de servicebus vanaf maart op dinsdag en donderdag rijdt, niet meer op vrijdag.';

    private Account $account;

    private Mailbox $mailbox;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('knowledge');
        Http::fake([GmailClient::BASE_URL.'/messages/send' => Http::response(['id' => 'reply-1', 'threadId' => 't'])]);
        Process::fake(['*pandoc*' => Process::result((string) json_encode(['blocks' => [
            ['t' => 'Header', 'c' => [1, ['', [], []], [['t' => 'Str', 'c' => 'Servicebus']]]],
            ['t' => 'Header', 'c' => [2, ['', [], []], [['t' => 'Str', 'c' => 'Anna']]]],
            ['t' => 'Para', 'c' => [['t' => 'Str', 'c' => self::DECISION]]],
        ]]))]);

        $this->account = Account::factory()->create(['locale' => 'nl']);
        $this->mailbox = Tenancy::for($this->account, fn () => Mailbox::factory()->create(['email_address' => 'kennis@noordkade.nl']));
        $this->fakeKnowledgeAi();
        $this->fakeMailAi(['intent' => 'information', 'statements' => [[
            'statement' => 'De servicebus rijdt vanaf maart op dinsdag en donderdag.',
            'subject' => 'servicebus',
            'valid_from' => null,
            'flag' => '',
            'from_history' => true,
        ]]]);
    }

    public function test_a_forward_with_short_history_is_read_as_a_source(): void
    {
        $forward = $this->email('fwd', 'Fwd: Servicebus', 'Ter info.', [$this->quote('Anna de Vries', self::DECISION)]);
        $interpretation = $this->interpret($forward);

        EmailClassifier::assertPrompted(fn (AgentPrompt $prompt) => str_contains($prompt->prompt, '<forwarded>')
            && str_contains($prompt->prompt, '<message from="Anna de Vries" date="2026-09-20">')
            && str_contains($prompt->prompt, 'servicebus vanaf maart')
            // Not also given as mere context.
            && ! str_contains($prompt->prompt, '<earlier>'));

        $this->assertSame(InterpretationOutcome::Added, $interpretation->outcome);
        $this->assertTrue($interpretation->statements[0]['from_history'] ?? false);
        $this->assertTrue(Tenancy::for($this->account, fn () => EmailFingerprint::query()->where('handled_as', FingerprintUse::Statements)->where('kind', 'message')->exists()));
    }

    public function test_long_history_is_filed_as_a_correspondence_document(): void
    {
        $email = $this->email('long', 'Re: Servicebus', 'Ik zet Howy erbij, zodat dit vastligt.', $this->longHistory());
        $interpretation = $this->interpret($email);

        EmailClassifier::assertPrompted(fn (AgentPrompt $prompt) => str_contains($prompt->prompt, '<history filed="document">')
            && ! str_contains($prompt->prompt, 'Bericht 3 over de servicebus'));

        Tenancy::for($this->account, function () use ($email) {
            $document = Document::query()->with('latestVersion')->sole();
            $this->assertSame(DocumentType::Correspondence, $document->type);
            $this->assertSame('Servicebus', $document->title);
            $this->assertSame(ProcessingStatus::Review, $document->latestVersion?->status);
            $this->assertTrue(DocumentSource::query()->where('email_id', $email->id)->exists());

            // Oldest first, one section per message.
            $markdown = (string) Storage::disk('knowledge')->get((string) $document->latestVersion?->path);
            $this->assertLessThan(strpos($markdown, 'Bericht 2 '), (int) strpos($markdown, 'Bericht 1 '));
            $this->assertStringContainsString('## Piet Jansen — ', $markdown);

            $this->assertTrue(EmailFingerprint::query()->where('handled_as', FingerprintUse::Document)->where('document_version_id', $document->latestVersion?->id)->exists());
            // Its statement waits for approval, beside the document.
            $this->assertTrue(EmailInterpretation::query()->where('email_id', $email->id)->value('needs_review'));
        });

        $this->assertNotNull($interpretation->outcome);
    }

    public function test_long_history_from_a_sender_howy_may_only_answer_is_not_filed(): void
    {
        $email = $this->email('long', 'Re: Servicebus', 'Wanneer rijdt de servicebus?', $this->longHistory());
        Tenancy::for($this->account, fn () => EmailInterpretation::query()->create(['email_id' => $email->id, 'sender_access' => SenderAccess::Answer]));

        $this->interpret($email);

        EmailClassifier::assertPrompted(fn (AgentPrompt $prompt) => ! str_contains($prompt->prompt, '<history')
            && ! str_contains($prompt->prompt, 'Bericht 3 over de servicebus'));
        $this->assertSame(0, Tenancy::for($this->account, fn () => Document::query()->count()));
        $this->assertSame(0, Tenancy::for($this->account, fn () => EmailFingerprint::query()->count()));
    }

    public function test_the_next_reply_quoting_the_same_history_adds_nothing(): void
    {
        $history = $this->longHistory();
        $this->interpret($this->email('long', 'Re: Servicebus', 'Ik zet Howy erbij, zodat dit vastligt.', $history));

        // A thanks that quotes everything again, including Howy's own reading of it.
        $thanks = $this->email('thanks', 'Re: Servicebus', null, [$this->quote('Tom Bakker', 'Ik zet Howy erbij, zodat dit vastligt.'), ...$history], inReplyTo: '<long@mail.gmail.com>');
        $interpretation = $this->interpret($thanks);

        $this->assertSame(InterpretationOutcome::Duplicate, $interpretation->outcome);
        EmailClassifier::assertPromptedTimes(1);
        $this->assertSame(1, Tenancy::for($this->account, fn () => Document::query()->count()));
    }

    public function test_the_same_history_forwarded_into_another_thread_is_recognised(): void
    {
        $this->interpret($this->email('fwd', 'Fwd: Servicebus', 'Ter info.', [$this->quote('Anna de Vries', self::DECISION)]));

        $again = $this->email('fwd2', 'Fwd: Servicebus', null, [$this->quote('Anna de Vries', '> '.self::DECISION)], thread: 'thread-other');

        $this->assertSame(InterpretationOutcome::Duplicate, $this->interpret($again)->outcome);
        EmailClassifier::assertPromptedTimes(1);
    }

    /**
     * @return list<array{name: string, email: string, date: string, date_text: null, text: string}>
     */
    private function longHistory(): array
    {
        $quotes = [];

        // Newest first, as a mail quotes them.
        foreach (range(9, 1) as $n) {
            $quotes[] = $this->quote($n % 2 === 0 ? 'Anna de Vries' : 'Piet Jansen', "Bericht {$n} over de servicebus. ".str_repeat("Dit is een lange alinea met overleg over de planning van de bus, nummer {$n}. ", 14), sprintf('2026-09-%02d', 10 + $n));
        }

        return $quotes;
    }

    /**
     * @return array{name: string, email: string, date: string, date_text: null, text: string}
     */
    private function quote(string $name, string $text, string $date = '2026-09-20'): array
    {
        return ['name' => $name, 'email' => strtolower(strtok($name, ' ')).'@noordkade.nl', 'date' => "{$date}T09:00:00+00:00", 'date_text' => null, 'text' => $text];
    }

    /**
     * @param  list<array<string, mixed>>  $quotes
     */
    private function email(string $id, string $subject, ?string $text, array $quotes, ?string $inReplyTo = null, string $thread = 'thread-1'): Email
    {
        return Tenancy::for($this->account, fn () => Email::factory()->create([
            'mailbox_id' => $this->mailbox->id,
            'provider_message_id' => $id,
            'provider_thread_id' => $thread,
            'message_id_header' => "<{$id}@mail.gmail.com>",
            'in_reply_to' => $inReplyTo,
            'subject' => $subject,
            'from_name' => 'Tom Bakker',
            'from_email' => 'tom@haarlem.nl',
            'to' => [['name' => null, 'email' => 'kennis@noordkade.nl']],
            'content_text' => $text,
            'body_text' => $text,
            'quotes' => $quotes,
        ]));
    }

    private function interpret(Email $email): EmailInterpretation
    {
        Tenancy::for($this->account, fn () => InterpretEmail::dispatchSync($email->id));

        return Tenancy::for($this->account, fn () => $email->interpretation()->sole());
    }
}
