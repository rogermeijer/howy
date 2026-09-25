<?php

namespace Tests\Feature\Tenancy;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_a_member_can_reach_a_tenant_route(): void
    {
        $account = Account::factory()->create();
        $user = User::factory()->withAccount($account)->create();

        $this->actingAs($user->refresh())
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_a_deleted_active_account_is_healed_to_a_remaining_membership(): void
    {
        $doomed = Account::factory()->create();
        $kept = Account::factory()->create();

        $user = User::factory()->withAccount($doomed)->create()->refresh();
        $kept->users()->attach($user, ['is_admin' => false]);

        $this->assertSame($doomed->id, $user->active_account_id);

        // nullOnDelete empties the pointer rather than leaving it dangling, so the
        // middleware has to pick the remaining membership up.
        $doomed->delete();

        $this->assertNull($user->refresh()->active_account_id);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $this->assertSame($kept->id, $user->refresh()->active_account_id);
    }

    public function test_an_active_account_the_user_no_longer_belongs_to_is_healed(): void
    {
        $kept = Account::factory()->create();
        $removed = Account::factory()->create();

        $user = User::factory()->withAccount($kept)->create()->refresh();

        // Simulate offboarding: the pointer still references the old account, but
        // the membership is gone. That is a normal event, not a lockout.
        $user->forceFill(['active_account_id' => $removed->id])->save();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $this->assertSame($kept->id, $user->refresh()->active_account_id);
    }

    public function test_a_user_without_any_account_is_forbidden_from_tenant_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }

    public function test_a_user_without_any_account_can_still_reach_their_profile(): void
    {
        $user = User::factory()->create();

        // Deliberate gap: someone with no membership must still be able to manage
        // their account and log out.
        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
    }
}
