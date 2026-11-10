<?php

namespace Tests\Feature\Setup;

use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Setup works on the current account only: another account's mailbox never
 * shows, and setting one account up leaves every other as it was.
 */
class SetupIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Account $ours;

    private Account $theirs;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ours = Account::factory()->notSetUp()->create();
        $this->theirs = Account::factory()->notSetUp()->create(['name' => 'Theirs']);
        $this->admin = User::factory()->withAccount($this->ours)->create();
    }

    public function test_another_accounts_mailbox_is_not_shown_as_connected(): void
    {
        Tenancy::for($this->theirs, fn () => Mailbox::factory()->create());

        $this->actingAs($this->admin)
            ->get(route('setup.mailbox'))
            ->assertInertia(fn (Assert $page) => $page->where('mailbox', null));

        $this->post(route('setup.finish'));

        $this->get(route('setup.done'))
            ->assertInertia(fn (Assert $page) => $page->where('mailbox', null));
    }

    public function test_setting_one_account_up_leaves_another_alone(): void
    {
        $this->actingAs($this->admin)->patch(route('setup.workspace.update'), [
            'name' => 'Ours',
            'domain' => 'ours.nl',
            'locale' => 'en',
            'timezone' => 'UTC',
        ]);
        $this->post(route('setup.finish'));

        $this->assertTrue($this->ours->refresh()->isSetUp());
        $this->assertFalse($this->theirs->refresh()->isSetUp());
        $this->assertSame('Theirs', $this->theirs->name);
        $this->assertNull($this->theirs->domain);
    }

    public function test_a_member_who_is_not_an_administrator_cannot_finish_setup(): void
    {
        $member = User::factory()->withAccount($this->theirs, isAdmin: false)->create();

        $this->actingAs($member)->post(route('setup.finish'))->assertForbidden();

        $this->assertFalse($this->theirs->refresh()->isSetUp());
    }
}
