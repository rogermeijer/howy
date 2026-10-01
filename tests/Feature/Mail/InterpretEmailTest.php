<?php

namespace Tests\Feature\Mail;

use App\Enums\EmailIntent;
use App\Enums\EmailSource;
use App\Enums\FactStatus;
use App\Enums\InterpretationMode;
use App\Enums\InterpretationOutcome;
use App\Enums\InterpretationStatus;
use App\Enums\ReplyStatus;
use App\Enums\SendPolicy;
use App\Facades\Tenancy;
use App\Jobs\Mail\InterpretEmail;
use App\Models\Account;
use App\Models\AiUsageRecord;
use App\Models\Email;
use App\Models\EmailInterpretation;
use App\Models\KnowledgeFact;
use App\Models\Mailbox;
use App\Models\User;
use App\Services\Gmail\GmailClient;
use App\Services\Gmail\StoreGmailMessage;
use App\Services\Knowledge\Ai\Agents\EmailClassifier;
use App\Services\Knowledge\Ai\Agents\FactConflictChecker;
use App\Services\Knowledge\Ai\Agents\QuestionAnswerer;
use App\Services\Knowledge\Search\KnowledgeSearch;
use App\Services\Knowledge\Search\SearchQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Prompts\AgentPrompt;
use RuntimeException;
use Tests\Concerns\FakesKnowledgeAi;
use Tests\Fixtures\GmailFixtures;
use Tests\TestCase;

