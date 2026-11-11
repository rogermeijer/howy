<?php

namespace Tests\Feature\Colleagues;

use App\Enums\EmailSource;
use App\Enums\SenderAccess;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Colleague;
use App\Models\Email;
use App\Models\Mailbox;
use App\Models\User;
use App\Services\Gmail\StoreGmailMessage;
use App\Services\Mailboxes\MailboxRuleEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\GmailFixtures;
use Tests\TestCase;

class RecordColleagueTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private Mailbox $mailbox;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->account = Account::factory()->create(['domain' => 'noordkade.nl']);
        $this->mailbox = Tenancy::for($this->account, fn () => Mailbox::factory()->rules(senders: [
            'klant.nl' => SenderAccess::LearnAndAnswer,
            'dochter.nl' => SenderAccess::Learn,
            'noordkade.nl' => SenderAccess::LearnAndAnswer,
        ])->create(['email_address' => 'support@noordkade.nl']));

        Tenancy::for($this->account, fn () => app(MailboxRuleEditor::class)->setForDomain($this->mailbox, 'dochter.nl', SenderAccess::Learn, createsColleagues: true));
    }

    public function test_someone_at_the_company_who_mails_becomes_a_colleague(): void
    {
        $email = $this->store('m1', 'Anna de Boer <Anna@Noordkade.nl>', at: '2026-09-29 10:00');
        $this->store('m2', 'Anna <anna@noordkade.nl>', at: '2026-10-02 09:00');
        // An earlier mail stored later moves first seen, not last mail.
        $this->store('m0', 'Anna <anna@noordkade.nl>', at: '2026-09-01 08:00');

        Tenancy::for($this->account, function () use ($email): void {
            $anna = Colleague::query()->sole();

            $this->assertSame('anna@noordkade.nl', $anna->email);
            $this->assertSame('Anna de Boer', $anna->name);
            $this->assertSame($this->mailbox->id, $anna->home_mailbox_id);
            $this->assertTrue($anna->receives_mail);
            $this->assertSame('2026-09-01 08:00', $anna->first_seen_at?->format('Y-m-d H:i'));
            $this->assertSame('2026-10-02 09:00', $anna->last_mail_at?->format('Y-m-d H:i'));
            $this->assertSame($anna->id, $email->refresh()->colleague_id);
            $this->assertSame(3, $anna->emails()->count());
        });
    }

    public function test_only_a_domain_rule_that_creates_colleagues_makes_them(): void
    {
        $this->store('m1', 'Piet <piet@klant.nl>');
        $this->store('m2', 'Eva <eva@dochter.nl>');

        Tenancy::for($this->account, function (): void {
            $this->assertSame(['eva@dochter.nl'], Colleague::query()->pluck('email')->all());
            // Made by a rule for the company's people, so the Howy mails are for them.
            $this->assertTrue(Colleague::query()->sole()->receives_mail);
        });
    }

    public function test_no_one_is_made_from_mail_howy_does_not_read_or_a_person_did_not_write(): void
    {
        Tenancy::for($this->account, fn () => $this->mailbox->ownDomainRule()?->update(['access' => SenderAccess::Ignore]));

        $this->store('m1', 'HR <hr@noordkade.nl>');
        $this->store('m2', 'Someone <someone@elsewhere.nl>');
        $this->store('m3', 'Support <support@noordkade.nl>');
        $this->store('m4', 'Anna <anna@noordkade.nl>', headers: [['name' => 'Auto-Submitted', 'value' => 'auto-replied']]);
        $this->store('m5', 'Jan <jan@noordkade.nl>', source: EmailSource::Import);

        Tenancy::for($this->account, fn () => $this->assertSame(0, Colleague::query()->count()));
    }

    public function test_an_import_links_mail_from_a_colleague_already_known(): void
    {
        $this->store('m1', 'Jan <jan@noordkade.nl>', at: '2026-10-01 10:00');
        $imported = $this->store('m2', 'Jan <jan@noordkade.nl>', at: '2026-06-01 10:00', source: EmailSource::Import);

        Tenancy::for($this->account, function () use ($imported): void {
            $jan = Colleague::query()->sole();

            $this->assertSame($jan->id, $imported->refresh()->colleague_id);
            $this->assertSame('2026-06-01', $jan->first_seen_at?->toDateString());
            $this->assertSame('2026-10-01', $jan->last_mail_at?->toDateString());
        });
    }

    public function test_a_member_of_the_account_is_linked_to_their_user(): void
    {
        $member = User::factory()->withAccount($this->account)->create(['email' => 'Roger@Noordkade.nl']);
        $elsewhere = User::factory()->withAccount()->create(['email' => 'piet@dochter.nl']);

        $this->store('m1', 'Roger <roger@noordkade.nl>');
        $this->store('m2', 'Piet <piet@dochter.nl>');

        Tenancy::for($this->account, function () use ($member): void {
            $this->assertSame($member->id, Colleague::query()->where('email', 'roger@noordkade.nl')->sole()->user_id);
            // A user of another account is not this account's member.
            $this->assertNull(Colleague::query()->where('email', 'piet@dochter.nl')->sole()->user_id);
        });
        $this->assertNotNull($elsewhere->id);
    }

    public function test_the_backfill_makes_colleagues_from_mail_stored_before(): void
    {
        Tenancy::for($this->account, function (): void {
            Email::factory()->create(['mailbox_id' => $this->mailbox->id, 'from_email' => 'anna@noordkade.nl', 'from_name' => 'Anna', 'source' => EmailSource::Import, 'received_at' => '2026-05-01 10:00']);
            Email::factory()->create(['mailbox_id' => $this->mailbox->id, 'from_email' => 'anna@noordkade.nl', 'from_name' => 'Anna', 'source' => EmailSource::Push, 'received_at' => '2026-10-01 10:00']);
            Email::factory()->create(['mailbox_id' => $this->mailbox->id, 'from_email' => 'piet@klant.nl', 'source' => EmailSource::Poll, 'received_at' => '2026-10-02 10:00']);
        });

        $this->artisan('colleagues:backfill')->assertSuccessful();
        // Again: nothing left to do, nothing doubled.
        $this->artisan('colleagues:backfill')->expectsOutputToContain('Linked 0 emails')->assertSuccessful();

        Tenancy::for($this->account, function (): void {
            $anna = Colleague::query()->sole();

            $this->assertSame('anna@noordkade.nl', $anna->email);
            $this->assertSame(2, $anna->emails()->count());
            $this->assertSame('2026-05-01', $anna->first_seen_at?->toDateString());
            $this->assertSame(1, Email::query()->whereNull('colleague_id')->count());
        });
    }

    public function test_another_accounts_colleagues_are_out_of_sight(): void
    {
        $this->store('m1', 'Anna <anna@noordkade.nl>');

        $other = Account::factory()->create();
        Tenancy::for($other, function (): void {
            $mailbox = Mailbox::factory()->create(['email_address' => 'kennis@noordkade.nl']);
            $this->assertSame(0, Colleague::query()->count());

            // The same address is a colleague of each account apart.
            app(StoreGmailMessage::class)->store($mailbox, GmailFixtures::message('m1', ['from' => 'Anna <anna@noordkade.nl>']), EmailSource::Push);
            $this->assertSame(1, Colleague::query()->count());
        });

        Tenancy::for($this->account, fn () => $this->assertSame(1, Colleague::query()->count()));
    }

    /**
     * @param  list<array{name: string, value: string}>  $headers
     */
    private function store(string $id, string $from, string $at = '2026-09-29 10:00', EmailSource $source = EmailSource::Push, array $headers = []): Email
    {
        $message = GmailFixtures::message($id, ['from' => $from, 'internalDate' => strtotime($at.' UTC') * 1000]);
        $message['payload']['headers'] = [...$message['payload']['headers'], ...$headers];

        return Tenancy::for($this->account, fn () => app(StoreGmailMessage::class)->store($this->mailbox, $message, $source));
    }
}
