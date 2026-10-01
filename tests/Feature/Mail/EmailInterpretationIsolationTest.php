<?php

namespace Tests\Feature\Mail;

use App\Enums\EmailIntent;
use App\Enums\InterpretationOutcome;
use App\Enums\InterpretationStatus;
use App\Enums\ReplyStatus;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Email;
use App\Models\EmailInterpretation;
use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmailInterpretationIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_inbox_and_the_thread_show_how_a_mail_was_read(): void
    {
        $account = Account::factory()->create();
        $user = User::factory()->withAccount($account)->create();

        $email = Tenancy::for($account, function () {
            $email = Email::factory()->create(['mailbox_id' => Mailbox::factory()->create()->id]);
            EmailInterpretation::factory()->create([
                'email_id' => $email->id,
                'status' => InterpretationStatus::Done,
                'intent' => EmailIntent::Information,
                'intent_confidence' => 0.8,
                'outcome' => InterpretationOutcome::Conflict,
                'summary' => 'Nieuwe verlofregel.',
                'statements' => [[
                    'statement' => 'Medewerkers hebben recht op 30 vakantiedagen.',
                    'subject' => 'verlof',
                    'valid_from' => null,
                    'verdict' => 'conflict',
                    'fact_id' => null,
                    'existing_fact_id' => 999,
                    'existing_statement' => 'Medewerkers hebben recht op 25 vakantiedagen.',
                    'explanation' => '25 tegen 30.',
                ]],
                'reply_status' => ReplyStatus::Blocked,
                'reply_text' => 'Dank voor de informatie.',
            ]);

            return $email;
        });

        $this->actingAs($user)
            ->get(route('inbox'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('threads.data.0.interpretation.status', 'done')
                ->where('threads.data.0.interpretation.outcome', 'conflict'));

        $this->actingAs($user)
            ->get(route('emails.show', $email))
            ->assertInertia(fn (Assert $page) => $page
                ->where('messages.0.interpretation.intent', 'information')
                ->where('messages.0.interpretation.summary', 'Nieuwe verlofregel.')
                ->where('messages.0.interpretation.statements.0.verdict', 'conflict')
                // The fact it was compared with is gone: no source, no error.
                ->where('messages.0.interpretation.statements.0.existingSource', null)
                ->where('messages.0.interpretation.replyStatus', 'blocked')
                ->where('messages.0.interpretation.replyText', 'Dank voor de informatie.'));
    }

    public function test_another_accounts_interpretations_stay_invisible(): void
    {
        $ours = Account::factory()->create();
        $theirs = Account::factory()->create();
        $user = User::factory()->withAccount($ours)->create();

        $email = Tenancy::for($theirs, function () {
            $email = Email::factory()->create(['mailbox_id' => Mailbox::factory()->create()->id]);
            EmailInterpretation::factory()->create(['email_id' => $email->id, 'summary' => 'Van een ander account.']);

            return $email;
        });

        $this->assertSame(0, Tenancy::for($ours, fn () => EmailInterpretation::query()->count()));
        $this->assertNull(Tenancy::for($ours, fn () => EmailInterpretation::query()->where('email_id', $email->id)->first()));

        $this->actingAs($user)->get(route('emails.show', $email))->assertNotFound();
        $this->actingAs($user)
            ->get(route('inbox'))
            ->assertInertia(fn (Assert $page) => $page->has('threads.data', 0));
    }

    public function test_an_interpretation_takes_its_account_from_the_context(): void
    {
        $account = Account::factory()->create();

        $interpretation = Tenancy::for($account, fn () => EmailInterpretation::factory()->create());

        $this->assertSame($account->id, $interpretation->account_id);
    }
}
