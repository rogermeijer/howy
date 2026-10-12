<?php

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * /admin is for platform super-admins only. An administrator of an account is
 * not one.
 */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function pages(): array
    {
        return [
            route('admin.accounts.index'),
            route('admin.accounts.create'),
            route('admin.users.index'),
            route('admin.users.create'),
            route('admin.beta.index'),
            route('admin.ai.index'),
        ];
    }

    public function test_guests_are_sent_to_log_in(): void
    {
        foreach ($this->pages() as $page) {
            $this->get($page)->assertRedirect(route('login'));
        }
    }

    public function test_an_account_admin_is_refused(): void
    {
        $user = User::factory()->withAccount(isAdmin: true)->create();
        $account = $user->accounts()->sole();

        $this->actingAs($user);

        foreach ($this->pages() as $page) {
            $this->get($page)->assertForbidden();
        }

        $this->get(route('admin.accounts.show', $account))->assertForbidden();
        $this->patch(route('admin.accounts.update', $account), ['name' => 'Taken over', 'locale' => 'en', 'timezone' => 'UTC'])->assertForbidden();
        $this->delete(route('admin.users.destroy', $user))->assertForbidden();

        $this->assertNotSame('Taken over', $account->refresh()->name);
    }

    public function test_a_super_admin_gets_in(): void
    {
        $admin = User::factory()->superAdmin()->withAccount()->create();

        $this->actingAs($admin);

        foreach ($this->pages() as $page) {
            $this->get($page)->assertOk();
        }

        $this->get(route('admin.index'))->assertRedirect('/admin/accounts');
    }

    public function test_a_super_admin_without_an_account_gets_in_too(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->get(route('admin.accounts.index'))->assertOk();
        $this->get(route('admin.accounts.show', Account::factory()->create()))->assertOk();
    }

    public function test_the_menu_knows_who_is_a_super_admin(): void
    {
        $this->actingAs(User::factory()->superAdmin()->withAccount()->create())
            ->get(route('inbox'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.is_admin', true));

        $this->actingAs(User::factory()->withAccount()->create())
            ->get(route('inbox'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.is_admin', false));
    }
}
