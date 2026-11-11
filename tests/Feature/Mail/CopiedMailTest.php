<?php

namespace Tests\Feature\Mail;

use App\Enums\FactStatus;
use App\Enums\InterpretationMode;
use App\Enums\InterpretationOutcome;
use App\Enums\ReplyStatus;
use App\Facades\Tenancy;
use App\Jobs\Mail\InterpretEmail;
use App\Models\Account;
use App\Models\Email;
use App\Models\EmailInterpretation;
use App\Models\KnowledgeFact;
use App\Models\Mailbox;
use App\Models\User;
use App\Services\Knowledge\Ai\Agents\EmailClassifier;
use App\Services\Knowledge\Ai\Agents\QuestionAnswerer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\Concerns\FakesGmailSending;
use Tests\Concerns\FakesKnowledgeAi;
use Tests\TestCase;

/**
 * The mailbox only copied: Howy listens. It answers nothing, writes to no
 * one, and files what the people in the conversation say.
 */
class CopiedMailTest extends TestCase
{
    use FakesGmailSending;
    use FakesKnowledgeAi, RefreshDatabase;

    private const string LEAVE = 'Medewerkers hebben recht op 25 vakantiedagen per jaar.';

    private Account $account;

    private Mailbox $mailbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create(['locale' => 'nl']);
        $this->mailbox = Tenancy::for($this->account, fn () => Mailbox::factory()->create(['email_address' => 'kennis@noordkade.nl']));

        $this->fakeGmailSend();
        $this->fakeKnowledgeAi();
    }

    public function test_a_question_to_someone_else_is_left_to_them(): void
    {
        $this->fact(self::LEAVE);
        $this->fakeMailAi(answer: fn (): array => ['answered' => true, 'answer' => '25 dagen.', 'sources' => ['F1'], 'confidence' => 0.9]);

        $question = $this->question();
        $interpretation = $this->interpret($question);

        $this->assertSame(InterpretationMode::Copied, $interpretation->mode);
        $this->assertSame(InterpretationOutcome::NoAction, $interpretation->outcome);
        $this->assertNull($interpretation->answer);
        $this->assertSame(ReplyStatus::None, $interpretation->reply_status);
        QuestionAnswerer::assertNeverPrompted();
        Http::assertNothingSent();

        $user = User::factory()->withAccount($this->account)->create();

        $this->actingAs($user)
            ->get(route('emails.show', $question))
            ->assertInertia(fn (Assert $page) => $page
                ->where('messages.0.interpretation.mode', 'copied')
                ->where('messages.0.interpretation.outcome', 'no_action'));
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
}
