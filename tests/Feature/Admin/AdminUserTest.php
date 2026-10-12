<?php

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->withAccount()->create();
    }

    public function test_it_searches_users(): void
    {
        User::factory()->create(['name' => 'Findable Person']);
        User::factory()->create(['name' => 'Someone Else']);

        $this->actingAs($this->admin)
            ->get(route('admin.users.index', ['q' => 'findable']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/index')
                ->has('users.data', 1)
                ->where('users.data.0.name', 'Findable Person'));
    }

    public function test_a_new_user_gets_a_link_to_choose_a_password(): void
    {
        Notification::fake();
        $account = Account::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), [
                'name' => 'New Person',
                'email' => 'new@example.com',
                'locale' => '',
                'account_id' => $account->id,
                'account_admin' => true,
                'is_super_admin' => false,
            ])
            ->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'new@example.com')->sole();

        $this->assertFalse($user->isSuperAdmin());
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->isAdminOf($account));
        $this->assertSame($account->id, $user->active_account_id);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_it_updates_a_user_and_their_super_admin_flag(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.users.update', $user), [
                'name' => 'Renamed',
                'email' => $user->email,
                'locale' => 'nl',
                'is_super_admin' => true,
            ])
            ->assertRedirect(route('admin.users.show', $user));

        $user->refresh();
        $this->assertSame('Renamed', $user->name);
        $this->assertSame('nl', $user->locale?->value);
        $this->assertTrue($user->isSuperAdmin());
    }

    public function test_you_cannot_demote_yourself(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('admin.users.update', $this->admin), [
                'name' => $this->admin->name,
                'email' => $this->admin->email,
                'is_super_admin' => false,
            ])
            ->assertSessionHasErrors('is_super_admin');

        $this->assertTrue($this->admin->refresh()->isSuperAdmin());
    }

    public function test_it_deletes_a_user_but_not_their_accounts(): void
    {
        $user = User::factory()->withAccount()->create();
        $account = $user->accounts()->sole();

        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect(route('admin.users.index'));

        $this->assertModelMissing($user);
        $this->assertModelExists($account);
    }

    public function test_you_cannot_delete_yourself(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $this->admin))
            ->assertSessionHasErrors('user');

        $this->assertModelExists($this->admin);
    }
}
