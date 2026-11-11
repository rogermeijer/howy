<?php

namespace Tests\Feature\Colleagues;

use App\Enums\SenderAccess;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Colleague;
use App\Models\Mailbox;
use App\Models\MailboxRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The senders listed on a mailbox and the access for its own domain move to
 * colleagues and rules without anyone's access changing.
 */
class ColleagueMigrationTest extends TestCase
{
    use RefreshDatabase;

    /** This one, and the later ones that hang on colleagues. */
    private const array MIGRATIONS = [
        'database/migrations/2026_11_11_100000_create_colleagues_and_mailbox_rules.php',
        'database/migrations/2026_11_12_100000_create_outbound_mails_table.php',
    ];

    public function test_senders_and_the_own_domain_become_rules_with_the_same_access(): void
    {
        $account = Account::factory()->create(['domain' => 'noordkade.nl']);
        User::factory()->withAccount($account)->create(['email' => 'Roger@Gmail.com']);

        [$kennis, $support, $gmail] = Tenancy::for($account, fn (): array => [
            Mailbox::factory()->rules(SenderAccess::Learn, [
                'klant.nl' => SenderAccess::Answer,
                'jan@noordkade.nl' => SenderAccess::Ignore,
                'piet@klant.nl' => SenderAccess::LearnAndAnswer,
            ])->create(['email_address' => 'kennis@noordkade.nl']),
            Mailbox::factory()->create(['email_address' => 'support@noordkade.nl']),
            Mailbox::factory()->rules(senders: ['roger@gmail.com' => SenderAccess::LearnAndAnswer])->create(['email_address' => 'howy@gmail.com']),
        ]);

        $this->artisan('migrate:rollback', ['--path' => self::MIGRATIONS])->assertSuccessful();

        // As it was: support@ ignored its own domain, but listed it too, and
        // the listed row went first.
        DB::table('mailboxes')->where('id', $support->id)->update(['domain_access' => 'ignore']);
        DB::table('mailbox_senders')->insert([
            ['account_id' => $account->id, 'mailbox_id' => $support->id, 'entry' => 'noordkade.nl', 'access' => 'learn'],
            ['account_id' => $account->id, 'mailbox_id' => $support->id, 'entry' => 'dochter.nl', 'access' => 'learn_answer'],
        ]);

        $this->assertSame('learn', DB::table('mailboxes')->where('id', $kennis->id)->value('domain_access'));
        $this->assertSame(3, DB::table('mailbox_senders')->where('mailbox_id', $kennis->id)->count());

        $this->artisan('migrate', ['--path' => self::MIGRATIONS])->assertSuccessful();

        Tenancy::for($account, function () use ($kennis, $support, $gmail): void {
            $access = fn (Mailbox $mailbox, string $address): SenderAccess => Mailbox::query()->findOrFail($mailbox->id)->access($address);

            $this->assertSame(SenderAccess::Learn, $access($kennis, 'anna@noordkade.nl'));
            $this->assertSame(SenderAccess::Ignore, $access($kennis, 'jan@noordkade.nl'));
            $this->assertSame(SenderAccess::Answer, $access($kennis, 'els@klant.nl'));
            $this->assertSame(SenderAccess::LearnAndAnswer, $access($kennis, 'piet@klant.nl'));
            $this->assertSame(SenderAccess::Ignore, $access($kennis, 'iemand@elders.nl'));
            $this->assertSame(SenderAccess::Learn, $access($support, 'anna@noordkade.nl'));
            $this->assertSame(SenderAccess::LearnAndAnswer, $access($support, 'eva@dochter.nl'));
            $this->assertSame(SenderAccess::LearnAndAnswer, $access($gmail, 'roger@gmail.com'));
            $this->assertSame(SenderAccess::Ignore, $access($gmail, 'piet@gmail.com'));

            // The own domain makes colleagues; another domain does not.
            $this->assertTrue(MailboxRule::query()->where('mailbox_id', $support->id)->where('domain', 'noordkade.nl')->sole()->creates_colleagues);
            $this->assertFalse(MailboxRule::query()->where('mailbox_id', $support->id)->where('domain', 'dochter.nl')->sole()->creates_colleagues);
            $this->assertFalse(MailboxRule::query()->where('mailbox_id', $gmail->id)->whereNotNull('domain')->exists());

            // Listed addresses became colleagues; Howy's own mails only go to
            // those at the company and to members.
            $this->assertSame(
                ['jan@noordkade.nl' => true, 'piet@klant.nl' => false, 'roger@gmail.com' => true],
                Colleague::query()->orderBy('email')->pluck('receives_mail', 'email')->all(),
            );
            $this->assertNotNull(Colleague::query()->where('email', 'roger@gmail.com')->sole()->user_id);
        });
    }
}
