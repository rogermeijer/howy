<?php

namespace Tests\Feature\Setup;

use App\Enums\Locale;
use App\Enums\MailboxStatus;
use App\Enums\SenderAccess;
use App\Facades\Tenancy;
use App\Jobs\Knowledge\TranslateKnowledge;
use App\Models\Account;
use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SetupTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->notSetUp()->create(['name' => 'Acme Logistics']);
        $this->admin = User::factory()->withAccount($this->account)->create(['email' => 'sanne@acme.com']);
    }

    public function test_an_administrator_of_an_account_not_set_up_is_sent_to_setup_first(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('dashboard'))->assertRedirect(route('setup.start'));
        $this->get(route('knowledge'))->assertRedirect(route('setup.start'));
        $this->get(route('setup.start'))->assertRedirect(route('setup.workspace'));
    }

    public function test_settings_stay_reachable_before_setup(): void
    {
        $this->actingAs($this->admin)->get(route('settings'))->assertOk();
    }

    public function test_members_who_are_not_administrators_go_where_they_were_going(): void
    {
        $member = User::factory()->withAccount($this->account, isAdmin: false)->create();

        $this->actingAs($member)->get(route('dashboard'))->assertOk();
        $this->actingAs($member)->get(route('setup.workspace'))->assertForbidden();
    }

    public function test_an_account_that_is_set_up_goes_straight_to_the_inbox(): void
    {
        $this->account->forceFill(['onboarded_at' => now()])->save();

        $this->actingAs($this->admin);

        $this->get(route('dashboard'))->assertOk();
        $this->get(route('setup.start'))->assertRedirect(route('dashboard'));
        $this->get(route('setup.workspace'))->assertRedirect(route('dashboard'));
        $this->get(route('setup.knowledge'))->assertRedirect(route('dashboard'));
        $this->get(route('setup.mailbox'))->assertRedirect(route('dashboard'));
    }

    public function test_setup_waits_for_a_verified_email_address(): void
    {
        $unverified = User::factory()->unverified()->withAccount($this->account)->create();

        $this->actingAs($unverified)->get(route('setup.workspace'))->assertRedirect(route('verification.notice'));
    }

    public function test_the_verification_page_says_setup_comes_next_for_an_administrator(): void
    {
        $unverified = User::factory()->unverified()->withAccount($this->account)->create();

        $this->actingAs($unverified)
            ->get(route('verification.notice'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/verify-email')
                ->where('email', $unverified->email)
                ->where('setupAhead', true));
    }

    public function test_the_workspace_step_suggests_the_domain_of_the_administrators_address(): void
    {
        $this->actingAs($this->admin)
            ->get(route('setup.workspace'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('setup/workspace')
                ->where('account.name', 'Acme Logistics')
                ->where('account.domain', 'acme.com'));
    }

    public function test_no_domain_is_suggested_for_an_address_anyone_can_have(): void
    {
        $this->admin->forceFill(['email' => 'sanne@gmail.com'])->save();

        $this->actingAs($this->admin)
            ->get(route('setup.workspace'))
            ->assertInertia(fn (Assert $page) => $page->where('account.domain', null));
    }

    public function test_the_workspace_step_saves_the_name_domain_language_and_timezone(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('setup.workspace.update'), [
                'name' => 'Acme',
                'domain' => ' https://ACME.com/ ',
                'locale' => 'en',
                'timezone' => 'Europe/Amsterdam',
            ])
            ->assertRedirect(route('setup.knowledge'));

        $this->account->refresh();
        $this->assertSame('Acme', $this->account->name);
        $this->assertSame('acme.com', $this->account->domain);
        $this->assertSame('Europe/Amsterdam', $this->account->timezone);
        $this->assertFalse($this->account->isSetUp());
    }

    public function test_a_domain_anyone_can_have_an_address_on_is_refused(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('setup.workspace.update'), [
                'name' => 'Acme',
                'domain' => 'gmail.com',
                'locale' => 'en',
                'timezone' => 'UTC',
            ])
            ->assertSessionHasErrors('domain');

        $this->actingAs($this->admin)
            ->patch(route('setup.workspace.update'), [
                'name' => 'Acme',
                'domain' => 'not a domain',
                'locale' => 'en',
                'timezone' => 'UTC',
            ])
            ->assertSessionHasErrors('domain');

        $this->assertNull($this->account->refresh()->domain);
    }

    public function test_choosing_another_workspace_language_moves_the_knowledge_language_along_during_setup(): void
    {
        Queue::fake();

        $this->actingAs($this->admin)
            ->patch(route('setup.workspace.update'), [
                'name' => 'Acme',
                'domain' => 'acme.com',
                'locale' => 'nl',
                'timezone' => 'UTC',
            ])
            ->assertRedirect(route('setup.knowledge'));

        $this->assertSame(Locale::Dutch, $this->account->refresh()->knowledge_locale);
    }

    public function test_the_knowledge_step_offers_every_language_with_an_example_in_that_language(): void
    {
        $this->actingAs($this->admin)
            ->get(route('setup.knowledge'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('setup/knowledge')
                ->where('knowledgeLocale', 'en')
                ->has('languages', count(Locale::cases()))
                ->where('languages.1.value', 'nl')
                ->where('languages.1.example.folderLabel', 'Map'));
    }

    public function test_the_knowledge_language_counts_only_once_its_permanence_is_understood(): void
    {
        Queue::fake();

        $this->actingAs($this->admin)
            ->patch(route('setup.knowledge.update'), ['knowledge_locale' => 'nl'])
            ->assertSessionHasErrors('understood');

        $this->assertSame(Locale::English, $this->account->refresh()->knowledge_locale);

        $this->actingAs($this->admin)
            ->patch(route('setup.knowledge.update'), ['knowledge_locale' => 'nl', 'understood' => '1'])
            ->assertRedirect(route('setup.mailbox'));

        $this->assertSame(Locale::Dutch, $this->account->refresh()->knowledge_locale);
        Queue::assertPushed(TranslateKnowledge::class);
    }

    public function test_the_mailbox_step_offers_an_address_of_its_own_until_one_is_connected(): void
    {
        $this->account->forceFill(['domain' => 'acme.com'])->save();

        $this->actingAs($this->admin)
            ->get(route('setup.mailbox'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('setup/mailbox')
                ->where('domain', 'acme.com')
                ->where('mailbox', null));

        Tenancy::for($this->account, fn () => Mailbox::factory()->create(['email_address' => 'howy@acme.com']));

        $this->actingAs($this->admin)
            ->get(route('setup.mailbox'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('mailbox.emailAddress', 'howy@acme.com')
                ->where('mailbox.domainAccess', SenderAccess::LearnAndAnswer->value));
    }

    public function test_a_disconnected_mailbox_is_not_offered_as_connected(): void
    {
        Tenancy::for($this->account, fn () => Mailbox::factory()->create(['status' => MailboxStatus::Disconnected]));

        $this->actingAs($this->admin)
            ->get(route('setup.mailbox'))
            ->assertInertia(fn (Assert $page) => $page->where('mailbox', null));
    }

    public function test_finishing_sets_the_account_up_with_or_without_a_mailbox(): void
    {
        $this->actingAs($this->admin)
            ->post(route('setup.finish'))
            ->assertRedirect(route('setup.done'));

        $this->assertTrue($this->account->refresh()->isSetUp());

        $this->actingAs($this->admin)
            ->get(route('setup.done'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('setup/done')
                ->where('account.name', 'Acme Logistics')
                ->where('mailbox', null));

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_finishing_again_keeps_the_moment_it_was_first_set_up(): void
    {
        $this->account->forceFill(['onboarded_at' => now()->subWeek()])->save();
        $first = $this->account->onboarded_at;

        $this->actingAs($this->admin)->post(route('setup.finish'))->assertRedirect(route('setup.done'));

        $this->assertTrue($first->equalTo($this->account->refresh()->onboarded_at));
    }

    public function test_done_is_only_shown_once_the_account_is_set_up(): void
    {
        $this->actingAs($this->admin)->get(route('setup.done'))->assertRedirect(route('setup.start'));
    }

    public function test_the_settings_page_saves_the_domain_too(): void
    {
        $this->account->forceFill(['onboarded_at' => now()])->save();

        $this->actingAs($this->admin)
            ->patch(route('account.update'), [
                'name' => 'Acme',
                'domain' => '@acme.nl',
                'locale' => 'en',
                'timezone' => 'UTC',
            ])
            ->assertRedirect(route('settings'));

        $this->assertSame('acme.nl', $this->account->refresh()->domain);

        $this->actingAs($this->admin)
            ->patch(route('account.update'), ['name' => 'Acme', 'domain' => '', 'locale' => 'en', 'timezone' => 'UTC'])
            ->assertRedirect(route('settings'));

        $this->assertNull($this->account->refresh()->domain);
    }
}
