<?php

namespace Tests\Feature\Mailboxes;

use App\Enums\SendPolicy;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MailboxSendPolicyTest extends TestCase
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

    public function test_an_admin_sets_the_policy_and_lists(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('mailboxes.send-policy.update', $this->mailbox), [
                'send_policy' => 'domain',
                'send_whitelist' => '',
                'send_blacklist' => " Jan@Noordkade.nl\n@directie.noordkade.nl\njan@noordkade.nl\n\n",
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->mailbox->refresh();

        $this->assertSame(SendPolicy::Domain, $this->mailbox->send_policy);
        $this->assertSame([], $this->mailbox->send_whitelist);
        $this->assertSame(['jan@noordkade.nl', '@directie.noordkade.nl'], $this->mailbox->send_blacklist);
        // An unticked checkbox is not sent at all: replies off.
        $this->assertFalse($this->mailbox->replies_enabled);

        $this->actingAs($this->admin)
            ->patch(route('mailboxes.send-policy.update', $this->mailbox), [
                'send_policy' => 'domain',
                'send_whitelist' => '',
                'send_blacklist' => '',
                'replies_enabled' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($this->mailbox->refresh()->replies_enabled);
    }

    public function test_a_new_mailbox_reads_from_its_own_domain_and_replies(): void
    {
        $mailbox = new Mailbox;

        $this->assertSame(SendPolicy::Domain, $mailbox->send_policy);
        $this->assertTrue($mailbox->replies_enabled);
    }

    public function test_a_mailbox_on_a_public_domain_has_no_same_domain(): void
    {
        $gmail = Tenancy::for($this->account, fn () => Mailbox::factory()->sendPolicy(SendPolicy::Whitelist, ['roger@gmail.com'])->create(['email_address' => 'howy@gmail.com']));

        $this->actingAs($this->admin)
            ->patch(route('mailboxes.send-policy.update', $gmail), ['send_policy' => 'domain', 'send_whitelist' => '', 'send_blacklist' => ''])
            ->assertSessionHasErrors('send_policy');

        $this->assertSame(SendPolicy::Whitelist, $gmail->refresh()->send_policy);
        $this->assertTrue($gmail->isOnPublicDomain());
        $this->assertFalse($this->mailbox->isOnPublicDomain());
    }

    public function test_entries_must_be_addresses_or_domains(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('mailboxes.send-policy.update', $this->mailbox), [
                'send_policy' => 'whitelist',
                'send_whitelist' => "jan@noordkade.nl\nnot a domain",
                'send_blacklist' => '',
            ])
            ->assertSessionHasErrors('send_whitelist.1');

        $this->actingAs($this->admin)
            ->patch(route('mailboxes.send-policy.update', $this->mailbox), ['send_policy' => 'sometimes'])
            ->assertSessionHasErrors('send_policy');

        $this->assertSame(SendPolicy::Always, $this->mailbox->refresh()->send_policy);
    }

    public function test_only_an_admin_of_the_account_may_change_it(): void
    {
        $member = User::factory()->withAccount($this->account, isAdmin: false)->create();
        $payload = ['send_policy' => 'domain', 'send_whitelist' => '', 'send_blacklist' => ''];

        $this->actingAs($member)->patch(route('mailboxes.send-policy.update', $this->mailbox), $payload)->assertForbidden();

        $outsider = User::factory()->withAccount()->create();
        $this->actingAs($outsider)->patch(route('mailboxes.send-policy.update', $this->mailbox), $payload)->assertNotFound();

        $this->assertSame(SendPolicy::Always, $this->mailbox->refresh()->send_policy);
    }

    public function test_the_settings_page_shows_the_policy(): void
    {
        $this->mailbox->update(['send_policy' => SendPolicy::Always, 'send_blacklist' => ['haarlem.nl']]);

        $this->actingAs($this->admin)
            ->get(route('settings'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('mailboxes.0.sendPolicy', 'always')
                ->where('mailboxes.0.sendBlacklist', ['haarlem.nl'])
                ->where('mailboxes.0.domain', 'noordkade.nl')
                ->where('mailboxes.0.publicDomain', false)
                ->where('mailboxes.0.repliesEnabled', true)
                ->has('sendPolicies', 3));
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

    public function test_the_policy_decides_who_is_accepted_and_may_get_a_reply(): void
    {
        $mailbox = new Mailbox(['email_address' => 'Kennis@Noordkade.nl']);

        $cases = [
            [SendPolicy::Always, [], [], 'iemand@gmail.com', true],
            [SendPolicy::Always, [], ['gmail.com'], 'iemand@gmail.com', false],
            [SendPolicy::Always, [], ['@gmail.com'], 'Iemand@Gmail.com', false],
            [SendPolicy::Always, [], ['jan@gmail.com'], 'piet@gmail.com', true],
            [SendPolicy::Domain, [], [], 'jan@noordkade.nl', true],
            [SendPolicy::Domain, [], [], 'jan@sub.noordkade.nl', false],
            [SendPolicy::Domain, [], [], 'jan@gmail.com', false],
            [SendPolicy::Domain, [], ['jan@noordkade.nl'], 'jan@noordkade.nl', false],
            [SendPolicy::Whitelist, ['jan@gmail.com', 'haarlem.nl'], [], 'jan@gmail.com', true],
            [SendPolicy::Whitelist, ['jan@gmail.com', 'haarlem.nl'], [], 'tom@haarlem.nl', true],
            [SendPolicy::Whitelist, ['jan@gmail.com', 'haarlem.nl'], [], 'piet@gmail.com', false],
            [SendPolicy::Always, [], [], 'not-an-address', false],
        ];

        foreach ($cases as $index => [$policy, $whitelist, $blacklist, $address, $expected]) {
            $mailbox->fill(['send_policy' => $policy, 'send_whitelist' => $whitelist, 'send_blacklist' => $blacklist]);

            $this->assertSame($expected, $mailbox->accepts($address), "Case {$index}: {$policy->value} → {$address}");
            $this->assertSame($expected, $mailbox->maySendTo($address), "Case {$index}: {$policy->value} → {$address}");
        }

        // Replies off: still accepted, never answered.
        $mailbox->fill(['send_policy' => SendPolicy::Domain, 'replies_enabled' => false]);

        $this->assertTrue($mailbox->accepts('jan@noordkade.nl'));
        $this->assertFalse($mailbox->maySendTo('jan@noordkade.nl'));
    }
}
