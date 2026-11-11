<?php

namespace Tests\Feature\Mailboxes;

use App\Enums\SenderAccess;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Colleague;
use App\Models\Mailbox;
use App\Models\MailboxRule;
use App\Models\User;
use App\Services\Mailboxes\MailboxRuleEditor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MailboxRulesTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private User $admin;

    private Mailbox $mailbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create();
        $this->admin = User::factory()->withAccount($this->account)->create();
        $this->mailbox = Tenancy::for($this->account, fn () => Mailbox::factory()->create(['email_address' => 'kennis@noordkade.nl']));
    }

    public function test_an_admin_sets_the_colleagues_and_the_senders(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('mailboxes.rules.update', $this->mailbox), [
                'domain_access' => 'learn',
                'sender_rules' => [
                    ['entry' => ' Jan@Noordkade.nl', 'access' => 'ignore'],
                    ['entry' => '@Klant.nl', 'access' => 'learn_answer'],
                    ['entry' => 'noreply@klant.nl', 'access' => 'ignore'],
                    // A later rule for the same sender replaces the earlier one.
                    ['entry' => 'jan@noordkade.nl', 'access' => 'answer'],
                    ['entry' => '  ', 'access' => 'learn'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(SenderAccess::Learn, Tenancy::for($this->account, fn () => $this->mailbox->refresh()->domainAccess()));
        $this->assertSame([
            ['entry' => 'klant.nl', 'access' => 'learn_answer'],
            ['entry' => 'noreply@klant.nl', 'access' => 'ignore'],
            ['entry' => 'jan@noordkade.nl', 'access' => 'answer'],
        ], $this->senders());

        // An address is a colleague's own rule. Only one at the company gets
        // Howy's own mails from the start.
        Tenancy::for($this->account, function (): void {
            $this->assertSame(
                ['jan@noordkade.nl' => true, 'noreply@klant.nl' => false],
                Colleague::query()->orderBy('email')->pluck('receives_mail', 'email')->all(),
            );
            $this->assertSame($this->mailbox->id, Colleague::query()->where('email', 'jan@noordkade.nl')->sole()->home_mailbox_id);
        });
    }

    public function test_a_sender_that_stays_keeps_its_row(): void
    {
        $this->listSenders(['klant.nl' => SenderAccess::LearnAndAnswer, 'jan@noordkade.nl' => SenderAccess::Learn]);
        $klant = Tenancy::for($this->account, fn () => MailboxRule::query()->where('domain', 'klant.nl')->sole());

        $this->actingAs($this->admin)
            ->patch(route('mailboxes.rules.update', $this->mailbox), [
                'domain_access' => 'learn_answer',
                'sender_rules' => [
                    ['entry' => 'anna@haarlem.nl', 'access' => 'learn'],
                    ['entry' => 'klant.nl', 'access' => 'answer'],
                ],
                'removed_rules' => ['Jan@Noordkade.nl'],
            ])
            ->assertSessionHasNoErrors();

        // Changed, it is the same rule; a new one comes after the rest.
        $this->assertSame([
            ['entry' => 'klant.nl', 'access' => 'answer'],
            ['entry' => 'anna@haarlem.nl', 'access' => 'learn'],
        ], $this->senders());
        $this->assertTrue(Tenancy::for($this->account, fn () => MailboxRule::query()->whereKey($klant->id)->exists()));

        // Without a rule of their own they are still a colleague, under the
        // rule for their domain.
        Tenancy::for($this->account, fn () => $this->assertTrue(Colleague::query()->where('email', 'jan@noordkade.nl')->exists()));
        $this->assertSame(SenderAccess::LearnAndAnswer, Tenancy::for($this->account, fn () => $this->mailbox->refresh()->access('jan@noordkade.nl')));
    }

    public function test_saving_leaves_alone_what_it_was_not_told_about(): void
    {
        $this->listSenders(['klant.nl' => SenderAccess::Learn]);

        // Another admin, or the colleagues page, added one since the page loaded.
        $this->actingAs($this->admin)
            ->patch(route('mailboxes.rules.update', $this->mailbox), [
                'domain_access' => 'learn_answer',
                'sender_rules' => [['entry' => 'anna@haarlem.nl', 'access' => 'learn']],
                'removed_rules' => ['someone@elsewhere.nl'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame([
            ['entry' => 'klant.nl', 'access' => 'learn'],
            ['entry' => 'anna@haarlem.nl', 'access' => 'learn'],
        ], $this->senders());
    }

    public function test_a_domain_rule_says_whether_it_creates_colleagues(): void
    {
        $creates = fn (string $domain): bool => Tenancy::for($this->account, fn () => MailboxRule::query()->where('domain', $domain)->sole()->creates_colleagues);
        $save = fn (array $rule) => $this->actingAs($this->admin)
            ->patch(route('mailboxes.rules.update', $this->mailbox), ['domain_access' => 'learn_answer', 'sender_rules' => [$rule]])
            ->assertSessionHasNoErrors();

        // Another domain does not, until an admin says it does.
        $save(['entry' => 'dochter.nl', 'access' => 'learn_answer']);
        $this->assertFalse($creates('dochter.nl'));

        $save(['entry' => 'dochter.nl', 'access' => 'learn_answer', 'creates_colleagues' => '1']);
        $this->assertTrue($creates('dochter.nl'));

        // Not said, it stays as it was.
        $save(['entry' => 'dochter.nl', 'access' => 'learn']);
        $this->assertTrue($creates('dochter.nl'));

        $save(['entry' => 'dochter.nl', 'access' => 'learn', 'creates_colleagues' => '0']);
        $this->assertFalse($creates('dochter.nl'));

        // The own domain always does.
        $this->assertTrue($creates('noordkade.nl'));
    }

    public function test_who_mails_a_mailbox_and_becomes_a_colleague(): void
    {
        $this->listSenders([
            'klant.nl' => SenderAccess::LearnAndAnswer,
            'dochter.nl' => SenderAccess::LearnAndAnswer,
            'hr@noordkade.nl' => SenderAccess::Ignore,
            'roger@gmail.com' => SenderAccess::Learn,
        ]);
        Tenancy::for($this->account, fn () => app(MailboxRuleEditor::class)->setForDomain($this->mailbox, 'dochter.nl', SenderAccess::LearnAndAnswer, createsColleagues: true));
        Tenancy::for($this->account, function (): void {
            $mailbox = $this->mailbox->refresh();

            $this->assertTrue($mailbox->createsColleague('jan@noordkade.nl'));
            $this->assertTrue($mailbox->createsColleague('piet@dochter.nl'));
            $this->assertTrue($mailbox->createsColleague('roger@gmail.com'));
            $this->assertFalse($mailbox->createsColleague('piet@klant.nl'));
            $this->assertFalse($mailbox->createsColleague('hr@noordkade.nl'));
            $this->assertFalse($mailbox->createsColleague('iemand@elders.nl'));
        });
    }

    public function test_a_rule_is_for_a_colleague_or_a_domain_never_both(): void
    {
        $colleague = Tenancy::for($this->account, fn () => Colleague::factory()->create());

        $this->expectException(QueryException::class);

        Tenancy::for($this->account, fn () => MailboxRule::query()->create([
            'mailbox_id' => $this->mailbox->id,
            'colleague_id' => $colleague->id,
            'domain' => 'klant.nl',
            'access' => SenderAccess::Learn,
        ]));
    }

    public function test_another_accounts_senders_are_out_of_sight(): void
    {
        $other = Account::factory()->create();
        [$theirs, $theirColleague] = Tenancy::for($other, function (): array {
            $mailbox = Mailbox::factory()->rules(senders: ['klant.nl' => SenderAccess::Learn, 'jan@noordkade.nl' => SenderAccess::Learn])->create(['email_address' => 'kennis@elders.nl']);

            return [$mailbox->rules()->where('domain', 'klant.nl')->sole(), Colleague::query()->sole()];
        });
        $this->listSenders(['haarlem.nl' => SenderAccess::Answer]);

        Tenancy::for($this->account, function () use ($theirs, $theirColleague): void {
            $this->assertEqualsCanonicalizing(['noordkade.nl', 'haarlem.nl'], MailboxRule::query()->pluck('domain')->all());
            $this->assertNull(MailboxRule::query()->find($theirs->id));
            $this->assertNull(Colleague::query()->find($theirColleague->id));
        });

        $this->actingAs($this->admin)
            ->get(route('settings'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('mailboxes', 1)
                ->where('mailboxes.0.senderRules', [['entry' => 'haarlem.nl', 'access' => 'answer', 'createsColleagues' => false, 'colleagueId' => null]]));

        // The same address here is a colleague of this account's own, and
        // saving this account's rules leaves the other account's alone.
        $this->actingAs($this->admin)
            ->patch(route('mailboxes.rules.update', $this->mailbox), [
                'domain_access' => 'learn_answer',
                'sender_rules' => [['entry' => 'jan@noordkade.nl', 'access' => 'answer']],
                'removed_rules' => ['klant.nl'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('learn', Tenancy::for($other, fn () => $theirs->refresh()->access->value));
        $this->assertSame(2, Tenancy::withoutTenancy(fn () => Colleague::query()->where('email', 'jan@noordkade.nl')->count()));
    }

    public function test_a_mailbox_without_rules_reads_no_one(): void
    {
        $mailbox = Tenancy::for($this->account, fn () => Mailbox::factory()->make(['email_address' => 'kennis@noordkade.nl'])->setRelation('rules', new Collection));

        $this->assertSame(SenderAccess::Ignore, $mailbox->domainAccess());
        $this->assertSame(SenderAccess::Ignore, $mailbox->access('jan@noordkade.nl'));
    }

    public function test_rules_are_addresses_or_domains_with_a_known_access(): void
    {
        $payload = ['domain_access' => 'learn_answer'];

        $this->actingAs($this->admin)
            ->patch(route('mailboxes.rules.update', $this->mailbox), [...$payload, 'sender_rules' => [
                ['entry' => 'jan@noordkade.nl', 'access' => 'learn'],
                ['entry' => 'not a domain', 'access' => 'learn'],
            ]])
            ->assertSessionHasErrors('sender_rules.1.entry');

        $this->actingAs($this->admin)
            ->patch(route('mailboxes.rules.update', $this->mailbox), [...$payload, 'sender_rules' => [
                ['entry' => 'jan@noordkade.nl', 'access' => 'sometimes'],
            ]])
            ->assertSessionHasErrors('sender_rules.0.access');

        $this->actingAs($this->admin)
            ->patch(route('mailboxes.rules.update', $this->mailbox), ['domain_access' => 'sometimes'])
            ->assertSessionHasErrors('domain_access');

        $this->assertSame(SenderAccess::LearnAndAnswer, Tenancy::for($this->account, fn () => $this->mailbox->refresh()->domainAccess()));
    }

    public function test_a_public_domain_is_listed_address_by_address(): void
    {
        $payload = ['domain_access' => 'learn_answer'];

        foreach (['gmail.com', '@Gmail.com', 'outlook.com'] as $domain) {
            $this->actingAs($this->admin)
                ->patch(route('mailboxes.rules.update', $this->mailbox), [...$payload, 'sender_rules' => [['entry' => $domain, 'access' => 'learn']]])
                ->assertSessionHasErrors('sender_rules.0.entry');
        }

        $this->actingAs($this->admin)
            ->patch(route('mailboxes.rules.update', $this->mailbox), [...$payload, 'sender_rules' => [['entry' => 'roger@gmail.com', 'access' => 'learn']]])
            ->assertSessionHasNoErrors();

        $this->assertSame([['entry' => 'roger@gmail.com', 'access' => 'learn']], $this->senders());
    }

    public function test_only_an_admin_of_the_account_may_change_it(): void
    {
        $member = User::factory()->withAccount($this->account, isAdmin: false)->create();
        $payload = ['domain_access' => 'ignore'];

        $this->actingAs($member)->patch(route('mailboxes.rules.update', $this->mailbox), $payload)->assertForbidden();

        // Another account's mailbox does not exist for an admin elsewhere.
        $outsider = User::factory()->withAccount()->create();
        $this->actingAs($outsider)->patch(route('mailboxes.rules.update', $this->mailbox), $payload)->assertNotFound();

        $this->assertSame(SenderAccess::LearnAndAnswer, Tenancy::for($this->account, fn () => $this->mailbox->refresh()->domainAccess()));
    }

    public function test_the_settings_page_shows_the_rules(): void
    {
        $this->listSenders(['haarlem.nl' => SenderAccess::Learn, 'jan@klant.nl' => SenderAccess::Answer]);
        $jan = Tenancy::for($this->account, fn () => Colleague::query()->where('email', 'jan@klant.nl')->sole());

        $this->actingAs($this->admin)
            ->get(route('settings'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('mailboxes.0.domainAccess', 'learn_answer')
                ->where('mailboxes.0.senderRules', [
                    ['entry' => 'haarlem.nl', 'access' => 'learn', 'createsColleagues' => false, 'colleagueId' => null],
                    ['entry' => 'jan@klant.nl', 'access' => 'answer', 'createsColleagues' => false, 'colleagueId' => $jan->id],
                ])
                ->where('mailboxes.0.domain', 'noordkade.nl')
                ->where('mailboxes.0.publicDomain', false)
                ->where('publicDomains', config('mailboxes.public_domains')));
    }

    public function test_the_explanation_of_a_mailbox_quotes_the_configured_numbers(): void
    {
        config(['knowledge.mail.answer_min_confidence' => 0.8, 'knowledge.ai_log.payload_retention_days' => 14]);

        $this->actingAs($this->admin)
            ->get(route('settings'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('mailFacts.answerConfidence', 80)
                ->where('mailFacts.aiLogDays', 14));

        // Not logging what went to the AI provider, it keeps it for no days.
        config(['knowledge.ai_log.payloads' => false]);

        $this->actingAs($this->admin)
            ->get(route('settings'))
            ->assertInertia(fn (Assert $page) => $page->where('mailFacts.aiLogDays', 0));
    }

    public function test_the_first_matching_rule_decides_what_howy_may_do(): void
    {
        $l = SenderAccess::LearnAndAnswer;
        $learn = SenderAccess::Learn;
        $answer = SenderAccess::Answer;
        $ignore = SenderAccess::Ignore;

        $cases = [
            // Colleagues, and everyone else ignored.
            'colleague' => [$l, [], 'jan@noordkade.nl', $l],
            'colleagues learned from only' => [$learn, [], 'jan@noordkade.nl', $learn],
            'outsider' => [$l, [], 'iemand@gmail.com', $ignore],
            'subdomain is not the domain' => [$l, [], 'jan@sub.noordkade.nl', $ignore],
            'case does not matter' => [$l, [], 'Jan@NoordKade.NL', $l],
            'not an address' => [$l, [], 'not-an-address', $ignore],
            // A listed domain beats the colleagues.
            'listed domain' => [$l, ['klant.nl' => $answer], 'piet@klant.nl', $answer],
            'listed own domain' => [$l, ['noordkade.nl' => $learn], 'jan@noordkade.nl', $learn],
            // A listed address beats its domain: one address inside an organisation.
            'ignored inside a listed domain' => [$ignore, ['klant.nl' => $l, 'noreply@klant.nl' => $ignore], 'noreply@klant.nl', $ignore],
            'the rest of that domain' => [$ignore, ['klant.nl' => $l, 'noreply@klant.nl' => $ignore], 'piet@klant.nl', $l],
            'ignored colleague' => [$l, ['hr@noordkade.nl' => $ignore], 'hr@noordkade.nl', $ignore],
            'listed outsider' => [$l, ['roger@gmail.com' => $learn], 'roger@gmail.com', $learn],
            'only that outsider' => [$l, ['roger@gmail.com' => $learn], 'piet@gmail.com', $ignore],
        ];

        foreach ($cases as $case => [$domain, $senders, $address, $expected]) {
            $mailbox = Tenancy::for($this->account, fn () => Mailbox::factory()->rules($domain, $senders)->make(['email_address' => 'Kennis@Noordkade.nl']));

            $this->assertSame($expected, $mailbox->access($address), $case);
            $this->assertSame($expected !== $ignore, $mailbox->accepts($address), $case);
            $this->assertSame($expected->answers(), $mailbox->maySendTo($address), $case);
        }

        // On gmail.com the own domain is everyone: it has no colleagues.
        $gmail = Tenancy::for($this->account, fn () => Mailbox::factory()->rules($l, ['roger@gmail.com' => $l])->make(['email_address' => 'howy@gmail.com']));

        $this->assertTrue($gmail->isOnPublicDomain());
        $this->assertSame($l, $gmail->access('roger@gmail.com'));
        $this->assertSame($ignore, $gmail->access('piet@gmail.com'));
    }

    public function test_an_access_covers_what_it_allows_at_least(): void
    {
        $this->assertTrue(SenderAccess::LearnAndAnswer->covers(SenderAccess::Learn));
        $this->assertTrue(SenderAccess::Learn->covers(SenderAccess::Ignore));
        $this->assertFalse(SenderAccess::Answer->covers(SenderAccess::Learn));
        $this->assertFalse(SenderAccess::Learn->covers(SenderAccess::LearnAndAnswer));
        $this->assertEqualsCanonicalizing([SenderAccess::LearnAndAnswer, SenderAccess::Answer], SenderAccess::Answer->coveredBy());
    }

    /**
     * @param  array<string, SenderAccess>  $senders
     */
    private function listSenders(array $senders): void
    {
        Tenancy::for($this->account, fn () => app(MailboxRuleEditor::class)->apply($this->mailbox, SenderAccess::LearnAndAnswer, array_map(
            fn (string $entry, SenderAccess $access): array => ['entry' => $entry, 'access' => $access],
            array_keys($senders),
            array_values($senders),
        )));
    }

    /**
     * The rules besides the one for the mailbox's own domain, oldest first.
     *
     * @return list<array{entry: string, access: string}>
     */
    private function senders(): array
    {
        return Tenancy::for($this->account, fn () => $this->mailbox->rules()->with('colleague')->oldest('id')->get()
            ->reject(fn (MailboxRule $rule): bool => $rule->domain === 'noordkade.nl')
            ->map(fn (MailboxRule $rule): array => ['entry' => $rule->entry(), 'access' => $rule->access->value])
            ->values()
            ->all());
    }
}
