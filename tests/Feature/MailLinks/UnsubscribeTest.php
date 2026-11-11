<?php

namespace Tests\Feature\MailLinks;

use App\Enums\MailLinkPurpose;
use App\Enums\OutboundMailKind;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Colleague;
use App\Models\Mailbox;
use App\Models\OutboundMail;
use App\Services\Adoption\Welcomes;
use App\Services\Mail\MailLinks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\FakesGmailSending;
use Tests\TestCase;

class UnsubscribeTest extends TestCase
{
    use FakesGmailSending;
    use RefreshDatabase;

    private Account $account;

    private Colleague $anna;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeGmailSend();
        $this->account = Account::factory()->create(['name' => 'Noordkade', 'locale' => 'nl']);
        $this->account->forceFill(['live_at' => now()])->save();
        $this->anna = Tenancy::for($this->account, function (): Colleague {
            $mailbox = Mailbox::factory()->create(['email_address' => 'howy@noordkade.nl']);

            return Colleague::factory()->create(['email' => 'anna@noordkade.nl', 'home_mailbox_id' => $mailbox->id]);
        });
    }

    public function test_every_one_of_howys_own_mails_carries_its_way_out(): void
    {
        Tenancy::for($this->account, fn () => app(Welcomes::class)->send($this->anna));

        $welcome = Tenancy::for($this->account, fn () => OutboundMail::query()->where('kind', OutboundMailKind::Welcome)->sole());
        $url = $welcome->headers['List-Unsubscribe'] ?? '';

        $this->assertMatchesRegularExpression('#^<'.preg_quote(url('/m/unsubscribe'), '#').'/[A-Za-z0-9_-]{100,200}>$#', $url);
        $this->assertSame('List-Unsubscribe=One-Click', $welcome->headers['List-Unsubscribe-Post'] ?? null);
        // The same link in the footer.
        $this->assertStringContainsString(e(trim($url, '<>')), $welcome->html);
    }

    public function test_the_link_unsubscribes_and_can_bring_them_back(): void
    {
        $token = $this->token($this->anna);

        // Opened in the language of the mail, whatever the browser says.
        $this->withHeader('Accept-Language', 'en')
            ->get(route('mail-links.unsubscribe.show', $token))
            ->assertInertia(fn (Assert $page) => $page
                ->component('mail-links/unsubscribe')
                ->where('company', 'Noordkade')
                ->where('email', 'anna@noordkade.nl')
                ->where('unsubscribed', false)
                ->where('locale', 'nl')
                ->where('translations.Unsubscribe', 'Uitschrijven'));

        $this->post(route('mail-links.unsubscribe.store', $token))->assertRedirect();
        $this->assertNotNull(Tenancy::for($this->account, fn () => $this->anna->refresh()->opted_out_at));

        $this->post(route('mail-links.unsubscribe.store', $token), ['subscribe' => '1'])->assertRedirect();
        $this->assertNull(Tenancy::for($this->account, fn () => $this->anna->refresh()->opted_out_at));
    }

    public function test_a_mail_clients_one_click_unsubscribes_without_a_session(): void
    {
        $this->post(route('mail-links.unsubscribe.store', $this->token($this->anna)), ['List-Unsubscribe' => 'One-Click'])
            ->assertOk();

        $this->assertNotNull(Tenancy::for($this->account, fn () => $this->anna->refresh()->opted_out_at));
    }

    public function test_a_link_this_app_did_not_make_or_for_another_page_is_not_found(): void
    {
        $token = $this->token($this->anna);
        $middle = intdiv(strlen($token), 2);
        $changed = substr_replace($token, $token[$middle] === 'A' ? 'B' : 'A', $middle, 1);

        $this->get(route('mail-links.unsubscribe.show', 'not-a-token'))->assertNotFound();
        $this->get(route('mail-links.unsubscribe.show', $changed))->assertNotFound();
        $this->post(route('mail-links.unsubscribe.store', $changed))->assertNotFound();

        // A review link is no way to unsubscribe.
        $review = Tenancy::for($this->account, fn () => app(MailLinks::class)->token(MailLinkPurpose::Review, $this->anna->id, $this->anna->email, 'nl'));
        $this->get(route('mail-links.unsubscribe.show', $review))->assertNotFound();
    }

    public function test_a_link_reads_only_its_own_accounts_records(): void
    {
        $theirs = Tenancy::for(Account::factory()->create(), function (): Colleague {
            $mailbox = Mailbox::factory()->create(['email_address' => 'howy@elders.nl']);

            return Colleague::factory()->create(['email' => 'eva@elders.nl', 'home_mailbox_id' => $mailbox->id]);
        });

        // This account's token, another account's colleague: not found, and untouched.
        $token = app(MailLinks::class)->token(MailLinkPurpose::Unsubscribe, $theirs->id, 'eva@elders.nl', 'nl', $this->account->id);

        $this->get(route('mail-links.unsubscribe.show', $token))->assertNotFound();
        $this->post(route('mail-links.unsubscribe.store', $token))->assertNotFound();

        $this->assertNull(Tenancy::withoutTenancy(fn () => $theirs->refresh()->opted_out_at));
    }

    public function test_the_link_to_unsubscribe_never_expires(): void
    {
        $token = $this->token($this->anna);

        $this->travel(2)->years();

        $this->get(route('mail-links.unsubscribe.show', $token))->assertOk();
    }

    private function token(Colleague $colleague): string
    {
        return Tenancy::for($this->account, fn () => app(MailLinks::class)->token(MailLinkPurpose::Unsubscribe, $colleague->id, $colleague->email, 'nl'));
    }
}
