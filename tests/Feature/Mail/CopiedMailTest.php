<?php

namespace Tests\Feature\Mail;

use App\Enums\FactStatus;
use App\Enums\InterpretationMode;
use App\Enums\InterpretationOutcome;
use App\Enums\ReplyStatus;
use App\Enums\SendPolicy;
use App\Facades\Tenancy;
use App\Jobs\Mail\InterpretEmail;
use App\Models\Account;
use App\Models\Email;
use App\Models\EmailInterpretation;
use App\Models\KnowledgeFact;
use App\Models\Mailbox;
use App\Models\User;
use App\Services\Gmail\GmailClient;
use App\Services\Knowledge\Ai\Agents\EmailClassifier;
use App\Services\Knowledge\Ai\Agents\QuestionAnswerer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\Concerns\FakesKnowledgeAi;
use Tests\TestCase;

/**
 * The mailbox only copied: Howy listens. It never writes to the sender, may
 * suggest an answer to whoever was asked, and files what they answer.
 */
class CopiedMailTest extends TestCase
{
    use FakesKnowledgeAi, RefreshDatabase;

    private const string LEAVE = 'Medewerkers hebben recht op 25 vakantiedagen per jaar.';

    private Account $account;

    private Mailbox $mailbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create(['locale' => 'nl']);
        $this->mailbox = Tenancy::for($this->account, fn () => Mailbox::factory()->create(['email_address' => 'kennis@noordkade.nl']));