class InterpretEmailTest extends TestCase
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

        Http::fake([GmailClient::BASE_URL.'/messages/send' => Http::response(['id' => 'reply-1', 'threadId' => 'thread-1'])]);
    }

    public function test_new_mail_addressed_to_the_mailbox_is_queued_for_interpretation(): void
    {
        Queue::fake();
        $mailbox = Tenancy::for($this->account, fn () => Mailbox::factory()->create(['email_address' => 'support@noordkade.nl']));

        $email = Tenancy::for($this->account, fn () => app(StoreGmailMessage::class)->store($mailbox, GmailFixtures::message('m1'), EmailSource::Push));

        Queue::assertPushed(InterpretEmail::class, fn (InterpretEmail $job) => $job->emailId === $email->id
            && $job->tenantAccountId === $this->account->id);
        $this->assertSame(InterpretationStatus::Queued, Tenancy::for($this->account, fn () => $email->interpretation()->sole()->status));

        // Stored again (a poll overlapping a push): not queued a second time.
        Tenancy::for($this->account, fn () => app(StoreGmailMessage::class)->store($mailbox, GmailFixtures::message('m1'), EmailSource::Poll));
        Queue::assertPushed(InterpretEmail::class, 1);
    }

    public function test_imported_and_own_mail_is_not_interpreted(): void
    {
        Queue::fake();
        $store = app(StoreGmailMessage::class);

        Tenancy::for($this->account, function () use ($store): void {
            $support = Mailbox::factory()->create(['email_address' => 'support@noordkade.nl']);
            $store->store($support, GmailFixtures::message('imported'), EmailSource::Import);
            $store->store($support, GmailFixtures::message('own', ['from' => 'Support <support@noordkade.nl>']), EmailSource::Push);
        });

        Queue::assertNotPushed(InterpretEmail::class);
        $this->assertSame(0, Tenancy::for($this->account, fn () => EmailInterpretation::query()->count()));
    }

    public function test_mail_the_mailbox_is_only_copied_on_is_interpreted_in_a_listening_role(): void
    {
        Queue::fake();

        // Addressed to support@, so the mailbox under test is only copied.
        $email = Tenancy::for($this->account, fn () => app(StoreGmailMessage::class)->store($this->mailbox, GmailFixtures::message('cc'), EmailSource::Push));

        Queue::assertPushed(InterpretEmail::class, fn (InterpretEmail $job) => $job->emailId === $email->id);
        $this->assertSame(InterpretationMode::Copied, Tenancy::for($this->account, fn () => $email->interpretation()->sole()->mode));
    }

    public function test_automated_mail_is_not_interpreted(): void
    {
        Queue::fake();

        $support = Tenancy::for($this->account, fn () => Mailbox::factory()->create(['email_address' => 'support@noordkade.nl']));

        foreach ([['Auto-Submitted', 'auto-replied'], ['Precedence', 'bulk'], ['List-Id', '<news.example.com>']] as $index => [$name, $value]) {
            $message = GmailFixtures::message("auto{$index}");
            $message['payload']['headers'][] = ['name' => $name, 'value' => $value];

            Tenancy::for($this->account, fn () => app(StoreGmailMessage::class)->store($support, $message, EmailSource::Push));
        }

        Queue::assertNotPushed(InterpretEmail::class);
    }

    public function test_a_question_is_answered_from_the_knowledge_base_in_its_thread(): void
    {
        $this->fakeKnowledgeAi();
        $fact = $this->fact(self::LEAVE);
        $this->fakeMailAi(answer: function (string $prompt): array {
            $this->assertStringContainsString(self::LEAVE, $prompt);
            $this->assertStringNotContainsString('tom@haarlem.nl', $prompt);

            return ['answered' => true, 'answer' => 'Je hebt recht op 25 vakantiedagen per jaar.', 'sources' => ['F1']];
        });

        $email = $this->email(['content_text' => "Hoeveel vakantiedagen heb ik?\n\nTom (tom@haarlem.nl)"]);
        $interpretation = $this->interpret($email);

        $this->assertSame(InterpretationStatus::Done, $interpretation->status);
        $this->assertSame(EmailIntent::Question, $interpretation->intent);
        $this->assertSame(InterpretationOutcome::Answered, $interpretation->outcome);
        $this->assertSame('Hoeveel vakantiedagen heb ik per jaar?', $interpretation->question);
        $this->assertSame('document', $interpretation->citations[0]['type'] ?? null);
        $this->assertSame($fact->document_id, $interpretation->citations[0]['id'] ?? null);
        $this->assertSame(ReplyStatus::Sent, $interpretation->reply_status);
        $this->assertSame('reply-1', $interpretation->reply_provider_message_id);

        EmailClassifier::assertPrompted(fn (AgentPrompt $prompt) => str_contains($prompt->prompt, '[email]')
            && ! str_contains($prompt->prompt, 'tom@haarlem.nl'));

        Http::assertSent(function (Request $request): bool {
            $raw = $this->decodedRaw($request);

            return $request->url() === GmailClient::BASE_URL.'/messages/send'
                && $request['threadId'] === 'thread-1'
                && str_contains($raw, 'In-Reply-To: <original@mail.gmail.com>')
                && str_contains($raw, 'To: tom@haarlem.nl')
                && str_contains($raw, 'Subject: Re: Vakantiedagen')
                && str_contains($raw, 'Je hebt recht op 25 vakantiedagen per jaar.')
                && str_contains($raw, 'Bronnen:')
                // The designed HTML goes along with the plain text.
                && str_contains($raw, 'Content-Type: text/html')
                && str_contains($raw, 'Antwoord uit de kennisbank')
                && str_contains($raw, 'Je vroeg')
                // The wordmark rides along as an inline image, referenced by cid.
                && str_contains($raw, 'Content-Type: multipart/related')
                && preg_match('/Content-Type: image\/png; name=cc-logo\.png.*?Content-Disposition: inline/s', $raw) === 1
                && preg_match('/<img src=3D"cid:[^"]+@symfony"|<img src="cid:[^"]+@symfony"/', $raw) === 1;
        });

        $this->assertTrue(Tenancy::for($this->account, fn () => AiUsageRecord::query()->where('step', 'mail_classify')->where('subject_type', 'email')->exists()));
        $this->assertTrue(Tenancy::for($this->account, fn () => AiUsageRecord::query()->where('step', 'mail_answer')->exists()));
    }

    public function test_an_answer_tells_the_sender_what_the_knowledge_base_does_not_know_yet(): void
    {
        $this->fakeKnowledgeAi();
        $this->fact(self::LEAVE);
        $this->fakeMailAi(answer: fn (): array => [
            'answered' => true,
            'answer' => 'Je hebt recht op 25 vakantiedagen per jaar.',
            'missing' => 'Of je ze mag meenemen naar volgend jaar, staat niet in de kennisbank.',
            'sources' => ['F1'],
            'confidence' => 0.8,
        ]);

        $interpretation = $this->interpret($this->email());

        $this->assertSame(InterpretationOutcome::Answered, $interpretation->outcome);
        $this->assertStringContainsString('Nog niet in de kennisbank: Of je ze mag meenemen', (string) $interpretation->reply_text);
    }

    public function test_a_reply_copies_in_everyone_else_the_mail_was_addressed_to(): void
    {
        $this->fakeKnowledgeAi();
        $this->fakeMailAi();

        $interpretation = $this->interpret($this->email([
            'to' => [['name' => null, 'email' => 'kennis@noordkade.nl'], ['name' => 'Anna', 'email' => 'Anna@noordkade.nl']],
            'cc' => [['name' => null, 'email' => 'piet@extern.nl'], ['name' => null, 'email' => 'tom@haarlem.nl']],
        ]));

        $this->assertSame(['tom@haarlem.nl', 'anna@noordkade.nl', 'piet@extern.nl'], $interpretation->reply_recipients);

        Http::assertSent(function (Request $request): bool {
            $raw = $this->decodedRaw($request);

            return str_contains($raw, 'To: tom@haarlem.nl')
                && str_contains($raw, 'Cc: anna@noordkade.nl, piet@extern.nl')
                && ! str_contains($raw, 'kennis@noordkade.nl, ');
        });
    }

    public function test_only_the_people_the_send_policy_allows_are_copied_in(): void
    {
        $this->fakeKnowledgeAi();
        $this->fakeMailAi();
        $this->mailbox->update(['send_policy' => SendPolicy::Always, 'send_blacklist' => ['extern.nl']]);

        $interpretation = $this->interpret($this->email([
            'cc' => [['name' => null, 'email' => 'anna@noordkade.nl'], ['name' => null, 'email' => 'piet@extern.nl']],
        ]));

        $this->assertSame(['tom@haarlem.nl', 'anna@noordkade.nl'], $interpretation->reply_recipients);
        Http::assertSent(fn (Request $request): bool => ! str_contains($this->decodedRaw($request), 'piet@extern.nl'));

        // The sender blocked: nothing goes out, not even to those copied in.
        $this->mailbox->update(['send_blacklist' => ['haarlem.nl']]);
        $blocked = $this->interpret($this->email(['cc' => [['name' => null, 'email' => 'anna@noordkade.nl']]]));

        $this->assertSame(ReplyStatus::Blocked, $blocked->reply_status);
        Http::assertSentCount(1);
    }

    public function test_a_question_without_an_answer_gets_a_not_found_reply(): void
    {
        $this->fakeKnowledgeAi();
        $this->fakeMailAi();

        $interpretation = $this->interpret($this->email());

        $this->assertSame(InterpretationOutcome::NotFound, $interpretation->outcome);
        $this->assertSame(ReplyStatus::Sent, $interpretation->reply_status);
        $this->assertStringContainsString('Hier weet de kennisbank nog niets over.', (string) $interpretation->reply_text);

        // Nothing to answer from: the answering model is not even asked.
        QuestionAnswerer::assertNeverPrompted();
    }

    public function test_new_information_waits_for_review_and_once_approved_is_found_citing_the_mail(): void
    {
        $this->fakeKnowledgeAi();
        $this->fakeMailAi(['intent' => 'information', 'question' => '', 'statements' => [
            ['statement' => 'De thuiswerkvergoeding is vanaf 1 januari 2027 3 euro per dag.', 'subject' => 'Thuiswerken', 'valid_from' => '2027-01-01', 'flag' => ''],
        ]]);

        $email = $this->email(['subject' => 'Thuiswerkvergoeding']);
        $interpretation = $this->interpret($email);

        $this->assertSame(InterpretationOutcome::Added, $interpretation->outcome);
        $this->assertSame(ReplyStatus::None, $interpretation->reply_status);
        $this->assertTrue($interpretation->needs_review);
        $this->assertSame('new', $interpretation->statements[0]['verdict'] ?? null);
        $this->assertSame('pending', $interpretation->statements[0]['review'] ?? null);
        $this->assertNull($interpretation->statements[0]['flag'] ?? null);

        $fact = Tenancy::for($this->account, fn () => KnowledgeFact::query()->sole());
        $this->assertSame($interpretation->statements[0]['fact_id'] ?? null, $fact->id);
        $this->assertSame('email', $fact->source_type);
        $this->assertSame($email->id, $fact->source_id);
        $this->assertSame(FactStatus::Proposed, $fact->status);
        $this->assertSame('thuiswerken', $fact->subject);
        $this->assertSame('2027-01-01', $fact->valid_from?->toDateString());
        $this->assertNotNull($fact->embedding);

        // Proposed: kept, but not found until someone approves it.
        $search = fn () => Tenancy::for($this->account, fn () => app(KnowledgeSearch::class)->search(new SearchQuery('thuiswerkvergoeding per dag')));
        $this->assertSame([], $search()->facts);

        $admin = User::factory()->withAccount($this->account)->create();
        $this->actingAs($admin)
            ->post(route('emails.statements.approve', ['email' => $email, 'statement' => 0]))
            ->assertRedirect();

        $this->assertSame($email->id, $search()->facts[0]->emailId ?? null);
        $this->assertSame('Thuiswerkvergoeding', $search()->facts[0]->emailSubject ?? null);
        Http::assertNothingSent();
    }

    public function test_interpreting_again_replaces_what_was_proposed_before(): void
    {
        $this->fakeKnowledgeAi();
        $this->fakeMailAi(['intent' => 'information', 'statements' => [['statement' => 'Eerste lezing.', 'subject' => null, 'valid_from' => null]]]);
        $email = $this->email();
        $this->interpret($email);

        $this->fakeMailAi(['intent' => 'information', 'statements' => [['statement' => 'Tweede lezing.', 'subject' => null, 'valid_from' => null]]]);
        $this->interpret($email, force: true);

        $this->assertSame(['Tweede lezing.'], Tenancy::for($this->account, fn () => KnowledgeFact::query()->pluck('statement')->all()));
    }

    public function test_information_the_knowledge_base_already_holds_is_not_added_again(): void
    {
        $this->fakeKnowledgeAi();
        $existing = $this->fact(self::LEAVE);
        $this->fakeMailAi(['intent' => 'information', 'statements' => [
            ['statement' => '  medewerkers hebben recht op 25 vakantiedagen  per jaar.', 'subject' => 'verlof', 'valid_from' => null],
        ]]);

        $interpretation = $this->interpret($this->email());

        $this->assertSame(InterpretationOutcome::Duplicate, $interpretation->outcome);
        $this->assertSame($existing->id, $interpretation->statements[0]['existing_fact_id'] ?? null);
        $this->assertSame(1, Tenancy::for($this->account, fn () => KnowledgeFact::query()->count()));
        FactConflictChecker::assertNeverPrompted();
    }

    public function test_contradicting_information_is_held_and_explained_to_the_sender(): void
    {
        $this->fakeKnowledgeAi();
        $existing = $this->fact(self::LEAVE);
        $this->fakeMailAi(
            ['intent' => 'information', 'statements' => [
                ['statement' => 'Medewerkers hebben recht op 30 vakantiedagen per jaar.', 'subject' => 'verlof', 'valid_from' => null],
                ['statement' => 'Het kantoor is op 5 december gesloten.', 'subject' => 'feestdagen', 'valid_from' => null],
            ]],
            verdicts: function (string $prompt) use ($existing): array {
                $this->assertStringContainsString("<fact id=\"{$existing->id}\"", $prompt);

                return ['verdicts' => [
                    ['id' => 'N1', 'verdict' => 'contradicts', 'existing_fact_id' => $existing->id, 'explanation' => 'De kennisbank noemt 25 dagen, de mail 30.'],
                ]];
            },
        );

        $interpretation = $this->interpret($this->email());

        $this->assertSame(InterpretationOutcome::Conflict, $interpretation->outcome);
        $this->assertSame('conflict', $interpretation->statements[0]['verdict'] ?? null);
        $this->assertSame(self::LEAVE, $interpretation->statements[0]['existing_statement'] ?? null);
        $this->assertSame('new', $interpretation->statements[1]['verdict'] ?? null);

        // The conflicting statement stays out; the other one goes in.
        $statements = Tenancy::for($this->account, fn () => KnowledgeFact::query()->pluck('statement')->all());
        $this->assertNotContains('Medewerkers hebben recht op 30 vakantiedagen per jaar.', $statements);
        $this->assertContains('Het kantoor is op 5 december gesloten.', $statements);

        $this->assertSame(ReplyStatus::Sent, $interpretation->reply_status);
        $reply = (string) $interpretation->reply_text;
        $this->assertStringContainsString('Je schreef: "Medewerkers hebben recht op 30 vakantiedagen per jaar."', $reply);
        $this->assertStringContainsString('De kennisbank zegt: "'.self::LEAVE.'"', $reply);
        $this->assertStringContainsString('De kennisbank noemt 25 dagen, de mail 30.', $reply);
        $this->assertStringContainsString('Het andere punt uit je mail staat nu in de kennisbank.', $reply);
        $this->assertStringContainsString('Toegevoegd: Het kantoor is op 5 december gesloten.', $reply);
    }

    public function test_a_reply_the_send_policy_does_not_allow_is_kept_but_not_sent(): void
    {
        $this->fakeKnowledgeAi();
        $this->fakeMailAi();

        foreach ([
            'off' => [SendPolicy::Off, [], []],
            'blacklisted domain' => [SendPolicy::Always, [], ['haarlem.nl']],
            'blacklisted address' => [SendPolicy::Always, [], ['TOM@haarlem.nl']],
            'other domain' => [SendPolicy::Domain, [], []],
            'not whitelisted' => [SendPolicy::Whitelist, ['jan@haarlem.nl'], []],
        ] as $case => [$policy, $whitelist, $blacklist]) {
            $this->mailbox->update(['send_policy' => $policy, 'send_whitelist' => $whitelist, 'send_blacklist' => $blacklist]);

            $interpretation = $this->interpret($this->email());

            $this->assertSame(ReplyStatus::Blocked, $interpretation->reply_status, $case);
            $this->assertNotNull($interpretation->reply_text, $case);
        }

        Http::assertNothingSent();
    }

    public function test_a_reply_goes_out_once_even_when_the_mail_is_interpreted_again(): void
    {
        $this->fakeKnowledgeAi();
        $this->fakeMailAi();
        $email = $this->email();

        $this->interpret($email);
        $this->interpret($email, force: true);
        // A redelivered job leaves a finished interpretation alone.
        $this->interpret($email);

        Http::assertSentCount(1);
        EmailClassifier::assertPromptedTimes(2);
    }

    public function test_without_an_ai_provider_the_mail_is_skipped(): void
    {
        config(['ai.providers.openai.key' => null]);

        $interpretation = $this->interpret($this->email());

        $this->assertSame(InterpretationStatus::Skipped, $interpretation->status);
        $this->assertNotNull($interpretation->error);
        Http::assertNothingSent();
    }

    public function test_a_failing_interpretation_is_marked_failed(): void
    {
        $this->fakeKnowledgeAi();
        $this->fakeMailAi();
        EmailClassifier::fake(fn () => throw new RuntimeException('Provider down'));

        $email = $this->email();

        try {
            $this->interpret($email);
            $this->fail('The job should have thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Provider down', $exception->getMessage());
        }

        $interpretation = Tenancy::for($this->account, fn () => $email->interpretation()->sole());
        $this->assertSame(InterpretationStatus::Failed, $interpretation->status);
        $this->assertSame('Provider down', $interpretation->error);
    }

    public function test_mail_that_is_not_about_anything_needs_no_action(): void
    {
        $this->fakeKnowledgeAi();
        $this->fakeMailAi(['intent' => 'other', 'summary' => 'Een bedankje.', 'question' => '']);

        $interpretation = $this->interpret($this->email());

        $this->assertSame(EmailIntent::Other, $interpretation->intent);
        $this->assertSame(InterpretationOutcome::NoAction, $interpretation->outcome);
        $this->assertSame(ReplyStatus::None, $interpretation->reply_status);
        Http::assertNothingSent();
    }

    public function test_the_command_interprets_a_mail_on_demand(): void
    {
        $this->fakeKnowledgeAi();
        $this->fakeMailAi(['intent' => 'other', 'question' => '']);
        $email = $this->email();

        $this->artisan('mail:interpret', ['account' => $this->account->id, 'email' => $email->id])
            ->expectsOutputToContain('no_action')
            ->assertSuccessful();

        $other = Account::factory()->create();
        $this->artisan('mail:interpret', ['account' => $other->id, 'email' => $email->id])->assertFailed();
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
            'content_text' => 'Hoeveel vakantiedagen heb ik?',
            ...$attributes,
        ]));
    }

    private function interpret(Email $email, bool $force = false): EmailInterpretation
    {
        Tenancy::for($this->account, fn () => InterpretEmail::dispatchSync($email->id, $force));

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
        return (string) base64_decode(strtr((string) $request['raw'], '-_', '+/'));
    }
}
