<?php

namespace Tests\Feature\Mail;

use App\Facades\Tenancy;
use App\Models\Account;
use App\Services\Mail\Interpretation\ReplyComposer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The replies as designed: HTML with the same message as plain text, in the
 * mail's language, and never trusting what the model or the sender wrote.
 */
class ReplyComposerTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create(['locale' => 'nl', 'timezone' => 'Europe/Amsterdam']);
    }

    public function test_an_answer_shows_the_question_the_answer_what_is_missing_and_the_sources(): void
    {
        $reply = $this->composer()->answer(
            'Hoeveel vakantiedagen heb ik?',
            'Je hebt recht op 25 vakantiedagen per jaar.',
            [['type' => 'document', 'id' => 1, 'title' => 'Personeelshandboek', 'section_id' => 3, 'heading_path' => 'Verlof', 'page' => 4]],
            'Of je ze mag meenemen, staat niet in de kennisbank.',
            'nl',
        );

        foreach (['Antwoord uit de kennisbank', 'Je vroeg', 'Hoeveel vakantiedagen heb ik?', 'Je hebt recht op 25 vakantiedagen per jaar.', 'Nog niet in de kennisbank', 'Of je ze mag meenemen', 'Bron', 'Personeelshandboek', '§ Verlof · p. 4', 'Antwoord gewoon op deze mail.'] as $expected) {
            $this->assertStringContainsString($expected, $reply->html);
        }

        $this->assertStringContainsString('Je vroeg: "Hoeveel vakantiedagen heb ik?"', $reply->text);
        $this->assertStringContainsString('- Personeelshandboek, § Verlof · p. 4', $reply->text);
        $this->assertStringContainsString('<html lang="nl">', $reply->html);
    }

    public function test_a_suggestion_names_the_asker_and_says_only_the_recipient_sees_it(): void
    {
        $reply = $this->composer()->suggestion(
            'Roger Meijer',
            CarbonImmutable::parse('2026-10-01 18:47:00', 'UTC'),
            'Welke certificaten hebben we?',
            'ISO 27001 en SOC 2 Type II.',
            [],
            'Tot wanneer ze geldig zijn.',
            0.98,
            'nl',
        );

        foreach (['Alleen voor jou', 'Roger Meijer vroeg je iets waar de kennisbank deels het antwoord op weet.', 'RM', '1 oktober, 20:47', 'Mogelijk antwoord', 'Weet jij het wel? Zet het in je antwoord aan Roger Meijer', 'Roger Meijer ziet deze suggestie niet.', 'Zeker: 98%'] as $expected) {
            $this->assertStringContainsString($expected, $reply->html);
        }

        $this->assertStringNotContainsString('Bronnen', $reply->html);
    }

    public function test_a_conflict_sets_each_statement_against_the_knowledge_base(): void
    {
        $reply = $this->composer()->conflict([
            ['statement' => 'Je krijgt 30 dagen.', 'subject' => null, 'valid_from' => null, 'verdict' => 'conflict', 'fact_id' => null, 'existing_fact_id' => 7, 'existing_statement' => 'Je krijgt 25 dagen.', 'explanation' => '25 tegen 30.'],
            ['statement' => 'Het kantoor is dicht op 5 december.', 'subject' => null, 'valid_from' => null, 'verdict' => 'new', 'fact_id' => 8, 'existing_fact_id' => null, 'existing_statement' => null, 'explanation' => null],
        ], [7 => ['type' => 'document', 'id' => 1, 'title' => 'Personeelshandboek', 'section_id' => null, 'heading_path' => 'Verlof', 'page' => null]], 'nl');

        foreach (['Ter goedkeuring', 'Eén punt spreekt de kennisbank tegen', 'Het andere punt uit je mail staat nu in de kennisbank.', 'Jij schreef', 'Je krijgt 30 dagen.', 'De kennisbank zegt', 'Je krijgt 25 dagen.', 'Personeelshandboek, § Verlof', '25 tegen 30.', 'Wat nu', 'Toegevoegd:', 'Het kantoor is dicht op 5 december.'] as $expected) {
            $this->assertStringContainsString($expected, $reply->html);
        }
    }

    public function test_not_found_and_another_language(): void
    {
        $reply = $this->composer()->notFound('Where can I park?', 'en');

        $this->assertStringContainsString('No answer yet', $reply->html);
        $this->assertStringContainsString('The knowledge base does not know about this yet.', $reply->html);
        $this->assertStringContainsString('<html lang="en">', $reply->html);
        $this->assertSame('en', app()->getLocale(), 'The locale is restored after composing.');
    }

    public function test_what_the_model_or_the_sender_wrote_is_escaped(): void
    {
        $reply = $this->composer()->answer('<b>vraag</b>', "Regel één\n<script>alert(1)</script>", [], null, 'nl');

        $this->assertStringNotContainsString('<script>', $reply->html);
        $this->assertStringNotContainsString('<b>vraag</b>', $reply->html);
        $this->assertStringContainsString('&lt;script&gt;', $reply->html);
        $this->assertStringContainsString('Regel één<br />', $reply->html);
    }

    private function composer(): ReplyComposer
    {
        Tenancy::set($this->account);

        return app(ReplyComposer::class);
    }
}