        Http::fake([GmailClient::BASE_URL.'/messages/send' => Http::response(['id' => 'suggestion-1', 'threadId' => 'thread-1'])]);
        $this->fakeKnowledgeAi();
    }

    public function test_a_question_to_someone_else_gets_a_suggestion_to_them_only(): void
    {
        $this->fact(self::LEAVE);
        $this->fakeMailAi(answer: fn (): array => ['answered' => true, 'answer' => 'Je hebt recht op 25 vakantiedagen per jaar.', 'sources' => ['F1'], 'confidence' => 0.9]);

        $interpretation = $this->interpret($this->question());

        $this->assertSame(InterpretationMode::Copied, $interpretation->mode);
        $this->assertSame(InterpretationOutcome::Suggested, $interpretation->outcome);
        $this->assertSame(0.9, $interpretation->answer_confidence);
        $this->assertSame(ReplyStatus::Sent, $interpretation->reply_status);
        $this->assertSame(['anna@noordkade.nl'], $interpretation->reply_recipients);

        Http::assertSent(function (Request $request): bool {
            $raw = $this->decodedRaw($request);

            return $request['threadId'] === 'thread-1'
                && str_contains($raw, 'To: anna@noordkade.nl')
                && ! str_contains($raw, 'tom@haarlem.nl')
                && str_contains($raw, 'Tom Bakker vroeg:')
                && str_contains($raw, 'Je hebt recht op 25 vakantiedagen per jaar.')
                && str_contains($raw, 'alleen naar jou gestuurd, niet naar Tom Bakker');
        });
    }

    public function test_the_thread_shows_the_suggestion_and_who_it_went_to(): void
    {
        $this->fact(self::LEAVE);
        $this->fakeMailAi(answer: fn (): array => ['answered' => true, 'answer' => '25 dagen.', 'sources' => ['F1'], 'confidence' => 0.9]);
        $question = $this->question();
        $this->interpret($question);

        $user = User::factory()->withAccount($this->account)->create();

        $this->actingAs($user)
            ->get(route('emails.show', $question))
            ->assertInertia(fn (Assert $page) => $page
                ->where('messages.0.interpretation.mode', 'copied')
                ->where('messages.0.interpretation.outcome', 'suggested')
                ->where('messages.0.interpretation.answer', '25 dagen.')
                ->where('messages.0.interpretation.replyRecipients', ['anna@noordkade.nl']));
    }

    public function test_a_partial_answer_says_what_is_missing_and_the_answer_to_it_is_learned(): void
    {
        $this->fact('Het bedrijf is ISO 27001-gecertificeerd.');
        $this->fakeMailAi(['question' => 'Welke certificaten heeft het bedrijf en tot wanneer zijn ze geldig?'], answer: fn (): array => [
            'answered' => true,
            'answer' => 'Het bedrijf is ISO 27001-gecertificeerd.',
            'missing' => 'Tot wanneer het certificaat geldig is, staat niet in de kennisbank.',
            'sources' => ['F1'],
            'confidence' => 0.8,
        ]);

        $question = $this->question(['subject' => 'Certificaten?', 'content_text' => 'Welke certificaten hebben we en tot wanneer?']);
        $interpretation = $this->interpret($question);

        $this->assertSame(InterpretationOutcome::Suggested, $interpretation->outcome);
        $this->assertSame('Tot wanneer het certificaat geldig is, staat niet in de kennisbank.', $interpretation->answer_gaps);

        Http::assertSent(fn (Request $request): bool => str_contains($this->decodedRaw($request), 'Nog niet in de kennisbank: Tot wanneer het certificaat geldig is')
            && str_contains($this->decodedRaw($request), 'Weet jij het wel? Zet het in je antwoord aan Tom Bakker'));

        // Anna fills the gap in her answer: read with the gap as context, filed.
        $this->fakeMailAi([
            'intent' => 'information',
            'question' => '',
            'statements' => [['statement' => 'Het ISO 27001-certificaat is geldig tot oktober 2028.', 'subject' => 'certificering', 'valid_from' => null]],
        ]);

        $answer = $this->interpret($this->answer($question, 'ISO 27001 loopt tot oktober 2028.'));

        EmailClassifier::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, '<missing>Tot wanneer het certificaat geldig is'));
        $this->assertSame(InterpretationOutcome::Added, $answer->outcome);
        $this->assertTrue(Tenancy::for($this->account, fn () => KnowledgeFact::query()->where('statement', 'Het ISO 27001-certificaat is geldig tot oktober 2028.')->exists()));
    }

    public function test_an_answer_that_is_not_sure_enough_is_kept_but_not_suggested(): void
    {
        $this->fact(self::LEAVE);
        $this->fakeMailAi(answer: fn (): array => ['answered' => true, 'answer' => 'Waarschijnlijk 25 dagen.', 'sources' => ['F1'], 'confidence' => 0.5]);

        $interpretation = $this->interpret($this->question());

        $this->assertSame(InterpretationOutcome::Unsure, $interpretation->outcome);
        $this->assertSame('Waarschijnlijk 25 dagen.', $interpretation->answer);
        $this->assertSame(ReplyStatus::None, $interpretation->reply_status);
        Http::assertNothingSent();
    }

    public function test_without_an_answer_cc_stays_silent(): void
    {
        $this->fakeMailAi();

        $interpretation = $this->interpret($this->question());

        $this->assertSame(InterpretationOutcome::NotFound, $interpretation->outcome);
        $this->assertSame(ReplyStatus::None, $interpretation->reply_status);
        QuestionAnswerer::assertNeverPrompted();
        Http::assertNothingSent();
    }

    public function test_the_suggestion_goes_only_to_recipients_the_send_policy_allows(): void
    {
        $this->mailbox->update(['send_policy' => SendPolicy::Domain]);
        $this->fact(self::LEAVE);
        $this->fakeMailAi(answer: fn (): array => ['answered' => true, 'answer' => '25 dagen.', 'sources' => ['F1'], 'confidence' => 0.9]);

        $interpretation = $this->interpret($this->question([
            'to' => [['name' => null, 'email' => 'anna@noordkade.nl'], ['name' => null, 'email' => 'piet@extern.nl']],
        ]));

        $this->assertSame(['anna@noordkade.nl'], $interpretation->reply_recipients);
        Http::assertSent(fn (Request $request): bool => ! str_contains($this->decodedRaw($request), 'piet@extern.nl'));
    }

    public function test_the_answer_someone_gives_is_read_with_the_question_and_filed(): void
    {
        $question = $this->question();
        $this->fakeMailAi([
            'intent' => 'information',
            'question' => '',
            'statements' => [['statement' => 'Medewerkers mogen maximaal drie dagen per week thuiswerken.', 'subject' => 'thuiswerken', 'valid_from' => null]],
        ]);

        $answer = $this->answer($question, 'Ja, tot drie dagen per week.');
        $interpretation = $this->interpret($answer);

        // The short reply was read with the question it answers.
        EmailClassifier::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, '<earlier>')
            && str_contains($prompt->prompt, 'Hoeveel dagen mogen we thuiswerken?'));

        $this->assertSame(InterpretationMode::Copied, $interpretation->mode);
        $this->assertSame(InterpretationOutcome::Added, $interpretation->outcome);

        $fact = Tenancy::for($this->account, fn () => KnowledgeFact::query()->sole());
        $this->assertSame('Medewerkers mogen maximaal drie dagen per week thuiswerken.', $fact->statement);
        $this->assertSame($answer->id, $fact->source_id);
        Http::assertNothingSent();
    }

    public function test_an_answer_that_is_general_advice_is_flagged_for_the_reviewer(): void
    {
        $this->fakeMailAi([
            'intent' => 'information',
            'question' => '',
            'statements' => [[
                'statement' => 'Alle toegang tot opslag moet TLS 1.2 of hoger gebruiken.',
                'subject' => 'transportbeveiliging',
                'valid_from' => null,
                'flag' => 'Algemeen advies over TLS, niet hoe wij het geregeld hebben.',
            ]],
        ]);

        $interpretation = $this->interpret($this->answer($this->question(), 'Hier de kern: dwing TLS 1.2+ af.'));

        $this->assertTrue($interpretation->needs_review);
        $this->assertSame('Algemeen advies over TLS, niet hoe wij het geregeld hebben.', $interpretation->statements[0]['flag'] ?? null);
        $this->assertSame(FactStatus::Proposed, Tenancy::for($this->account, fn () => KnowledgeFact::query()->sole()->status));
    }

    public function test_an_answer_that_contradicts_the_knowledge_base_is_flagged_without_writing_to_anyone(): void
    {
        $existing = $this->fact(self::LEAVE);
        $this->fakeMailAi(
            ['intent' => 'information', 'question' => '', 'statements' => [
                ['statement' => 'Medewerkers hebben recht op 30 vakantiedagen per jaar.', 'subject' => 'verlof', 'valid_from' => null],
            ]],
            verdicts: fn (): array => ['verdicts' => [
                ['id' => 'N1', 'verdict' => 'contradicts', 'existing_fact_id' => $existing->id, 'explanation' => '25 tegen 30.'],
            ]],
        );

        $interpretation = $this->interpret($this->answer($this->question(), 'Je hebt er 30.'));

        $this->assertSame(InterpretationOutcome::Conflict, $interpretation->outcome);
        $this->assertSame(ReplyStatus::None, $interpretation->reply_status);
        $this->assertSame(1, Tenancy::for($this->account, fn () => KnowledgeFact::query()->count()));
        Http::assertNothingSent();
    }

    /**
     * Tom asks Anna, with the mailbox in CC.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function question(array $attributes = []): Email
    {
        return Tenancy::for($this->account, fn () => Email::factory()->create([
            'mailbox_id' => $this->mailbox->id,
            'provider_thread_id' => 'thread-1',
            'message_id_header' => '<question@mail.gmail.com>',
            'subject' => 'Thuiswerken',
            'from_name' => 'Tom Bakker',
            'from_email' => 'tom@haarlem.nl',
            'to' => [['name' => 'Anna', 'email' => 'anna@noordkade.nl']],
            'cc' => [['name' => null, 'email' => 'kennis@noordkade.nl']],
            'content_text' => 'Hoeveel dagen mogen we thuiswerken?',
            ...$attributes,
        ]));
    }

    /**
     * Anna answers Tom, keeping the mailbox in CC.
     */
    private function answer(Email $question, string $text): Email
    {
        return Tenancy::for($this->account, fn () => Email::factory()->create([
            'mailbox_id' => $this->mailbox->id,
            'provider_thread_id' => 'thread-1',
            'message_id_header' => '<answer@mail.gmail.com>',
            'in_reply_to' => $question->message_id_header,
            'subject' => 'Re: Thuiswerken',
            'from_name' => 'Anna',
            'from_email' => 'anna@noordkade.nl',
            'to' => [['name' => 'Tom Bakker', 'email' => 'tom@haarlem.nl']],
            'cc' => [['name' => null, 'email' => 'kennis@noordkade.nl']],
            'content_text' => $text,
        ]));
    }

    private function interpret(Email $email): EmailInterpretation
    {
        Tenancy::for($this->account, fn () => InterpretEmail::dispatchSync($email->id));

        return Tenancy::for($this->account, fn () => $email->interpretation()->sole());
    }

    private function fact(string $statement): KnowledgeFact
    {
        return Tenancy::for($this->account, fn () => KnowledgeFact::factory()->create([
            'statement' => $statement,
            'subject' => 'verlof',
            'content_hash' => hash('sha256', mb_strtolower($statement)),
            'embedding' => $this->fakeVector('verlof: '.$statement, (int) config('knowledge.embeddings.dimensions')),
            'embedding_model' => config('knowledge.embeddings.model'),
        ]));
    }

    private function decodedRaw(Request $request): string
    {
        // The body is quoted-printable: decode it, so long lines read whole.
        return quoted_printable_decode((string) base64_decode(strtr((string) $request['raw'], '-_', '+/')));
    }
}
