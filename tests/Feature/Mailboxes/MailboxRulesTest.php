<?php

namespace Tests\Feature\Mailboxes;

use App\Enums\SenderAccess;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Mailbox;
use App\Models\MailboxSender;
use App\Models\User;
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

        $this->mailbox->refresh();

        $this->assertSame(SenderAccess::Learn, $this->mailbox->domain_access);
        $this->assertSame([
            ['entry' => 'klant.nl', 'access' => 'learn_answer'],
            ['entry' => 'noreply@klant.nl', 'access' => 'ignore'],
            ['entry' => 'jan@noordkade.nl', 'access' => 'answer'],
        ], $this->senders());
    }

    public function test_a_sender_that_stays_keeps_its_row(): void
    {
        $this->listSenders(['klant.nl' => SenderAccess::LearnAndAnswer, 'jan@noordkade.nl' => SenderAccess::Learn]);
        $klant = Tenancy::for($this->account, fn () => MailboxSender::query()->where('entry', 'klant.nl')->sole());

        $this->actingAs($this->admin)
            ->patch(route('mailboxes.rules.update', $this->mailbox), [
                'domain_access' => 'learn_answer',
                'sender_rules' => [
                    ['entry' => 'anna@haarlem.nl', 'access' => 'learn'],
                    ['entry' => 'klant.nl', 'access' => 'answer'],
                ],
            ])
            ->assertSessionHasNoErrors();

        // Changed, it is the same sender; a new one comes after those listed.
        $this->assertSame([
            ['entry' => 'klant.nl', 'access' => 'answer'],
            ['entry' => 'anna@haarlem.nl', 'access' => 'learn'],
        ], $this->senders());
        $this->assertTrue(Tenancy::for($this->account, fn () => MailboxSender::query()->whereKey($klant->id)->exists()));
    }

    public function test_another_accounts_senders_are_out_of_sight(): void
    {
        $other = Account::factory()->create();
        $theirs = Tenancy::for($other, function (): MailboxSender {
            $mailbox = Mailbox::factory()->rules(senders: ['klant.nl' => SenderAccess::Learn])->create(['email_address' => 'kennis@elders.nl']);

            return $mailbox->senders()->sole();
        });
        $this->listSenders(['haarlem.nl' => SenderAccess::Answer]);

        Tenancy::for($this->account, function () use ($theirs): void {
            $this->assertSame(['haarlem.nl'], MailboxSender::query()->pluck('entry')->all());
            $this->assertNull(MailboxSender::query()->find($theirs->id));
        });

        $this->actingAs($this->admin)
            ->get(route('settings'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('mailboxes', 1)
                ->where('mailboxes.0.senderRules', [['entry' => 'haarlem.nl', 'access' => 'answer']]));

        // Saving this account's rules leaves the other account's alone.
        $this->actingAs($this->admin)
            ->patch(route('mailboxes.rules.update', $this->mailbox), ['domain_access' => 'learn_answer'])
            ->assertSessionHasNoErrors();

        $this->assertSame('learn', Tenancy::for($other, fn () => $theirs->refresh()->access->value));
    }

    public function test_an_empty_list_posts_nothing_and_clears_the_senders(): void
    {
        $this->listSenders(['klant.nl' => SenderAccess::Learn]);

        $this->actingAs($this->admin)
            ->patch(route('mailboxes.rules.update', $this->mailbox), ['domain_access' => 'learn_answer'])
            ->assertSessionHasNoErrors();

        $this->assertSame([], $this->senders());
    }

    public function test_a_new_mailbox_learns_from_and_answers_its_own_domain_only(): void
    {
        $mailbox = new Mailbox;

        $this->assertSame(SenderAccess::LearnAndAnswer, $mailbox->domain_access);
        $this->assertCount(0, $mailbox->senders);
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

        $this->assertSame(SenderAccess::LearnAndAnswer, $this->mailbox->refresh()->domain_access);
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

        $this->assertSame(SenderAccess::LearnAndAnswer, $this->mailbox->refresh()->domain_access);
    }

    public function test_the_settings_page_shows_the_rules(): void
    {
        $this->listSenders(['haarlem.nl' => SenderAccess::Learn]);

        $this->actingAs($this->admin)
            ->get(route('settings'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('mailboxes.0.domainAccess', 'learn_answer')
                ->where('mailboxes.0.senderRules', [['entry' => 'haarlem.nl', 'access' => 'learn']])
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
        Tenancy::for($this->account, fn () => $this->mailbox->syncSenders(array_map(
            fn (string $entry, SenderAccess $access): array => ['entry' => $entry, 'access' => $access],
            array_keys($senders),
            array_values($senders),
        )));
    }

    /**
     * @return list<array{entry: string, access: string}>
     */
    private function senders(): array
    {
        return Tenancy::for($this->account, fn () => $this->mailbox->senders()->oldest('id')->get()
            ->map(fn (MailboxSender $sender): array => ['entry' => $sender->entry, 'access' => $sender->access->value])
            ->values()
            ->all());
    }
}
