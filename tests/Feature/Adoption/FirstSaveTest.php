<?php

namespace Tests\Feature\Adoption;

use App\Enums\EmailSource;
use App\Enums\FingerprintUse;
use App\Enums\OutboundMailKind;
use App\Enums\OutboundMailStatus;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Colleague;
use App\Models\EmailFingerprint;
use App\Models\Mailbox;
use App\Models\OutboundMail;
use App\Services\Adoption\FlowSender;
use App\Services\Gmail\GmailClient;
use App\Services\Gmail\StoreGmailMessage;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesGmailSending;
use Tests\Concerns\FakesKnowledgeAi;
use Tests\Fixtures\GmailFixtures;
use Tests\TestCase;

/**
 * Saved! (04): the thanks for the first mail after the welcome that Howy files
 * something from, to its sender alone.
 */
class FirstSaveTest extends TestCase
{
    use FakesGmailSending;
    use FakesKnowledgeAi;
    use RefreshDatabase;

    private Account $account;

    private Mailbox $mailbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeGmailSend('sent-1', 'thread-1');
        Http::preventStrayRequests();
        $this->fakeKnowledgeAi();

        $this->account = Account::factory()->create(['locale' => 'en']);
        $this->account->forceFill(['live_at' => now()])->save();
        $this->mailbox = Tenancy::for($this->account, fn () => Mailbox::factory()->create(['email_address' => 'howy@noordkade.nl', 'token_expires_at' => now()->addMonth()]));
    }

    public function test_the_first_contribution_after_the_welcome_is_thanked_for_to_its_sender_alone(): void
    {
        $anna = $this->welcomed('anna@noordkade.nl', 'Anna de Boer');
        $this->shares([
            'Medewerkers mogen drie dagen per week thuiswerken.',
            'De kantoortijden zijn van 8 tot 18 uur.',
            'Parkeren kan in garage Noord.',
            'De lunch begint om half een.',
        ]);

        $this->arrives('m1', 'Anna de Boer <anna@noordkade.nl>', 'Afspraken thuiswerken');

        Tenancy::for($this->account, function () use ($anna): void {
            $thanks = OutboundMail::query()->where('kind', OutboundMailKind::FirstSave)->sole();

            $this->assertSame(OutboundMailStatus::Sent, $thanks->status);
            $this->assertSame('anna@noordkade.nl', $thanks->recipient);
            $this->assertNull($thanks->cc);
            $this->assertNotNull($thanks->email_id);
            $this->assertStringContainsString('Thank you, Anna!', $thanks->text);
            $this->assertStringContainsString('Afspraken thuiswerken', $thanks->text);
            $this->assertStringContainsString('Medewerkers mogen drie dagen per week thuiswerken.', $thanks->text);
            // Three shown, the rest counted.
            $this->assertStringNotContainsString('De lunch begint om half een.', $thanks->text);
            $this->assertStringContainsString('…and 1 more point.', $thanks->text);
            // Waiting for an admin, and saying so; with the page to look it over.
            $this->assertStringContainsString('takes a quick look', $thanks->text);
            $this->assertStringContainsString('Review or adjust', $thanks->text);
            $this->assertStringContainsString(url('/m/review').'/', $thanks->text);
            $this->assertNotNull($anna->refresh()->first_saved_at);

            // What it wrote back is no news when quoted under a reply.
            $this->assertTrue(EmailFingerprint::query()->where('email_id', $thanks->email_id)->where('handled_as', FingerprintUse::Reply)->exists());
        });

        // A message of its own, not a reply in the thread the mail was in.
        Http::assertSent(fn (Request $request): bool => $request->url() === GmailClient::BASE_URL.'/messages/send'
            && ! isset($request['threadId']));
    }

    public function test_once_only(): void
    {
        $this->welcomed('anna@noordkade.nl', 'Anna');
        $this->shares(['Medewerkers mogen drie dagen per week thuiswerken.']);
        $this->arrives('m1', 'Anna <anna@noordkade.nl>', 'Thuiswerken');

        $this->shares(['De kantoortijden zijn van 8 tot 18 uur.']);
        $this->arrives('m2', 'Anna <anna@noordkade.nl>', 'Kantoortijden');

        $this->assertSame(1, Tenancy::for($this->account, fn () => OutboundMail::query()->where('kind', OutboundMailKind::FirstSave)->count()));
    }

    public function test_the_mail_that_brought_the_welcome_is_not_thanked_for_the_next_one_is(): void
    {
        $this->shares(['Medewerkers mogen drie dagen per week thuiswerken.']);
        $this->arrives('m1', 'Anna <anna@noordkade.nl>', 'Thuiswerken');

        $this->assertSame(['welcome'], $this->kinds());

        $this->travel(1)->hours();
        $this->shares(['De kantoortijden zijn van 8 tot 18 uur.']);
        $this->arrives('m2', 'Anna <anna@noordkade.nl>', 'Kantoortijden', at: now());

        $this->assertSame(['welcome', 'first_save'], $this->kinds());
    }

    public function test_nothing_filed_or_nothing_new_is_no_contribution(): void
    {
        $this->welcomed('anna@noordkade.nl', 'Anna');

        $this->fakeMailAi(['intent' => 'other', 'question' => '']);
        $this->arrives('m1', 'Anna <anna@noordkade.nl>', 'Dank je');

        $this->assertSame([], $this->kinds());
    }

    public function test_no_thanks_while_paused_or_for_someone_who_may_not_get_it(): void
    {
        $anna = $this->welcomed('anna@noordkade.nl', 'Anna');
        Tenancy::for($this->account, fn () => $anna->forceFill(['receives_mail' => false])->save());
        $this->shares(['Medewerkers mogen drie dagen per week thuiswerken.']);
        $this->arrives('m1', 'Anna <anna@noordkade.nl>', 'Thuiswerken');

        $jan = $this->welcomed('jan@noordkade.nl', 'Jan');
        $this->account->forceFill(['live_at' => null])->save();
        $this->shares(['De kantoortijden zijn van 8 tot 18 uur.']);
        $this->arrives('m2', 'Jan <jan@noordkade.nl>', 'Kantoortijden');

        $this->assertSame([], $this->kinds());
        $this->assertNull(Tenancy::for($this->account, fn () => $jan->refresh()->first_saved_at));
    }

    public function test_sent_again_it_is_written_afresh_from_the_same_mail(): void
    {
        $this->welcomed('anna@noordkade.nl', 'Anna');
        $this->shares(['Medewerkers mogen drie dagen per week thuiswerken.']);
        $this->arrives('m1', 'Anna <anna@noordkade.nl>', 'Thuiswerken');
        $thanks = Tenancy::for($this->account, fn () => OutboundMail::query()->where('kind', OutboundMailKind::FirstSave)->sole());

        $this->assertTrue(Tenancy::for($this->account, fn () => app(FlowSender::class)->resend($thanks)));

        Tenancy::for($this->account, function () use ($thanks): void {
            $again = OutboundMail::query()->where('kind', OutboundMailKind::FirstSave)->whereKeyNot($thanks->id)->sole();

            $this->assertSame($thanks->email_id, $again->email_id);
            $this->assertStringContainsString('Medewerkers mogen drie dagen per week thuiswerken.', $again->text);
        });
    }

    private function welcomed(string $email, string $name): Colleague
    {
        return Tenancy::for($this->account, function () use ($email, $name): Colleague {
            $colleague = Colleague::factory()->create(['email' => $email, 'name' => $name, 'home_mailbox_id' => $this->mailbox->id]);
            $colleague->forceFill(['welcomed_at' => now()->subDay()])->save();

            return $colleague;
        });
    }

    /**
     * @param  list<string>  $statements
     */
    private function shares(array $statements): void
    {
        $this->fakeMailAi([
            'intent' => 'information',
            'question' => '',
            'statements' => array_map(fn (string $statement): array => ['statement' => $statement, 'subject' => 'kantoor', 'valid_from' => null], $statements),
        ]);
    }

    private function arrives(string $id, string $from, string $subject, ?DateTimeInterface $at = null): void
    {
        $message = GmailFixtures::message($id, ['from' => $from, 'subject' => $subject, 'internalDate' => ($at ?? now())->getTimestamp() * 1000]);

        Tenancy::for($this->account, fn () => app(StoreGmailMessage::class)->store($this->mailbox, $message, EmailSource::Push));
    }

    /**
     * @return list<string>
     */
    private function kinds(): array
    {
        return Tenancy::for($this->account, fn () => OutboundMail::query()->orderBy('id')->get()->map(fn (OutboundMail $mail): string => $mail->kind->value)->all());
    }
}
